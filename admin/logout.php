<?php
/**
 * Admin Logout - Bolão Vitimba
 */
require_once '../config/config.php';
require_once '../includes/functions.php';

// Clear admin session
unset($_SESSION['admin_id']);
unset($_SESSION['admin_nome']);

// Start a new session to set flash message
setFlashMessage('success', 'Você foi desconectado da área administrativa.');

// Redirect to admin login page
redirect(APP_URL . '/admin/login.php');
?> 