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
// CHECK LOGIN
// ==========================================

if (!isset($_SESSION["user_id"])) {

    header("Location: login.php");

    exit();
}

$user_id = $_SESSION["user_id"];

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

$password_error = "";
$password_success = "";

// ==========================================
// GET CURRENT USER
// ==========================================

$stmt = $conn->prepare(
    "SELECT
        id,
        username,
        email,
        password,
        created_at
     FROM users
     WHERE id = ?"
);

$stmt->bind_param(
    "i",
    $user_id
);

$stmt->execute();

$result =
    $stmt->get_result();

$user =
    $result->fetch_assoc();

$stmt->close();

// ==========================================
// USER DOES NOT EXIST
// ==========================================

if (!$user) {

    session_unset();

    session_destroy();

    header("Location: login.php");

    exit();
}

// ==========================================
// CHANGE PASSWORD
// ==========================================

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["action"]) &&
    $_POST["action"] === "change_password"
) {

    // ======================================
    // VERIFY CSRF
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
    // GET PASSWORD DATA
    // ======================================

    $current_password =
        $_POST["current_password"] ?? "";

    $new_password =
        $_POST["new_password"] ?? "";

    $confirm_password =
        $_POST["confirm_password"] ?? "";

    // ======================================
    // VALIDATION
    // ======================================

    if (
        $current_password === "" ||
        $new_password === "" ||
        $confirm_password === ""
    ) {

        $password_error =
            "Please fill in all password fields.";

    } elseif (
        !password_verify(
            $current_password,
            $user["password"]
        )
    ) {

        $password_error =
            "Current password is incorrect.";

    } elseif (
        strlen($new_password) < 6
    ) {

        $password_error =
            "New password must be at least 6 characters.";

    } elseif (
        $new_password !== $confirm_password
    ) {

        $password_error =
            "New passwords do not match.";

    } elseif (
        password_verify(
            $new_password,
            $user["password"]
        )
    ) {

        $password_error =
            "New password must be different from your current password.";

    } else {

        // ==================================
        // HASH NEW PASSWORD
        // ==================================

        $new_hashed_password =
            password_hash(
                $new_password,
                PASSWORD_DEFAULT
            );

        // ==================================
        // UPDATE PASSWORD
        // ==================================

        $stmt = $conn->prepare(
            "UPDATE users
             SET password = ?
             WHERE id = ?"
        );

        $stmt->bind_param(
            "si",
            $new_hashed_password,
            $user_id
        );

        if ($stmt->execute()) {

            $password_success =
                "Password changed successfully.";

            // Refresh password stored in $user
            $user["password"] =
                $new_hashed_password;

        } else {

            $password_error =
                "Password could not be changed. Please try again.";
        }

        $stmt->close();
    }
}

