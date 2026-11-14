<?
require("connect.php");
header("Content-type : text/html; charset=TIS-620");
setlocale(LC_TIME, 'tha');
?>
<head>
<meta http-equiv="Content-Language" content="th"> 
<meta http-equiv="Content-Type" content="text/html; charset=tis-620" />
<META HTTP-EQUIV="Refresh"  CONTENT="1; URL='stock_overview.php' "> 

<script type="text/javascript">

</script>
</head>
<body>
<?

$status = $_GET['status'];

if($status == "insert"){
		$sql = "insert into part ";
		$sql .= "(part_name, part_product, part_amount, part_vendor, part_price, part_min_alert, part_shipment, part_link, part_image,
		part_leadtime, part_by,part_categories, part_stock_no,
		part_puchase_quantity, part_transport_company,  part_period_use, part_check_draft, part_remarks)";
		$sql .= "values ";
		$sql .= "('".$_GET['part_name']."', '".$_GET['part_product']."',  '".$_GET['part_amount']."', '".$_GET['part_vendor']."', '".$_GET['part_price']."'
		, '".$_GET['part_min_alert']."', '".$_GET['part_shipment']."', '".$_GET['part_link']."' , '".$_GET['part_image']."', '".$_GET['part_leadtime']."'
		, '".$_GET['part_by']."' , '".$_GET['part_categories']."' , '".$_GET['part_stock_no']."' 
		, '".$_GET['part_puchase_quantity']."' , '".$_GET['part_transport_company']."' , '".$_GET['part_period_use']."' 
		, '".$_GET['part_check_draft']."' , '".$_GET['part_remarks']."' ) ";
		
		$exec = odbc_exec($connect, $sql) or die("Error Query [" . $sql . "]"); 
		

	
		if($exec){ 
		
			$sqlCheck = "SELECT TOP 1  * FROM part  ORDER BY part_id DESC" ;  //SELECT TOP 1 * FROM Table ORDER BY ID DESC
			$execCheck = odbc_exec($connect, $sqlCheck); 
			$check_insert;
		 	while ($resultCheck = odbc_fetch_array($execCheck)){
					$check_insert = $resultCheck['part_id'];
			}
			
			$sqlPD = "SELECT * FROM product  WHERE pd_name = '".$_GET['part_product']."' " ;  //SELECT TOP 1 * FROM Table ORDER BY ID DESC
			$execPD = odbc_exec($connect, $sqlPD) or die("Error Query [" . $sqlPD . "]"); 
			$pd_id;
			$pd_name;
		 	while ($resultPD = odbc_fetch_array($execPD)){
					$pd_id = $resultPD['pd_id'];
					$pd_name =  $resultPD['pd_name'];
			}
			
			$sqlPF = "insert into production_formula ";
			$sqlPF .= "(pd_id, pd_name, part_id, formula_name_id, ct_id)";
			$sqlPF .= "values ";
			$sqlPF .= "( '".$pd_id."', '".$pd_name."',  '".$check_insert."', '".$pd_id."', '".$_GET['part_categories']."' ) ";
			
			$execPF = odbc_exec($connect, $sqlPF) or die("Error Query [" . $sqlPF . "]"); 
		
		
			echo "Check ID = ".$check_insert."<br>";

			if($execPF){
				echo '<script>alert("Save success..")</script>';
			}else{
				echo '<script>alert("Save failed!")</script>';
			}
		} else { 
			echo '<script>alert("Save failed!")</script>';
		}
	

}else if($status == "update"){


			$sqlPD = "SELECT * FROM categories_product  WHERE ct_name = '".$_GET['part_categories']."' " ;  //SELECT TOP 1 * FROM Table ORDER BY ID DESC
			$execPD = odbc_exec($connect, $sqlPD) or die("Error Query [" . $sqlPD . "]"); 
			$ct_id;
		 	while ($resultPD = odbc_fetch_array($execPD)){
					$ct_id = $resultPD['ct_id'];
			}


		$sqlUpdate= "UPDATE part ";
		$sqlUpdate .= "SET part_name = '".$_GET['part_name']."', part_product =  '".$_GET['part_product']."', 
		part_amount = '".$_GET['part_amount']."' , 
		part_vendor =  '".$_GET['part_vendor']."', 
		part_price = '".$_GET['part_price']."', 
		part_min_alert =  '".$_GET['part_min_alert']."', 
		part_link = '".$_GET['part_link']."' , 
		part_image =  '".$_GET['part_image']."',
		part_leadtime = '".$_GET['part_leadtime']."', 
		part_by = '".$_GET['part_by']."' ,
		part_categories = ".$ct_id." , 
		part_status_purchase = '".$_GET['part_status_purchase']."',  
		part_date_purchase = '".$_GET['part_date_purchase']."'  ,
		part_shipment = '".$_GET['part_shipment']."' , 
		part_stock_no = '".$_GET['part_stock_no']."',  
		part_puchase_quantity = '".$_GET['part_puchase_quantity']."' ,
		part_transport_company = '".$_GET['part_transport_company']."' , 
		part_period_use = '".$_GET['part_period_use']."',  
		part_check_draft = '".$_GET['part_check_draft']."' ,
		part_remarks = '".$_GET['part_remarks']."' 
		WHERE part_id =  ".$_GET['part_id']."  ";
		
		$execUpdate = odbc_exec($connect, $sqlUpdate) or die("Error Query [" . $sqlUpdate . "]"); 
		if($execUpdate){
				echo '<script>alert("Update success..")</script>';
			}else{
				echo '<script>alert("Update failed!")</script>';
			}

}





odbc_close($connect);

?>
</body>
</html>
