<?php
// includes/session_init.php – central session configuration and CSRF helpers

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (($_SERVER['SERVER_PORT'] ?? null) == 443)
    );

    $cookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => $cookieParams['lifetime'],
        'path' => $cookieParams['path'],
        'domain' => $cookieParams['domain'],
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrfInputField(): string
{
    $token = htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

function verifyCsrfToken(?string $submittedToken): bool
{
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if ($sessionToken === '' || $submittedToken === null || $submittedToken === '') {
        return false;
    }

    return hash_equals($sessionToken, $submittedToken);
}
