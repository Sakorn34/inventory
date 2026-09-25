"""
split_inventory_data.py — แยก inventory_data.sql เป็นหลายไฟล์สำหรับ import บน hosting

วัตถุประสงค์:
    phpMyAdmin มักจำกัด upload ~2 MB — ไฟล์ data รวม ~7 MB
    แยกตามตาราง และแบ่ง pre_stock / log_stock เป็นชิ้นละไม่เกิน max bytes

Flow:
    1. อ่าน database/inventory_data.sql
    2. เขียนไฟล์ลง database/data_parts/
    3. สร้าง IMPORT_ORDER.txt

ตัวอย่าง:
    python database/split_inventory_data.py
    python database/split_inventory_data.py 1200000
"""

# ─ Imports ────────────────────────────────────────────────────────────────────

import re
import sys
from datetime import datetime
from pathlib import Path

# ─ Constants ──────────────────────────────────────────────────────────────────

ROOT = Path(__file__).resolve().parent.parent
SOURCE = ROOT / "database" / "inventory_data.sql"
OUTPUT_DIR = ROOT / "database" / "data_parts"
DEFAULT_MAX_BYTES = 1_500_000

PREAMBLE = """SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

USE `biton_inventory`;

"""

FOOTER = "SET FOREIGN_KEY_CHECKS = 1;\n"


# ─ Helpers ────────────────────────────────────────────────────────────────────


def safe_filename(name: str) -> str:
    """แปลงชื่อ section เป็นชื่อไฟล์"""
    name = re.sub(r"\s*\(.+\)\s*$", "", name)
    name = re.sub(r"[^a-zA-Z0-9_\-]+", "_", name).strip("_")
    return name.lower() or "section"


def extract_statements(content: str) -> list[str]:
    """แยกคำสั่ง SQL สมบูรณ์จากเนื้อหา section"""
    statements: list[str] = []
    buffer: list[str] = []

    for line in content.splitlines():
        trimmed = line.strip()
        if not trimmed or trimmed.startswith("--"):
            continue
        buffer.append(line)
        if line.rstrip().endswith(";"):
            stmt = "\n".join(buffer).strip().rstrip(";").strip()
            if stmt:
                statements.append(stmt + ";")
            buffer = []

    if buffer:
        statements.append("\n".join(buffer).strip())

    return statements


def chunk_statements(table_comment: str, statements: list[str], max_bytes: int) -> list[str]:
    """แยก statements เป็นชิ้นตามขนาด"""
    chunks: list[str] = []
    current = table_comment + "\n\n"
    current_bytes = len(current.encode("utf-8"))

    for sql in statements:
        add = sql + "\n\n"
        add_bytes = len(add.encode("utf-8"))

        if current_bytes + add_bytes > max_bytes and current_bytes > len((table_comment + "\n\n").encode("utf-8")):
            chunks.append(current.rstrip() + "\n")
            current = table_comment + " (ต่อ)\n\n"
            current_bytes = len(current.encode("utf-8"))

        current += add
        current_bytes += add_bytes

    if current.strip():
        chunks.append(current.rstrip() + "\n")

    return chunks


def parse_sections(raw: str) -> list[tuple[str, str]]:
    """แยกไฟล์ SQL เป็น section ตาม comment หัวตาราง"""
    sections: list[tuple[str, str]] = []
    current_name = "_preamble"
    current_lines: list[str] = []

    for line in raw.splitlines():
        m = re.match(r"^-- ─ (.+?) ─", line)
        if m:
            if current_lines:
                sections.append((current_name, "\n".join(current_lines)))
            current_name = m.group(1).strip()
            current_lines = [line]
            continue

        if line.strip().startswith("SET FOREIGN_KEY_CHECKS = 1"):
            if current_lines:
                sections.append((current_name, "\n".join(current_lines)))
                current_lines = []
            continue

        if line.strip().startswith("-- END"):
            continue

        current_lines.append(line)

    if current_lines:
        sections.append((current_name, "\n".join(current_lines)))

    return sections


def run(max_bytes: int) -> int:
    """
    แยก inventory_data.sql

    Parameters:
        max_bytes: ขนาดสูงสุดต่อไฟล์

    Returns:
        exit code 0 สำเร็จ, 1 ล้มเหลว
    """
    if not SOURCE.exists():
        print(f"ไม่พบไฟล์: {SOURCE}")
        return 1

    raw = SOURCE.read_text(encoding="utf-8-sig")
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)

    written: list[tuple[str, int, str]] = []
    seq = 0

    for name, body in parse_sections(raw):
        if name == "_preamble":
            continue

        body = body.strip()
        table_comment = ""
        cm = re.search(r"^(-- ─ .+)$", body, re.MULTILINE)
        if cm:
            table_comment = cm.group(1)
            body = re.sub(r"^-- ─ .+\n?", "", body, count=1).strip()

        statements = extract_statements(body)
        if not statements:
            continue

        section_bytes = len(body.encode("utf-8"))

        if section_bytes <= max_bytes:
            seq += 1
            fname = f"{seq:02d}_{safe_filename(name)}.sql"
            content = PREAMBLE + table_comment + "\n\n" + "\n\n".join(statements) + "\n"
            (OUTPUT_DIR / fname).write_text(content, encoding="utf-8")
            written.append((fname, len(content.encode("utf-8")), name))
            continue

        chunks = chunk_statements(table_comment, statements, max_bytes - len(PREAMBLE.encode("utf-8")))
        for part, chunk in enumerate(chunks, start=1):
            seq += 1
            fname = f"{seq:02d}_{safe_filename(name)}_part{part:02d}.sql"
            content = PREAMBLE + chunk
            (OUTPUT_DIR / fname).write_text(content, encoding="utf-8")
            written.append((fname, len(content.encode("utf-8")), f"{name} part {part}"))

    seq += 1
    footer_name = f"{seq:02d}_footer.sql"
    footer_content = PREAMBLE + FOOTER
    (OUTPUT_DIR / footer_name).write_text(footer_content, encoding="utf-8")
    written.append((footer_name, len(footer_content.encode("utf-8")), "footer"))

    order_lines = [
        "# ลำดับ import ใน phpMyAdmin (หลัง inventory_schema_access.sql)",
        f"# สร้างเมื่อ: {datetime.now():%Y-%m-%d %H:%M:%S}",
        f"# แหล่ง: {SOURCE.name}",
        "",
    ]
    for fname, size, label in written:
        order_lines.append(f"{fname}  ({size:,} bytes)  — {label}")

    (OUTPUT_DIR / "IMPORT_ORDER.txt").write_text("\n".join(order_lines) + "\n", encoding="utf-8")

    print(f"แยกไฟล์สำเร็จ: {len(written)} ไฟล์")
    print(f"โฟลเดอร์: {OUTPUT_DIR}")
    print(f"max ต่อไฟล์: {max_bytes:,} bytes")
    print("ดูลำดับ import: database/data_parts/IMPORT_ORDER.txt")
    for fname, size, label in written:
        print(f"  {fname} — {size:,} bytes")

    return 0


if __name__ == "__main__":
    limit = DEFAULT_MAX_BYTES
    if len(sys.argv) > 1 and sys.argv[1].isdigit():
        limit = int(sys.argv[1])
    raise SystemExit(run(limit))
