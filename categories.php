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

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION["csrf_token"];

$error = "";
$success = "";

// ==========================================
// CSRF VERIFICATION
// ==========================================

function verify_csrf($token)
{
    return isset($token)
        && isset($_SESSION["csrf_token"])
        && hash_equals(
            $_SESSION["csrf_token"],
            $token
        );
}

// ==========================================
// HANDLE REQUESTS
// ==========================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!verify_csrf($_POST["csrf_token"] ?? null)) {
        die("Invalid request. Please refresh the page.");
    }

    $action = $_POST["action"] ?? "";

    // ======================================
    // ADD CATEGORY
    // ======================================

    if ($action === "add") {

        $name = trim($_POST["name"] ?? "");

        if ($name === "") {

            $error = "Category name is required.";

        } elseif (strlen($name) > 50) {

            $error = "Category name cannot exceed 50 characters.";

        } else {

            // Check duplicate category
            $check = $conn->prepare(
                "SELECT id
                 FROM categories
                 WHERE user_id = ?
                 AND name = ?"
            );

            $check->bind_param(
                "is",
                $user_id,
                $name
            );

            $check->execute();

            $result = $check->get_result();

            if ($result->num_rows > 0) {

                $error = "This category already exists.";

            } else {

                $stmt = $conn->prepare(
                    "INSERT INTO categories
                    (user_id, name)
                    VALUES (?, ?)"
                );

                $stmt->bind_param(
                    "is",
                    $user_id,
                    $name
                );

                if ($stmt->execute()) {

                    $success =
                        "Category added successfully.";

                } else {

                    $error =
                        "Unable to add category.";
                }

                $stmt->close();
            }

            $check->close();
        }
    }

    // ======================================
    // DELETE CATEGORY
    // ======================================

    elseif ($action === "delete") {

        $category_id =
            (int) ($_POST["category_id"] ?? 0);

        if ($category_id <= 0) {

            $error = "Invalid category.";

        } else {

            $stmt = $conn->prepare(
                "DELETE FROM categories
                 WHERE id = ?
                 AND user_id = ?"
            );

            $stmt->bind_param(
                "ii",
                $category_id,
                $user_id
            );

            if ($stmt->execute()) {

                if ($stmt->affected_rows > 0) {

                    $success =
                        "Category deleted successfully.";

                } else {

                    $error =
                        "Category not found.";
                }

            } else {

                $error =
                    "Unable to delete category.";
            }

            $stmt->close();
        }
    }
}

// ==========================================
// GET CATEGORIES
// ==========================================

$stmt = $conn->prepare(
    "SELECT
        c.id,
        c.name,
        c.created_at,
        COUNT(h.id) AS habit_count

     FROM categories c

     LEFT JOIN habits h
        ON c.id = h.category_id
        AND h.user_id = ?

     WHERE c.user_id = ?

     GROUP BY
        c.id,
        c.name,
        c.created_at

     ORDER BY c.name ASC"
);

$stmt->bind_param(
    "ii",
    $user_id,
    $user_id
);

$stmt->execute();

$categories =
    $stmt
        ->get_result()
        ->fetch_all(MYSQLI_ASSOC);

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

<title>Categories - Habit Tracker</title>

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
    color: white !important;
    text-decoration: none;
    padding: 9px 14px;
    border-radius: 7px;
}

.container {
    width: 92%;
    max-width: 900px;
    margin: 40px auto;
}

.header {
    margin-bottom: 25px;
}

.header h1 {
    font-size: 30px;
    margin-bottom: 8px;
}

.header p {
    color: #9ca3af;
}

.card {
    background: #111827;
    border: 1px solid #1f2937;
    border-radius: 12px;
    padding: 22px;
    margin-bottom: 20px;
}

.card h2 {
    margin-bottom: 18px;
}

.form-group {
    margin-bottom: 15px;
}

label {
    display: block;
    margin-bottom: 7px;
    color: #d1d5db;
    font-size: 14px;
}

