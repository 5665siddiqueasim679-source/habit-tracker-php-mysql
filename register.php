<?php

// ==========================================
// START SESSION
// ==========================================

session_start();

require_once "db.php";

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
$success = "";

$username = "";
$email = "";

// ==========================================
// FORM SUBMITTED
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

    $email =
        trim($_POST["email"] ?? "");

    $password =
        $_POST["password"] ?? "";

    // ======================================
    // VALIDATION
    // ======================================

    if (
        $username === "" ||
        $email === "" ||
        $password === ""
    ) {

        $error =
            "Please fill in all fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error =
            "Please enter a valid email address.";

    } elseif (strlen($username) < 3) {

        $error =
            "Username must be at least 3 characters.";

    } elseif (strlen($password) < 6) {

        $error =
            "Password must be at least 6 characters.";

    } else {

        // ==================================
        // CHECK DUPLICATE USERNAME / EMAIL
        // ==================================

        $stmt = $conn->prepare(
            "SELECT id
             FROM users
             WHERE username = ?
             OR email = ?"
        );

        if (!$stmt) {

            $error =
                "Something went wrong. Please try again.";

        } else {

            $stmt->bind_param(
                "ss",
                $username,
                $email
            );

            $stmt->execute();

            $result =
                $stmt->get_result();

            if ($result->num_rows > 0) {

                $error =
                    "Username or email already exists.";

            } else {

                // ==============================
                // HASH PASSWORD
                // ==============================

                $hashed_password =
                    password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                // ==============================
                // INSERT USER
                // ==============================

                $insert = $conn->prepare(
                    "INSERT INTO users
                    (
                        username,
                        email,
                        password
                    )
                    VALUES (?, ?, ?)"
                );

                if (!$insert) {

                    $error =
                        "Something went wrong. Please try again.";

                } else {

                    $insert->bind_param(
                        "sss",
                        $username,
                        $email,
                        $hashed_password
                    );

                    if ($insert->execute()) {

                        $success =
                            "Registration successful! You can now login.";

                        // Clear fields after successful registration
                        $username = "";
                        $email = "";

                    } else {

                        $error =
                            "Registration failed. Please try again.";
                    }

                    $insert->close();
                }
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
    Register - Habit Tracker
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

.register-container {

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

.register-btn {

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

.register-btn:hover {

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

.success {

    background: #064e3b;

    border: 1px solid #065f46;

    color: #a7f3d0;

    padding: 12px;

    border-radius: 8px;

    margin-bottom: 18px;

    font-size: 14px;

    text-align: center;
}

.login-link {

    text-align: center;

    margin-top: 20px;

    color: #9ca3af;

    font-size: 14px;
}

.login-link a {

    color: #60a5fa;

    text-decoration: none;
}

.login-link a:hover {

    text-decoration: underline;
}

</style>

</head>

<body>

<div class="register-container">

    <div class="logo">
        Habit Tracker
    </div>

    <div class="subtitle">
        Create your account
    </div>

    <?php if ($error !== ""): ?>

        <div class="error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>

    <?php if ($success !== ""): ?>

        <div class="success">

            <?= htmlspecialchars($success) ?>

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
                minlength="3"
                autocomplete="username"
            >

        </div>

        <!-- EMAIL -->

        <div class="form-group">

            <label for="email">
                Email
            </label>

            <input
                type="email"
                id="email"
                name="email"
                value="<?= htmlspecialchars($email) ?>"
                required
                autocomplete="email"
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
                minlength="6"
                autocomplete="new-password"
            >

        </div>

        <button
            type="submit"
            class="register-btn"
        >
            Register
        </button>

    </form>

    <div class="login-link">

        Already have an account?

        <a href="login.php">
            Login
        </a>

    </div>

</div>

</body>

</html>