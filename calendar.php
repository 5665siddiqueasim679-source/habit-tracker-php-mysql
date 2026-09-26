<?php

session_set_cookie_params([
    "httponly" => true,
    "samesite" => "Lax"
]);

session_start();

require_once "db.php";

// Make sure user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION["user_id"];
$username = $_SESSION["username"];

// --------------------------------------------------
// GET SELECTED MONTH
// --------------------------------------------------

$month = isset($_GET["month"]) ? (int) $_GET["month"] : (int) date("m");
$year = isset($_GET["year"]) ? (int) $_GET["year"] : (int) date("Y");

// Make sure month is valid
if ($month < 1 || $month > 12) {
    $month = (int) date("m");
}

// Make sure year is reasonable
if ($year < 2000 || $year > 2100) {
    $year = (int) date("Y");
}

$selected_date = isset($_GET["date"])
    ? $_GET["date"]
    : date("Y-m-d");

// Validate selected date
$date_object = DateTime::createFromFormat("Y-m-d", $selected_date);

if (!$date_object || $date_object->format("Y-m-d") !== $selected_date) {
    $selected_date = date("Y-m-d");
}

// --------------------------------------------------
// MONTH INFORMATION
// --------------------------------------------------

$first_day = new DateTime("$year-$month-01");

$days_in_month = (int) $first_day->format("t");

// Monday = 1, Sunday = 7
$starting_day = (int) $first_day->format("N");

$month_name = $first_day->format("F");

// Previous month
$previous_month = clone $first_day;
$previous_month->modify("-1 month");

$previous_month_number = $previous_month->format("m");
$previous_year = $previous_month->format("Y");

// Next month
$next_month = clone $first_day;
$next_month->modify("+1 month");

$next_month_number = $next_month->format("m");
$next_year = $next_month->format("Y");

// --------------------------------------------------
// GET USER'S HABITS
// --------------------------------------------------

$habits = [];

$stmt = $conn->prepare("
    SELECT id, habit_name, description, frequency
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

// --------------------------------------------------
// GET COMPLETIONS FOR SELECTED MONTH
// --------------------------------------------------

$month_start = $first_day->format("Y-m-d");

$month_end = clone $first_day;
$month_end->modify("last day of this month");

$month_end_date = $month_end->format("Y-m-d");

$completion_dates = [];

$stmt = $conn->prepare("
    SELECT hc.completed_date
    FROM habit_completions hc
    INNER JOIN habits h
        ON hc.habit_id = h.id
    WHERE h.user_id = ?
    AND hc.completed_date BETWEEN ? AND ?
    GROUP BY hc.completed_date
");

$stmt->bind_param(
    "iss",
    $user_id,
    $month_start,
    $month_end_date
);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $completion_dates[$row["completed_date"]] = true;
}

$stmt->close();

// --------------------------------------------------
// GET COMPLETIONS FOR SELECTED DATE
// --------------------------------------------------

$selected_habits = [];

$stmt = $conn->prepare("
    SELECT
        h.id,
        h.habit_name,
        h.description,
        h.frequency
    FROM habit_completions hc
    INNER JOIN habits h
        ON hc.habit_id = h.id
    WHERE h.user_id = ?
    AND hc.completed_date = ?
    ORDER BY h.habit_name ASC
");

$stmt->bind_param(
    "is",
    $user_id,
    $selected_date
);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $selected_habits[] = $row;
}

$stmt->close();

// --------------------------------------------------
// GET TOTAL COMPLETIONS FOR MONTH
// --------------------------------------------------

$monthly_completion_count = 0;

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM habit_completions hc
    INNER JOIN habits h
        ON hc.habit_id = h.id
    WHERE h.user_id = ?
    AND hc.completed_date BETWEEN ? AND ?
");

$stmt->bind_param(
    "iss",
    $user_id,
    $month_start,
    $month_end_date
);

$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $monthly_completion_count = (int) $row["total"];
}

$stmt->close();

