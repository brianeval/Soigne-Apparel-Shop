<?php
// Shows the message saved in the session by the previous page, then forgets it.
//   $_SESSION['message'] = red message (error)
//   $_SESSION['success'] = green message (success)
// Use it on any page with: include("includes/alert.php");
if (isset($_SESSION['message'])) {
    echo "<div class='alert alert-error' role='alert' style='margin:0 0 14px;padding:13px 15px;border:1px solid #f0c7c3;border-radius:6px;background:#fff4f2;color:#963d35;font-size:14px;'>"
        . htmlspecialchars($_SESSION['message']) . "</div>";
    unset($_SESSION['message']);
}

if (isset($_SESSION['success'])) {
    echo "<div class='alert alert-success' role='status' style='margin:0 0 14px;padding:13px 15px;border:1px solid #c6e3c9;border-radius:6px;background:#eef7ee;color:#1e6b2e;font-size:14px;'>"
        . htmlspecialchars($_SESSION['success']) . "</div>";
    unset($_SESSION['success']);
}
