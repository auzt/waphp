/**
 * ===============================================================================
 * DEVICES.JS - WhatsApp Monitor Device Management
 * ===============================================================================
 * JavaScript untuk manajemen devices di dashboard AdminLTE
 * - Add/Edit/Delete devices
 * - Real-time monitoring
 * - QR Code display
 * - Device status management
 * - API communication dengan Node.js backend
 * ===============================================================================
 */

class DeviceManager {
    constructor() {
        this.apiBaseUrl = '/api';
        this.devices = new Map();
        this.statusSocket = null;
        this.refreshInterval = null;
        this.qrInterval = null;

        // Status mapping untuk UI
        this.statusConfig = {
            'connecting': {
                class: 'warning',
                icon: 'fa-spinner fa-spin',
                text: 'Connecting...',
                color: '#ffc107'
            },
            'connected': {
                class: 'success',
                icon: 'fa-check-circle',
                text: 'Connected',
                color: '#28a745'
            },
            'disconnected': {
                class: 'secondary',
                icon: 'fa-times-circle',
                text: 'Disconnected',
                color: '#6c757d'
            },
            'pairing': {
                class: 'info',
                icon: 'fa-qrcode',
                text: 'Scan QR Code',
                color: '#17a2b8'
            },
            'banned': {
                class: 'danger',
                icon: 'fa-ban',
                text: 'Banned',
                color: '#dc3545'
            },
            'error': {
                class: 'danger',
                icon: 'fa-exclamation-triangle',
                text: 'Error',
                color: '#dc3545'
            },
            'timeout': {
                class: 'warning',
                icon: 'fa-clock',
                text: 'Timeout',
                color: '#ffc107'
            },
            'auth_failure': {
                class: 'danger',
                icon: 'fa-key',
                text: 'Auth Failed',
                color: '#dc3545'
            },
            'logout': {
                class: 'secondary',
                icon: 'fa-sign-out-alt',
                text: 'Logged Out',
                color: '#6c757d'
            }
        };

        this.init();
    }

    /**
     * Initialize device manager
     */
    init() {
        this.bindEvents();
        this.loadDevices();
        this.startRealTimeUpdates();

        // Auto refresh setiap 30 detik
        this.refreshInterval = setInterval(() => {
            this.loadDevices();
        }, 30000);

        console.log('DeviceManager initialized');
    }

    /**
     * Bind DOM events
     */
    bindEvents() {
        // Add device button
        $(document).on('click', '#btn-add-device', () => {
            this.showAddDeviceModal();
        });

        // Edit device
        $(document).on('click', '.btn-edit-device', (e) => {
            const deviceId = $(e.target).closest('tr').data('device-id');
            this.showEditDeviceModal(deviceId);
        });

        // Delete device
        $(document).on('click', '.btn-delete-device', (e) => {
            const deviceId = $(e.target).closest('tr').data('device-id');
            this.deleteDevice(deviceId);
        });

        // View device details
        $(document).on('click', '.btn-view-device', (e) => {
            const deviceId = $(e.target).closest('tr').data('device-id');
            this.showDeviceDetails(deviceId);
        });

        // Connect/Disconnect device
        $(document).on('click', '.btn-connect-device', (e) => {
            const deviceId = $(e.target).closest('tr').data('device-id');
            this.connectDevice(deviceId);
        });

        $(document).on('click', '.btn-disconnect-device', (e) => {
            const deviceId = $(e.target).closest('tr').data('device-id');
            this.disconnectDevice(deviceId);
        });

        // Show QR Code
        $(document).on('click', '.btn-show-qr', (e) => {
            const deviceId = $(e.target).closest('tr').data('device-id');
            this.showQRCode(deviceId);
        });

        // Restart device
        $(document).on('click', '.btn-restart-device', (e) => {
            const deviceId = $(e.target).closest('tr').data('device-id');
            this.restartDevice(deviceId);
        });

        // Refresh devices
        $(document).on('click', '#btn-refresh-devices', () => {
            this.loadDevices();
        });

        // Form submissions
        $(document).on('submit', '#form-add-device', (e) => {
            e.preventDefault();
            this.handleAddDevice();
        });

        $(document).on('submit', '#form-edit-device', (e) => {
            e.preventDefault();
            this.handleEditDevice();
        });

        // Phone number formatting
        $(document).on('input', '.phone-input', (e) => {
            this.formatPhoneNumber(e.target);
        });

        // Auto-validate phone numbers
        $(document).on('blur', '.phone-input', (e) => {
            this.validatePhoneNumber(e.target);
        });
    }

    /**
     * Load devices from server
     */
    async loadDevices() {
        try {
            this.showLoading('#devices-table-container', 'Loading devices...');

            const response = await this.apiRequest('GET', '/devices.php');

            if (response.success) {
                this.devices.clear();
                response.data.forEach(device => {
                    this.devices.set(device.id, device);
                });

                this.renderDevicesTable(response.data);
                this.updateDevicesCount(response.data.length);
                this.updateStatusSummary(response.data);
            } else {
                this.showError('Failed to load devices: ' + response.message);
            }
        } catch (error) {
            console.error('Error loading devices:', error);
            this.showError('Error loading devices. Please refresh the page.');
        } finally {
            this.hideLoading('#devices-table-container');
        }
    }

