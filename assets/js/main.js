/**
 * Main JavaScript File
 * Deliverance Church Management System
 * 
 * Contains all common JavaScript functions and utilities
 */

// Global variables
window.ChurchCMS = window.ChurchCMS || {};

// Configuration
ChurchCMS.config = {
    ajaxTimeout: 30000,
    autoSaveInterval: 30000,
    notificationCheckInterval: 60000,
    sessionWarningTime: 300000, // 5 minutes
    animationDuration: 300,
    debounceDelay: 300
};

// =====================================================
// UTILITY FUNCTIONS
// =====================================================

/**
 * Debounce function to limit function calls
 * @param {Function} func - Function to debounce
 * @param {number} wait - Wait time in milliseconds
 * @param {boolean} immediate - Execute immediately
 * @returns {Function} Debounced function
 */
ChurchCMS.debounce = function(func, wait, immediate) {
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
};

/**
 * Throttle function to limit function execution rate
 * @param {Function} func - Function to throttle
 * @param {number} limit - Time limit in milliseconds
 * @returns {Function} Throttled function
 */
ChurchCMS.throttle = function(func, limit) {
    let inThrottle;
    return function(...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
};

/**
 * Format currency amount
 * @param {number} amount - Amount to format
 * @param {string} currency - Currency symbol
 * @returns {string} Formatted currency string
 */
ChurchCMS.formatCurrency = function(amount, currency = 'Ksh') {
    if (isNaN(amount)) return currency + ' 0.00';
    return currency + ' ' + parseFloat(amount).toLocaleString('en-KE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
};

/**
 * Format date for display
 * @param {string|Date} dateString - Date to format
 * @param {boolean} includeTime - Include time in format
 * @returns {string} Formatted date string
 */
ChurchCMS.formatDate = function(dateString, includeTime = false) {
    if (!dateString || dateString === '0000-00-00' || dateString === '0000-00-00 00:00:00') {
        return '-';
    }
    
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return '-';
    
    const options = {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    };
    
    if (includeTime) {
        options.hour = '2-digit';
        options.minute = '2-digit';
        options.hour12 = false;
    }
    
    return date.toLocaleDateString('en-GB', options) + 
           (includeTime ? ' ' + date.toLocaleTimeString('en-GB', {hour12: false}) : '');
};

/**
 * Calculate time ago from date
 * @param {string|Date} dateString - Date to calculate from
 * @returns {string} Time ago string
 */
ChurchCMS.timeAgo = function(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);
    
    if (seconds < 60) return 'just now';
    if (seconds < 3600) return Math.floor(seconds / 60) + ' minutes ago';
    if (seconds < 86400) return Math.floor(seconds / 3600) + ' hours ago';
    if (seconds < 2592000) return Math.floor(seconds / 86400) + ' days ago';
    if (seconds < 31104000) return Math.floor(seconds / 2592000) + ' months ago';
    
    return Math.floor(seconds / 31104000) + ' years ago';
};

/**
 * Generate unique ID
 * @param {string} prefix - ID prefix
 * @returns {string} Unique ID
 */
ChurchCMS.generateId = function(prefix = 'id') {
    return prefix + '_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
};

/**
 * Validate email address
 * @param {string} email - Email to validate
 * @returns {boolean} Is valid email
 */
ChurchCMS.isValidEmail = function(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
};

/**
 * Validate phone number (Kenyan format)
 * @param {string} phone - Phone number to validate
 * @returns {boolean} Is valid phone number
 */
ChurchCMS.isValidPhone = function(phone) {
    const phoneRegex = /^(\+254|0)[7-9]\d{8}$/;
    return phoneRegex.test(phone.replace(/[\s-]/g, ''));
};

/**
 * Format phone number
 * @param {string} phone - Phone number to format
 * @returns {string} Formatted phone number
 */
ChurchCMS.formatPhone = function(phone) {
    const cleaned = phone.replace(/[^\d+]/g, '');
    
    if (cleaned.startsWith('+254')) {
        return cleaned;
    } else if (cleaned.startsWith('254')) {
        return '+' + cleaned;
    } else if (cleaned.startsWith('0') && cleaned.length === 10) {
        return '+254' + cleaned.substr(1);
    } else if (cleaned.length === 9) {
        return '+254' + cleaned;
    }
    
    return phone; // Return original if can't format
};

// =====================================================
// UI COMPONENTS
// =====================================================

/**
 * Show loading overlay
 * @param {string} message - Loading message
 */
ChurchCMS.showLoading = function(message = 'Please wait...') {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        const messageEl = overlay.querySelector('.text-center div:last-child');
        if (messageEl) messageEl.textContent = message;
        overlay.classList.remove('d-none');
    }
};

