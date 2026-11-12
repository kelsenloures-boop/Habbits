<?php
require_once __DIR__ . '/inc/bootstrap.php';

$_SESSION = [];
session_destroy();
header('Location: login.php?saistou=1');
exit;
