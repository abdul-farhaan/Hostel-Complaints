<?php
// Current status of the complaints shown on a page. Called every 15 seconds by js/refresh.js.
// Request:  GET api/statuses.php?ids=3,5,9
// Response (JSON): { "ok": true, "complaints": [ { "complaintId": 3, "status": "In Progress", ... } ],
//                    "summary": { ... }, "latestId": 9, "checkedAt": "10:42:05 AM" }
require "../auth.php";
require "../db.php";
require "../functions.php";

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    send_json(405, ["ok" => false, "message" => "Use a GET request."]);
}

if (!is_logged_in()) {
    send_json(401, ["ok" => false, "message" => "Your session has ended. Please log in again."]);
}

// "3,5,9" -> [3, 5, 9]; anything that is not a positive whole number is ignored.
$ids = [];
$idText = isset($_GET["ids"]) ? trim($_GET["ids"]) : "";

if ($idText !== "") {
    foreach (explode(",", $idText) as $part) {
        $id = (int) $part;
        if ($id > 0 && !in_array($id, $ids)) {
            $ids[] = $id;
        }
    }
}

if (count($ids) > 200) {
    send_json(400, ["ok" => false, "message" => "Too many complaints were requested at once."]);
}

// Students only ever receive their own complaints; staff can see every complaint.
$ownerId = is_staff() ? null : $_SESSION["userId"];

send_json(200, [
    "ok" => true,
    "complaints" => get_complaint_states($pdo, $ids, $ownerId),
    "summary" => get_summary($pdo, $ownerId),
    "latestId" => get_latest_id($pdo, $ownerId),
    "checkedAt" => date("h:i:s A"),
]);
?>
