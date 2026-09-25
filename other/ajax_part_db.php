<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=tis-620" />
</head>
<body>
<?php
require "connect.php";
if(isset($_POST['lp_product']) && $_POST['lp_product']!=""){ ?>

  <option value="">1 Select Model</option>
<?php
$modelSQL = "SELECT * FROM part_list  ";
$exec = db_exec($connect,$modelSQL) or die ("Error Query [".$modelSQL."]");
while($result=db_fetch_array($exec)) {
?>
  <option value="<?=$result['part_name']?>"><?=$result['part_name']?></option>
  <?php } ?>
  <?php }else{ ?>
  <option value="">2 Select Model</option>
<?php } ?>
</body>
</html>

