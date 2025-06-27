<?php

/**
 * AdminLTE Sidebar Navigation
 * 
 * Main navigation menu with role-based access control
 */

// Get current user data
$currentUser = getCurrentUser();
$userRole = getCurrentUserRole();

// Get current page for active menu highlighting
$currentPage = $currentPage ?? '';
$currentUri = $_SERVER['REQUEST_URI'] ?? '';

// Helper function to check if menu is active
function isMenuActive($menuPath, $currentUri)
{
    return strpos($currentUri, $menuPath) !== false;
}

// Helper function to get menu class
function getMenuClass($menuPath, $currentUri)
{
    return isMenuActive($menuPath, $currentUri) ? 'active' : '';
}

// Get quick device stats for sidebar
try {
    $db = Database::getInstance();

    $sidebarStats = $db->selectOne("
        SELECT 
            COUNT(*) as total_devices,
            SUM(CASE WHEN status = 'connected' THEN 1 ELSE 0 END) as connected_devices,
            SUM(CASE WHEN status = 'banned' THEN 1 ELSE 0 END) as banned_devices,
            SUM(CASE WHEN status IN ('error', 'timeout') THEN 1 ELSE 0 END) as error_devices
        FROM devices 
        WHERE user_id = ?
    ", [getCurrentUserId()]);
} catch (Exception $e) {
    $sidebarStats = [
        'total_devices' => 0,
        'connected_devices' => 0,
        'banned_devices' => 0,
        'error_devices' => 0
    ];
}
?>

<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="<?php echo BASE_URL; ?>/pages/dashboard/" class="brand-link">
        <img src="<?php echo ASSETS_URL; ?>/custom/images/logo.png"
            alt="WhatsApp Monitor Logo"
            class="brand-image img-circle elevation-3"
            style="opacity: .8"
            onerror="this.src='<?php echo ASSETS_URL; ?>/adminlte/dist/img/AdminLTELogo.png'">
        <span class="brand-text font-weight-light">WA Monitor</span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Sidebar user panel (optional) -->
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="image">
                <img src="<?php echo $currentUser['avatar_url'] ?: ASSETS_URL . '/adminlte/dist/img/user2-160x160.jpg'; ?>"
                    class="img-circle elevation-2"
                    alt="User Image">
            </div>
            <div class="info">
                <a href="<?php echo BASE_URL; ?>/pages/users/profile.php" class="d-block">
                    <?php echo htmlspecialchars($currentUser['full_name']); ?>
                </a>
                <small class="text-muted">
                    <i class="fas fa-circle text-success" style="font-size: 8px;"></i>
                    <?php echo ucfirst($currentUser['role']); ?>
                </small>
            </div>
        </div>

        <!-- SidebarSearch Form -->
        <div class="form-inline">
            <div class="input-group" data-widget="sidebar-search">
                <input class="form-control form-control-sidebar" type="search" placeholder="Cari menu..." aria-label="Search">
                <div class="input-group-append">
                    <button class="btn btn-sidebar">
                        <i class="fas fa-search fa-fw"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Sidebar Menu -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">

                <!-- Dashboard -->
                <li class="nav-item">
                    <a href="<?php echo BASE_URL; ?>/pages/dashboard/"
                        class="nav-link <?php echo getMenuClass('/dashboard', $currentUri); ?>">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>Dashboard</p>
                    </a>
                </li>

                <!-- Device Management -->
                <?php if (hasPermission(PERM_MANAGE_DEVICES, $userRole) || hasPermission(PERM_VIEW_MONITORING, $userRole)): ?>
                    <li class="nav-item <?php echo (isMenuActive('/devices', $currentUri) || isMenuActive('/monitoring', $currentUri)) ? 'menu-open' : ''; ?>">
                        <a href="#" class="nav-link <?php echo (isMenuActive('/devices', $currentUri) || isMenuActive('/monitoring', $currentUri)) ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-mobile-alt"></i>
                            <p>
                                Device Management
                                <i class="right fas fa-angle-left"></i>
                                <?php if ($sidebarStats['total_devices'] > 0): ?>
                                    <span class="right badge badge-info"><?php echo $sidebarStats['total_devices']; ?></span>
                                <?php endif; ?>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <?php if (hasPermission(PERM_MANAGE_DEVICES, $userRole)): ?>
                                <li class="nav-item">
                                    <a href="<?php echo BASE_URL; ?>/pages/devices/"
                                        class="nav-link <?php echo getMenuClass('/devices/index', $currentUri); ?>">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>
                                            Daftar Devices
                                            <?php if ($sidebarStats['connected_devices'] > 0): ?>
                                                <small class="badge badge-success right"><?php echo $sidebarStats['connected_devices']; ?></small>
                                            <?php endif; ?>
                                        </p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="<?php echo BASE_URL; ?>/pages/devices/add.php"
                                        class="nav-link <?php echo getMenuClass('/devices/add', $currentUri); ?>">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Tambah Device</p>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php if (hasPermission(PERM_VIEW_MONITORING, $userRole)): ?>
                                <li class="nav-item">
                                    <a href="<?php echo BASE_URL; ?>/pages/monitoring/devices.php"
                                        class="nav-link <?php echo getMenuClass('/monitoring/devices', $currentUri); ?>">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>
                                            Monitoring
                                            <?php if ($sidebarStats['error_devices'] > 0): ?>
                                                <small class="badge badge-danger right"><?php echo $sidebarStats['error_devices']; ?></small>
                                            <?php endif; ?>
                                        </p>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </li>
                <?php endif; ?>

                <!-- API Tokens -->
                <?php if (hasPermission(PERM_MANAGE_API_TOKENS, $userRole)): ?>
                    <li class="nav-item">
                        <a href="<?php echo BASE_URL; ?>/pages/api-tokens/"
                            class="nav-link <?php echo getMenuClass('/api-tokens', $currentUri); ?>">
                            <i class="nav-icon fas fa-key"></i>
                            <p>API Tokens</p>
                        </a>
                    </li>
                <?php endif; ?>

                <!-- Messages -->
                <?php if (hasPermission(PERM_VIEW_MESSAGES, $userRole) || hasPermission(PERM_SEND_MESSAGES, $userRole)): ?>
                    <li class="nav-item <?php echo isMenuActive('/messages', $currentUri) ? 'menu-open' : ''; ?>">
                        <a href="#" class="nav-link <?php echo isMenuActive('/messages', $currentUri) ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-comments"></i>
                            <p>
                                Messages
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <?php if (hasPermission(PERM_SEND_MESSAGES, $userRole)): ?>
                                <li class="nav-item">
                                    <a href="<?php echo BASE_URL; ?>/pages/messages/send.php"
                                        class="nav-link <?php echo getMenuClass('/messages/send', $currentUri); ?>">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Kirim Pesan</p>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php if (hasPermission(PERM_VIEW_MESSAGES, $userRole)): ?>
                                <li class="nav-item">
                                    <a href="<?php echo BASE_URL; ?>/pages/messages/inbox.php"
                                        class="nav-link <?php echo getMenuClass('/messages/inbox', $currentUri); ?>">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Inbox</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="<?php echo BASE_URL; ?>/pages/messages/outbox.php"
                                        class="nav-link <?php echo getMenuClass('/messages/outbox', $currentUri); ?>">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Outbox</p>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </li>
                <?php endif; ?>

                <!-- Logs & Reports -->
                <?php if (hasPermission(PERM_VIEW_LOGS, $userRole)): ?>
                    <li class="nav-item <?php echo isMenuActive('/logs', $currentUri) ? 'menu-open' : ''; ?>">
                        <a href="#" class="nav-link <?php echo isMenuActive('/logs', $currentUri) ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-file-alt"></i>
                            <p>
                                Logs & Reports
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="<?php echo BASE_URL; ?>/pages/logs/api-logs.php"
                                    class="nav-link <?php echo getMenuClass('/logs/api-logs', $currentUri); ?>">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>API Logs</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo BASE_URL; ?>/pages/logs/message-logs.php"
                                    class="nav-link <?php echo getMenuClass('/logs/message-logs', $currentUri); ?>">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Message Logs</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo BASE_URL; ?>/pages/logs/webhook-logs.php"
                                    class="nav-link <?php echo getMenuClass('/logs/webhook-logs', $currentUri); ?>">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Webhook Logs</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo BASE_URL; ?>/pages/logs/system-logs.php"
                                    class="nav-link <?php echo getMenuClass('/logs/system-logs', $currentUri); ?>">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>System Logs</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>

                <!-- User Management (Admin Only) -->
                <?php if (hasPermission(PERM_MANAGE_USERS, $userRole)): ?>
                    <li class="nav-header">ADMINISTRATION</li>
                    <li class="nav-item <?php echo isMenuActive('/users', $currentUri) ? 'menu-open' : ''; ?>">
                        <a href="#" class="nav-link <?php echo isMenuActive('/users', $currentUri) ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-users"></i>
                            <p>
                                User Management
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="<?php echo BASE_URL; ?>/pages/users/"
                                    class="nav-link <?php echo getMenuClass('/users/index', $currentUri); ?>">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Daftar Users</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo BASE_URL; ?>/pages/users/add.php"
                                    class="nav-link <?php echo getMenuClass('/users/add', $currentUri); ?>">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Tambah User</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo BASE_URL; ?>/pages/users/roles.php"
                                    class="nav-link <?php echo getMenuClass('/users/roles', $currentUri); ?>">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Role Management</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>

                <!-- Settings (Admin Only) -->
                <?php if (hasPermission(PERM_MANAGE_SETTINGS, $userRole)): ?>
                    <li class="nav-item <?php echo isMenuActive('/settings', $currentUri) ? 'menu-open' : ''; ?>">
                        <a href="#" class="nav-link <?php echo isMenuActive('/settings', $currentUri) ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-cogs"></i>
                            <p>
                                Settings
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="<?php echo BASE_URL; ?>/pages/settings/system.php"
                                    class="nav-link <?php echo getMenuClass('/settings/system', $currentUri); ?>">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>System Settings</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo BASE_URL; ?>/pages/settings/api.php"
                                    class="nav-link <?php echo getMenuClass('/settings/api', $currentUri); ?>">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>API Settings</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo BASE_URL; ?>/pages/settings/webhook.php"
                                    class="nav-link <?php echo getMenuClass('/settings/webhook', $currentUri); ?>">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Webhook Settings</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo BASE_URL; ?>/pages/settings/backup.php"
                                    class="nav-link <?php echo getMenuClass('/settings/backup', $currentUri); ?>">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Backup & Restore</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>

                <!-- Monitoring & Alerts -->
                <?php if (hasPermission(PERM_VIEW_MONITORING, $userRole)): ?>
                    <li class="nav-item <?php echo isMenuActive('/monitoring', $currentUri) ? 'menu-open' : ''; ?>">
                        <a href="#" class="nav-link <?php echo isMenuActive('/monitoring', $currentUri) ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-chart-line"></i>
                            <p>
                                Monitoring
                                <i class="right fas fa-angle-left"></i>
                                <?php if ($sidebarStats['banned_devices'] > 0): ?>
                                    <span class="right badge badge-danger"><?php echo $sidebarStats['banned_devices']; ?></span>
                                <?php endif; ?>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="<?php echo BASE_URL; ?>/pages/monitoring/performance.php"
                                    class="nav-link <?php echo getMenuClass('/monitoring/performance', $currentUri); ?>">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Performance</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo BASE_URL; ?>/pages/monitoring/alerts.php"
                                    class="nav-link <?php echo getMenuClass('/monitoring/alerts', $currentUri); ?>">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>
                                        Alerts
                                        <?php if ($sidebarStats['banned_devices'] > 0): ?>
                                            <small class="badge badge-danger right"><?php echo $sidebarStats['banned_devices']; ?></small>
                                        <?php endif; ?>
                                    </p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo BASE_URL; ?>/pages/monitoring/statistics.php"
                                    class="nav-link <?php echo getMenuClass('/monitoring/statistics', $currentUri); ?>">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Statistics</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>

                <!-- Quick Actions -->
                <li class="nav-header">QUICK ACTIONS</li>

                <?php if (hasPermission(PERM_MANAGE_DEVICES, $userRole)): ?>
                    <li class="nav-item">
                        <a href="<?php echo BASE_URL; ?>/pages/devices/add.php" class="nav-link">
                            <i class="nav-icon fas fa-plus text-success"></i>
                            <p>Tambah Device</p>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if (hasPermission(PERM_SEND_MESSAGES, $userRole)): ?>
                    <li class="nav-item">
                        <a href="<?php echo BASE_URL; ?>/pages/messages/send.php" class="nav-link">
                            <i class="nav-icon fas fa-paper-plane text-primary"></i>
                            <p>Kirim Pesan</p>
                        </a>
                    </li>
                <?php endif; ?>

                <li class="nav-item">
                    <a href="<?php echo BASE_URL; ?>/pages/users/profile.php" class="nav-link">
                        <i class="nav-icon fas fa-user text-info"></i>
                        <p>Profile Saya</p>
                    </a>
                </li>

                <!-- Logout -->
                <li class="nav-item">
                    <a href="<?php echo BASE_URL; ?>/pages/auth/logout.php" class="nav-link">
                        <i class="nav-icon fas fa-sign-out-alt text-danger"></i>
                        <p>Logout</p>
                    </a>
                </li>

                <!-- Help & Support -->
                <li class="nav-header">HELP & SUPPORT</li>
                <li class="nav-item">
                    <a href="#" class="nav-link" onclick="showHelpModal()">
                        <i class="nav-icon fas fa-question-circle text-warning"></i>
                        <p>Help</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link" onclick="showAboutModal()">
                        <i class="nav-icon fas fa-info-circle text-info"></i>
                        <p>About</p>
                    </a>
                </li>

            </ul>
        </nav>
        <!-- /.sidebar-menu -->

        <!-- Sidebar Footer -->
        <div class="sidebar-footer mt-3 p-3">
            <div class="text-center">
                <small class="text-muted">
                    <strong>WhatsApp Monitor</strong><br>
                    Version 1.0.0<br>
                    <i class="fas fa-circle text-success" style="font-size: 8px;"></i> System Online
                </small>
            </div>
        </div>
    </div>
    <!-- /.sidebar -->
</aside>

<!-- Help Modal -->
<div class="modal fade" id="helpModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">
                    <i class="fas fa-question-circle"></i> Help & Documentation
                </h4>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5><i class="fas fa-mobile-alt text-primary"></i> Device Management</h5>
                        <ul>
                            <li>Tambah device baru melalui menu Device Management</li>
                            <li>Scan QR code untuk pairing dengan WhatsApp</li>
                            <li>Monitor status device secara real-time</li>
                            <li>Kelola multiple device dalam satu dashboard</li>
                        </ul>

                        <h5><i class="fas fa-key text-warning"></i> API Tokens</h5>
                        <ul>
                            <li>Setiap device memiliki API token unik</li>
                            <li>Gunakan token untuk akses API external</li>
                            <li>Monitor usage dan regenerate jika diperlukan</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h5><i class="fas fa-comments text-success"></i> Messaging</h5>
                        <ul>
                            <li>Kirim pesan teks dan media</li>
                            <li>Monitor pesan masuk dan keluar</li>
                            <li>View message logs dan statistics</li>
                            <li>Bulk messaging untuk multiple contact</li>
                        </ul>

                        <h5><i class="fas fa-chart-line text-info"></i> Monitoring</h5>
                        <ul>
                            <li>Real-time monitoring device status</li>
                            <li>Performance metrics dan analytics</li>
                            <li>Alert system untuk device issues</li>
                            <li>Comprehensive logging system</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <a href="#" class="btn btn-primary">View Full Documentation</a>
            </div>
        </div>
    </div>
</div>

<!-- About Modal -->
<div class="modal fade" id="aboutModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">
                    <i class="fas fa-info-circle"></i> About WhatsApp Monitor
                </h4>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body text-center">
                <img src="<?php echo ASSETS_URL; ?>/custom/images/logo.png"
                    alt="WhatsApp Monitor"
                    class="img-fluid mb-3"
                    style="max-width: 100px;"
                    onerror="this.src='<?php echo ASSETS_URL; ?>/adminlte/dist/img/AdminLTELogo.png'">

                <h4>WhatsApp Monitor</h4>
                <p class="text-muted">Professional WhatsApp Management System</p>

                <hr>

                <div class="row">
                    <div class="col-sm-6">
                        <strong>Version:</strong><br>
                        <span class="text-muted">1.0.0</span>
                    </div>
                    <div class="col-sm-6">
                        <strong>Release Date:</strong><br>
                        <span class="text-muted">January 2025</span>
                    </div>
                </div>

                <hr>

                <p class="text-sm">
                    <strong>Developer:</strong> WhatsApp Monitor Team<br>
                    <strong>Framework:</strong> PHP 8.x + AdminLTE 3.x<br>
                    <strong>Database:</strong> MySQL 8.x<br>
                    <strong>Backend:</strong> Node.js + Baileys
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    function showHelpModal() {
        $('#helpModal').modal('show');
    }

    function showAboutModal() {
        $('#aboutModal').modal('show');
    }

    // Auto-collapse sidebar on mobile after navigation
    $(document).on('click', '.nav-link', function() {
        if ($(window).width() < 992) {
            $('body').removeClass('sidebar-open');
        }
    });

    // Update sidebar stats every 60 seconds
    setInterval(function() {
        updateSidebarStats();
    }, 60000);

    function updateSidebarStats() {
        $.get('<?php echo BASE_URL; ?>/api/devices.php?action=sidebar_stats')
            .done(function(response) {
                if (response.success) {
                    // Update device count badge
                    if (response.data.total_devices > 0) {
                        $('.nav-sidebar').find('.badge-info').text(response.data.total_devices);
                    }

                    // Update connected devices badge
                    if (response.data.connected_devices > 0) {
                        $('.nav-sidebar').find('.badge-success').text(response.data.connected_devices);
                    }

                    // Update error devices badge
                    if (response.data.error_devices > 0) {
                        $('.nav-sidebar').find('.badge-danger').text(response.data.error_devices);
                    }
                }
            })
            .fail(function() {
                console.log('Failed to update sidebar stats');
            });
    }
</script>