<?php
session_start();
include_once('../includes/config.php');
require_once __DIR__ . '/../includes/profile_image.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'users/login.php?redirect=' . urlencode(BASE_URL . 'users/profile.php'));
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$errors = [];
$success = $_SESSION['profile_success'] ?? '';
unset($_SESSION['profile_success']);

if (!isset($_SESSION['profile_csrf'])) {
    $_SESSION['profile_csrf'] = bin2hex(random_bytes(32));
}

$stmt = mysqli_prepare($conn, "SELECT u.username, u.email, c.customer_name, c.phone, c.address
                               FROM users u
                               LEFT JOIN customers c ON c.user_id = u.user_id
                               WHERE u.user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$profile = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

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
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!is_string($csrf_token) || !hash_equals($_SESSION['profile_csrf'], $csrf_token)) {
        $errors[] = 'Your session expired. Please reload the page and try again.';
    }

    $form_action = $_POST['form_action'] ?? '';
    if (!$errors && $form_action === 'update_photo') {
        $saved_profile_image = null;
        try {
            if (!isset($_FILES['profile_photo'])) {
                throw new RuntimeException('Please choose a profile picture to upload.');
            }
            $saved_profile_image = save_profile_image_upload($_FILES['profile_photo'], $user_id);
            if ($saved_profile_image === null) {
                throw new RuntimeException('Please choose a profile picture to upload.');
            }
            remove_old_profile_images($user_id, $saved_profile_image);
            $_SESSION['profile_success'] = 'Your profile picture has been updated.';
            unset($_SESSION['profile_csrf']);
            header('Location: ' . BASE_URL . 'users/profile.php');
            exit;
        } catch (RuntimeException $e) {
            if ($saved_profile_image !== null) {
                remove_old_profile_images($user_id);
            }
            $errors[] = $e->getMessage();
        }
    } elseif (!$errors && $form_action === 'update_details') {
        $posted_name = $_POST['customer_name'] ?? '';
        $posted_phone = $_POST['phone'] ?? '';
        $posted_address = $_POST['address'] ?? '';
        $posted_username = $_POST['username'] ?? '';
        $posted_email = $_POST['email'] ?? '';
        $posted_password = $_POST['new_password'] ?? '';
        $posted_confirm_password = $_POST['confirm_password'] ?? '';

        $name = is_string($posted_name) ? trim($posted_name) : '';
        $phone = is_string($posted_phone) ? trim($posted_phone) : '';
        $address = is_string($posted_address) ? trim($posted_address) : '';
        $username = is_string($posted_username) ? trim($posted_username) : '';
        $email = is_string($posted_email) ? trim($posted_email) : '';
        $password = is_string($posted_password) ? $posted_password : '';
        $confirm_password = is_string($posted_confirm_password) ? $posted_confirm_password : '';

        if ($name === '' || mb_strlen($name) > 120) {
            $errors[] = 'Please enter your name (up to 120 characters).';
        }
        if (mb_strlen($phone) > 30) {
            $errors[] = 'Phone number must be 30 characters or fewer.';
        }
        if (mb_strlen($address) > 255) {
            $errors[] = 'Address must be 255 characters or fewer.';
        }
        if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
            $errors[] = 'Username must be 3-50 characters: letters, numbers and underscores only.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) {
            $errors[] = 'Please enter a valid email address.';
        }
        if ($password !== '' || $confirm_password !== '') {
            if (strlen($password) < 8) {
                $errors[] = 'A new password must be at least 8 characters.';
            } elseif (strlen($password) > 72) {
                $errors[] = 'A new password must be 72 characters or fewer.';
            }
            if ($password !== $confirm_password) {
                $errors[] = 'The new password and confirmation do not match.';
            }
        }

        if (!$errors) {
            $stmt = mysqli_prepare($conn, "SELECT username, email FROM users
                                           WHERE (username = ? OR email = ?) AND user_id <> ?");
            mysqli_stmt_bind_param($stmt, 'ssi', $username, $email, $user_id);
            mysqli_stmt_execute($stmt);
            $taken = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
            foreach ($taken as $account) {
                if (strcasecmp($account['username'], $username) === 0) {
                    $errors[] = 'That username is already taken.';
                }
                if (strcasecmp($account['email'], $email) === 0) {
                    $errors[] = 'That email is already registered.';
                }
            }
        }

        if (!$errors) {
            try {
                mysqli_begin_transaction($conn);

                $stmt = mysqli_prepare($conn, "UPDATE users SET username = ?, email = ? WHERE user_id = ?");
                mysqli_stmt_bind_param($stmt, 'ssi', $username, $email, $user_id);
                mysqli_stmt_execute($stmt);

                $stmt = mysqli_prepare($conn, "INSERT INTO customers (user_id, customer_name, phone, address)
                                               VALUES (?, ?, ?, ?)
                                               ON DUPLICATE KEY UPDATE customer_name = VALUES(customer_name),
                                                                       phone = VALUES(phone),
                                                                       address = VALUES(address)");
                mysqli_stmt_bind_param($stmt, 'isss', $user_id, $name, $phone, $address);
                mysqli_stmt_execute($stmt);

                if ($password !== '') {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE user_id = ?");
                    mysqli_stmt_bind_param($stmt, 'si', $password_hash, $user_id);
                    mysqli_stmt_execute($stmt);
                }

                mysqli_commit($conn);
                $_SESSION['username'] = $username;
                $_SESSION['profile_success'] = 'Your profile has been updated.';
                unset($_SESSION['profile_csrf']);
                header('Location: ' . BASE_URL . 'users/profile.php');
                exit;
            } catch (mysqli_sql_exception $e) {
                mysqli_rollback($conn);
                $errors[] = $e->getCode() == 1062
                    ? 'That username or email is already registered.'
                    : 'Could not update your profile. Please try again.';
            }
        }
    } elseif (!$errors) {
        $errors[] = 'Invalid profile update request.';
    }
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
          <button class="profile-edit profile-photo-edit" id="profile-photo-edit" type="button">
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <path d="M12 20h9"></path>
              <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4Z"></path>
            </svg>
            <span>Edit</span>
          </button>
        </div>
        <form method="post" action="<?php echo BASE_URL; ?>users/profile.php" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['profile_csrf']); ?>">
          <input type="hidden" name="form_action" value="update_photo">
          <img class="profile-photo-preview" id="profile-photo-preview"
               src="<?php echo htmlspecialchars(profile_image_url($user_id)); ?>"
               alt="Your profile picture">
          <div class="profile-photo-picker" id="profile-photo-picker" hidden>
            <label for="profile_photo">Choose a new picture</label>
            <div class="profile-file-picker">
              <label class="profile-file-button" for="profile_photo">Choose file</label>
              <span class="profile-file-name" id="profile-file-name">No file chosen</span>
              <input type="file" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png,image/webp" disabled required>
            </div>
            <small>JPEG, PNG, or WebP; max 5 MB.</small>
          </div>
          <div class="profile-photo-actions" id="profile-photo-actions" hidden>
            <button class="btn" type="submit">Update picture</button>
            <button class="profile-cancel" id="profile-photo-cancel" type="button">Cancel</button>
          </div>
        </form>
      </section>

      <section class="profile-panel profile-details-panel" aria-labelledby="profile-details-title">
        <div class="profile-panel-heading">
          <h2 id="profile-details-title">Personal information</h2>
          <button class="profile-edit" id="profile-edit" type="button" aria-label="Edit profile details">
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <path d="M12 20h9"></path>
              <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4Z"></path>
            </svg>
            <span>Edit</span>
          </button>
        </div>
        <form method="post" action="<?php echo BASE_URL; ?>users/profile.php" id="profile-form">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['profile_csrf']); ?>">
          <input type="hidden" name="form_action" value="update_details">
          <div class="profile-field">
            <label for="customer_name">Name</label>
            <input type="text" id="customer_name" name="customer_name" maxlength="120"
                   value="<?php echo htmlspecialchars($name); ?>" readonly required>
          </div>

          <div class="profile-field">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" maxlength="50"
                   value="<?php echo htmlspecialchars($username); ?>" readonly required>
          </div>

          <div class="profile-field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" maxlength="120"
                   value="<?php echo htmlspecialchars($email); ?>" readonly required>
          </div>

          <div class="profile-field">
            <label for="phone">Phone number</label>
            <input type="tel" id="phone" name="phone" maxlength="30"
                   value="<?php echo htmlspecialchars($phone); ?>" readonly>
          </div>

          <div class="profile-field">
            <label for="address">Address</label>
            <textarea id="address" name="address" maxlength="255" readonly><?php echo htmlspecialchars($address); ?></textarea>
          </div>

          <div class="profile-field">
            <label for="password-mask">Password</label>
            <input type="text" id="password-mask" value="********" readonly aria-label="Password hidden">
          </div>

          <div class="profile-password-fields" id="profile-password-fields" hidden>
            <p>Leave these blank if you don't want to change your password.</p>
            <div class="profile-field">
              <label for="new_password">New password</label>
              <input type="password" id="new_password" name="new_password" minlength="8" maxlength="72"
                     autocomplete="new-password">
            </div>
            <div class="profile-field">
              <label for="confirm_password">Confirm new password</label>
              <input type="password" id="confirm_password" name="confirm_password" minlength="8" maxlength="72"
                     autocomplete="new-password">
            </div>
          </div>

          <div class="profile-actions" id="profile-actions" hidden>
            <button class="btn" type="submit">Save changes</button>
            <button class="profile-cancel" id="profile-cancel" type="button">Cancel</button>
          </div>
        </form>
      </section>
    </div>
  </section>
