<?php
require "auth.php";

if (is_logged_in()) {
    header("Location: index.php");
    exit;
}

$flash = take_flash();
$lastLoginId = isset($_SESSION["lastLoginId"]) ? $_SESSION["lastLoginId"] : "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log In | Hostel Helpdesk</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="login-page">
    <div class="login-card">
        <section class="login-intro">
            <span class="brand-mark">HH</span>
            <h1>Hostel Helpdesk</h1>
            <p>Maintenance and complaint tracker for hostel residents and staff.</p>
            <ol class="intro-steps">
                <li><strong>Report</strong> a problem in your room or hostel</li>
                <li><strong>Track</strong> it from Submitted to In Progress</li>
                <li><strong>See it resolved</strong>, with the staff's remarks</li>
            </ol>
        </section>

        <section class="login-form-area">
            <h2>Log in</h2>
            <p class="subtitle">Use your admission number or staff ID.</p>

            <?php if ($flash !== null): ?>
                <p class="flash flash-<?php echo htmlspecialchars($flash["type"]); ?>">
                    <?php echo htmlspecialchars($flash["text"]); ?>
                </p>
            <?php endif; ?>

            <form id="login-form" action="process_login.php" method="post">
                <label for="login-id">Admission Number / Staff ID</label>
                <input type="text" id="login-id" name="loginId" maxlength="20"
                       value="<?php echo htmlspecialchars($lastLoginId); ?>">

                <label for="password">Password</label>
                <input type="password" id="password" name="password">

                <button type="submit" class="button-primary">Log In</button>
            </form>

            <p id="login-message" class="form-message is-error" aria-live="polite"></p>

            <div class="demo-accounts">
                <h3>Demo accounts</h3>
                <p>Password for every account: <code>hostel123</code></p>
                <ul>
                    <li><code>24JE9101</code>, <code>24JE9102</code>, <code>24JE9103</code>: demo students</li>
                    <li><code>24JE0134</code>, <code>24JE0312</code>, <code>24JE0369</code>: team members (students)</li>
                    <li><code>warden</code>, <code>maintenance</code>: hostel staff (update complaint status)</li>
                </ul>
            </div>
        </section>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="js/login.js"></script>
</body>
</html>
