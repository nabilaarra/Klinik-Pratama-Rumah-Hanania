<?php
require_once __DIR__ . '/../config/database.php';
echo "=== TABLE PASIEN ===\n";
$q = $conn->query("DESCRIBE pasien");
while ($r = $q->fetch_assoc()) {
    echo $r['Field'] . " (" . $r['Type'] . ")\n";
}
echo "\n=== TABLE PENDAFTARAN ===\n";
$q = $conn->query("DESCRIBE pendaftaran");
while ($r = $q->fetch_assoc()) {
    echo $r['Field'] . " (" . $r['Type'] . ")\n";
}
