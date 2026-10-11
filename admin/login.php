<?php
session_start();
include_once __DIR__ . '/../includes/config.php';

if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin') {
    header('Location: ' . BASE_URL . 'admin/index.php');
    exit;
}

if (!isset($_SESSION['admin_login_csrf'])) {
    $_SESSION['admin_login_csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$login_value = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_login = $_POST['login'] ?? '';
    $posted_password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    $login_value = is_string($posted_login) ? trim($posted_login) : '';
    $password = is_string($posted_password) ? $posted_password : '';

    if (!is_string($csrf_token) || !hash_equals($_SESSION['admin_login_csrf'], $csrf_token)) {
        $error = 'Your sign-in session expired. Please refresh the page and try again.';
    } elseif ($login_value === '' || $password === '') {
        $error = 'Enter your username or email and password.';
    } else {
        $query = "SELECT user_id, username, password, role
                  FROM users
                  WHERE (username = ? OR email = ?) AND role = 'admin' AND status = 'active'
                  LIMIT 1";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'ss', $login_value, $login_value);
        mysqli_stmt_execute($stmt);
        $admin = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if ($admin && password_verify($password, $admin['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$admin['user_id'];
            $_SESSION['username'] = $admin['username'];
            $_SESSION['role'] = $admin['role'];
            unset($_SESSION['admin_login_csrf']);
            header('Location: ' . BASE_URL . 'admin/index.php');
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
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>admin/styles/admin.css">
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

        <?php if ($error !== '') { ?>
          <p class="admin-alert" role="alert"><?php echo htmlspecialchars($error); ?></p>
        <?php } ?>

        <form method="post" action="<?php echo BASE_URL; ?>admin/login.php">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_login_csrf']); ?>">

          <label for="login">Username or email</label>
          <input type="text" id="login" name="login" value="<?php echo htmlspecialchars($login_value); ?>" autocomplete="username" required autofocus>

          <label for="password">Password</label>
          <input type="password" id="password" name="password" autocomplete="current-password" required>

          <button class="admin-button admin-login-button" type="submit">Sign in to dashboard</button>
        </form>

        <a class="admin-back-link" href="<?php echo BASE_URL; ?>index.php">&larr; Back to storefront</a>
      </div>
    </section>
  </main>
</body>
</html>
