<?php
require_once __DIR__ . '/includes/bootstrap.php';

$redirect = logoutRedirectPath();
logoutUser();
flash('success', 'You have been logged out.');
redirect($redirect);
