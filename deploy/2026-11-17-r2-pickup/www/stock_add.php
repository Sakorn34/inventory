<?php
/**
 * หน้ารับของเข้า Stock (นำเข้า)
 *
 * กรอกวันที่ Vendor และรายการ Product/Part หลายแถว แล้วส่ง stock_save.php
 * ใช้ธีม inv-app / inventory-ui.css
 */
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/inventory_session_actor.php';
require_once __DIR__ . '/stock_flash.php';

$stockAddFlash = stock_flash_consume();
header('Content-Type: text/html; charset=UTF-8');
@setlocale(LC_TIME, 'th_TH.UTF-8', 'th_TH', 'tha');

$h = static function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};

$productIds = array();
$productNames = array();
$sqlSP = 'SELECT pd_id, pd_name FROM product ORDER BY pd_name ASC';
$exeSP = db_exec($connect, $sqlSP);
if ($exeSP) {
    while ($resultSP = db_fetch_array($exeSP)) {
        $productIds[] = $resultSP['pd_id'];
        $productNames[] = $resultSP['pd_name'];
    }
}
$productCount = count($productIds);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.6.3/css/font-awesome.min.css">
<link rel="stylesheet" type="text/css" href="css/smoothness/jquery-ui-1.7.2.custom.css">
<?php require_once __DIR__ . '/inventory_ui_head.php'; ?>

<title>เพิ่ม Stock</title>
<script type="text/javascript" src="js/jquery-1.3.2.min.js"></script>  
<script type="text/javascript" src="js/jquery-ui-1.7.2.custom.min.js"></script>  
<script type="text/javascript" src="js/jquery.maskedinput-1.2.1.js"></script>
<script type="text/javascript" src="js/jquery-3.5.1.min.js"></script>
<script type="text/javascript" src="js/jquery-add_object.min.js"></script>

<script type="text/javascript">

function save_job(){
	  // document.getElementById("form1").submit();

	var count = document.getElementById("count_sp").value;
	if(count == 0){
		alert("ยังไม่มีรายการ กรุณากด add ก่อนบันทึก " + count);
		return;
	}
	
	var vendorInput = document.getElementById("vendor_com");
	if(vendorInput.value === "") {
		alert("กรุณากรอกชื่อ Vendor");
		vendorInput.focus();
		return;
	}
	if(vendorInput.getAttribute("data-confirmed") !== "1") {
		alert("กรุณาเลือก Vendor จากรายการ (พิมพ์ค้นหาแล้วคลิกเลือก)\nหากยังไม่มีในระบบ ให้เพิ่มที่เมนู Vendor ก่อน");
		vendorInput.focus();
		return;
	}
	if(document.getElementById("log_by").value == "" ){ 
		alert("ไม่พบชื่อผู้บันทึกจาก session — กรุณาเข้าสู่ระบบ SSO ใหม่");
		return;
	}

	for(var a = 1; a <= count; a++){
		if(document.getElementById("pd_sp"+a).value == "" || document.getElementById("sn_sp"+a).value == ""
			|| document.getElementById("amount_sp"+a).value == "" || document.getElementById("price_sp"+a).value == ""){
			alert("กรุณากรอกข้อมูลให้ครบถ้วน");
			return;
		}
	}

	document.getElementById("form_add_stock").submit();
	}

// ค้นหา Vendor — ต้องเลือกจาก tbl_vendor เท่านั้น (ไม่อนุญาตพิมพ์ชื่อใหม่)
$(function () {
    var $vendorInput = $("#vendor_com");

    function stockAddVendorClearDropdown($input) {
        $input.siblings(".result").empty();
    }

    $vendorInput.on("keyup input", function () {
        var $input = $(this);
        // การพิมพ์ใดๆ ถือว่ายังไม่ได้เลือก
        $input.removeClass("stock-add-vendor--locked").attr("data-confirmed", "0");
        var inputVal = $input.val();
        var resultDropdown = $input.siblings(".result");
        if (inputVal.length) {
            $.get("search_cusname.php", {term: inputVal}).done(function (data) {
                resultDropdown.html(data);
            });
        } else {
            resultDropdown.empty();
        }
    });

    $vendorInput.on("keydown", function (e) {
        // ปิด dropdown ด้วย Escape; Enter ไม่มีผล (ไม่ lock ชื่อที่พิมพ์เอง)
        if (e.key === "Escape") {
            stockAddVendorClearDropdown($(this));
        }
        if (e.key === "Enter" || e.which === 13) {
            e.preventDefault();
        }
    });

    $(document).on("click", ".search-box-user .result p", function () {
        var $input = $(this).parents(".search-box-user").find('input[type="text"]');
        $input.val($(this).text());
        stockAddVendorClearDropdown($input);
        // ยืนยันว่าเลือกจากรายการแล้ว
        $input.addClass("stock-add-vendor--locked").attr("data-confirmed", "1");
    });
});

