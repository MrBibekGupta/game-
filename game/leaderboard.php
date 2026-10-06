<?php

session_start();

require_once "../config/db.php";

$isLoggedIn = isset($_SESSION["user_id"]);
$currentUserId = $isLoggedIn ? (int) $_SESSION["user_id"] : 0;

// Registered users: home is the game. Visitors: home is the landing page.
$homeLink = $isLoggedIn ? "play.php" : "../index.php";
$playLink = $isLoggedIn ? "play.php" : "../auth/register.php";

$sql = "
    SELECT
        u.id,
        u.username,
        u.profile_image,
        u.total_games,
        u.total_score,
        COALESCE(MAX(gs.score), 0) AS best_score
    FROM users u
    LEFT JOIN game_sessions gs
        ON u.id = gs.user_id
    GROUP BY
        u.id,
        u.username,
        u.profile_image,
        u.total_games,
        u.total_score
    ORDER BY
        best_score DESC,
        u.total_score DESC,
        u.username ASC
";

$result = $conn->query($sql);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard | Banana Game</title>
    <style>
        :root {
            --banana: #ffb703;
            --banana-soft: #fff4c7;
            --cream: #fffaf0;
            --ink: #1f1b12;
            --muted: #6f6a5c;
            --line: #e6e1d3;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: "Segoe UI", system-ui, -apple-system, Roboto, Arial, sans-serif;
            background: var(--cream);
            color: var(--ink);
            min-height: 100vh;
            line-height: 1.5;
        }

        a:focus-visible, button:focus-visible {
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

        .nav-links { display: flex; align-items: center; gap: 8px; }

        .btn {
            display: inline-flex;
            align-items: center;
            height: 40px;
            padding: 0 18px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            transition: background .15s;
        }

        .btn-link { color: var(--muted); }
        .btn-link:hover { color: var(--ink); }

        .btn-dark { background: var(--ink); color: #fff; }
        .btn-dark:hover { background: #000; }

        .btn-yellow { background: var(--banana); color: var(--ink); }
        .btn-yellow:hover { background: #f2aa00; }

        /* Main */
        .main { max-width: 860px; margin: 0 auto; padding: 56px 24px 72px; }

        .heading { margin-bottom: 28px; }

        .heading h1 {
            font-size: 38px;
            letter-spacing: -0.8px;
            line-height: 1.15;
            margin-bottom: 8px;
        }

        .heading p { color: var(--muted); font-size: 15px; }

        /* Table */
        .board {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 18px 50px rgba(60, 45, 0, .08);
        }

        .cols {
            display: grid;
            grid-template-columns: 72px 1fr 120px 120px;
            align-items: center;
            padding: 0 26px;
        }

        .board-head {
            height: 48px;
            border-bottom: 1px solid var(--line);
            background: #fdfbf4;
            color: var(--muted);
            font-size: 13px;
            font-weight: 600;
        }

        .row {
            min-height: 68px;
            border-bottom: 1px solid #f0ecdf;
        }

        .row:last-child { border-bottom: none; }

        .row.me { background: var(--banana-soft); }

        .rank { font-size: 15px; font-weight: 800; color: var(--muted); }
        .rank.medal { font-size: 22px; }

        .player { display: flex; align-items: center; gap: 12px; min-width: 0; }

        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--banana);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 800;
            overflow: hidden;
            flex-shrink: 0;
        }

        .avatar img { width: 100%; height: 100%; object-fit: cover; }

        .name {
            font-size: 15px;
            font-weight: 700;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .you {
            margin-left: 6px;
            padding: 2px 8px;
            background: var(--ink);
            color: #fff;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }

        .score { font-size: 16px; font-weight: 800; }
        .score small { color: var(--muted); font-weight: 600; font-size: 13px; }
        .games { font-size: 14px; color: var(--muted); }

        /* Empty */
        .empty { text-align: center; padding: 64px 24px; }
        .empty .icon { font-size: 44px; margin-bottom: 12px; }
        .empty h2 { font-size: 22px; margin-bottom: 6px; }
        .empty p { color: var(--muted); font-size: 14px; margin-bottom: 22px; }

        .footer { text-align: center; padding: 8px 24px 36px; color: var(--muted); font-size: 13px; }

        @media (max-width: 640px) {
            .nav { padding: 0 16px; }
            .btn-hide-sm { display: none; }
            .main { padding: 36px 14px 56px; }
            .heading h1 { font-size: 30px; }
            .cols { grid-template-columns: 48px 1fr 70px; padding: 0 16px; }
            .cols > :nth-child(4) { display: none; }
            .rank.medal { font-size: 20px; }
        }
    </style>
</head>
<body>

<header class="site-header">
    <div class="nav">

        <a href="<?php echo $homeLink; ?>" class="logo">🍌 Banana Game</a>

        <nav class="nav-links" aria-label="Main">
            <?php if ($isLoggedIn): ?>
                <a href="profile.php" class="btn btn-link">Profile</a>
                <a href="../auth/logout.php" class="btn btn-link btn-hide-sm">Log out</a>
                <a href="play.php" class="btn btn-yellow">Play game</a>
            <?php else: ?>
                <a href="../index.php" class="btn btn-link btn-hide-sm">Home</a>
                <a href="../auth/login.php" class="btn btn-link">Log in</a>
                <a href="../auth/register.php" class="btn btn-dark">Create account</a>
            <?php endif; ?>
        </nav>

    </div>
</header>

<main class="main">

    <div class="heading">
        <h1>Leaderboard</h1>
        <p>Players are ranked by best score in a single game, then by total score.</p>
    </div>

    <div class="board">

        <?php if ($result && $result->num_rows > 0): ?>

            <div class="cols board-head">
                <span>Rank</span>
                <span>Player</span>
                <span>Best score</span>
                <span>Games</span>
            </div>

            <?php
            $rank = 1;
            $medals = [1 => "🥇", 2 => "🥈", 3 => "🥉"];

            while ($player = $result->fetch_assoc()):
                $isMe = $currentUserId === (int) $player["id"];
                $initial = strtoupper(substr($player["username"], 0, 1));
            ?>

                <div class="cols row<?php echo $isMe ? " me" : ""; ?>">

                    <div class="rank<?php echo $rank <= 3 ? " medal" : ""; ?>">
                        <?php echo $rank <= 3 ? $medals[$rank] : "#" . $rank; ?>
                    </div>

                    <div class="player">
                        <div class="avatar">
                            <?php if (!empty($player["profile_image"])): ?>
                                <img
                                    src="../<?php echo htmlspecialchars($player["profile_image"]); ?>"
                                    alt=""
                                >
                            <?php else: ?>
                                <?php echo htmlspecialchars($initial); ?>
                            <?php endif; ?>
                        </div>

                        <div class="name">
                            <?php echo htmlspecialchars($player["username"]); ?>
                            <?php if ($isMe): ?><span class="you">You</span><?php endif; ?>
                        </div>
                    </div>

                    <div class="score">
                        <?php echo (int) $player["best_score"]; ?><small>/10</small>
                    </div>

                    <div class="games">
                        <?php echo (int) $player["total_games"]; ?>
                    </div>

                </div>

            <?php
                $rank++;
            endwhile;
            ?>

        <?php else: ?>

            <div class="empty">
                <div class="icon">🍌</div>
                <h2>No players yet</h2>
                <p>Finish a game to be the first name on the board.</p>
                <a href="<?php echo $playLink; ?>" class="btn btn-yellow">
                    <?php echo $isLoggedIn ? "Start playing" : "Create account"; ?>
                </a>
            </div>

        <?php endif; ?>

    </div>

</main>

<footer class="footer">
    &copy; <?php echo date("Y"); ?> Banana Game. All rights reserved.
</footer>

</body>
</html>