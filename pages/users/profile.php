<!-- File: pages/users/profile.php -->
<?php
session_start();
require_once '../../config/database.php';
require_once '../../classes/Auth.php';
require_once '../../classes/User.php';
require_once '../../includes/functions.php';

// Check authentication
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    redirect('/pages/auth/login.php');
}

$db = Database::getInstance()->getConnection();
$userClass = new User($db);
$currentUser = $auth->getCurrentUser();
$errors = [];
$successMessage = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $fullName = sanitizeInput($_POST['full_name']);
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    
    // Validation
    if (empty($fullName)) {
        $errors[] = 'Full name is required';
    }
    
    if (empty($email)) {
        $errors[] = 'Valid email is required';
    } elseif ($email != $currentUser['email'] && $userClass->emailExists($email)) {
        $errors[] = 'Email already exists';
    }
    
    // Update profile if no errors
    if (empty($errors)) {
        $updateData = [
            'full_name' => $fullName,
            'email' => $email
        ];
        
        if ($userClass->update($currentUser['id'], $updateData)) {
            $successMessage = 'Profile updated successfully';
            // Refresh current user data
            $currentUser = $userClass->getById($currentUser['id']);
            $_SESSION['user'] = $currentUser;
        } else {
            $errors[] = 'Failed to update profile. Please try again.';
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Validation
    if (empty($currentPassword)) {
        $errors[] = 'Current password is required';
    } elseif (!password_verify($currentPassword, $currentUser['password'])) {
        $errors[] = 'Current password is incorrect';
    }
    
    if (empty($newPassword)) {
        $errors[] = 'New password is required';
    } elseif (strlen($newPassword) < 8) {
        $errors[] = 'New password must be at least 8 characters long';
    } elseif ($newPassword !== $confirmPassword) {
        $errors[] = 'New passwords do not match';
    }
    
    // Update password if no errors
    if (empty($errors)) {
        if ($userClass->updatePassword($currentUser['id'], $newPassword)) {
            $successMessage = 'Password changed successfully';
        } else {
            $errors[] = 'Failed to change password. Please try again.';
        }
    }
}

// Get user statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT d.id) as device_count,
        COUNT(DISTINCT CASE WHEN d.status = 'connected' THEN d.id END) as connected_devices,
        COUNT(DISTINCT t.id) as token_count,
        COUNT(DISTINCT ml.id) as message_count
    FROM users u
    LEFT JOIN devices d ON u.id = d.user_id
    LEFT JOIN api_tokens t ON d.id = t.device_id AND t.is_active = 1
    LEFT JOIN message_logs ml ON d.id = ml.device_id
    WHERE u.id = ?
");
$stmt->execute([$currentUser['id']]);
$userStats = $stmt->fetch();

// Get recent activity
$stmt = $db->prepare("
    SELECT 
        'device' as type,
        d.device_name as name,
        d.created_at as timestamp,
        'Created new device' as action
    FROM devices d
    WHERE d.user_id = ?
    UNION ALL
    SELECT 
        'message' as type,
        d.device_name as name,
        ml.created_at as timestamp,
        CONCAT('Sent message via ', d.device_name) as action
    FROM message_logs ml
    JOIN devices d ON ml.device_id = d.id
    WHERE d.user_id = ? AND ml.direction = 'outgoing'
    ORDER BY timestamp DESC
    LIMIT 10
");
$stmt->execute([$currentUser['id'], $currentUser['id']]);
$recentActivity = $stmt->fetchAll();

$pageTitle = 'My Profile';
$currentPage = 'profile';
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
                        <h1 class="m-0">My Profile</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="/pages/dashboard/">Home</a></li>
                            <li class="breadcrumb-item active">Profile</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($successMessage): ?>
                    <div class="alert alert-success alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                        <?php echo $successMessage; ?>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Profile Overview -->
                    <div class="col-md-4">
                        <div class="card card-primary card-outline">
                            <div class="card-body box-profile">
                                <div class="text-center">
                                    <div class="profile-avatar mb-3">
                                        <i class="fas fa-user-circle fa-8x text-gray"></i>
                                    </div>
                                </div>

                                <h3 class="profile-username text-center"><?php echo htmlspecialchars($currentUser['full_name']); ?></h3>
                                <p class="text-muted text-center">
                                    <span class="badge badge-<?php echo getRoleBadgeClass($currentUser['role']); ?>">
                                        <?php echo ucfirst($currentUser['role']); ?>
                                    </span>
                                </p>

                                <ul class="list-group list-group-unbordered mb-3">
                                    <li class="list-group-item">
                                        <b>Username</b> <a class="float-right"><?php echo htmlspecialchars($currentUser['username']); ?></a>
                                    </li>
                                    <li class="list-group-item">
                                        <b>Email</b> <a class="float-right"><?php echo htmlspecialchars($currentUser['email']); ?></a>
                                    </li>
                                    <li class="list-group-item">
                                        <b>Member Since</b> <a class="float-right"><?php echo date('M d, Y', strtotime($currentUser['created_at'])); ?></a>
                                    </li>
                                    <li class="list-group-item">
                                        <b>Last Login</b> <a class="float-right"><?php echo $currentUser['last_login'] ? formatDateTime($currentUser['last_login']) : 'N/A'; ?></a>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Statistics Card -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">My Statistics</h3>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-6">
                                        <div class="description-block">
                                            <h5 class="description-header"><?php echo $userStats['device_count']; ?></h5>
                                            <span class="description-text">DEVICES</span>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="description-block">
                                            <h5 class="description-header"><?php echo $userStats['connected_devices']; ?></h5>
                                            <span class="description-text">CONNECTED</span>
                                        </div>
                                    </div>
                                </div>
                                <hr>
                                <div class="row text-center">
                                    <div class="col-6">
                                        <div class="description-block">
                                            <h5 class="description-header"><?php echo $userStats['token_count']; ?></h5>
                                            <span class="description-text">API TOKENS</span>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="description-block">
                                            <h5 class="description-header"><?php echo $userStats['message_count']; ?></h5>
                                            <span class="description-text">MESSAGES</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Settings -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header p-2">
                                <ul class="nav nav-pills">
                                    <li class="nav-item"><a class="nav-link active" href="#profile" data-toggle="tab">Profile</a></li>
                                    <li class="nav-item"><a class="nav-link" href="#password" data-toggle="tab">Password</a></li>
                                    <li class="nav-item"><a class="nav-link" href="#activity" data-toggle="tab">Activity</a></li>
                                </ul>
                            </div>
                            <div class="card-body">
                                <div class="tab-content">
                                    <!-- Profile Tab -->
                                    <div class="active tab-pane" id="profile">
                                        <form method="POST" action="">
                                            <div class="form-group">
                                                <label for="full_name">Full Name</label>
                                                <input type="text" class="form-control" id="full_name" name="full_name" 
                                                       value="<?php echo htmlspecialchars($currentUser['full_name']); ?>" required>
                                            </div>
                                            <div class="form-group">
                                                <label for="email">Email</label>
                                                <input type="email" class="form-control" id="email" name="email" 
                                                       value="<?php echo htmlspecialchars($currentUser['email']); ?>" required>
                                            </div>
                                            <div class="form-group">
                                                <label>Username</label>
                                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($currentUser['username']); ?>" disabled>
                                                <small class="form-text text-muted">Username cannot be changed</small>
                                            </div>
                                            <div class="form-group">
                                                <label>Role</label>
                                                <input type="text" class="form-control" value="<?php echo ucfirst($currentUser['role']); ?>" disabled>
                                                <small class="form-text text-muted">Contact administrator to change role</small>
                                            </div>
                                            <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                                        </form>
                                    </div>

                                    <!-- Password Tab -->
                                    <div class="tab-pane" id="password">
                                        <form method="POST" action="">
                                            <div class="form-group">
                                                <label for="current_password">Current Password</label>
                                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                                            </div>
                                            <div class="form-group">
                                                <label for="new_password">New Password</label>
                                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                                <small class="form-text text-muted">Minimum 8 characters</small>
                                            </div>
                                            <div class="form-group">
                                                <label for="confirm_password">Confirm New Password</label>
                                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                            </div>
                                            <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                                        </form>
                                    </div>

                                    <!-- Activity Tab -->
                                    <div class="tab-pane" id="activity">
                                        <div class="timeline timeline-inverse">
                                            <?php 
                                            $lastDate = '';
                                            foreach ($recentActivity as $activity): 
                                                $activityDate = date('Y-m-d', strtotime($activity['timestamp']));
                                                if ($activityDate != $lastDate):
                                                    if ($lastDate != '') echo '</div>';
                                                    $lastDate = $activityDate;
                                            ?>
                                                <div class="time-label">
                                                    <span class="bg-primary">
                                                        <?php echo date('M d, Y', strtotime($activityDate)); ?>
                                                    </span>
                                                </div>
                                                <div>
                                            <?php endif; ?>
                                                <i class="fas fa-<?php echo $activity['type'] == 'device' ? 'mobile-alt' : 'envelope'; ?> bg-<?php echo $activity['type'] == 'device' ? 'info' : 'success'; ?>"></i>
                                                <div class="timeline-item">
                                                    <span class="time"><i class="far fa-clock"></i> <?php echo date('H:i', strtotime($activity['timestamp'])); ?></span>
                                                    <h3 class="timeline-header"><?php echo htmlspecialchars($activity['action']); ?></h3>
                                                </div>
                                            <?php endforeach; ?>
                                            <?php if ($lastDate != '') echo '</div>'; ?>
                                            <div>
                                                <i class="far fa-clock bg-gray"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
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
    $('#new_password').on('keyup', function() {
        var password = $(this).val();
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
        if ($(this).val() !== $('#new_password').val()) {
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