//Search ����ѷ û�.
$(document.getElementById("security_company")).ready(function(){

    $('.search-box-sec input[type="text"]').on("keyup input", function(){
        /* Get input value on change */
        var inputVal = $(this).val();
        var resultDropdown = $(this).siblings(".result_sec");
        if(inputVal.length){
            $.get("search_cusname.php", {term: inputVal}).done(function(data){
                resultDropdown.html(data);  // Display the returned data in browser
            });
        } else{  resultDropdown.empty(); }
    });
    // Set search input value on click of result item
    $(document).on("click", ".result_sec p", function(){
        $(this).parents(".search-box-sec").find('input[type="text"]').val($(this).text());
        $(this).parent(".result_sec").empty();
    });
});

//select s/n spare 1
$(document.getElementById("pd_sp1")).ready(function(){
     $("select#pd_sp1").change(function(){ $.get("list_part.php",{ pd_sp1:$(this).val() },
		 function(data) { $("select#sn_sp1").html(data);  });
      }); 
});
//select s/n spare 2
$(document.getElementById("pd_sp2")).ready(function(){
     $("select#pd_sp2").change(function(){ $.get("list_part.php",{ pd_sp2:$(this).val() },
		 function(data) { $("select#sn_sp2").html(data);  });
      }); 
});
//select s/n spare 3
$(document.getElementById("pd_sp3")).ready(function(){
     $("select#pd_sp3").change(function(){ $.get("list_part.php",{ pd_sp3:$(this).val() },
		 function(data) { $("select#sn_sp3").html(data);  });
      }); 
});
//select s/n spare 4
$(document.getElementById("pd_sp4")).ready(function(){
     $("select#pd_sp4").change(function(){ $.get("list_part.php",{ pd_sp4:$(this).val() },
		 function(data) { $("select#sn_sp4").html(data);  });
      }); 
});
//select s/n spare 5
$(document.getElementById("pd_sp5")).ready(function(){
     $("select#pd_sp5").change(function(){ $.get("list_part.php",{ pd_sp5:$(this).val() },
		 function(data) { $("select#sn_sp5").html(data);  });
      }); 
});

// Add Product Maintenance
$(document).ready(function() {
    var max_fields = 10;
    var wrapper = $(".container_maintenance");
    var add_button = $(".add_form_field");

    var x = 1;
    $(add_button).click(function(e) {
        e.preventDefault();
        if (x < max_fields) {
            x++;
            $(wrapper).append('<div><input type="text" name="mytext[]"/><a href="#" class="delete">Delete</a></div>'); //add input box
        } else {
            alert('You Reached the limits')
        }
    });

    $(wrapper).on("click", ".delete", function(e) {
        e.preventDefault();
        $(this).parent('div').remove();
        x--;
    })
}); 

function checkEmpty(){

	var count_sp= document.getElementById("count_sp").value;

	if(count_sp == 0){
		alert("ยังไม่มีรายการ กรุณากด add ก่อนบันทึก " + count_sp);
		return;
	}
}

var r_spare = 0;

function stockAddClearRow(n) {
	var pd = document.getElementById("pd_sp" + n);
	var sn = document.getElementById("sn_sp" + n);
	var amount = document.getElementById("amount_sp" + n);
	var price = document.getElementById("price_sp" + n);
	var remarks = document.getElementById("remarks_sp" + n);
	if (pd) { pd.value = ""; }
	if (sn) { sn.innerHTML = '<option value="">-- เลือก part --</option>'; }
	if (amount) { amount.value = ""; }
	if (price) { price.value = ""; }
	if (remarks) { remarks.value = ""; }
}

function stockAddShowRow(n, show) {
	var row = document.getElementById("stock_add_row_" + n);
	if (row) {
		row.style.display = show ? "table-row" : "none";
	}
}

function addSpare(mode) {
	if (r_spare >= 5 && mode === "add") {
		alert("เพิ่มรายการได้สูงสุด 5 รายการ");
		return;
	}

	$('#log_type').val("นำเข้า");

	if (mode === "add") {
		if (r_spare >= 1 && (document.getElementById("pd_sp" + r_spare).value === ""
			|| document.getElementById("sn_sp" + r_spare).value === ""
			|| document.getElementById("amount_sp" + r_spare).value === ""
			|| document.getElementById("price_sp" + r_spare).value === "")) {
			alert("กรุณากรอกข้อมูลก่อนคลิกถัดไป");
			return;
		}
		r_spare += 1;
		if (r_spare >= 5) {
			r_spare = 5;
		}
		stockAddClearRow(r_spare);
		stockAddShowRow(r_spare, true);
		$('#count_sp').val(r_spare);
	} else {
		if (r_spare <= 0) {
			r_spare = 0;
			return;
		}
		stockAddClearRow(r_spare);
		stockAddShowRow(r_spare, false);
		if (r_spare >= 1) {
			r_spare -= 1;
		}
		$('#count_sp').val(r_spare);
	}
}

</script>
</head>
<body class="inv-app stock-add">
<?php require __DIR__ . '/title-h.php'; ?>

