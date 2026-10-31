<?php
session_start();
include_once('../includes/config.php');
require_once('../includes/product_images.php');
require_once('../includes/customer_auth.php');

require_login();

$user_id = (int)$_SESSION['user_id'];
$errors = [];
$success = $_SESSION['profile_success'] ?? '';
unset($_SESSION['profile_success']);

$profile = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT u.username, u.email, c.customer_name, c.phone, c.address
     FROM users u
     LEFT JOIN customers c ON c.user_id = u.user_id
     WHERE u.user_id = $user_id"));

if (!$profile) {
    http_response_code(404);
    exit('The signed-in user account could not be found.');
}

$name = $profile['customer_name'] ?? '';
$phone = $profile['phone'] ?? '';
$address = $profile['address'] ?? '';
$username = $profile['username'];
$email = $profile['email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_action = $_POST['form_action'] ?? '';

    if ($form_action === 'update_photo') {
        if (!empty($_FILES['profile_photo']['name'])) {
            foreach (glob(APP_ROOT . '/images/profiles/profile-' . $user_id . '-*') as $old_file) {
                unlink($old_file);
            }
            save_image_upload($_FILES['profile_photo'], 'profiles', 'profile-' . $user_id);
            $_SESSION['profile_success'] = 'Your profile picture has been updated.';
            header('Location: ' . BASE_URL . 'users/profile.php');
            exit;
        }
        $errors[] = 'Please choose a profile picture to upload.';
    } elseif ($form_action === 'update_details') {
        $name = trim($_POST['customer_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if ($name === '' || $username === '' || $email === '') {
            $errors[] = 'Name, username and email are required.';
        }
        if ($password !== '' && $password !== $confirm_password) {
            $errors[] = 'The new password and confirmation do not match.';
        }

        if (!$errors) {
            $username_sql = mysqli_real_escape_string($conn, $username);
            $email_sql = mysqli_real_escape_string($conn, $email);
            $taken = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT username, email FROM users
                 WHERE (username = '$username_sql' OR email = '$email_sql') AND user_id <> $user_id"));
            if ($taken) {
                $errors[] = strcasecmp($taken['username'], $username) === 0
                    ? 'That username is already taken.'
                    : 'That email is already registered.';
            }
        }

        if (!$errors) {
            $name_sql = mysqli_real_escape_string($conn, $name);
            $phone_sql = mysqli_real_escape_string($conn, $phone);
            $address_sql = mysqli_real_escape_string($conn, $address);

            mysqli_query($conn, "UPDATE users SET username = '$username_sql', email = '$email_sql' WHERE user_id = $user_id");
            mysqli_query($conn,
                "INSERT INTO customers (user_id, customer_name, phone, address)
                 VALUES ($user_id, '$name_sql', '$phone_sql', '$address_sql')
                 ON DUPLICATE KEY UPDATE customer_name = '$name_sql', phone = '$phone_sql', address = '$address_sql'");

            if ($password !== '') {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                mysqli_query($conn, "UPDATE users SET password = '$password_hash' WHERE user_id = $user_id");
            }

            $_SESSION['username'] = $username;
            $_SESSION['profile_success'] = 'Your profile has been updated.';
            header('Location: ' . BASE_URL . 'users/profile.php');
            exit;
        }
    }
}

$avatar = BASE_URL . 'images/default-profile.jpg';
foreach (glob(APP_ROOT . '/images/profiles/profile-' . $user_id . '-*') as $file) {
    $avatar = BASE_URL . 'images/profiles/' . basename($file);
}

$page_title = 'My profile | Soigné';
$extra_css = 'profile.css';
include_once('../includes/header.php');
?>

<main class="profile-page">
  <section class="profile-card" aria-labelledby="profile-title">
    <div class="profile-heading">
      <div>
        <h1 id="profile-title">My profile</h1>
        <p>View and update your account details.</p>
      </div>
    </div>

    <?php if ($success !== '') { ?>
      <p class="profile-message is-success" role="status"><?php echo htmlspecialchars($success); ?></p>
    <?php } ?>

    <?php if ($errors) { ?>
      <div class="profile-message is-error" role="alert">
        <ul>
          <?php foreach ($errors as $error) { ?>
            <li><?php echo htmlspecialchars($error); ?></li>
          <?php } ?>
        </ul>
      </div>
    <?php } ?>

    <div class="profile-panels">
      <section class="profile-panel profile-picture-panel" aria-labelledby="profile-picture-title">
        <div class="profile-panel-heading">
          <h2 id="profile-picture-title">Profile picture</h2>
        </div>
        <form method="post" action="<?php echo BASE_URL; ?>users/profile.php" enctype="multipart/form-data" novalidate>
          <input type="hidden" name="form_action" value="update_photo">
          <img class="profile-photo-preview" id="profile-photo-preview"
               src="<?php echo htmlspecialchars($avatar); ?>"
               alt="Your profile picture">
          <div class="profile-photo-picker">
            <label for="profile_photo">Choose a new picture</label>
            <div class="profile-file-picker">
              <label class="profile-file-button" for="profile_photo">Choose file</label>
              <span class="profile-file-name" id="profile-file-name">No file chosen</span>
              <input type="file" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png,image/webp">
            </div>
            <small>JPEG, PNG, or WebP; max 5 MB.</small>
          </div>
          <div class="profile-photo-actions">
            <button class="btn" type="submit">Update picture</button>
          </div>
        </form>
      </section>

      <section class="profile-panel profile-details-panel" aria-labelledby="profile-details-title">
        <div class="profile-panel-heading">
          <h2 id="profile-details-title">Personal information</h2>
        </div>
        <form method="post" action="<?php echo BASE_URL; ?>users/profile.php" id="profile-form" novalidate>
          <input type="hidden" name="form_action" value="update_details">
          <div class="profile-field">
            <label for="customer_name">Name</label>
            <input type="text" id="customer_name" name="customer_name"
                   value="<?php echo htmlspecialchars($name); ?>">
          </div>

          <div class="profile-field">
            <label for="username">Username</label>
            <input type="text" id="username" name="username"
                   value="<?php echo htmlspecialchars($username); ?>">
          </div>

          <div class="profile-field">
            <label for="email">Email</label>
            <input type="text" id="email" name="email"
                   value="<?php echo htmlspecialchars($email); ?>">
          </div>

          <div class="profile-field">
            <label for="phone">Phone number</label>
            <input type="tel" id="phone" name="phone"
                   value="<?php echo htmlspecialchars($phone); ?>">
          </div>

          <div class="profile-field">
            <label for="address">Address</label>
            <textarea id="address" name="address"><?php echo htmlspecialchars($address); ?></textarea>
          </div>

          <div class="profile-password-fields">
            <p>Leave these blank if you don't want to change your password.</p>
            <div class="profile-field">
              <label for="new_password">New password</label>
              <input type="password" id="new_password" name="new_password"
                     autocomplete="new-password">
            </div>
            <div class="profile-field">
              <label for="confirm_password">Confirm new password</label>
              <input type="password" id="confirm_password" name="confirm_password"
                     autocomplete="new-password">
            </div>
          </div>

          <div class="profile-actions">
            <button class="btn" type="submit">Save changes</button>
          </div>
        </form>
      </section>
    </div>
  </section>
</main>

<?php include_once('../includes/footer.php'); ?>
