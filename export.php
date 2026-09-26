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

// CSV download headers
header("Content-Type: text/csv; charset=utf-8");
header(
    "Content-Disposition: attachment; filename=habit_tracker_export_" .
    date("Y-m-d") .
    ".csv"
);
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Open output stream
$output = fopen("php://output", "w");

// UTF-8 BOM for better Excel compatibility
fwrite($output, "\xEF\xBB\xBF");

// CSV column headings
fputcsv($output, [
    "Habit",
    "Description",
    "Frequency",
    "Completion Date",
    "Created At"
]);

// Get only the logged-in user's habit completion data
$sql = "
    SELECT
        h.habit_name,
        h.description,
        h.frequency,
        hc.completed_date,
        h.created_at
    FROM habit_completions hc
    INNER JOIN habits h
        ON hc.habit_id = h.id
    WHERE h.user_id = ?
    ORDER BY hc.completed_date DESC, h.habit_name ASC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    fclose($output);
    exit("Failed to prepare export query.");
}

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

// Add each completion as a CSV row
while ($row = $result->fetch_assoc()) {

    fputcsv($output, [
        $row["habit_name"],
        $row["description"],
        $row["frequency"],
        $row["completed_date"],
        $row["created_at"]
    ]);
}

$stmt->close();
fclose($output);

exit();

?>