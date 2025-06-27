<?php

/**
 * Message Logs Viewer
 * View WhatsApp message history
 */

require_once '../../includes/session.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/Device.php';

// Check permission
requireLogin();
if (!hasPermission('view_logs')) {
    redirect('/index.php', 'You do not have permission to view logs');
}

$pageTitle = 'Message Logs';
$db = Database::getInstance()->getConnection();
$deviceObj = new Device();

// Get filter parameters
$filter = [
    'date_from' => $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days')),
    'date_to' => $_GET['date_to'] ?? date('Y-m-d'),
    'device_id' => $_GET['device_id'] ?? '',
    'direction' => $_GET['direction'] ?? '',
    'type' => $_GET['type'] ?? '',
    'status' => $_GET['status'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// Get user's devices for filter
$userDevices = [];
if (hasRole('admin')) {
    $stmt = $db->query("SELECT id, device_name, phone_number FROM devices ORDER BY device_name");
} else {
    $stmt = $db->prepare("SELECT id, device_name, phone_number FROM devices WHERE user_id = ? ORDER BY device_name");
    $stmt->execute([$_SESSION['user_id']]);
}
$userDevices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build query
$query = "
    SELECT 
        m.*,
        d.device_name,
        d.phone_number as device_phone
    FROM message_logs m
    JOIN devices d ON m.device_id = d.id
    WHERE 1=1
";

$params = [];

// Apply filters
if (!hasRole('admin')) {
    $query .= " AND d.user_id = ?";
    $params[] = $_SESSION['user_id'];
}

if ($filter['date_from']) {
    $query .= " AND DATE(m.created_at) >= ?";
    $params[] = $filter['date_from'];
}

if ($filter['date_to']) {
    $query .= " AND DATE(m.created_at) <= ?";
    $params[] = $filter['date_to'];
}

if ($filter['device_id']) {
    $query .= " AND m.device_id = ?";
    $params[] = $filter['device_id'];
}

if ($filter['direction']) {
    $query .= " AND m.direction = ?";
    $params[] = $filter['direction'];
}

if ($filter['type']) {
    $query .= " AND m.message_type = ?";
    $params[] = $filter['type'];
}

if ($filter['status']) {
    $query .= " AND m.status = ?";
    $params[] = $filter['status'];
}

if ($filter['search']) {
    $query .= " AND (m.message_content LIKE ? OR m.from_number LIKE ? OR m.to_number LIKE ? OR m.chat_id LIKE ?)";
    $params[] = '%' . $filter['search'] . '%';
    $params[] = '%' . $filter['search'] . '%';
    $params[] = '%' . $filter['search'] . '%';
    $params[] = '%' . $filter['search'] . '%';
}

$query .= " ORDER BY m.created_at DESC LIMIT 500";

// Execute query
$stmt = $db->prepare($query);
$stmt->execute($params);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$statsQuery = "
    SELECT 
        COUNT(*) as total_messages,
        COUNT(CASE WHEN direction = 'incoming' THEN 1 END) as incoming_messages,
        COUNT(CASE WHEN direction = 'outgoing' THEN 1 END) as outgoing_messages,
        COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_messages,
        COUNT(DISTINCT chat_id) as unique_chats
    FROM message_logs m
    JOIN devices d ON m.device_id = d.id
    WHERE DATE(m.created_at) BETWEEN ? AND ?
";

$statsParams = [$filter['date_from'], $filter['date_to']];
if (!hasRole('admin')) {
    $statsQuery .= " AND d.user_id = ?";
    $statsParams[] = $_SESSION['user_id'];
}

$stmt = $db->prepare($statsQuery);
$stmt->execute($statsParams);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

require_once '../../includes/header.php';
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Message Logs</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="/index.php">Home</a></li>
                    <li class="breadcrumb-item">Logs</li>
                    <li class="breadcrumb-item active">Message Logs</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <!-- Statistics -->
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3><?php echo number_format($stats['total_messages']); ?></h3>
                        <p>Total Messages</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-comments"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3><?php echo number_format($stats['incoming_messages']); ?></h3>
                        <p>Incoming</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-arrow-down"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-6">
                <div class="small-box bg-primary">
                    <div class="inner">
                        <h3><?php echo number_format($stats['outgoing_messages']); ?></h3>
                        <p>Outgoing</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-arrow-up"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3><?php echo number_format($stats['failed_messages']); ?></h3>
                        <p>Failed</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3><?php echo number_format($stats['unique_chats']); ?></h3>
                        <p>Unique Chats</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Form -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Filter Messages</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <form method="get" action="">
                    <div class="row">
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Date From</label>
                                <input type="date" name="date_from" class="form-control"
                                    value="<?php echo htmlspecialchars($filter['date_from']); ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Date To</label>
                                <input type="date" name="date_to" class="form-control"
                                    value="<?php echo htmlspecialchars($filter['date_to']); ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Device</label>
                                <select name="device_id" class="form-control select2">
                                    <option value="">All Devices</option>
                                    <?php foreach ($userDevices as $device): ?>
                                        <option value="<?php echo $device['id']; ?>"
                                            <?php echo $filter['device_id'] == $device['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($device['device_name']); ?>
                                            (<?php echo htmlspecialchars($device['phone_number']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Direction</label>
                                <select name="direction" class="form-control">
                                    <option value="">All</option>
                                    <option value="incoming" <?php echo $filter['direction'] === 'incoming' ? 'selected' : ''; ?>>Incoming</option>
                                    <option value="outgoing" <?php echo $filter['direction'] === 'outgoing' ? 'selected' : ''; ?>>Outgoing</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Type</label>
                                <select name="type" class="form-control">
                                    <option value="">All Types</option>
                                    <option value="text" <?php echo $filter['type'] === 'text' ? 'selected' : ''; ?>>Text</option>
                                    <option value="image" <?php echo $filter['type'] === 'image' ? 'selected' : ''; ?>>Image</option>
                                    <option value="video" <?php echo $filter['type'] === 'video' ? 'selected' : ''; ?>>Video</option>
                                    <option value="audio" <?php echo $filter['type'] === 'audio' ? 'selected' : ''; ?>>Audio</option>
                                    <option value="document" <?php echo $filter['type'] === 'document' ? 'selected' : ''; ?>>Document</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" class="form-control">
                                    <option value="">All Status</option>
                                    <option value="sent" <?php echo $filter['status'] === 'sent' ? 'selected' : ''; ?>>Sent</option>
                                    <option value="delivered" <?php echo $filter['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                    <option value="read" <?php echo $filter['status'] === 'read' ? 'selected' : ''; ?>>Read</option>
                                    <option value="failed" <?php echo $filter['status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Search</label>
                                <input type="text" name="search" class="form-control"
                                    placeholder="Search messages, phone numbers..."
                                    value="<?php echo htmlspecialchars($filter['search']); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <button type="submit" class="btn btn-primary mt-4">
                                <i class="fas fa-filter"></i> Apply Filter
                            </button>
                            <a href="message-logs.php" class="btn btn-default mt-4">
                                <i class="fas fa-undo"></i> Reset
                            </a>
                            <button type="button" class="btn btn-success float-right mt-4" onclick="exportMessages()">
                                <i class="fas fa-download"></i> Export
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Messages Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Message History</h3>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th width="150">Timestamp</th>
                            <th>Device</th>
                            <th width="60">Direction</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Type</th>
                            <th>Message</th>
                            <th width="80">Status</th>
                            <th width="100">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($messages as $msg): ?>
                            <tr>
                                <td>
                                    <small>
                                        <?php echo date('Y-m-d H:i:s', strtotime($msg['created_at'])); ?><br>
                                        <span class="text-muted">
                                            <?php echo date('H:i:s', $msg['timestamp'] / 1000); ?> WA
                                        </span>
                                    </small>
                                </td>
                                <td>
                                    <small>
                                        <?php echo htmlspecialchars($msg['device_name']); ?><br>
                                        <span class="text-muted"><?php echo htmlspecialchars($msg['device_phone']); ?></span>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($msg['direction'] === 'incoming'): ?>
                                        <span class="badge badge-success">
                                            <i class="fas fa-arrow-down"></i> In
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-primary">
                                            <i class="fas fa-arrow-up"></i> Out
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?php echo formatPhoneNumber($msg['from_number']); ?></small>
                                </td>
                                <td>
                                    <small><?php echo formatPhoneNumber($msg['to_number']); ?></small>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo getMessageTypeBadge($msg['message_type']); ?>">
                                        <?php echo ucfirst($msg['message_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($msg['message_type'] === 'text'): ?>
                                        <span class="message-preview" data-full-message="<?php echo htmlspecialchars($msg['message_content']); ?>">
                                            <?php echo htmlspecialchars(substr($msg['message_content'], 0, 50)); ?>
                                            <?php if (strlen($msg['message_content']) > 50): ?>...<?php endif; ?>
                                        </span>
                                    <?php elseif ($msg['media_url']): ?>
                                        <a href="<?php echo htmlspecialchars($msg['media_url']); ?>" target="_blank">
                                            <i class="fas fa-paperclip"></i> View Media
                                        </a>
                                    <?php else: ?>
                                        <small class="text-muted">[<?php echo ucfirst($msg['message_type']); ?>]</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo getMessageStatusBadge($msg['status']); ?>">
                                        <?php echo ucfirst($msg['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-xs btn-info" onclick="viewMessage(<?php echo $msg['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($msg['direction'] === 'incoming'): ?>
                                            <button class="btn btn-xs btn-success" onclick="replyToMessage('<?php echo htmlspecialchars($msg['from_number']); ?>', <?php echo $msg['device_id']; ?>)">
                                                <i class="fas fa-reply"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($messages)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted py-3">
                                    No messages found for the selected criteria
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<!-- Message Details Modal -->
<div class="modal fade" id="messageModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Message Details</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="messageDetails">
                <div class="text-center">
                    <i class="fas fa-spinner fa-spin"></i> Loading...
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reply Modal -->
<div class="modal fade" id="replyModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Reply to Message</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="replyForm">
                <div class="modal-body">
                    <input type="hidden" id="replyDeviceId" name="device_id">
                    <input type="hidden" id="replyToNumber" name="to_number">

                    <div class="form-group">
                        <label>To:</label>
                        <input type="text" class="form-control" id="replyToDisplay" readonly>
                    </div>

                    <div class="form-group">
                        <label>Message:</label>
                        <textarea class="form-control" name="message" rows="4" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Send Reply
                    </button>
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Initialize Select2
    $(document).ready(function() {
        $('.select2').select2();

        // Handle message preview click
        $('.message-preview').click(function() {
            const fullMessage = $(this).data('full-message');
            alert(fullMessage);
        });
    });

    // View message details
    function viewMessage(messageId) {
        $('#messageModal').modal('show');
        $('#messageDetails').html('<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</div>');

        $.get('ajax/get-message-details.php', {
            id: messageId
        }, function(response) {
            if (response.success) {
                let html = `
                <table class="table table-sm">
                    <tr><th width="30%">Message ID:</th><td>${response.data.message_id || 'N/A'}</td></tr>
                    <tr><th>Chat ID:</th><td>${response.data.chat_id}</td></tr>
                    <tr><th>Direction:</th><td>${response.data.direction}</td></tr>
                    <tr><th>From:</th><td>${response.data.from_number}</td></tr>
                    <tr><th>To:</th><td>${response.data.to_number}</td></tr>
                    <tr><th>Type:</th><td>${response.data.message_type}</td></tr>
                    <tr><th>Status:</th><td>${response.data.status}</td></tr>
                    <tr><th>WA Timestamp:</th><td>${new Date(response.data.timestamp).toLocaleString()}</td></tr>
                    <tr><th>Received At:</th><td>${response.data.created_at}</td></tr>
                </table>
            `;

                if (response.data.message_content) {
                    html += `<div class="mt-3"><strong>Message:</strong><br><pre class="bg-light p-2">${response.data.message_content}</pre></div>`;
                }

                if (response.data.media_url) {
                    html += `<div class="mt-3"><strong>Media:</strong><br><a href="${response.data.media_url}" target="_blank" class="btn btn-sm btn-info"><i class="fas fa-download"></i> View/Download Media</a></div>`;
                }

                $('#messageDetails').html(html);
            } else {
                $('#messageDetails').html('<div class="alert alert-danger">Failed to load message details</div>');
            }
        });
    }

    // Reply to message
    function replyToMessage(toNumber, deviceId) {
        $('#replyDeviceId').val(deviceId);
        $('#replyToNumber').val(toNumber);
        $('#replyToDisplay').val(formatPhoneNumber(toNumber));
        $('#replyModal').modal('show');
    }

    // Handle reply form submission
    $('#replyForm').submit(function(e) {
        e.preventDefault();

        const btn = $(this).find('button[type="submit"]');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Sending...');

        $.post('/api/messages.php', {
            action: 'send',
            device_id: $('#replyDeviceId').val(),
            to: $('#replyToNumber').val(),
            message: $(this).find('[name="message"]').val()
        }, function(response) {
            if (response.success) {
                toastr.success('Message sent successfully!');
                $('#replyModal').modal('hide');
                $('#replyForm')[0].reset();
                setTimeout(() => location.reload(), 1500);
            } else {
                toastr.error(response.message || 'Failed to send message');
            }
        }).always(function() {
            btn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Send Reply');
        });
    });

    // Export messages
    function exportMessages() {
        let params = new URLSearchParams(window.location.search);
        params.append('export', '1');
        window.location.href = 'export-messages.php?' + params.toString();
    }

    // Format phone number helper
    function formatPhoneNumber(number) {
        return number.replace('@s.whatsapp.net', '').replace('@g.us', ' (Group)');
    }

    // Helper functions
    <?php
    function formatPhoneNumber($number)
    {
        return str_replace(['@s.whatsapp.net', '@g.us'], ['', ' (Group)'], $number);
    }

    function getMessageTypeBadge($type)
    {
        $badges = [
            'text' => 'secondary',
            'image' => 'info',
            'video' => 'primary',
            'audio' => 'warning',
            'document' => 'dark',
            'sticker' => 'success',
            'location' => 'danger',
            'contact' => 'light'
        ];
        return $badges[$type] ?? 'secondary';
    }

    function getMessageStatusBadge($status)
    {
        $badges = [
            'sent' => 'primary',
            'delivered' => 'info',
            'read' => 'success',
            'failed' => 'danger'
        ];
        return $badges[$status] ?? 'secondary';
    }
    ?>
</script>

<?php require_once '../../includes/footer.php'; ?>