/**
 * Hide loading overlay
 */
ChurchCMS.hideLoading = function() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.classList.add('d-none');
    }
};

/**
 * Show toast notification
 * @param {string} message - Notification message
 * @param {string} type - Notification type (success, error, warning, info)
 * @param {number} duration - Display duration in milliseconds
 */
ChurchCMS.showToast = function(message, type = 'info', duration = 5000) {
    // Create toast container if it doesn't exist
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '1055';
        document.body.appendChild(container);
    }
    
    // Map type to Bootstrap color
    const colorMap = {
        success: 'success',
        error: 'danger',
        warning: 'warning',
        info: 'info'
    };
    const bgColor = colorMap[type] || 'info';
    
    // Create toast element
    const toastId = ChurchCMS.generateId('toast');
    const toastHtml = `
        <div id="${toastId}" class="toast align-items-center text-white bg-${bgColor} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', toastHtml);
    
    // Initialize and show toast
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, { delay: duration });
    toast.show();
    
    // Remove element after hidden
    toastElement.addEventListener('hidden.bs.toast', function() {
        this.remove();
    });
    
    return toast;
};

/**
 * Show confirmation dialog
 * @param {string} message - Confirmation message
 * @param {Function} onConfirm - Callback for confirm
 * @param {Function} onCancel - Callback for cancel
 * @param {string} title - Dialog title
 */
ChurchCMS.showConfirm = function(message, onConfirm, onCancel, title = 'Confirm Action') {
    const modalId = ChurchCMS.generateId('confirmModal');
    const modalHtml = `
        <div class="modal fade" id="${modalId}" tabindex="-1" data-bs-backdrop="static">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-church-blue text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-question-circle me-2"></i>${title}
                        </h5>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">${message}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-church-primary" id="${modalId}_confirm">Confirm</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    const modal = new bootstrap.Modal(document.getElementById(modalId));
    const confirmBtn = document.getElementById(`${modalId}_confirm`);
    
    confirmBtn.addEventListener('click', function() {
        modal.hide();
        if (onConfirm) onConfirm();
    });
    
    document.getElementById(modalId).addEventListener('hidden.bs.modal', function() {
        this.remove();
        if (onCancel) onCancel();
    });
    
    modal.show();
    return modal;
};

/**
 * Copy text to clipboard
 * @param {string} text - Text to copy
 * @param {string} successMessage - Success message
 */
ChurchCMS.copyToClipboard = function(text, successMessage = 'Copied to clipboard!') {
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(() => {
            ChurchCMS.showToast(successMessage, 'success');
        }).catch(err => {
            console.error('Could not copy text: ', err);
            ChurchCMS.showToast('Failed to copy text', 'error');
        });
    } else {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            document.execCommand('copy');
            ChurchCMS.showToast(successMessage, 'success');
        } catch (err) {
            console.error('Could not copy text: ', err);
            ChurchCMS.showToast('Failed to copy text', 'error');
        }
        
        document.body.removeChild(textArea);
    }
};

// =====================================================
// AJAX UTILITIES
// =====================================================

/**
 * Make AJAX request
 * @param {Object} options - Request options
 * @returns {Promise} Request promise
 */
ChurchCMS.ajax = function(options) {
    const defaults = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        timeout: ChurchCMS.config.ajaxTimeout
    };
    
    const config = Object.assign({}, defaults, options);
    
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        const timeoutId = setTimeout(() => {
            xhr.abort();
            reject(new Error('Request timeout'));
        }, config.timeout);
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === XMLHttpRequest.DONE) {
                clearTimeout(timeoutId);
                
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        resolve(response);
                    } catch (e) {
                        resolve(xhr.responseText);
                    }
                } else {
                    reject(new Error(`HTTP ${xhr.status}: ${xhr.statusText}`));
                }
            }
        };
        
        xhr.onerror = function() {
            clearTimeout(timeoutId);
            reject(new Error('Network error'));
        };
        
        xhr.open(config.method, config.url);
        
        // Set headers
        for (const [key, value] of Object.entries(config.headers)) {
            xhr.setRequestHeader(key, value);
        }
        
        // Send request
        if (config.data) {
            if (config.headers['Content-Type'] === 'application/json') {
                xhr.send(JSON.stringify(config.data));
            } else {
                xhr.send(config.data);
            }
        } else {
            xhr.send();
        }
    });
};

