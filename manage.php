<?php
require "auth.php";
require "db.php";
require "functions.php";

require_staff();

// Filter values arrive with GET, so a filtered view has its own URL.
$categoryId = isset($_GET["category"]) ? (int) $_GET["category"] : 0;
$status = isset($_GET["status"]) ? $_GET["status"] : "";
$priority = isset($_GET["priority"]) ? $_GET["priority"] : "";
$priorities = ["High", "Medium", "Low"];

if (!in_array($status, all_statuses(), true)) {
    $status = "";
}

if (!in_array($priority, $priorities, true)) {
    $priority = "";
}

// Build the WHERE clause from the chosen filters. Every user-supplied value
// goes through a placeholder; only fixed SQL text is joined into $sql.
// "1 = 1" is always true; it lets every chosen filter be added with AND.
$conditions = ["1 = 1"];
$params = [];

if ($categoryId > 0) {
    $conditions[] = "c.category_id = :categoryId";
    $params[":categoryId"] = $categoryId;
}

if ($status !== "") {
    $conditions[] = "c.status = :status";
    $params[":status"] = $status;
}

if ($priority !== "") {
    $conditions[] = "c.priority = :priority";
    $params[":priority"] = $priority;
}

// Work that still needs doing comes first: Submitted, then In Progress, then
// Resolved; inside each group, High priority first and then the oldest first.
$sql = "SELECT c.complaint_id, c.title, c.location, c.priority, c.status, c.created_at, c.updated_at,
               cat.name AS category, u.full_name, u.login_id,
               (SELECT h.remark FROM complaint_updates h
                WHERE h.complaint_id = c.complaint_id AND h.remark IS NOT NULL
                ORDER BY h.update_id DESC LIMIT 1) AS last_remark
        FROM complaints c
        JOIN categories cat ON c.category_id = cat.category_id
        JOIN users u ON c.user_id = u.user_id
        WHERE " . implode(" AND ", $conditions) . "
        ORDER BY CASE c.status WHEN 'Submitted' THEN 1 WHEN 'In Progress' THEN 2 ELSE 3 END,
                 CASE c.priority WHEN 'High' THEN 1 WHEN 'Medium' THEN 2 ELSE 3 END,
                 c.created_at";

$statement = $pdo->prepare($sql);
$statement->execute($params);
$complaints = $statement->fetchAll();

$categories = $pdo->query("SELECT category_id, name FROM categories ORDER BY category_id")->fetchAll();
$summary = get_summary($pdo, null);
$isFiltered = $categoryId > 0 || $status !== "" || $priority !== "";

