<?php

session_start();

require_once "../config/db.php";
require_once "../includes/api.php";

// User must be logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];
$username = $_SESSION["username"] ?? "Player";

$totalQuestions = 10;


/*
|--------------------------------------------------------------------------
| START NEW GAME
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["game_session_id"])) {

    $stmt = $conn->prepare("
        INSERT INTO game_sessions
        (user_id, score, total_questions, current_question)
        VALUES (?, 0, ?, 1)
    ");

    $stmt->bind_param("ii", $userId, $totalQuestions);
    $stmt->execute();

    $_SESSION["game_session_id"] = $stmt->insert_id;
    $_SESSION["game_score"] = 0;
    $_SESSION["game_question"] = 1;
    $_SESSION["banana_question"] = null;
    $_SESSION["banana_solution"] = null;
    $_SESSION["game_finished"] = false;

    $stmt->close();
}

$sessionId = (int) $_SESSION["game_session_id"];

$currentQuestion = (int) ($_SESSION["game_question"] ?? 1);
$currentScore = (int) ($_SESSION["game_score"] ?? 0);

$gameFinished = $_SESSION["game_finished"] ?? false;

$resultMessage = "";
$resultType = "";
$apiError = "";


/*
|--------------------------------------------------------------------------
| SUBMIT ANSWER
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["answer"]) &&
    !$gameFinished
) {

    $userAnswer = filter_var($_POST["answer"], FILTER_VALIDATE_INT);

    if ($userAnswer === false) {

        $resultMessage = "Please enter a valid number.";
        $resultType = "error";

    } elseif (!isset($_SESSION["banana_solution"])) {

        $resultMessage = "Question expired. Please try again.";
        $resultType = "error";

    } else {

        $correctAnswer = (int) $_SESSION["banana_solution"];
        $currentQuestion = (int) $_SESSION["game_question"];
        $isCorrect = ($userAnswer === $correctAnswer);

        // Update score
        if ($isCorrect) {
            $_SESSION["game_score"]++;
            $currentScore++;
        }

        // Save answer
        $correctValue = $isCorrect ? 1 : 0;

        $stmt = $conn->prepare("
            INSERT INTO game_results
            (
                session_id,
                question_number,
                user_answer,
                correct_answer,
                is_correct
            )
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "iiiii",
            $sessionId,
            $currentQuestion,
            $userAnswer,
            $correctAnswer,
            $correctValue
        );

        $stmt->execute();
        $stmt->close();

        // Game finished
        if ($currentQuestion >= $totalQuestions) {

            $finalScore = (int) $_SESSION["game_score"];

            $stmt = $conn->prepare("
                UPDATE game_sessions
                SET
                    score = ?,
                    current_question = ?
                WHERE id = ?
                AND user_id = ?
            ");

            $finalQuestion = $totalQuestions;

            $stmt->bind_param(
                "iiii",
                $finalScore,
                $finalQuestion,
                $sessionId,
                $userId
            );

            $stmt->execute();
            $stmt->close();

            // Update user's total statistics
            $stmt = $conn->prepare("
                UPDATE users
                SET
                    total_games = total_games + 1,
                    total_score = total_score + ?
                WHERE id = ?
            ");

            $stmt->bind_param("ii", $finalScore, $userId);
            $stmt->execute();
            $stmt->close();

            $_SESSION["game_finished"] = true;

            $gameFinished = true;
            $currentScore = $finalScore;

        } else {

            // Next question
            $_SESSION["game_question"]++;

            $_SESSION["banana_question"] = null;
            $_SESSION["banana_solution"] = null;

            $currentQuestion++;

            $resultMessage = $isCorrect
                ? "Correct! Great job."
                : "Incorrect. The correct answer was " . $correctAnswer . ".";

            $resultType = $isCorrect ? "success" : "error";
        }
    }
}


/*
|--------------------------------------------------------------------------
| GET NEW QUESTION
|--------------------------------------------------------------------------
*/

if (!$gameFinished) {

    if (
        empty($_SESSION["banana_question"]) ||
        !isset($_SESSION["banana_solution"])
    ) {

        $apiResult = getBananaQuestion();

        if (!$apiResult["success"]) {

            $apiError = $apiResult["message"];

        } else {

            $_SESSION["banana_question"] = $apiResult["question"];
            $_SESSION["banana_solution"] = $apiResult["solution"];
        }
    }
}


/*
|--------------------------------------------------------------------------
| REFRESH VARIABLES
|--------------------------------------------------------------------------
*/

$currentQuestion = (int) ($_SESSION["game_question"] ?? 1);
$currentScore = (int) ($_SESSION["game_score"] ?? 0);
$gameFinished = $_SESSION["game_finished"] ?? false;