// ==========================================
// DELETE ACCOUNT
// ==========================================

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["action"]) &&
    $_POST["action"] === "delete_account"
) {

    // ======================================
    // VERIFY CSRF
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
    // GET CONFIRMATION
    // ======================================

    $delete_password =
        $_POST["delete_password"] ?? "";

    // ======================================
    // VERIFY PASSWORD
    // ======================================

    if (
        !password_verify(
            $delete_password,
            $user["password"]
        )
    ) {

        $error =
            "Incorrect password. Your account was not deleted.";

    } else {

        // ==================================
        // DELETE USER
        // ==================================

        $stmt = $conn->prepare(
            "DELETE FROM users
             WHERE id = ?"
        );

        $stmt->bind_param(
            "i",
            $user_id
        );

        if ($stmt->execute()) {

            // ==================================
            // DESTROY SESSION
            // ==================================

            $_SESSION = [];

            if (
                ini_get("session.use_cookies")
            ) {

                $params =
                    session_get_cookie_params();

                setcookie(
                    session_name(),
                    "",
                    time() - 42000,
                    $params["path"],
                    $params["domain"],
                    $params["secure"],
                    $params["httponly"]
                );
            }

            session_destroy();

            // ==================================
            // REDIRECT
            // ==================================

            header(
                "Location: register.php"
            );

            exit();

        } else {

            $error =
                "Account could not be deleted. Please try again.";
        }

        $stmt->close();
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
    Profile - Habit Tracker
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
}

/* ==========================================
   NAVBAR
========================================== */

.navbar {

    background: #111827;

    border-bottom: 1px solid #1f2937;

    padding: 18px 30px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    flex-wrap: wrap;

    gap: 15px;
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

    color: white;

    text-decoration: none;

    padding: 9px 14px;

    border-radius: 7px;
}

/* ==========================================
   CONTAINER
========================================== */

.container {

    width: 92%;

    max-width: 1000px;

    margin: 40px auto;
}

/* ==========================================
   PAGE HEADER
========================================== */

.page-header {

    margin-bottom: 25px;
}

.page-header h1 {

    font-size: 30px;

    margin-bottom: 8px;
}

.page-header p {

    color: #9ca3af;
}

/* ==========================================
   GRID
========================================== */

.profile-grid {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 20px;
}

/* ==========================================
   CARD
========================================== */

.card {

    background: #111827;

    border: 1px solid #1f2937;

    border-radius: 12px;

    padding: 25px;
}

.card.full {

    grid-column: 1 / -1;
}

.card h2 {

    font-size: 19px;

    margin-bottom: 20px;
}

/* ==========================================
   PROFILE INFO
========================================== */

.profile-item {

    padding: 14px 0;

    border-bottom: 1px solid #1f2937;
}

.profile-item:last-child {

    border-bottom: none;
}

.profile-label {

    color: #9ca3af;

    font-size: 12px;

    margin-bottom: 5px;
}

.profile-value {

    color: white;

    font-size: 15px;

    word-break: break-word;
}

/* ==========================================
   FORM
========================================== */

.form-group {

    margin-bottom: 17px;
}

label {

    display: block;

    color: #d1d5db;

    font-size: 14px;

    margin-bottom: 7px;
}

input {

    width: 100%;

    padding: 12px;

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

.button {

    width: 100%;

    border: none;

    padding: 12px;

    border-radius: 8px;

    color: white;

    font-weight: bold;

    cursor: pointer;

    font-size: 14px;
}

.primary {

    background: #2563eb;
}

.primary:hover {

    background: #1d4ed8;
}

.danger {

    background: #dc2626;
}

.danger:hover {

    background: #b91c1c;
}

/* ==========================================
   MESSAGES
========================================== */

.error {

    background: #3f1d1d;

    border: 1px solid #7f1d1d;

    color: #fecaca;

    padding: 12px;

    border-radius: 8px;

    margin-bottom: 18px;

    font-size: 14px;
}

.success {

    background: #064e3b;

    border: 1px solid #065f46;

    color: #a7f3d0;

    padding: 12px;

    border-radius: 8px;

    margin-bottom: 18px;

    font-size: 14px;
}

/* ==========================================
   WARNING
========================================== */

.warning {

    background: #3f2f0b;

    border: 1px solid #854d0e;

    color: #fde68a;

    padding: 14px;

    border-radius: 8px;

    margin-bottom: 18px;

    line-height: 1.5;

    font-size: 13px;
}

/* ==========================================
   DELETE SECTION
========================================== */

.delete-card {

    border-color: #7f1d1d;
}

.delete-card h2 {

    color: #fca5a5;
}

.delete-text {

    color: #9ca3af;

    line-height: 1.6;

    margin-bottom: 18px;

    font-size: 14px;
}

/* ==========================================
   RESPONSIVE
========================================== */

@media (max-width: 750px) {

    .profile-grid {

        grid-template-columns: 1fr;
    }

    .card.full {

        grid-column: auto;
    }

    .navbar {

        flex-direction: column;

        align-items: stretch;

        text-align: center;
    }

    .nav-links {

        justify-content: center;
    }

    .user-area {

        justify-content: center;
    }
}

</style>

</head>

<body>

<!-- ==========================================
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
            href="profile.php"
            class="active"
        >
            Profile
        </a>

    </div>

    <div class="user-area">

        <span class="username">

            <?= htmlspecialchars(
                $user["username"]
            ) ?>

        </span>

        <a
            href="logout.php"
            class="logout"
        >
            Logout
        </a>

    </div>

</nav>

<!-- ==========================================
     MAIN CONTENT
========================================== -->

<div class="container">

    <div class="page-header">

        <h1>
            Profile & Settings
        </h1>

        <p>
            Manage your account and security settings.
        </p>

    </div>

    <?php if ($error !== ""): ?>

        <div class="error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>

    <div class="profile-grid">

        <!-- ==================================
             ACCOUNT INFORMATION
        =================================== -->

        <div class="card">

            <h2>
                Account Information
            </h2>

            <div class="profile-item">

                <div class="profile-label">
                    Username
                </div>

                <div class="profile-value">

                    <?= htmlspecialchars(
                        $user["username"]
                    ) ?>

                </div>

            </div>

            <div class="profile-item">

                <div class="profile-label">
                    Email
                </div>

                <div class="profile-value">

                    <?= htmlspecialchars(
                        $user["email"]
                    ) ?>

                </div>

            </div>

            <div class="profile-item">

                <div class="profile-label">
                    Account Created
                </div>

                <div class="profile-value">

                    <?= htmlspecialchars(
                        date(
                            "F j, Y",
                            strtotime(
                                $user["created_at"]
                            )
                        )
                    ) ?>

                </div>

            </div>

        </div>

        <!-- ==================================
             CHANGE PASSWORD
        =================================== -->

        <div class="card">

            <h2>
                Change Password
            </h2>

            <?php if (
                $password_error !== ""
            ): ?>

                <div class="error">

                    <?= htmlspecialchars(
                        $password_error
                    ) ?>

                </div>

            <?php endif; ?>

            <?php if (
                $password_success !== ""
            ): ?>

                <div class="success">

                    <?= htmlspecialchars(
                        $password_success
                    ) ?>

                </div>

            <?php endif; ?>

            <form
                method="POST"
                action=""
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $csrf_token
                    ) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="change_password"
                >

                <div class="form-group">

                    <label for="current_password">

                        Current Password

                    </label>

                    <input
                        type="password"
                        id="current_password"
                        name="current_password"
                        required
                        autocomplete="current-password"
                    >

                </div>

                <div class="form-group">

                    <label for="new_password">

                        New Password

                    </label>

                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        minlength="6"
                        required
                        autocomplete="new-password"
                    >

                </div>

                <div class="form-group">

                    <label for="confirm_password">

                        Confirm New Password

                    </label>

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        minlength="6"
                        required
                        autocomplete="new-password"
                    >

                </div>

                <button
                    type="submit"
                    class="button primary"
                >

                    Change Password

                </button>

            </form>

        </div>

        <!-- ==================================
             DELETE ACCOUNT
        =================================== -->

        <div class="
            card
            full
            delete-card
        ">

            <h2>
                Delete Account
            </h2>

            <div class="warning">

                This action is permanent.
                Deleting your account will also
                delete your habits and habit
                completion history.

            </div>

            <p class="delete-text">

                Enter your current password below
                to confirm that you want to
                permanently delete your account.

            </p>

            <form
                method="POST"
                action=""
                onsubmit="
                    return confirm(
                        'Are you absolutely sure you want to delete your account? This cannot be undone.'
                    );
                "
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $csrf_token
                    ) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="delete_account"
                >

                <div class="form-group">

                    <label for="delete_password">

                        Current Password

                    </label>

                    <input
                        type="password"
                        id="delete_password"
                        name="delete_password"
                        required
                        autocomplete="current-password"
                    >

                </div>

                <button
                    type="submit"
                    class="button danger"
                >

                    Permanently Delete Account

                </button>

            </form>

        </div>

    </div>

</div>

</body>

</html>