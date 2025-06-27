<?php

/**
 * AdminLTE Top Navigation Bar
 * 
 * Contains navbar with user menu, notifications, and sidebar toggle
 */

// Get current user data
$currentUser = getCurrentUser();
$userRole = getCurrentUserRole();

// Get notification counts (you can implement these functions later)
// $notificationCount = getNotificationCount();
// $messageCount = getUnreadMessageCount(); 
$notificationCount = 0;
$messageCount = 0;

// Get some quick stats for navbar
try {
    $db = Database::getInstance();

    // Get device stats
    $deviceStats = $db->selectOne("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'connected' THEN 1 ELSE 0 END) as connected,
            SUM(CASE WHEN status = 'banned' THEN 1 ELSE 0 END) as banned
        FROM devices 
        WHERE user_id = ?
    ", [getCurrentUserId()]);

    // Get today's message count  
    $messageStats = $db->selectOne("
        SELECT COUNT(*) as today_messages
        FROM message_logs ml
        JOIN devices d ON ml.device_id = d.id
        WHERE d.user_id = ? AND DATE(ml.created_at) = CURDATE()
    ", [getCurrentUserId()]);
} catch (Exception $e) {
    $deviceStats = ['total' => 0, 'connected' => 0, 'banned' => 0];
    $messageStats = ['today_messages' => 0];
}
?>

<!-- Navbar -->
<nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <!-- Left navbar links -->
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link" data-widget="pushmenu" href="#" role="button">
                <i class="fas fa-bars"></i>
            </a>
        </li>
        <li class="nav-item d-none d-sm-inline-block">
            <a href="<?php echo BASE_URL; ?>/pages/dashboard/" class="nav-link">Dashboard</a>
        </li>
        <li class="nav-item d-none d-sm-inline-block">
            <a href="<?php echo BASE_URL; ?>/pages/devices/" class="nav-link">Devices</a>
        </li>
        <?php if (hasPermission(PERM_VIEW_LOGS, $userRole)): ?>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="<?php echo BASE_URL; ?>/pages/logs/api-logs.php" class="nav-link">Logs</a>
            </li>
        <?php endif; ?>
    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">

        <!-- Quick Stats -->
        <li class="nav-item d-none d-md-block">
            <div class="navbar-text">
                <small class="text-muted">
                    <i class="fas fa-mobile-alt text-info"></i>
                    <span class="badge badge-info"><?php echo $deviceStats['connected']; ?></span>/<span class="text-muted"><?php echo $deviceStats['total']; ?></span>

                    <i class="fas fa-envelope text-primary ml-2"></i>
                    <span class="badge badge-primary"><?php echo $messageStats['today_messages']; ?></span>

                    <?php if ($deviceStats['banned'] > 0): ?>
                        <i class="fas fa-ban text-danger ml-2"></i>
                        <span class="badge badge-danger"><?php echo $deviceStats['banned']; ?></span>
                    <?php endif; ?>
                </small>
            </div>
        </li>

        <!-- Messages Dropdown Menu -->
        <li class="nav-item dropdown">
            <a class="nav-link" data-toggle="dropdown" href="#">
                <i class="far fa-comments"></i>
                <?php if ($messageCount > 0): ?>
                    <span class="badge badge-danger navbar-badge"><?php echo $messageCount; ?></span>
                <?php endif; ?>
            </a>
            <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                <a href="#" class="dropdown-item">
                    <!-- Message Start -->
                    <div class="media">
                        <img src="<?php echo ASSETS_URL; ?>/adminlte/dist/img/user1-128x128.jpg" alt="User Avatar" class="img-size-50 mr-3 img-circle">
                        <div class="media-body">
                            <h3 class="dropdown-item-title">
                                WhatsApp System
                                <span class="float-right text-sm text-danger"><i class="fas fa-star"></i></span>
                            </h3>
                            <p class="text-sm">Tidak ada pesan baru</p>
                            <p class="text-sm text-muted"><i class="far fa-clock mr-1"></i> <?php echo date('H:i'); ?></p>
                        </div>
                    </div>
                    <!-- Message End -->
                </a>
                <div class="dropdown-divider"></div>
                <a href="<?php echo BASE_URL; ?>/pages/logs/message-logs.php" class="dropdown-item dropdown-footer">Lihat Semua Pesan</a>
            </div>
        </li>

        <!-- Notifications Dropdown Menu -->
        <li class="nav-item dropdown">
            <a class="nav-link" data-toggle="dropdown" href="#">
                <i class="far fa-bell"></i>
                <?php if ($notificationCount > 0): ?>
                    <span class="badge badge-warning navbar-badge"><?php echo $notificationCount; ?></span>
                <?php endif; ?>
            </a>
            <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                <span class="dropdown-item dropdown-header"><?php echo $notificationCount; ?> Notifikasi</span>

                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item">
                    <i class="fas fa-envelope mr-2"></i> Tidak ada notifikasi baru
                    <span class="float-right text-muted text-sm"><?php echo date('H:i'); ?></span>
                </a>

                <div class="dropdown-divider"></div>
                <a href="<?php echo BASE_URL; ?>/pages/monitoring/alerts.php" class="dropdown-item dropdown-footer">Lihat Semua Notifikasi</a>
            </div>
        </li>

        <!-- User Account Menu -->
        <li class="nav-item dropdown user-menu">
            <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown">
                <img src="<?php echo $currentUser['avatar_url'] ?: ASSETS_URL . '/adminlte/dist/img/user2-160x160.jpg'; ?>"
                    class="user-image img-circle elevation-2"
                    alt="User Image">
                <span class="d-none d-md-inline"><?php echo htmlspecialchars($currentUser['full_name']); ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                <!-- User image -->
                <li class="user-header bg-primary">
                    <img src="<?php echo $currentUser['avatar_url'] ?: ASSETS_URL . '/adminlte/dist/img/user2-160x160.jpg'; ?>"
                        class="img-circle elevation-2"
                        alt="User Image">
                    <p>
                        <?php echo htmlspecialchars($currentUser['full_name']); ?>
                        <small><?php echo ucfirst($currentUser['role']); ?> - Login sejak <?php echo timeAgo($currentUser['login_time']); ?></small>
                    </p>
                </li>

                <!-- Menu Body -->
                <li class="user-body">
                    <div class="row">
                        <div class="col-4 text-center">
                            <a href="<?php echo BASE_URL; ?>/pages/devices/">
                                <strong><?php echo $deviceStats['total']; ?></strong><br>
                                <small>Devices</small>
                            </a>
                        </div>
                        <div class="col-4 text-center">
                            <a href="<?php echo BASE_URL; ?>/pages/api-tokens/">
                                <strong><?php echo $deviceStats['connected']; ?></strong><br>
                                <small>Online</small>
                            </a>
                        </div>
                        <div class="col-4 text-center">
                            <a href="<?php echo BASE_URL; ?>/pages/logs/message-logs.php">
                                <strong><?php echo $messageStats['today_messages']; ?></strong><br>
                                <small>Pesan Hari Ini</small>
                            </a>
                        </div>
                    </div>
                </li>

                <!-- Menu Footer-->
                <li class="user-footer">
                    <a href="<?php echo BASE_URL; ?>/pages/users/profile.php" class="btn btn-default btn-flat">
                        <i class="fas fa-user"></i> Profile
                    </a>
                    <a href="<?php echo BASE_URL; ?>/pages/auth/logout.php" class="btn btn-default btn-flat float-right">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>
        </li>

        <!-- Control Sidebar Toggle -->
        <li class="nav-item">
            <a class="nav-link" data-widget="control-sidebar" data-slide="true" href="#" role="button">
                <i class="fas fa-th-large"></i>
            </a>
        </li>
    </ul>
</nav>
<!-- /.navbar -->

<!-- Real-time Status Updates -->
<script>
    // Update navbar stats every 30 seconds
    setInterval(function() {
        updateNavbarStats();
    }, 30000);

    function updateNavbarStats() {
        $.get('<?php echo BASE_URL; ?>/api/devices.php?action=quick_stats')
            .done(function(response) {
                if (response.success) {
                    // Update device count badges
                    $('.navbar-nav .badge-info').first().text(response.data.connected);
                    $('.navbar-nav .text-muted').first().text(response.data.total);

                    // Update message count
                    if (response.data.today_messages !== undefined) {
                        $('.navbar-nav .badge-primary').text(response.data.today_messages);
                    }

                    // Update banned count
                    if (response.data.banned > 0) {
                        if ($('.navbar-nav .badge-danger').length === 0) {
                            // Add banned indicator if not exists
                            $('.navbar-nav .badge-primary').parent().append(
                                '<i class="fas fa-ban text-danger ml-2"></i> <span class="badge badge-danger">' + response.data.banned + '</span>'
                            );
                        } else {
                            $('.navbar-nav .badge-danger').text(response.data.banned);
                        }
                    } else {
                        // Remove banned indicator if no banned devices
                        $('.navbar-nav .fa-ban').parent().find('.fa-ban, .badge-danger').remove();
                    }
                }
            })
            .fail(function() {
                console.log('Failed to update navbar stats');
            });
    }

    // Initialize tooltips
    $(function() {
        $('[data-toggle="tooltip"]').tooltip();
    });
</script>