/**
 * GET request helper
 * @param {string} url - Request URL
 * @param {Object} params - Query parameters
 * @returns {Promise} Request promise
 */
ChurchCMS.get = function(url, params = {}) {
    const queryString = new URLSearchParams(params).toString();
    const fullUrl = queryString ? `${url}?${queryString}` : url;
    
    return ChurchCMS.ajax({
        method: 'GET',
        url: fullUrl
    });
};

/**
 * POST request helper
 * @param {string} url - Request URL
 * @param {Object} data - Request data
 * @returns {Promise} Request promise
 */
ChurchCMS.post = function(url, data = {}) {
    return ChurchCMS.ajax({
        method: 'POST',
        url: url,
        data: data
    });
};

/**
 * PUT request helper
 * @param {string} url - Request URL
 * @param {Object} data - Request data
 * @returns {Promise} Request promise
 */
ChurchCMS.put = function(url, data = {}) {
    return ChurchCMS.ajax({
        method: 'PUT',
        url: url,
        data: data
    });
};

/**
 * DELETE request helper
 * @param {string} url - Request URL
 * @returns {Promise} Request promise
 */
ChurchCMS.delete = function(url) {
    return ChurchCMS.ajax({
        method: 'DELETE',
        url: url
    });
};

// =====================================================
// FORM UTILITIES
// =====================================================

/**
 * Serialize form data to object
 * @param {HTMLFormElement} form - Form element
 * @returns {Object} Form data object
 */
