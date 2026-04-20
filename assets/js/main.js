/**
 * Main JavaScript for Careway Welfare Management System
 */

// Document ready
$(document).ready(function() {
    initializeApp();
    setupEventListeners();
    setupFormValidation();
    setupDataTables();
    setupSelect2();
    setupDatePickers();
    setupTooltips();
    initializeCharts();
    setupNotifications();
});

// Initialize application
function initializeApp() {
    // Set CSRF token for all AJAX requests
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });
    
    // Check for dark mode preference
    if (localStorage.getItem('darkMode') === 'enabled') {
        $('body').addClass('dark-mode');
        $('#darkModeToggle i').removeClass('fa-moon').addClass('fa-sun');
    }
    
    // Set current year in footer
    $('.current-year').text(new Date().getFullYear());
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow', function() {
            $(this).remove();
        });
    }, 5000);
}

// Setup global event listeners
function setupEventListeners() {
    // Dark mode toggle
    $('#darkModeToggle').on('click', function() {
        $('body').toggleClass('dark-mode');
        const isDark = $('body').hasClass('dark-mode');
        
        if (isDark) {
            localStorage.setItem('darkMode', 'enabled');
            $(this).find('i').removeClass('fa-moon').addClass('fa-sun');
        } else {
            localStorage.setItem('darkMode', 'disabled');
            $(this).find('i').removeClass('fa-sun').addClass('fa-moon');
        }
    });
    
    // Sidebar toggle
    $('#toggleSidebar').on('click', function() {
        $('#sidebar').toggleClass('collapsed');
        $('.main-content').toggleClass('expanded');
        
        // Store sidebar state
        localStorage.setItem('sidebarCollapsed', $('#sidebar').hasClass('collapsed'));
    });
    
    // Restore sidebar state
    if (localStorage.getItem('sidebarCollapsed') === 'true') {
        $('#sidebar').addClass('collapsed');
        $('.main-content').addClass('expanded');
    }
    
    // Mobile sidebar overlay
    $(document).on('click', '.sidebar-overlay', function() {
        $('#sidebar').removeClass('show');
        $(this).removeClass('show');
    });
    
    // Search functionality
    $('.search-bar form').on('submit', function(e) {
        e.preventDefault();
        const query = $(this).find('input[name="q"]').val();
        if (query.trim()) {
            window.location.href = '/search.php?q=' + encodeURIComponent(query);
        }
    });
    
    // Print functionality
    $(document).on('click', '.print-btn', function() {
        window.print();
    });
    
    // Refresh button
    $(document).on('click', '.refresh-btn', function() {
        location.reload();
    });
    
    // Back button
    $(document).on('click', '.back-btn', function() {
        window.history.back();
    });
    
    // Select all checkboxes
    $(document).on('change', '.select-all', function() {
        const isChecked = $(this).prop('checked');
        $(this).closest('table').find('.select-item').prop('checked', isChecked);
        updateBulkActionsButton();
    });
    
    $(document).on('change', '.select-item', function() {
        updateBulkActionsButton();
    });
}

// Update bulk actions button visibility
function updateBulkActionsButton() {
    const selectedCount = $('.select-item:checked').length;
    if (selectedCount > 0) {
        $('.bulk-actions-btn').show();
        $('.selected-count').text(selectedCount);
    } else {
        $('.bulk-actions-btn').hide();
    }
}

// Setup form validation
function setupFormValidation() {
    // Add validation classes to forms
    $('form').each(function() {
        if (!$(this).hasClass('no-validate')) {
            $(this).addClass('needs-validation');
        }
    });
    
    // Custom validation on submit
    $(document).on('submit', '.needs-validation', function(e) {
        const form = this;
        
        if (!form.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        $(form).addClass('was-validated');
        
        // Disable submit button to prevent double submission
        const submitBtn = $(form).find('button[type="submit"]');
        if (form.checkValidity()) {
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Processing...');
            
            // Re-enable after timeout (in case of error)
            setTimeout(function() {
                submitBtn.prop('disabled', false).html(submitBtn.data('original-text') || 'Submit');
            }, 10000);
        }
    });
    
    // Store original button text
    $('form button[type="submit"]').each(function() {
        $(this).data('original-text', $(this).html());
    });
}

// Setup DataTables
function setupDataTables() {
    $('.datatable').each(function() {
        const table = $(this);
        const options = {
            responsive: true,
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search...",
                lengthMenu: "Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ entries",
                infoEmpty: "Showing 0 to 0 of 0 entries",
                infoFiltered: "(filtered from _MAX_ total entries)",
                zeroRecords: "No matching records found",
                paginate: {
                    first: "First",
                    last: "Last",
                    next: "Next",
                    previous: "Previous"
                }
            },
            pageLength: table.data('page-length') || 25,
            order: table.data('order') ? JSON.parse(table.data('order')) : [],
            columnDefs: table.data('column-defs') ? JSON.parse(table.data('column-defs')) : []
        };
        
        // Add export buttons if enabled
        if (table.data('export') === 'true') {
            // Add export buttons logic here
        }
        
        table.DataTable(options);
    });
}

