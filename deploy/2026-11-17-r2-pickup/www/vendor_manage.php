<?php
/**
 * หน้าจัดการ Vendor — แสดงรายการและค้นหา
 *
 * ?ajax=1&search=... → JSON fragment (live search)
 * ปกติ             → full HTML page
 */

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/inventory_session_actor.php';

$h = static function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};

// ─ PK column ───────────────────────────────────────────────────────────────

function vendor_pk_column($conn)
{
    static $col = null;
    if ($col !== null) {
        return $col;
    }
    $r = mysqli_query($conn, 'SELECT `vid` FROM `tbl_vendor` LIMIT 1');
    $col = ($r !== false) ? 'vid' : 'vno';
    return $col;
}

$pkCol  = vendor_pk_column($connect);
$search = isset($_GET['search']) ? trim((string) $_GET['search']) : '';

// ─ Query ───────────────────────────────────────────────────────────────────

$params = array();
$types  = '';
$where  = '';

if ($search !== '') {
    $like   = '%' . $search . '%';
    $where  = 'WHERE `vname` LIKE ? OR `contactname` LIKE ? OR `tel1` LIKE ? OR `email` LIKE ?';
    $params = array($like, $like, $like, $like);
    $types  = 'ssss';
}

$sql = 'SELECT `' . $pkCol . '` AS vendor_id, `vname`, `type`, `contactname`,
               `tel1`, `email`, `Location`
        FROM `tbl_vendor` ' . $where . '
        ORDER BY `vname` ASC';

$stmt    = mysqli_prepare($connect, $sql);
$vendors = array();
if ($stmt) {
    if ($types !== '') {
        $bindArgs = array($stmt, $types);
        foreach ($params as $k => $dummy) {
            $bindArgs[] = &$params[$k];
        }
        call_user_func_array('mysqli_stmt_bind_param', $bindArgs);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $vendors[] = $row;
    }
    mysqli_stmt_close($stmt);
}

$totalVendors = count($vendors);

// ─ Shared: render results content (ใช้ทั้ง AJAX และ full page) ────────────

function vendor_render_results(array $vendors, $search, $h)
{
    $totalVendors = count($vendors);
    ob_start();
    if ($totalVendors === 0) : ?>
      <div style="padding:2.5rem 1.25rem; text-align:center; color:var(--inv-muted);">
        <i class="fa fa-inbox" style="font-size:2rem; display:block; margin-bottom:.75rem; opacity:.4;"></i>
        <?php echo $search !== '' ? 'ไม่พบ Vendor ที่ตรงกับ "' . $h($search) . '"' : 'ยังไม่มี Vendor ในระบบ'; ?>
      </div>
    <?php else : ?>
      <div class="inv-table-wrap">
        <table class="inv-table">
          <thead>
            <tr>
              <th class="inv-col-num">#</th>
              <th>ชื่อ Vendor</th>
              <th>ประเภท</th>
              <th>ผู้ติดต่อ</th>
              <th>โทรศัพท์</th>
              <th>อีเมล</th>
              <th class="inv-col-center">สาขา</th>
              <th class="inv-col-actions">แก้ไข</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($vendors as $i => $v) : ?>
            <tr>
              <td class="inv-col-num"><?php echo $i + 1; ?></td>
              <td>
                <a href="vendor_edit.php?id=<?php echo (int) $v['vendor_id']; ?>" class="inv-link-part">
                  <?php echo $h($v['vname']); ?>
                </a>
              </td>
              <td><?php echo $h((string) $v['type']); ?></td>
              <td><?php echo $h((string) $v['contactname']); ?></td>
              <td><?php echo $h((string) $v['tel1']); ?></td>
              <td class="inv-tbl-email"><?php echo $h((string) $v['email']); ?></td>
              <td class="inv-col-center">
                <?php if (trim((string) $v['Location']) !== '') : ?>
                  <span class="inv-tbl-loc"><?php echo $h((string) $v['Location']); ?></span>
                <?php endif; ?>
              </td>
              <td class="inv-col-actions">
                <a href="vendor_edit.php?id=<?php echo (int) $v['vendor_id']; ?>"
                   class="inv-btn inv-btn--edit" title="แก้ไข">
                  <i class="fa fa-pencil"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif;
    return ob_get_clean();
}

// ─ AJAX branch: คืน JSON ไม่แตะ HTML ──────────────────────────────────────

if (!empty($_GET['ajax'])) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array(
        'html'   => vendor_render_results($vendors, $search, $h),
        'count'  => $totalVendors,
        'search' => $search,
    ));
    exit;
}

// ─ Full page ───────────────────────────────────────────────────────────────

