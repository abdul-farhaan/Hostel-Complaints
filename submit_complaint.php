<?php
require "auth.php";
require "db.php";

require_student();

$categoryId = isset($_POST["categoryId"]) ? (int) $_POST["categoryId"] : 0;
$title = isset($_POST["title"]) ? trim($_POST["title"]) : "";
$location = isset($_POST["location"]) ? trim($_POST["location"]) : "";
$priority = isset($_POST["priority"]) ? $_POST["priority"] : "";
$description = isset($_POST["description"]) ? trim($_POST["description"]) : "";
$token = isset($_POST["token"]) ? $_POST["token"] : "";

$old = [
    "categoryId" => $categoryId,
    "title" => $title,
    "location" => $location,
    "priority" => $priority,
    "description" => $description,
];

// Send the user back to the form with a message and the values they typed.
function reject($text, $old) {
    set_flash("error", $text);
    $_SESSION["oldComplaint"] = $old;
    header("Location: new_complaint.php");
    exit;
}

if (!token_is_valid($token)) {
    reject("The form has expired. Please submit it again.", $old);
}

// The browser has already checked these in new_complaint.js, but a request can
// bypass JavaScript, so the server checks them again.
if ($categoryId <= 0 || $title === "" || $location === "" || $description === "") {
    reject("Category, title, location and details are required.", $old);
}

if (!in_array($priority, ["Low", "Medium", "High"], true)) {
    reject("Choose a priority: Low, Medium or High.", $old);
}

if (mb_strlen($title) > 100) {
    reject("The title can be at most 100 characters long.", $old);
}

if (mb_strlen($location) > 80) {
    reject("The location can be at most 80 characters long.", $old);
}

if (mb_strlen($description) < 10 || mb_strlen($description) > 1000) {
    reject("The details must be between 10 and 1000 characters long.", $old);
}

// Do not accept the same problem twice while the first complaint is still open.
$statement = $pdo->prepare(
    "SELECT complaint_id FROM complaints
     WHERE user_id = :userId AND category_id = :categoryId AND title = :title AND status <> 'Resolved'"
);
$statement->execute([":userId" => $_SESSION["userId"], ":categoryId" => $categoryId, ":title" => $title]);
$existing = $statement->fetch();

if ($existing !== false) {
    reject("You already have an open complaint about this (#" . $existing["complaint_id"] . "). "
        . "Please wait for it to be resolved instead of submitting it again.", $old);
}

// The complaint and the first row of its history are saved together: either
// both are stored or, if anything fails, neither is.
try {
    $pdo->beginTransaction();

    $statement = $pdo->prepare(
        "INSERT INTO complaints (user_id, category_id, title, description, location, priority)
         VALUES (:userId, :categoryId, :title, :description, :location, :priority)"
    );
    $statement->execute([
        ":userId" => $_SESSION["userId"],
        ":categoryId" => $categoryId,
        ":title" => $title,
        ":description" => $description,
        ":location" => $location,
        ":priority" => $priority,
    ]);
    $complaintId = (int) $pdo->lastInsertId();

    $statement = $pdo->prepare(
        "INSERT INTO complaint_updates (complaint_id, old_status, new_status, updated_by)
         VALUES (:complaintId, NULL, 'Submitted', :userId)"
    );
    $statement->execute([":complaintId" => $complaintId, ":userId" => $_SESSION["userId"]]);

    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    // A category_id that does not exist is rejected by the foreign key.
    reject("The complaint could not be saved. Check the category and try again.", $old);
}

set_flash("success", "Complaint #" . $complaintId . " has been submitted. Its status will update here automatically.");
header("Location: index.php");
exit;
?>
