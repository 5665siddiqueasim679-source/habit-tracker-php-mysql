<?php

session_set_cookie_params([
    "httponly" => true,
    "samesite" => "Lax"
]);

session_start();

require_once "db.php";

// ==================================================
// AUTHENTICATION
// ==================================================

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION["user_id"];
$username = $_SESSION["username"];

$today = date("Y-m-d");

// ==================================================
// HELPER FUNCTIONS
// ==================================================

function getDailyCurrentStreak($conn, $habit_id)
{
    $dates = [];

    $stmt = $conn->prepare("
        SELECT completed_date
        FROM habit_completions
        WHERE habit_id = ?
        ORDER BY completed_date DESC
    ");

    $stmt->bind_param("i", $habit_id);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $dates[] = $row["completed_date"];
    }

    $stmt->close();

    if (empty($dates)) {
        return 0;
    }

    $today = new DateTime(date("Y-m-d"));
    $first_date = new DateTime($dates[0]);

    $difference = (int) $today->diff($first_date)->format("%r%a");

    if ($difference < -1) {
        return 0;
    }

    if ($dates[0] === $today->format("Y-m-d")) {
        $expected = clone $today;
    } else {
        $expected = clone $today;
        $expected->modify("-1 day");

        if ($dates[0] !== $expected->format("Y-m-d")) {
            return 0;
        }
    }

    $streak = 0;

    foreach ($dates as $date) {

        if ($date === $expected->format("Y-m-d")) {

            $streak++;

            $expected->modify("-1 day");

        } elseif ($date < $expected->format("Y-m-d")) {

            break;
        }
    }

    return $streak;
}


function getDailyLongestStreak($conn, $habit_id)
{
    $dates = [];

    $stmt = $conn->prepare("
        SELECT completed_date
        FROM habit_completions
        WHERE habit_id = ?
        ORDER BY completed_date ASC
    ");

    $stmt->bind_param("i", $habit_id);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $dates[] = $row["completed_date"];
    }

    $stmt->close();

    if (empty($dates)) {
        return 0;
    }

    $longest = 1;
    $current = 1;

    for ($i = 1; $i < count($dates); $i++) {

        $previous = new DateTime($dates[$i - 1]);
        $current_date = new DateTime($dates[$i]);

        $difference =
            (int) $previous->diff($current_date)->format("%a");

        if ($difference === 1) {

            $current++;

            if ($current > $longest) {
                $longest = $current;
            }

        } else {

            $current = 1;
        }
    }

    return $longest;
}


// ==================================================
// GET ALL USER HABITS
// ==================================================

$habits = [];

$stmt = $conn->prepare("
    SELECT
        id,
        habit_name,
        description,
        frequency,
        created_at
    FROM habits
    WHERE user_id = ?
    ORDER BY created_at ASC
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $habits[] = $row;
}

$stmt->close();


// ==================================================
// TOTAL COMPLETIONS
// ==================================================

$total_completions = 0;

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM habit_completions hc
    INNER JOIN habits h
        ON hc.habit_id = h.id
    WHERE h.user_id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $total_completions = (int) $row["total"];
}

$stmt->close();


// ==================================================
// TODAY'S COMPLETIONS
// ==================================================

$today_completed = 0;

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM habit_completions hc
    INNER JOIN habits h
        ON hc.habit_id = h.id
    WHERE h.user_id = ?
    AND hc.completed_date = ?
");

$stmt->bind_param("is", $user_id, $today);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $today_completed = (int) $row["total"];
}

$stmt->close();


// ==================================================
// TODAY'S COMPLETION RATE
// ==================================================

$total_habits = count($habits);

if ($total_habits > 0) {

    $today_percentage =
        round(($today_completed / $total_habits) * 100);

} else {

    $today_percentage = 0;
}


// ==================================================
// LAST 7 DAYS
// ==================================================

$seven_days_ago = date(
    "Y-m-d",
    strtotime("-6 days")
);

$seven_day_total = 0;

$seven_day_activity = [];

for ($i = 6; $i >= 0; $i--) {

    $date = date(
        "Y-m-d",
        strtotime("-$i days")
    );

    $seven_day_activity[$date] = 0;
}


