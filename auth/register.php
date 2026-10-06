<?php

session_start();

require_once __DIR__ . "/../config/db.php";

if (isset($_SESSION["user_id"])) {
    header("Location: ../index.php");
    exit;
}

$username = "";
$email = "";
$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    if ($username === "" || $email === "" || $password === "" || $confirmPassword === "") {
        $error = "Fill in all fields.";
    } elseif (strlen($username) < 3) {
        $error = "Username must be at least 3 characters.";
    } elseif (strlen($username) > 50) {
        $error = "Username can be at most 50 characters.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $confirmPassword) {
        $error = "The two passwords do not match.";
    } else {

        $checkStmt = $conn->prepare("SELECT username, email FROM users WHERE username = ? OR email = ? LIMIT 2");
        $checkStmt->bind_param("ss", $username, $email);
        $checkStmt->execute();
        $rows = $checkStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $checkStmt->close();

        $usernameTaken = false;
        foreach ($rows as $row) {
            if (strcasecmp($row["username"], $username) === 0) {
                $usernameTaken = true;
            }
        }

        if ($usernameTaken) {
            $error = "That username is taken. Try another one.";
        } elseif (count($rows) > 0) {
            $error = "An account with this email already exists.";
        } else {

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $insertStmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $insertStmt->bind_param("sss", $username, $email, $hashedPassword);

            if ($insertStmt->execute()) {
                $success = "Account created. You can log in now.";
                $username = "";
                $email = "";
            } else {
                $error = "Could not create the account. Try again.";
            }

            $insertStmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create account | Banana Game</title>
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
                <h1>Solve puzzles. Beat your best score.</h1>
                <p>Create a free account to save your games and appear on the leaderboard.</p>

                <ul class="points">
                    <li><span>🎯</span><span><b>10 puzzles per game.</b> Solve each one and enter the answer.</span></li>
                    <li><span>🏆</span><span><b>Your scores are saved.</b> See every game you have played.</span></li>
                    <li><span>📊</span><span><b>Compare with others</b> on the leaderboard.</span></li>
                </ul>
            </div>
        </section>

        <section class="form-side">
            <div class="form-wrap">

                <h2>Create your account</h2>
                <p class="lead">It takes less than a minute.</p>

                <?php if ($error !== ""): ?>
                    <div class="alert error" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success !== ""): ?>
                    <div class="alert success" role="status">
                        <?php echo htmlspecialchars($success); ?>
                        <a href="login.php">Log in</a>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">

                    <div class="field">
                        <label for="username">Username</label>
                        <div class="control">
                            <input
                                type="text"
                                id="username"
                                name="username"
                                value="<?php echo htmlspecialchars($username); ?>"
                                autocomplete="username"
                                maxlength="50"
                                required
                                autofocus
                            >
                        </div>
                    </div>

                    <div class="field">
                        <label for="email">Email address</label>
                        <div class="control">
                            <input
                                type="email"
                                id="email"
                                name="email"
                                value="<?php echo htmlspecialchars($email); ?>"
                                placeholder="you@example.com"
                                autocomplete="email"
                                required
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
                                autocomplete="new-password"
                                required
                            >
                            <button type="button" class="toggle" data-target="password">Show</button>
                        </div>
                        <p class="hint">At least 6 characters.</p>
                    </div>

                    <div class="field">
                        <label for="confirm_password">Confirm password</label>
                        <div class="control has-toggle">
                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                autocomplete="new-password"
                                required
                            >
                            <button type="button" class="toggle" data-target="confirm_password">Show</button>
                        </div>
                    </div>

                    <button type="submit" class="btn">Create account</button>

                </form>

                <p class="switch">
                    Already have an account? <a href="login.php">Log in</a>
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