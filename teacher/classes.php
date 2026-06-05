<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('teacher');
redirect('teacher/dashboard.php');