// Progress percentage
$progress = (($currentQuestion - 1) / $totalQuestions) * 100;

if ($gameFinished) {
    $progress = 100;
}

// Message on the results screen
if ($currentScore >= 8) {
    $finalMessage = "Excellent work";
} elseif ($currentScore >= 5) {
    $finalMessage = "A solid game";
} else {
    $finalMessage = "Good try. The next game is a fresh start";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Play Game | Banana Game</title>

    <style>
        :root {
            --banana: #ffb703;
            --banana-soft: #fff4c7;
            --cream: #fffaf0;
            --ink: #1f1b12;
            --muted: #6f6a5c;
            --line: #e6e1d3;
            --danger: #b3261e;
            --danger-bg: #fdf0ef;
            --ok: #1e6b2e;
            --ok-bg: #edf7ef;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: "Segoe UI", system-ui, -apple-system, Roboto, Arial, sans-serif;
            background: var(--cream);
            color: var(--ink);
            min-height: 100vh;
            line-height: 1.5;
        }

        a:focus-visible, button:focus-visible, input:focus-visible {
            outline: 3px solid var(--ink);
            outline-offset: 3px;
        }

        /* Header */
        .site-header {
            background: rgba(255, 250, 240, .92);
            border-bottom: 1px solid var(--line);
        }

        .nav {
            max-width: 1080px;
            height: 68px;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .logo {
            color: var(--ink);
            text-decoration: none;
            font-size: 21px;
            font-weight: 800;
        }

        .nav-links { display: flex; align-items: center; gap: 4px; }

        .who {
            font-size: 14px;
            font-weight: 600;
            margin-right: 10px;
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .nav-link {
            padding: 9px 12px;
            border-radius: 10px;
            color: var(--muted);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }

        .nav-link:hover { color: var(--ink); background: #f5f1e4; }

        /* Main */
        .main { max-width: 820px; margin: 0 auto; padding: 44px 24px 72px; }

        .top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 24px;
        }

        .top h1 { font-size: 32px; letter-spacing: -0.6px; line-height: 1.15; margin-bottom: 4px; }
        .top p { color: var(--muted); font-size: 15px; }

        .score {
            min-width: 92px;
            padding: 10px 18px;
            border-radius: 14px;
            background: var(--banana);
            text-align: center;
        }

        .score small { display: block; font-size: 12px; font-weight: 600; }
        .score strong { display: block; font-size: 28px; line-height: 1.1; font-weight: 800; }

        /* Progress */
        .progress { margin-bottom: 22px; }

        .progress-info {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 8px;
        }

        .bar { height: 8px; background: #ece7d8; border-radius: 20px; overflow: hidden; }

        .bar-fill {
            height: 100%;
            background: var(--banana);
            width: <?php echo $progress; ?>%;
            transition: width .3s ease;
        }

        /* Card */
        .card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 18px 50px rgba(60, 45, 0, .08);
        }

        .card-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .q-label { font-size: 14px; font-weight: 700; }

        .tag {
            background: var(--banana-soft);
            color: #7a5a00;
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 700;
        }

        .alert {
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 20px;
            border: 1px solid;
        }

        .alert.success { background: var(--ok-bg); color: var(--ok); border-color: #bfe0c6; }
        .alert.error { background: var(--danger-bg); color: var(--danger); border-color: #f2c7c4; }

        .question h2 { font-size: 18px; text-align: center; margin-bottom: 16px; }

        .puzzle {
            background: #fafaf6;
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 280px;
            margin-bottom: 26px;
        }

        .puzzle img {
            display: block;
            max-width: 100%;
            max-height: 460px;
            object-fit: contain;
            border-radius: 6px;
        }

        .answer-form { max-width: 480px; margin: 0 auto; }

        .answer-form label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .answer-row { display: flex; gap: 10px; }

        .answer-row input {
            flex: 1;
            min-width: 0;
            height: 50px;
            padding: 0 14px;
            font: inherit;
            font-size: 16px;
            color: var(--ink);
            border: 1px solid #d6d0bf;
            border-radius: 10px;
            transition: border-color .15s, box-shadow .15s;
        }

        .answer-row input::placeholder { color: #a59f8d; }

        .answer-row input:focus {
            outline: none;
            border-color: var(--banana);
            box-shadow: 0 0 0 3px rgba(255, 183, 3, .25);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 50px;
            padding: 0 26px;
            border: none;
            border-radius: 10px;
            font: inherit;
            font-size: 15px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: background .15s;
        }

        .btn-dark { background: var(--ink); color: #fff; }
        .btn-dark:hover { background: #000; }

        .btn-yellow { background: var(--banana); color: var(--ink); }
        .btn-yellow:hover { background: #f2aa00; }

        .btn-ghost { background: #fff; color: var(--ink); border: 1px solid #d6d0bf; }
        .btn-ghost:hover { border-color: var(--ink); }

        /* Finished */
        .finished { text-align: center; padding: 28px 12px 12px; }
        .finished .icon { font-size: 52px; margin-bottom: 10px; }
        .finished h2 { font-size: 30px; letter-spacing: -0.5px; margin-bottom: 6px; }
        .finished p { color: var(--muted); }

        .final-score { font-size: 64px; font-weight: 800; letter-spacing: -2px; margin: 18px 0 6px; }
        .final-score small { font-size: 26px; color: var(--muted); font-weight: 600; letter-spacing: 0; }

        .finished-actions { display: flex; justify-content: center; flex-wrap: wrap; gap: 10px; margin-top: 26px; }

        @media (max-width: 650px) {
            .nav { padding: 0 16px; }
            .who { display: none; }
            .nav-link { padding: 8px; font-size: 13px; }
            .main { padding: 28px 14px 52px; }
            .top h1 { font-size: 26px; }
            .card { padding: 20px 16px; }
            .puzzle { min-height: 200px; padding: 10px; }
            .answer-row { flex-direction: column; }
            .btn { width: 100%; }
            .final-score { font-size: 52px; }
        }

        @media (prefers-reduced-motion: reduce) {
            .bar-fill { transition: none; }
        }
    </style>
</head>
<body>

<header class="site-header">
    <div class="nav">

        <!-- Home for logged-in users is the game -->
        <a href="play.php" class="logo">🍌 Banana Game</a>

        <nav class="nav-links" aria-label="Main">
            <span class="who"><?php echo htmlspecialchars($username); ?></span>
            <a href="leaderboard.php" class="nav-link">Leaderboard</a>
            <a href="profile.php" class="nav-link">Profile</a>
            <a href="../auth/logout.php" class="nav-link">Log out</a>
        </nav>

    </div>
</header>

<main class="main">

    <div class="top">
        <div>
            <h1>Banana Challenge</h1>
            <p>Solve the puzzle and enter your answer.</p>
        </div>

        <div class="score" aria-label="Score">
            <small>Score</small>
            <strong><?php echo $currentScore; ?></strong>
        </div>
    </div>

    <div class="progress">
        <div class="progress-info">
            <span>
                <?php if ($gameFinished): ?>
                    Game complete
                <?php else: ?>
                    Question <?php echo min($currentQuestion, $totalQuestions); ?> of <?php echo $totalQuestions; ?>
                <?php endif; ?>
            </span>
            <span><?php echo $currentScore; ?> correct</span>
        </div>

        <div class="bar"><div class="bar-fill"></div></div>
    </div>

    <div class="card">

        <?php if ($gameFinished): ?>

            <div class="finished">
                <div class="icon">🏆</div>
                <h2>Game complete</h2>
                <p><?php echo htmlspecialchars($finalMessage); ?>, <?php echo htmlspecialchars($username); ?>.</p>

                <div class="final-score">
                    <?php echo $currentScore; ?><small>/<?php echo $totalQuestions; ?></small>
                </div>

                <div class="finished-actions">
                    <!-- Starts a completely new game -->
                    <a href="new_game.php" class="btn btn-yellow">Play again</a>
                    <a href="leaderboard.php" class="btn btn-ghost">View leaderboard</a>
                </div>
            </div>

        <?php else: ?>

            <div class="card-head">
                <span class="q-label">Question <?php echo $currentQuestion; ?></span>
                <span class="tag">🍌 Challenge</span>
            </div>

            <?php if ($resultMessage !== ""): ?>
                <div class="alert <?php echo $resultType; ?>" role="status">
                    <?php echo htmlspecialchars($resultMessage); ?>
                </div>
            <?php endif; ?>

            <?php if ($apiError !== ""): ?>

                <div class="alert error" role="alert">
                    <?php echo htmlspecialchars($apiError); ?>
                </div>

            <?php elseif (!empty($_SESSION["banana_question"])): ?>

                <div class="question">

                    <h2>What is the answer?</h2>

                    <div class="puzzle">
                        <img
                            src="<?php echo htmlspecialchars($_SESSION["banana_question"]); ?>"
                            alt="Banana puzzle"
                        >
                    </div>

                    <form method="POST" class="answer-form">

                        <label for="answer">Your answer</label>

                        <div class="answer-row">
                            <input
                                type="number"
                                name="answer"
                                id="answer"
                                placeholder="Enter a number"
                                required
                                autofocus
                            >

                            <button type="submit" class="btn btn-dark">Submit</button>
                        </div>

                    </form>

                </div>

            <?php endif; ?>

        <?php endif; ?>

    </div>

</main>

</body>
</html>