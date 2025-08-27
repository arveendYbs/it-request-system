<?php
/**
 * Main Index Page - Redirects to appropriate page
 * index.php
 */

require_once 'includes/auth.php';

// Check if user is logged in
if ($auth->isLoggedIn()) {
    // Redirect to dashboard
    header('Location: dashboard.php');
} else {
    // Redirect to login
    header('Location: login.php');
}

exit();
?>