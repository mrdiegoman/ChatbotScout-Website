<?php
require '../inc/bootstrap.php';
require '../app/auth.php';

// Odhlás používateľa
logout();

// Redirect na homepage
header('Location: index.php');
exit;