<?php
// Included at the top of every page, before any HTML output.
session_start();

function is_logged_in() {
    return isset($_SESSION["userId"]);
}

function is_staff() {
    return is_logged_in() && $_SESSION["role"] === "staff";
}

// For ordinary pages: send visitors who are not logged in to the login page.
function require_login() {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit;
    }
}

function require_student() {
    require_login();

    if (is_staff()) {
        header("Location: manage.php");
        exit;
    }
}

function require_staff() {
    require_login();

    if (!is_staff()) {
        http_response_code(403);
        echo "Only hostel staff can open this page. <a href=\"index.php\">Back to my complaints</a>";
        exit;
    }
}

// The token is created at login and sent back with every form or fetch request
// that changes data, so another website cannot submit those requests for the user.
function token_is_valid($token) {
    return isset($_SESSION["token"]) && is_string($token) && hash_equals($_SESSION["token"], $token);
}

// One-time message shown on the next page after a redirect.
function set_flash($type, $text) {
    $_SESSION["flash"] = ["type" => $type, "text" => $text];
}

function take_flash() {
    if (!isset($_SESSION["flash"])) {
        return null;
    }

    $flash = $_SESSION["flash"];
    unset($_SESSION["flash"]);
    return $flash;
}
?>
