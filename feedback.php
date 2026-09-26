<?php

session_set_cookie_params([
    "httponly" => true,
    "samesite" => "Lax"
]);

session_start();

require_once "db.php";

// --------------------------------------------------
// AUTHENTICATION
// --------------------------------------------------

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION["user_id"];
$username = $_SESSION["username"];

// --------------------------------------------------
// GET USER INFORMATION
// --------------------------------------------------

$user_email = "";

$stmt = $conn->prepare("
    SELECT email
    FROM users
    WHERE id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

if ($user = $result->fetch_assoc()) {
    $user_email = $user["email"];
}

$stmt->close();

// --------------------------------------------------
// CSRF TOKEN
// --------------------------------------------------

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION["csrf_token"];

// --------------------------------------------------
// VARIABLES
// --------------------------------------------------

$success_message = "";
$error_message = "";

$feedback_type = "";
$rating = "";
$message = "";

// --------------------------------------------------
// HANDLE FEEDBACK SUBMISSION
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // CSRF CHECK
    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals(
            $_SESSION["csrf_token"],
            $_POST["csrf_token"]
        )
    ) {

        $error_message = "Invalid request. Please try again.";

    } else {

        $feedback_type = trim(
            $_POST["type"] ?? ""
        );

        $rating = (int) (
            $_POST["rating"] ?? 0
        );

        $message = trim(
            $_POST["message"] ?? ""
        );


        // ------------------------------------------
        // VALIDATION
        // ------------------------------------------

        if ($feedback_type === "") {

            $error_message =
                "Please select a feedback type.";

        } elseif (
            !in_array(
                $feedback_type,
                [
                    "Suggestion",
                    "Bug Report",
                    "Feature Request",
                    "General Feedback"
                ],
                true
            )
        ) {

            $error_message =
                "Invalid feedback type.";

        } elseif ($rating < 1 || $rating > 5) {

            $error_message =
                "Please select a rating from 1 to 5.";

        } elseif ($message === "") {

            $error_message =
                "Please enter your feedback.";

        } elseif (strlen($message) < 5) {

            $error_message =
                "Feedback must contain at least 5 characters.";

        } elseif (strlen($message) > 2000) {

            $error_message =
                "Feedback cannot exceed 2000 characters.";

        } else {

            // --------------------------------------
            // INSERT FEEDBACK
            // --------------------------------------

            $stmt = $conn->prepare("
                INSERT INTO feedback
                (
                    user_id,
                    type,
                    rating,
                    message
                )
                VALUES (?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "isis",
                $user_id,
                $feedback_type,
                $rating,
                $message
            );

            if ($stmt->execute()) {

                $success_message =
                    "Thank you! Your feedback has been submitted successfully.";

                // Clear form
                $feedback_type = "";
                $rating = "";
                $message = "";

            } else {

                $error_message =
                    "Something went wrong. Please try again.";

            }

            $stmt->close();
        }
    }
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

    <title>Feedback - Habit Tracker</title>

    <style>

        /* =========================================
           THEME VARIABLES
        ========================================== */

        :root {

            --background: #070b14;
            --surface: #111827;
            --surface-secondary: #0f172a;
            --border: #1f2937;

            --text: #ffffff;
            --text-secondary: #9ca3af;

            --input-border: #374151;

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

            --input-border: #9ca3af;

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

            padding: 9px 13px;

            border-radius: 7px;

            color: var(--text-secondary);

            font-size: 14px;

            transition: 0.2s;

        }


        .nav-links a:hover {

            background:
                var(--surface-secondary);

            color: var(--text);

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


        /* =========================================
           CONTAINER
        ========================================== */

        .container {

            max-width: 900px;

            margin: 0 auto;

            padding: 40px 20px 60px;

        }


        /* =========================================
           HEADER
        ========================================== */

        .page-header {

            text-align: center;

            margin-bottom: 30px;

        }


        .page-header h1 {

            font-size: 32px;

            margin-bottom: 10px;

        }


        .page-header p {

            color:
                var(--text-secondary);

            line-height: 1.5;

        }


        /* =========================================
           CARD
        ========================================== */

        .feedback-card {

            background:
                var(--surface);

            border:
                1px solid var(--border);

            border-radius: 14px;

            padding: 30px;

        }


        /* =========================================
           USER INFO
        ========================================== */

        .user-info {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 15px;

            margin-bottom: 25px;

        }


        .info-box {

            background:
                var(--surface-secondary);

            border:
                1px solid var(--border);

            border-radius: 8px;

            padding: 14px;

        }


        .info-label {

            color:
                var(--text-secondary);

            font-size: 12px;

            margin-bottom: 5px;

        }


        .info-value {

            font-size: 15px;

            font-weight: bold;

            word-break: break-word;

        }


        /* =========================================
           FORM
        ========================================== */

        .form-group {

            margin-bottom: 22px;

        }


        label {

            display: block;

            margin-bottom: 8px;

            font-size: 14px;

            font-weight: bold;

        }


        select,
        textarea {

            width: 100%;

            background:
                var(--surface-secondary);

            color:
                var(--text);

            border:
                1px solid var(--input-border);

            border-radius: 8px;

            padding: 12px;

            font-family: Arial, sans-serif;

            font-size: 14px;

            outline: none;

            transition:
                border-color 0.2s ease;

        }


        select:focus,
        textarea:focus {

            border-color:
                var(--primary);

        }


        textarea {

            min-height: 150px;

            resize: vertical;

            line-height: 1.5;

        }


        /* =========================================
           RATING
        ========================================== */

        .rating-container {

            display: flex;

            flex-direction: row-reverse;

            justify-content: flex-end;

            gap: 6px;

        }


        .rating-container input {

            display: none;

        }


        .rating-container label {

            font-size: 34px;

            color: #6b7280;

            cursor: pointer;

            transition:
                color 0.15s ease,
                transform 0.15s ease;

        }


        .rating-container label:hover {

            transform: scale(1.1);

        }


        .rating-container
        input:checked ~ label {

            color: #f59e0b;

        }


        .rating-container
        label:hover,
        .rating-container
        label:hover ~ label {

            color: #f59e0b;

        }


        .rating-help {

            color:
                var(--text-secondary);

            font-size: 12px;

            margin-top: 6px;

        }


        /* =========================================
           BUTTON
        ========================================== */

        .submit-button {

            width: 100%;

            padding: 13px;

            border: none;

            border-radius: 8px;

            background:
                var(--primary);

            color: white;

            font-size: 15px;

            font-weight: bold;

            cursor: pointer;

            transition:
                background 0.2s ease,
                transform 0.2s ease;

        }


        .submit-button:hover {

            background:
                var(--primary-hover);

            transform: translateY(-1px);

        }


        /* =========================================
           MESSAGES
        ========================================== */

        .message {

            padding: 13px 15px;

            border-radius: 8px;

            margin-bottom: 20px;

            font-size: 14px;

            line-height: 1.4;

        }


        .success-message {

            background:
                rgba(22, 163, 74, 0.12);

            border:
                1px solid rgba(22, 163, 74, 0.4);

            color:
                #4ade80;

        }


        .error-message {

            background:
                rgba(220, 38, 38, 0.12);

            border:
                1px solid rgba(220, 38, 38, 0.4);

            color:
                #f87171;

        }


        /* =========================================
           RESPONSIVE
        ========================================== */

        @media (max-width: 700px) {

            .navbar {

                padding: 15px 18px;

                flex-wrap: wrap;

            }


            .nav-links {

                width: 100%;

                justify-content: center;

            }


            .container {

                padding:
                    30px 15px 45px;

            }


            .feedback-card {

                padding: 20px;

            }


            .user-info {

                grid-template-columns: 1fr;

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

            <a href="profile.php">
                Profile
            </a>

            <a
                href="feedback.php"
                class="active"
            >
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
         MAIN CONTENT
    ========================================== -->

    <main class="container">


        <div class="page-header">

            <h1>
                Send Feedback
            </h1>

            <p>
                Help improve Habit Tracker by sharing
                your suggestions, problems, or ideas.
            </p>

        </div>


        <div class="feedback-card">


            <?php if ($success_message !== ""): ?>

                <div class="message success-message">

                    <?php
                    echo htmlspecialchars(
                        $success_message
                    );
                    ?>

                </div>

            <?php endif; ?>


            <?php if ($error_message !== ""): ?>

                <div class="message error-message">

                    <?php
                    echo htmlspecialchars(
                        $error_message
                    );
                    ?>

                </div>

            <?php endif; ?>


            <!-- USER INFORMATION -->

            <div class="user-info">

                <div class="info-box">

                    <div class="info-label">
                        Username
                    </div>

                    <div class="info-value">

                        <?php
                        echo htmlspecialchars(
                            $username
                        );
                        ?>

                    </div>

                </div>


                <div class="info-box">

                    <div class="info-label">
                        Email
                    </div>

                    <div class="info-value">

                        <?php
                        echo htmlspecialchars(
                            $user_email
                        );
                        ?>

                    </div>

                </div>

            </div>


            <!-- FEEDBACK FORM -->

            <form
                method="POST"
                action="feedback.php"
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


                <!-- TYPE -->

                <div class="form-group">

                    <label for="type">
                        Feedback Type
                    </label>

                    <select
                        id="type"
                        name="type"
                        required
                    >

                        <option value="">
                            Select feedback type
                        </option>

                        <option
                            value="Suggestion"
                            <?php
                            echo $feedback_type === "Suggestion"
                                ? "selected"
                                : "";
                            ?>
                        >
                            Suggestion
                        </option>

                        <option
                            value="Bug Report"
                            <?php
                            echo $feedback_type === "Bug Report"
                                ? "selected"
                                : "";
                            ?>
                        >
                            Bug Report
                        </option>

                        <option
                            value="Feature Request"
                            <?php
                            echo $feedback_type === "Feature Request"
                                ? "selected"
                                : "";
                            ?>
                        >
                            Feature Request
                        </option>

                        <option
                            value="General Feedback"
                            <?php
                            echo $feedback_type === "General Feedback"
                                ? "selected"
                                : "";
                            ?>
                        >
                            General Feedback
                        </option>

                    </select>

                </div>


                <!-- RATING -->

                <div class="form-group">

                    <label>
                        Rate Your Experience
                    </label>

                    <div class="rating-container">

                        <input
                            type="radio"
                            id="star5"
                            name="rating"
                            value="5"
                            <?php
                            echo $rating == 5
                                ? "checked"
                                : "";
                            ?>
                            required
                        >

                        <label
                            for="star5"
                            title="5 stars"
                        >
                            ★
                        </label>


                        <input
                            type="radio"
                            id="star4"
                            name="rating"
                            value="4"
                            <?php
                            echo $rating == 4
                                ? "checked"
                                : "";
                            ?>
                        >

                        <label
                            for="star4"
                            title="4 stars"
                        >
                            ★
                        </label>


                        <input
                            type="radio"
                            id="star3"
                            name="rating"
                            value="3"
                            <?php
                            echo $rating == 3
                                ? "checked"
                                : "";
                            ?>
                        >

                        <label
                            for="star3"
                            title="3 stars"
                        >
                            ★
                        </label>


                        <input
                            type="radio"
                            id="star2"
                            name="rating"
                            value="2"
                            <?php
                            echo $rating == 2
                                ? "checked"
                                : "";
                            ?>
                        >

                        <label
                            for="star2"
                            title="2 stars"
                        >
                            ★
                        </label>


                        <input
                            type="radio"
                            id="star1"
                            name="rating"
                            value="1"
                            <?php
                            echo $rating == 1
                                ? "checked"
                                : "";
                            ?>
                        >

                        <label
                            for="star1"
                            title="1 star"
                        >
                            ★
                        </label>

                    </div>

                    <div class="rating-help">
                        Select between 1 and 5 stars.
                    </div>

                </div>


                <!-- MESSAGE -->

                <div class="form-group">

                    <label for="message">
                        Your Feedback
                    </label>

                    <textarea
                        id="message"
                        name="message"
                        maxlength="2000"
                        placeholder="Tell us what you think..."
                        required
                    ><?php
                    echo htmlspecialchars(
                        $message
                    );
                    ?></textarea>

                </div>


                <!-- SUBMIT -->

                <button
                    type="submit"
                    class="submit-button"
                >
                    Submit Feedback
                </button>

            </form>


        </div>

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