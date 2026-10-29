<?php
require "auth.php";
require "db.php";
require "functions.php";

require_student();

$userId = $_SESSION["userId"];
$flash = take_flash();

// Filter values arrive with GET, so a filtered view has its own URL.
$categoryId = isset($_GET["category"]) ? (int) $_GET["category"] : 0;
$status = isset($_GET["status"]) ? $_GET["status"] : "";

if (!in_array($status, all_statuses(), true)) {
    $status = "";
}

// Build the WHERE clause from the chosen filters. Every user-supplied value
// goes through a placeholder; only fixed SQL text is joined into $sql.
$conditions = ["c.user_id = :userId"];
$params = [":userId" => $userId];

if ($categoryId > 0) {
    $conditions[] = "c.category_id = :categoryId";
    $params[":categoryId"] = $categoryId;
}

if ($status !== "") {
    $conditions[] = "c.status = :status";
    $params[":status"] = $status;
}

$sql = "SELECT c.complaint_id, c.title, c.description, c.location, c.priority, c.status,
               c.created_at, c.updated_at, cat.name AS category,
               (SELECT h.remark FROM complaint_updates h
                WHERE h.complaint_id = c.complaint_id AND h.remark IS NOT NULL
                ORDER BY h.update_id DESC LIMIT 1) AS last_remark
        FROM complaints c
        JOIN categories cat ON c.category_id = cat.category_id
        WHERE " . implode(" AND ", $conditions) . "
        ORDER BY c.created_at DESC";

$statement = $pdo->prepare($sql);
$statement->execute($params);
$complaints = $statement->fetchAll();

$categories = $pdo->query("SELECT category_id, name FROM categories ORDER BY category_id")->fetchAll();
$summary = get_summary($pdo, $userId);
$isFiltered = $categoryId > 0 || $status !== "";

$pageTitle = "My Complaints";
$activePage = "mine";
require "header.php";
?>
    <main class="page-container">
        <div class="page-heading">
            <h1>My Complaints</h1>
            <a href="new_complaint.php" class="button-link button-primary">+ New Complaint</a>
            <p class="page-intro">Everything you have reported, with its current status. Statuses update on their own.</p>
        </div>

        <?php if ($flash !== null): ?>
            <p class="flash flash-<?php echo htmlspecialchars($flash["type"]); ?>">
                <?php echo htmlspecialchars($flash["text"]); ?>
            </p>
        <?php endif; ?>

        <section class="summary" aria-label="Your summary">
            <a class="summary-tile" href="index.php">
                <span class="summary-number" id="summary-total"><?php echo $summary["total"]; ?></span>
                <span class="summary-label">All complaints</span>
            </a>
            <a class="summary-tile tile-submitted" href="index.php?status=Submitted">
                <span class="summary-number" id="summary-submitted"><?php echo $summary["submitted"]; ?></span>
                <span class="summary-label">Submitted</span>
            </a>
            <a class="summary-tile tile-progress" href="index.php?status=In+Progress">
                <span class="summary-number" id="summary-progress"><?php echo $summary["inProgress"]; ?></span>
                <span class="summary-label">In progress</span>
            </a>
            <a class="summary-tile tile-resolved" href="index.php?status=Resolved">
                <span class="summary-number" id="summary-resolved"><?php echo $summary["resolved"]; ?></span>
                <span class="summary-label">Resolved</span>
            </a>
        </section>

        <form id="filter-form" class="filter-bar" action="index.php" method="get">
            <div class="filter-field">
                <label for="category">Category</label>
                <select id="category" name="category">
                    <option value="0">All categories</option>
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
                    <option value="">All statuses</option>
                    <?php foreach (all_statuses() as $option): ?>
                        <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $option === $status ? "selected" : ""; ?>>
                            <?php echo htmlspecialchars($option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-field filter-buttons">
                <button type="submit" class="button-primary">Apply</button>
                <a href="index.php" class="button-link">Clear</a>
            </div>
        </form>

        <div class="refresh-bar">
            <span id="refresh-time">Statuses are checked automatically every 15 seconds.</span>
            <button type="button" id="refresh-button">Refresh status now</button>
        </div>
        <p id="refresh-message" class="status-message" aria-live="polite"></p>
        <p id="new-complaints" class="new-complaints" data-latest-id="<?php echo get_latest_id($pdo, $userId); ?>">
            A new complaint has been submitted from another window. <a href="index.php">Reload the list</a>
        </p>

        <p class="result-count">
            <?php
            echo count($complaints) === 1 ? "1 complaint" : count($complaints) . " complaints";
            echo $isFiltered ? " match the selected filters." : " submitted by you.";
            ?>
        </p>

        <?php if (count($complaints) === 0): ?>
            <p class="empty-message">
                <?php if ($isFiltered): ?>
                    No complaints match these filters. <a href="index.php">Show all complaints</a>.
                <?php else: ?>
                    You have not submitted any complaints yet. <a href="new_complaint.php">Submit one</a>.
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <div class="complaint-list">
            <?php foreach ($complaints as $complaint): ?>
                <article class="complaint-card tracked-complaint" id="complaint-<?php echo $complaint["complaint_id"]; ?>"
                         data-complaint-id="<?php echo $complaint["complaint_id"]; ?>"
                         data-status="<?php echo htmlspecialchars($complaint["status"]); ?>">
                    <div class="card-top">
                        <span class="complaint-number">#<?php echo $complaint["complaint_id"]; ?></span>
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

                    <h2 class="complaint-title"><?php echo htmlspecialchars($complaint["title"]); ?></h2>
                    <p class="complaint-location"><?php echo htmlspecialchars($complaint["location"]); ?></p>
                    <p class="complaint-body"><?php echo nl2br(htmlspecialchars($complaint["description"])); ?></p>

                    <?php print_progress($complaint["status"]); ?>

                    <p class="staff-remark" <?php echo $complaint["last_remark"] === null ? "hidden" : ""; ?>>
                        <strong>Latest update from staff:</strong>
                        <span class="remark-text"><?php echo htmlspecialchars((string) $complaint["last_remark"]); ?></span>
                    </p>

                    <p class="complaint-meta">
                        Submitted <?php echo format_time($complaint["created_at"]); ?>
                        &middot; Last updated <span class="updated-text"><?php echo format_time($complaint["updated_at"]); ?></span>
                        &middot; <a href="complaint.php?id=<?php echo $complaint["complaint_id"]; ?>">View history</a>
                    </p>
                </article>
            <?php endforeach; ?>
        </div>
    </main>

<?php require "footer.php"; ?>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="js/common.js"></script>
    <script src="js/refresh.js"></script>
    <script src="js/filters.js"></script>
</body>
</html>
