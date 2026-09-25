"""
mdb_to_mysql.py - แปลงข้อมูลจาก Microsoft Access (.mdb) เป็น MySQL INSERT statements

วัตถุประสงค์:
    อ่านทุกตารางจากไฟล์ .mdb ผ่าน ODBC แล้ว generate ไฟล์ SQL
    ที่ import เข้า MySQL / MariaDB ได้โดยตรง

ขั้นตอนการใช้งาน:
    1. รัน: python mdb_to_mysql.py
    2. ไฟล์ output จะอยู่ที่ database/inventory_data.sql
    3. import ด้วย: mysql -u root -p biton_inventory < inventory_data.sql

หมายเหตุ: ต้องติดตั้ง Microsoft Access Database Engine ODBC Driver
    https://www.microsoft.com/en-us/download/details.aspx?id=54920
"""

# ─ Imports ────────────────────────────────────────────────────────────────────

import pyodbc
import os
import sys
import datetime
from pathlib import Path

# ให้ print ภาษาไทยออก terminal ได้ถูกต้องบน Windows
if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
if hasattr(sys.stderr, "reconfigure"):
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")

# ─ Constants ──────────────────────────────────────────────────────────────────

PYTHON_EXE  = r"C:\Users\admin\AppData\Local\Programs\Python\Python313\python.exe"
MDB_PATH    = r"C:\Users\admin\OneDrive\Music\www\inventory\_db\inventory.mdb"
OUTPUT_DIR  = Path(__file__).parent          # database/
OUTPUT_FILE = OUTPUT_DIR / "inventory_data.sql"

# ตารางที่ต้องการ export ตามลำดับ (FK dependency)
TARGET_TABLES = [
    "users",
    "categories_product",
    "product",
    "tbl_vendor",
    "part",
    "formula_name",
    "production_formula",
    "pre_stock",
    "log_stock",
    "setup_log",
    "setup_pd",
]

# ─ Helpers ────────────────────────────────────────────────────────────────────


def escape_mysql_string(value: str) -> str:
    """
    Escape สตริงให้ปลอดภัยสำหรับ MySQL INSERT

    แทนที่อักขระพิเศษที่อาจทำให้ SQL syntax ผิดพลาดหรือเกิด injection
    เนื่องจากเราควบคุมแหล่งข้อมูล (local MDB) จึงใช้ string escape แทน
    prepared statement เพื่อให้ได้ไฟล์ SQL ที่ import ได้ตรงๆ

    Parameters:
        value: สตริงต้นฉบับ

    Returns:
        สตริงที่ escape แล้ว พร้อมครอบด้วย single quotes
    """
    value = value.replace("\\", "\\\\")
    value = value.replace("'",  "\\'")
    value = value.replace("\r\n", "\\n")
    value = value.replace("\n",  "\\n")
    value = value.replace("\r",  "\\n")
    value = value.replace("\0",  "")
    return f"'{value}'"


def format_value(val) -> str:
    """
    แปลงค่าจาก Python type เป็น MySQL literal string

    Parameters:
        val: ค่าจาก pyodbc (อาจเป็น None, bool, int, float, str, datetime)

    Returns:
        MySQL literal เช่น NULL, 1, 'text', '2024-01-15 08:30:00'
    """
    if val is None:
        return "NULL"

    if isinstance(val, bool):
        # Access เก็บ Boolean เป็น -1/0 แต่เราแปลงเป็น 1/0 ให้ MySQL
        return "1" if val else "0"

    if isinstance(val, int):
        return str(val)

    if isinstance(val, float):
        # ป้องกัน scientific notation ที่ MySQL อาจตีความผิด
        return f"{val:.10g}"

    if isinstance(val, (datetime.datetime, datetime.date)):
        return f"'{val}'"

    if isinstance(val, datetime.time):
        return f"'{val}'"

    if isinstance(val, (bytes, bytearray)):
        # Binary/OLE Object — แปลงเป็น hex literal
        hex_str = val.hex()
        return f"0x{hex_str}"

    # ค่าที่เหลือทั้งหมด treat เป็น string
    return escape_mysql_string(str(val))


