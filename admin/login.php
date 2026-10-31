<?php
session_start();
include_once("../includes/config.php");
require_once("../includes/admin_redirect.php");

$redirect = safe_admin_return_path($_POST['redirect'] ?? $_GET['redirect'] ?? '');
$login_notice = $_SESSION['admin_login_notice'] ?? '';
unset($_SESSION['admin_login_notice']);

if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin') {
    header('Location: ' . BASE_URL . 'admin/manage-products/');
    exit;
}

$error = '';
$login_value = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_login = $_POST['login'] ?? '';
    $posted_password = $_POST['password'] ?? '';
    $login_value = is_string($posted_login) ? trim($posted_login) : '';
    $password = is_string($posted_password) ? $posted_password : '';

    if ($login_value === '' && $password === '') {
        $error = 'Enter your username or email and password.';
    } elseif ($login_value === '') {
        $error = 'Enter your username or email.';
    } elseif ($password === '') {
        $error = 'Enter your password.';
    } else {
        $admin_lookup_query = "SELECT user_id, username, password, role
                               FROM users
                               WHERE (username = ? OR email = ?) AND role = 'admin' AND status = 'active'
                               LIMIT 1";
        $admin_lookup_statement = mysqli_prepare($conn, $admin_lookup_query);
        mysqli_stmt_bind_param($admin_lookup_statement, 'ss', $login_value, $login_value);
        mysqli_stmt_execute($admin_lookup_statement);
        $admin = mysqli_fetch_assoc(mysqli_stmt_get_result($admin_lookup_statement));

        if ($admin && password_verify($password, $admin['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$admin['user_id'];
            $_SESSION['username'] = $admin['username'];
            $_SESSION['role'] = $admin['role'];
            header('Location: ' . $redirect);
            exit;
        }

        $error = 'The username/email or password is incorrect, or this account is not an administrator.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin sign in | Soigné</title>
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>admin/styles/admin.css?v=<?php echo filemtime(APP_ROOT . '/admin/styles/admin.css'); ?>">
</head>
<body class="admin-login-body">
  <main class="admin-login-layout">
    <section class="admin-login-brand" aria-label="Soigné administration">
      <a class="admin-brand" href="<?php echo BASE_URL; ?>admin/login.php">
        <span class="admin-brand-mark">S</span>
        <span>SOIGNÉ <small>ADMINISTRATION</small></span>
      </a>
      <div class="admin-login-message">
        <p class="admin-eyebrow">STORE MANAGEMENT</p>
        <h1>Welcome back.<br>Let's get to work.</h1>
        <p>Manage your products, orders, and customers from one place.</p>
      </div>
      <p class="admin-login-footnote">Authorized staff only</p>
    </section>

    <section class="admin-login-panel">
      <div class="admin-login-card">
        <p class="admin-eyebrow">ADMIN PORTAL</p>
        <h2>Sign in to your account</h2>
        <p class="admin-login-help">Use your administrator username or email.</p>

        <?php if (is_string($login_notice) && $login_notice !== '') { ?>
          <p class="admin-alert" role="status"><?php echo htmlspecialchars($login_notice, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php } ?>
        <?php if ($error !== '') { ?>
          <p class="admin-alert" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php } ?>

        <form method="post" action="<?php echo BASE_URL; ?>admin/login.php" novalidate>
          <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8'); ?>">

          <label for="login">Username or email</label>
          <input type="text" id="login" name="login" value="<?php echo htmlspecialchars($login_value); ?>" autocomplete="username" autofocus>

          <label for="password">Password</label>
          <input type="password" id="password" name="password" autocomplete="current-password">

          <button class="admin-button admin-login-button" type="submit">Sign in to dashboard</button>
        </form>

        <a class="admin-back-link" href="<?php echo BASE_URL; ?>index.php">&larr; Back to storefront</a>
      </div>
    </section>
  </main>
</body>
</html>
