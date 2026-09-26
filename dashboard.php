<?php

session_set_cookie_params([
    "httponly" => true,
    "samesite" => "Lax"
]);

session_start();

require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION["user_id"];
$username = $_SESSION["username"];

$today = date("Y-m-d");

// ==========================================
// TOTAL HABITS
// ==========================================

$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM habits
     WHERE user_id = ?"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$total_habits = $stmt->get_result()->fetch_assoc()["total"];

$stmt->close();

// ==========================================
// TODAY'S COMPLETED HABITS
// ==========================================

$stmt = $conn->prepare(
    "SELECT COUNT(*) AS completed
     FROM habit_completions hc
     INNER JOIN habits h
        ON hc.habit_id = h.id
     WHERE h.user_id = ?
     AND hc.completed_date = ?"
);

$stmt->bind_param("is", $user_id, $today);
$stmt->execute();

$completed_today =
    $stmt->get_result()->fetch_assoc()["completed"];

$stmt->close();

// ==========================================
// REMAINING
// ==========================================

$remaining_today =
    max(0, $total_habits - $completed_today);

// ==========================================
// TODAY PERCENTAGE
// ==========================================

$today_percentage = 0;

if ($total_habits > 0) {

    $today_percentage =
        round(
            ($completed_today / $total_habits) * 100
        );
}

// ==========================================
// TOTAL COMPLETIONS
// ==========================================

$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM habit_completions hc
     INNER JOIN habits h
        ON hc.habit_id = h.id
     WHERE h.user_id = ?"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$total_completions =
    $stmt->get_result()->fetch_assoc()["total"];

$stmt->close();

// ==========================================
// TODAY'S HABITS
// ==========================================

$stmt = $conn->prepare(
    "SELECT
        h.id,
        h.habit_name,
        h.description,
        h.frequency,
        CASE
            WHEN hc.id IS NOT NULL THEN 1
            ELSE 0
        END AS completed_today
     FROM habits h
     LEFT JOIN habit_completions hc
        ON h.id = hc.habit_id
        AND hc.completed_date = ?
     WHERE h.user_id = ?
     ORDER BY h.created_at DESC"
);

$stmt->bind_param("si", $today, $user_id);
$stmt->execute();

$today_habits =
    $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt->close();

// ==========================================
// RECENT COMPLETIONS
// ==========================================

