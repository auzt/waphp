/**
 * WhatsApp Monitor - API Client
 * JavaScript utility untuk komunikasi dengan API
 */

class WhatsAppAPI {
    constructor(options = {}) {
        this.baseURL = options.baseURL || '/api';
        this.timeout = options.timeout || 30000;
        this.retryAttempts = options.retryAttempts || 3;
        this.retryDelay = options.retryDelay || 1000;
        this.debug = options.debug || false;

        // Event listeners
        this.listeners = {
            'request:start': [],
            'request:success': [],
            'request:error': [],
            'request:complete': []
        };

        this.log('API Client initialized', { baseURL: this.baseURL });
    }

    /**
     * Add event listener
     */
    on(event, callback) {
        if (this.listeners[event]) {
            this.listeners[event].push(callback);
        }
    }

    /**
     * Emit event
     */
    emit(event, data) {
        if (this.listeners[event]) {
            this.listeners[event].forEach(callback => callback(data));
        }
    }

    /**
     * Log message if debug is enabled
     */
    log(...args) {
        if (this.debug) {
            console.log('[WhatsAppAPI]', ...args);
        }
    }

    /**
     * Sleep utility
     */
    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * Make HTTP request with retry logic
     */
    async request(endpoint, options = {}) {
        const url = `${this.baseURL}${endpoint}`;
        const requestOptions = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...options.headers
            },
            ...options
        };

        // Add body if method is POST/PUT/PATCH
        if (['POST', 'PUT', 'PATCH'].includes(requestOptions.method) && options.data) {
            requestOptions.body = JSON.stringify(options.data);
        }

        this.emit('request:start', { url, options: requestOptions });

        let lastError;
        for (let attempt = 1; attempt <= this.retryAttempts; attempt++) {
            try {
                this.log(`Request attempt ${attempt}:`, url);

                // Create abort controller for timeout
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), this.timeout);
                requestOptions.signal = controller.signal;

                const response = await fetch(url, requestOptions);
                clearTimeout(timeoutId);

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error || `HTTP ${response.status}: ${response.statusText}`);
                }

                this.emit('request:success', { url, data, response });
                this.emit('request:complete', { url, success: true });

                this.log('Request successful:', data);
                return data;

            } catch (error) {
                lastError = error;
                this.log(`Request attempt ${attempt} failed:`, error.message);

                // Don't retry for certain errors
                if (error.name === 'AbortError' ||
                    error.message.includes('401') ||
                    error.message.includes('403') ||
                    attempt === this.retryAttempts) {
                    break;
                }

                // Wait before retry
                if (attempt < this.retryAttempts) {
                    await this.sleep(this.retryDelay * attempt);
                }
            }
        }

        this.emit('request:error', { url, error: lastError });
        this.emit('request:complete', { url, success: false });

        throw lastError;
    }

    /**
     * GET request
     */
    async get(endpoint, params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = queryString ? `${endpoint}?${queryString}` : endpoint;
        return this.request(url, { method: 'GET' });
    }

    /**
     * POST request
     */
    async post(endpoint, data = {}) {
        return this.request(endpoint, { method: 'POST', data });
    }

    /**
     * PUT request
     */
    async put(endpoint, data = {}) {
        return this.request(endpoint, { method: 'PUT', data });
    }

    /**
     * DELETE request
     */
    async delete(endpoint, data = {}) {
        return this.request(endpoint, { method: 'DELETE', data });
    }

    // =============================================================================
    // DEVICE API METHODS
    // =============================================================================

    /**
     * Get all devices
     */
    async getDevices() {
        return this.get('/devices');
    }

    /**
     * Get device by ID
     */
    async getDevice(deviceId) {
        return this.get(`/devices/${deviceId}`);
    }

    /**
     * Create new device
     */
    async createDevice(deviceData) {
        return this.post('/devices', deviceData);
    }

    /**
     * Update device
     */
    async updateDevice(deviceId, deviceData) {
        return this.put(`/devices/${deviceId}`, deviceData);
    }

    /**
     * Delete device
     */
    async deleteDevice(deviceId) {
        return this.delete(`/devices/${deviceId}`);
    }

    /**
     * Connect device
     */
    async connectDevice(deviceId) {
        return this.post(`/devices/${deviceId}/connect`);
    }

    /**
     * Disconnect device
     */
    async disconnectDevice(deviceId) {
        return this.post(`/devices/${deviceId}/disconnect`);
    }

    /**
     * Get device QR code
     */
    async getQRCode(deviceId) {
        return this.get(`/devices/${deviceId}/qr`);
    }

    /**
     * Get device status
     */
    async getDeviceStatus(deviceId) {
        return this.get(`/devices/${deviceId}/status`);
    }

    // =============================================================================
    // MESSAGE API METHODS
    // =============================================================================

    /**
     * Send text message
     */
    async sendMessage(deviceId, to, message) {
        return this.post('/messages/send', {
            deviceId,
            to,
            message,
            type: 'text'
        });
    }

    /**
     * Send media message
     */
    async sendMedia(deviceId, to, mediaData) {
        return this.post('/messages/send-media', {
            deviceId,
            to,
            ...mediaData
        });
    }

    /**
     * Get message history
     */
    async getMessages(deviceId, params = {}) {
        return this.get(`/messages/${deviceId}`, params);
    }

    // =============================================================================
    // API TOKEN METHODS
    // =============================================================================

    /**
     * Get API tokens
     */
    async getTokens() {
        return this.get('/tokens');
    }

    /**
     * Generate new token
     */
    async generateToken(deviceId, tokenName) {
        return this.post('/tokens/generate', { deviceId, tokenName });
    }

    /**
     * Revoke token
     */
    async revokeToken(tokenId) {
        return this.delete(`/tokens/${tokenId}`);
    }

    // =============================================================================
    // LOG METHODS
    // =============================================================================

    /**
     * Get API logs
     */
    async getApiLogs(params = {}) {
        return this.get('/logs/api', params);
    }

    /**
     * Get message logs
     */
    async getMessageLogs(deviceId, params = {}) {
        return this.get(`/logs/messages/${deviceId}`, params);
    }

    /**
     * Get system logs
     */
    async getSystemLogs(params = {}) {
        return this.get('/logs/system', params);
    }

    // =============================================================================
    // WEBHOOK METHODS
    // =============================================================================

    /**
     * Get webhook logs
     */
    async getWebhookLogs(deviceId, params = {}) {
        return this.get(`/webhooks/${deviceId}`, params);
    }

    /**
     * Test webhook
     */
    async testWebhook(deviceId, webhookUrl) {
        return this.post('/webhooks/test', { deviceId, webhookUrl });
    }

    // =============================================================================
    // DASHBOARD/STATS METHODS
    // =============================================================================

    /**
     * Get dashboard statistics
     */
    async getDashboardStats() {
        return this.get('/dashboard/stats');
    }

    /**
     * Get system health
     */
    async getSystemHealth() {
        return this.get('/system/health');
    }
}

