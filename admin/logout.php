<?php
require_once __DIR__ . '/../config/session_bootstrap.php';

// Futa session zote
session_unset();
session_destroy();

// Redirect kwenda login page
header("Location: login.php");
exit();
