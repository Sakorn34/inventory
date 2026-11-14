<?php
/**
 * หน้ารายการเบิกของของระบบ inventory
 *
 * ขอบเขตของไฟล์นี้คือแสดงรายการเบิกตามวันที่ที่เลือก และโหลดรายละเอียดผ่าน
 * `pickup_list_order.php` เพื่อให้ผู้ใช้ตรวจสอบหรือดำเนินการเบิกต่อ
 *
 * Flow สั้น: เปิดหน้า `pickup_overview.php`, เลือกวันที่, JavaScript โหลดรายการ
 * ลงในพื้นที่ผลลัพธ์กลางหน้า ใช้ธีม inv-app / การ์ด inv-card เหมือน stock_overview
 */

// ─ Imports ──────────────────────────────────────────────────────────────────

require_once __DIR__ . '/connect.php';
header("Content-Type: text/html; charset=UTF-8");
@setlocale(LC_TIME, 'th_TH.UTF-8', 'th_TH', 'tha');
//$date = date("Y-m-d");
//echo $date . "<br>";
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.6.3/css/font-awesome.min.css">
<?php
if (!defined('INVENTORY_UI_CSS')) {
    define('INVENTORY_UI_CSS', true);
}
?>
<link rel="stylesheet" type="text/css" href="css/smoothness/jquery-ui-1.7.2.custom.css">
<link rel="stylesheet" href="css/inventory-ui.css">

<title>รายการเบิกของ</title>
<script type="text/javascript" src="js/jquery-1.3.2.min.js"></script>  
<script type="text/javascript" src="js/jquery-ui-1.7.2.custom.min.js"></script>  
<script type="text/javascript" src="js/jquery-3.5.1.min.js"></script>
<script type="text/javascript" src="js/jquery-add_object.min.js"></script>
<script type="text/javascript">
	
//Search   Enduser / 
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


//select product_name
$(document.getElementById("pre_date")).ready(function(){
     $("#pre_date").change(function(){ 
		 $.get("pickup_list_order.php",{ pre_date:$(this).val() },
		 function(data) { $("#result").html(data);  });
      }); 
});

function listDateNow(){

    /*$tz_object = new DateTimeZone('Asia/Bangkok');

    $datetime = new DateTime();
    $datetime->setTimezone($tz_object);
    $datetime->format('Y\-m\-d\ h:i:s');*/
    $dateNow = pre_date.value;
   //  alert($dateNow);
	 $.get("pickup_list_order.php",{ pre_date:$dateNow },
	 function(data) { 
         $("#result").html(data); 
     });
}

$(document).on("submit", ".delete-pickup-form", function(event) {
    event.preventDefault();

    var form = $(this);

    $.post(form.attr("action"), form.serialize(), function(data) {
        $("#result").html(data);
    }).fail(function() {
        alert("ลบรายการไม่สำเร็จ กรุณาลองใหม่อีกครั้ง");
    });
});

</script>
</head>
<body class="inv-app pickup-overview" onload="listDateNow()">
<?php require __DIR__ . '/title-h.php'; ?>

<main class="inv-main">
  <header class="inv-page-header">
    <h1 class="inv-page-title">รายการเบิกของ</h1>
    <p class="inv-page-desc">เอกสารที่ยังรอคลังจ่าย — เลือกวันที่เพื่อโหลดรายการ (แสดงรายการค้างทั้งหมด)</p>
  </header>

  <form id="form_production" name="form_production" method="post" action="save_stock">
    <section class="inv-card">
      <div class="inv-card__head"><i class="fa fa-filter"></i> กรองรายการ</div>
      <div class="inv-card__body">
        <div class="inv-filters pickup-overview__filters">
          <div class="inv-field">
            <label for="pre_date">วันที่เบิก</label>
            <input class="inv-input" type="date" name="pre_date" id="pre_date" value="<?php echo htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>" />
          </div>
          <div class="inv-filters__actions pickup-overview__history">
            <a class="pickup-overview__history-link inv-btn inv-btn--ghost" href="./pickup_log.php"><i class="fa fa-list-alt"></i> ดูประวัติการเบิกของ</a>
          </div>
        </div>
      </div>
    </section>

    <section class="inv-card">
      <div class="inv-card__head"><i class="fa fa-table"></i> รายการเอกสารรอจ่าย</div>
      <div class="inv-card__body inv-card__body--flush">
        <div id="result" name="result" class="pickup-result">result</div>
      </div>
    </section>
  </form>
</main>

</body>
</html>
