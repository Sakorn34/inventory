<?php
/**
 * หน้าเพิ่ม Part ใหม่ในระบบ inventory
 *
 * แสดงฟอร์มบันทึกข้อมูล Part, Product, สต็อก และการจัดซื้อ แล้วส่งไป part_save.php
 * ใช้ธีม inv-app / inventory-ui.css ให้สอดคล้องหน้าหลักอื่น
 */
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/inventory_session_actor.php';
header('Content-Type: text/html; charset=UTF-8');
@setlocale(LC_TIME, 'th_TH.UTF-8', 'th_TH', 'tha');

$h = static function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};

$array = array();
$sqlSP = 'SELECT pd_name FROM product ORDER BY pd_name ASC';
$exeSP = db_exec($connect, $sqlSP);
if ($exeSP) {
    while ($resultSP = db_fetch_array($exeSP)) {
        $array[] = $resultSP['pd_name'];
    }
}
$arrLength = count($array);

$arrayVD = array();
$sqlVD = 'SELECT vname FROM tbl_vendor ORDER BY vname ASC';
$exeVD = db_exec($connect, $sqlVD);
if ($exeVD) {
    while ($resultVD = db_fetch_array($exeVD)) {
        $arrayVD[] = $resultVD['vname'];
    }
}
$arrLengthVD = count($arrayVD);

$arrayCt = array();
$arrayCt_id = array();
$sqlCt = 'SELECT ct_id, ct_name FROM categories_product ORDER BY ct_name ASC';
$exeCt = db_exec($connect, $sqlCt);
if ($exeCt) {
    while ($resultCt = db_fetch_array($exeCt)) {
        $arrayCt_id[] = $resultCt['ct_id'];
        $arrayCt[] = $resultCt['ct_name'];
    }
}
$arrLengthCt = count($arrayCt);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.6.3/css/font-awesome.min.css">
<link rel="stylesheet" type="text/css" href="css/smoothness/jquery-ui-1.7.2.custom.css">
<?php require_once __DIR__ . '/inventory_ui_head.php'; ?>
<title>เพิ่มข้อมูล Part</title>
<script type="text/javascript" src="js/jquery-1.3.2.min.js"></script>  
<script type="text/javascript" src="js/jquery-ui-1.7.2.custom.min.js"></script>  
<script type="text/javascript" src="js/jquery.maskedinput-1.2.1.js"></script>
<script type="text/javascript" src="js/jquery-3.5.1.min.js"></script>
<script type="text/javascript" src="js/jquery-add_object.min.js"></script>

<script type="text/javascript">

function save_job(){
//	document.getElementById("form_part").submit();
	
		if(document.getElementById("part_name").value == ""){ 
			alert("กรุณากรอกข้อมูลให้ครบถ้วน");
			document.getElementById("part_name").focus();
			return;
		}
		
		if(document.getElementById("part_amount").value == ""){ 
			alert("กรุณากรอกข้อมูลให้ครบถ้วน");
			document.getElementById("part_amount").focus();
			return;
		}

		if(document.getElementById("part_min_alert").value == ""){ 
			alert("กรุณากรอกข้อมูลให้ครบถ้วน");
			document.getElementById("part_min_alert").focus();
			return;
		}

		if(document.getElementById("part_price").value == ""){ 
			alert("กรุณากรอกข้อมูลให้ครบถ้วน");
			document.getElementById("part_price").focus();
			return;
		}

		if(document.getElementById("part_by").value == ""){ 
			alert("ไม่พบชื่อผู้บันทึกจาก session — กรุณาเข้าสู่ระบบ SSO ใหม่");
			return;
		}

		document.getElementById("form_part").submit();
					
	}

//Search ?????  Enduser / ????????
$(document.getElementById("user_company")).ready(function(){
    $('.search-box-user input[type="text"]').on("keyup input", function(){
        /* Get input value on change */
        var inputVal = $(this).val();
        var resultDropdown = $(this).siblings(".result");
        if(inputVal.length){
            $.get("search_cusname.php", {term: inputVal}).done(function(data){
                resultDropdown.html(data);  // Display the returned data in browser
            });
        } else{  resultDropdown.empty(); }
	});
	
    // Set search input value on click of result item
    $(document).on("click", ".result p", function(){
        $(this).parents(".search-box-user").find('input[type="text"]').val($(this).text());
        $(this).parent(".result").empty();
    });
});