$stmt = $conn->prepare("
    SELECT
        hc.completed_date,
        COUNT(*) AS total
    FROM habit_completions hc
    INNER JOIN habits h
        ON hc.habit_id = h.id
    WHERE h.user_id = ?
    AND hc.completed_date BETWEEN ? AND ?
    GROUP BY hc.completed_date
    ORDER BY hc.completed_date ASC
");

$stmt->bind_param(
    "iss",
    $user_id,
    $seven_days_ago,
    $today
);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $date = $row["completed_date"];

    if (isset($seven_day_activity[$date])) {

        $seven_day_activity[$date] =
            (int) $row["total"];

        $seven_day_total +=
            (int) $row["total"];
    }
}

$stmt->close();


// ==================================================
// 7-DAY COMPLETION RATE
// ==================================================

$possible_seven_day =
    $total_habits * 7;

if ($possible_seven_day > 0) {

    $seven_day_percentage =
        round(
            ($seven_day_total / $possible_seven_day) * 100
        );

    if ($seven_day_percentage > 100) {
        $seven_day_percentage = 100;
    }

} else {

    $seven_day_percentage = 0;
}


// ==================================================
// LAST 30 DAYS
// ==================================================

$thirty_days_ago = date(
    "Y-m-d",
    strtotime("-29 days")
);

$thirty_day_total = 0;

$thirty_day_activity = [];

for ($i = 29; $i >= 0; $i--) {

    $date = date(
        "Y-m-d",
        strtotime("-$i days")
    );

    $thirty_day_activity[$date] = 0;
}


$stmt = $conn->prepare("
    SELECT
        hc.completed_date,
        COUNT(*) AS total
    FROM habit_completions hc
    INNER JOIN habits h
        ON hc.habit_id = h.id
    WHERE h.user_id = ?
    AND hc.completed_date BETWEEN ? AND ?
    GROUP BY hc.completed_date
    ORDER BY hc.completed_date ASC
");

$stmt->bind_param(
    "iss",
    $user_id,
    $thirty_days_ago,
    $today
);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $date = $row["completed_date"];

    if (isset($thirty_day_activity[$date])) {

        $thirty_day_activity[$date] =
            (int) $row["total"];

        $thirty_day_total +=
            (int) $row["total"];
    }
}

$stmt->close();


// ==================================================
// 30-DAY COMPLETION RATE
// ==================================================

$possible_thirty_day =
    $total_habits * 30;

if ($possible_thirty_day > 0) {

    $thirty_day_percentage =
        round(
            ($thirty_day_total / $possible_thirty_day) * 100
        );

    if ($thirty_day_percentage > 100) {
        $thirty_day_percentage = 100;
    }

} else {

    $thirty_day_percentage = 0;
}


// ==================================================
// OVERALL COMPLETION RATE
// ==================================================

$possible_overall = 0;

foreach ($habits as $habit) {

    $created =
        new DateTime(
            date(
                "Y-m-d",
                strtotime($habit["created_at"])
            )
        );

    $today_object =
        new DateTime($today);

    $days =
        (int) $created->diff($today_object)->days + 1;

    $possible_overall += $days;
}


if ($possible_overall > 0) {

    $overall_percentage =
        round(
            ($total_completions / $possible_overall) * 100
        );

    if ($overall_percentage > 100) {
        $overall_percentage = 100;
    }

} else {

    $overall_percentage = 0;
}


// ==================================================
// PER-HABIT STATISTICS
// ==================================================

$habit_statistics = [];

$highest_current_streak = 0;
$longest_streak = 0;

$most_completed_habit = "None";
$least_completed_habit = "None";

$highest_completion_count = -1;
$lowest_completion_count = PHP_INT_MAX;