ChurchCMS.serializeForm = function(form) {
    const formData = new FormData(form);
    const data = {};
    
    for (const [key, value] of formData.entries()) {
        if (data[key]) {
            // Handle multiple values (checkboxes, multiple select)
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
};

/**
 * Validate form fields
 * @param {HTMLFormElement} form - Form element
 * @param {Object} rules - Validation rules
 * @returns {Object} Validation result
 */
ChurchCMS.validateForm = function(form, rules = {}) {
    const errors = {};
    const formData = ChurchCMS.serializeForm(form);
    
    // Clear previous errors
    form.querySelectorAll('.is-invalid').forEach(el => {
        el.classList.remove('is-invalid');
    });
    form.querySelectorAll('.invalid-feedback').forEach(el => {
        el.remove();
    });
    
    // Apply validation rules
    for (const [field, fieldRules] of Object.entries(rules)) {
        const value = formData[field] || '';
        const element = form.querySelector(`[name="${field}"]`);
        
        for (const rule of fieldRules) {
            if (rule === 'required' && !value.trim()) {
                errors[field] = 'This field is required';
                break;
            }
            
            if (rule === 'email' && value && !ChurchCMS.isValidEmail(value)) {
                errors[field] = 'Please enter a valid email address';
                break;
            }
            
            if (rule === 'phone' && value && !ChurchCMS.isValidPhone(value)) {
                errors[field] = 'Please enter a valid phone number';
                break;
            }
            
            if (rule.startsWith('min:')) {
                const minLength = parseInt(rule.split(':')[1]);
                if (value.length < minLength) {
                    errors[field] = `Minimum length is ${minLength} characters`;
                    break;
                }
            }
            
            if (rule.startsWith('max:')) {
                const maxLength = parseInt(rule.split(':')[1]);
                if (value.length > maxLength) {
                    errors[field] = `Maximum length is ${maxLength} characters`;
                    break;
                }
            }
        }
        
        // Show error in UI
        if (errors[field] && element) {
            element.classList.add('is-invalid');
            const feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            feedback.textContent = errors[field];
            element.parentNode.appendChild(feedback);
        }
    }
    
    return {
        isValid: Object.keys(errors).length === 0,
        errors: errors,
        data: formData
    };
};

/**
 * Auto-save form data
 * @param {HTMLFormElement} form - Form element
 * @param {string} key - Storage key
 */
ChurchCMS.autoSaveForm = function(form, key) {
    if (!form.id) {
        form.id = ChurchCMS.generateId('form');
    }
    
    const storageKey = key || `autosave_${form.id}`;
    
    // Load saved data
    const savedData = localStorage.getItem(storageKey);
    if (savedData) {
        try {
            const data = JSON.parse(savedData);
            Object.entries(data).forEach(([name, value]) => {
                const field = form.querySelector(`[name="${name}"]`);
                if (field && field.type !== 'password' && field.type !== 'file') {
                    if (field.type === 'checkbox' || field.type === 'radio') {
                        field.checked = value;
                    } else {
                        field.value = value;
                    }
                }
            });
        } catch (e) {
            console.error('Error loading saved form data:', e);
        }
    }
    
    // Save data on input
    const saveData = ChurchCMS.debounce(() => {
        const data = ChurchCMS.serializeForm(form);
        // Remove sensitive fields
        delete data.password;
        delete data.password_confirm;
        localStorage.setItem(storageKey, JSON.stringify(data));
    }, 1000);
    
    form.addEventListener('input', saveData);
    form.addEventListener('change', saveData);
    
    // Clear saved data on submit
    form.addEventListener('submit', () => {
        localStorage.removeItem(storageKey);
    });
};

// =====================================================
// DATA TABLE UTILITIES
// =====================================================

/**
 * Initialize data table
 * @param {string} selector - Table selector
 * @param {Object} options - DataTable options
 */
ChurchCMS.initDataTable = function(selector, options = {}) {
    if (typeof jQuery === 'undefined' || !jQuery.fn.DataTable) {
        console.warn('DataTables library not loaded');
        return null;
    }
    
    const defaultOptions = {
        responsive: true,
        pageLength: 25,
        order: [[0, 'desc']],
        language: {
            search: "Search:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "No entries found",
            infoFiltered: "(filtered from _MAX_ total entries)",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
            },
            emptyTable: "No data available"
        },
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
             '<"row"<"col-sm-12"tr>>' +
             '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        columnDefs: [
            { targets: 'no-sort', orderable: false }
        ]
    };
    
    const config = Object.assign({}, defaultOptions, options);
    
    try {
        const table = $(selector).DataTable(config);
        
        // Add export buttons if requested
        if (options.buttons || options.export) {
            new $.fn.dataTable.Buttons(table, {
                buttons: [
                    {
                        extend: 'excel',
                        text: '<i class="fas fa-file-excel"></i> Excel',
                        className: 'btn btn-success btn-sm me-1'
                    },
                    {
                        extend: 'pdf',
                        text: '<i class="fas fa-file-pdf"></i> PDF',
                        className: 'btn btn-danger btn-sm me-1'
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print"></i> Print',
                        className: 'btn btn-info btn-sm'
                    }
                ]
            });
            
            table.buttons().container().appendTo($(selector + '_wrapper .col-md-6:eq(0)'));
        }
        
        return table;
    } catch (error) {
        console.error('Error initializing DataTable:', error);
        return null;
    }
};

// =====================================================
// NAV ACTIVE / OPEN STATE (NEW)
// =====================================================

/**
 * Normalize a URL to a comparable path+query (relative to BASE_URL if present)
 * @param {string} url
 * @param {boolean} includeQuery
 */
ChurchCMS._normalizeUrl = function(url, includeQuery = true) {
    try {
        const u = new URL(url, window.location.origin);
        let basePath = (window.BASE_URL || '').replace(window.location.origin, '');
        if (basePath && basePath.endsWith('/')) basePath = basePath.slice(0, -1);

        // Strip the app's base path if present
        let path = u.pathname;
        if (basePath && path.startsWith(basePath)) path = path.slice(basePath.length);

        // Normalize trailing slash & index.php
        path = path.replace(/\/+$/, '');
        path = path.replace(/\/index\.php$/i, '');

        // Optionally include query
        const query = includeQuery ? (u.search || '') : '';
        return (path || '/') + query;
    } catch {
        // Fallback for weird hrefs (like '#', 'javascript:')
        return '';
    }
};

/**
 * Return a "match score" between a link href and current URL.
 * Higher is better. 0 means "not a match".
 * Supports data-active-pattern (regex) for custom matching.
 */
