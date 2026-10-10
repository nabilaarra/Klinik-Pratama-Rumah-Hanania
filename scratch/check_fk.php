<?php
require_once __DIR__ . '/../config/database.php';

$res = $conn->query("
    SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE REFERENCED_TABLE_SCHEMA = 'klinik_pratama_rumah_hanania'
");
while ($r = $res->fetch_assoc()) {
    echo "{$r['TABLE_NAME']}.{$r['COLUMN_NAME']} -> {$r['REFERENCED_TABLE_NAME']}.{$r['REFERENCED_COLUMN_NAME']} ({$r['CONSTRAINT_NAME']})\n";
}