require_once __DIR__ . '/stock_flash.php';
$flash = stock_flash_consume();
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.6.3/css/font-awesome.min.css">
<link rel="stylesheet" type="text/css" href="css/smoothness/jquery-ui-1.7.2.custom.css">
<?php require_once __DIR__ . '/inventory_ui_head.php'; ?>
<title>จัดการ Vendor</title>
<script src="js/jquery-3.5.1.min.js"></script>
<style>
.vendor-search-wrap { display:flex; gap:.75rem; align-items:flex-end; flex-wrap:wrap; }
.vendor-search-wrap .inv-field { flex:1; min-width:220px; }
.vendor-count { font-size:.85rem; color:var(--inv-muted); }
.inv-btn--edit { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; font-size:.82rem; padding:.35rem .75rem; }
.inv-btn--edit:hover { background:#dbeafe; }
.inv-tbl-email { font-size:.82rem; color:var(--inv-muted); }
.inv-tbl-loc { display:inline-block; padding:.1rem .45rem; border-radius:999px; background:#f1f5f9; font-size:.78rem; color:var(--inv-muted); }
.flash-ok  { background:var(--inv-success-bg); color:var(--inv-success); border:1px solid #a7f3d0; padding:.75rem 1rem; border-radius:8px; margin-bottom:1rem; display:flex; align-items:center; gap:.5rem; }
.flash-err { background:var(--inv-danger-bg);  color:var(--inv-danger);  border:1px solid #fecaca; padding:.75rem 1rem; border-radius:8px; margin-bottom:1rem; display:flex; align-items:center; gap:.5rem; }
#vendor-results-wrap { transition: opacity .15s; }
#vendor-results-wrap.loading { opacity: .4; pointer-events: none; }
</style>
</head>
<body class="inv-app">
<?php require_once __DIR__ . '/title-h.php'; ?>

<main class="inv-main">
  <div class="inv-page-header">
    <h1 class="inv-page-title"><i class="fa fa-building-o"></i> จัดการ Vendor</h1>
    <p class="inv-page-desc">รายการ Vendor / ผู้จำหน่ายทั้งหมด — เพิ่มใหม่หรือแก้ไขข้อมูลได้ที่นี่</p>
  </div>

<?php if ($flash['message'] !== '') : ?>
  <div class="flash-<?php echo $flash['type'] === 'error' ? 'err' : 'ok'; ?>">
    <i class="fa fa-<?php echo $flash['type'] === 'error' ? 'times-circle' : 'check-circle'; ?>"></i>
    <?php echo $h($flash['message']); ?>
  </div>
<?php endif; ?>

  <!-- ─ Filter + ปุ่มเพิ่ม ──────────────────────────────────────────────── -->
  <div class="inv-card" style="margin-bottom:1rem;">
    <div class="inv-card__body">
      <form method="get" action="vendor_manage.php" id="form_search">
        <div class="vendor-search-wrap">
          <div class="inv-field">
            <label for="search">ค้นหา Vendor</label>
            <input type="text" id="search" name="search" class="inv-input"
              placeholder="ชื่อ, ผู้ติดต่อ, โทรศัพท์ หรืออีเมล"
              value="<?php echo $h($search); ?>" autocomplete="off">
          </div>
          <div class="inv-filters__actions">
            <button type="submit" class="inv-btn inv-btn--primary">
              <i class="fa fa-search"></i> ค้นหา
            </button>
            <a href="vendor_manage.php" class="inv-btn inv-btn--ghost" id="btn_clear"
               style="<?php echo $search === '' ? 'display:none' : ''; ?>">
              <i class="fa fa-times"></i> ล้าง
            </a>
          </div>
          <div style="margin-left:auto;">
            <a href="vendor_add.php" class="inv-btn inv-btn--primary">
              <i class="fa fa-plus"></i> เพิ่ม Vendor ใหม่
            </a>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- ─ ตาราง ───────────────────────────────────────────────────────────── -->
  <div class="inv-card">
    <div class="inv-card__head">
      <i class="fa fa-list"></i> รายการ Vendor
      <span id="vendor-count-label" class="vendor-count" style="margin-left:.5rem;">
        (<?php echo $totalVendors; ?> รายการ<?php echo $search !== '' ? ' — ผลการค้นหา "' . $h($search) . '"' : ''; ?>)
      </span>
    </div>
    <div id="vendor-results-wrap" class="inv-card__body--flush">
      <?php echo vendor_render_results($vendors, $search, $h); ?>
    </div>
  </div>
</main>

<script>
$(function () {
    var searchTimer = null;
    var $input      = $('#search');
    var $wrap       = $('#vendor-results-wrap');
    var $counter    = $('#vendor-count-label');
    var $btnClear   = $('#btn_clear');

    function fetchResults(term) {
        $wrap.addClass('loading');
        $.getJSON('vendor_manage.php', { ajax: '1', search: term })
            .done(function (data) {
                $wrap.html(data.html);
                var label = data.count + ' รายการ';
                if (data.search !== '') {
                    label += ' — ผลการค้นหา “'
                           + $('<span>').text(data.search).html() + '”';
                }
                $counter.text('(' + label + ')');
                $btnClear.toggle(data.search !== '');
            })
            .always(function () {
                $wrap.removeClass('loading');
            });
    }

    $input.on('input', function () {
        clearTimeout(searchTimer);
        var term = $(this).val();
        searchTimer = setTimeout(function () {
            fetchResults(term);
        }, 350);
    });

    // ล้างแบบ live ทันที
    $btnClear.on('click', function (e) {
        e.preventDefault();
        $input.val('').trigger('input');
    });
});
</script>
</body>
</html>