$stmt = $conn->prepare(
    "SELECT
        h.habit_name,
        hc.completed_date
     FROM habit_completions hc
     INNER JOIN habits h
        ON hc.habit_id = h.id
     WHERE h.user_id = ?
     ORDER BY hc.completed_date DESC, hc.id DESC
     LIMIT 8"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$recent_completions =
    $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt->close();

// ==========================================
// GREETING
// ==========================================

$hour = (int) date("H");

if ($hour < 12) {
    $greeting = "Good morning";
} elseif ($hour < 18) {
    $greeting = "Good afternoon";
} else {
    $greeting = "Good evening";
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Dashboard - Habit Tracker</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: #070b14;
    color: white;
    min-height: 100vh;
}

.navbar {
    background: #111827;
    border-bottom: 1px solid #1f2937;
    padding: 18px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.logo {
    font-size: 22px;
    font-weight: bold;
}

.nav-links {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.nav-links a {
    color: #d1d5db;
    text-decoration: none;
    padding: 9px 14px;
    border-radius: 7px;
}

.nav-links a:hover,
.nav-links a.active {
    background: #1f2937;
    color: white;
}

.user-area {
    display: flex;
    align-items: center;
    gap: 15px;
}

.username {
    color: #9ca3af;
}

.logout {
    background: #dc2626;
    color: white !important;
    text-decoration: none;
    padding: 9px 14px;
    border-radius: 7px;
}

.container {
    width: 92%;
    max-width: 1200px;
    margin: 40px auto;
}

.header {
    margin-bottom: 30px;
}

.header h1 {
    font-size: 30px;
    margin-bottom: 8px;
}

.header p {
    color: #9ca3af;
}

.stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
    margin-bottom: 25px;
}

.card {
    background: #111827;
    border: 1px solid #1f2937;
    border-radius: 12px;
    padding: 22px;
}

.stat-number {
    font-size: 30px;
    font-weight: bold;
    margin-bottom: 7px;
}

.stat-label {
    color: #9ca3af;
    font-size: 14px;
}

.progress-card {
    margin-bottom: 25px;
}

.progress-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 12px;
}

.progress-bar {
    height: 12px;
    background: #1f2937;
    border-radius: 20px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: #2563eb;
}

.section-grid {
    display: grid;
    grid-template-columns: 1.4fr 1fr;
    gap: 20px;
}

.card h2 {
    font-size: 19px;
    margin-bottom: 20px;
}

.habit {
    padding: 15px 0;
    border-bottom: 1px solid #1f2937;
}

.habit:last-child {
    border-bottom: none;
}

.habit-name {
    font-weight: bold;
    margin-bottom: 5px;
}

.habit-description {
    color: #9ca3af;
    font-size: 13px;
}

.completed {
    color: #86efac;
    font-size: 13px;
    margin-top: 7px;
}

.pending {
    color: #fbbf24;
    font-size: 13px;
    margin-top: 7px;
}

.activity {
    padding: 13px 0;
    border-bottom: 1px solid #1f2937;
}

.activity:last-child {
    border-bottom: none;
}

.activity-name {
    font-weight: bold;
}

.activity-date {
    color: #9ca3af;
    font-size: 13px;
    margin-top: 4px;
}

.actions {
    display: flex;
    gap: 10px;
    margin-top: 25px;
    flex-wrap: wrap;
}

.button {
    display: inline-block;
    padding: 11px 16px;
    border-radius: 8px;
    text-decoration: none;
    color: white;
    background: #2563eb;
}

.button.secondary {
    background: #374151;
}

.empty {
    color: #9ca3af;
    padding: 10px 0;
}

@media (max-width: 900px) {

    .stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .section-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 600px) {

    .stats {
        grid-template-columns: 1fr;
    }

    .navbar {
        flex-direction: column;
        text-align: center;
    }

    .user-area {
        justify-content: center;
    }

    .nav-links {
        justify-content: center;
    }
}

</style>

</head>

<body>

<nav class="navbar">

    <div class="logo">
        Habit Tracker
    </div>

    <div class="nav-links">

        <a href="dashboard.php" class="active">
            Dashboard
        </a>

        <a href="habits.php">
            Habits
        </a>

        <a href="analytics.php">
            Analytics
        </a>

        <a href="profile.php">
            Profile
        </a>

    </div>

    <div class="user-area">

        <span class="username">
            <?= htmlspecialchars($username) ?>
        </span>

        <a href="logout.php" class="logout">
            Logout
        </a>

    </div>

</nav>

<div class="container">

    <div class="header">

        <h1>
            <?= htmlspecialchars($greeting) ?>,
            <?= htmlspecialchars($username) ?>!
        </h1>

        <p>
            <?= date("l, F j, Y") ?>
        </p>

    </div>

    <div class="stats">

        <div class="card">
            <div class="stat-number">
                <?= $total_habits ?>
            </div>

            <div class="stat-label">
                Total Habits
            </div>
        </div>

        <div class="card">
            <div class="stat-number">
                <?= $completed_today ?>
            </div>

            <div class="stat-label">
                Completed Today
            </div>
        </div>

        <div class="card">
            <div class="stat-number">
                <?= $remaining_today ?>
            </div>

            <div class="stat-label">
                Remaining Today
            </div>
        </div>

        <div class="card">
            <div class="stat-number">
                <?= $total_completions ?>
            </div>

            <div class="stat-label">
                Total Completions
            </div>
        </div>

    </div>

    <div class="card progress-card">

        <div class="progress-header">

            <strong>
                Today's Progress
            </strong>

            <strong>
                <?= $today_percentage ?>%
            </strong>

        </div>

        <div class="progress-bar">

            <div
                class="progress-fill"
                style="width: <?= $today_percentage ?>%;"
            ></div>

        </div>

    </div>

    <div class="section-grid">

        <div class="card">

            <h2>
                Today's Habits
            </h2>

            <?php if (empty($today_habits)): ?>

                <div class="empty">
                    You have not created any habits yet.
                </div>

            <?php else: ?>

                <?php foreach ($today_habits as $habit): ?>

                    <div class="habit">

                        <div class="habit-name">

                            <?= htmlspecialchars(
                                $habit["habit_name"]
                            ) ?>

                        </div>

                        <?php if (
                            !empty($habit["description"])
                        ): ?>

                            <div class="habit-description">

                                <?= htmlspecialchars(
                                    $habit["description"]
                                ) ?>

                            </div>

                        <?php endif; ?>

                        <?php if (
                            $habit["completed_today"]
                        ): ?>

                            <div class="completed">
                                ✓ Completed today
                            </div>

                        <?php else: ?>

                            <div class="pending">
                                ○ Not completed yet
                            </div>

                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

            <div class="actions">

                <a
                    href="habits.php"
                    class="button"
                >
                    Manage Habits
                </a>

                <a
                    href="analytics.php"
                    class="button secondary"
                >
                    View Analytics
                </a>

            </div>

        </div>

        <div class="card">

            <h2>
                Recent Activity
            </h2>

            <?php if (
                empty($recent_completions)
            ): ?>

                <div class="empty">
                    No habit completions yet.
                </div>

            <?php else: ?>

                <?php foreach (
                    $recent_completions
                    as $activity
                ): ?>

                    <div class="activity">

                        <div class="activity-name">

                            <?= htmlspecialchars(
                                $activity["habit_name"]
                            ) ?>

                        </div>

                        <div class="activity-date">

                            Completed on
                            <?= htmlspecialchars(
                                date(
                                    "M j, Y",
                                    strtotime(
                                        $activity[
                                            "completed_date"
                                        ]
                                    )
                                )
                            ) ?>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </div>

</div>

</body>
</html>