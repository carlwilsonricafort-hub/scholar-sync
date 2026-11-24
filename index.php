<?php
require_once __DIR__ . '/includes/config.php';
header('Location: ' . (isset($_SESSION['user']) ? 'dashboard.php' : 'login.php'));
exit();
