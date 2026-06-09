<?php
include("dbconnect.php");
include("head.php");
date_default_timezone_set("Asia/Bangkok");
if ($_POST["submit"] = "submit") {

$customer_code = $_POST["customer_code"];
$customer_name = $_POST["customer_name"];
$type_customer = $_POST["type_customer"];
$preface_name = $_POST["preface_name"];
$cus_address = $_POST["cus_address"];
$cus_ampher = $_POST["cus_ampher"];
$cus_province = $_POST["cus_province"];
$cus_postcode = $_POST["cus_postcode"];
$cus_tel = $_POST["cus_tel"];
$cus_fax = $_POST["cus_fax"];
$bill_name = $_POST["bill_name"];
$bill_address = $_POST["bill_address"];
$bill_ampher = $_POST["bill_ampher"];
$billl_province = $_POST["billl_province"];
$bill_postcode = $_POST["bill_postcode"];
$bill_tel = $_POST["bill_tel"];
$tax_id = $_POST["tax_id"];
$delivery_name = $_POST["delivery_name"];
$del_address = $_POST["del_address"];
$del_ampher = $_POST["del_ampher"];
$del_province = $_POST["del_province"];
$del_postcode = $_POST["del_postcode"];
$del_tel = $_POST["del_tel"];
$mode_name = $_POST["h_mode_name"];
$contact_name = $_POST["contact_name"];
$sale_code = $_POST["sale_code"];
$customer_id = $_POST["customer_id"];
$customer_coden = $_POST["customer_coden"];
$warranty = $_POST["warranty"];
$h_ckk = $_POST["h_ckk"];
$brun_no = $_POST["brun_no"];
$close_ckk = $_POST["close_ckk"];	
$add_date = date('Y-m-d H:i:s');
$name =  $_SESSION['name'];
$surname =	$_SESSION['surname'];
$add_by = "$name $surname";	
$remark_cus = $_POST["remark_cus"];

$rental_name = $_POST["rental_name"];
$rental_tel = $_POST["rental_tel"];
$rental_address = $_POST["rental_address"];
$rental_ampher = $_POST["rental_ampher"];
$rental_province = $_POST["rental_province"];
$rental_postcode = $_POST["rental_postcode"];
$rental_postcode = $_POST["rental_postcode"];	
$rental_emer = $_POST["rental_emer"];
$rental_emertel = $_POST["rental_emertel"];
$patient_name = $_POST["patient_name"];		
$install_address = $_POST["install_address"];	
$rental_contacttel = $_POST["rental_contacttel"];	
$rental_contact = $_POST["rental_contact"];
$email_cus = $_POST["email_cus"];
$edit_date = date('Y-m-d H:i:s');
$name = $_SESSION["name"];
$surname =	$_SESSION["surname"];
$edit_name = "$name $surname";
$vip_ckk = $_POST["vip_ckk"];
$credit_ckk = $_POST["credit_ckk"];	
$credit_thb = str_replace(',', '',$_POST["credit_thb"]);	
	
/*$sql1 = "SELECT cus_tel    FROM tb_customer where cus_tel  ='".$cus_tel."' ";
	//echo $sql1;
$qry1 = mysqli_query($conn,$sql1) or die(mysqli_error());
$Num_Rows1 = mysqli_num_rows($qry1);


if($Num_Rows1 > 0 ){
echo"<script>alert('ได้มีการบันทึกลูกค้าด้วยเบอร์โทรนี้ไปแล้วค่ะ');history.back();</script>";
exit();
}	*/
	
	
$save=" Update  tb_customer set 
customer_code='".$customer_code."',customer_name='".$customer_name."',type_customer='".$type_customer."',preface_name='".$preface_name."',cus_address='".$cus_address."',cus_ampher='".$cus_ampher."',cus_province='".$cus_province."',cus_postcode='".$cus_postcode."',cus_tel='".$cus_tel."',cus_fax='".$cus_fax."',bill_name='".$bill_name."',bill_address='".$bill_address."',bill_ampher='".$bill_ampher."',billl_province='".$billl_province."',bill_postcode='".$bill_postcode."',bill_tel='".$bill_tel."',tax_id='".$tax_id."',delivery_name='".$delivery_name."',del_address='".$del_address."',del_ampher='".$del_ampher."',del_province='".$del_province."',del_postcode='".$del_postcode."',del_tel='".$del_tel."',contact_name='".$contact_name."',customer_coden='".$customer_coden."',warranty='".$warranty."',brun_no='".$brun_no."',h_ckk='".$h_ckk."',close_ckk='".$close_ckk."',remark_cus='".$remark_cus."',rental_name='".$rental_name."',rental_address='".$rental_address."',rental_ampher='".$rental_ampher."',rental_province='".$rental_province."',rental_postcode='".$rental_postcode."',rental_emer='".$rental_emer."',rental_emertel='".$rental_emertel."',patient_name='".$patient_name."',install_address='".$install_address."',rental_tel='".$rental_tel."',rental_contacttel='".$rental_contacttel."',rental_contact='".$rental_contact."',mode_name='".$mode_name."',edit_date='".$edit_date."',edit_name='".$edit_name."',email_cus='".$email_cus."',vip_ckk='".$vip_ckk."'   where customer_id='".$customer_id."'";

$qsave=mysqli_query($conn,$save);
	
	
 if($_SESSION["name"]=='นงลักษณ์' or $_SESSION["name"]=='อัจฉรา'  or $_SESSION["name"]=='สุดารัตน์' or $_SESSION["name"]=='รุจิรา' or $_SESSION["name"]=='พัชร์ชนัญ' ){ 
	
$save=" Update  tb_customer set credit_ckk='".$credit_ckk."',credit_thb='".$credit_thb."'   where customer_id='".$customer_id."'";

$qsave=mysqli_query($conn,$save);

	 
 }	 
	
	
$selected_sales = isset($_POST['sale_code']) ? $_POST['sale_code'] : array();
	
$strSQL = "SELECT sale_code FROM tb_selected_sales WHERE id_customer ='$customer_id'";
$objQuery = mysqli_query($conn, $strSQL);
$existing_sales = array();

while ($row = mysqli_fetch_assoc($objQuery)) {
    $existing_sales[] = $row['sale_code'];
}

// Insert new records
foreach ($selected_sales as $sale_code) {
    if (!in_array($sale_code, $existing_sales)) {
        $insertSQL = "INSERT INTO tb_selected_sales (id_customer, sale_code,customer_name) VALUES ('$customer_id', '$sale_code','".$bill_name."')";
        mysqli_query($conn, $insertSQL);
    }
}

// Delete unselected records
foreach ($existing_sales as $sale_code) {
    if (!in_array($sale_code, $selected_sales)) {
        $deleteSQL = "DELETE FROM tb_selected_sales WHERE id_customer ='$customer_id' AND sale_code ='$sale_code'";
        mysqli_query($conn, $deleteSQL);
    }
}		
	

	
$save2 = "Update  tb__buypro set mode_cus='".$mode_name."'   where bill_id='".$customer_id."'";
$qsave2 = mysqli_query($conn,$save2);
	
$save3 = "Update  tb__discash set mode_cus='".$mode_name."'   where bill_id='".$customer_id."'";
$qsave3 = mysqli_query($conn,$save3);	
	
$save4 = "Update  hos__so set mode_cus='".$mode_name."'   where bill_id='".$customer_id."'";
$qsave4 = mysqli_query($conn,$save4);	

$save5 = "Update  tb_credit_note set mode_cus='".$mode_name."'   where bill_id='".$customer_id."'";
$qsave5 = mysqli_query($conn,$save5);		
	

/*ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set("Asia/Bangkok");
$sToken	='3mN5wuBOGBEH7SOjDZWIpqRsr2BRUQYRb7RDj7yRnYl';
$sMessage = "มีการแก้ไขข้อมูลลูกค้า 
ID :  $customer_id
ชื่อลูกค้า : $customer_name
เบอร์โทร : $cus_tel
แก้ไขโดย : $add_by
แก้ไขเวลา : $add_date
สามารถตรวจสอบได้ที่ https://sol.allwellcenter.com/
";
$chOne = curl_init();
curl_setopt( $chOne, CURLOPT_URL, "https://notify-api.line.me/api/notify");
curl_setopt( $chOne, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt( $chOne, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt( $chOne, CURLOPT_POST, 1);
curl_setopt( $chOne, CURLOPT_POSTFIELDS, "message=".$sMessage);
$headers = array( 'Content-type: application/x-www-form-urlencoded', 'Authorization: Bearer '.$sToken.'', );
curl_setopt($chOne, CURLOPT_HTTPHEADER, $headers);
curl_setopt( $chOne, CURLOPT_RETURNTRANSFER, 1);
$result = curl_exec( $chOne );
if(curl_error($chOne))
{
//echo 'error:' . curl_error($chOne);
}
else {
$result_ = json_decode($result, true);
//echo "status : ".$result_['status']; echo "message : ". $result_['message'];
}
curl_close( $chOne ); 	*/		








 if($qsave){
   echo "<script language=\"JavaScript\">";
echo "alert('บันทึกข้อมูลของท่านเรียบร้อยแล้ว');window.location='add_customer.php'";
echo "</script>";
  } else {
   echo "Cannot";
  }
	}
?>