<?php
// Helpers shared by the pages and the JSON endpoints.

// The three statuses in the order a complaint moves through them.
function all_statuses() {
    return ["Submitted", "In Progress", "Resolved"];
}

// The status that comes after $status, or null if the complaint is already resolved.
function next_status($status) {
    $statusOrder = all_statuses();
    $position = array_search($status, $statusOrder);

    if ($position === false || $position === count($statusOrder) - 1) {
        return null;
    }

    return $statusOrder[$position + 1];
}

// CSS class used for a status pill, e.g. "In Progress" -> "status-progress".
function status_class($status) {
    if ($status === "In Progress") {
        return "status-progress";
    } elseif ($status === "Resolved") {
        return "status-resolved";
    }

    return "status-submitted";
}

function format_time($timestamp) {
    return date("d M Y, h:i A", strtotime($timestamp));
}

// The three-step progress bar shown for every complaint. JavaScript updates the
// same classes when the status changes.
function print_progress($status) {
    $statusOrder = all_statuses();
    $current = array_search($status, $statusOrder);

    echo '<ol class="progress-steps">';
    foreach ($statusOrder as $position => $step) {
        $class = "step";
        if ($position < $current) {
            $class .= " step-done";
        } elseif ($position === $current) {
            $class .= $status === "Resolved" ? " step-done" : " step-current";
        }
        echo '<li class="' . $class . '">' . htmlspecialchars($step) . '</li>';
    }
    echo '</ol>';
}

// Counts for the summary tiles: one student's complaints, or every complaint
// when $userId is null (staff).
function get_summary($pdo, $userId) {
    $sql = "SELECT COUNT(*) AS total,
                   SUM(status = 'Submitted') AS submitted,
                   SUM(status = 'In Progress') AS inProgress,
                   SUM(status = 'Resolved') AS resolved
            FROM complaints";
    $params = [];

    if ($userId !== null) {
        $sql .= " WHERE user_id = :userId";
        $params[":userId"] = $userId;
    }

    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $row = $statement->fetch();

    return [
        "total" => (int) $row["total"],
        "submitted" => (int) $row["submitted"],
        "inProgress" => (int) $row["inProgress"],
        "resolved" => (int) $row["resolved"],
    ];
}

// The highest complaint number the user can see. If it grows while a page is
// open, a new complaint has been submitted since the page was loaded.
function get_latest_id($pdo, $userId) {
    $sql = "SELECT COALESCE(MAX(complaint_id), 0) FROM complaints";
    $params = [];

    if ($userId !== null) {
        $sql .= " WHERE user_id = :userId";
        $params[":userId"] = $userId;
    }

    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return (int) $statement->fetchColumn();
}

// How long ago a complaint was submitted, e.g. "today", "1 day ago", "5 days ago".
function days_ago($timestamp) {
    $today = new DateTime(date("Y-m-d"));
    $day = new DateTime(date("Y-m-d", strtotime($timestamp)));
    $days = (int) $day->diff($today)->format("%a");

    if ($days === 0) {
        return "today";
    } elseif ($days === 1) {
        return "1 day ago";
    }

    return $days . " days ago";
}

// The staff controls for one complaint: an optional remark and a button that
// moves the complaint to its next status. js/common.js (showStaffActions)
// changes the same elements after an update.
function print_staff_actions($complaintId, $status) {
    $next = next_status($status);
    $labels = ["In Progress" => "Start work", "Resolved" => "Mark resolved"];
    $hide = ' style="display: none"';
    ?>
    <div class="staff-actions">
        <input type="text" class="remark-input" maxlength="255"
               aria-label="Remark for complaint #<?php echo $complaintId; ?>"
               placeholder="<?php echo $next === "Resolved" ? "What was done? (required)" : "Remark for the student (optional)"; ?>"
               <?php echo $next === null ? $hide : ""; ?>>
        <button type="button" class="move-button" data-complaint-id="<?php echo $complaintId; ?>"
                data-to="<?php echo htmlspecialchars((string) $next); ?>"
                <?php echo $next === null ? $hide : ""; ?>>
            <?php echo $next === null ? "" : $labels[$next]; ?>
        </button>
        <span class="no-action" <?php echo $next === null ? "" : $hide; ?>>Closed</span>
    </div>
    <?php
}

// Current status, last update time and latest staff remark of the given
// complaints. Students only receive their own complaints ($userId), staff
// receive any complaint ($userId = null).
function get_complaint_states($pdo, $ids, $userId) {
    if (count($ids) === 0) {
        return [];
    }

    // One placeholder per id: :id0, :id1, ...
    $placeholders = [];
    $params = [];
    foreach ($ids as $index => $id) {
        $placeholders[] = ":id" . $index;
        $params[":id" . $index] = $id;
    }

    $sql = "SELECT c.complaint_id, c.status, c.updated_at,
                   (SELECT h.remark FROM complaint_updates h
                    WHERE h.complaint_id = c.complaint_id AND h.remark IS NOT NULL
                    ORDER BY h.update_id DESC LIMIT 1) AS last_remark
            FROM complaints c
            WHERE c.complaint_id IN (" . implode(", ", $placeholders) . ")";

    if ($userId !== null) {
        $sql .= " AND c.user_id = :userId";
        $params[":userId"] = $userId;
    }

    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    $states = [];
    foreach ($statement->fetchAll() as $row) {
        $states[] = complaint_state($row);
    }
    return $states;
}

// The JSON form of one complaint's state, read by applyComplaintState() in js/common.js.
function complaint_state($row) {
    return [
        "complaintId" => (int) $row["complaint_id"],
        "status" => $row["status"],
        "statusClass" => status_class($row["status"]),
        "nextStatus" => next_status($row["status"]),
        "updatedText" => format_time($row["updated_at"]),
        "lastRemark" => $row["last_remark"],
    ];
}

// Used by the JSON endpoints in the api folder.
function send_json($statusCode, $data) {
    http_response_code($statusCode);
    header("Content-Type: application/json");
    echo json_encode($data);
    exit;
}

// Checks a POST request sent by fetch and returns its JSON body as a PHP array.
// These are the same checks as the pages make, but every failure is
// answered with JSON instead of a redirect.
function read_api_request() {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        send_json(405, ["ok" => false, "message" => "Use a POST request."]);
    }

    if (!is_logged_in()) {
        send_json(401, ["ok" => false, "message" => "Your session has ended. Please log in again."]);
    }

    $data = json_decode(file_get_contents("php://input"), true);

    if (!is_array($data)) {
        send_json(400, ["ok" => false, "message" => "The request body must be JSON."]);
    }

    if (!token_is_valid(isset($data["token"]) ? $data["token"] : "")) {
        send_json(403, ["ok" => false, "message" => "Invalid request token. Reload the page and try again."]);
    }

    return $data;
}
?>