ChurchCMS._matchUrlScore = function(linkEl, currentPathQuery, currentPathOnly) {
    const href = linkEl.getAttribute('href') || '';
    if (!href || href[0] === '#' || href.startsWith('javascript:')) return 0;

    // custom regex pattern support (optional)
    const pattern = linkEl.getAttribute('data-active-pattern');
    if (pattern) {
        try {
            const rx = new RegExp(pattern);
            if (rx.test(window.location.href)) return 1000; // strongest match
        } catch (e) {
            console.warn('Invalid data-active-pattern:', pattern, e);
        }
    }

    const linkPathQuery = ChurchCMS._normalizeUrl(href, true);
    const linkPathOnly  = ChurchCMS._normalizeUrl(href, false);

    let score = 0;
    if (!linkPathQuery && !linkPathOnly) return 0;

    // Exact (path+query) match
    if (linkPathQuery && linkPathQuery === currentPathQuery) score = Math.max(score, 500);
    // Exact (path) match
    if (linkPathOnly && linkPathOnly === currentPathOnly) score = Math.max(score, 400);
    // "Starts with" path (module grouping)
    if (linkPathOnly && currentPathOnly.startsWith(linkPathOnly)) {
        // reward longer prefixes (deeper modules)
        score = Math.max(score, 200 + Math.min(150, linkPathOnly.length));
    }

    return score;
};

/**
 * Highlight active nav link and open its parent collapses (supports nested).
 * @param {string} sidebarSelector
 */
ChurchCMS.highlightActiveNav = function(sidebarSelector = '#sidebar') {
    const sidebar = document.querySelector(sidebarSelector);
    if (!sidebar) return;

    const currentPathQuery = ChurchCMS._normalizeUrl(window.location.href, true);
    const currentPathOnly  = ChurchCMS._normalizeUrl(window.location.href, false);

    const links = sidebar.querySelectorAll('a.nav-link[href]');
    let best = { el: null, score: 0 };

    links.forEach((a) => {
        const score = ChurchCMS._matchUrlScore(a, currentPathQuery, currentPathOnly);
        if (score > best.score) best = { el: a, score };
    });

    if (!best.el) return;

    // Remove previous active states
    sidebar.querySelectorAll('.nav-link.active').forEach(el => el.classList.remove('active'));
    sidebar.querySelectorAll('.nav-item.active').forEach(el => el.classList.remove('active'));

    // Mark the best link active
    best.el.classList.add('active');
    const li = best.el.closest('.nav-item');
    if (li) li.classList.add('active');

    // If inside a collapse, open all ancestor collapses
    const openParents = (el) => {
        const collapse = el.closest('.collapse');
        if (!collapse) return;
        collapse.classList.add('show');

        // Find the toggler that controls this collapse
        const id = collapse.id ? ('#' + collapse.id) : null;
        if (id) {
            const toggler = sidebar.querySelector(`[data-bs-toggle="collapse"][data-bs-target="${id}"], a[href="${id}"]`);
            if (toggler) {
                toggler.classList.add('active');
                toggler.setAttribute('aria-expanded', 'true');
                const togglerItem = toggler.closest('.nav-item');
                if (togglerItem) togglerItem.classList.add('active');
            }
        }
        // Recurse up for nested menus
        openParents(collapse.parentElement);
    };

    // If the best link toggles a collapse, open its target too
    const target = best.el.getAttribute('data-bs-target');
    if (target && target.startsWith('#')) {
        const collapse = sidebar.querySelector(target);
        if (collapse) {
            collapse.classList.add('show');
            best.el.classList.add('active');
            best.el.setAttribute('aria-expanded', 'true');
        }
    }

    openParents(best.el);
};

/**
 * Re-run highlight when sidebar content is loaded dynamically (optional).
 */
ChurchCMS.observeSidebarForActiveState = function(sidebarSelector = '#sidebar') {
    const sidebar = document.querySelector(sidebarSelector);
    if (!sidebar || !window.MutationObserver) return;
    const mo = new MutationObserver(ChurchCMS.debounce(() => {
        ChurchCMS.highlightActiveNav(sidebarSelector);
    }, 100));
    mo.observe(sidebar, { childList: true, subtree: true });
};

// =====================================================
// NOTIFICATION SYSTEM
// =====================================================

/**
 * Check for new notifications
 */
