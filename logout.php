<?php
// logout.php - خروج کاربر

require_once 'config.php';

session_destroy();
redirect('login.php');
?>
