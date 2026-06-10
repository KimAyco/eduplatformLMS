<?php

require_once __DIR__ . '/../includes/bootstrap.php';

$max = (int) ($argv[1] ?? 5);
$processed = aiProcessPendingJobs($max);
echo "Processed {$processed} AI job(s).\n";