foreach ($habits as $habit) {

    $habit_id = (int) $habit["id"];

    // ----------------------------------------------
    // Completion count
    // ----------------------------------------------

    $completion_count = 0;

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM habit_completions
        WHERE habit_id = ?
    ");

    $stmt->bind_param(
        "i",
        $habit_id
    );

    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $completion_count =
            (int) $row["total"];
    }

    $stmt->close();


    // ----------------------------------------------
    // Current streak
    // ----------------------------------------------

    $current_streak =
        getDailyCurrentStreak(
            $conn,
            $habit_id
        );


    // ----------------------------------------------
    // Longest streak
    // ----------------------------------------------

    $habit_longest_streak =
        getDailyLongestStreak(
            $conn,
            $habit_id
        );


    // ----------------------------------------------
    // Habit age
    // ----------------------------------------------

    $created =
        new DateTime(
            date(
                "Y-m-d",
                strtotime($habit["created_at"])
            )
        );

    $today_object =
        new DateTime($today);

    $habit_days =
        (int) $created->diff($today_object)->days + 1;


    // ----------------------------------------------
    // Completion percentage
    // ----------------------------------------------

    if ($habit_days > 0) {

        $completion_percentage =
            round(
                ($completion_count / $habit_days) * 100
            );

        if ($completion_percentage > 100) {
            $completion_percentage = 100;
        }

    } else {

        $completion_percentage = 0;
    }


    // ----------------------------------------------
    // Update global streaks
    // ----------------------------------------------

    if ($current_streak > $highest_current_streak) {

        $highest_current_streak =
            $current_streak;
    }


    if ($habit_longest_streak > $longest_streak) {

        $longest_streak =
            $habit_longest_streak;
    }


    // ----------------------------------------------
    // Most completed
    // ----------------------------------------------

    if ($completion_count > $highest_completion_count) {

        $highest_completion_count =
            $completion_count;

        $most_completed_habit =
            $habit["habit_name"];
    }


    // ----------------------------------------------
    // Least completed
    // ----------------------------------------------

    if ($completion_count < $lowest_completion_count) {

        $lowest_completion_count =
            $completion_count;

        $least_completed_habit =
            $habit["habit_name"];
    }


    $habit_statistics[] = [

        "id" =>
            $habit_id,

        "habit_name" =>
            $habit["habit_name"],

        "description" =>
            $habit["description"],

        "frequency" =>
            $habit["frequency"],

        "completion_count" =>
            $completion_count,

        "completion_percentage" =>
            $completion_percentage,

        "current_streak" =>
            $current_streak,

        "longest_streak" =>
            $habit_longest_streak,

        "created_at" =>
            $habit["created_at"]

    ];
}


// ==================================================
// MAXIMUM VALUE FOR 7-DAY CHART
// ==================================================

$max_seven_day =
    max(
        $seven_day_activity
    );

