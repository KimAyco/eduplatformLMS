<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$rows = db()->query("SELECT id, name FROM schools WHERE school_code IS NULL OR school_code = ''")->fetchAll();

if (empty($rows)) {
    echo 'All schools already have a school code.' . PHP_EOL;
    exit(0);
}

echo 'Schools missing a code (set manually via registration or UPDATE):' . PHP_EOL;
foreach ($rows as $row) {
    echo '  #' . $row['id'] . ' ' . $row['name'] . ' — suggested: ' . suggestSchoolCodeFromName($row['name']) . PHP_EOL;
}
