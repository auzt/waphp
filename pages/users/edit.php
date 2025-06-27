<!-- File: pages/users/edit.php -->
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
$currentUser = $auth->getCurrentUser();
$errors = [];

// Get user ID from query parameter
$userId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$userId) {
    $_SESSION['error'] = 'Invalid user ID';
    redirect('/pages/users/index.php');
}

// Get user data
$user = $userClass->getById($userId);
if (!$user) {
    $_SESSION['error'] = 'User not found';
    redirect('/pages/users/index.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitizeInput($_POST['username']);
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $fullName = sanitizeInput($_POST['full_name']);
    $role = sanitizeInput($_POST['role']);
    $status = sanitizeInput($_POST['status']);
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($username)) {
        $errors[] = 'Username is required';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
        $errors[] = 'Username must be 3-50 characters long and contain only letters, numbers, and underscores';
    } elseif ($username != $user['username'] && $userClass->usernameExists($username)) {
        $errors[] = 'Username already exists';
    }
    
    if (empty($email)) {
        $errors[] = 'Valid email is required';
    } elseif ($email != $user['email'] && $userClass->emailExists($email)) {
        $errors[] = 'Email already exists';
    }
    
    if (empty($fullName)) {
        $errors[] = 'Full name is required';
    }
    
    if (!in_array($role, ['admin', 'operator', 'viewer'])) {
        $errors[] = 'Invalid role selected';
    }
    
    if (!in_array($status, ['active', 'inactive'])) {
        $errors[] = 'Invalid status selected';
    }
    
    // Password validation if provided
    if (!empty($password)) {
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        } elseif ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match';
        }
    }
    
    // Prevent user from changing their own role or deactivating themselves
    if ($userId == $currentUser['id']) {
        if ($role != $currentUser['role']) {
            $errors[] = 'You cannot change your own role';
        }
        if ($status == 'inactive') {
            $errors[] = 'You cannot deactivate your own account';
        }
    }
    
    // Update user if no errors
    if (empty($errors)) {
        $updateData = [
            'username' => $username,
            'email' => $email,
            'full_name' => $fullName,
            'role' => $role,
            'status' => $status
        ];
        
        if (!empty($password)) {
            $updateData['password'] = $password;
        }
        
        if ($userClass->update($userId, $updateData)) {
            $_SESSION['success'] = 'User updated successfully';
            redirect('/pages/users/index.php');
        } else {
            $errors[] = 'Failed to update user. Please try again.';
        }
    }
}

// Get user's devices count
$stmt = $db->prepare("SELECT COUNT(*) as device_count FROM devices WHERE user_id = ?");
$stmt->execute([$userId]);
$deviceCount = $stmt->fetch()['device_count'];

$pageTitle = 'Edit User';
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
                        <h1 class="m-0">Edit User</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/pages/dashboard/">Home</a></li>
                            <li class="breadcrumb-item"><a href="/pages/users/">Users</a></li>
                            <li class="breadcrumb-item active">Edit</li>
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

                        <!-- User Info Card -->
                        <div class="card card-info">
                            <div class="card-header">
                                <h3 class="card-title">User Information</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>User ID:</strong> <?php echo $user['id']; ?></p>
                                        <p><strong>Created:</strong> <?php echo formatDateTime($user['created_at']); ?></p>
                                        <p><strong>Last Updated:</strong> <?php echo formatDateTime($user['updated_at']); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Last Login:</strong> <?php echo $user['last_login'] ? formatDateTime($user['last_login']) : 'Never'; ?></p>
                                        <p><strong>Devices:</strong> <?php echo $deviceCount; ?></p>
                                        <p><strong>Current Status:</strong> 
                                            <span class="badge badge-<?php echo $user['status'] == 'active' ? 'success' : 'danger'; ?>">
                                                <?php echo ucfirst($user['status']); ?>
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Edit Form Card -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Edit User Details</h3>
                            </div>
                            <form method="POST" action="">
                                <div class="card-body">
                                    <div class="form-group">
                                        <label for="username">Username <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="username" name="username" 
                                               value="<?php echo htmlspecialchars($_POST['username'] ?? $user['username']); ?>" 
                                               required>
                                        <small class="form-text text-muted">3-50 characters, letters, numbers, and underscores only</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="email">Email <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($_POST['email'] ?? $user['email']); ?>" 
                                               required>
                                    </div>

                                    <div class="form-group">
                                        <label for="full_name">Full Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="full_name" name="full_name" 
                                               value="<?php echo htmlspecialchars($_POST['full_name'] ?? $user['full_name']); ?>" 
                                               required>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="role">Role <span class="text-danger">*</span></label>
                                                <select class="form-control" id="role" name="role" required 
                                                        <?php echo ($userId == $currentUser['id']) ? 'disabled' : ''; ?>>
                                                    <option value="admin" <?php echo (($_POST['role'] ?? $user['role']) == 'admin') ? 'selected' : ''; ?>>Admin</option>
                                                    <option value="operator" <?php echo (($_POST['role'] ?? $user['role']) == 'operator') ? 'selected' : ''; ?>>Operator</option>
                                                    <option value="viewer" <?php echo (($_POST['role'] ?? $user['role']) == 'viewer') ? 'selected' : ''; ?>>Viewer</option>
                                                </select>
                                                <?php if ($userId == $currentUser['id']): ?>
                                                    <input type="hidden" name="role" value="<?php echo $user['role']; ?>">
                                                    <small class="form-text text-muted">You cannot change your own role</small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="status">Status <span class="text-danger">*</span></label>
                                                <select class="form-control" id="status" name="status" required 
                                                        <?php echo ($userId == $currentUser['id']) ? 'disabled' : ''; ?>>
                                                    <option value="active" <?php echo (($_POST['status'] ?? $user['status']) == 'active') ? 'selected' : ''; ?>>Active</option>
                                                    <option value="inactive" <?php echo (($_POST['status'] ?? $user['status']) == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                                </select>
                                                <?php if ($userId == $currentUser['id']): ?>
                                                    <input type="hidden" name="status" value="<?php echo $user['status']; ?>">
                                                    <small class="form-text text-muted">You cannot change your own status</small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <hr>
                                    <h5>Change Password</h5>
                                    <p class="text-muted">Leave blank to keep current password</p>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="password">New Password</label>
                                                <input type="password" class="form-control" id="password" name="password">
                                                <small class="form-text text-muted">Minimum 8 characters</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="confirm_password">Confirm New Password</label>
                                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <button type="submit" class="btn btn-primary">Update User</button>
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
        if (password.length === 0) {
            $(this).siblings('.form-text').html('Minimum 8 characters');
            return;
        }
        
        var strength = 0;
        if (password.length >= 8) strength++;
        if (password.match(/[a-z]+/)) strength++;
        if (password.match(/[A-Z]+/)) strength++;
        if (password.match(/[0-9]+/)) strength++;
        if (password.match(/[$@#&!]+/)) strength++;
        
        var strengthText = '';
        var strengthClass = '';
        
        switch(strength) {
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
        
        $(this).siblings('.form-text').html('Minimum 8 characters <span class="' + strengthClass + ' ml-2">' + strengthText + '</span>');
    });
    
    // Confirm password validation
    $('#confirm_password').on('keyup', function() {
        if ($('#password').val().length > 0 && $(this).val() !== $('#password').val()) {
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