    /**
     * Render devices table
     */
    renderDevicesTable(devices) {
        const tbody = $('#devices-table tbody');
        tbody.empty();

        if (devices.length === 0) {
            tbody.append(`
                <tr>
                    <td colspan="8" class="text-center">
                        <div class="py-4">
                            <i class="fas fa-mobile-alt fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No devices found. <a href="#" id="btn-add-first-device">Add your first device</a></p>
                        </div>
                    </td>
                </tr>
            `);
            return;
        }

        devices.forEach(device => {
            const status = this.statusConfig[device.status] || this.statusConfig.error;
            const lastSeen = device.last_seen ? this.formatDateTime(device.last_seen) : 'Never';
            const onlineIndicator = device.is_online ?
                '<i class="fas fa-circle text-success" title="Online"></i>' :
                '<i class="fas fa-circle text-secondary" title="Offline"></i>';

            const row = `
                <tr data-device-id="${device.id}" class="device-row">
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="device-avatar me-3">
                                <i class="fas fa-mobile-alt fa-2x text-primary"></i>
                            </div>
                            <div>
                                <div class="fw-bold">${this.escapeHtml(device.device_name)}</div>
                                <small class="text-muted">${this.escapeHtml(device.phone_number)}</small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="d-flex align-items-center">
                            ${onlineIndicator}
                            <span class="badge bg-${status.class} ms-2">
                                <i class="fas ${status.icon} me-1"></i>
                                ${status.text}
                            </span>
                        </div>
                    </td>
                    <td>
                        <div class="text-truncate" style="max-width: 200px;" title="${this.escapeHtml(device.whatsapp_name || 'Not set')}">
                            ${this.escapeHtml(device.whatsapp_name || 'Not set')}
                        </div>
                    </td>
                    <td>
                        <small class="text-muted">${lastSeen}</small>
                    </td>
                    <td>
                        <span class="badge bg-info">${device.messages_today || 0}</span>
                    </td>
                    <td>
                        <small class="text-muted">${this.escapeHtml(device.owner || 'Unknown')}</small>
                    </td>
                    <td>
                        <div class="btn-group" role="group">
                            ${this.renderDeviceActions(device)}
                        </div>
                    </td>
                    <td>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item btn-view-device" href="#">
                                    <i class="fas fa-eye me-2"></i>View Details
                                </a></li>
                                <li><a class="dropdown-item btn-edit-device" href="#">
                                    <i class="fas fa-edit me-2"></i>Edit
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item btn-restart-device" href="#">
                                    <i class="fas fa-redo me-2"></i>Restart
                                </a></li>
                                <li><a class="dropdown-item text-danger btn-delete-device" href="#">
                                    <i class="fas fa-trash me-2"></i>Delete
                                </a></li>
                            </ul>
                        </div>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });

        // Initialize tooltips
        $('[data-bs-toggle="tooltip"]').tooltip();
    }

    /**
     * Render device action buttons based on status
     */
    renderDeviceActions(device) {
        const actions = [];

        switch (device.status) {
            case 'disconnected':
            case 'error':
            case 'timeout':
            case 'auth_failure':
                actions.push(`
                    <button class="btn btn-sm btn-success btn-connect-device" title="Connect">
                        <i class="fas fa-play"></i>
                    </button>
                `);
                break;

            case 'connected':
                actions.push(`
                    <button class="btn btn-sm btn-warning btn-disconnect-device" title="Disconnect">
                        <i class="fas fa-stop"></i>
                    </button>
                `);
                break;

            case 'connecting':
                actions.push(`
                    <button class="btn btn-sm btn-secondary" disabled title="Connecting...">
                        <i class="fas fa-spinner fa-spin"></i>
                    </button>
                `);
                break;

            case 'pairing':
                actions.push(`
                    <button class="btn btn-sm btn-info btn-show-qr" title="Show QR Code">
                        <i class="fas fa-qrcode"></i>
                    </button>
                `);
                break;

            case 'banned':
                actions.push(`
                    <button class="btn btn-sm btn-danger" disabled title="Device Banned">
                        <i class="fas fa-ban"></i>
                    </button>
                `);
                break;

            default:
                actions.push(`
                    <button class="btn btn-sm btn-primary btn-connect-device" title="Connect">
                        <i class="fas fa-play"></i>
                    </button>
                `);
        }

        return actions.join('');
    }

    /**
     * Show add device modal
     */
    showAddDeviceModal() {
        const modal = $('#modal-add-device');

        // Reset form
        modal.find('form')[0].reset();
        modal.find('.is-invalid').removeClass('is-invalid');
        modal.find('.invalid-feedback').remove();

        // Generate device ID
        const deviceId = 'device_' + Date.now();
        modal.find('#device_id').val(deviceId);

        modal.modal('show');
    }

    /**
     * Show edit device modal
     */
    async showEditDeviceModal(deviceId) {
        try {
            const device = this.devices.get(parseInt(deviceId));
            if (!device) {
                this.showError('Device not found');
                return;
            }

            const modal = $('#modal-edit-device');

            // Populate form
            modal.find('#edit_device_id').val(device.id);
            modal.find('#edit_device_name').val(device.device_name);
            modal.find('#edit_phone_number').val(device.phone_number);
            modal.find('#edit_device_identifier').val(device.device_id);

            // Reset validation
            modal.find('.is-invalid').removeClass('is-invalid');
            modal.find('.invalid-feedback').remove();

            modal.modal('show');
        } catch (error) {
            console.error('Error showing edit modal:', error);
            this.showError('Error loading device data');
        }
    }

    /**
     * Handle add device form submission
     */
    async handleAddDevice() {
        try {
            const form = document.getElementById('form-add-device');
            const formData = new FormData(form);

            const data = {
                device_name: formData.get('device_name'),
                phone_number: formData.get('phone_number'),
                device_id: formData.get('device_id')
            };

            // Validate
            if (!this.validateDeviceForm(data, 'add')) {
                return;
            }

            // Show loading
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding...';
            submitBtn.disabled = true;

            const response = await this.apiRequest('POST', '/devices.php', data);

            if (response.success) {
                $('#modal-add-device').modal('hide');
                this.showSuccess('Device added successfully');
                this.loadDevices();
            } else {
                this.showValidationErrors(response.errors || {}, 'add');
                this.showError(response.message || 'Failed to add device');
            }
        } catch (error) {
            console.error('Error adding device:', error);
            this.showError('Error adding device. Please try again.');
        } finally {
            // Reset button
            const submitBtn = document.querySelector('#form-add-device button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-plus me-2"></i>Add Device';
            submitBtn.disabled = false;
        }
    }

    /**
     * Handle edit device form submission
     */
    async handleEditDevice() {
        try {
            const form = document.getElementById('form-edit-device');
            const formData = new FormData(form);

            const data = {
                id: formData.get('id'),
                device_name: formData.get('device_name'),
                phone_number: formData.get('phone_number'),
                device_id: formData.get('device_id')
            };

            // Validate
            if (!this.validateDeviceForm(data, 'edit')) {
                return;
            }

            // Show loading
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Updating...';
            submitBtn.disabled = true;

            const response = await this.apiRequest('PUT', `/devices.php?id=${data.id}`, data);

            if (response.success) {
                $('#modal-edit-device').modal('hide');
                this.showSuccess('Device updated successfully');
                this.loadDevices();
            } else {
                this.showValidationErrors(response.errors || {}, 'edit');
                this.showError(response.message || 'Failed to update device');
            }
        } catch (error) {
            console.error('Error updating device:', error);
            this.showError('Error updating device. Please try again.');
        } finally {
            // Reset button
            const submitBtn = document.querySelector('#form-edit-device button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-save me-2"></i>Update Device';
            submitBtn.disabled = false;
        }
    }

    /**
     * Delete device
     */
    async deleteDevice(deviceId) {
        try {
            const device = this.devices.get(parseInt(deviceId));
            if (!device) {
                this.showError('Device not found');
                return;
            }

            const result = await Swal.fire({
                title: 'Delete Device?',
                html: `
                    <p>Are you sure you want to delete the device:</p>
                    <strong>${this.escapeHtml(device.device_name)}</strong><br>
                    <small class="text-muted">${this.escapeHtml(device.phone_number)}</small><br><br>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        This action cannot be undone and will also delete all associated data.
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-trash me-2"></i>Yes, Delete',
                cancelButtonText: 'Cancel',
                reverseButtons: true
            });

            if (result.isConfirmed) {
                const response = await this.apiRequest('DELETE', `/devices.php?id=${deviceId}`);

                if (response.success) {
                    this.showSuccess('Device deleted successfully');
                    this.loadDevices();
                } else {
                    this.showError(response.message || 'Failed to delete device');
                }
            }
        } catch (error) {
            console.error('Error deleting device:', error);
            this.showError('Error deleting device. Please try again.');
        }
    }

    /**
     * Connect device to WhatsApp
     */
    async connectDevice(deviceId) {
        try {
            const device = this.devices.get(parseInt(deviceId));
            if (!device) {
                this.showError('Device not found');
                return;
            }

            this.updateDeviceRowStatus(deviceId, 'connecting');

            const response = await this.apiRequest('POST', `/devices.php?action=connect&id=${deviceId}`);

            if (response.success) {
                this.showSuccess('Device connection initiated');
                // Status akan diupdate melalui real-time updates
                setTimeout(() => this.loadDevices(), 2000);
            } else {
                this.showError(response.message || 'Failed to connect device');
                this.loadDevices(); // Reload untuk reset status
            }
        } catch (error) {
            console.error('Error connecting device:', error);
            this.showError('Error connecting device. Please try again.');
            this.loadDevices();
        }
    }

    /**
     * Disconnect device from WhatsApp
     */
    async disconnectDevice(deviceId) {
        try {
            const device = this.devices.get(parseInt(deviceId));
            if (!device) {
                this.showError('Device not found');
                return;
            }

            const result = await Swal.fire({
                title: 'Disconnect Device?',
                text: `Disconnect ${device.device_name} from WhatsApp?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#ffc107',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Disconnect',
                cancelButtonText: 'Cancel'
            });

            if (result.isConfirmed) {
                this.updateDeviceRowStatus(deviceId, 'disconnected');

                const response = await this.apiRequest('POST', `/devices.php?action=disconnect&id=${deviceId}`);

                if (response.success) {
                    this.showSuccess('Device disconnected successfully');
                    setTimeout(() => this.loadDevices(), 1000);
                } else {
                    this.showError(response.message || 'Failed to disconnect device');
                    this.loadDevices();
                }
            }
        } catch (error) {
            console.error('Error disconnecting device:', error);
            this.showError('Error disconnecting device. Please try again.');
            this.loadDevices();
        }
    }

    /**
     * Restart device connection
     */
    async restartDevice(deviceId) {
        try {
            const device = this.devices.get(parseInt(deviceId));
            if (!device) {
                this.showError('Device not found');
                return;
            }

            const result = await Swal.fire({
                title: 'Restart Device?',
                text: `Restart ${device.device_name} connection?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#007bff',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-redo me-2"></i>Restart',
                cancelButtonText: 'Cancel'
            });

            if (result.isConfirmed) {
                this.updateDeviceRowStatus(deviceId, 'connecting');

                const response = await this.apiRequest('POST', `/devices.php?action=restart&id=${deviceId}`);

                if (response.success) {
                    this.showSuccess('Device restart initiated');
                    setTimeout(() => this.loadDevices(), 2000);
                } else {
                    this.showError(response.message || 'Failed to restart device');
                    this.loadDevices();
                }
            }
        } catch (error) {
            console.error('Error restarting device:', error);
            this.showError('Error restarting device. Please try again.');
            this.loadDevices();
        }
    }

    /**
     * Show QR Code modal
     */
    async showQRCode(deviceId) {
        try {
            const device = this.devices.get(parseInt(deviceId));
            if (!device) {
                this.showError('Device not found');
                return;
            }

            const modal = $('#modal-qr-code');

            // Set device info
            modal.find('.device-name').text(device.device_name);
            modal.find('.device-phone').text(device.phone_number);

            // Clear previous QR
            modal.find('#qr-code-container').html(`
                <div class="text-center py-4">
                    <i class="fas fa-spinner fa-spin fa-2x text-primary mb-3"></i>
                    <p>Loading QR Code...</p>
                </div>
            `);

            modal.modal('show');

            // Get QR code
            const response = await this.apiRequest('GET', `/devices.php?action=qr&id=${deviceId}`);

            if (response.success && response.data.qr_code) {
                modal.find('#qr-code-container').html(`
                    <div class="text-center">
                        <img src="${response.data.qr_code}" class="img-fluid rounded" style="max-width: 300px;" alt="QR Code">
                        <p class="mt-3 text-muted">
                            <i class="fas fa-mobile-alt me-2"></i>
                            Scan this QR code with your WhatsApp mobile app
                        </p>
                        <small class="text-muted">
                            QR Code expires in: <span id="qr-countdown">${response.data.expires_in || 300}</span> seconds
                        </small>
                    </div>
                `);

                // Start countdown
                this.startQRCountdown(response.data.expires_in || 300);
            } else {
                modal.find('#qr-code-container').html(`
                    <div class="text-center py-4">
                        <i class="fas fa-exclamation-triangle fa-2x text-warning mb-3"></i>
                        <p>QR Code not available</p>
                        <p class="text-muted">${response.message || 'Device may not be in pairing mode'}</p>
                    </div>
                `);
            }
        } catch (error) {
            console.error('Error showing QR code:', error);
            this.showError('Error loading QR code. Please try again.');
        }
    }

    /**
     * Show device details modal
     */
    async showDeviceDetails(deviceId) {
        try {
            const device = this.devices.get(parseInt(deviceId));
            if (!device) {
                this.showError('Device not found');
                return;
            }

            const modal = $('#modal-device-details');

            // Basic info
            modal.find('.device-name').text(device.device_name);
            modal.find('.device-phone').text(device.phone_number);
            modal.find('.device-id').text(device.device_id);
            modal.find('.device-owner').text(device.owner || 'Unknown');

            // Status info
            const status = this.statusConfig[device.status] || this.statusConfig.error;
            modal.find('.device-status').html(`
                <span class="badge bg-${status.class}">
                    <i class="fas ${status.icon} me-1"></i>
                    ${status.text}
                </span>
            `);

            modal.find('.device-whatsapp-name').text(device.whatsapp_name || 'Not available');
            modal.find('.device-last-seen').text(device.last_seen ? this.formatDateTime(device.last_seen) : 'Never');
            modal.find('.device-created').text(this.formatDateTime(device.created_at));
            modal.find('.device-updated').text(this.formatDateTime(device.updated_at));

            // Stats
            modal.find('.messages-today').text(device.messages_today || 0);
            modal.find('.retry-count').text(device.retry_count || 0);
            modal.find('.is-online').html(device.is_online ?
                '<i class="fas fa-circle text-success me-1"></i>Online' :
                '<i class="fas fa-circle text-secondary me-1"></i>Offline'
            );

            // API Token
            if (device.token) {
                modal.find('.api-token').text(device.token);
                modal.find('.token-container').show();
            } else {
                modal.find('.token-container').hide();
            }

            modal.modal('show');
        } catch (error) {
            console.error('Error showing device details:', error);
            this.showError('Error loading device details');
        }
    }

    /**
     * Start QR code countdown
     */
    startQRCountdown(seconds) {
        if (this.qrInterval) {
            clearInterval(this.qrInterval);
        }

        let remaining = seconds;
        const countdownElement = $('#qr-countdown');

        this.qrInterval = setInterval(() => {
            remaining--;

            if (countdownElement.length) {
                countdownElement.text(remaining);

                if (remaining <= 30) {
                    countdownElement.addClass('text-warning');
                }
                if (remaining <= 10) {
                    countdownElement.addClass('text-danger');
                }
            }

            if (remaining <= 0) {
                clearInterval(this.qrInterval);
                if (countdownElement.length) {
                    countdownElement.closest('#qr-code-container').html(`
                        <div class="text-center py-4">
                            <i class="fas fa-clock fa-2x text-warning mb-3"></i>
                            <p>QR Code has expired</p>
                            <button class="btn btn-primary" onclick="deviceManager.showQRCode(${$('#modal-qr-code').data('device-id')})">
                                <i class="fas fa-refresh me-2"></i>Generate New QR Code
                            </button>
                        </div>
                    `);
                }
            }
        }, 1000);
    }

    /**
     * Update device row status in real-time
     */
    updateDeviceRowStatus(deviceId, newStatus) {
        const row = $(`.device-row[data-device-id="${deviceId}"]`);
        if (row.length) {
            const status = this.statusConfig[newStatus] || this.statusConfig.error;
            const statusCell = row.find('td:nth-child(2)');

            statusCell.html(`
                <div class="d-flex align-items-center">
                    <i class="fas fa-circle text-secondary" title="Offline"></i>
                    <span class="badge bg-${status.class} ms-2">
                        <i class="fas ${status.icon} me-1"></i>
                        ${status.text}
                    </span>
                </div>
            `);

            // Update actions
            const actionsCell = row.find('td:nth-child(7)');
            if (this.devices.has(parseInt(deviceId))) {
                const device = this.devices.get(parseInt(deviceId));
                device.status = newStatus;
                actionsCell.find('.btn-group').html(this.renderDeviceActions(device));
            }
        }
    }

    /**
     * Update devices count in UI
     */
    updateDevicesCount(count) {
        $('.devices-count').text(count);
    }

    /**
     * Update status summary in dashboard
     */
    updateStatusSummary(devices) {
        const summary = {
            total: devices.length,
            connected: 0,
            connecting: 0,
            disconnected: 0,
            pairing: 0,
            banned: 0,
            error: 0
        };

        devices.forEach(device => {
            if (summary.hasOwnProperty(device.status)) {
                summary[device.status]++;
            } else {
                summary.error++;
            }
        });

        // Update summary cards
        $('.total-devices').text(summary.total);
        $('.connected-devices').text(summary.connected);
        $('.connecting-devices').text(summary.connecting);
        $('.disconnected-devices').text(summary.disconnected);
        $('.pairing-devices').text(summary.pairing);
        $('.banned-devices').text(summary.banned);
        $('.error-devices').text(summary.error);

        // Update progress bars if they exist
        if (summary.total > 0) {
            const connectedPercent = (summary.connected / summary.total) * 100;
            $('.connection-progress').css('width', connectedPercent + '%').attr('aria-valuenow', connectedPercent);
        }
    }

    /**
     * Start real-time updates via WebSocket or polling
     */
    startRealTimeUpdates() {
        // Try WebSocket first, fallback to polling
        if (typeof WebSocket !== 'undefined') {
            this.initWebSocket();
        } else {
            console.log('WebSocket not supported, using polling');
            this.startPolling();
        }
    }

    /**
     * Initialize WebSocket connection for real-time updates
     */
    initWebSocket() {
        try {
            const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
            const wsUrl = `${protocol}//${window.location.host}/ws/devices`;

            this.statusSocket = new WebSocket(wsUrl);

            this.statusSocket.onopen = () => {
                console.log('WebSocket connected for device updates');
            };

            this.statusSocket.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    this.handleRealTimeUpdate(data);
                } catch (error) {
                    console.error('Error parsing WebSocket message:', error);
                }
            };

            this.statusSocket.onclose = () => {
                console.log('WebSocket disconnected, attempting to reconnect...');
                setTimeout(() => this.initWebSocket(), 5000);
            };

            this.statusSocket.onerror = (error) => {
                console.error('WebSocket error:', error);
                this.startPolling(); // Fallback to polling
            };
        } catch (error) {
            console.error('Failed to initialize WebSocket:', error);
            this.startPolling();
        }
    }

    /**
     * Start polling for updates (fallback)
     */
    startPolling() {
        setInterval(() => {
            this.checkDeviceUpdates();
        }, 10000); // Poll every 10 seconds
    }

    /**
     * Check for device updates via API
     */
    async checkDeviceUpdates() {
        try {
            const lastUpdate = localStorage.getItem('devices_last_update') || '0';
            const response = await this.apiRequest('GET', `/devices.php?action=updates&since=${lastUpdate}`);

            if (response.success && response.data.updates.length > 0) {
                response.data.updates.forEach(update => {
                    this.handleRealTimeUpdate(update);
                });
                localStorage.setItem('devices_last_update', response.data.timestamp);
            }
        } catch (error) {
            console.error('Error checking device updates:', error);
        }
    }

    /**
     * Handle real-time device updates
     */
    handleRealTimeUpdate(data) {
        switch (data.type) {
            case 'device_status_change':
                this.handleDeviceStatusChange(data);
                break;
            case 'device_message_count':
                this.handleMessageCountUpdate(data);
                break;
            case 'device_qr_update':
                this.handleQRUpdate(data);
                break;
            case 'device_info_update':
                this.handleDeviceInfoUpdate(data);
                break;
            default:
                console.log('Unknown update type:', data.type);
        }
    }

    /**
     * Handle device status change
     */
    handleDeviceStatusChange(data) {
        const { device_id, status, whatsapp_name, is_online } = data;

        // Update local device data
        if (this.devices.has(device_id)) {
            const device = this.devices.get(device_id);
            device.status = status;
            device.whatsapp_name = whatsapp_name || device.whatsapp_name;
            device.is_online = is_online;
            device.last_seen = new Date().toISOString();
        }

        // Update UI
        this.updateDeviceRowStatus(device_id, status);

        // Show notification for important status changes
        if (['connected', 'banned', 'error'].includes(status)) {
            const device = this.devices.get(device_id);
            if (device) {
                this.showStatusNotification(device, status);
            }
        }
    }

    /**
     * Handle message count update
     */
    handleMessageCountUpdate(data) {
        const { device_id, count } = data;
        const row = $(`.device-row[data-device-id="${device_id}"]`);

        if (row.length) {
            row.find('td:nth-child(5) .badge').text(count);
        }

        // Update local data
        if (this.devices.has(device_id)) {
            this.devices.get(device_id).messages_today = count;
        }
    }

    /**
     * Handle QR code update
     */
    handleQRUpdate(data) {
        const { device_id, qr_code, expires_in } = data;

        // If QR modal is open for this device, update it
        const modal = $('#modal-qr-code');
        if (modal.hasClass('show') && modal.data('device-id') == device_id) {
            if (qr_code) {
                modal.find('#qr-code-container').html(`
                    <div class="text-center">
                        <img src="${qr_code}" class="img-fluid rounded" style="max-width: 300px;" alt="QR Code">
                        <p class="mt-3 text-muted">
                            <i class="fas fa-mobile-alt me-2"></i>
                            Scan this QR code with your WhatsApp mobile app
                        </p>
                        <small class="text-muted">
                            QR Code expires in: <span id="qr-countdown">${expires_in || 300}</span> seconds
                        </small>
                    </div>
                `);
                this.startQRCountdown(expires_in || 300);
            }
        }
    }

    /**
     * Handle device info update
     */
    handleDeviceInfoUpdate(data) {
        const { device_id, whatsapp_name, phone_number } = data;

        if (this.devices.has(device_id)) {
            const device = this.devices.get(device_id);
            device.whatsapp_name = whatsapp_name || device.whatsapp_name;
            device.phone_number = phone_number || device.phone_number;

            // Update table row
            const row = $(`.device-row[data-device-id="${device_id}"]`);
            if (row.length && whatsapp_name) {
                row.find('td:nth-child(3)').html(`
                    <div class="text-truncate" style="max-width: 200px;" title="${this.escapeHtml(whatsapp_name)}">
                        ${this.escapeHtml(whatsapp_name)}
                    </div>
                `);
            }
        }
    }

    /**
     * Show status notification
     */
    showStatusNotification(device, status) {
        const statusConfig = this.statusConfig[status];
        if (!statusConfig) return;

        let title, text, icon;

        switch (status) {
            case 'connected':
                title = 'Device Connected';
                text = `${device.device_name} is now connected to WhatsApp`;
                icon = 'success';
                break;
            case 'banned':
                title = 'Device Banned';
                text = `${device.device_name} has been banned by WhatsApp`;
                icon = 'error';
                break;
            case 'error':
                title = 'Device Error';
                text = `${device.device_name} encountered an error`;
                icon = 'error';
                break;
            default:
                return;
        }

        // Use SweetAlert for notifications
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 5000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });

        Toast.fire({
            icon: icon,
            title: title,
            text: text
        });
    }

    /**
     * Validate device form
     */
    validateDeviceForm(data, mode = 'add') {
        const errors = {};

        // Device name validation
        if (!data.device_name || data.device_name.trim().length < 3) {
            errors.device_name = 'Device name must be at least 3 characters long';
        }

        // Phone number validation
        if (!data.phone_number || !this.isValidPhoneNumber(data.phone_number)) {
            errors.phone_number = 'Please enter a valid phone number';
        }

        // Device ID validation
        if (!data.device_id || data.device_id.trim().length < 5) {
            errors.device_id = 'Device ID must be at least 5 characters long';
        }

        if (Object.keys(errors).length > 0) {
            this.showValidationErrors(errors, mode);
            return false;
        }

        return true;
    }

    /**
     * Show validation errors
     */
    showValidationErrors(errors, mode = 'add') {
        const prefix = mode === 'edit' ? 'edit_' : '';

        // Clear previous errors
        $(`.${mode}-form .is-invalid`).removeClass('is-invalid');
        $(`.${mode}-form .invalid-feedback`).remove();

        // Show new errors
        Object.keys(errors).forEach(field => {
            const input = $(`#${prefix}${field}`);
            const message = errors[field];

            input.addClass('is-invalid');
            input.after(`<div class="invalid-feedback">${message}</div>`);
        });
    }

    /**
     * Format phone number as user types
     */
    formatPhoneNumber(input) {
        let value = input.value.replace(/\D/g, ''); // Remove non-digits

        // Add country code if not present
        if (value.length > 0 && !value.startsWith('62')) {
            if (value.startsWith('08')) {
                value = '62' + value.substring(1); // Replace 08 with 62
            } else if (value.startsWith('8')) {
                value = '62' + value; // Add 62 prefix
            }
        }

        // Format with spaces for readability
        if (value.length > 2) {
            value = value.replace(/(\d{2})(\d{3})(\d{4})(\d{4})/, '$1 $2 $3 $4');
        }

        input.value = value;
    }

    /**
     * Validate phone number
     */
    isValidPhoneNumber(phone) {
        // Remove all non-digits
        const cleaned = phone.replace(/\D/g, '');

        // Indonesian phone number validation
        // Should start with 62 and be 10-15 digits total
        return /^62[8-9]\d{8,12}$/.test(cleaned);
    }

    /**
     * Validate phone number input field
     */
    validatePhoneNumber(input) {
        const phone = input.value.replace(/\D/g, '');
        const isValid = this.isValidPhoneNumber(phone);

        if (phone.length > 0 && !isValid) {
            $(input).addClass('is-invalid');
            $(input).siblings('.invalid-feedback').remove();
            $(input).after('<div class="invalid-feedback">Please enter a valid Indonesian phone number</div>');
        } else {
            $(input).removeClass('is-invalid');
            $(input).siblings('.invalid-feedback').remove();
        }

        return isValid;
    }

    /**
     * API request helper
     */
    async apiRequest(method, endpoint, data = null) {
        try {
            const options = {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            };

            if (data && ['POST', 'PUT', 'PATCH'].includes(method)) {
                options.body = JSON.stringify(data);
            }

            const response = await fetch(this.apiBaseUrl + endpoint, options);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            return await response.json();
        } catch (error) {
            console.error('API request failed:', error);
            throw error;
        }
    }

    /**
     * Show loading indicator
     */
    showLoading(container, message = 'Loading...') {
        const loadingHtml = `
            <div class="d-flex justify-content-center align-items-center py-5 loading-overlay">
                <div class="text-center">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="text-muted">${message}</p>
                </div>
            </div>
        `;

        $(container).prepend(loadingHtml);
    }

    /**
     * Hide loading indicator
     */
    hideLoading(container) {
        $(container).find('.loading-overlay').remove();
    }

    /**
     * Show success message
     */
    showSuccess(message) {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });

        Toast.fire({
            icon: 'success',
            title: 'Success',
            text: message
        });
    }

    /**
     * Show error message
     */
    showError(message) {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 5000,
            timerProgressBar: true
        });

        Toast.fire({
            icon: 'error',
            title: 'Error',
            text: message
        });
    }

    /**
     * Show info message
     */
    showInfo(message) {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 4000,
            timerProgressBar: true
        });

        Toast.fire({
            icon: 'info',
            title: 'Info',
            text: message
        });
    }

    /**
     * Format date time for display
     */
    formatDateTime(dateString) {
        if (!dateString) return 'Never';

        try {
            const date = new Date(dateString);
            const now = new Date();
            const diff = now - date;

            // Less than 1 minute
            if (diff < 60000) {
                return 'Just now';
            }

            // Less than 1 hour
            if (diff < 3600000) {
                const minutes = Math.floor(diff / 60000);
                return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
            }

            // Less than 1 day
            if (diff < 86400000) {
                const hours = Math.floor(diff / 3600000);
                return `${hours} hour${hours > 1 ? 's' : ''} ago`;
            }

            // Less than 1 week
            if (diff < 604800000) {
                const days = Math.floor(diff / 86400000);
                return `${days} day${days > 1 ? 's' : ''} ago`;
            }

            // More than 1 week, show actual date
            return date.toLocaleDateString('id-ID', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        } catch (error) {
            return 'Invalid date';
        }
    }

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        if (!text) return '';

        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };

        return text.replace(/[&<>"']/g, function (m) { return map[m]; });
    }

    /**
     * Copy text to clipboard
     */
    async copyToClipboard(text) {
        try {
            await navigator.clipboard.writeText(text);
            this.showSuccess('Copied to clipboard');
        } catch (error) {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            this.showSuccess('Copied to clipboard');
        }
    }

    /**
     * Export devices data
     */
    async exportDevices(format = 'csv') {
        try {
            const response = await this.apiRequest('GET', `/devices.php?action=export&format=${format}`);

            if (response.success) {
                // Create download link
                const blob = new Blob([response.data], {
                    type: format === 'csv' ? 'text/csv' : 'application/json'
                });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `devices_${new Date().toISOString().split('T')[0]}.${format}`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);

                this.showSuccess(`Devices exported as ${format.toUpperCase()}`);
            } else {
                this.showError('Failed to export devices');
            }
        } catch (error) {
            console.error('Error exporting devices:', error);
            this.showError('Error exporting devices. Please try again.');
        }
    }

    /**
     * Cleanup resources
     */
    destroy() {
        // Clear intervals
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
        }

        if (this.qrInterval) {
            clearInterval(this.qrInterval);
        }

        // Close WebSocket
        if (this.statusSocket) {
            this.statusSocket.close();
        }

        // Clear event listeners
        $(document).off('.deviceManager');

        console.log('DeviceManager destroyed');
    }
}

