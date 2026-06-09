<?php


include"dbconnect.php"; 

$strProduct = trim($_POST["bill_id"]);

$strSQL = "SELECT * FROM tb_customer WHERE customer_id = '".$strProduct."' ";

$objQuery = mysqli_query($conn,$strSQL) or die ("Error Query [".$strSQL."]");
$objResult = mysqli_fetch_array($objQuery);
if($objResult)
{
	
$strSQL1 = "SELECT type_name FROM tb_typecustomer WHERE type_id = '".$objResult["type_customer"]."' ";
$objQuery1 = mysqli_query($conn,$strSQL) or die ("Error Query [".$strSQL1."]");
$objResult1 = mysqli_fetch_array($objQuery1);	
	
	$pro ="จ.";
	$del_address =$objResult["del_address"];
	$del_ampher = $objResult["del_ampher"];
	$del_province =$objResult["del_province"];
	$del_postcode=$objResult["del_postcode"];
	$delivery ="$del_address $del_ampher $del_province $del_postcode";
	
echo $objResult["bill_name"]."|".$objResult["bill_address"]." ".$objResult["bill_ampher"]." ". $pro." ".$objResult["billl_province"]." ".$objResult["bill_postcode"]."|".$objResult["bill_tel"]."|".$objResult["tax_id"]."|".$objResult["customer_no"]."|".$objResult["cus_tel"]."|".$objResult["bill_address"]."|".$objResult["bill_ampher"]."|".$objResult["billl_province"]."|".$objResult["bill_postcode"]."|".$objResult["preface_name"]."|".$objResult["tax_id"]."|".$objResult["delivery_name"]."|".$objResult["del_address"]."|".$objResult["del_ampher"]."|".$objResult["del_province"]."|".$objResult["del_postcode"]."|".$objResult["del_tel"]."|".$objResult["contact_name"]."|".$delivery."|".$objResult["mode_name"]."|".$objResult["email_cus"]."|".$objResult1["type_name"]."|".$objResult["credit_ckk"]."|".$objResult["credit_thb"];

}

?>


