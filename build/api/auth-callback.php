<?php
// Clean OAuth Callback - NO JSON OUTPUT
error_reporting(0);
session_start();

// Check for code parameter
if (isset($_GET['code'])) {
    // Store in session
    $_SESSION['oauth_code'] = $_GET['code'];
    $_SESSION['oauth_state'] = $_GET['state'] ?? '';
}

// ALWAYS redirect - NEVER output JSON
// Redirect to the application subpath
header('Location: /aze/');
exit();
?>
