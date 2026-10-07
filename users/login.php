<?php
session_start();
include_once('../includes/config.php');

// Only allow redirects to paths on this site (never full URLs)
function safe_redirect($path) {
    return preg_match('#^/(?!/)#', $path) ? $path : 'index.php';
}

$redirect = safe_redirect($_POST['redirect'] ?? $_GET['redirect'] ?? '');

// Already signed in? Skip the form.
if (isset($_SESSION['user_id'])) {
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
        $q = "SELECT user_id, username, password, role FROM users WHERE username = ? OR email = ? LIMIT 1";
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
        $error = 'Incorrect username/email or password.';
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