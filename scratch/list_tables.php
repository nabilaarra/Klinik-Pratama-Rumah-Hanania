<?php
require_once __DIR__ . '/../config/database.php';
$res = $conn->query("SHOW TABLES");
while ($r = $res->fetch_row()) {
    echo $r[0] . "\n";
}
