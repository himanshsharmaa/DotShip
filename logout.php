<?php
require_once __DIR__ . '/includes/init.php';

dotship_logout_user();
header('Location: ' . dotship_path('index.php'));
exit;