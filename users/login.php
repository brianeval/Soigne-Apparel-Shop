<?php
session_start();
include_once("../includes/config.php");


$redirect = $_POST['redirect'] ?? $_GET['redirect'] ?? '';
if (strpos($redirect, BASE_URL) !== 0) {
    $redirect = BASE_URL . 'index.php';
}

$cart_redirects = [
    BASE_URL . 'cart/cart.php',
    BASE_URL . 'cart/cart_add.php?resume=1',
];
$is_cart_redirect = in_array($redirect, $cart_redirects);


if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') == 'admin' && !$is_cart_redirect) {
    header('Location: ' . BASE_URL . 'admin/manage-products/');
    exit;
}
if (isset($_SESSION['user_id']) && in_array($_SESSION['role'] ?? '', ['customer', 'vip'])) {
    header('Location: ' . $redirect);
    exit;
}

$error = '';
$login_value = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login_value = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($login_value == '' || $password == '') {
        $error = 'Please enter your username or email and your password.';
    } else {

        $user_result = mysqli_execute_query($conn, "SELECT user_id, username, password, role
             FROM users
             WHERE (username = ? OR email = ?) AND role IN ('customer', 'vip') AND status = 'active'
             LIMIT 1", [$login_value, $login_value]);
        $user = mysqli_fetch_assoc($user_result);

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']  = (int)$user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];
            header('Location: ' . $redirect);
            exit;
        }


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