// ===============================================================================
// INITIALIZATION AND GLOBAL FUNCTIONS
// ===============================================================================

// Global device manager instance
let deviceManager;

// Initialize when document is ready
$(document).ready(function () {
    deviceManager = new DeviceManager();

    // Global functions for use in HTML onclick attributes
    window.deviceManager = deviceManager;

    // Handle page unload
    $(window).on('beforeunload', function () {
        if (deviceManager) {
            deviceManager.destroy();
        }
    });
});

// ===============================================================================
// ADDITIONAL UTILITY FUNCTIONS
// ===============================================================================

/**
 * Refresh devices list manually
 */
function refreshDevices() {
    if (deviceManager) {
        deviceManager.loadDevices();
    }
}

/**
 * Show add device modal (global function)
 */
function showAddDevice() {
    if (deviceManager) {
        deviceManager.showAddDeviceModal();
    }
}

/**
 * Quick connect device
 */
function quickConnectDevice(deviceId) {
    if (deviceManager) {
        deviceManager.connectDevice(deviceId);
    }
}

/**
 * Quick disconnect device
 */
function quickDisconnectDevice(deviceId) {
    if (deviceManager) {
        deviceManager.disconnectDevice(deviceId);
    }
}

/**
 * Copy API token to clipboard
 */