// =============================================================================
// UI HELPER FUNCTIONS
// =============================================================================

/**
 * UI utility class for common operations
 */
class UIHelper {
    constructor() {
        this.toastContainer = null;
        this.loadingElements = new Set();
        this.init();
    }

    /**
     * Initialize UI helper
     */
    init() {
        this.createToastContainer();
        this.setupGlobalErrorHandler();
    }

    /**
     * Create toast container
     */
    createToastContainer() {
        if (!document.querySelector('.toast-container')) {
            this.toastContainer = document.createElement('div');
            this.toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
            this.toastContainer.style.zIndex = '9999';
            document.body.appendChild(this.toastContainer);
        } else {
            this.toastContainer = document.querySelector('.toast-container');
        }
    }

    /**
     * Setup global error handler
     */
    setupGlobalErrorHandler() {
        window.addEventListener('unhandledrejection', (event) => {
            this.showError('An unexpected error occurred: ' + event.reason);
            console.error('Unhandled promise rejection:', event.reason);
        });
    }

    /**
     * Show toast notification
     */
    showToast(message, type = 'info', duration = 5000) {
        const toastId = 'toast-' + Date.now();
        const bgClass = {
            'success': 'bg-success',
            'error': 'bg-danger',
            'warning': 'bg-warning',
            'info': 'bg-info'
        }[type] || 'bg-info';

        const iconClass = {
            'success': 'fas fa-check-circle',
            'error': 'fas fa-exclamation-circle',
            'warning': 'fas fa-exclamation-triangle',
            'info': 'fas fa-info-circle'
        }[type] || 'fas fa-info-circle';

        const toastHTML = `
            <div id="${toastId}" class="toast align-items-center text-white ${bgClass} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="${iconClass} me-2"></i>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;

        this.toastContainer.insertAdjacentHTML('beforeend', toastHTML);

        const toastElement = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastElement, { delay: duration });
        toast.show();

        // Remove toast element after it's hidden
        toastElement.addEventListener('hidden.bs.toast', () => {
            toastElement.remove();
        });

        return toast;
    }

    /**
     * Show success message
     */
    showSuccess(message, duration) {
        return this.showToast(message, 'success', duration);
    }

    /**
     * Show error message
     */
    showError(message, duration) {
        return this.showToast(message, 'error', duration);
    }

    /**
     * Show warning message
     */
    showWarning(message, duration) {
        return this.showToast(message, 'warning', duration);
    }

    /**
     * Show info message
     */
    showInfo(message, duration) {
        return this.showToast(message, 'info', duration);
    }

    /**
     * Show loading spinner on element
     */
    showLoading(element, text = 'Loading...') {
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }

        if (!element) return;

        // Store original content
        if (!element.dataset.originalContent) {
            element.dataset.originalContent = element.innerHTML;
        }

        // Add to loading set
        this.loadingElements.add(element);

        // Show loading state
        element.disabled = true;
        element.innerHTML = `
            <span class="loading-spinner"></span>
            ${text}
        `;
        element.classList.add('loading');
    }

    /**
     * Hide loading spinner from element
     */
    hideLoading(element) {
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }

        if (!element) return;

        // Remove from loading set
        this.loadingElements.delete(element);

        // Restore original state
        element.disabled = false;
        element.innerHTML = element.dataset.originalContent || '';
        element.classList.remove('loading');
        delete element.dataset.originalContent;
    }

    /**
     * Show modal
     */
    showModal(modalId, options = {}) {
        const modalElement = document.getElementById(modalId);
        if (!modalElement) {
            console.error(`Modal with ID ${modalId} not found`);
            return null;
        }

        const modal = new bootstrap.Modal(modalElement, options);
        modal.show();
        return modal;
    }

    /**
     * Hide modal
     */
    hideModal(modalId) {
        const modalElement = document.getElementById(modalId);
        if (modalElement) {
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) {
                modal.hide();
            }
        }
    }

    /**
     * Confirm dialog
     */
    async confirm(message, title = 'Confirm', options = {}) {
        return new Promise((resolve) => {
            const modalId = 'confirm-modal-' + Date.now();
            const {
                confirmText = 'Yes',
                cancelText = 'Cancel',
                confirmClass = 'btn-primary',
                cancelClass = 'btn-secondary'
            } = options;

            const modalHTML = `
                <div class="modal fade" id="${modalId}" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">${title}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p>${message}</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn ${cancelClass}" data-bs-dismiss="modal">${cancelText}</button>
                                <button type="button" class="btn ${confirmClass}" id="${modalId}-confirm">${confirmText}</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', modalHTML);

            const modalElement = document.getElementById(modalId);
            const modal = new bootstrap.Modal(modalElement);

            // Handle confirm button
            document.getElementById(`${modalId}-confirm`).addEventListener('click', () => {
                modal.hide();
                resolve(true);
            });

            // Handle modal close
            modalElement.addEventListener('hidden.bs.modal', () => {
                modalElement.remove();
                resolve(false);
            });

            modal.show();
        });
    }

    /**
     * Format date
     */
    formatDate(date, format = 'datetime') {
        if (!date) return '-';

        const d = new Date(date);
        if (isNaN(d.getTime())) return '-';

        const options = {
            'date': { year: 'numeric', month: '2-digit', day: '2-digit' },
            'time': { hour: '2-digit', minute: '2-digit', second: '2-digit' },
            'datetime': {
                year: 'numeric', month: '2-digit', day: '2-digit',
                hour: '2-digit', minute: '2-digit', second: '2-digit'
            },
            'relative': null
        };

        if (format === 'relative') {
            return this.getRelativeTime(d);
        }

        return d.toLocaleString('en-US', options[format] || options.datetime);
    }

    /**
     * Get relative time (e.g., "2 minutes ago")
     */
    getRelativeTime(date) {
        const now = new Date();
        const diff = now - date;
        const seconds = Math.floor(diff / 1000);
        const minutes = Math.floor(seconds / 60);
        const hours = Math.floor(minutes / 60);
        const days = Math.floor(hours / 24);

        if (seconds < 60) return 'Just now';
        if (minutes < 60) return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
        if (hours < 24) return `${hours} hour${hours > 1 ? 's' : ''} ago`;
        if (days < 7) return `${days} day${days > 1 ? 's' : ''} ago`;

        return this.formatDate(date, 'date');
    }

    /**
     * Format bytes
     */
    formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';

        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];

        const i = Math.floor(Math.log(bytes) / Math.log(k));

        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }

    /**
     * Debounce function
     */
    debounce(func, wait, immediate = false) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                timeout = null;
                if (!immediate) func(...args);
            };
            const callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func(...args);
        };
    }

    /**
     * Throttle function
     */
    throttle(func, limit) {
        let inThrottle;
        return function executedFunction(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }

    /**
     * Copy text to clipboard
     */
    async copyToClipboard(text) {
        try {
            await navigator.clipboard.writeText(text);
            this.showSuccess('Copied to clipboard!');
            return true;
        } catch (err) {
            console.error('Failed to copy text: ', err);
            this.showError('Failed to copy to clipboard');
            return false;
        }
    }

    /**
     * Download data as file
     */
    downloadFile(data, filename, type = 'application/json') {
        const blob = new Blob([data], { type });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
    }
}

