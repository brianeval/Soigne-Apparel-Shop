<?php
session_start();
include_once('../includes/config.php');

// Only allow redirects to paths on this site (never full URLs)
function safe_redirect($path) {
    return preg_match('#^/(?!/)#', $path) ? $path : 'index.php';
}

$redirect = safe_redirect($_POST['redirect'] ?? $_GET['redirect'] ?? '');

$cart_redirects = [
    BASE_URL . 'cart/cart.php',
    BASE_URL . 'cart/cart_add.php?resume=1',
];
$is_cart_redirect = in_array($redirect, $cart_redirects, true);

// Keep administrators in the admin area except when they are switching to a customer account for cart access.
if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin') {
    if (!$is_cart_redirect) {
        header('Location: ' . BASE_URL . 'admin/index.php');
        exit;
    }
}
if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'customer') {
    header('Location: ' . $redirect);
    exit;
}

$error = '';
$login_value = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_value = trim($_POST['login'] ?? '');
    $password    = $_POST['password'] ?? '';

    if ($login_value === '' || $password === '') {
        $error = 'Please enter your username or email and your password.';
    } else {
        // Only active customer accounts can sign in through the storefront login.
        $q = "SELECT user_id, username, password, role
              FROM users
              WHERE (username = ? OR email = ?) AND role = 'customer' AND status = 'active'
              LIMIT 1";
        $stmt = mysqli_prepare($conn, $q);
        mysqli_stmt_bind_param($stmt, 'ss', $login_value, $login_value);
        mysqli_stmt_execute($stmt);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);   // new session id after login
            $_SESSION['user_id']  = (int)$user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];
            header('Location: ' . $redirect);
            exit;
        }

        // Same message for wrong user and wrong password
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

    <form method="post" action="login.php">
      <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">

      <label for="login">Username or email</label>
      <input type="text" id="login" name="login" value="<?php echo htmlspecialchars($login_value); ?>" required autofocus>

      <label for="password">Password</label>
      <input type="password" id="password" name="password" required>

      <button class="btn" type="submit">Sign in</button>
    </form>

    <p class="login-alt">
      No account yet?
      <a href="register.php?redirect=<?php echo urlencode($redirect); ?>">Create one</a>
    </p>
  </div>
</main>

<?php include('../includes/footer.php'); ?>