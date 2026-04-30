<?php
require_once dirname(__DIR__) . '/config.php';

// Make $conn available globally for legacy files that use mysqli_* functions
$conn = getDB();