def get_connection(mdb_path: str) -> pyodbc.Connection:
    """
    สร้าง ODBC connection ไปยังไฟล์ .mdb

    Parameters:
        mdb_path: path ไปยังไฟล์ .mdb

    Returns:
        pyodbc.Connection object

    Raises:
        SystemExit: ถ้าไม่สามารถเชื่อมต่อได้
    """
    conn_str = (
        r"DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};"
        rf"DBQ={mdb_path};"
        r"PWD=;"
    )
    try:
        conn = pyodbc.connect(conn_str, autocommit=True)
        return conn
    except pyodbc.Error as e:
        print(f"[ERROR] เชื่อมต่อ MDB ไม่ได้: {e}")
        print("        ตรวจสอบว่าติดตั้ง Microsoft Access Database Engine แล้ว")
        sys.exit(1)


def get_tables(cursor: pyodbc.Cursor) -> list[str]:
    """
    ดึงรายชื่อตารางทั้งหมดจาก MDB (เฉพาะ TABLE ไม่รวม system table)

    Parameters:
        cursor: pyodbc.Cursor

    Returns:
        list ของชื่อตาราง
    """
    tables = []
    for row in cursor.tables(tableType="TABLE"):
        name = row.table_name
        # ข้าม Access system tables (MSys*)
        if not name.startswith("MSys"):
            tables.append(name)
    return tables


def get_columns(cursor: pyodbc.Cursor, table_name: str) -> list[str]:
    """
    ดึงชื่อคอลัมน์ทั้งหมดของตาราง

    ใช้ cursor.description หลัง SELECT TOP 0 แทน cursor.columns()
    เพราะ cursor.columns() ใน Access ODBC อาจเกิด UnicodeDecodeError
    กับ field metadata ที่มีอักขระพิเศษ

    Parameters:
        cursor:     pyodbc.Cursor
        table_name: ชื่อตาราง

    Returns:
        list ของชื่อคอลัมน์ หรือ [] ถ้าเกิดข้อผิดพลาด
    """
    try:
        cursor.execute(f"SELECT TOP 1 * FROM [{table_name}]")
        # ดึงชื่อคอลัมน์จาก description (index 0 = column_name)
        columns = [desc[0] for desc in cursor.description]
        # ล้าง resultset ที่เหลือ
        cursor.fetchall()
        return columns
    except pyodbc.Error as e:
        print(f"\n  [WARN] ดึงคอลัมน์ {table_name} ไม่ได้: {e}")
        return []


def export_table(cursor: pyodbc.Cursor, table_name: str, f) -> int:
    """
    Export ข้อมูลทั้งหมดในตารางเป็น MySQL INSERT statements

    เขียนลงไฟล์ f โดยตรง แบ่งเป็น batch ละ 500 แถว
    เพื่อให้ import เร็วขึ้นและไม่เกิด memory ปัญหา

    Parameters:
        cursor:     pyodbc.Cursor
        table_name: ชื่อตาราง
        f:          file object สำหรับเขียน SQL

    Returns:
        จำนวนแถวที่ export
    """
    BATCH_SIZE = 500

    columns = get_columns(cursor, table_name)
    if not columns:
        print(f"  [SKIP] {table_name} — ไม่พบคอลัมน์")
        return 0

    col_list = ", ".join(f"`{c}`" for c in columns)
    table_sql = f"`{table_name}`"

    # ดึงข้อมูลทั้งตาราง
    try:
        cursor.execute(f"SELECT * FROM [{table_name}]")
    except pyodbc.Error as e:
        print(f"  [ERROR] SELECT {table_name}: {e}")
        return 0

    rows = cursor.fetchall()
    total = len(rows)

    if total == 0:
        f.write(f"-- {table_name}: ไม่มีข้อมูล\n\n")
        return 0

    f.write(f"-- ─ {table_name} ({total:,} rows) {'─' * max(1, 60 - len(table_name))}\n\n")

    row_count = 0
    batch_values = []

    for row in rows:
        values = ", ".join(format_value(v) for v in row)
        batch_values.append(f"  ({values})")
        row_count += 1

        # flush ทุก BATCH_SIZE แถว
        if len(batch_values) >= BATCH_SIZE:
            f.write(f"INSERT INTO {table_sql} ({col_list}) VALUES\n")
            f.write(",\n".join(batch_values))
            f.write(";\n\n")
            batch_values = []

    # flush แถวที่เหลือ
    if batch_values:
        f.write(f"INSERT INTO {table_sql} ({col_list}) VALUES\n")
        f.write(",\n".join(batch_values))
        f.write(";\n\n")

    return row_count


