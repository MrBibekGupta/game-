<?php

session_start();

$isLoggedIn = isset($_SESSION["user_id"]);
$playLink = $isLoggedIn ? "game/play.php" : "auth/register.php";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Banana Game - Solve 10 banana puzzles, save your score and climb the leaderboard.">
    <title>Banana Game | Solve puzzles, beat your score</title>

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

        html { scroll-behavior: smooth; scroll-padding-top: 80px; }

        body {
            font-family: "Segoe UI", system-ui, -apple-system, Roboto, Arial, sans-serif;
            background: var(--cream);
            color: var(--ink);
            line-height: 1.55;
        }

        a { color: inherit; }

        a:focus-visible, button:focus-visible {
            outline: 3px solid var(--ink);
            outline-offset: 3px;
        }

        .container { max-width: 1080px; margin: 0 auto; padding: 0 24px; }

        /* Header */
        .site-header {
            position: sticky;
            top: 0;
            z-index: 10;
            background: rgba(255, 250, 240, .92);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid var(--line);
        }

        .nav {
            height: 68px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .logo {
            text-decoration: none;
            font-size: 21px;
            font-weight: 800;
        }

        .menu { display: flex; gap: 26px; }

        .menu a {
            text-decoration: none;
            font-size: 14px;
            color: var(--muted);
        }

        .menu a:hover { color: var(--ink); }

        .nav-auth { display: flex; align-items: center; gap: 8px; }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 42px;
            padding: 0 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            border: 1px solid transparent;
            transition: background .15s, border-color .15s;
        }

        .btn-dark { background: var(--ink); color: #fff; }
        .btn-dark:hover { background: #000; }

        .btn-yellow { background: var(--banana); color: var(--ink); }
        .btn-yellow:hover { background: #f2aa00; }

        .btn-ghost { color: var(--ink); border-color: #d6d0bf; background: #fff; }
        .btn-ghost:hover { border-color: var(--ink); }

        .btn-link { color: var(--muted); font-weight: 600; }
        .btn-link:hover { color: var(--ink); }

        .btn-lg { height: 52px; padding: 0 28px; font-size: 15px; }

        /* Hero */
        .hero { padding: 72px 0 84px; }

        .hero-grid {
            display: grid;
            grid-template-columns: 6fr 5fr;
            gap: 56px;
            align-items: center;
        }

        .hero h1 {
            font-size: 58px;
            line-height: 1.05;
            letter-spacing: -1.5px;
            margin-bottom: 20px;
        }

        .hero h1 mark {
            background: linear-gradient(transparent 62%, var(--banana) 62%);
            color: inherit;
        }

        .hero p.lead {
            font-size: 18px;
            color: var(--muted);
            max-width: 480px;
            margin-bottom: 30px;
        }

        .hero-actions { display: flex; flex-wrap: wrap; gap: 10px; }

        .hero-facts {
            display: flex;
            gap: 28px;
            margin-top: 36px;
            padding-top: 22px;
            border-top: 1px solid var(--line);
            font-size: 14px;
            color: var(--muted);
        }

        .hero-facts b { color: var(--ink); font-size: 15px; }

        /* Game preview (mirrors the real play page) */
        .preview {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 18px 50px rgba(60, 45, 0, .09);
        }

        .preview-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 8px;
        }

        .bar { height: 8px; background: #eee; border-radius: 20px; overflow: hidden; margin-bottom: 20px; }
        .bar i { display: block; height: 100%; width: 30%; background: var(--banana); }

        .preview-q {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
            font-size: 13px;
            font-weight: 700;
        }

        .tag {
            background: var(--banana-soft);
            color: #7a5a00;
            padding: 5px 11px;
            border-radius: 30px;
            font-size: 11px;
        }

        .puzzle {
            background: #fafafa;
            border: 1px solid #eee;
            border-radius: 14px;
            padding: 22px 12px;
            text-align: center;
            margin-bottom: 16px;
        }

        .puzzle .row { font-size: 26px; letter-spacing: 3px; }
        .puzzle .row + .row { margin-top: 8px; }
        .puzzle .row span { font-size: 18px; font-weight: 700; color: var(--muted); margin: 0 6px; }

        .answer-row { display: flex; gap: 8px; }

        .fake-input {
            flex: 1;
            height: 44px;
            border: 1px solid #d6d0bf;
            border-radius: 10px;
            padding: 0 14px;
            display: flex;
            align-items: center;
            font-size: 14px;
            color: #a59f8d;
        }

        .fake-btn {
            height: 44px;
            padding: 0 18px;
            border-radius: 10px;
            background: var(--ink);
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            display: flex;
            align-items: center;
        }

        /* Sections */
        section.block { padding: 80px 0; }
        section.block.alt { background: #fff; border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); }

        .section-head { max-width: 560px; margin-bottom: 44px; }

        .section-head h2 {
            font-size: 36px;
            line-height: 1.15;
            letter-spacing: -0.8px;
            margin-bottom: 12px;
        }

        .section-head p { color: var(--muted); font-size: 16px; }

        /* Steps: a real sequence, so numbered */
        .steps {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            list-style: none;
            counter-reset: step;
            border-top: 2px solid var(--ink);
        }

        .steps li {
            counter-increment: step;
            padding: 22px 22px 0 0;
        }

        .steps li::before {
            content: counter(step);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--banana);
            font-weight: 800;
            font-size: 14px;
            margin-bottom: 14px;
        }

        .steps h3 { font-size: 18px; margin-bottom: 6px; }
        .steps p { font-size: 14px; color: var(--muted); }

        /* Features */
        .features {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0 48px;
        }

        .feature {
            padding: 24px 0;
            border-top: 1px solid var(--line);
            display: flex;
            gap: 16px;
        }

        .feature .icon {
            flex-shrink: 0;
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: var(--banana-soft);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .feature h3 { font-size: 17px; margin-bottom: 4px; }
        .feature p { font-size: 14px; color: var(--muted); }

        /* CTA */
        .cta {
            background: var(--banana);
            border-radius: 20px;
            padding: 56px 40px;
            text-align: center;
        }

        .cta h2 { font-size: 34px; letter-spacing: -0.6px; margin-bottom: 10px; }
        .cta p { color: #3d3000; margin-bottom: 26px; }
        .cta-actions { display: flex; justify-content: center; flex-wrap: wrap; gap: 10px; }

        /* Footer */
        .site-footer { padding: 40px 0 48px; }

        .footer-inner {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            padding-top: 28px;
            border-top: 1px solid var(--line);
        }

        .footer-links { display: flex; flex-wrap: wrap; gap: 20px; }
        .footer-links a { font-size: 14px; color: var(--muted); text-decoration: none; }
        .footer-links a:hover { color: var(--ink); }
        .copy { width: 100%; font-size: 13px; color: var(--muted); }

        /* Responsive */
        @media (max-width: 900px) {
            .hero { padding: 48px 0 60px; }
            .hero-grid { grid-template-columns: 1fr; gap: 40px; }
            .hero h1 { font-size: 44px; }
            .steps { grid-template-columns: repeat(2, 1fr); }
            .steps li { padding-bottom: 22px; }
            .features { grid-template-columns: 1fr; }
            .menu { display: none; }
        }

        @media (max-width: 520px) {
            .container { padding: 0 18px; }
            .hero h1 { font-size: 36px; letter-spacing: -1px; }
            .hero-facts { gap: 18px; }
            .steps { grid-template-columns: 1fr; }
            .section-head h2 { font-size: 29px; }
            .cta { padding: 40px 22px; }
            .cta h2 { font-size: 27px; }
            .btn-lg { width: 100%; }
            .btn-hide-sm { display: none; }
        }

        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
        }
    </style>
</head>
<body>

<header class="site-header">
    <div class="container nav">

        <a href="index.php" class="logo">🍌 Banana Game</a>

        <nav class="menu" aria-label="Main">
            <a href="#how-to-play">How to play</a>
            <a href="#features">Features</a>
            <a href="game/leaderboard.php">Leaderboard</a>
        </nav>

        <div class="nav-auth">
            <?php if ($isLoggedIn): ?>
                <a href="game/profile.php" class="btn btn-link btn-hide-sm">Profile</a>
                <a href="game/play.php" class="btn btn-dark">Play now</a>
            <?php else: ?>
                <a href="auth/login.php" class="btn btn-link">Log in</a>
                <a href="auth/register.php" class="btn btn-dark">Create account</a>
            <?php endif; ?>
        </div>

    </div>
</header>

<main>

    <!-- Hero -->
    <section class="hero">
        <div class="container hero-grid">

            <div>
                <h1>Think. <mark>Solve.</mark> Score.</h1>

                <p class="lead">
                    Ten banana puzzles per game. Work out each answer,
                    save your score and see how you rank against other players.
                </p>

                <div class="hero-actions">
                    <a href="<?php echo $playLink; ?>" class="btn btn-yellow btn-lg">
                        <?php echo $isLoggedIn ? "Play game" : "Start playing"; ?>
                    </a>

                    <?php if ($isLoggedIn): ?>
                        <a href="game/leaderboard.php" class="btn btn-ghost btn-lg">View leaderboard</a>
                    <?php else: ?>
                        <a href="auth/login.php" class="btn btn-ghost btn-lg">I have an account</a>
                    <?php endif; ?>
                </div>

                <div class="hero-facts">
                    <div><b>10</b><br>puzzles per game</div>
                    <div><b>Free</b><br>to play</div>
                    <div><b>Saved</b><br>scores</div>
                </div>
            </div>

            <div class="preview" aria-hidden="true">
                <div class="preview-top">
                    <span>Question 3 of 10</span>
                    <span>2 correct</span>
                </div>
                <div class="bar"><i></i></div>

                <div class="preview-q">
                    <span>What is the answer?</span>
                    <span class="tag">🍌 Challenge</span>
                </div>

                <div class="puzzle">
                    <div class="row">🍌🍌🍌 <span>=</span> 12</div>
                    <div class="row">🍌 <span>+</span> 🍌🍌 <span>=</span> ?</div>
                </div>

                <div class="answer-row">
                    <div class="fake-input">Enter answer</div>
                    <div class="fake-btn">Submit</div>
                </div>
            </div>

        </div>
    </section>

    <!-- How to play -->
    <section class="block alt" id="how-to-play">
        <div class="container">

            <div class="section-head">
                <h2>How to play</h2>
                <p>No complicated rules. Look closely, think it through and type the number.</p>
            </div>

            <ol class="steps">
                <li>
                    <h3>Look</h3>
                    <p>Study the puzzle image and spot how the bananas relate to each other.</p>
                </li>
                <li>
                    <h3>Work it out</h3>
                    <p>Use logic and simple maths to find the value you are asked for.</p>
                </li>
                <li>
                    <h3>Answer</h3>
                    <p>Enter your answer and find out straight away if it was right.</p>
                </li>
                <li>
                    <h3>Score</h3>
                    <p>Finish all 10 puzzles and your score goes on the leaderboard.</p>
                </li>
            </ol>

        </div>
    </section>

    <!-- Features -->
    <section class="block" id="features">
        <div class="container">

            <div class="section-head">
                <h2>Built to make you think</h2>
                <p>Every round tests observation and logic, and every result is saved to your account.</p>
            </div>

            <div class="features">
                <div class="feature">
                    <span class="icon">🧩</span>
                    <div>
                        <h3>Real brain teasers</h3>
                        <p>Each puzzle needs careful observation, not guessing.</p>
                    </div>
                </div>

                <div class="feature">
                    <span class="icon">🔄</span>
                    <div>
                        <h3>New puzzles every game</h3>
                        <p>Play again and get a fresh set of questions.</p>
                    </div>
                </div>

                <div class="feature">
                    <span class="icon">📊</span>
                    <div>
                        <h3>Your score history</h3>
                        <p>Your games and totals are linked to your profile.</p>
                    </div>
                </div>

                <div class="feature">
                    <span class="icon">🏆</span>
                    <div>
                        <h3>Leaderboard</h3>
                        <p>See how your score compares with other players.</p>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- CTA -->
    <section class="block" style="padding-top:0">
        <div class="container">
            <div class="cta">
                <h2>Can you solve all ten?</h2>

                <p>
                    <?php echo $isLoggedIn
                        ? "Your account is ready. Start a new game whenever you like."
                        : "Create a free account and start your first game."; ?>
                </p>

                <div class="cta-actions">
                    <a href="<?php echo $playLink; ?>" class="btn btn-dark btn-lg">
                        <?php echo $isLoggedIn ? "Play Banana Game" : "Create your account"; ?>
                    </a>

                    <?php if (!$isLoggedIn): ?>
                        <a href="auth/login.php" class="btn btn-ghost btn-lg">Log in</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

</main>

<footer class="site-footer">
    <div class="container footer-inner">

        <a href="index.php" class="logo">🍌 Banana Game</a>

        <div class="footer-links">
            <a href="#how-to-play">How to play</a>
            <a href="#features">Features</a>
            <a href="game/leaderboard.php">Leaderboard</a>

            <?php if ($isLoggedIn): ?>
                <a href="game/profile.php">Profile</a>
                <a href="game/play.php">Play game</a>
                <a href="auth/logout.php">Log out</a>
            <?php else: ?>
                <a href="auth/login.php">Log in</a>
                <a href="auth/register.php">Register</a>
            <?php endif; ?>
        </div>

        <p class="copy">&copy; <?php echo date("Y"); ?> Banana Game. All rights reserved.</p>

    </div>
</footer>

</body>
</html>