<?php

session_start();

require_once "../config/db.php";

if (isset($_SESSION["user_id"])) {
    header("Location: ../game/play.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $login = trim($_POST["login"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($login === "" || $password === "") {
        $error = "Enter your username or email and your password.";
    } else {

        $stmt = $conn->prepare(
            "SELECT id, username, email, password
             FROM users
             WHERE username = ? OR email = ?
             LIMIT 1"
        );

        $stmt->bind_param("ss", $login, $login);
        $stmt->execute();

        $result = $stmt->get_result();
        $user = $result->num_rows === 1 ? $result->fetch_assoc() : null;
        $stmt->close();

        if ($user && password_verify($password, $user["password"])) {

            session_regenerate_id(true);

            $_SESSION["user_id"] = $user["id"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["email"] = $user["email"];

            header("Location: ../game/play.php");
            exit;
        }

        $error = "Username, email or password is incorrect.";
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log in | Banana Game</title>
    <style>
        /* Shared styles for login.php and register.php */
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

        .page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 20px;
        }

        .auth-box {
            width: 100%;
            max-width: 960px;
            display: grid;
            grid-template-columns: 5fr 6fr;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 18px 50px rgba(60, 45, 0, .08);
        }

        /* Brand panel */
        .brand {
            background: var(--banana);
            padding: 44px 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 40px;
        }

        .logo {
            color: var(--ink);
            text-decoration: none;
            font-size: 22px;
            font-weight: 800;
        }

        .brand h1 {
            font-size: 38px;
            line-height: 1.12;
            letter-spacing: -0.5px;
            margin-bottom: 14px;
        }

        .brand p {
            font-size: 15px;
            color: #3d3000;
            max-width: 340px;
        }

        .points { list-style: none; margin-top: 28px; }

        .points li {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            font-size: 14px;
            color: #3d3000;
            padding: 10px 0;
            border-top: 1px solid rgba(31, 27, 18, .18);
        }

        .points b { color: var(--ink); }

        /* Form panel */
        .form-side {
            padding: 48px 52px;
            display: flex;
            align-items: center;
        }

        .form-wrap { width: 100%; max-width: 380px; margin: 0 auto; }

        .form-wrap h2 { font-size: 28px; letter-spacing: -0.3px; margin-bottom: 6px; }

        .lead { color: var(--muted); font-size: 14px; margin-bottom: 26px; }

        .alert {
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 14px;
            margin-bottom: 20px;
            border: 1px solid;
        }

        .alert.error { background: var(--danger-bg); color: var(--danger); border-color: #f2c7c4; }
        .alert.success { background: var(--ok-bg); color: var(--ok); border-color: #bfe0c6; }

        .field { margin-bottom: 18px; }

        .field label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .control { position: relative; }

        .control input {
            width: 100%;
            height: 48px;
            padding: 0 14px;
            font-size: 15px;
            font-family: inherit;
            color: var(--ink);
            background: #fff;
            border: 1px solid #d6d0bf;
            border-radius: 10px;
            transition: border-color .15s, box-shadow .15s;
        }

        .control input::placeholder { color: #a59f8d; }

        .control input:focus {
            outline: none;
            border-color: var(--banana);
            box-shadow: 0 0 0 3px rgba(255, 183, 3, .25);
        }

        .control.has-toggle input { padding-right: 68px; }

        .toggle {
            position: absolute;
            right: 6px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: none;
            font: inherit;
            font-size: 13px;
            font-weight: 600;
            color: var(--muted);
            padding: 8px 10px;
            border-radius: 8px;
            cursor: pointer;
        }

        .toggle:hover { color: var(--ink); background: #f5f1e4; }

        .hint { font-size: 12px; color: var(--muted); margin-top: 5px; }

        .btn {
            width: 100%;
            height: 50px;
            margin-top: 6px;
            border: none;
            border-radius: 10px;
            background: var(--ink);
            color: #fff;
            font: inherit;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: background .15s;
        }

        .btn:hover { background: #000; }

        .switch {
            margin-top: 24px;
            text-align: center;
            font-size: 14px;
            color: var(--muted);
        }

        .switch a, .back {
            color: var(--ink);
            font-weight: 600;
            text-decoration-color: var(--banana);
            text-decoration-thickness: 2px;
            text-underline-offset: 3px;
        }

        .back {
            display: block;
            text-align: center;
            margin-top: 14px;
            font-size: 13px;
            font-weight: 500;
            color: var(--muted);
            text-decoration: none;
        }

        .back:hover { color: var(--ink); }

        a:focus-visible, button:focus-visible {
            outline: 3px solid var(--ink);
            outline-offset: 2px;
        }

        @media (max-width: 800px) {
            .auth-box { grid-template-columns: 1fr; max-width: 480px; }
            .brand { padding: 28px 26px; gap: 20px; }
            .brand h1 { font-size: 28px; }
            .points { display: none; }
            .form-side { padding: 32px 26px 36px; }
        }

        @media (max-width: 480px) {
            .page { padding: 0; }
            .auth-box { border-radius: 0; border: none; min-height: 100vh; }
        }
    </style>
</head>
<body>

<div class="page">
    <div class="auth-box">

        <section class="brand">
            <a href="../index.php" class="logo">🍌 Banana Game</a>

            <div>
                <h1>Pick up where you left off.</h1>
                <p>Log in to keep solving banana puzzles and climbing the leaderboard.</p>

                <ul class="points">
                    <li><span>🎯</span><span><b>10 puzzles per game.</b> Solve each one and enter the answer.</span></li>
                    <li><span>🏆</span><span><b>Your scores are saved.</b> See every game you have played.</span></li>
                    <li><span>📊</span><span><b>Compare with others</b> on the leaderboard.</span></li>
                </ul>
            </div>
        </section>

        <section class="form-side">
            <div class="form-wrap">

                <h2>Log in</h2>
                <p class="lead">Use your username or email address.</p>

                <?php if ($error !== ""): ?>
                    <div class="alert error" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">

                    <div class="field">
                        <label for="login">Username or email</label>
                        <div class="control">
                            <input
                                type="text"
                                id="login"
                                name="login"
                                value="<?php echo htmlspecialchars($_POST["login"] ?? ""); ?>"
                                autocomplete="username"
                                required
                                autofocus
                            >
                        </div>
                    </div>

                    <div class="field">
                        <label for="password">Password</label>
                        <div class="control has-toggle">
                            <input
                                type="password"
                                id="password"
                                name="password"
                                autocomplete="current-password"
                                required
                            >
                            <button type="button" class="toggle" data-target="password">Show</button>
                        </div>
                    </div>

                    <button type="submit" class="btn">Log in</button>

                </form>

                <p class="switch">
                    New here? <a href="register.php">Create an account</a>
                </p>

                <a href="../index.php" class="back">Back to home</a>

            </div>
        </section>

    </div>
</div>

<script>
document.querySelectorAll(".toggle").forEach(function (btn) {
    btn.addEventListener("click", function () {
        var input = document.getElementById(btn.dataset.target);
        var show = input.type === "password";
        input.type = show ? "text" : "password";
        btn.textContent = show ? "Hide" : "Show";
    });
});
</script>

</body>
</html>