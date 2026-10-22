<?php
/**
 * MYFINANCE — Logout Handler
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

logout_user();
set_flash('info', 'Anda telah berhasil keluar (logout) dari sistem MYFINANCE.');
header('Location: login.php');
exit;