// =============================================================================
// GLOBAL INSTANCES & INITIALIZATION
// =============================================================================

// Create global instances
let whatsappAPI;
let uiHelper;

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function () {
    // Initialize API client
    whatsappAPI = new WhatsAppAPI({
        debug: window.location.hostname === 'localhost',
        baseURL: '/api'
    });

    // Initialize UI helper
    uiHelper = new UIHelper();

    // Setup global API event listeners
    whatsappAPI.on('request:start', (data) => {
        console.log('API Request started:', data.url);
    });

    whatsappAPI.on('request:error', (data) => {
        console.error('API Request failed:', data.url, data.error);

        // Show user-friendly error message
        let errorMessage = 'Request failed';
        if (data.error.message.includes('Failed to fetch')) {
            errorMessage = 'Network error. Please check your connection.';
        } else if (data.error.message.includes('401')) {
            errorMessage = 'Unauthorized. Please login again.';
        } else if (data.error.message.includes('403')) {
            errorMessage = 'Access denied.';
        } else if (data.error.message.includes('404')) {
            errorMessage = 'Resource not found.';
        } else if (data.error.message.includes('500')) {
            errorMessage = 'Server error. Please try again later.';
        } else {
            errorMessage = data.error.message;
        }

        uiHelper.showError(errorMessage);
    });

    // Expose globally for easy access in other scripts
    window.whatsappAPI = whatsappAPI;
    window.uiHelper = uiHelper;

    console.log('WhatsApp Monitor API Client initialized');
});

