<?php
/**
 * CashTrack - Logout Handler
 */
session_start();
require_once __DIR__ . '/config/database.php';

// Clear remember token in DB if user logged in
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("UPDATE `users` SET `remember_token` = NULL WHERE `id` = ?");
    $stmt->execute([$_SESSION['user_id']]);
}

// Clear Session
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}
session_destroy();

// Clear Remember Me Cookie
setcookie('cashtrack_remember', '', time() - 3600, '/');

// Redirect with notice
header("Location: index.php?logged_out=1");
exit;