if ($max_seven_day < 1) {
    $max_seven_day = 1;
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

    <title>
        Analytics - Habit Tracker
    </title>


    <style>

        /* =========================================
           THEME
        ========================================== */

        :root {

            --background: #070b14;
            --surface: #111827;
            --surface-secondary: #0f172a;
            --border: #1f2937;

            --text: #ffffff;
            --text-secondary: #9ca3af;

            --primary: #2563eb;
            --primary-hover: #1d4ed8;

            --success: #16a34a;
            --success-light: #22c55e;

            --warning: #f59e0b;

            --danger: #dc2626;

        }


        body.light-theme {

            --background: #f3f4f6;
            --surface: #ffffff;
            --surface-secondary: #f9fafb;
            --border: #d1d5db;

            --text: #111827;
            --text-secondary: #6b7280;

            --primary: #2563eb;
            --primary-hover: #1d4ed8;

            --success: #16a34a;
            --success-light: #22c55e;

            --warning: #f59e0b;

            --danger: #dc2626;

        }


        /* =========================================
           RESET
        ========================================== */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }


        body {

            font-family: Arial, sans-serif;

            background:
                var(--background);

            color:
                var(--text);

            min-height: 100vh;

            transition:
                background 0.3s ease,
                color 0.3s ease;

        }


        a {
            text-decoration: none;
            color: inherit;
        }


        /* =========================================
           NAVBAR
        ========================================== */

        .navbar {

            background:
                var(--surface);

            border-bottom:
                1px solid var(--border);

            padding:
                16px 30px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 20px;

        }


        .logo {

            font-size: 22px;

            font-weight: bold;

        }


        .nav-links {

            display: flex;

            align-items: center;

            gap: 8px;

            flex-wrap: wrap;

        }


        .nav-links a {

            padding:
                9px 13px;

            border-radius: 7px;

            color:
                var(--text-secondary);

            font-size: 14px;

            transition: 0.2s;

        }


        .nav-links a:hover {

            background:
                var(--surface-secondary);

            color:
                var(--text);

        }


        .nav-links a.active {

            background:
                var(--primary);

            color: white;

        }


        /* =========================================
           THEME TOGGLE
        ========================================== */

        .theme-toggle {

            width: 40px;
            height: 40px;

            border:
                1px solid var(--border);

            border-radius: 8px;

            background:
                var(--surface-secondary);

            color:
                var(--text);

            cursor: pointer;

            font-size: 18px;

            display: flex;

            align-items: center;

            justify-content: center;

            transition:
                background 0.2s ease,
                transform 0.2s ease;

        }


        .theme-toggle:hover {

            transform:
                scale(1.05);

        }


        /* =========================================
           CONTAINER
        ========================================== */

        .container {

            max-width: 1250px;

            margin: 0 auto;

            padding:
                35px 25px 60px;

        }


        .page-header {

            margin-bottom: 25px;

            display: flex;

            align-items: flex-end;

            justify-content: space-between;

            gap: 20px;

        }


        .page-header-content {

            flex: 1;

        }


        .page-header h1 {

            font-size: 30px;

            margin-bottom: 7px;

        }


        .page-header p {

            color:
                var(--text-secondary);

        }


        /* =========================================
           EXPORT BUTTON
        ========================================== */

        .export-button {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 8px;

            padding:
                11px 17px;

            background:
                var(--primary);

            color: white;

            border-radius: 8px;

            font-size: 14px;

            font-weight: bold;

            white-space: nowrap;

            transition:
                background 0.2s ease,
                transform 0.2s ease;

        }


        .export-button:hover {

            background:
                var(--primary-hover);

            transform:
                translateY(-1px);

        }


        /* =========================================
           STAT CARDS
        ========================================== */

        .stats-grid {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 15px;

            margin-bottom: 25px;

        }


        .stat-card {

            background:
                var(--surface);

            border:
                1px solid var(--border);

            border-radius: 12px;

            padding: 20px;

        }


        .stat-label {

            color:
                var(--text-secondary);

            font-size: 13px;

            margin-bottom: 9px;

        }


        .stat-value {

            font-size: 28px;

            font-weight: bold;

        }


        .stat-description {

            color:
                var(--text-secondary);

            font-size: 12px;

            margin-top: 7px;

        }


        /* =========================================
           SECTION
        ========================================== */

        .section {

            background:
                var(--surface);

            border:
                1px solid var(--border);

            border-radius: 12px;

            padding: 22px;

            margin-bottom: 20px;

        }


        .section-title {

            font-size: 20px;

            margin-bottom: 18px;

        }


        /* =========================================
           RATE CARDS
        ========================================== */

        .rate-grid {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 15px;

        }


        .rate-card {

            background:
                var(--surface-secondary);

            border:
                1px solid var(--border);

            border-radius: 10px;

            padding: 18px;

        }


        .rate-header {

            display: flex;

            align-items: center;

            justify-content: space-between;

            margin-bottom: 10px;

        }


        .rate-name {

            font-weight: bold;

            font-size: 14px;

        }


        .rate-number {

            font-size: 21px;

            font-weight: bold;

        }


        .progress {

            height: 9px;

            background:
                var(--border);

            border-radius: 20px;

            overflow: hidden;

        }


        .progress-bar {

            height: 100%;

            background:
                var(--primary);

            border-radius: 20px;

        }


        /* =========================================
           INSIGHTS
        ========================================== */

        .insights-grid {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 15px;

        }


        .insight-card {

            background:
                var(--surface-secondary);

            border:
                1px solid var(--border);

            border-radius: 10px;

            padding: 18px;

        }


        .insight-label {

            color:
                var(--text-secondary);

            font-size: 13px;

            margin-bottom: 8px;

        }


        .insight-value {

            font-size: 19px;

            font-weight: bold;

        }


        /* =========================================
           7 DAY CHART
        ========================================== */

        .chart {

            display: flex;

            align-items: flex-end;

            justify-content: space-between;

            gap: 10px;

            height: 220px;

            padding-top: 15px;

        }


        .chart-column {

            flex: 1;

            height: 100%;

            display: flex;

            flex-direction: column;

            align-items: center;

            justify-content: flex-end;

            gap: 8px;

        }


        .chart-number {

            font-size: 12px;

            color:
                var(--text-secondary);

        }


        .chart-bar-container {

            width: 100%;

            max-width: 55px;

            height: 160px;

            display: flex;

            align-items: flex-end;

        }


        .chart-bar {

            width: 100%;

            background:
                var(--primary);

            border-radius:
                6px 6px 2px 2px;

            min-height: 3px;

            transition:
                height 0.3s ease;

        }


        .chart-label {

            color:
                var(--text-secondary);

            font-size: 11px;

        }


        /* =========================================
           30 DAY ACTIVITY
        ========================================== */

        .activity-grid {

            display: grid;

            grid-template-columns:
                repeat(10, 1fr);

            gap: 7px;

        }


        .activity-day {

            aspect-ratio: 1;

            border-radius: 4px;

            background:
                var(--border);

            position: relative;

            cursor: default;

        }


        .activity-day.level-1 {

            background:
                rgba(37, 99, 235, 0.35);

        }


        .activity-day.level-2 {

            background:
                rgba(37, 99, 235, 0.55);

        }


        .activity-day.level-3 {

            background:
                rgba(37, 99, 235, 0.75);

        }


        .activity-day.level-4 {

            background:
                var(--primary);

        }


        .activity-tooltip {

            display: none;

            position: absolute;

            bottom: 125%;

            left: 50%;

            transform:
                translateX(-50%);

            background:
                #000;

            color: white;

            padding: 6px 8px;

            border-radius: 5px;

            white-space: nowrap;

            font-size: 11px;

            z-index: 10;

        }


        .activity-day:hover
        .activity-tooltip {

            display: block;

        }


        /* =========================================
           HABIT TABLE
        ========================================== */

        .table-wrapper {

            overflow-x: auto;

        }


        table {

            width: 100%;

            border-collapse: collapse;

            min-width: 700px;

        }


        th,
        td {

            padding:
                13px 12px;

            border-bottom:
                1px solid var(--border);

            text-align: left;

        }


        th {

            color:
                var(--text-secondary);

            font-size: 12px;

            text-transform: uppercase;

            letter-spacing: 0.4px;

        }


        td {

            font-size: 14px;

        }


        .habit-title {

            font-weight: bold;

        }


        .habit-frequency {

            color:
                var(--text-secondary);

            font-size: 12px;

            margin-top: 3px;

        }


        .percentage-wrapper {

            min-width: 130px;

        }


        .small-progress {

            height: 7px;

            background:
                var(--border);

            border-radius: 20px;

            overflow: hidden;

            margin-top: 6px;

        }


        .small-progress-bar {

            height: 100%;

            background:
                var(--success);

        }


        .percentage-text {

            font-size: 12px;

            color:
                var(--text-secondary);

        }


        .streak-badge {

            display: inline-block;

            padding:
                5px 8px;

            border-radius: 6px;

            background:
                rgba(37, 99, 235, 0.12);

            color:
                #60a5fa;

            font-size: 12px;

            font-weight: bold;

        }


        /* =========================================
           EMPTY STATE
        ========================================== */

        .empty-state {

            text-align: center;

            padding: 35px;

            color:
                var(--text-secondary);

        }


        /* =========================================
           RESPONSIVE
        ========================================== */

        @media (max-width: 1000px) {

            .stats-grid {

                grid-template-columns:
                    repeat(2, 1fr);

            }


            .rate-grid {

                grid-template-columns:
                    1fr;

            }

        }


        @media (max-width: 750px) {

            .navbar {

                padding:
                    15px 18px;

                flex-wrap: wrap;

            }


            .nav-links {

                width: 100%;

                justify-content: center;

            }


            .container {

                padding:
                    25px 15px 45px;

            }


            .page-header {

                align-items: stretch;

                flex-direction: column;

            }


            .export-button {

                width: 100%;

            }


            .insights-grid {

                grid-template-columns:
                    1fr;

            }


            .activity-grid {

                grid-template-columns:
                    repeat(10, 1fr);

                gap: 4px;

            }

        }


        @media (max-width: 500px) {

            .stats-grid {

                grid-template-columns:
                    1fr;

            }


            .page-header h1 {

                font-size: 26px;

            }


            .section {

                padding:
                    17px;

            }

        }

    </style>

