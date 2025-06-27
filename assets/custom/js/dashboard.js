/**
 * WhatsApp Monitor - Dashboard JavaScript
 * Functionality untuk halaman dashboard
 */

class DashboardManager {
    constructor() {
        this.refreshInterval = null;
        this.autoRefresh = true;
        this.refreshRate = 30000; // 30 seconds
        this.charts = {};
        this.counters = {};

        this.init();
    }

    /**
     * Initialize dashboard
     */
    init() {
        console.log('Initializing Dashboard Manager');

        // Load initial data
        this.loadDashboardData();

        // Setup auto refresh
        this.setupAutoRefresh();

        // Setup event listeners
        this.setupEventListeners();

        // Initialize charts
        this.initializeCharts();

        // Setup real-time updates
        this.setupRealTimeUpdates();
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Refresh button
        const refreshBtn = document.getElementById('refresh-dashboard');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => {
                this.refreshDashboard();
            });
        }

        // Auto refresh toggle
        const autoRefreshToggle = document.getElementById('auto-refresh-toggle');
        if (autoRefreshToggle) {
            autoRefreshToggle.addEventListener('change', (e) => {
                this.autoRefresh = e.target.checked;
                if (this.autoRefresh) {
                    this.startAutoRefresh();
                } else {
                    this.stopAutoRefresh();
                }
            });
        }

        // Device action buttons
        document.addEventListener('click', (e) => {
            if (e.target.matches('.btn-connect-device')) {
                this.handleDeviceConnect(e.target.dataset.deviceId);
            } else if (e.target.matches('.btn-disconnect-device')) {
                this.handleDeviceDisconnect(e.target.dataset.deviceId);
            } else if (e.target.matches('.btn-view-qr')) {
                this.showQRCode(e.target.dataset.deviceId);
            } else if (e.target.matches('.btn-device-logs')) {
                this.showDeviceLogs(e.target.dataset.deviceId);
            }
        });
    }

    /**
     * Load dashboard data
     */
    async loadDashboardData() {
        try {
            uiHelper.showLoading('.dashboard-content', 'Loading dashboard...');

            // Load all dashboard data in parallel
            const [statsData, devicesData, recentLogs] = await Promise.all([
                whatsappAPI.getDashboardStats(),
                whatsappAPI.getDevices(),
                whatsappAPI.getApiLogs({ limit: 10 })
            ]);

            // Update statistics
            this.updateStatistics(statsData.data);

            // Update devices grid
            this.updateDevicesGrid(devicesData.data);

            // Update recent activity
            this.updateRecentActivity(recentLogs.data);

            // Update charts
            this.updateCharts(statsData.data, devicesData.data);

        } catch (error) {
            console.error('Error loading dashboard data:', error);
            uiHelper.showError('Failed to load dashboard data');
        } finally {
            uiHelper.hideLoading('.dashboard-content');
        }
    }

    /**
     * Update statistics cards
     */
    updateStatistics(stats) {
        const statElements = {
            'total-devices': stats.totalDevices || 0,
            'connected-devices': stats.connectedDevices || 0,
            'messages-today': stats.messagesToday || 0,
            'active-users': stats.activeUsers || 0,
            'error-devices': stats.errorDevices || 0,
            'banned-devices': stats.bannedDevices || 0
        };

        Object.entries(statElements).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                this.animateCounter(element, value);
            }
        });

        // Update connection percentage
        const connectionRate = stats.totalDevices > 0 ?
            ((stats.connectedDevices / stats.totalDevices) * 100).toFixed(1) : 0;

        const connectionElement = document.getElementById('connection-rate');
        if (connectionElement) {
            connectionElement.textContent = connectionRate + '%';
        }

        // Update progress bars
        this.updateProgressBar('devices-progress', stats.connectedDevices, stats.totalDevices);
    }

    /**
     * Animate counter
     */
    animateCounter(element, targetValue) {
        const startValue = parseInt(element.textContent) || 0;
        const duration = 1000; // 1 second
        const steps = 60;
        const stepValue = (targetValue - startValue) / steps;
        const stepDuration = duration / steps;

        let currentStep = 0;

        // Store reference to prevent multiple animations
        if (this.counters[element.id]) {
            clearInterval(this.counters[element.id]);
        }

        this.counters[element.id] = setInterval(() => {
            currentStep++;
            const currentValue = Math.round(startValue + (stepValue * currentStep));
            element.textContent = currentValue;

            if (currentStep >= steps) {
                element.textContent = targetValue;
                clearInterval(this.counters[element.id]);
                delete this.counters[element.id];
            }
        }, stepDuration);
    }

    /**
     * Update progress bar
     */
    updateProgressBar(id, current, total) {
        const progressBar = document.getElementById(id);
        if (!progressBar) return;

        const percentage = total > 0 ? (current / total) * 100 : 0;
        progressBar.style.width = percentage + '%';
        progressBar.setAttribute('aria-valuenow', percentage);

        // Update color based on percentage
        progressBar.className = progressBar.className.replace(/bg-\w+/, '');
        if (percentage >= 80) {
            progressBar.classList.add('bg-success');
        } else if (percentage >= 50) {
            progressBar.classList.add('bg-warning');
        } else {
            progressBar.classList.add('bg-danger');
        }
    }

    /**
     * Update devices grid
     */
    updateDevicesGrid(devices) {
        const container = document.getElementById('devices-grid');
        if (!container) return;

        if (!devices || devices.length === 0) {
            container.innerHTML = `
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-mobile-alt fa-3x text-muted mb-3"></i>
                            <h5>No Devices</h5>
                            <p class="text-muted">Add your first WhatsApp device to get started.</p>
                            <a href="/devices/add" class="btn btn-primary">Add Device</a>
                        </div>
                    </div>
                </div>
            `;
            return;
        }

        const devicesHTML = devices.map(device => this.createDeviceCard(device)).join('');
        container.innerHTML = devicesHTML;
    }

    /**
     * Create device card HTML
     */
    createDeviceCard(device) {
        const statusClass = this.getStatusClass(device.status);
        const statusText = this.getStatusText(device.status);
        const lastSeen = device.lastSeen ? uiHelper.formatDate(device.lastSeen, 'relative') : 'Never';
        const isOnline = device.isOnline;

        return `
            <div class="col-lg-4 col-md-6">
                <div class="card device-card h-100">
                    <div class="card-body">
                        <div class="device-info">
                            <div class="device-avatar">
                                ${device.deviceName.charAt(0).toUpperCase()}
                            </div>
                            <div class="device-details">
                                <h5>${device.deviceName}</h5>
                                <p>${device.phoneNumber}</p>
                            </div>
                        </div>
                        
                        <div class="device-status mb-3">
                            <span class="status-badge status-${statusClass}">
                                ${statusText}
                            </span>
                            ${isOnline ? '<span class="badge bg-success ms-2">Online</span>' : ''}
                        </div>
                        
                        <div class="device-meta">
                            <small class="text-muted">
                                <i class="fas fa-clock me-1"></i>
                                Last seen: ${lastSeen}
                            </small>
                        </div>
                        
                        <div class="device-actions mt-3">
                            ${this.createDeviceActionButtons(device)}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Create device action buttons
     */
    createDeviceActionButtons(device) {
        const buttons = [];

        if (device.status === 'disconnected' || device.status === 'error') {
            buttons.push(`
                <button class="btn btn-success btn-sm btn-connect-device" 
                        data-device-id="${device.id}">
                    <i class="fas fa-play me-1"></i> Connect
                </button>
            `);
        }

        if (device.status === 'connected') {
            buttons.push(`
                <button class="btn btn-warning btn-sm btn-disconnect-device" 
                        data-device-id="${device.id}">
                    <i class="fas fa-stop me-1"></i> Disconnect
                </button>
            `);
        }

        if (device.status === 'pairing') {
            buttons.push(`
                <button class="btn btn-info btn-sm btn-view-qr" 
                        data-device-id="${device.id}">
                    <i class="fas fa-qrcode me-1"></i> Show QR
                </button>
            `);
        }

        buttons.push(`
            <button class="btn btn-outline-secondary btn-sm btn-device-logs" 
                    data-device-id="${device.id}">
                <i class="fas fa-list me-1"></i> Logs
            </button>
        `);

        buttons.push(`
            <a href="/devices/view?id=${device.id}" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-eye me-1"></i> View
            </a>
        `);

        return buttons.join(' ');
    }

    /**
     * Get status CSS class
     */
    getStatusClass(status) {
        const statusMap = {
            'connected': 'connected',
            'connecting': 'connecting',
            'disconnected': 'disconnected',
            'pairing': 'pairing',
            'error': 'error',
            'banned': 'banned',
            'timeout': 'error',
            'auth_failure': 'error'
        };
        return statusMap[status] || 'disconnected';
    }

    /**
     * Get status text
     */
    getStatusText(status) {
        const statusMap = {
            'connected': 'Connected',
            'connecting': 'Connecting',
            'disconnected': 'Disconnected',
            'pairing': 'Waiting for QR',
            'error': 'Error',
            'banned': 'Banned',
            'timeout': 'Timeout',
            'auth_failure': 'Auth Failed'
        };
        return statusMap[status] || 'Unknown';
    }

    /**
     * Update recent activity
     */
    updateRecentActivity(logs) {
        const container = document.getElementById('recent-activity');
        if (!container) return;

        if (!logs || logs.length === 0) {
            container.innerHTML = `
                <div class="text-center text-muted py-4">
                    <i class="fas fa-history fa-2x mb-2"></i>
                    <p>No recent activity</p>
                </div>
            `;
            return;
        }

        const logsHTML = logs.map(log => `
            <div class="activity-item">
                <div class="activity-icon ${this.getLogIconClass(log.type)}">
                    <i class="${this.getLogIcon(log.type)}"></i>
                </div>
                <div class="activity-content">
                    <div class="activity-text">${log.message}</div>
                    <div class="activity-time">${uiHelper.formatDate(log.createdAt, 'relative')}</div>
                </div>
            </div>
        `).join('');

        container.innerHTML = logsHTML;
    }

    /**
     * Get log icon
     */
    getLogIcon(type) {
        const iconMap = {
            'connection': 'fas fa-link',
            'message': 'fas fa-envelope',
            'error': 'fas fa-exclamation-triangle',
            'webhook': 'fas fa-bolt',
            'api': 'fas fa-code'
        };
        return iconMap[type] || 'fas fa-info-circle';
    }

    /**
     * Get log icon class
     */
    getLogIconClass(type) {
        const classMap = {
            'connection': 'bg-primary',
            'message': 'bg-success',
            'error': 'bg-danger',
            'webhook': 'bg-warning',
            'api': 'bg-info'
        };
        return classMap[type] || 'bg-secondary';
    }

    /**
     * Initialize charts
     */
    initializeCharts() {
        this.initializeDeviceStatusChart();
        this.initializeMessageChart();
        this.initializePerformanceChart();
    }

    /**
     * Initialize device status chart
     */
    initializeDeviceStatusChart() {
        const ctx = document.getElementById('deviceStatusChart');
        if (!ctx) return;

        this.charts.deviceStatus = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Connected', 'Disconnected', 'Error', 'Pairing'],
                datasets: [{
                    data: [0, 0, 0, 0],
                    backgroundColor: [
                        '#28a745',
                        '#6c757d',
                        '#dc3545',
                        '#17a2b8'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    /**
     * Initialize message chart
     */
    initializeMessageChart() {
        const ctx = document.getElementById('messageChart');
        if (!ctx) return;

        this.charts.messages = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Messages Sent',
                    data: [],
                    borderColor: '#25D366',
                    backgroundColor: 'rgba(37, 211, 102, 0.1)',
                    borderWidth: 2,
                    fill: true
                }, {
                    label: 'Messages Received',
                    data: [],
                    borderColor: '#128C7E',
                    backgroundColor: 'rgba(18, 140, 126, 0.1)',
                    borderWidth: 2,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        position: 'top'
                    }
                }
            }
        });
    }

    /**
     * Initialize performance chart
     */
    initializePerformanceChart() {
        const ctx = document.getElementById('performanceChart');
        if (!ctx) return;

        this.charts.performance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Success Rate', 'Response Time', 'Uptime'],
                datasets: [{
                    label: 'Performance Metrics',
                    data: [0, 0, 0],
                    backgroundColor: [
                        '#28a745',
                        '#ffc107',
                        '#17a2b8'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }

    /**
     * Update charts with data
     */
    updateCharts(stats, devices) {
        this.updateDeviceStatusChart(devices);
        this.updateMessageChart(stats);
        this.updatePerformanceChart(stats);
    }

    /**
     * Update device status chart
     */
    updateDeviceStatusChart(devices) {
        if (!this.charts.deviceStatus || !devices) return;

        const statusCounts = {
            connected: 0,
            disconnected: 0,
            error: 0,
            pairing: 0
        };

        devices.forEach(device => {
            const status = device.status;
            if (status === 'connected') {
                statusCounts.connected++;
            } else if (status === 'pairing') {
                statusCounts.pairing++;
            } else if (['error', 'banned', 'timeout', 'auth_failure'].includes(status)) {
                statusCounts.error++;
            } else {
                statusCounts.disconnected++;
            }
        });

        this.charts.deviceStatus.data.datasets[0].data = [
            statusCounts.connected,
            statusCounts.disconnected,
            statusCounts.error,
            statusCounts.pairing
        ];

        this.charts.deviceStatus.update();
    }

    /**
     * Update message chart
     */
    updateMessageChart(stats) {
        if (!this.charts.messages || !stats.messageHistory) return;

        // Update with last 7 days data
        const labels = stats.messageHistory.map(item =>
            new Date(item.date).toLocaleDateString()
        );
        const sentData = stats.messageHistory.map(item => item.sent || 0);
        const receivedData = stats.messageHistory.map(item => item.received || 0);

        this.charts.messages.data.labels = labels;
        this.charts.messages.data.datasets[0].data = sentData;
        this.charts.messages.data.datasets[1].data = receivedData;

        this.charts.messages.update();
    }

    /**
     * Update performance chart
     */
    updatePerformanceChart(stats) {
        if (!this.charts.performance) return;

        const successRate = stats.successRate || 0;
        const avgResponseTime = 100 - (stats.avgResponseTime || 0); // Invert for display
        const uptime = stats.uptime || 0;

        this.charts.performance.data.datasets[0].data = [
            successRate,
            avgResponseTime,
            uptime
        ];

        this.charts.performance.update();
    }

    /**
     * Handle device connect
     */
    async handleDeviceConnect(deviceId) {
        try {
            const confirmed = await uiHelper.confirm(
                'Are you sure you want to connect this device?',
                'Connect Device'
            );

            if (!confirmed) return;

            uiHelper.showInfo('Connecting device...');

            const result = await whatsappAPI.connectDevice(deviceId);

            if (result.success) {
                uiHelper.showSuccess('Device connection initiated');
                this.refreshDashboard();
            }
        } catch (error) {
            console.error('Error connecting device:', error);
        }
    }

    /**
     * Handle device disconnect
     */
    async handleDeviceDisconnect(deviceId) {
        try {
            const confirmed = await uiHelper.confirm(
                'Are you sure you want to disconnect this device?',
                'Disconnect Device',
                { confirmClass: 'btn-warning' }
            );

            if (!confirmed) return;

            uiHelper.showInfo('Disconnecting device...');

            const result = await whatsappAPI.disconnectDevice(deviceId);

            if (result.success) {
                uiHelper.showSuccess('Device disconnected');
                this.refreshDashboard();
            }
        } catch (error) {
            console.error('Error disconnecting device:', error);
        }
    }

    /**
     * Show QR code modal
     */
    async showQRCode(deviceId) {
        try {
            uiHelper.showInfo('Loading QR code...');

            const result = await whatsappAPI.getQRCode(deviceId);

            if (result.success && result.data.qrCode) {
                this.displayQRModal(result.data.qrCode, deviceId);
            } else {
                uiHelper.showWarning('QR code not available');
            }
        } catch (error) {
            console.error('Error loading QR code:', error);
        }
    }

    /**
     * Display QR code modal
     */
    displayQRModal(qrCode, deviceId) {
        const modalHTML = `
            <div class="modal fade" id="qrModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-qrcode me-2"></i>
                                Scan QR Code
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-center">
                            <div class="qr-container">
                                <img src="${qrCode}" alt="QR Code" class="qr-code img-fluid">
                                <p class="mt-3 text-muted">
                                    Open WhatsApp on your phone and scan this QR code
                                </p>
                                <div class="mt-3">
                                    <button class="btn btn-outline-primary btn-refresh-qr" 
                                            data-device-id="${deviceId}">
                                        <i class="fas fa-refresh me-1"></i> Refresh QR
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Remove existing modal
        const existingModal = document.getElementById('qrModal');
        if (existingModal) {
            existingModal.remove();
        }

        // Add new modal
        document.body.insertAdjacentHTML('beforeend', modalHTML);

        // Show modal
        const modal = uiHelper.showModal('qrModal');

        // Setup refresh button
        document.querySelector('.btn-refresh-qr').addEventListener('click', () => {
            modal.hide();
            this.showQRCode(deviceId);
        });

        // Auto refresh QR code every 30 seconds
        const qrRefreshInterval = setInterval(() => {
            if (document.getElementById('qrModal')) {
                this.refreshQRCode(deviceId);
            } else {
                clearInterval(qrRefreshInterval);
            }
        }, 30000);
    }

    /**
     * Refresh QR code in modal
     */
    async refreshQRCode(deviceId) {
        try {
            const result = await whatsappAPI.getQRCode(deviceId);

            if (result.success && result.data.qrCode) {
                const qrImg = document.querySelector('#qrModal .qr-code');
                if (qrImg) {
                    qrImg.src = result.data.qrCode;
                }
            }
        } catch (error) {
            console.error('Error refreshing QR code:', error);
        }
    }

    /**
     * Show device logs
     */
    async showDeviceLogs(deviceId) {
        try {
            const result = await whatsappAPI.getMessageLogs(deviceId, { limit: 20 });

            if (result.success) {
                this.displayLogsModal(result.data, deviceId);
            }
        } catch (error) {
            console.error('Error loading device logs:', error);
        }
    }

    /**
     * Display logs modal
     */
    displayLogsModal(logs, deviceId) {
        const logsHTML = logs.map(log => `
            <tr>
                <td>${uiHelper.formatDate(log.createdAt, 'datetime')}</td>
                <td>
                    <span class="badge bg-${log.direction === 'incoming' ? 'success' : 'primary'}">
                        ${log.direction}
                    </span>
                </td>
                <td>${log.fromNumber}</td>
                <td>${log.toNumber}</td>
                <td>${log.messageType}</td>
                <td class="text-truncate" style="max-width: 200px;">
                    ${log.messageContent || '-'}
                </td>
            </tr>
        `).join('');

        const modalHTML = `
            <div class="modal fade" id="logsModal" tabindex="-1">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-list me-2"></i>
                                Device Logs
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>Direction</th>
                                            <th>From</th>
                                            <th>To</th>
                                            <th>Type</th>
                                            <th>Content</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${logsHTML || '<tr><td colspan="6" class="text-center">No logs found</td></tr>'}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a href="/logs/messages?device=${deviceId}" class="btn btn-primary">
                                View All Logs
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Remove existing modal
        const existingModal = document.getElementById('logsModal');
        if (existingModal) {
            existingModal.remove();
        }

        // Add new modal
        document.body.insertAdjacentHTML('beforeend', modalHTML);

        // Show modal
        uiHelper.showModal('logsModal');
    }

    /**
     * Setup auto refresh
     */
    setupAutoRefresh() {
        if (this.autoRefresh) {
            this.startAutoRefresh();
        }
    }

    /**
     * Start auto refresh
     */
    startAutoRefresh() {
        this.stopAutoRefresh(); // Clear existing interval

        this.refreshInterval = setInterval(() => {
            this.refreshDashboard();
        }, this.refreshRate);

        console.log('Auto refresh started');
    }

    /**
     * Stop auto refresh
     */
    stopAutoRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
        }
        console.log('Auto refresh stopped');
    }

    /**
     * Refresh dashboard
     */
    async refreshDashboard() {
        const refreshBtn = document.getElementById('refresh-dashboard');
        if (refreshBtn) {
            uiHelper.showLoading(refreshBtn, 'Refreshing...');
        }

        try {
            await this.loadDashboardData();

            // Update last refresh time
            const lastRefreshElement = document.getElementById('last-refresh');
            if (lastRefreshElement) {
                lastRefreshElement.textContent = uiHelper.formatDate(new Date(), 'time');
            }

        } catch (error) {
            console.error('Error refreshing dashboard:', error);
        } finally {
            if (refreshBtn) {
                uiHelper.hideLoading(refreshBtn);
            }
        }
    }

    /**
     * Setup real-time updates (WebSocket or Server-Sent Events)
     */
    setupRealTimeUpdates() {
        // This would integrate with WebSocket or SSE for real-time updates
        // For now, we'll use polling with auto-refresh
        console.log('Real-time updates setup (using polling)');
    }

    /**
     * Cleanup
     */
    destroy() {
        this.stopAutoRefresh();

        // Clear counters
        Object.values(this.counters).forEach(interval => {
            clearInterval(interval);
        });

        // Destroy charts
        Object.values(this.charts).forEach(chart => {
            chart.destroy();
        });

        console.log('Dashboard Manager destroyed');
    }
}

// Initialize dashboard when DOM is ready
document.addEventListener('DOMContentLoaded', function () {
    // Only initialize on dashboard page
    if (document.body.classList.contains('dashboard-page') ||
        document.getElementById('dashboard-content')) {

        window.dashboardManager = new DashboardManager();

        console.log('Dashboard initialized');
    }
});

// Handle page visibility change to pause/resume auto refresh
document.addEventListener('visibilitychange', function () {
    if (window.dashboardManager) {
        if (document.hidden) {
            window.dashboardManager.stopAutoRefresh();
        } else if (window.dashboardManager.autoRefresh) {
            window.dashboardManager.startAutoRefresh();
        }
    }
});

// Cleanup on page unload
window.addEventListener('beforeunload', function () {
    if (window.dashboardManager) {
        window.dashboardManager.destroy();
    }
});