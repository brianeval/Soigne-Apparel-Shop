<?php
session_start();
include_once("../includes/config.php");
require_once("../includes/profile_image.php");

// Redirect validation
function safe_redirect($redirect_path) {
    return preg_match('#^/(?!/)#', $redirect_path) ? $redirect_path : BASE_URL . 'index.php';
}

$redirect = safe_redirect($_POST['redirect'] ?? $_GET['redirect'] ?? '');

// Admin redirect
if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin') {
    header('Location: ' . BASE_URL . 'admin/index.php');
    exit;
}

// Existing customer redirect
if (isset($_SESSION['user_id']) && in_array($_SESSION['role'] ?? '', ['customer', 'vip'], true)) {
    header('Location: ' . $redirect);
    exit;
}

$errors   = [];
$name     = '';
$username = '';
$email    = '';
$phone    = '';
$address  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['customer_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $address  = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate registration
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
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }
    // Check account availability
    if (!$errors) {
        // Check username and email
        $accounts_stmt = mysqli_prepare($conn, "SELECT username, email FROM users WHERE username = ? OR email = ?");
        mysqli_stmt_bind_param($accounts_stmt, 'ss', $username, $email);
        mysqli_stmt_execute($accounts_stmt);
        $taken = mysqli_fetch_all(mysqli_stmt_get_result($accounts_stmt), MYSQLI_ASSOC);
        foreach ($taken as $account) {
            if (strcasecmp($account['username'], $username) === 0) {
                $errors[] = 'That username is already taken.';
            }
            if (strcasecmp($account['email'], $email) === 0) {
                $errors[] = 'That email is already registered.';
            }
        }
        $errors = array_unique($errors);
    }

    // Create account
    if (!$errors) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $user_id = 0;
        $saved_profile_image = null;

        try {
            mysqli_begin_transaction($conn);

            // Save login details
            $user_insert_stmt = mysqli_prepare($conn, "INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($user_insert_stmt, 'sss', $username, $email, $password_hash);
            mysqli_stmt_execute($user_insert_stmt);
            $user_id = mysqli_insert_id($conn);

            // Save customer details
            $customer_insert_stmt = mysqli_prepare($conn, "INSERT INTO customers (user_id, customer_name, phone, address) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($customer_insert_stmt, 'isss', $user_id, $name, $phone, $address);
            mysqli_stmt_execute($customer_insert_stmt);

            $profile_upload = $_FILES['profile_photo'] ?? null;
            if (is_array($profile_upload) && ($profile_upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $saved_profile_image = save_profile_image_upload($profile_upload, $user_id);
            }

            mysqli_commit($conn);

            // Sign in new customer
            session_regenerate_id(true);
            $_SESSION['user_id']  = (int)$user_id;
            $_SESSION['username'] = $username;
            $_SESSION['role']     = 'customer';
            header('Location: ' . $redirect);
            exit;

        } catch (RuntimeException $e) {
            mysqli_rollback($conn);
            if ($user_id > 0 && $saved_profile_image !== null) {
                try {
                    remove_old_profile_images($user_id);
                } catch (RuntimeException $cleanup_error) {
                    $errors[] = $cleanup_error->getMessage();
                }
            }
            $errors[] = $e->getMessage();
        } catch (mysqli_sql_exception $e) {
            mysqli_rollback($conn);
            if ($user_id > 0 && $saved_profile_image !== null) {
                try {
                    remove_old_profile_images($user_id);
                } catch (RuntimeException $cleanup_error) {
                    $errors[] = $cleanup_error->getMessage();
                }
            }
            $errors[] = $e->getCode() === 1062
                ? 'That username or email is already registered.'
                : 'Could not create your account. Please try again.';
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

    <form method="post" action="register.php" enctype="multipart/form-data" novalidate>
      <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">

      <label for="customer_name">Full name</label>
      <input type="text" id="customer_name" name="customer_name" value="<?php echo htmlspecialchars($name); ?>" autofocus>

      <label for="username">Username</label>
      <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>">

      <label for="email">Email</label>
      <input type="text" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>">

      <label for="phone">Phone number</label>
      <input type="tel" id="phone" name="phone">

      <label for="address">Address</label>
      <textarea id="address" name="address"></textarea>

      <label for="profile_photo">Profile image (optional)</label>
      <input type="file" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png,image/webp" aria-describedby="profile-photo-help">
      <small id="profile-photo-help" class="register-photo-help">JPEG, PNG, or WebP - 5 MB max</small>

      <label for="password">Password (at least 8 characters)</label>
      <input type="password" id="password" name="password">

      <label for="confirm_password">Confirm password</label>
      <input type="password" id="confirm_password" name="confirm_password">

      <button class="btn" type="submit">Create account</button>
    </form>

    <p class="login-alt">
      Already have an account?
      <a href="login.php?redirect=<?php echo urlencode($redirect); ?>">Sign in</a>
    </p>
  </div>
</main>

<?php include_once('../includes/footer.php'); ?>