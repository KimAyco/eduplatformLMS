<?php
require_once __DIR__ . '/includes/bootstrap.php';

logoutUser();
flash('success', 'You have been logged out.');
redirect('login.php');