//Search ????? ??.
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
     $("select#pd_sp1").change(function(){ $.get("list_sn_spare.php",{ pd_sp1:$(this).val() },
		 function(data) { $("select#sn_sp1").html(data);  });
      }); 
});
//select s/n spare 2
$(document.getElementById("pd_sp2")).ready(function(){
     $("select#pd_sp2").change(function(){ $.get("list_sn_spare.php",{ pd_sp2:$(this).val() },
		 function(data) { $("select#sn_sp2").html(data);  });
      }); 
});
//select s/n spare 3
$(document.getElementById("pd_sp3")).ready(function(){
     $("select#pd_sp3").change(function(){ $.get("list_sn_spare.php",{ pd_sp3:$(this).val() },
		 function(data) { $("select#sn_sp3").html(data);  });
      }); 
});
//select s/n spare 4
$(document.getElementById("pd_sp4")).ready(function(){
     $("select#pd_sp4").change(function(){ $.get("list_sn_spare.php",{ pd_sp4:$(this).val() },
		 function(data) { $("select#sn_sp4").html(data);  });
      }); 
});
//select s/n spare 5
$(document.getElementById("pd_sp5")).ready(function(){
     $("select#pd_sp5").change(function(){ $.get("list_sn_spare.php",{ pd_sp5:$(this).val() },
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

var r_spare = 0;
//Add ??????????????
function addSpare(mode) {


			if(r_spare >= 5 && mode ==  "add"){
				alert("เพิ่มได้สูงสุด 5 รายการ");
				return;
			}
			
			if(mode == "add"){

				if(r_spare >= 1 && (document.getElementById("pd_sp"+r_spare).value == "" || document.getElementById("sn_sp"+r_spare).value == "")){
					alert("กรุณากรอกข้อมูลก่อนคลิกถัดไป");
					return;
				}
				r_spare += 1;

				if(r_spare >= 5){
					r_spare = 5;
				}
				//number_sp1
				document.getElementById("number_sp"+r_spare).style.display = '';
				//pd_sp1
				document.getElementById("pd_sp"+r_spare).style.display = '';
				document.getElementById("pd_sp"+r_spare).value = "";
				//sn_sp1
				document.getElementById("sn_sp"+r_spare).style.display = '';
				document.getElementById("sn_sp"+r_spare).value = ""; 
			
			}else{
				if(r_spare <= 0){
					r_spare = 0;
					return;
				}
				//number_sp1
				document.getElementById("number_sp"+r_spare).style.display = 'none';
				//pd_sp1
				document.getElementById("pd_sp"+r_spare).style.display = 'none';
				document.getElementById("pd_sp"+r_spare).value = "";
				//sn_sp1
				document.getElementById("sn_sp"+r_spare).style.display = 'none';
				document.getElementById("sn_sp"+r_spare).value = 0;
				if(r_spare >= 1){
					r_spare -= 1;
				}
			}	
}

</script>
</head>
<body class="inv-app part-add">
<?php require __DIR__ . '/title-h.php'; ?>

<main class="inv-main">
  <header class="inv-page-header">
    <h1 class="inv-page-title">เพิ่ม Part</h1>
    <p class="inv-page-desc">กรอกข้อมูล Part, สต็อก และแหล่งจัดซื้อ แล้วกดบันทึก</p>
  </header>

  <form id="form_part" name="form_part" method="post" action="part_save.php">
    <input type="hidden" name="status" value="insert" />

    <section class="inv-card part-add-card">
      <div class="inv-card__head"><i class="fa fa-cube"></i> รายละเอียด Part</div>
      <div class="inv-card__body">
        <h2 class="part-add-section-title">ข้อมูลหลัก</h2>
        <div class="part-add-grid">
          <div class="inv-field">
            <label for="part_name">ชื่อ Part</label>
            <input class="inv-input" type="text" name="part_name" id="part_name" autocomplete="off" />
          </div>
          <div class="inv-field">
            <label for="part_stock_no">Stock No.</label>
            <input class="inv-input" type="text" name="part_stock_no" id="part_stock_no" autocomplete="off" />
          </div>
          <div class="inv-field">
            <label for="part_product">Product</label>
            <select class="inv-select" id="part_product" name="part_product">
              <option value="">-- เลือก --</option>
              <?php for ($a = 0; $a < $arrLength; $a++) { ?>
              <option value="<?php echo $h($array[$a]); ?>"><?php echo $h($array[$a]); ?></option>
              <?php } ?>
            </select>
          </div>
          <div class="inv-field">
            <label for="part_categories">Categories</label>
            <select class="inv-select" id="part_categories" name="part_categories">
              <option value="">-- เลือก --</option>
              <?php for ($a = 0; $a < $arrLengthCt; $a++) { ?>
              <option value="<?php echo $h($arrayCt_id[$a]); ?>"><?php echo $h($arrayCt[$a]); ?></option>
              <?php } ?>
            </select>
          </div>
          <div class="inv-field">
            <label for="part_amount">จำนวน Stock</label>
            <input class="inv-input" type="number" name="part_amount" id="part_amount" min="0" />
          </div>
          <div class="inv-field">
            <label for="part_image">รูปภาพ ( path / URL )</label>
            <input class="inv-input" type="text" name="part_image" id="part_image" autocomplete="off" />
          </div>
        </div>

        <h2 class="part-add-section-title">สต็อกและการแจ้งเตือน</h2>
        <div class="part-add-grid">
          <div class="inv-field">
            <label for="part_min_alert">จำนวนขั้นต่ำ Stock</label>
            <input class="inv-input" type="number" name="part_min_alert" id="part_min_alert" min="0" />
          </div>
          <div class="inv-field part-add-field-check">
            <span class="part-add-check-label">แจ้งเตือน Line เมื่อสต็อกต่ำ</span>
            <label class="part-add-check"><input type="checkbox" name="part_line_alert" id="part_line_alert" value="1" /> เปิดใช้งาน (ส่งวันละครั้ง)</label>
          </div>
        </div>

        <h2 class="part-add-section-title">การจัดซื้อ</h2>
        <div class="part-add-grid">
          <div class="inv-field">
            <label for="part_vendor">ร้าน Vendor</label>
            <select class="inv-select" id="part_vendor" name="part_vendor">
              <option value="">-- เลือก --</option>
              <?php for ($a = 0; $a < $arrLengthVD; $a++) { ?>
              <option value="<?php echo $h($arrayVD[$a]); ?>"><?php echo $h($arrayVD[$a]); ?></option>
              <?php } ?>
            </select>
          </div>
          <div class="inv-field">
            <label for="part_puchase_quantity">จำนวนซื้อ/ครั้ง</label>
            <input class="inv-input" type="number" name="part_puchase_quantity" id="part_puchase_quantity" min="0" />
          </div>
          <div class="inv-field part-add-grid__full">
            <label for="part_link">Link สั่งซื้อ</label>
            <input class="inv-input" type="text" name="part_link" id="part_link" autocomplete="off" />
          </div>
          <div class="inv-field">
            <label for="part_price">ราคา</label>
            <input class="inv-input" type="text" name="part_price" id="part_price" inputmode="decimal" autocomplete="off" />
          </div>
          <div class="inv-field">
            <label for="part_shipment">การจัดส่ง</label>
            <select class="inv-select" name="part_shipment" id="part_shipment">
              <option value="">-- เลือก --</option>
              <option value="ในประเทศ">ในประเทศ</option>
              <option value="ต่างประเทศ">ต่างประเทศ</option>
            </select>
          </div>
          <div class="inv-field">
            <label for="part_leadtime">ระยะเวลาจัดส่ง (วัน)</label>
            <input class="inv-input" type="number" name="part_leadtime" id="part_leadtime" min="0" />
          </div>
          <div class="inv-field">
            <label for="part_transport_company">บริษัทขนส่ง</label>
            <input class="inv-input" type="text" name="part_transport_company" id="part_transport_company" autocomplete="off" />
          </div>
          <div class="inv-field">
            <label for="part_period_use">ระยะเวลาใช้ (วัน)</label>
            <input class="inv-input" type="number" name="part_period_use" id="part_period_use" min="0" />
          </div>
          <div class="inv-field part-add-field-check">
            <span class="part-add-check-label">ต้องตรวจร่างก่อน</span>
            <label class="part-add-check"><input type="checkbox" name="part_check_draft" id="part_check_draft" value="Yes" /> ใช่</label>
          </div>
          <div class="inv-field part-add-grid__full">
            <label for="part_remarks">หมายเหตุ</label>
            <input class="inv-input" type="text" name="part_remarks" id="part_remarks" autocomplete="off" />
          </div>
        </div>
      </div>

      <div class="inv-card__footer part-add-footer">
        <?php inventory_render_recorder_field('part_by', 'part_by', 'ผู้บันทึก', 'part-add-footer__by'); ?>
        <button type="button" class="inv-btn inv-btn--primary" onclick="save_job();"><i class="fa fa-save"></i> บันทึก</button>
      </div>
    </section>
  </form>
</main>
</body>
</html>

