<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

logoutUser();
header("Location: index.php?msg=" . urlencode("Logged out successfully."));
exit;