function copyApiToken(token) {
    if (deviceManager) {
        deviceManager.copyToClipboard(token);
    }
}

/**
 * Export devices data
 */
function exportDevicesData(format) {
    if (deviceManager) {
        deviceManager.exportDevices(format);
    }
}

// ===============================================================================
// CSS ANIMATIONS AND STYLES (Inject via JavaScript)
// ===============================================================================

// Add custom CSS for animations
const customCSS = `
<style>
.device-row {
    transition: all 0.3s ease;
}

.device-row:hover {
    background-color: rgba(0,123,255,0.05);
}

.loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255,255,255,0.9);
    z-index: 1000;
}

.status-indicator {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

.qr-code-container img {
    transition: transform 0.3s ease;
}

.qr-code-container img:hover {
    transform: scale(1.05);
}

.device-avatar {
    position: relative;
}

.device-avatar .online-indicator {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 12px;
    height: 12px;
    border: 2px solid white;
    border-radius: 50%;
}

.btn-group .btn {
    transition: all 0.2s ease;
}

.btn-group .btn:hover {
    transform: translateY(-1px);
}

.modal-qr-code .modal-body {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.modal-qr-code .qr-container {
    background: white;
    border-radius: 15px;
    padding: 20px;
    margin: 20px 0;
}

.device-status-badge {
    font-size: 0.875rem;
    padding: 0.375rem 0.75rem;
}

.phone-input:focus {
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.table-responsive {
    border-radius: 10px;
    overflow: hidden;
}

.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: 1px solid rgba(0,0,0,.125);
}

.btn-outline-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,123,255,0.3);
}

.text-truncate {
    max-width: 200px;
}

@media (max-width: 768px) {
    .btn-group {
        flex-direction: column;
    }
    
    .btn-group .btn {
        margin-bottom: 2px;
    }
    
    .table-responsive {
        font-size: 0.875rem;
    }
}
</style>
`;

// Inject CSS
if (!document.getElementById('devices-custom-css')) {
    const styleElement = document.createElement('div');
    styleElement.id = 'devices-custom-css';
    styleElement.innerHTML = customCSS;
    document.head.appendChild(styleElement);
}