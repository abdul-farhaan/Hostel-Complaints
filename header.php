<?php
// Shared top of every logged-in page: the side menu and the start of the main area.
// The including page sets $pageTitle and $activePage. footer.php closes the main area.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($pageTitle); ?> | Hostel Helpdesk</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="app">
    <aside class="sidebar">
        <a class="brand" href="index.php">
            <span class="brand-mark">HH</span>
            <span class="brand-name">Hostel Helpdesk</span>
            <span class="brand-tagline">Maintenance &amp; complaints</span>
        </a>

        <nav class="side-nav">
            <?php if (is_staff()): ?>
                <a href="manage.php" class="<?php echo $activePage === "manage" ? "active" : ""; ?>">All Complaints</a>
            <?php else: ?>
                <a href="index.php" class="<?php echo $activePage === "mine" ? "active" : ""; ?>">My Complaints</a>
                <a href="new_complaint.php" class="<?php echo $activePage === "new" ? "active" : ""; ?>">New Complaint</a>
            <?php endif; ?>
        </nav>

        <div class="side-user">
            <span class="side-user-name"><?php echo htmlspecialchars($_SESSION["fullName"]); ?></span>
            <span class="side-user-details">
                <?php echo htmlspecialchars($_SESSION["loginId"]); ?> &middot; <?php echo htmlspecialchars($_SESSION["role"]); ?>
            </span>
            <a href="logout.php" class="logout-link">Log out</a>
        </div>
    </aside>

    <div class="main-area">
        <!-- Read by the JavaScript files and sent with every fetch request that changes data -->
        <input type="hidden" id="csrf-token" value="<?php echo htmlspecialchars($_SESSION["token"]); ?>">