// Setup Select2
function setupSelect2() {
    $('.select2').each(function() {
        const options = {
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: $(this).data('placeholder') || 'Select an option',
            allowClear: $(this).data('allow-clear') || false
        };
        
        if ($(this).data('tags') === 'true') {
            options.tags = true;
        }
        
        if ($(this).data('ajax-url')) {
            options.ajax = {
                url: $(this).data('ajax-url'),
                dataType: 'json',
                delay: 250,
                processResults: function(data) {
                    return {
                        results: data.results
                    };
                },
                cache: true
            };
        }
        
        $(this).select2(options);
    });
}

// Setup date pickers
function setupDatePickers() {
    $('.datepicker').each(function() {
        $(this).attr('type', 'date');
        
        // Set min/max dates if provided
        if ($(this).data('min')) {
            $(this).attr('min', $(this).data('min'));
        }
        if ($(this).data('max')) {
            $(this).attr('max', $(this).data('max'));
        }
    });
    
    // Date range picker
    $('.daterange').each(function() {
        // Implement date range picker if needed
    });
}

// Setup tooltips
function setupTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function(popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
}

// Setup notifications
function setupNotifications() {
    // Start notification polling if user is logged in
    if ($('body').hasClass('logged-in')) {
        startNotificationPolling('/api/notifications.php', 30000);
    }
}

// Format currency input
function formatCurrencyInput(input) {
    let value = $(input).val().replace(/[^0-9.]/g, '');
    if (value) {
        const parts = value.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        $(input).val(parts.join('.'));
    }
}

$(document).on('blur', '.currency-input', function() {
    formatCurrencyInput(this);
});

$(document).on('focus', '.currency-input', function() {
    let value = $(this).val().replace(/,/g, '');
    $(this).val(value);
});

// Number input validation
$(document).on('input', '.number-input', function() {
    this.value = this.value.replace(/[^0-9]/g, '');
});

// Decimal input validation
$(document).on('input', '.decimal-input', function() {
    this.value = this.value.replace(/[^0-9.]/g, '');
    if ((this.value.match(/\./g) || []).length > 1) {
        this.value = this.value.replace(/\.+$/, '');
    }
});

// Email validation
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Phone number validation
function validatePhone(phone) {
    const re = /^[0-9+\-\s()]{10,15}$/;
    return re.test(phone);
}

// Password strength checker
function checkPasswordStrength(password) {
    let strength = 0;
    
    if (password.length >= 8) strength++;
    if (password.length >= 12) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^a-zA-Z0-9]/.test(password)) strength++;
    
    if (strength <= 2) return 'weak';
    if (strength <= 4) return 'medium';
    return 'strong';
}

// Display password strength
$(document).on('input', '#password, #new_password', function() {
    const strength = checkPasswordStrength($(this).val());
    const indicator = $(this).siblings('.password-strength');
    
    if (indicator.length) {
        indicator.removeClass('strength-weak strength-medium strength-strong');
        
        if (strength === 'weak') {
            indicator.addClass('strength-weak').text('Weak password');
        } else if (strength === 'medium') {
            indicator.addClass('strength-medium').text('Medium password');
        } else if (strength === 'strong') {
            indicator.addClass('strength-strong').text('Strong password');
        }
    }
});

// Confirm password validation
$(document).on('input', '#confirm_password, #confirm_new_password', function() {
    const password = $(this).closest('form').find('#password, #new_password').val();
    const confirm = $(this).val();
    
    if (password !== confirm) {
        $(this).addClass('is-invalid');
        $(this).siblings('.invalid-feedback').text('Passwords do not match');
    } else {
        $(this).removeClass('is-invalid');
        $(this).addClass('is-valid');
    }
});

// Infinite scroll
$(window).on('scroll', function() {
    if ($(window).scrollTop() + $(window).height() >= $(document).height() - 100) {
        if (typeof loadMoreData === 'function') {
            loadMoreData();
        }
    }
});

// Lazy load images
function lazyLoadImages() {
    const images = document.querySelectorAll('img[data-src]');
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
                imageObserver.unobserve(img);
            }
        });
    });
    
    images.forEach(img => imageObserver.observe(img));
}

// Initialize lazy loading
if ('IntersectionObserver' in window) {
    lazyLoadImages();
}

// Handle offline/online status
window.addEventListener('online', function() {
    showToast('You are back online!', 'success');
});

window.addEventListener('offline', function() {
    showToast('You are offline. Please check your connection.', 'warning');
});

// Handle before unload
let formChanged = false;
$(document).on('change', 'form input, form select, form textarea', function() {
    formChanged = true;
});

window.addEventListener('beforeunload', function(e) {
    if (formChanged) {
        e.preventDefault();
        e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
        return e.returnValue;
    }
});

// Export functionality
function exportTableToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    const rows = table.querySelectorAll('tr');
    const csv = [];
    
    for (let i = 0; i < rows.length; i++) {
        const row = [];
        const cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            row.push('"' + cols[j].innerText.replace(/"/g, '""') + '"');
        }
        
        csv.push(row.join(','));
    }
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename + '.csv';
    a.click();
    URL.revokeObjectURL(url);
}

// Console logging for development
if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
    console.log('%cCareway Welfare Management System v' + window.appVersion, 'color: #667eea; font-size: 16px; font-weight: bold;');
    console.log('%cDevelopment Mode', 'color: #ed8936; font-size: 12px;');
}

// Export global functions
window.showToast = showToast;
window.formatCurrency = formatCurrency;
window.formatDate = formatDate;
window.confirmDelete = confirmDelete;
window.copyToClipboard = copyToClipboard;