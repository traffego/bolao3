<?php
/**
 * Logout - Bolão Vitimba
 */
require_once 'config/config.php';
require_once 'includes/functions.php';

// Clear all session data
session_unset();
session_destroy();

// Start a new session to set flash message
session_start();
setFlashMessage('success', 'Você foi desconectado com sucesso.');

// Redirect to home page
redirect(APP_URL);
?> 