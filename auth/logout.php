<?php

require_once __DIR__ . '/../includes/session_init.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken($_POST["csrf_token"] ?? null)) {
        http_response_code(403);
        echo "Invalid security token. Please refresh and try again.";
        exit;
    }

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
}

if (!isset($_SESSION["user_id"])) {
    header("Location: /Game/index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log out | Banana Game</title>
</head>
<body style="font-family: 'Segoe UI', system-ui, sans-serif; background: #fffaf0; color: #1f1b12; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0;">
    <div style="background: #fff; border: 1px solid #e6e1d3; border-radius: 14px; padding: 28px; max-width: 380px; text-align: center;">
        <h2 style="margin-top: 0;">Log out?</h2>
        <p style="color: #6f6a5c;">Confirm that you want to end your current session.</p>
        <form method="POST" action="" style="display: inline-flex; gap: 10px; margin-top: 12px;">
            <?php echo csrfInputField(); ?>
            <button type="submit" style="background: #1f1b12; color: #fff; border: 0; border-radius: 8px; padding: 10px 18px; font-weight: 700; cursor: pointer;">Log out</button>
            <a href="/Game/game/play.php" style="background: #fff; color: #1f1b12; border: 1px solid #d6d0bf; border-radius: 8px; padding: 10px 18px; font-weight: 700; text-decoration: none;">Cancel</a>
        </form>
    </div>
</body>
</html>