<main class="inv-main">
  <header class="inv-page-header">
    <h1 class="inv-page-title">เพิ่ม Stock</h1>
    <p class="inv-page-desc">บันทึกของเข้าคลัง — กรอกวันที่ Vendor แล้วเพิ่มรายการ Product / Part</p>
  </header>
  <?php if ($stockAddFlash['message'] !== '') { ?>
  <div class="inv-flash-message <?php echo $h($stockAddFlash['type']); ?>" id="stock_add_flash" role="alert">
    <?php echo $h($stockAddFlash['message']); ?>
  </div>
  <?php } ?>

  <form id="form_add_stock" name="form_add_stock" method="post" action="stock_save.php">
    <input type="hidden" id="count_sp" name="count_sp" value="0" />
    <input type="hidden" id="log_type" name="log_type" value="" />

    <section class="inv-card stock-add-card">
      <div class="inv-card__head"><i class="fa fa-truck"></i> หัวเอกสาร</div>
      <div class="inv-card__body">
        <div class="stock-add-head-grid">
          <div class="inv-field">
            <label for="log_date">วันที่</label>
            <input class="inv-input" type="date" name="log_date" id="log_date" value="<?php echo $h(date('Y-m-d')); ?>" />
          </div>
          <div class="inv-field stock-add-vendor-field">
            <label for="vendor_com">ร้าน Vendor</label>
            <div class="inv-search-box search-box-user">
              <input class="inv-input" type="text" autocomplete="off" placeholder="พิมพ์เพื่อค้นหา Vendor..." name="vendor_com" id="vendor_com" required />
              <div class="result inv-search-box__result"></div>
            </div>
            <p class="inv-field__hint">พิมพ์เพื่อค้นหา แล้วคลิกเลือกจากรายการ — หากยังไม่มี <a href="vendor_manage.php" target="_blank">เพิ่ม Vendor ใหม่</a></p>
          </div>
        </div>
      </div>
    </section>

    <section class="inv-card stock-add-lines-card">
      <div class="inv-card__head stock-add-lines-head">
        <span><i class="fa fa-list"></i> รายการของเข้า</span>
        <div class="stock-add-lines-toolbar">
          <button type="button" class="inv-btn inv-btn--ghost stock-add-toolbar-btn" onclick="addSpare('del'); return false;"><i class="fa fa-minus-circle"></i> ลบแถว</button>
          <button type="button" class="inv-btn inv-btn--primary stock-add-toolbar-btn" onclick="addSpare('add'); return false;"><i class="fa fa-plus-circle"></i> เพิ่มแถว</button>
        </div>
      </div>
      <div class="inv-card__body inv-card__body--flush">
        <div class="inv-table-wrap">
          <table class="inv-table stock-add-table" id="table2">
            <thead>
              <tr>
                <th class="inv-col-num">No.</th>
                <th>Product</th>
                <th>Part</th>
                <th class="inv-col-num">จำนวน</th>
                <th class="inv-col-num">ราคา</th>
                <th>หมายเหตุ</th>
              </tr>
            </thead>
            <tbody>
              <?php for ($line = 1; $line <= 5; $line++) { ?>
              <tr class="stock-add-line" id="stock_add_row_<?php echo (int) $line; ?>" style="display:none">
                <td class="inv-col-num"><span id="number_sp<?php echo (int) $line; ?>"><?php echo (int) $line; ?></span></td>
                <td>
                  <select class="inv-select stock-add-line__control" id="pd_sp<?php echo (int) $line; ?>" name="pd_sp<?php echo (int) $line; ?>">
                    <option value="">-- เลือก Product --</option>
                    <?php for ($a = 0; $a < $productCount; $a++) { ?>
                    <option value="<?php echo $h($productIds[$a]); ?>"><?php echo $h($productNames[$a]); ?></option>
                    <?php } ?>
                  </select>
                </td>
                <td>
                  <select class="inv-select stock-add-line__control" name="sn_sp<?php echo (int) $line; ?>" id="sn_sp<?php echo (int) $line; ?>">
                    <option value="">-- เลือก part --</option>
                  </select>
                </td>
                <td class="inv-col-num">
                  <input class="inv-input stock-add-line__qty" type="number" name="amount_sp<?php echo (int) $line; ?>" id="amount_sp<?php echo (int) $line; ?>" min="0" />
                </td>
                <td class="inv-col-num">
                  <input class="inv-input stock-add-line__price" type="number" name="price_sp<?php echo (int) $line; ?>" id="price_sp<?php echo (int) $line; ?>" min="0" step="any" inputmode="decimal" />
                </td>
                <td>
                  <input class="inv-input" type="text" name="remarks_sp<?php echo (int) $line; ?>" id="remarks_sp<?php echo (int) $line; ?>" autocomplete="off" />
                </td>
              </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
        <p class="stock-add-lines-hint">กด «เพิ่มแถว» เพื่อกรอกรายการ (สูงสุด 5 แถว)</p>
      </div>
      <div class="inv-card__footer stock-add-footer">
        <?php inventory_render_recorder_field('log_by', 'log_by', 'ผู้บันทึก', 'stock-add-footer__by'); ?>
        <button type="button" class="inv-btn inv-btn--primary" onclick="save_job();"><i class="fa fa-save"></i> บันทึก</button>
      </div>
    </section>
  </form>
</main>
</body>
</html>

