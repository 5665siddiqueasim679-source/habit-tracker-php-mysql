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

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] =
        bin2hex(random_bytes(32));
}

$csrf_token =
    $_SESSION["csrf_token"];

$error = "";
$success = "";

// ==========================================
// CSRF FUNCTION
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
// HANDLE POST REQUESTS
// ==========================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (
        !verify_csrf(
            $_POST["csrf_token"] ?? null
        )
    ) {

        die(
            "Invalid request. Please refresh the page."
        );
    }

    $action =
        $_POST["action"] ?? "";

    // ======================================
    // ADD HABIT
    // ======================================

    if ($action === "add") {

        $habit_name =
            trim($_POST["habit_name"] ?? "");

        $description =
            trim($_POST["description"] ?? "");

        $frequency =
            $_POST["frequency"] ?? "Daily";

        $category_id =
            (int) ($_POST["category_id"] ?? 0);

        $allowed =
            ["Daily", "Weekly", "Monthly"];

        if (!in_array(
            $frequency,
            $allowed,
            true
        )) {

            $frequency = "Daily";
        }

        if ($habit_name === "") {

            $error =
                "Habit name is required.";

        } else {

            // --------------------------------------
            // Validate category ownership
            // --------------------------------------

            if ($category_id > 0) {

                $category_stmt = $conn->prepare(
                    "SELECT id
                     FROM categories
                     WHERE id = ?
                     AND user_id = ?"
                );

                $category_stmt->bind_param(
                    "ii",
                    $category_id,
                    $user_id
                );

                $category_stmt->execute();

                $category_result =
                    $category_stmt->get_result();

                if ($category_result->num_rows === 0) {

                    $category_id = 0;
                }

                $category_stmt->close();
            }

            // --------------------------------------
            // Insert habit
            // --------------------------------------

            if ($category_id > 0) {

                $stmt = $conn->prepare(
                    "INSERT INTO habits
                    (
                        user_id,
                        category_id,
                        habit_name,
                        description,
                        frequency
                    )
                    VALUES (?, ?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "iisss",
                    $user_id,
                    $category_id,
                    $habit_name,
                    $description,
                    $frequency
                );

            } else {

                $stmt = $conn->prepare(
                    "INSERT INTO habits
                    (
                        user_id,
                        habit_name,
                        description,
                        frequency
                    )
                    VALUES (?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "isss",
                    $user_id,
                    $habit_name,
                    $description,
                    $frequency
                );
            }

            if ($stmt->execute()) {

                $success =
                    "Habit added successfully.";

            } else {

                $error =
                    "Unable to add habit.";
            }

            $stmt->close();
        }
    }

    // ======================================
    // EDIT HABIT
    // ======================================

    elseif ($action === "edit") {

        $habit_id =
            (int) ($_POST["habit_id"] ?? 0);

        $habit_name =
            trim($_POST["habit_name"] ?? "");

        $description =
            trim($_POST["description"] ?? "");

        $frequency =
            $_POST["frequency"] ?? "Daily";

        $category_id =
            (int) ($_POST["category_id"] ?? 0);

        $allowed =
            ["Daily", "Weekly", "Monthly"];

        if (!in_array(
            $frequency,
            $allowed,
            true
        )) {

            $frequency = "Daily";
        }

        if (
            $habit_id <= 0 ||
            $habit_name === ""
        ) {

            $error =
                "Please provide a valid habit.";

        } else {

            // --------------------------------------
            // Validate category ownership
            // --------------------------------------

            if ($category_id > 0) {

                $category_stmt = $conn->prepare(
                    "SELECT id
                     FROM categories
                     WHERE id = ?
                     AND user_id = ?"
                );

                $category_stmt->bind_param(
                    "ii",
                    $category_id,
                    $user_id
                );

                $category_stmt->execute();

                $category_result =
                    $category_stmt->get_result();

                if ($category_result->num_rows === 0) {

                    $category_id = 0;
                }

                $category_stmt->close();
            }

            // --------------------------------------
            // Update habit
            // --------------------------------------

            if ($category_id > 0) {

                $stmt = $conn->prepare(
                    "UPDATE habits
                     SET
                        category_id = ?,
                        habit_name = ?,
                        description = ?,
                        frequency = ?
                     WHERE id = ?
                     AND user_id = ?"
                );

                $stmt->bind_param(
                    "isssii",
                    $category_id,
                    $habit_name,
                    $description,
                    $frequency,
                    $habit_id,
                    $user_id
                );

            } else {

                $stmt = $conn->prepare(
                    "UPDATE habits
                     SET
                        category_id = NULL,
                        habit_name = ?,
                        description = ?,
                        frequency = ?
                     WHERE id = ?
                     AND user_id = ?"
                );

                $stmt->bind_param(
                    "sssii",
                    $habit_name,
                    $description,
                    $frequency,
                    $habit_id,
                    $user_id
                );
            }

            if ($stmt->execute()) {

                $success =
                    "Habit updated successfully.";

            } else {

                $error =
                    "Unable to update habit.";
            }

            $stmt->close();
        }
    }

    // ======================================
    // COMPLETE HABIT
    // ======================================

    elseif ($action === "complete") {

        $habit_id =
            (int) ($_POST["habit_id"] ?? 0);

        if ($habit_id > 0) {

            $stmt = $conn->prepare(
                "INSERT IGNORE INTO habit_completions
                (
                    habit_id,
                    completed_date
                )
                SELECT
                    id,
                    ?
                FROM habits
                WHERE id = ?
                AND user_id = ?"
            );

            $stmt->bind_param(
                "sii",
                $today,
                $habit_id,
                $user_id
            );

            if ($stmt->execute()) {

                $success =
                    "Habit marked as completed.";

            } else {

                $error =
                    "Unable to complete habit.";
            }

            $stmt->close();
        }
    }

    // ======================================
    // UNDO COMPLETION
    // ======================================

    elseif ($action === "undo") {

        $habit_id =
            (int) ($_POST["habit_id"] ?? 0);

        $stmt = $conn->prepare(
            "DELETE hc
             FROM habit_completions hc
             INNER JOIN habits h
                ON hc.habit_id = h.id
             WHERE hc.habit_id = ?
             AND hc.completed_date = ?
             AND h.user_id = ?"
        );

        $stmt->bind_param(
            "isi",
            $habit_id,
            $today,
            $user_id
        );

        if ($stmt->execute()) {

            $success =
                "Today's completion has been undone.";

        } else {

            $error =
                "Unable to undo completion.";
        }

        $stmt->close();
    }

    // ======================================
    // DELETE HABIT
    // ======================================

    elseif ($action === "delete") {

        $habit_id =
            (int) ($_POST["habit_id"] ?? 0);

        $stmt = $conn->prepare(
            "DELETE FROM habits
             WHERE id = ?
             AND user_id = ?"
        );

        $stmt->bind_param(
            "ii",
            $habit_id,
            $user_id
        );

        if ($stmt->execute()) {

            $success =
                "Habit deleted successfully.";

        } else {

            $error =
                "Unable to delete habit.";
        }

        $stmt->close();
    }
}

// ==========================================
// GET USER CATEGORIES
// ==========================================

$category_stmt = $conn->prepare(
    "SELECT
        id,
        name
     FROM categories
     WHERE user_id = ?
     ORDER BY name ASC"
);

$category_stmt->bind_param(
    "i",
    $user_id
);

$category_stmt->execute();

$categories =
    $category_stmt
        ->get_result()
        ->fetch_all(MYSQLI_ASSOC);

$category_stmt->close();

// ==========================================
// GET HABITS
// ==========================================

$stmt = $conn->prepare(
    "SELECT
        h.id,
        h.habit_name,
        h.description,
        h.frequency,
        h.category_id,
        h.created_at,
        c.name AS category_name,

        CASE
            WHEN hc.id IS NOT NULL THEN 1
            ELSE 0
        END AS completed_today

     FROM habits h

     LEFT JOIN categories c
        ON h.category_id = c.id
        AND c.user_id = ?

     LEFT JOIN habit_completions hc
        ON h.id = hc.habit_id
        AND hc.completed_date = ?

     WHERE h.user_id = ?

     ORDER BY h.created_at DESC"
);

$stmt->bind_param(
    "isi",
    $user_id,
    $today,
    $user_id
);

$stmt->execute();

$habits =
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

<title>Habits - Habit Tracker</title>

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
    max-width: 1100px;
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

.form-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 15px;
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

input,
textarea,
select {
    width: 100%;
    background: #0f172a;
    color: white;
    border: 1px solid #374151;
    border-radius: 8px;
    padding: 11px;
    outline: none;
}

textarea {
    resize: vertical;
    min-height: 80px;
}

input:focus,
textarea:focus,
select:focus {
    border-color: #2563eb;
}

.button {
    border: none;
    padding: 10px 15px;
    border-radius: 7px;
    color: white;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}

.primary {
    background: #2563eb;
}

.success-btn {
    background: #059669;
}

.warning-btn {
    background: #d97706;
}

.danger {
    background: #dc2626;
}

.secondary {
    background: #374151;
}

.habit {
    border: 1px solid #1f2937;
    background: #0f172a;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 15px;
}

.habit-header {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 10px;
}

.habit-name {
    font-size: 18px;
    font-weight: bold;
}

.frequency {
    color: #93c5fd;
    font-size: 13px;
}

.category {
    display: inline-block;
    margin-top: 8px;
    padding: 5px 9px;
    border-radius: 20px;
    background: #1e3a8a;
    color: #bfdbfe;
    font-size: 12px;
}

.no-category {
    display: inline-block;
    margin-top: 8px;
    padding: 5px 9px;
    border-radius: 20px;
    background: #374151;
    color: #d1d5db;
    font-size: 12px;
}

.description {
    color: #9ca3af;
    margin-top: 12px;
    margin-bottom: 15px;
}

.actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.inline-form {
    display: inline;
}

.completed-label {
    color: #86efac;
    margin-bottom: 12px;
    font-size: 14px;
}

.empty {
    color: #9ca3af;
    padding: 15px 0;
}

.edit-form {
    display: none;
    margin-top: 18px;
    padding-top: 18px;
    border-top: 1px solid #1f2937;
}

.edit-form.show {
    display: block;
}

.edit-grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr;
    gap: 15px;
}

