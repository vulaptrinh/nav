<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
$code = '';
for ($i = 0; $i < 4; $i++) {
    $code .= $chars[rand(0, strlen($chars) - 1)];
}
$_SESSION['cashtrack_security_code'] = $code;

echo json_encode(['code' => $code]);
exit;