</main>

<script>
(function () {
  var form = document.getElementById('profile-form');
  var editButton = document.getElementById('profile-edit');
  var cancelButton = document.getElementById('profile-cancel');
  var actions = document.getElementById('profile-actions');
  var passwordFields = document.getElementById('profile-password-fields');
  var editableFields = form.querySelectorAll('#customer_name, #username, #email, #phone, #address');
  var photoForm = document.querySelector('.profile-picture-panel form');
  var photoEditButton = document.getElementById('profile-photo-edit');
  var photoCancelButton = document.getElementById('profile-photo-cancel');
  var photoActions = document.getElementById('profile-photo-actions');
  var photoPicker = document.getElementById('profile-photo-picker');
  var photoInput = document.getElementById('profile_photo');
  var photoPreview = document.getElementById('profile-photo-preview');
  var originalPhoto = photoPreview.src;

  function setEditing(editing) {
    editableFields.forEach(function (field) {
      field.readOnly = !editing;
    });
    editButton.hidden = editing;
    actions.hidden = !editing;
    passwordFields.hidden = !editing;
  }

  editButton.addEventListener('click', function () {
    setEditing(true);
    document.getElementById('customer_name').focus();
  });

  cancelButton.addEventListener('click', function () {
    form.reset();
    setEditing(false);
  });

  function setPhotoEditing(editing) {
    photoEditButton.hidden = editing;
    photoPicker.hidden = !editing;
    photoActions.hidden = !editing;
    photoInput.disabled = !editing;
  }

  photoEditButton.addEventListener('click', function () {
    setPhotoEditing(true);
    photoInput.focus();
  });

  var fileNameText = document.getElementById('profile-file-name');

  function updateFileLabel() {
    if (photoInput.files && photoInput.files[0]) {
      fileNameText.textContent = photoInput.files[0].name;
      photoPreview.src = URL.createObjectURL(photoInput.files[0]);
    } else {
      fileNameText.textContent = 'No file chosen';
    }
  }

  photoInput.addEventListener('change', updateFileLabel);

  photoCancelButton.addEventListener('click', function () {
    photoForm.reset();
    photoPreview.src = originalPhoto;
    fileNameText.textContent = 'No file chosen';
    setPhotoEditing(false);
  });
})();
</script>

<?php include_once('../includes/footer.php'); ?>