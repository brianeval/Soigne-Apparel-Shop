<?php
session_start();
include_once("../includes/config.php");

// Redirect validation
function safe_redirect($redirect_path) {
    return preg_match('#^/(?!/)#', $redirect_path) ? $redirect_path : 'index.php';
}

$redirect = safe_redirect($_POST['redirect'] ?? $_GET['redirect'] ?? '');

$cart_redirects = [
    BASE_URL . 'cart/cart.php',
    BASE_URL . 'cart/cart_add.php?resume=1',
];
$is_cart_redirect = in_array($redirect, $cart_redirects, true);

// Admin redirect
if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin') {
    if (!$is_cart_redirect) {
        header('Location: ' . BASE_URL . 'admin/manage-products/');
        exit;
    }
}
if (isset($_SESSION['user_id']) && in_array($_SESSION['role'] ?? '', ['customer', 'vip'], true)) {
    header('Location: ' . $redirect);
    exit;
}

$error = '';
$login_value = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted_login = $_POST['login'] ?? '';
    $submitted_password = $_POST['password'] ?? '';
    $login_value = is_string($submitted_login) ? trim($submitted_login) : '';
    $password = is_string($submitted_password) ? $submitted_password : '';

    if ($login_value === '' && $password === '') {
        $error = 'Please enter your username or email and your password.';
    } elseif ($login_value === '') {
        $error = 'Please enter your username or email.';
    } elseif ($password === '') {
        $error = 'Please enter your password.';
    } else {
        // Customer account lookup
        $login_column = filter_var($login_value, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $user_lookup_sql = "SELECT user_id, username, password, role, vip_expires_at,
                     (vip_expires_at IS NOT NULL AND vip_expires_at <= CURRENT_TIMESTAMP) AS vip_expired
              FROM users
              WHERE $login_column = ? AND role IN ('customer', 'vip') AND status = 'active'
              LIMIT 1";
        $user_stmt = mysqli_prepare($conn, $user_lookup_sql);
        mysqli_stmt_bind_param($user_stmt, 's', $login_value);
        mysqli_stmt_execute($user_stmt);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($user_stmt));

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);   // Refresh session ID
            if ($user['role'] === 'vip' && (int)$user['vip_expired'] === 1) {
                $expire_stmt = mysqli_prepare($conn,
                    "UPDATE users SET role = 'customer', vip_expires_at = NULL
                     WHERE user_id = ? AND role = 'vip' AND vip_expires_at <= CURRENT_TIMESTAMP"
                );
                mysqli_stmt_bind_param($expire_stmt, 'i', $user['user_id']);
                mysqli_stmt_execute($expire_stmt);
                $user['role'] = 'customer';
                $user['vip_expires_at'] = null;
            }
            $_SESSION['user_id']  = (int)$user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];
            if ($user['role'] === 'vip') {
                $_SESSION['vip_expires_at'] = $user['vip_expires_at'];
            } else {
                unset($_SESSION['vip_expires_at']);
            }
            header('Location: ' . $redirect);
            exit;
        }

        // Login error
        $error = 'Incorrect username/email or password, or this is not an active customer account.';
    }
}

$page_title = 'Sign in | Soigné';
$extra_css  = 'login.css';
include_once('../includes/header.php');
?>

<main class="login-page">
  <div class="login-card">
    <h1>Sign in</h1>
    <p class="login-sub">Welcome back. Sign in to manage your cart and orders.</p>

    <?php if (($_SESSION['role'] ?? '') === 'admin' && $is_cart_redirect) { ?>
      <p class="login-sub">Your cart is for customer accounts. Sign in with an active customer account to continue.</p>
    <?php } ?>

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