.form-actions {
    display: flex;
    gap: 8px;
    margin-top: 15px;
}

@media (max-width: 800px) {

    .edit-grid {
        grid-template-columns: 1fr;
    }
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

    .form-grid {
        grid-template-columns: 1fr;
    }

    .habit-header {
        flex-direction: column;
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

        <a href="habits.php" class="active">
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

        <a href="categories.php">
            Categories
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
            My Habits
        </h1>

        <p>
            Build consistency by completing your habits every day.
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
         ADD NEW HABIT
    ====================================== -->

    <div class="card">

        <h2>
            Add New Habit
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

            <div class="form-grid">

                <div class="form-group">

                    <label>
                        Habit Name
                    </label>

                    <input
                        type="text"
                        name="habit_name"
                        placeholder="e.g. Read for 30 minutes"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>
                        Frequency
                    </label>

                    <select name="frequency">

                        <option value="Daily">
                            Daily
                        </option>

                        <option value="Weekly">
                            Weekly
                        </option>

                        <option value="Monthly">
                            Monthly
                        </option>

                    </select>

                </div>

            </div>


            <div class="form-group">

                <label>
                    Category
                </label>

                <select name="category_id">

                    <option value="0">
                        No Category
                    </option>

                    <?php foreach ($categories as $category): ?>

                        <option
                            value="<?= $category["id"] ?>"
                        >
                            <?= htmlspecialchars(
                                $category["name"]
                            ) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="form-group">

                <label>
                    Description
                </label>

                <textarea
                    name="description"
                    placeholder="Optional description"
                ></textarea>

            </div>

            <button
                type="submit"
                class="button primary"
            >
                Add Habit
            </button>

        </form>

    </div>


    <!-- =====================================
         YOUR HABITS
    ====================================== -->

    <div class="card">

        <h2>
            Your Habits
        </h2>

        <?php if (empty($habits)): ?>

            <div class="empty">
                No habits yet. Add your first habit above.
            </div>

        <?php else: ?>

            <?php foreach ($habits as $habit): ?>

                <div class="habit">

                    <div class="habit-header">

                        <div>

                            <div class="habit-name">

                                <?= htmlspecialchars(
                                    $habit["habit_name"]
                                ) ?>

                            </div>

                            <?php if (
                                !empty($habit["category_name"])
                            ): ?>

                                <span class="category">

                                    <?= htmlspecialchars(
                                        $habit["category_name"]
                                    ) ?>

                                </span>

                            <?php else: ?>

                                <span class="no-category">
                                    No Category
                                </span>

                            <?php endif; ?>

                        </div>

                        <div class="frequency">

                            <?= htmlspecialchars(
                                $habit["frequency"]
                            ) ?>

                        </div>

                    </div>


                    <?php if (
                        !empty($habit["description"])
                    ): ?>

                        <div class="description">

                            <?= htmlspecialchars(
                                $habit["description"]
                            ) ?>

                        </div>

                    <?php endif; ?>


                    <?php if (
                        $habit["completed_today"]
                    ): ?>

                        <div class="completed-label">

                            ✓ Completed today

                        </div>

                    <?php endif; ?>


                    <div class="actions">

                        <?php if (
                            $habit["completed_today"]
                        ): ?>

                            <form
                                method="POST"
                                class="inline-form"
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
                                    value="undo"
                                >

                                <input
                                    type="hidden"
                                    name="habit_id"
                                    value="<?= $habit["id"] ?>"
                                >

                                <button
                                    type="submit"
                                    class="button warning-btn"
                                >
                                    Undo
                                </button>

                            </form>

                        <?php else: ?>

                            <form
                                method="POST"
                                class="inline-form"
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
                                    value="complete"
                                >

                                <input
                                    type="hidden"
                                    name="habit_id"
                                    value="<?= $habit["id"] ?>"
                                >

                                <button
                                    type="submit"
                                    class="button success-btn"
                                >
                                    Complete
                                </button>

                            </form>

                        <?php endif; ?>


                        <button
                            type="button"
                            class="button secondary"
                            onclick="toggleEdit(
                                <?= $habit["id"] ?>
                            )"
                        >
                            Edit
                        </button>


                        <form
                            method="POST"
                            class="inline-form"
                            onsubmit="
                                return confirm(
                                    'Delete this habit?'
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
                                name="habit_id"
                                value="<?= $habit["id"] ?>"
                            >

                            <button
                                type="submit"
                                class="button danger"
                            >
                                Delete
                            </button>

                        </form>

                    </div>


                    <!-- =====================================
                         EDIT FORM
                    ====================================== -->

                    <div
                        id="edit-<?= $habit["id"] ?>"
                        class="edit-form"
                    >

                        <form method="POST">

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
                                value="edit"
                            >

                            <input
                                type="hidden"
                                name="habit_id"
                                value="<?= $habit["id"] ?>"
                            >


                            <div class="edit-grid">

                                <div class="form-group">

                                    <label>
                                        Habit Name
                                    </label>

                                    <input
                                        type="text"
                                        name="habit_name"
                                        value="<?= htmlspecialchars(
                                            $habit["habit_name"]
                                        ) ?>"
                                        required
                                    >

                                </div>


                                <div class="form-group">

                                    <label>
                                        Frequency
                                    </label>

                                    <select name="frequency">

                                        <option
                                            value="Daily"
                                            <?= $habit["frequency"] === "Daily"
                                                ? "selected"
                                                : "" ?>
                                        >
                                            Daily
                                        </option>

                                        <option
                                            value="Weekly"
                                            <?= $habit["frequency"] === "Weekly"
                                                ? "selected"
                                                : "" ?>
                                        >
                                            Weekly
                                        </option>

                                        <option
                                            value="Monthly"
                                            <?= $habit["frequency"] === "Monthly"
                                                ? "selected"
                                                : "" ?>
                                        >
                                            Monthly
                                        </option>

                                    </select>

                                </div>


                                <div class="form-group">

                                    <label>
                                        Category
                                    </label>

                                    <select name="category_id">

                                        <option value="0">
                                            No Category
                                        </option>

                                        <?php foreach (
                                            $categories
                                            as $category
                                        ): ?>

                                            <option
                                                value="<?= $category["id"] ?>"
                                                <?= (int) $habit["category_id"]
                                                    === (int) $category["id"]
                                                    ? "selected"
                                                    : "" ?>
                                            >
                                                <?= htmlspecialchars(
                                                    $category["name"]
                                                ) ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>

                            </div>


                            <div class="form-group">

                                <label>
                                    Description
                                </label>

                                <textarea
                                    name="description"
                                ><?= htmlspecialchars(
                                    $habit["description"] ?? ""
                                ) ?></textarea>

                            </div>


                            <div class="form-actions">

                                <button
                                    type="submit"
                                    class="button primary"
                                >
                                    Save Changes
                                </button>

                                <button
                                    type="button"
                                    class="button secondary"
                                    onclick="toggleEdit(
                                        <?= $habit["id"] ?>
                                    )"
                                >
                                    Cancel
                                </button>

                            </div>

                        </form>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</div>


<script>

function toggleEdit(habitId) {

    const editForm =
        document.getElementById(
            "edit-" + habitId
        );

    if (editForm.classList.contains("show")) {

        editForm.classList.remove("show");

    } else {

        editForm.classList.add("show");
    }
}

</script>

</body>
</html>