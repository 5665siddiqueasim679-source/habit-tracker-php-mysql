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


// ==================================================
// CSRF TOKEN
// ==================================================

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION["csrf_token"];


// ==================================================
// MESSAGE VARIABLES
// ==================================================

$success_message = "";
$error_message = "";


// ==================================================
// UPDATE REMINDER
// ==================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // ----------------------------------------------
    // CSRF CHECK
    // ----------------------------------------------

    $submitted_token = $_POST["csrf_token"] ?? "";

    if (
        empty($submitted_token) ||
        !hash_equals($csrf_token, $submitted_token)
    ) {

        $error_message = "Invalid security request.";

    } else {

        $habit_id = filter_input(
            INPUT_POST,
            "habit_id",
            FILTER_VALIDATE_INT
        );

        $reminder_time = trim(
            $_POST["reminder_time"] ?? ""
        );


        if (!$habit_id) {

            $error_message = "Invalid habit.";

        } else {

            // --------------------------------------
            // Validate time
            // --------------------------------------

            if ($reminder_time !== "") {

                $time_object = DateTime::createFromFormat(
                    "H:i",
                    $reminder_time
                );

                $valid_time =
                    $time_object &&
                    $time_object->format("H:i") === $reminder_time;

                if (!$valid_time) {
                    $error_message =
                        "Please enter a valid reminder time.";
                }
            }


            // --------------------------------------
            // Save reminder
            // --------------------------------------

            if ($error_message === "") {

                if ($reminder_time === "") {

                    $stmt = $conn->prepare("
                        UPDATE habits
                        SET reminder_time = NULL
                        WHERE id = ?
                        AND user_id = ?
                    ");

                    $stmt->bind_param(
                        "ii",
                        $habit_id,
                        $user_id
                    );

                } else {

                    $stmt = $conn->prepare("
                        UPDATE habits
                        SET reminder_time = ?
                        WHERE id = ?
                        AND user_id = ?
                    ");

                    $stmt->bind_param(
                        "sii",
                        $reminder_time,
                        $habit_id,
                        $user_id
                    );
                }


                if ($stmt->execute()) {

                    $success_message =
                        "Reminder saved successfully.";

                } else {

                    $error_message =
                        "Failed to update reminder.";
                }

                $stmt->close();
            }
        }
    }
}


// ==================================================
// GET USER HABITS
// ==================================================

$habits = [];

$stmt = $conn->prepare("
    SELECT
        id,
        habit_name,
        description,
        frequency,
        reminder_time,
        created_at
    FROM habits
    WHERE user_id = ?
    ORDER BY
        CASE
            WHEN reminder_time IS NULL THEN 1
            ELSE 0
        END,
        reminder_time ASC,
        habit_name ASC
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $habits[] = $row;
}

$stmt->close();

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
        Reminders - Habit Tracker
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


        /* =========================================
           NAVBAR
        ========================================== */

        .navbar {

            background: var(--surface);

            border-bottom:
                1px solid var(--border);

            padding: 16px 30px;

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
           THEME BUTTON
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

        }


        /* =========================================
           CONTAINER
        ========================================== */

        .container {

            max-width: 1000px;

            margin: 0 auto;

            padding:
                35px 25px 60px;

        }


        .page-header {

            margin-bottom: 25px;

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
           NOTIFICATION CONTROL
        ========================================== */

        .notification-box {

            background:
                var(--surface);

            border:
                1px solid var(--border);

            border-radius: 12px;

            padding: 20px;

            margin-bottom: 25px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 20px;

        }


        .notification-info h2 {

            font-size: 17px;

            margin-bottom: 5px;

        }


        .notification-info p {

            color:
                var(--text-secondary);

            font-size: 13px;

            line-height: 1.5;

        }


        .notification-button {

            border: none;

            padding:
                11px 17px;

            border-radius: 8px;

            background:
                var(--primary);

            color: white;

            cursor: pointer;

            font-weight: bold;

            white-space: nowrap;

        }


        .notification-button:hover {

            background:
                var(--primary-hover);

        }


        .notification-status {

            margin-top: 8px;

            font-size: 12px;

        }


        .status-enabled {

            color:
                #4ade80;

        }


        .status-disabled {

            color:
                #f87171;

        }


        /* =========================================
           MESSAGE
        ========================================== */

        .message {

            padding:
                13px 16px;

            border-radius: 8px;

            margin-bottom: 20px;

            font-size: 14px;

        }


        .success {

            background:
                rgba(22, 163, 74, 0.12);

            border:
                1px solid rgba(22, 163, 74, 0.35);

            color:
                #4ade80;

        }


        .error {

            background:
                rgba(220, 38, 38, 0.12);

            border:
                1px solid rgba(220, 38, 38, 0.35);

            color:
                #f87171;

        }


        /* =========================================
           REMINDER CARDS
        ========================================== */

        .reminders-list {

            display: flex;

            flex-direction: column;

            gap: 15px;

        }


        .reminder-card {

            background:
                var(--surface);

            border:
                1px solid var(--border);

            border-radius: 12px;

            padding: 20px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 20px;

        }


        .habit-info {

            flex: 1;

            min-width: 0;

        }


        .habit-name {

            font-size: 18px;

            font-weight: bold;

            margin-bottom: 5px;

        }


        .habit-description {

            color:
                var(--text-secondary);

            font-size: 13px;

            margin-bottom: 8px;

        }


        .habit-frequency {

            color:
                var(--text-secondary);

            font-size: 12px;

        }


        .reminder-form {

            display: flex;

            align-items: center;

            gap: 10px;

        }


        .reminder-form label {

            color:
                var(--text-secondary);

            font-size: 13px;

        }


        .reminder-form input {

            padding:
                10px 12px;

            border:
                1px solid var(--border);

            border-radius: 7px;

            background:
                var(--surface-secondary);

            color:
                var(--text);

            font-size: 14px;

        }


        .reminder-form input:focus {

            outline: none;

            border-color:
                var(--primary);

        }


        .save-button {

            border: none;

            padding:
                10px 15px;

            border-radius: 7px;

            background:
                var(--primary);

            color: white;

            cursor: pointer;

            font-weight: bold;

        }


        .save-button:hover {

            background:
                var(--primary-hover);

        }


        .reminder-status {

            font-size: 12px;

            color:
                var(--success);

            margin-top: 6px;

        }


        /* =========================================
           EMPTY STATE
        ========================================== */

        .empty-state {

            background:
                var(--surface);

            border:
                1px solid var(--border);

            border-radius: 12px;

            text-align: center;

            padding: 50px 25px;

            color:
                var(--text-secondary);

        }


        .empty-state h2 {

            color:
                var(--text);

            margin-bottom: 10px;

        }


        .create-link {

            display: inline-block;

            margin-top: 18px;

            padding:
                10px 16px;

            border-radius: 7px;

            background:
                var(--primary);

            color: white;

        }


        /* =========================================
           RESPONSIVE
        ========================================== */

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


            .notification-box {

                flex-direction: column;

                align-items: stretch;

            }


            .notification-button {

                width: 100%;

            }


            .reminder-card {

                flex-direction: column;

                align-items: stretch;

            }


            .reminder-form {

                flex-wrap: wrap;

            }

        }


        @media (max-width: 500px) {

            .reminder-form {

                flex-direction: column;

                align-items: stretch;

            }


            .reminder-form input,
            .save-button {

                width: 100%;

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

            <a href="calendar.php">
                Calendar
            </a>

            <a
                href="reminders.php"
                class="active"
            >
                Reminders
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
            >
                ☀️
            </button>

        </div>

    </nav>


    <!-- =========================================
         MAIN
    ========================================== -->

    <main class="container">


        <div class="page-header">

            <h1>
                Habit Reminders
            </h1>

            <p>
                Set a daily reminder time for each habit.
            </p>

        </div>


        <!-- =====================================
             NOTIFICATION PERMISSION
        ====================================== -->

        <div class="notification-box">

            <div class="notification-info">

                <h2>
                    🔔 Browser Notifications
                </h2>

                <p>
                    Allow notifications so Habit Tracker
                    can remind you when a habit is due.
                </p>

                <div
                    id="notificationStatus"
                    class="notification-status"
                >
                    Checking notification permission...
                </div>

            </div>


            <button
                type="button"
                class="notification-button"
                id="notificationButton"
            >
                Enable Notifications
            </button>

        </div>


        <!-- =====================================
             PHP MESSAGES
        ====================================== -->

        <?php if ($success_message !== ""): ?>

            <div class="message success">

                <?php
                echo htmlspecialchars(
                    $success_message
                );
                ?>

            </div>

        <?php endif; ?>


        <?php if ($error_message !== ""): ?>

            <div class="message error">

                <?php
                echo htmlspecialchars(
                    $error_message
                );
                ?>

            </div>

        <?php endif; ?>


        <!-- =====================================
             HABITS
        ====================================== -->

        <?php if (!empty($habits)): ?>


            <div class="reminders-list">


                <?php foreach ($habits as $habit): ?>


                    <div class="reminder-card">


                        <div class="habit-info">

                            <div class="habit-name">

                                <?php
                                echo htmlspecialchars(
                                    $habit["habit_name"]
                                );
                                ?>

                            </div>


                            <?php if (
                                !empty($habit["description"])
                            ): ?>

                                <div class="habit-description">

                                    <?php
                                    echo htmlspecialchars(
                                        $habit["description"]
                                    );
                                    ?>

                                </div>

                            <?php endif; ?>


                            <div class="habit-frequency">

                                Frequency:

                                <?php
                                echo htmlspecialchars(
                                    $habit["frequency"]
                                );
                                ?>

                            </div>


                            <?php if (
                                !empty($habit["reminder_time"])
                            ): ?>

                                <div class="reminder-status">

                                    Reminder set for

                                    <?php
                                    echo date(
                                        "g:i A",
                                        strtotime(
                                            $habit["reminder_time"]
                                        )
                                    );
                                    ?>

                                </div>

                            <?php endif; ?>

                        </div>


                        <form
                            method="POST"
                            class="reminder-form"
                        >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?php
                                echo htmlspecialchars(
                                    $csrf_token
                                );
                                ?>"
                            >


                            <input
                                type="hidden"
                                name="habit_id"
                                value="<?php
                                echo (int) $habit["id"];
                                ?>"
                            >


                            <label>
                                Reminder
                            </label>


                            <input
                                type="time"
                                name="reminder_time"
                                value="<?php

                                if (
                                    !empty(
                                        $habit["reminder_time"]
                                    )
                                ) {

                                    echo htmlspecialchars(
                                        substr(
                                            $habit["reminder_time"],
                                            0,
                                            5
                                        )
                                    );
                                }

                                ?>"
                            >


                            <button
                                type="submit"
                                class="save-button"
                            >
                                Save
                            </button>

                        </form>


                    </div>


                <?php endforeach; ?>


            </div>


        <?php else: ?>


            <div class="empty-state">

                <h2>
                    No habits yet
                </h2>

                <p>
                    Create a habit first, then you can
                    set a reminder for it.
                </p>


                <a
                    href="habits.php"
                    class="create-link"
                >
                    Create Habit
                </a>

            </div>


        <?php endif; ?>


    </main>


    <!-- =========================================
         JAVASCRIPT
         NOTIFICATION SYSTEM
    ========================================== -->

    <script>

        // ==================================================
        // HABIT DATA FROM PHP
        // ==================================================

        const habits = <?php

            echo json_encode(
                array_map(
                    function ($habit) {

                        return [
                            "id" =>
                                (int) $habit["id"],

                            "name" =>
                                $habit["habit_name"],

                            "reminder_time" =>
                                $habit["reminder_time"]
                        ];

                    },
                    $habits
                ),
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            );

        ?>;


        // ==================================================
        // NOTIFICATION ELEMENTS
        // ==================================================

        const notificationButton =
            document.getElementById(
                "notificationButton"
            );

        const notificationStatus =
            document.getElementById(
                "notificationStatus"
            );


        // ==================================================
        // UPDATE NOTIFICATION STATUS
        // ==================================================

        function updateNotificationStatus() {

            if (!("Notification" in window)) {

                notificationStatus.textContent =
                    "This browser does not support notifications.";

                notificationStatus.className =
                    "notification-status status-disabled";

                notificationButton.style.display =
                    "none";

                return;
            }


            if (Notification.permission === "granted") {

                notificationStatus.textContent =
                    "Notifications are enabled.";

                notificationStatus.className =
                    "notification-status status-enabled";

                notificationButton.textContent =
                    "Notifications Enabled";

                notificationButton.disabled = true;

            }

            else if (
                Notification.permission === "denied"
            ) {

                notificationStatus.textContent =
                    "Notifications are blocked. Enable them in Firefox site permissions.";

                notificationStatus.className =
                    "notification-status status-disabled";

            }

            else {

                notificationStatus.textContent =
                    "Notifications have not been enabled yet.";

                notificationStatus.className =
                    "notification-status status-disabled";

                notificationButton.disabled =
                    false;
            }

        }


        // ==================================================
        // REQUEST NOTIFICATION PERMISSION
        // ==================================================

        notificationButton.addEventListener(
            "click",
            async function () {

                if (!("Notification" in window)) {

                    alert(
                        "Your browser does not support notifications."
                    );

                    return;
                }


                const permission =
                    await Notification.requestPermission();


                updateNotificationStatus();


                if (permission === "granted") {

                    // Test notification

                    new Notification(
                        "Habit Tracker",
                        {
                            body:
                                "Notifications are now enabled!",
                            icon:
                                ""
                        }
                    );

                }

            }
        );


        // ==================================================
        // PREVENT DUPLICATE NOTIFICATIONS
        // ==================================================

        function getNotificationKey(
            habitId,
            date
        ) {

            return (
                "habitReminder_" +
                habitId +
                "_" +
                date
            );

        }


        // ==================================================
        // CHECK REMINDERS
        // ==================================================

        function checkReminders() {

            if (!("Notification" in window)) {
                return;
            }


            if (Notification.permission !== "granted") {
                return;
            }


            const now =
                new Date();


            // Current hour

            const currentHour =
                String(
                    now.getHours()
                ).padStart(2, "0");


            // Current minute

            const currentMinute =
                String(
                    now.getMinutes()
                ).padStart(2, "0");


            const currentTime =
                currentHour +
                ":" +
                currentMinute;


            // Today's date

            const today =
                now.getFullYear() +
                "-" +
                String(
                    now.getMonth() + 1
                ).padStart(2, "0") +
                "-" +
                String(
                    now.getDate()
                ).padStart(2, "0");


            // Check every habit

            habits.forEach(
                function (habit) {

                    if (!habit.reminder_time) {
                        return;
                    }


                    // MySQL returns HH:MM:SS

                    const reminderTime =
                        habit.reminder_time.substring(
                            0,
                            5
                        );


                    // Check current time

                    if (
                        reminderTime ===
                        currentTime
                    ) {

                        const storageKey =
                            getNotificationKey(
                                habit.id,
                                today
                            );


                        // Already notified today?

                        if (
                            localStorage.getItem(
                                storageKey
                            ) === "sent"
                        ) {

                            return;
                        }


                        // Show notification

                        new Notification(
                            "🔔 Habit Reminder",
                            {
                                body:
                                    "Time to complete: " +
                                    habit.name,

                                tag:
                                    "habit-" +
                                    habit.id
                            }
                        );


                        // Remember notification

                        localStorage.setItem(
                            storageKey,
                            "sent"
                        );

                    }

                }
            );

        }


        // ==================================================
        // START REMINDER CHECKER
        // ==================================================

        updateNotificationStatus();


        // Check immediately

        checkReminders();


        // Check every 10 seconds

        setInterval(
            checkReminders,
            10000
        );


        // ==================================================
        // THEME SYSTEM
        // ==================================================

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

            themeToggle.textContent =
                "🌙";

        } else {

            themeToggle.textContent =
                "☀️";

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

                    themeToggle.textContent =
                        "🌙";

                } else {

                    localStorage.setItem(
                        "habitTrackerTheme",
                        "dark"
                    );

                    themeToggle.textContent =
                        "☀️";

                }

            }
        );

    </script>


</body>

</html>