input {
    width: 100%;
    background: #0f172a;
    color: white;
    border: 1px solid #374151;
    border-radius: 8px;
    padding: 11px;
    outline: none;
}

input:focus {
    border-color: #2563eb;
}

.button {
    border: none;
    padding: 10px 15px;
    border-radius: 7px;
    color: white;
    cursor: pointer;
}

.primary {
    background: #2563eb;
}

.danger {
    background: #dc2626;
}

.message {
    padding: 13px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.success {
    background: #064e3b;
    border: 1px solid #065f46;
    color: #a7f3d0;
}

.error {
    background: #3f1d1d;
    border: 1px solid #7f1d1d;
    color: #fecaca;
}

.category {
    background: #0f172a;
    border: 1px solid #1f2937;
    border-radius: 10px;
    padding: 18px;
    margin-bottom: 12px;

    display: flex;
    justify-content: space-between;
    align-items: center;

    gap: 15px;
}

.category-name {
    font-size: 17px;
    font-weight: bold;
}

.category-info {
    color: #9ca3af;
    font-size: 13px;
    margin-top: 5px;
}

.empty {
    color: #9ca3af;
    padding: 15px 0;
}

@media (max-width: 700px) {

    .navbar {
        flex-direction: column;
        text-align: center;
    }

    .nav-links {
        justify-content: center;
    }

    .user-area {
        justify-content: center;
    }

    .category {
        flex-direction: column;
        align-items: flex-start;
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

        <a href="reminders.php">
            Reminders
        </a>

        <a href="categories.php" class="active">
            Categories
        </a>

        <a href="profile.php">
            Profile
        </a>

        <a href="feedback.php">
            Feedback
        </a>

    </div>

    <div class="user-area">

        <span class="username">
            <?= htmlspecialchars($username) ?>
        </span>

        <a
            href="logout.php"
            class="logout"
        >
            Logout
        </a>

    </div>

</nav>


<div class="container">

    <div class="header">

        <h1>
            Categories
        </h1>

        <p>
            Organize your habits into different categories.
        </p>

    </div>


    <?php if ($success !== ""): ?>

        <div class="message success">

            <?= htmlspecialchars($success) ?>

        </div>

    <?php endif; ?>


    <?php if ($error !== ""): ?>

        <div class="message error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <!-- =====================================
         ADD CATEGORY
    ====================================== -->

    <div class="card">

        <h2>
            Add New Category
        </h2>

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($csrf_token) ?>"
            >

            <input
                type="hidden"
                name="action"
                value="add"
            >

            <div class="form-group">

                <label>
                    Category Name
                </label>

                <input
                    type="text"
                    name="name"
                    placeholder="e.g. Study"
                    maxlength="50"
                    required
                >

            </div>

            <button
                type="submit"
                class="button primary"
            >
                Add Category
            </button>

        </form>

    </div>


    <!-- =====================================
         CATEGORY LIST
    ====================================== -->

    <div class="card">

        <h2>
            Your Categories
        </h2>


        <?php if (empty($categories)): ?>

            <div class="empty">

                No categories yet.
                Create your first category above.

            </div>

        <?php else: ?>

            <?php foreach ($categories as $category): ?>

                <div class="category">

                    <div>

                        <div class="category-name">

                            <?= htmlspecialchars(
                                $category["name"]
                            ) ?>

                        </div>

                        <div class="category-info">

                            <?= (int) $category["habit_count"] ?>

                            habit(s)

                        </div>

                    </div>


                    <form
                        method="POST"
                        onsubmit="
                            return confirm(
                                'Delete this category? Habits using it will not be deleted.'
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
                            value="delete"
                        >

                        <input
                            type="hidden"
                            name="category_id"
                            value="<?= $category["id"] ?>"
                        >

                        <button
                            type="submit"
                            class="button danger"
                        >
                            Delete
                        </button>

                    </form>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</div>

</body>
</html>