# ─ Main ───────────────────────────────────────────────────────────────────────


def main():
    """
    Entry point: เชื่อมต่อ MDB, loop ทุกตาราง, สร้างไฟล์ SQL output

    Flow:
        1. ตรวจสอบไฟล์ MDB มีอยู่จริง
        2. เปิด ODBC connection
        3. หาตารางที่มีใน MDB และ map กับ TARGET_TABLES
        4. เขียน header SQL (SET NAMES, FOREIGN_KEY_CHECKS)
        5. Export แต่ละตารางเป็น INSERT statements
        6. เขียน footer และปิดไฟล์
    """
    if not os.path.exists(MDB_PATH):
        print(f"[ERROR] ไม่พบไฟล์: {MDB_PATH}")
        sys.exit(1)

    print(f"[INFO] เปิดไฟล์: {MDB_PATH}")
    conn   = get_connection(MDB_PATH)
    cursor = conn.cursor()

    # ดึงตารางทั้งหมดใน MDB
    mdb_tables_raw = get_tables(cursor)
    # normalise เป็น lowercase เพื่อ case-insensitive match
    mdb_tables_map = {t.lower(): t for t in mdb_tables_raw}

    print(f"[INFO] พบ {len(mdb_tables_raw)} ตารางใน MDB:")
    for t in mdb_tables_raw:
        print(f"       - {t}")

    print(f"\n[INFO] เขียน output -> {OUTPUT_FILE}\n")

    with open(OUTPUT_FILE, "w", encoding="utf-8") as f:

        # ─ Header ───────────────────────────────────────────────────────────
        f.write("-- =============================================================================\n")
        f.write(f"-- inventory_data.sql — generated by mdb_to_mysql.py\n")
        f.write(f"-- Source : {MDB_PATH}\n")
        f.write(f"-- Date   : {datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S')}\n")
        f.write("--\n")
        f.write("-- ขั้นตอน: รัน inventory_schema_access.sql ก่อน แล้วค่อย import ไฟล์นี้\n")
        f.write("-- =============================================================================\n\n")

        f.write("SET NAMES utf8mb4;\n")
        f.write("SET FOREIGN_KEY_CHECKS = 0;\n")
        f.write("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n")
        f.write("USE `biton_inventory`;\n\n")

        # ─ Export ตาม TARGET_TABLES ก่อน (ลำดับ FK) ──────────────────────
        exported = set()
        total_rows = 0

        for target in TARGET_TABLES:
            real_name = mdb_tables_map.get(target.lower())
            if real_name is None:
                print(f"  [SKIP] ไม่พบตาราง '{target}' ใน MDB")
                f.write(f"-- [SKIP] ไม่พบตาราง '{target}' ใน MDB\n\n")
                continue

            print(f"  [EXPORT] {real_name} ...", end=" ")
            rows = export_table(cursor, real_name, f)
            print(f"{rows:,} rows")
            total_rows += rows
            exported.add(real_name.lower())

        # ─ Export ตารางที่เหลือ (ไม่อยู่ใน TARGET_TABLES) ─────────────────
        extras = [t for t in mdb_tables_raw if t.lower() not in exported]
        if extras:
            f.write("-- ─ ตารางเพิ่มเติม (ไม่อยู่ใน schema หลัก) ──────────────────────────────\n\n")
            for tbl in extras:
                print(f"  [EXTRA] {tbl} ...", end=" ")
                rows = export_table(cursor, tbl, f)
                print(f"{rows:,} rows")
                total_rows += rows

        # ─ Footer ───────────────────────────────────────────────────────────
        f.write("SET FOREIGN_KEY_CHECKS = 1;\n")
        f.write(f"-- END — total rows exported: {total_rows:,}\n")

    cursor.close()
    conn.close()

    size_kb = OUTPUT_FILE.stat().st_size / 1024
    print(f"\n[DONE] {total_rows:,} rows → {OUTPUT_FILE.name} ({size_kb:,.1f} KB)")
    print(f"\nวิธี import:")
    print(f'  mysql -u biton_inventory -p biton_inventory < "{OUTPUT_FILE}"')
    print(f"  หรือเปิดไฟล์ใน phpMyAdmin แล้ว Import")


if __name__ == "__main__":
    main()
