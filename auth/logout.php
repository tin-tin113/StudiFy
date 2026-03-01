<?php
/**
 * STUDIFY – Secure Logout
 * Properly destroy session and redirect
 */
define('BASE_URL', '../');
require_once '../includes/auth.php';

secureLogout();

header("Location: " . BASE_URL . "auth/login.php?message=Logged+out+successfully");
exit();
?>
