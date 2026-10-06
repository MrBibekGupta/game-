
<?php

session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/db.php";

$userId = (int) $_SESSION["user_id"];

$stmt = $conn->prepare("
    SELECT 
        id,
        username,
        email,
        profile_image,
        total_games,
        total_score,
        created_at
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}

$initial = strtoupper(substr($user["username"], 0, 1));

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Profile - Banana Game</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f5f7fb;
            color: #1f2937;
            min-height: 100vh;
        }

        /* Header */

        .header {
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            padding: 18px 0;
        }

        .header-inner {
            width: 92%;
            max-width: 1100px;
            margin: auto;

            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            text-decoration: none;
            color: #111827;
            font-size: 22px;
            font-weight: 700;
        }

        .nav {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav a {
            text-decoration: none;
            color: #4b5563;
            font-size: 14px;
            font-weight: 600;

            padding: 9px 13px;
            border-radius: 8px;
        }

        .nav a:hover {
            background: #f3f4f6;
            color: #111827;
        }

        .logout {
            background: #111827 !important;
            color: #ffffff !important;
        }

        /* Main */

        .container {
            width: 92%;
            max-width: 850px;
            margin: 45px auto;
        }

        .page-heading {
            margin-bottom: 25px;
        }

        .page-heading h1 {
            font-size: 30px;
            color: #111827;
            margin-bottom: 7px;
        }

        .page-heading p {
            color: #6b7280;
            font-size: 15px;
        }

        /* Profile Card */

        .profile-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            padding: 35px;

            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 24px;

            padding-bottom: 30px;
            border-bottom: 1px solid #e5e7eb;
        }

        .avatar {
            width: 95px;
            height: 95px;

            border-radius: 50%;

            background: #f59e0b;
            color: #ffffff;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 38px;
            font-weight: 700;

            overflow: hidden;
            flex-shrink: 0;
        }

        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-info h2 {
            font-size: 26px;
            color: #111827;
            margin-bottom: 7px;
        }

        .user-info p {
            color: #6b7280;
            font-size: 15px;
        }

        /* Statistics */

        .stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;

            margin-top: 30px;
        }

        .stat {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 22px;
        }

        .stat-label {
            color: #6b7280;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .stat-value {
            color: #111827;
            font-size: 27px;
            font-weight: 700;
        }

        /* Account Details */

        .details {
            margin-top: 32px;
        }

        .details h3 {
            font-size: 18px;
            color: #111827;
            margin-bottom: 15px;
        }

        .detail-row {
            display: flex;
            align-items: center;
            justify-content: space-between;

            padding: 15px 0;
            border-bottom: 1px solid #eeeeee;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: #6b7280;
            font-size: 14px;
        }

        .detail-value {
            color: #111827;
            font-size: 14px;
            font-weight: 600;
        }

        /* Buttons */

        .actions {
            display: flex;
            gap: 12px;
            margin-top: 30px;
        }

        .btn {
            display: inline-block;

            padding: 12px 20px;

            border-radius: 9px;

            text-decoration: none;

            font-size: 14px;
            font-weight: 600;

            transition: 0.2s;
        }

        .btn-primary {
            background: #f59e0b;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #d97706;
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }

        /* Footer */

        .footer {
            text-align: center;
            color: #9ca3af;
            font-size: 13px;

            padding: 10px 0 35px;
        }

        /* Mobile */

        @media (max-width: 650px) {

            .header-inner {
                width: 92%;
            }

            .logo {
                font-size: 19px;
            }

            .nav a {
                font-size: 13px;
                padding: 8px;
            }

            .nav a:not(.logout) {
                display: none;
            }

            .container {
                margin: 30px auto;
            }

            .profile-card {
                padding: 25px 20px;
            }

            .profile-header {
                flex-direction: column;
                text-align: center;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .detail-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 6px;
            }

            .actions {
                flex-direction: column;
            }

            .btn {
                text-align: center;
            }

        }

    </style>

</head>

<body>

<header class="header">

    <div class="header-inner">

        <a href="../index.php" class="logo">
            🍌 Banana Game
        </a>

        <nav class="nav">

            <a href="../index.php">
                Home
            </a>

            <a href="play.php">
                Play Game
            </a>

            <a href="leaderboard.php">
                Leaderboard
            </a>

            <a href="profile.php">
                Profile
            </a>

            <a href="../auth/logout.php" class="logout">
                Logout
            </a>

        </nav>

    </div>

</header>


<main class="container">

    <div class="page-heading">

        <h1>
            My Profile
        </h1>

        <p>
            View your account information and game statistics.
        </p>

    </div>


    <div class="profile-card">

        <!-- Profile Header -->

        <div class="profile-header">

            <div class="avatar">

                <?php if (!empty($user["profile_image"])): ?>

                    <img
                        src="../<?php echo htmlspecialchars($user["profile_image"]); ?>"
                        alt="Profile Image"
                    >

                <?php else: ?>

                    <?php echo htmlspecialchars($initial); ?>

                <?php endif; ?>

            </div>


            <div class="user-info">

                <h2>
                    <?php echo htmlspecialchars($user["username"]); ?>
                </h2>

                <p>
                    <?php echo htmlspecialchars($user["email"]); ?>
                </p>

            </div>

        </div>


        <!-- Statistics -->

        <div class="stats">

            <div class="stat">

                <div class="stat-label">
                    Total Games
                </div>

                <div class="stat-value">
                    <?php echo (int) $user["total_games"]; ?>
                </div>

            </div>


            <div class="stat">

                <div class="stat-label">
                    Total Score
                </div>

                <div class="stat-value">
                    <?php echo (int) $user["total_score"]; ?>
                </div>

            </div>

        </div>


        <!-- Account Details -->

        <div class="details">

            <h3>
                Account Information
            </h3>


            <div class="detail-row">

                <span class="detail-label">
                    Username
                </span>

                <span class="detail-value">
                    <?php echo htmlspecialchars($user["username"]); ?>
                </span>

            </div>


            <div class="detail-row">

                <span class="detail-label">
                    Email
                </span>

                <span class="detail-value">
                    <?php echo htmlspecialchars($user["email"]); ?>
                </span>

            </div>


            <div class="detail-row">

                <span class="detail-label">
                    Member Since
                </span>

                <span class="detail-value">
                    <?php echo date("F d, Y", strtotime($user["created_at"])); ?>
                </span>

            </div>

        </div>


        <!-- Actions -->

        <div class="actions">

            <a href="play.php" class="btn btn-primary">
                🍌 Play Game
            </a>

            <a href="leaderboard.php" class="btn btn-secondary">
                View Leaderboard
            </a>

        </div>

    </div>

</main>


<footer class="footer">

    © <?php echo date("Y"); ?> Banana Game.
    All rights reserved.

</footer>

</body>

</html>