ChurchCMS.checkNotifications = function() {
    if (!window.BASE_URL || !window.USER_ROLE) return;
    
    ChurchCMS.get(BASE_URL + 'api/notifications.php', { action: 'check_new' })
        .then(response => {
            if (response.success && response.data.has_new) {
                // Update notification badge
                const badge = document.querySelector('.navbar-nav .badge');
                if (badge) {
                    badge.textContent = response.data.count;
                    badge.classList.remove('d-none');
                }
                
                // Show urgent notifications
                if (response.data.urgent && response.data.urgent.length > 0) {
                    response.data.urgent.forEach(notification => {
                        ChurchCMS.showToast(notification.message, 'warning', 8000);
                    });
                }
            }
        })
        .catch(error => {
            console.error('Error checking notifications:', error);
        });
};

// =====================================================
// SESSION MANAGEMENT
// =====================================================

/**
 * Extend user session
 */
ChurchCMS.extendSession = function() {
    return ChurchCMS.post(BASE_URL + 'auth/extend_session.php')
        .then(response => {
            if (response.success) {
                ChurchCMS.showToast('Session extended successfully', 'success');
                return true;
            } else {
                throw new Error(response.message || 'Failed to extend session');
            }
        })
        .catch(error => {
            console.error('Error extending session:', error);
            ChurchCMS.showToast('Failed to extend session', 'error');
            return false;
        });
};

/**
 * Session timeout warning
 */
ChurchCMS.handleSessionTimeout = function() {
    let timeoutWarning = null;
    let sessionCheckInterval = null;
    
    const showWarning = () => {
        if (timeoutWarning) return; // Warning already shown
        
        ChurchCMS.showConfirm(
            'Your session will expire in 5 minutes. Do you want to extend your session?',
            () => {
                ChurchCMS.extendSession().then(success => {
                    if (success) {
                        timeoutWarning = null;
                        // Reset the timeout
                        clearInterval(sessionCheckInterval);
                        startSessionCheck();
                    }
                });
            },
            () => {
                timeoutWarning = null;
            },
            'Session Timeout Warning'
        );
        
        timeoutWarning = true;
    };
    
    const startSessionCheck = () => {
        if (typeof SESSION_TIMEOUT === 'undefined') return;
        
        let remainingTime = SESSION_TIMEOUT * 1000; // Convert to milliseconds
        
        sessionCheckInterval = setInterval(() => {
            remainingTime -= 60000; // Decrease by 1 minute
            
            // Show warning at 5 minutes
            if (remainingTime <= ChurchCMS.config.sessionWarningTime && !timeoutWarning) {
                showWarning();
            }
            
            // Auto logout when time expires
            if (remainingTime <= 0) {
                clearInterval(sessionCheckInterval);
                ChurchCMS.showToast('Your session has expired. Redirecting to login...', 'error');
                setTimeout(() => {
                    window.location.href = BASE_URL + 'auth/logout.php?expired=1';
                }, 3000);
            }
        }, 60000); // Check every minute
    };
    
    // Start session monitoring if user is logged in
    if (window.USER_ROLE) {
        startSessionCheck();
    }
};

// =====================================================
// INITIALIZATION
// =====================================================

/**
 * Initialize ChurchCMS when DOM is ready
 */
