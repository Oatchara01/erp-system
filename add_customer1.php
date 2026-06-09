<?php
include("dbconnect.php");
include("head.php");
date_default_timezone_set("Asia/Bangkok");
if ($_POST["submit"] = "submit") {

	
function normalizeThaiName($text) {
    $text = trim($text);
    $text = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $text);
    $text = preg_replace('/[\p{Z}\s]+/u', ' ', $text);
    return trim($text);
}


	
$qfirst = "select * from tb_customer ORDER BY customer_id DESC LIMIT 1";
$first = mysqli_query($conn,$qfirst);
$Num_Rows88 = mysqli_num_rows($first);
$ffirst = mysqli_fetch_array($first);
	
$customer_id = $ffirst['customer_id']+1;	

	
	
$preface_name = $_POST["preface_name"];
$customer_code = $_POST["customer_code"];
$customer_name1 = normalizeThaiName($_POST['customer_name']);	
//$customer_name1 = $_POST["customer_name"];
$customer_name ="$preface_name $customer_name1";
$type_customer = $_POST["type_customer"];
$h_ckk = $_POST["h_ckk"];
$credit_ckk = $_POST["credit_ckk"];	
$brun_no = $_POST["brun_no"];
$cus_address = $_POST["cus_address"];
$cus_ampher = $_POST["cus_ampher"];
$cus_province = $_POST["cus_province"];
$cus_postcode = $_POST["cus_postcode"];
$cus_tel = $_POST["cus_tel"];
$cus_fax = $_POST["cus_fax"];
$tax_id = $_POST["tax_id"];	
$ckk_1 = $_POST["ckk_1"];	
$warranty = $_POST["warranty"];	
	
if($ckk_1=='1'){	
$bill_name = $_POST["customer_name"];
$bill_address = $_POST["cus_address"];
$bill_ampher = $_POST["cus_ampher"];
$billl_province = $_POST["cus_province"];
$bill_postcode = $_POST["cus_postcode"];
$bill_tel = $_POST["cus_tel"];

}else{
	
$bill_name = $_POST["bill_name"];
$bill_address = $_POST["bill_address"];
$bill_ampher = $_POST["bill_ampher"];
$billl_province = $_POST["billl_province"];
$bill_postcode = $_POST["bill_postcode"];
$bill_tel = $_POST["bill_tel"];	
	
}

	
$ckk_3 = $_POST["ckk_3"];		
if($ckk_3=='1'){	
$rental_name = $_POST["customer_name"];
$rental_address = $_POST["cus_address"];
$rental_ampher = $_POST["cus_ampher"];
$rental_province = $_POST["cus_province"];
$rental_postcode = $_POST["cus_postcode"];
$rental_postcode = $_POST["cus_tel"];

}else{
	
$rental_name = $_POST["rental_name"];
$rental_address = $_POST["rental_address"];
$rental_ampher = $_POST["rental_ampher"];
$rental_province = $_POST["rental_province"];
$rental_postcode = $_POST["rental_postcode"];
$rental_postcode = $_POST["rental_postcode"];	
	
}	
	
$rental_emer = $_POST["rental_emer"];
$rental_emertel = $_POST["rental_emertel"];
$patient_name = $_POST["patient_name"];	
$install_address = $_POST["install_address"];
$rental_contact = $_POST["rental_contact"];
$rental_contacttel = $_POST["rental_contacttel"];
	
$ckk_2 = $_POST["ckk_2"];	
	
	
if($ckk_2=='1'){	
$delivery_name = "$preface_name $customer_name1";
$del_address = $_POST["cus_address"];
$del_ampher = $_POST["cus_ampher"];
$del_province = $_POST["cus_province"];
$del_postcode = $_POST["cus_postcode"];
$del_tel = $_POST["cus_tel"];	
	
}else{
$delivery_name = $_POST["delivery_name"];
$del_address = $_POST["del_address"];
$del_ampher = $_POST["del_ampher"];
$del_province = $_POST["del_province"];
$del_postcode = $_POST["del_postcode"];
$del_tel = $_POST["del_tel"];
}
	
if($_POST["contact_name"] !='')	{
$contact_name = $_POST["contact_name"];	
	}else{
$contact_name ="$customer_name1/$cus_tel";	
}
	
$mode_name 	= $_POST["h_mode_name"];	
$sale_code = $_POST["sale_code"];
$email_cus = $_POST["email_cus"];
$vip_ckk = $_POST["vip_ckk"];	
	
$sql1 = "SELECT cus_tel    FROM tb_customer where cus_tel  ='".$cus_tel."' ";
	//echo $sql1;
$qry1 = mysqli_query($conn,$sql1) or die(mysqli_error());
$Num_Rows1 = mysqli_num_rows($qry1);


if($Num_Rows1 > 0 ){

//echo "<script language=\"JavaScript\">";
//echo "alert('ได้มีการบันทึกลูกค้าด้วยเบอร์โทรนี้ไปแล้วค่ะ');window.location='add_customer.php';";
echo"<script>alert('ได้มีการบันทึกลูกค้าด้วยเบอร์โทรนี้ไปแล้วค่ะ');history.back();</script>";
//echo "</script>";
exit();

}
	

$save="insert into tb_customer
(customer_code,customer_name,type_customer,preface_name,cus_address,cus_ampher,cus_province,cus_postcode,cus_tel,cus_fax,bill_name,bill_address,bill_ampher,billl_province,bill_postcode,bill_tel,tax_id,delivery_name,del_address,del_ampher,del_province,del_postcode,del_tel,contact_name,warranty,brun_no,h_ckk,rental_name,rental_emer,rental_address,rental_ampher,rental_province,rental_postcode,rental_emertel,rental_tel,patient_name,install_address,rental_contact,rental_contacttel,mode_name,email_cus,vip_ckk,credit_ckk)
values
('".$customer_code."','".$customer_name."','".$type_customer."','".$preface_name."','".$cus_address."','".$cus_ampher."','".$cus_province."','".$cus_postcode."','".$cus_tel."','".$cus_fax."','".$bill_name."','".$bill_address."','".$bill_ampher."','".$billl_province."','".$bill_postcode."','".$bill_tel."','".$tax_id."','".$delivery_name."','".$del_address."','".$del_ampher."','".$del_province."','".$del_postcode."','".$del_tel."','".$contact_name."','".$warranty."','".$brun_no."','".$h_ckk."','".$rental_name."','".$rental_emer."','".$rental_address."','".$rental_ampher."','".$rental_province."','".$rental_postcode."','".$rental_emertel."','".$rental_tel."','".$patient_name."','".$install_address."','".$rental_contact."','".$rental_contacttel."','".$mode_name."','".$email_cus."','".$vip_ckk."','".$credit_ckk."')";

$qsave=mysqli_query($conn,$save);



$sale_code = $_POST["sale_code"];
foreach ($sale_code as $code) {
    // Example query to save
    $sql = "INSERT INTO tb_selected_sales (sale_code, id_customer, customer_name) VALUES ('$code', '$customer_id', '$bill_name')";
    $qsave2 = mysqli_query($conn, $sql);
    }









 if($qsave && $qsave2){
   echo "<script language=\"JavaScript\">";
echo "alert('บันทึกข้อมูลของท่านเรียบร้อยแล้ว');window.location='add_customer.php'";
echo "</script>";
  } else {
   echo "Cannot";
  }
	}
?>