</head>


<body>


    <!-- =========================================
         NAVBAR
    ========================================== -->

    <nav class="navbar">

        <div class="logo">
            Habit Tracker
        </div>


        <div class="nav-links">

            <a href="dashboard.php">
                Dashboard
            </a>

            <a href="habits.php">
                Habits
            </a>

            <a
                href="analytics.php"
                class="active"
            >
                Analytics
            </a>

            <a href="calendar.php">
                Calendar
            </a>

            <a href="profile.php">
                Profile
            </a>

            <a href="feedback.php">
                Feedback
            </a>

            <a href="logout.php">
                Logout
            </a>


            <button
                type="button"
                class="theme-toggle"
                id="themeToggle"
                aria-label="Toggle theme"
            >
                ☀️
            </button>

        </div>

    </nav>


    <!-- =========================================
         MAIN
    ========================================== -->

    <main class="container">


        <!-- =====================================
             PAGE HEADER
        ====================================== -->

        <div class="page-header">

            <div class="page-header-content">

                <h1>
                    Analytics
                </h1>

                <p>
                    Track your consistency, progress,
                    and habit performance.
                </p>

            </div>


            <!-- EXPORT BUTTON -->

            <a
                href="export.php"
                class="export-button"
            >
                ↓ Export CSV
            </a>

        </div>


        <!-- =====================================
             MAIN STATISTICS
        ====================================== -->

        <div class="stats-grid">


            <div class="stat-card">

                <div class="stat-label">
                    Total Habits
                </div>

                <div class="stat-value">
                    <?php echo $total_habits; ?>
                </div>

                <div class="stat-description">
                    Habits currently being tracked
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-label">
                    Total Completions
                </div>

                <div class="stat-value">
                    <?php echo $total_completions; ?>
                </div>

                <div class="stat-description">
                    All-time completed habits
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-label">
                    Current Streak
                </div>

                <div class="stat-value">
                    <?php echo $highest_current_streak; ?>
                </div>

                <div class="stat-description">
                    Consecutive days
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-label">
                    Longest Streak
                </div>

                <div class="stat-value">
                    <?php echo $longest_streak; ?>
                </div>

                <div class="stat-description">
                    Best consecutive-day streak
                </div>

            </div>

        </div>


        <!-- =====================================
             COMPLETION RATES
        ====================================== -->

        <section class="section">

            <h2 class="section-title">
                Completion Rates
            </h2>


            <div class="rate-grid">


                <div class="rate-card">

                    <div class="rate-header">

                        <span class="rate-name">
                            Overall
                        </span>

                        <span class="rate-number">
                            <?php
                            echo $overall_percentage;
                            ?>%
                        </span>

                    </div>


                    <div class="progress">

                        <div
                            class="progress-bar"
                            style="width: <?php
                            echo $overall_percentage;
                            ?>%;"
                        ></div>

                    </div>

                </div>


                <div class="rate-card">

                    <div class="rate-header">

                        <span class="rate-name">
                            Last 7 Days
                        </span>

                        <span class="rate-number">
                            <?php
                            echo $seven_day_percentage;
                            ?>%
                        </span>

                    </div>


                    <div class="progress">

                        <div
                            class="progress-bar"
                            style="width: <?php
                            echo $seven_day_percentage;
                            ?>%;"
                        ></div>

                    </div>

                </div>


                <div class="rate-card">

                    <div class="rate-header">

                        <span class="rate-name">
                            Last 30 Days
                        </span>

                        <span class="rate-number">
                            <?php
                            echo $thirty_day_percentage;
                            ?>%
                        </span>

                    </div>


                    <div class="progress">

                        <div
                            class="progress-bar"
                            style="width: <?php
                            echo $thirty_day_percentage;
                            ?>%;"
                        ></div>

                    </div>

                </div>

            </div>

        </section>


        <!-- =====================================
             INSIGHTS
        ====================================== -->

        <section class="section">

            <h2 class="section-title">
                Habit Insights
            </h2>


            <div class="insights-grid">


                <div class="insight-card">

                    <div class="insight-label">
                        Most Completed Habit
                    </div>

                    <div class="insight-value">

                        <?php
                        echo htmlspecialchars(
                            $most_completed_habit
                        );
                        ?>

                    </div>

                </div>


                <div class="insight-card">

                    <div class="insight-label">
                        Least Completed Habit
                    </div>

                    <div class="insight-value">

                        <?php
                        echo htmlspecialchars(
                            $least_completed_habit
                        );
                        ?>

                    </div>

                </div>


                <div class="insight-card">

                    <div class="insight-label">
                        Completed Today
                    </div>

                    <div class="insight-value">

                        <?php
                        echo $today_completed;
                        ?>

                        /

                        <?php
                        echo $total_habits;
                        ?>

                        habits

                    </div>

                </div>


                <div class="insight-card">

                    <div class="insight-label">
                        30-Day Completions
                    </div>

                    <div class="insight-value">

                        <?php
                        echo $thirty_day_total;
                        ?>

                    </div>

                </div>

            </div>

        </section>


        <!-- =====================================
             7 DAY CHART
        ====================================== -->

        <section class="section">

            <h2 class="section-title">
                Last 7 Days
            </h2>


            <div class="chart">

                <?php foreach (
                    $seven_day_activity
                    as $date => $count
                ): ?>

                    <?php

                    $height =
                        ($count / $max_seven_day) * 100;

                    if ($count > 0 && $height < 5) {
                        $height = 5;
                    }

                    $date_object =
                        new DateTime($date);

                    $day_label =
                        $date_object->format("D");

                    ?>

                    <div class="chart-column">


                        <div class="chart-number">

                            <?php
                            echo $count;
                            ?>

                        </div>


                        <div class="chart-bar-container">

                            <div
                                class="chart-bar"
                                style="height: <?php
                                echo $height;
                                ?>%;"
                                title="<?php
                                echo $date;
                                ?>: <?php
                                echo $count;
                                ?> completions"
                            ></div>

                        </div>


                        <div class="chart-label">

                            <?php
                            echo $day_label;
                            ?>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        </section>


        <!-- =====================================
             30 DAY ACTIVITY
        ====================================== -->

        <section class="section">

            <h2 class="section-title">
                30-Day Activity
            </h2>


            <div class="activity-grid">

                <?php foreach (
                    $thirty_day_activity
                    as $date => $count
                ): ?>

                    <?php

                    if ($count === 0) {

                        $level = 0;

                    } elseif ($count === 1) {

                        $level = 1;

                    } elseif ($count === 2) {

                        $level = 2;

                    } elseif ($count === 3) {

                        $level = 3;

                    } else {

                        $level = 4;
                    }

                    ?>

                    <div
                        class="activity-day level-<?php
                        echo $level;
                        ?>"
                    >

                        <div class="activity-tooltip">

                            <?php
                            echo $date;
                            ?>

                            :

                            <?php
                            echo $count;
                            ?>

                            completion<?php
                            echo $count === 1
                                ? ""
                                : "s";
                            ?>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        </section>


        <!-- =====================================
             HABIT PERFORMANCE
        ====================================== -->

        <section class="section">

            <h2 class="section-title">
                Habit Performance
            </h2>


            <?php if (!empty($habit_statistics)): ?>


                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    Habit
                                </th>

                                <th>
                                    Completions
                                </th>

                                <th>
                                    Completion Rate
                                </th>

                                <th>
                                    Current Streak
                                </th>

                                <th>
                                    Longest Streak
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach (
                                $habit_statistics
                                as $habit
                            ): ?>


                                <tr>


                                    <td>

                                        <div class="habit-title">

                                            <?php
                                            echo htmlspecialchars(
                                                $habit["habit_name"]
                                            );
                                            ?>

                                        </div>


                                        <div class="habit-frequency">

                                            <?php
                                            echo htmlspecialchars(
                                                $habit["frequency"]
                                            );
                                            ?>

                                        </div>

                                    </td>


                                    <td>

                                        <?php
                                        echo $habit[
                                            "completion_count"
                                        ];
                                        ?>

                                    </td>


                                    <td>

                                        <div class="percentage-wrapper">

                                            <span
                                                class="percentage-text"
                                            >

                                                <?php
                                                echo $habit[
                                                    "completion_percentage"
                                                ];
                                                ?>%

                                            </span>


                                            <div class="small-progress">

                                                <div
                                                    class="small-progress-bar"
                                                    style="width: <?php
                                                    echo $habit[
                                                        "completion_percentage"
                                                    ];
                                                    ?>%;"
                                                ></div>

                                            </div>

                                        </div>

                                    </td>


                                    <td>

                                        <span class="streak-badge">

                                            <?php
                                            echo $habit[
                                                "current_streak"
                                            ];
                                            ?>

                                            days

                                        </span>

                                    </td>


                                    <td>

                                        <span class="streak-badge">

                                            <?php
                                            echo $habit[
                                                "longest_streak"
                                            ];
                                            ?>

                                            days

                                        </span>

                                    </td>


                                </tr>


                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>


            <?php else: ?>


                <div class="empty-state">

                    You haven't created any habits yet.

                    <br><br>

                    <a
                        href="habits.php"
                        style="color: var(--primary);"
                    >
                        Create your first habit
                    </a>

                </div>


            <?php endif; ?>

        </section>


        <!-- =====================================
             PERIOD SUMMARY
        ====================================== -->

        <section class="section">

            <h2 class="section-title">
                Period Summary
            </h2>


            <div class="insights-grid">


                <div class="insight-card">

                    <div class="insight-label">
                        7-Day Completions
                    </div>

                    <div class="insight-value">

                        <?php
                        echo $seven_day_total;
                        ?>

                    </div>

                </div>


                <div class="insight-card">

                    <div class="insight-label">
                        30-Day Completions
                    </div>

                    <div class="insight-value">

                        <?php
                        echo $thirty_day_total;
                        ?>

                    </div>

                </div>


                <div class="insight-card">

                    <div class="insight-label">
                        7-Day Rate
                    </div>

                    <div class="insight-value">

                        <?php
                        echo $seven_day_percentage;
                        ?>%

                    </div>

                </div>


                <div class="insight-card">

                    <div class="insight-label">
                        30-Day Rate
                    </div>

                    <div class="insight-value">

                        <?php
                        echo $thirty_day_percentage;
                        ?>%

                    </div>

                </div>

            </div>

        </section>


    </main>


    <!-- =========================================
         THEME JAVASCRIPT
    ========================================== -->

    <script>

        const themeToggle =
            document.getElementById(
                "themeToggle"
            );


        const savedTheme =
            localStorage.getItem(
                "habitTrackerTheme"
            );


        if (savedTheme === "light") {

            document.body.classList.add(
                "light-theme"
            );

            themeToggle.textContent = "🌙";

        } else {

            themeToggle.textContent = "☀️";

        }


        themeToggle.addEventListener(
            "click",
            function () {

                document.body.classList.toggle(
                    "light-theme"
                );


                const isLight =
                    document.body.classList.contains(
                        "light-theme"
                    );


                if (isLight) {

                    localStorage.setItem(
                        "habitTrackerTheme",
                        "light"
                    );

                    themeToggle.textContent = "🌙";

                } else {

                    localStorage.setItem(
                        "habitTrackerTheme",
                        "dark"
                    );

                    themeToggle.textContent = "☀️";

                }

            }
        );

    </script>


</body>

</html>