// =============================================================================
// UTILITY FUNCTIONS FOR FORMS
// =============================================================================

/**
 * Enhanced form handler with validation and API integration
 */
class FormHandler {
    constructor(formSelector, options = {}) {
        this.form = document.querySelector(formSelector);
        this.options = {
            validateOnInput: true,
            showSuccess: true,
            resetOnSuccess: false,
            ...options
        };

        if (this.form) {
            this.init();
        }
    }

    init() {
        // Add form submit handler
        this.form.addEventListener('submit', this.handleSubmit.bind(this));

        // Add real-time validation if enabled
        if (this.options.validateOnInput) {
            this.form.addEventListener('input', this.handleInput.bind(this));
        }
    }

    async handleSubmit(event) {
        event.preventDefault();

        const submitBtn = this.form.querySelector('[type="submit"]');

        try {
            // Show loading state
            if (submitBtn) {
                uiHelper.showLoading(submitBtn, 'Processing...');
            }

            // Validate form
            if (!this.validateForm()) {
                return;
            }

            // Get form data
            const formData = this.getFormData();

            // Call submit handler if provided
            if (this.options.onSubmit) {
                const result = await this.options.onSubmit(formData);

                if (result && result.success !== false) {
                    if (this.options.showSuccess) {
                        uiHelper.showSuccess(result.message || 'Operation completed successfully');
                    }

                    if (this.options.resetOnSuccess) {
                        this.form.reset();
                    }

                    if (this.options.onSuccess) {
                        this.options.onSuccess(result);
                    }
                }
            }

        } catch (error) {
            console.error('Form submission error:', error);
            uiHelper.showError(error.message || 'An error occurred');

            if (this.options.onError) {
                this.options.onError(error);
            }
        } finally {
            // Hide loading state
            if (submitBtn) {
                uiHelper.hideLoading(submitBtn);
            }
        }
    }

