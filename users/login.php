<?php
session_start();
include_once('../includes/config.php');

$redirect = $_POST['redirect'] ?? $_GET['redirect'] ?? '';
if (!is_string($redirect) || strpos($redirect, '/') !== 0 || strpos($redirect, '//') === 0) {
    $redirect = BASE_URL . 'index.php';
}

if (isset($_SESSION['user_id'])) {
    header('Location: ' . (($_SESSION['role'] ?? '') === 'admin' ? BASE_URL . 'admin/manage-products/' : $redirect));
    exit;
}

$error = '';
$login_value = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_value = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($login_value === '' || $password === '') {
        $error = 'Please enter your username or email and your password.';
    } else {
        $column = strpos($login_value, '@') !== false ? 'email' : 'username';
        $login_sql = mysqli_real_escape_string($conn, $login_value);
        $user = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT user_id, username, password, role, vip_expires_at
             FROM users
             WHERE $column = '$login_sql' AND role IN ('customer', 'vip') AND status = 'active'
             LIMIT 1"));

        if ($user && password_verify($password, $user['password'])) {
            if ($user['role'] === 'vip' && $user['vip_expires_at'] && strtotime($user['vip_expires_at']) <= time()) {
                $user_id = (int)$user['user_id'];
                mysqli_query($conn, "UPDATE users SET role = 'customer', vip_expires_at = NULL WHERE user_id = $user_id");
                $user['role'] = 'customer';
                $user['vip_expires_at'] = null;
            }

            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            if ($user['role'] === 'vip') {
                $_SESSION['vip_expires_at'] = $user['vip_expires_at'];
            }
            header('Location: ' . $redirect);
            exit;
        }

        $error = 'Incorrect username/email or password, or this is not an active customer account.';
    }
}

$page_title = 'Sign in | Soigné';
$extra_css = 'login.css';
include_once('../includes/header.php');
?>

<main class="login-page">
  <div class="login-card">
    <h1>Sign in</h1>
    <p class="login-sub">Welcome back. Sign in to manage your cart and orders.</p>

    <?php if ($error !== '') { ?>
      <p class="login-error" role="alert"><?php echo htmlspecialchars($error); ?></p>
    <?php } ?>

    <form method="post" action="login.php" novalidate>
      <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">

      <label for="login">Username or email</label>
      <input type="text" id="login" name="login" value="<?php echo htmlspecialchars($login_value); ?>" autocomplete="username" autofocus>

      <label for="password">Password</label>
      <input type="password" id="password" name="password">

      <button class="btn" type="submit">Sign in</button>
    </form>

    <p class="login-alt">
      No account yet?
      <a href="register.php?redirect=<?php echo urlencode($redirect); ?>">Create one</a>
    </p>
  </div>
</main>

<?php include('../includes/footer.php'); ?>
