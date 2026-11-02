<?php
/**
 * CashTrack - Authentication Middleware
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// Check if user is logged in via Session
if (!isset($_SESSION['user_id'])) {
    // Check if Remember Me cookie exists
    if (isset($_COOKIE['cashtrack_remember'])) {
        $token = $_COOKIE['cashtrack_remember'];
        $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `remember_token` = ? AND `status` = 1 LIMIT 1");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['email'] = $user['email'];
        } else {
            // Invalid token, remove cookie
            setcookie('cashtrack_remember', '', time() - 3600, '/');
            header("Location: index.php");
            exit;
        }
    } else {
        header("Location: index.php");
        exit;
    }
}
