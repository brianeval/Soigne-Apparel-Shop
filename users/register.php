<?php
session_start();
include_once("../includes/config.php");


$redirect = $_POST['redirect'] ?? $_GET['redirect'] ?? '';
if (strpos($redirect, BASE_URL) !== 0) {
    $redirect = BASE_URL . 'index.php';
}


if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') == 'admin') {
    header('Location: ' . BASE_URL . 'admin/manage-products/');
    exit;
}
if (isset($_SESSION['user_id']) && in_array($_SESSION['role'] ?? '', ['customer', 'vip'])) {
    header('Location: ' . $redirect);
    exit;
}

$errors   = [];
$name     = '';
$username = '';
$email    = '';
$phone    = '';
$address  = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name     = trim($_POST['customer_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $address  = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';


    if ($name == '') {
        $errors[] = 'Please enter your full name.';
    }
    if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
        $errors[] = 'Username must be 3-50 characters: letters, numbers and underscores only.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password != $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }


    if (!$errors) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            mysqli_begin_transaction($conn);


            $user_insert_result = mysqli_execute_query($conn, "INSERT INTO users (username, email, password) VALUES (?, ?, ?)", [$username, $email, $password_hash]);
            $user_id = mysqli_insert_id($conn);


            $customer_insert_result = mysqli_execute_query($conn, "INSERT INTO customers (user_id, customer_name, phone, address) VALUES (?, ?, ?, ?)", [$user_id, $name, $phone, $address]);


            $upload_error = '';
            if (isset($_FILES['profile_photo'])) {
                $upload = $_FILES['profile_photo'];
                $upload_folder = 'profiles';
                $upload_prefix = 'profile-' . $user_id . '-';
                include("../includes/upload_image.php");
            }

            if ($upload_error != '') {
                mysqli_rollback($conn);
                $errors[] = $upload_error;
            } else {
                mysqli_commit($conn);


                session_regenerate_id(true);
                $_SESSION['user_id']  = (int)$user_id;
                $_SESSION['username'] = $username;
                $_SESSION['role']     = 'customer';
                header('Location: ' . $redirect);
                exit;
            }
        } catch (mysqli_sql_exception $e) {
            mysqli_rollback($conn);
            if ($e->getCode() == 1062) {
                $errors[] = 'That username or email is already registered.';
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