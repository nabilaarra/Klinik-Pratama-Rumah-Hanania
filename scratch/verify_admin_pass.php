<?php
require_once __DIR__ . '/../config/database.php';
$res = $conn->query("SELECT password FROM users WHERE username = 'admin' LIMIT 1");
$row = $res->fetch_assoc();
$hash = $row['password'];
echo "Matches admin123? " . (password_verify('admin123', $hash) || $hash === 'admin123' ? 'YES' : 'NO') . "\n";