$pageTitle = "All Complaints";
$activePage = "manage";
require "header.php";
?>
    <main class="page-container wide">
        <div class="page-heading">
            <h1>All Complaints</h1>
            <p class="page-intro">Move each complaint from Submitted to In Progress to Resolved. Urgent and older work is listed first.</p>
        </div>

        <section class="summary" aria-label="Summary of all complaints">
            <a class="summary-tile" href="manage.php">
                <span class="summary-number" id="summary-total"><?php echo $summary["total"]; ?></span>
                <span class="summary-label">All complaints</span>
            </a>
            <a class="summary-tile tile-submitted" href="manage.php?status=Submitted">
                <span class="summary-number" id="summary-submitted"><?php echo $summary["submitted"]; ?></span>
                <span class="summary-label">Waiting (Submitted)</span>
            </a>
            <a class="summary-tile tile-progress" href="manage.php?status=In+Progress">
                <span class="summary-number" id="summary-progress"><?php echo $summary["inProgress"]; ?></span>
                <span class="summary-label">In progress</span>
            </a>
            <a class="summary-tile tile-resolved" href="manage.php?status=Resolved">
                <span class="summary-number" id="summary-resolved"><?php echo $summary["resolved"]; ?></span>
                <span class="summary-label">Resolved</span>
            </a>
        </section>

        <form id="filter-form" class="filter-bar" action="manage.php" method="get">
            <div class="filter-field">
                <label for="category">Category</label>
                <select id="category" name="category">
                    <option value="0">All</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category["category_id"]; ?>"
                            <?php echo (int) $category["category_id"] === $categoryId ? "selected" : ""; ?>>
                            <?php echo htmlspecialchars($category["name"]); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All</option>
                    <?php foreach (all_statuses() as $option): ?>
                        <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $option === $status ? "selected" : ""; ?>>
                            <?php echo htmlspecialchars($option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-field">
                <label for="priority">Priority</label>
                <select id="priority" name="priority">
                    <option value="">All</option>
                    <?php foreach ($priorities as $option): ?>
                        <option value="<?php echo $option; ?>" <?php echo $option === $priority ? "selected" : ""; ?>>
                            <?php echo $option; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-field filter-buttons">
                <button type="submit" class="button-primary">Apply</button>
                <a href="manage.php" class="button-link">Clear</a>
            </div>
        </form>

        <div class="refresh-bar">
            <span id="refresh-time">Statuses are checked automatically every 15 seconds.</span>
            <button type="button" id="refresh-button">Refresh status now</button>
        </div>
        <p id="refresh-message" class="status-message" aria-live="polite"></p>
        <p id="new-complaints" class="new-complaints" data-latest-id="<?php echo get_latest_id($pdo, null); ?>">
            New complaints have been submitted since this page was loaded. <a href="manage.php">Reload the list</a>
        </p>

        <section class="panel">
            <p class="result-count">
                <?php
                echo count($complaints) === 1 ? "1 complaint" : count($complaints) . " complaints";
                echo $isFiltered ? " match the selected filters." : " in total.";
                ?>
                Move a complaint to its next status with the button in the last column.
                A remark is optional when starting work and required when resolving.
            </p>

            <p id="update-message" class="status-message" aria-live="polite"></p>

            <?php if (count($complaints) === 0): ?>
                <p class="empty-message">No complaints match. <a href="manage.php">Clear the filters</a>.</p>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="complaint-table">
                        <thead>
                            <tr>
                                <th>Complaint</th>
                                <th>Student</th>
                                <th>Priority</th>
                                <th>Submitted</th>
                                <th>Status</th>
                                <th>Update</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($complaints as $complaint): ?>
                                <tr id="complaint-<?php echo $complaint["complaint_id"]; ?>" class="tracked-complaint"
                                    data-complaint-id="<?php echo $complaint["complaint_id"]; ?>"
                                    data-status="<?php echo htmlspecialchars($complaint["status"]); ?>">
                                    <td>
                                        <span class="complaint-number">#<?php echo $complaint["complaint_id"]; ?></span>
                                        <span class="badge badge-<?php echo strtolower(htmlspecialchars($complaint["category"])); ?>">
                                            <?php echo htmlspecialchars($complaint["category"]); ?>
                                        </span>
                                        <a class="table-title" href="complaint.php?id=<?php echo $complaint["complaint_id"]; ?>">
                                            <?php echo htmlspecialchars($complaint["title"]); ?>
                                        </a>
                                        <span class="muted"><?php echo htmlspecialchars($complaint["location"]); ?></span>
                                        <span class="staff-remark table-remark" <?php echo $complaint["last_remark"] === null ? "hidden" : ""; ?>>
                                            Last remark: <span class="remark-text"><?php echo htmlspecialchars((string) $complaint["last_remark"]); ?></span>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($complaint["full_name"]); ?><br>
                                        <span class="muted"><?php echo htmlspecialchars($complaint["login_id"]); ?></span>
                                    </td>
                                    <td>
                                        <span class="priority priority-<?php echo strtolower($complaint["priority"]); ?>">
                                            <?php echo htmlspecialchars($complaint["priority"]); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo date("d M Y", strtotime($complaint["created_at"])); ?><br>
                                        <span class="muted"><?php echo days_ago($complaint["created_at"]); ?></span>
                                    </td>
                                    <td>
                                        <span class="status-pill <?php echo status_class($complaint["status"]); ?>">
                                            <?php echo htmlspecialchars($complaint["status"]); ?>
                                        </span>
                                        <span class="muted updated-line">
                                            Updated <span class="updated-text"><?php echo format_time($complaint["updated_at"]); ?></span>
                                        </span>
                                    </td>
                                    <td>
                                        <?php print_staff_actions($complaint["complaint_id"], $complaint["status"]); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>

<?php require "footer.php"; ?>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="js/common.js"></script>
    <script src="js/refresh.js"></script>
    <script src="js/update.js"></script>
    <script src="js/filters.js"></script>
</body>
</html>
