<?php
require "auth.php";
require "db.php";
require "functions.php";

require_student();

$flash = take_flash();

// If submit_complaint.php rejected the form, it saved the typed values so they can be shown again.
$old = isset($_SESSION["oldComplaint"]) ? $_SESSION["oldComplaint"] : [
    "categoryId" => 0,
    "title" => "",
    "location" => $_SESSION["room"],
    "priority" => "Medium",
    "description" => "",
];
unset($_SESSION["oldComplaint"]);

$categories = $pdo->query("SELECT category_id, name FROM categories ORDER BY category_id")->fetchAll();
$priorities = ["Low", "Medium", "High"];

$pageTitle = "New Complaint";
$activePage = "new";
require "header.php";
?>
    <main class="page-container narrow">
        <div class="page-heading">
            <h1>Submit a Complaint</h1>
            <p class="page-intro">One problem per complaint, please.</p>
        </div>

        <?php if ($flash !== null): ?>
            <p class="flash flash-<?php echo htmlspecialchars($flash["type"]); ?>">
                <?php echo htmlspecialchars($flash["text"]); ?>
            </p>
        <?php endif; ?>

        <section class="panel">
            <p class="panel-help">
                Describe one problem per complaint. The hostel office will move it from
                <strong>Submitted</strong> to <strong>In Progress</strong> to <strong>Resolved</strong>,
                and you can follow it on the My Complaints page.
            </p>

            <form id="complaint-form" action="submit_complaint.php" method="post">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION["token"]); ?>">

                <label for="category-id">Category</label>
                <select id="category-id" name="categoryId">
                    <option value="">Choose a category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category["category_id"]; ?>"
                            <?php echo (int) $category["category_id"] === (int) $old["categoryId"] ? "selected" : ""; ?>>
                            <?php echo htmlspecialchars($category["name"]); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="title">Short title <span id="title-count" class="char-count"></span></label>
                <input type="text" id="title" name="title" maxlength="100"
                       placeholder="For example: Tube light not working"
                       value="<?php echo htmlspecialchars($old["title"]); ?>">

                <label for="location">Location</label>
                <input type="text" id="location" name="location" maxlength="80"
                       placeholder="Hostel and room, or the floor / area"
                       value="<?php echo htmlspecialchars($old["location"]); ?>">

                <fieldset class="priority-options">
                    <legend>Priority</legend>
                    <?php foreach ($priorities as $priority): ?>
                        <label class="radio-label">
                            <input type="radio" name="priority" value="<?php echo $priority; ?>"
                                <?php echo $old["priority"] === $priority ? "checked" : ""; ?>>
                            <?php echo $priority; ?>
                        </label>
                    <?php endforeach; ?>
                    <p class="field-help">Choose High only for safety problems or when a basic service (water, power) is down.</p>
                </fieldset>

                <label for="description">Details <span id="description-count" class="char-count"></span></label>
                <textarea id="description" name="description" rows="5" maxlength="1000"
                          placeholder="What is wrong, since when, and anything the staff should know"><?php echo htmlspecialchars($old["description"]); ?></textarea>

                <button type="submit" class="button-primary">Submit Complaint</button>
                <a href="index.php" class="button-link">Cancel</a>
            </form>

            <p id="complaint-form-message" class="form-message" aria-live="polite"></p>
        </section>
    </main>

<?php require "footer.php"; ?>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="js/common.js"></script>
    <script src="js/new_complaint.js"></script>
</body>
</html>
