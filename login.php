<?php

// ==========================================
// SESSION CONFIGURATION
// ==========================================

session_set_cookie_params([
    "httponly" => true,
    "samesite" => "Lax"
]);

session_start();

require_once "db.php";

// ==========================================
// IF ALREADY LOGGED IN
// ==========================================

if (isset($_SESSION["user_id"])) {

    header("Location: dashboard.php");

    exit();
}

// ==========================================
// CREATE CSRF TOKEN
// ==========================================

if (empty($_SESSION["csrf_token"])) {

    $_SESSION["csrf_token"] =
        bin2hex(random_bytes(32));
}

$csrf_token =
    $_SESSION["csrf_token"];

// ==========================================
// VARIABLES
// ==========================================

$error = "";

$username = "";

// ==========================================
// LOGIN FORM SUBMITTED
// ==========================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // ======================================
    // VERIFY CSRF TOKEN
    // ======================================

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals(
            $_SESSION["csrf_token"],
            $_POST["csrf_token"]
        )
    ) {

        die(
            "Invalid request. Please refresh the page and try again."
        );
    }

    // ======================================
    // GET FORM DATA
    // ======================================

    $username =
        trim($_POST["username"] ?? "");

    $password =
        $_POST["password"] ?? "";

    // ======================================
    // VALIDATION
    // ======================================

    if (
        $username === "" ||
        $password === ""
    ) {

        $error =
            "Please enter username and password.";

    } else {

        // ==================================
        // FIND USER
        // ==================================

        $stmt = $conn->prepare(
            "SELECT
                id,
                username,
                password
             FROM users
             WHERE username = ?"
        );

        if (!$stmt) {

            $error =
                "Something went wrong. Please try again.";

        } else {

            $stmt->bind_param(
                "s",
                $username
            );

            $stmt->execute();

            $result =
                $stmt->get_result();

            // ==================================
            // USER FOUND
            // ==================================

            if ($result->num_rows === 1) {

                $user =
                    $result->fetch_assoc();

                // ==============================
                // VERIFY PASSWORD
                // ==============================

                if (
                    password_verify(
                        $password,
                        $user["password"]
                    )
                ) {

                    // ==============================
                    // PREVENT SESSION FIXATION
                    // ==============================

                    session_regenerate_id(true);

                    // ==============================
                    // STORE SESSION DATA
                    // ==============================

                    $_SESSION["user_id"] =
                        $user["id"];

                    $_SESSION["username"] =
                        $user["username"];

                    // ==============================
                    // REDIRECT
                    // ==============================

                    header(
                        "Location: dashboard.php"
                    );

                    exit();

                } else {

                    $error =
                        "Wrong username or password.";
                }

            } else {

                $error =
                    "Wrong username or password.";
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

<title>
    Login - Habit Tracker
</title>

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

    display: flex;

    justify-content: center;

    align-items: center;

    padding: 20px;
}

.login-container {

    width: 100%;

    max-width: 420px;

    background: #111827;

    border: 1px solid #1f2937;

    border-radius: 14px;

    padding: 30px;

    box-shadow:
        0 20px 50px
        rgba(0, 0, 0, 0.35);
}

.logo {

    text-align: center;

    font-size: 26px;

    font-weight: bold;

    margin-bottom: 8px;
}

.subtitle {

    text-align: center;

    color: #9ca3af;

    margin-bottom: 25px;

    font-size: 14px;
}

.form-group {

    margin-bottom: 18px;
}

label {

    display: block;

    margin-bottom: 7px;

    color: #d1d5db;

    font-size: 14px;
}

input {

    width: 100%;

    padding: 12px 13px;

    background: #0f172a;

    color: white;

    border: 1px solid #374151;

    border-radius: 8px;

    outline: none;

    font-size: 14px;
}

input:focus {

    border-color: #2563eb;
}

.login-btn {

    width: 100%;

    padding: 13px;

    border: none;

    border-radius: 8px;

    background: #2563eb;

    color: white;

    font-size: 15px;

    font-weight: bold;

    cursor: pointer;
}

.login-btn:hover {

    background: #1d4ed8;
}

.error {

    background: #3f1d1d;

    border: 1px solid #7f1d1d;

    color: #fecaca;

    padding: 12px;

    border-radius: 8px;

    margin-bottom: 18px;

    font-size: 14px;

    text-align: center;
}

.register-link {

    text-align: center;

    margin-top: 20px;

    color: #9ca3af;

    font-size: 14px;
}

.register-link a {

    color: #60a5fa;

    text-decoration: none;
}

.register-link a:hover {

    text-decoration: underline;
}

</style>

</head>

<body>

<div class="login-container">

    <div class="logo">
        Habit Tracker
    </div>

    <div class="subtitle">
        Login to continue tracking your habits
    </div>

    <?php if ($error !== ""): ?>

        <div class="error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>

    <form
        method="POST"
        action=""
    >

        <!-- CSRF PROTECTION -->

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($csrf_token) ?>"
        >

        <!-- USERNAME -->

        <div class="form-group">

            <label for="username">
                Username
            </label>

            <input
                type="text"
                id="username"
                name="username"
                value="<?= htmlspecialchars($username) ?>"
                required
                autocomplete="username"
            >

        </div>

        <!-- PASSWORD -->

        <div class="form-group">

            <label for="password">
                Password
            </label>

            <input
                type="password"
                id="password"
                name="password"
                required
                autocomplete="current-password"
            >

        </div>

        <button
            type="submit"
            class="login-btn"
        >
            Login
        </button>

    </form>

    <div class="register-link">

        Don't have an account?

        <a href="register.php">
            Register
        </a>

    </div>

</div>

</body>

</html>