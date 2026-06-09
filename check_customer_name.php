<?php
include('dbconnect.php');

header('Content-Type: application/json; charset=utf-8');

function normalizeThaiName($text) {
    $text = trim($text);

    // ลบ zero-width และอักขระแฝงที่พบบ่อย
    $text = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $text);

    // แปลงช่องว่างทุกชนิดให้เป็น space ปกติ
    $text = preg_replace('/[\p{Z}\s]+/u', ' ', $text);

    // trim อีกรอบ
    $text = trim($text);

    return $text;
}

$name = isset($_POST['customer_name']) ? $_POST['customer_name'] : '';
$name = normalizeThaiName($name);

// เอาช่องว่างออกทั้งหมดเพื่อใช้เทียบ
$compareName = preg_replace('/\s+/u', '', $name);

$response = [
    'exists' => false,
    'count' => 0,
    'names' => []
];

if ($compareName !== '') {

    $sql = "SELECT customer_name
            FROM tb_customer
            ORDER BY customer_name ASC";

    $result = mysqli_query($conn, $sql);

    $names = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $dbName = normalizeThaiName($row['customer_name']);
        $dbCompareName = preg_replace('/\s+/u', '', $dbName);

        if (mb_stripos($dbCompareName, $compareName, 0, 'UTF-8') !== false) {
            $names[] = $row['customer_name'];
        }
    }

    $response['count'] = count($names);
    $response['exists'] = count($names) > 0;
    $response['names'] = array_slice($names, 0, 10);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>