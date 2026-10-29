<?php
// Moves a complaint to its next status (staff only).
// Request (JSON):  { "complaintId": 4, "fromStatus": "Submitted", "toStatus": "In Progress",
//                    "remark": "Electrician assigned", "token": "..." }
// Response (JSON): { "ok": true, "message": "...", "complaint": { ... }, "summary": { ... },
//                    "historyEntry": { ... } }
require "../auth.php";
require "../db.php";
require "../functions.php";

$data = read_api_request();

if (!is_staff()) {
    send_json(403, ["ok" => false, "message" => "Only hostel staff can change the status of a complaint."]);
}

$complaintId = isset($data["complaintId"]) ? (int) $data["complaintId"] : 0;
$fromStatus = isset($data["fromStatus"]) && is_string($data["fromStatus"]) ? $data["fromStatus"] : "";
$toStatus = isset($data["toStatus"]) && is_string($data["toStatus"]) ? $data["toStatus"] : "";
$remark = isset($data["remark"]) && is_string($data["remark"]) ? trim($data["remark"]) : "";

if ($complaintId <= 0 || !in_array($fromStatus, all_statuses(), true)) {
    send_json(400, ["ok" => false, "message" => "A complaint and its current status are required."]);
}

// Only one step forward is allowed: Submitted -> In Progress -> Resolved.
if ($toStatus === "" || $toStatus !== next_status($fromStatus)) {
    send_json(400, ["ok" => false, "message" => "A complaint can only move from Submitted to In Progress to Resolved."]);
}

if (mb_strlen($remark) > 255) {
    send_json(400, ["ok" => false, "message" => "The remark can be at most 255 characters long."]);
}

if ($toStatus === "Resolved" && $remark === "") {
    send_json(400, ["ok" => false, "message" => "Describe the work done before marking the complaint resolved."]);
}

$pdo->beginTransaction();

// "AND status = :fromStatus" makes the update happen only if nobody has changed
// the complaint since this page showed it. If two staff members click at the
// same time, the second update matches no row.
if ($toStatus === "Resolved") {
    $sql = "UPDATE complaints SET status = :toStatus, updated_at = NOW(), resolved_at = NOW()
            WHERE complaint_id = :complaintId AND status = :fromStatus";
} else {
    $sql = "UPDATE complaints SET status = :toStatus, updated_at = NOW()
            WHERE complaint_id = :complaintId AND status = :fromStatus";
}

$statement = $pdo->prepare($sql);
$statement->execute([":toStatus" => $toStatus, ":complaintId" => $complaintId, ":fromStatus" => $fromStatus]);

if ($statement->rowCount() === 0) {
    $pdo->rollBack();
    $states = get_complaint_states($pdo, [$complaintId], null);

    if (count($states) === 0) {
        send_json(404, ["ok" => false, "message" => "This complaint does not exist."]);
    }

    // Send the current state so the page can show it.
    send_json(409, [
        "ok" => false,
        "message" => "It was already moved to " . $states[0]["status"] . " by someone else. The page now shows its current status.",
        "complaint" => $states[0],
    ]);
}

$statement = $pdo->prepare(
    "INSERT INTO complaint_updates (complaint_id, old_status, new_status, remark, updated_by)
     VALUES (:complaintId, :oldStatus, :newStatus, :remark, :updatedBy)"
);
$statement->execute([
    ":complaintId" => $complaintId,
    ":oldStatus" => $fromStatus,
    ":newStatus" => $toStatus,
    ":remark" => $remark === "" ? null : $remark,
    ":updatedBy" => $_SESSION["userId"],
]);

$pdo->commit();

$states = get_complaint_states($pdo, [$complaintId], null);

send_json(200, [
    "ok" => true,
    "message" => "Complaint #" . $complaintId . " moved to " . $toStatus . ".",
    "complaint" => $states[0],
    "summary" => get_summary($pdo, null),
    "historyEntry" => [
        "oldStatus" => $fromStatus,
        "newStatus" => $toStatus,
        "remark" => $remark === "" ? null : $remark,
        "byName" => $_SESSION["fullName"],
        "timeText" => format_time(date("Y-m-d H:i:s")),
    ],
]);
?>
