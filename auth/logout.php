<?php

session_start();

// Clear all session data
$_SESSION = [];

// Delete the session cookie so the browser forgets the session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        "",
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy the session on the server
session_destroy();

// Redirect to landing page
header("Location: /Game/index.php");
exit;