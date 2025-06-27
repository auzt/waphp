<!-- File: pages/users/index.php -->
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

// Handle user status toggle
if (isset($_POST['toggle_status'])) {
    $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $status = $_POST['status'] == 'active' ? 'inactive' : 'active';
    
    if ($userId && $userId != $currentUser['id']) {
        $userClass->updateStatus($userId, $status);
        $_SESSION['success'] = 'User status updated successfully';
        redirect('/pages/users/index.php');
    }
}

// Handle user deletion
if (isset($_POST['delete_user'])) {
    $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    
    if ($userId && $userId != $currentUser['id']) {
        if ($userClass->delete($userId)) {
            $_SESSION['success'] = 'User deleted successfully';
        } else {
            $_SESSION['error'] = 'Failed to delete user';
        }
        redirect('/pages/users/index.php');
    }
}

// Get all users with statistics
$users = $userClass->getAllWithStats();

$pageTitle = 'User Management';
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
                        <h1 class="m-0">User Management</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item active">Users</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">All Users</h3>
                        <div class="card-tools">
                            <a href="add.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> Add New User
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <table id="usersTable" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Devices</th>
                                    <th>Status</th>
                                    <th>Last Login</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td>
                                        <a href="edit.php?id=<?php echo $user['id']; ?>">
                                            <?php echo htmlspecialchars($user['username']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo getRoleBadgeClass($user['role']); ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-info">
                                            <?php echo $user['device_count'] ?? 0; ?> devices
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($user['id'] != $currentUser['id']): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="status" value="<?php echo $user['status']; ?>">
                                                <button type="submit" name="toggle_status" class="btn btn-xs btn-<?php echo $user['status'] == 'active' ? 'success' : 'danger'; ?>">
                                                    <?php echo ucfirst($user['status']); ?>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $user['last_login'] ? formatDateTime($user['last_login']) : 'Never'; ?>
                                    </td>
                                    <td><?php echo formatDateTime($user['created_at']); ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="edit.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($user['id'] != $currentUser['id']): ?>
                                                <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <?php include '../../includes/footer.php'; ?>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete user <strong id="deleteUsername"></strong>?</p>
                <p class="text-danger">This action cannot be undone. All user data including devices will be deleted.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="user_id" id="deleteUserId">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_user" class="btn btn-danger">Delete User</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#usersTable').DataTable({
        "paging": true,
        "lengthChange": true,
        "searching": true,
        "ordering": true,
        "info": true,
        "autoWidth": false,
        "responsive": true,
        "order": [[0, "desc"]]
    });
});

function confirmDelete(userId, username) {
    $('#deleteUserId').val(userId);
    $('#deleteUsername').text(username);
    $('#deleteModal').modal('show');
}
</script>
</body>
</html>





