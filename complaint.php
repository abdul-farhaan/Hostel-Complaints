<?php
require "auth.php";
require "db.php";
require "functions.php";

require_login();

$complaintId = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

$statement = $pdo->prepare(
    "SELECT c.complaint_id, c.user_id, c.title, c.description, c.location, c.priority, c.status,
            c.created_at, c.updated_at, c.resolved_at, cat.name AS category, u.full_name, u.login_id
     FROM complaints c
     JOIN categories cat ON c.category_id = cat.category_id
     JOIN users u ON c.user_id = u.user_id
     WHERE c.complaint_id = :complaintId"
);
$statement->execute([":complaintId" => $complaintId]);
$complaint = $statement->fetch();

// Students may only open their own complaints. The same message is used for a
// complaint that does not exist, so nothing is revealed about other students.
if ($complaint === false || (!is_staff() && (int) $complaint["user_id"] !== $_SESSION["userId"])) {
    http_response_code(404);
    echo "Complaint not found. <a href=\"index.php\">Back</a>";
    exit;
}

$statement = $pdo->prepare(
    "SELECT h.old_status, h.new_status, h.remark, h.updated_at, u.full_name, u.role
     FROM complaint_updates h
     JOIN users u ON h.updated_by = u.user_id
     WHERE h.complaint_id = :complaintId
     ORDER BY h.update_id"
);
$statement->execute([":complaintId" => $complaintId]);
$history = $statement->fetchAll();

$pageTitle = "Complaint #" . $complaintId;
$activePage = is_staff() ? "manage" : "mine";
require "header.php";
?>
    <main class="page-container narrow">
        <p class="back-link"><a href="<?php echo is_staff() ? "manage.php" : "index.php"; ?>">&larr; Back to the list</a></p>

        <article class="complaint-card tracked-complaint" id="complaint-<?php echo $complaintId; ?>"
                 data-complaint-id="<?php echo $complaintId; ?>"
                 data-status="<?php echo htmlspecialchars($complaint["status"]); ?>">
            <div class="card-top">
                <span class="complaint-number">#<?php echo $complaintId; ?></span>
                <span class="badge badge-<?php echo strtolower(htmlspecialchars($complaint["category"])); ?>">
                    <?php echo htmlspecialchars($complaint["category"]); ?>
                </span>
                <span class="priority priority-<?php echo strtolower(htmlspecialchars($complaint["priority"])); ?>">
                    <?php echo htmlspecialchars($complaint["priority"]); ?> priority
                </span>
                <span class="status-pill <?php echo status_class($complaint["status"]); ?>">
                    <?php echo htmlspecialchars($complaint["status"]); ?>
                </span>
            </div>

            <h1 class="complaint-title"><?php echo htmlspecialchars($complaint["title"]); ?></h1>
            <p class="complaint-location"><?php echo htmlspecialchars($complaint["location"]); ?></p>
            <p class="complaint-body"><?php echo nl2br(htmlspecialchars($complaint["description"])); ?></p>

            <?php print_progress($complaint["status"]); ?>

            <p class="complaint-meta">
                <?php if (is_staff()): ?>
                    Submitted by <?php echo htmlspecialchars($complaint["full_name"]); ?>
                    (<?php echo htmlspecialchars($complaint["login_id"]); ?>) on
                <?php else: ?>
                    Submitted
                <?php endif; ?>
                <?php echo format_time($complaint["created_at"]); ?>
                &middot; Last updated <span class="updated-text"><?php echo format_time($complaint["updated_at"]); ?></span>
            </p>

            <?php if (is_staff()): ?>
                <div class="detail-actions">
                    <h2>Update status</h2>
                    <?php print_staff_actions($complaintId, $complaint["status"]); ?>
                    <p id="update-message" class="status-message" aria-live="polite"></p>
                </div>
            <?php endif; ?>
        </article>

        <section class="panel">
            <h2>History</h2>
            <ol id="timeline" class="timeline">
                <?php foreach ($history as $entry): ?>
                    <li>
                        <?php if ($entry["old_status"] === null): ?>
                            <strong>Submitted</strong>
                        <?php else: ?>
                            <strong><?php echo htmlspecialchars($entry["old_status"]); ?> &rarr; <?php echo htmlspecialchars($entry["new_status"]); ?></strong>
                        <?php endif; ?>
                        <span class="muted">
                            by <?php echo htmlspecialchars($entry["full_name"]); ?>,
                            <?php echo format_time($entry["updated_at"]); ?>
                        </span>
                        <?php if ($entry["remark"] !== null): ?>
                            <p class="timeline-remark"><?php echo htmlspecialchars($entry["remark"]); ?></p>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>

            <div class="refresh-bar">
                <span id="refresh-time">The status is checked automatically every 15 seconds.</span>
                <button type="button" id="refresh-button">Refresh status now</button>
            </div>
            <p id="refresh-message" class="status-message" aria-live="polite"></p>
        </section>
    </main>

<?php require "footer.php"; ?>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="js/common.js"></script>
    <script src="js/refresh.js"></script>
    <?php if (is_staff()): ?>
        <script src="js/update.js"></script>
    <?php endif; ?>
</body>
</html>