// --------------------------------------------------
// MONTHLY ACTIVE DAYS
// --------------------------------------------------

$active_days = count($completion_dates);

// --------------------------------------------------
// TODAY
// --------------------------------------------------

$today = date("Y-m-d");

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Calendar - Habit Tracker</title>

    <style>

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
            --danger: #dc2626;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: var(--background);
            color: var(--text);
            min-height: 100vh;
            transition:
                background 0.3s ease,
                color 0.3s ease;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        /* -----------------------------------------
           NAVBAR
        ----------------------------------------- */

        .navbar {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 16px 30px;

            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 20px;
        }

        .logo {
            font-size: 22px;
            font-weight: bold;
            color: var(--text);
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .nav-links a {
            padding: 9px 13px;
            border-radius: 7px;
            color: var(--text-secondary);
            font-size: 14px;
            transition: 0.2s;
        }

        .nav-links a:hover {
            background: var(--surface-secondary);
            color: var(--text);
        }

        .nav-links a.active {
            background: var(--primary);
            color: white;
        }

        .theme-toggle {
            width: 40px;
            height: 40px;

            border: 1px solid var(--border);
            border-radius: 8px;

            background: var(--surface-secondary);
            color: var(--text);

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
            transform: scale(1.05);
        }

        /* -----------------------------------------
           MAIN
        ----------------------------------------- */

        .container {
            max-width: 1250px;
            margin: 0 auto;
            padding: 35px 25px 60px;
        }

        .page-header {
            margin-bottom: 25px;
        }

        .page-header h1 {
            font-size: 30px;
            margin-bottom: 7px;
        }

        .page-header p {
            color: var(--text-secondary);
        }

        /* -----------------------------------------
           SUMMARY
        ----------------------------------------- */

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .summary-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
        }

        .summary-label {
            color: var(--text-secondary);
            font-size: 13px;
            margin-bottom: 8px;
        }

        .summary-value {
            font-size: 27px;
            font-weight: bold;
        }

        /* -----------------------------------------
           CALENDAR CARD
        ----------------------------------------- */

        .calendar-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 22px;
        }

        .calendar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 20px;
        }

        .calendar-title {
            font-size: 23px;
            font-weight: bold;
        }

        .calendar-navigation {
            display: flex;
            gap: 8px;
        }

        .nav-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            padding: 9px 13px;

            border: 1px solid var(--border);
            border-radius: 7px;

            background: var(--surface-secondary);
            color: var(--text);

            font-size: 14px;
            cursor: pointer;

            transition: 0.2s;
        }

        .nav-button:hover {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        .today-button {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        /* -----------------------------------------
           CALENDAR
        ----------------------------------------- */

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
        }

        .weekday {
            text-align: center;
            color: var(--text-secondary);
            font-size: 13px;
            font-weight: bold;
            padding: 10px 5px;
        }

        .calendar-day {
            min-height: 105px;

            border: 1px solid var(--border);
            border-radius: 9px;

            background: var(--surface-secondary);

            padding: 10px;

            position: relative;

            transition:
                border-color 0.2s ease,
                transform 0.2s ease;
        }

        .calendar-day:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        .calendar-day.empty {
            background: transparent;
            border-color: transparent;
        }

        .calendar-day-number {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .calendar-day a {
            display: block;
            width: 100%;
            height: 100%;
        }

        .calendar-day.completed {
            border-color: var(--success);
        }

        .completion-dot {
            width: 9px;
            height: 9px;

            background: var(--success-light);

            border-radius: 50%;

            display: inline-block;
            margin-right: 5px;
        }

        .completion-text {
            color: var(--success-light);
            font-size: 12px;
        }

        .calendar-day.selected {
            border: 2px solid var(--primary);
        }

        .calendar-day.today {
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.25);
        }

        .today-label {
            display: inline-block;
            margin-top: 5px;

            font-size: 10px;

            padding: 3px 6px;

            border-radius: 4px;

            background: var(--primary);
            color: white;
        }

        /* -----------------------------------------
           SELECTED DATE
        ----------------------------------------- */

        .selected-section {
            margin-top: 25px;

            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }

        .selected-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 22px;
        }

        .selected-card h2 {
            font-size: 21px;
            margin-bottom: 5px;
        }

        .selected-date {
            color: var(--text-secondary);
            margin-bottom: 18px;
            font-size: 14px;
        }

        .habit-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .habit-item {
            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 15px;

            padding: 14px;

            border: 1px solid var(--border);
            border-radius: 8px;

            background: var(--surface-secondary);
        }

        .habit-name {
            font-weight: bold;
        }

        .habit-description {
            color: var(--text-secondary);
            font-size: 13px;
            margin-top: 4px;
        }

        .completed-badge {
            padding: 6px 9px;
            border-radius: 6px;

            background: rgba(22, 163, 74, 0.15);
            color: var(--success-light);

            font-size: 12px;
            font-weight: bold;

            white-space: nowrap;
        }

        .empty-state {
            padding: 30px 15px;
            text-align: center;
            color: var(--text-secondary);
        }

        /* -----------------------------------------
           FOOTER NOTE
        ----------------------------------------- */

        .info-box {
            margin-top: 20px;
            padding: 14px 16px;

            background: var(--surface-secondary);
            border: 1px solid var(--border);
            border-radius: 8px;

            color: var(--text-secondary);
            font-size: 13px;
            line-height: 1.5;
        }

        /* -----------------------------------------
           RESPONSIVE
        ----------------------------------------- */

        @media (max-width: 900px) {

            .navbar {
                padding: 15px 18px;
                flex-wrap: wrap;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .calendar-day {
                min-height: 85px;
            }

        }

        @media (max-width: 650px) {

            .container {
                padding: 25px 15px 45px;
            }

            .nav-links {
                width: 100%;
                justify-content: center;
            }

            .calendar-card {
                padding: 12px;
            }

            .calendar-grid {
                gap: 4px;
            }

            .weekday {
                font-size: 10px;
                padding: 7px 2px;
            }

            .calendar-day {
                min-height: 65px;
                padding: 6px;
            }

            .calendar-day-number {
                font-size: 12px;
                margin-bottom: 4px;
            }

            .completion-text {
                display: none;
            }

            .completion-dot {
                width: 8px;
                height: 8px;
            }

            .today-label {
                font-size: 8px;
                padding: 2px 4px;
            }

            .calendar-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .calendar-navigation {
                width: 100%;
            }

            .nav-button {
                flex: 1;
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

            <a href="analytics.php">
                Analytics
            </a>

            <a
                href="calendar.php"
                class="active"
            >
                Calendar
            </a>

            <a href="profile.php">
                Profile
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
         MAIN CONTENT
    ========================================== -->

    <main class="container">

        <div class="page-header">

            <h1>
                Habit Calendar
            </h1>

            <p>
                View your habit completion history month by month.
            </p>

        </div>


        <!-- =====================================
             SUMMARY
        ====================================== -->

        <div class="summary-grid">

            <div class="summary-card">

                <div class="summary-label">
                    Active Days
                </div>

                <div class="summary-value">
                    <?php echo $active_days; ?>
                </div>

            </div>


            <div class="summary-card">

                <div class="summary-label">
                    Total Completions
                </div>

                <div class="summary-value">
                    <?php echo $monthly_completion_count; ?>
                </div>

            </div>


            <div class="summary-card">

                <div class="summary-label">
                    Habits
                </div>

                <div class="summary-value">
                    <?php echo count($habits); ?>
                </div>

            </div>

        </div>


        <!-- =====================================
             CALENDAR
        ====================================== -->

        <section class="calendar-card">

            <div class="calendar-header">

                <div class="calendar-title">

                    <?php echo htmlspecialchars($month_name); ?>

                    <?php echo $year; ?>

                </div>


                <div class="calendar-navigation">

                    <a
                        class="nav-button"
                        href="calendar.php?month=<?php echo $previous_month_number; ?>&year=<?php echo $previous_year; ?>"
                    >
                        ← Previous
                    </a>

                    <a
                        class="nav-button today-button"
                        href="calendar.php?month=<?php echo date("m"); ?>&year=<?php echo date("Y"); ?>&date=<?php echo $today; ?>"
                    >
                        Today
                    </a>

                    <a
                        class="nav-button"
                        href="calendar.php?month=<?php echo $next_month_number; ?>&year=<?php echo $next_year; ?>"
                    >
                        Next →
                    </a>

                </div>

            </div>


            <div class="calendar-grid">

                <!-- Weekdays -->

                <div class="weekday">
                    Mon
                </div>

                <div class="weekday">
                    Tue
                </div>

                <div class="weekday">
                    Wed
                </div>

                <div class="weekday">
                    Thu
                </div>

                <div class="weekday">
                    Fri
                </div>

                <div class="weekday">
                    Sat
                </div>

                <div class="weekday">
                    Sun
                </div>


                <?php

                // Empty cells before first day

                for ($i = 1; $i < $starting_day; $i++) {

                    echo '
                        <div class="calendar-day empty"></div>
                    ';

                }


                // Calendar days

                for ($day = 1; $day <= $days_in_month; $day++) {

                    $current_date = sprintf(
                        "%04d-%02d-%02d",
                        $year,
                        $month,
                        $day
                    );

                    $is_completed =
                        isset($completion_dates[$current_date]);

                    $is_selected =
                        $current_date === $selected_date;

                    $is_today =
                        $current_date === $today;


                    $classes = "calendar-day";

                    if ($is_completed) {
                        $classes .= " completed";
                    }

                    if ($is_selected) {
                        $classes .= " selected";
                    }

                    if ($is_today) {
                        $classes .= " today";
                    }

                    ?>

                    <div class="<?php echo $classes; ?>">

                        <a
                            href="calendar.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>&date=<?php echo $current_date; ?>"
                        >

                            <div class="calendar-day-number">

                                <?php echo $day; ?>

                            </div>


                            <?php if ($is_completed): ?>

                                <div>

                                    <span class="completion-dot"></span>

                                    <span class="completion-text">
                                        Completed
                                    </span>

                                </div>

                            <?php endif; ?>


                            <?php if ($is_today): ?>

                                <div class="today-label">
                                    Today
                                </div>

                            <?php endif; ?>

                        </a>

                    </div>

                    <?php

                }

                ?>

            </div>


            <div class="info-box">

                <strong>How it works:</strong>

                Green-marked dates contain at least one completed habit.
                Click any date to see which habits you completed on that day.

            </div>

        </section>


        <!-- =====================================
             SELECTED DATE
        ====================================== -->

        <section class="selected-section">

            <div class="selected-card">

                <h2>
                    Completed Habits
                </h2>

                <div class="selected-date">

                    <?php

                    $selected_date_object =
                        new DateTime($selected_date);

                    echo $selected_date_object->format(
                        "l, F j, Y"
                    );

                    ?>

                </div>


                <?php if (count($selected_habits) > 0): ?>

                    <div class="habit-list">

                        <?php foreach ($selected_habits as $habit): ?>

                            <div class="habit-item">

                                <div>

                                    <div class="habit-name">

                                        <?php
                                        echo htmlspecialchars(
                                            $habit["habit_name"]
                                        );
                                        ?>

                                    </div>


                                    <?php if (!empty($habit["description"])): ?>

                                        <div class="habit-description">

                                            <?php
                                            echo htmlspecialchars(
                                                $habit["description"]
                                            );
                                            ?>

                                        </div>

                                    <?php endif; ?>

                                </div>


                                <div class="completed-badge">

                                    ✓ Completed

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <div class="empty-state">

                        No habits were completed on this date.

                    </div>

                <?php endif; ?>

            </div>

        </section>

    </main>


    <!-- =========================================
         THEME JAVASCRIPT
    ========================================== -->

    <script>

        const themeToggle =
            document.getElementById("themeToggle");


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