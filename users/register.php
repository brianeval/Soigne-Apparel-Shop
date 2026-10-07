<?php
session_start();
include_once('../includes/config.php');

// Only allow redirects to paths on this site (never full URLs)
function safe_redirect($path) {
    return preg_match('#^/(?!/)#', $path) ? $path : 'index.php';
}

$redirect = safe_redirect($_POST['redirect'] ?? $_GET['redirect'] ?? '');

// Already signed in? No need to register.
if (isset($_SESSION['user_id'])) {
    header('Location: ' . $redirect);
    exit;
}

$errors   = [];
$name     = '';
$username = '';
$email    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['customer_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    // --- Validate ---
    if ($name === '' || mb_strlen($name) > 120) {
        $errors[] = 'Please enter your full name.';
    }
    if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
        $errors[] = 'Username must be 3-50 characters: letters, numbers and underscores only.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } elseif (strlen($password) > 72) {
        $errors[] = 'Password must be 72 characters or fewer.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    // --- Check username / email are free ---
    if (!$errors) {
        $stmt = mysqli_prepare($conn, "SELECT username, email FROM users WHERE username = ? OR email = ?");
        mysqli_stmt_bind_param($stmt, 'ss', $username, $email);
        mysqli_stmt_execute($stmt);
        $taken = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
        foreach ($taken as $t) {
            if (strcasecmp($t['username'], $username) === 0) { $errors[] = 'That username is already taken.'; }
            if (strcasecmp($t['email'], $email) === 0)       { $errors[] = 'That email is already registered.'; }
        }
        $errors = array_unique($errors);
    }

    // --- Create the account ---
    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            mysqli_begin_transaction($conn);

            $stmt = mysqli_prepare($conn, "INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'sss', $username, $email, $hash);
            mysqli_stmt_execute($stmt);
            $user_id = mysqli_insert_id($conn);

            $stmt = mysqli_prepare($conn, "INSERT INTO customers (user_id, customer_name) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, 'is', $user_id, $name);
            mysqli_stmt_execute($stmt);

            mysqli_commit($conn);

            // Sign the new user in and send them back where they came from
            session_regenerate_id(true);
            $_SESSION['user_id']  = (int)$user_id;
            $_SESSION['username'] = $username;
            $_SESSION['role']     = 'customer';
            header('Location: ' . $redirect);
            exit;

        } catch (mysqli_sql_exception $e) {
            mysqli_rollback($conn);
            if ($e->getCode() == 1062) {
                $errors[] = 'That username or email is already registered.';  // two people signed up at the same moment
            } else {
                $errors[] = 'Could not create your account. Please try again.';
            }
        }
    }
}

$page_title = 'Create account | Soigné';
$extra_css  = 'login.css';
include_once('../includes/header.php');
?>

<main class="login-page">
  <div class="login-card">
    <h1>Create account</h1>
    <p class="login-sub">Sign up to add items to your cart and track your orders.</p>

    <?php if ($errors) { ?>
      <div class="login-error" role="alert">
        <ul>
          <?php foreach ($errors as $err) { ?>
            <li><?php echo htmlspecialchars($err); ?></li>
          <?php } ?>
        </ul>
      </div>
    <?php } ?>

    <form method="post" action="register.php">
      <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">

      <label for="customer_name">Full name</label>
      <input type="text" id="customer_name" name="customer_name" value="<?php echo htmlspecialchars($name); ?>" required autofocus>

      <label for="username">Username</label>
      <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>

      <label for="email">Email</label>
      <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>

      <label for="password">Password (at least 8 characters)</label>
      <input type="password" id="password" name="password" required minlength="8">

      <label for="confirm_password">Confirm password</label>
      <input type="password" id="confirm_password" name="confirm_password" required minlength="8">

      <button class="btn" type="submit">Create account</button>
    </form>

    <p class="login-alt">
      Already have an account?
      <a href="login.php?redirect=<?php echo urlencode($redirect); ?>">Sign in</a>
    </p>
  </div>
</main>

<?php include_once('../includes/footer.php'); ?>