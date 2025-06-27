<!-- File: pages/users/add.php -->
<?php
session_start();
require_once '../../config/database.php';
require_once '../../classes/Auth.php';
require_once '../../classes/User.php';
require_once '../../includes/functions.php';

// Check authentication and admin role
$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->hasRole('admin')) {
    redirect('/pages/auth/login.php');
}

$db = Database::getInstance()->getConnection();
$userClass = new User($db);
$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitizeInput($_POST['username']);
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $fullName = sanitizeInput($_POST['full_name']);
    $role = sanitizeInput($_POST['role']);

    // Validation
    if (empty($username)) {
        $errors[] = 'Username is required';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
        $errors[] = 'Username must be 3-50 characters long and contain only letters, numbers, and underscores';
    } elseif ($userClass->usernameExists($username)) {
        $errors[] = 'Username already exists';
    }

    if (empty($email)) {
        $errors[] = 'Valid email is required';
    } elseif ($userClass->emailExists($email)) {
        $errors[] = 'Email already exists';
    }

    if (empty($password)) {
        $errors[] = 'Password is required';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long';
    } elseif ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match';
    }

    if (empty($fullName)) {
        $errors[] = 'Full name is required';
    }

    if (!in_array($role, ['admin', 'operator', 'viewer'])) {
        $errors[] = 'Invalid role selected';
    }

    // Create user if no errors
    if (empty($errors)) {
        $userData = [
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'full_name' => $fullName,
            'role' => $role
        ];

        if ($userClass->create($userData)) {
            $_SESSION['success'] = 'User created successfully';
            redirect('/pages/users/index.php');
        } else {
            $errors[] = 'Failed to create user. Please try again.';
        }
    }
}

$pageTitle = 'Add New User';
$currentPage = 'users';
?>
<!DOCTYPE html>
<html lang="en">
<?php include '../../includes/header.php'; ?>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">
        <?php include '../../includes/navbar.php'; ?>
        <?php include '../../includes/sidebar.php'; ?>

        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <!-- Content Header -->
            <div class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1 class="m-0">Add New User</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="/pages/dashboard/">Home</a></li>
                                <li class="breadcrumb-item"><a href="/pages/users/">Users</a></li>
                                <li class="breadcrumb-item active">Add New</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main content -->
            <section class="content">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-md-8 offset-md-2">
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo $error; ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">User Information</h3>
                                </div>
                                <form method="POST" action="">
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label for="username">Username <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="username" name="username"
                                                value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                                                required>
                                            <small class="form-text text-muted">3-50 characters, letters, numbers, and underscores only</small>
                                        </div>

                                        <div class="form-group">
                                            <label for="email">Email <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control" id="email" name="email"
                                                value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                                required>
                                        </div>

                                        <div class="form-group">
                                            <label for="full_name">Full Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="full_name" name="full_name"
                                                value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>"
                                                required>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="password">Password <span class="text-danger">*</span></label>
                                                    <input type="password" class="form-control" id="password" name="password" required>
                                                    <small class="form-text text-muted">Minimum 8 characters</small>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="confirm_password">Confirm Password <span class="text-danger">*</span></label>
                                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="role">Role <span class="text-danger">*</span></label>
                                            <select class="form-control" id="role" name="role" required>
                                                <option value="">Select Role</option>
                                                <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                                                <option value="operator" <?php echo (isset($_POST['role']) && $_POST['role'] == 'operator') ? 'selected' : ''; ?>>Operator</option>
                                                <option value="viewer" <?php echo (isset($_POST['role']) && $_POST['role'] == 'viewer') ? 'selected' : ''; ?>>Viewer</option>
                                            </select>
                                            <small class="form-text text-muted">
                                                <strong>Admin:</strong> Full access to all features<br>
                                                <strong>Operator:</strong> Can manage devices and messages<br>
                                                <strong>Viewer:</strong> Read-only access
                                            </small>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <button type="submit" class="btn btn-primary">Create User</button>
                                        <a href="/pages/users/" class="btn btn-default">Cancel</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <?php include '../../includes/footer.php'; ?>
    </div>

    <script>
        $(document).ready(function() {
            // Password strength indicator
            $('#password').on('keyup', function() {
                var password = $(this).val();
                var strength = 0;

                if (password.length >= 8) strength++;
                if (password.match(/[a-z]+/)) strength++;
                if (password.match(/[A-Z]+/)) strength++;
                if (password.match(/[0-9]+/)) strength++;
                if (password.match(/[$@#&!]+/)) strength++;

                var strengthText = '';
                var strengthClass = '';

                switch (strength) {
                    case 0:
                    case 1:
                        strengthText = 'Weak';
                        strengthClass = 'text-danger';
                        break;
                    case 2:
                    case 3:
                        strengthText = 'Medium';
                        strengthClass = 'text-warning';
                        break;
                    case 4:
                    case 5:
                        strengthText = 'Strong';
                        strengthClass = 'text-success';
                        break;
                }

                $('#password').siblings('.form-text').html('Minimum 8 characters <span class="' + strengthClass + ' ml-2">' + strengthText + '</span>');
            });

            // Confirm password validation
            $('#confirm_password').on('keyup', function() {
                if ($(this).val() !== $('#password').val()) {
                    $(this).addClass('is-invalid');
                    if (!$(this).next('.invalid-feedback').length) {
                        $(this).after('<div class="invalid-feedback">Passwords do not match</div>');
                    }
                } else {
                    $(this).removeClass('is-invalid');
                    $(this).next('.invalid-feedback').remove();
                }
            });
        });
    </script>
</body>

</html>