ChurchCMS.init = function() {
    console.log('ChurchCMS JavaScript initialized');
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // Handle sidebar toggle
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    
    if (sidebarToggle && sidebar && mainContent) {
        sidebarToggle.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                // Mobile: show/hide sidebar
                sidebar.classList.toggle('show');
                mainContent.classList.toggle('sidebar-open');
            } else {
                // Desktop: collapse/expand sidebar
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
            }
            
            // Save state
            const isCollapsed = sidebar.classList.contains('collapsed');
            const isMobileOpen = sidebar.classList.contains('show');
            localStorage.setItem('sidebar-collapsed', isCollapsed);
            localStorage.setItem('sidebar-mobile-open', isMobileOpen);
        });
        
        // Restore sidebar state
        const isCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';
        const isMobileOpen = localStorage.getItem('sidebar-mobile-open') === 'true';
        
        if (window.innerWidth > 768 && isCollapsed) {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('expanded');
        } else if (window.innerWidth <= 768 && isMobileOpen) {
            sidebar.classList.add('show');
            mainContent.classList.add('sidebar-open');
        }
    }

    // NEW: Highlight active module/submodule in sidebar
    ChurchCMS.highlightActiveNav('#sidebar');
    ChurchCMS.observeSidebarForActiveState('#sidebar');
    
    // Handle mobile sidebar close on overlay click
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('show')) {
            if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                sidebar.classList.remove('show');
                mainContent.classList.remove('sidebar-open');
                localStorage.setItem('sidebar-mobile-open', 'false');
            }
        }
    });
    
    // Initialize auto-save for forms
    document.querySelectorAll('form:not(.no-autosave)').forEach(form => {
        ChurchCMS.autoSaveForm(form);
    });
    
    // Handle form validation
    document.querySelectorAll('.needs-validation').forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
    
    // Handle confirmation dialogs
    document.addEventListener('click', function(e) {
        const target = e.target.closest('.confirm-delete, .confirm-action');
        if (target) {
            e.preventDefault();
            
            const message = target.dataset.message || 'Are you sure you want to perform this action?';
            const title = target.dataset.title || 'Confirm Action';
            
            ChurchCMS.showConfirm(message, () => {
                if (target.tagName === 'A') {
                    window.location.href = target.href;
                } else if (target.tagName === 'BUTTON' && target.form) {
                    target.form.submit();
                } else if (target.onclick) {
                    target.onclick();
                }
            }, null, title);
        }
    });
    
    // Handle copy to clipboard
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('copy-to-clipboard') || e.target.closest('.copy-to-clipboard')) {
            const element = e.target.classList.contains('copy-to-clipboard') ? e.target : e.target.closest('.copy-to-clipboard');
            const text = element.dataset.copy || element.textContent.trim();
            ChurchCMS.copyToClipboard(text);
        }
    });
    
    // Initialize data tables
    if (typeof jQuery !== 'undefined' && jQuery.fn.DataTable) {
        document.querySelectorAll('.data-table').forEach(table => {
            if (!table.classList.contains('dataTable')) {
                ChurchCMS.initDataTable('#' + table.id || '.data-table');
            }
        });
    }
    
    // Back to top button
    const backToTopBtn = document.getElementById('backToTop');
    if (backToTopBtn) {
        window.addEventListener('scroll', ChurchCMS.throttle(function() {
            if (window.pageYOffset > 300) {
                backToTopBtn.classList.remove('d-none');
            } else {
                backToTopBtn.classList.add('d-none');
            }
        }, 100));
        
        backToTopBtn.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }
    
    // Initialize notification checking
    if (window.USER_ROLE && window.BASE_URL) {
        // Check immediately
        ChurchCMS.checkNotifications();
        
        // Set up periodic checks
        setInterval(ChurchCMS.checkNotifications, ChurchCMS.config.notificationCheckInterval);
    }
    
    // Initialize session timeout handling
    ChurchCMS.handleSessionTimeout();
    
    // Handle browser navigation warnings
    let hasUnsavedChanges = false;
    
    document.addEventListener('input', function(e) {
        if (e.target.form && !e.target.form.classList.contains('no-confirm-leave')) {
            hasUnsavedChanges = true;
        }
    });
    
    document.addEventListener('submit', function() {
        hasUnsavedChanges = false;
    });
    
    window.addEventListener('beforeunload', function(e) {
        if (hasUnsavedChanges) {
            e.preventDefault();
            e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            return e.returnValue;
        }
    });
    
    // Real-time clock update
    const updateClock = () => {
        const clockElements = document.querySelectorAll('.current-time, #current-time');
        clockElements.forEach(element => {
            element.textContent = new Date().toLocaleTimeString('en-GB', { hour12: false });
        });
    };
    
    setInterval(updateClock, 1000);
    updateClock(); // Initial call
    
    // Handle window resize
    window.addEventListener('resize', ChurchCMS.debounce(function() {
        // Adjust sidebar behavior on window resize
        if (sidebar && mainContent) {
            if (window.innerWidth <= 768) {
                // Mobile view
                sidebar.classList.remove('collapsed');
                mainContent.classList.remove('expanded');
                if (sidebar.classList.contains('show')) {
                    mainContent.classList.add('sidebar-open');
                }
            } else {
                // Desktop view
                sidebar.classList.remove('show');
                mainContent.classList.remove('sidebar-open');
                
                const isCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';
                if (isCollapsed) {
                    sidebar.classList.add('collapsed');
                    mainContent.classList.add('expanded');
                }
            }
        }
    }, 250));
};

// =====================================================
// DOM READY INITIALIZATION
// =====================================================

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', ChurchCMS.init);
} else {
    ChurchCMS.init();
}

// Export ChurchCMS to global scope
window.ChurchCMS = ChurchCMS;
