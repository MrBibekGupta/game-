<?php
// new_game.php – resets the game session and redirects to play.php
require_once __DIR__ . '/../includes/session_init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Unset game-specific session variables
unset(
    $_SESSION['game_session_id'],
    $_SESSION['game_score'],
    $_SESSION['game_question'],
    $_SESSION['banana_question'],
    $_SESSION['banana_solution'],
    $_SESSION['game_finished']
);

// Redirect to a fresh game start
header('Location: play.php');
exit;
