<?php
// admin/logout.php
require_once __DIR__ . '/../api/config.php'; // Pour session_start()
$_SESSION = [];
session_destroy();
header('Location: login.php?message=logged_out');
exit;
?>