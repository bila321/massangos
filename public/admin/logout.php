<?php
// public/admin/logout.php
session_start();
unset($_SESSION['admin_id']);
unset($_SESSION['admin_role']);
header("Location: login.php");
exit();
?>
