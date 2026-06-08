<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('student');
redirect('student/classes.php');
