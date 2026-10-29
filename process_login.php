<?php
require "auth.php";
require "db.php";

$loginId = isset($_POST["loginId"]) ? trim($_POST["loginId"]) : "";
$password = isset($_POST["password"]) ? $_POST["password"] : "";

// Remember the typed ID so the login form can show it again after an error.
$_SESSION["lastLoginId"] = $loginId;

if ($loginId === "" || $password === "") {
    set_flash("error", "Please enter both your ID and password.");
    header("Location: login.php");
    exit;
}

$statement = $pdo->prepare(
    "SELECT user_id, login_id, full_name, password_hash, role, hostel, room_no
     FROM users WHERE login_id = :loginId"
);
$statement->execute([":loginId" => $loginId]);
$user = $statement->fetch();

if ($user === false || !password_verify($password, $user["password_hash"])) {
    set_flash("error", "Incorrect ID or password.");
    header("Location: login.php");
    exit;
}

// A fresh session ID after login prevents an old ID from being reused.
session_regenerate_id(true);
unset($_SESSION["lastLoginId"]);

$_SESSION["userId"] = (int) $user["user_id"];
$_SESSION["loginId"] = $user["login_id"];
$_SESSION["fullName"] = $user["full_name"];
$_SESSION["role"] = $user["role"];
$_SESSION["token"] = bin2hex(random_bytes(16));

// Used to fill in the location on the new-complaint form.
if ($user["hostel"] !== null) {
    $_SESSION["room"] = $user["hostel"] . ", Room " . $user["room_no"];
} else {
    $_SESSION["room"] = "";
}

header("Location: " . ($user["role"] === "staff" ? "manage.php" : "index.php"));
exit;
?>