    handleInput(event) {
        const field = event.target;
        this.validateField(field);
    }

    validateForm() {
        let isValid = true;
        const fields = this.form.querySelectorAll('input, select, textarea');

        fields.forEach(field => {
            if (!this.validateField(field)) {
                isValid = false;
            }
        });

        return isValid;
    }

    validateField(field) {
        const value = field.value.trim();
        const rules = this.getValidationRules(field);
        let isValid = true;
        let errorMessage = '';

        // Required validation
        if (rules.required && !value) {
            isValid = false;
            errorMessage = 'This field is required';
        }

        // Email validation
        if (isValid && rules.email && value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                isValid = false;
                errorMessage = 'Please enter a valid email address';
            }
        }

        // Phone validation
        if (isValid && rules.phone && value) {
            const phoneRegex = /^[\+]?[1-9][\d]{0,15}$/;
            if (!phoneRegex.test(value)) {
                isValid = false;
                errorMessage = 'Please enter a valid phone number';
            }
        }

        // Min length validation
        if (isValid && rules.minLength && value.length < rules.minLength) {
            isValid = false;
            errorMessage = `Minimum ${rules.minLength} characters required`;
        }

        // Max length validation
        if (isValid && rules.maxLength && value.length > rules.maxLength) {
            isValid = false;
            errorMessage = `Maximum ${rules.maxLength} characters allowed`;
        }

        // Update field UI
        this.updateFieldUI(field, isValid, errorMessage);

        return isValid;
    }

    getValidationRules(field) {
        const rules = {};

        // Get rules from data attributes
        rules.required = field.hasAttribute('required');
        rules.email = field.type === 'email';
        rules.phone = field.dataset.validation === 'phone';
        rules.minLength = field.dataset.minLength ? parseInt(field.dataset.minLength) : null;
        rules.maxLength = field.dataset.maxLength ? parseInt(field.dataset.maxLength) : null;

        return rules;
    }

    updateFieldUI(field, isValid, errorMessage) {
        const fieldGroup = field.closest('.form-group') || field.closest('.mb-3');
        let errorElement = fieldGroup?.querySelector('.invalid-feedback');

        // Remove existing validation classes
        field.classList.remove('is-valid', 'is-invalid');

        if (!isValid && errorMessage) {
            // Add error state
            field.classList.add('is-invalid');

            // Create or update error message
            if (!errorElement) {
                errorElement = document.createElement('div');
                errorElement.className = 'invalid-feedback';
                field.parentNode.appendChild(errorElement);
            }
            errorElement.textContent = errorMessage;
        } else if (isValid && field.value.trim()) {
            // Add success state
            field.classList.add('is-valid');

            // Remove error message
            if (errorElement) {
                errorElement.remove();
            }
        } else {
            // Remove error message for empty valid fields
            if (errorElement) {
                errorElement.remove();
            }
        }
    }

    getFormData() {
        const formData = new FormData(this.form);
        const data = {};

        for (let [key, value] of formData.entries()) {
            // Handle multiple values for the same key (checkboxes, multi-select)
            if (data[key]) {
                if (Array.isArray(data[key])) {
                    data[key].push(value);
                } else {
                    data[key] = [data[key], value];
                }
            } else {
                data[key] = value;
            }
        }

        return data;
    }

    reset() {
        this.form.reset();

        // Remove validation classes
        const fields = this.form.querySelectorAll('input, select, textarea');
        fields.forEach(field => {
            field.classList.remove('is-valid', 'is-invalid');
        });

        // Remove error messages
        const errorElements = this.form.querySelectorAll('.invalid-feedback');
        errorElements.forEach(element => element.remove());
    }
}

// Make FormHandler available globally
window.FormHandler = FormHandler;