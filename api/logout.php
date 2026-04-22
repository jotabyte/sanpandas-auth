<?php
session_start();
session_destroy();
// Clear session cookies across domain securely
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], 
        '.sanpandas.com', // Match the custom domain pattern
        $params["secure"], 
        $params["httponly"]
    );
}

header("Location: ../index.php");
exit;
