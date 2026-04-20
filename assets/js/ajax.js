/**
 * AJAX Functions for Careway Welfare Management System
 */

// Global AJAX settings
$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    },
    beforeSend: function() {
        if (typeof showSpinner === 'function') {
            showSpinner();
        }
    },
    complete: function() {
        if (typeof hideSpinner === 'function') {
            hideSpinner();
        }
    }
});

// Generic AJAX request function
function ajaxRequest(url, method, data, successCallback, errorCallback) {
    $.ajax({
        url: url,
        method: method,
        data: data,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                if (successCallback) successCallback(response);
                if (response.message) showToast(response.message, 'success');
            } else {
                if (errorCallback) errorCallback(response);
                if (response.message) showToast(response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            showToast('An error occurred. Please try again.', 'error');
            if (errorCallback) errorCallback({ success: false, message: error });
        }
    });
}

// Load data from server
function loadData(url, params, callback) {
    $.ajax({
        url: url,
        method: 'GET',
        data: params,
        dataType: 'json',
        success: function(response) {
            if (callback) callback(response);
        },
        error: function(xhr, status, error) {
            console.error('Load Data Error:', error);
            showToast('Failed to load data', 'error');
        }
    });
}

// Submit form via AJAX
function submitFormAjax(formId, url, successCallback, errorCallback) {
    const form = $('#' + formId);
    const formData = new FormData(form[0]);
    
    $.ajax({
        url: url || form.attr('action'),
        method: form.attr('method') || 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                if (successCallback) successCallback(response);
                if (response.message) showToast(response.message, 'success');
            } else {
                if (errorCallback) errorCallback(response);
                if (response.message) showToast(response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('Form Submit Error:', error);
            showToast('Failed to submit form', 'error');
            if (errorCallback) errorCallback({ success: false, message: error });
        }
    });
}

// Delete item via AJAX
function deleteItem(url, id, name, callback) {
    Swal.fire({
        title: 'Confirm Delete',
        html: `Are you sure you want to delete <strong>${name}</strong>?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: url,
                method: 'DELETE',
                data: { id: id },
                success: function(response) {
                    if (response.success) {
                        showToast(response.message, 'success');
                        if (callback) callback(response);
                    } else {
                        showToast(response.message, 'error');
                    }
                },
                error: function() {
                    showToast('Failed to delete item', 'error');
                }
            });
        }
    });
}

// Load dropdown options dynamically
function loadDropdown(url, params, targetId, valueField, textField) {
    $.ajax({
        url: url,
        method: 'GET',
        data: params,
        dataType: 'json',
        success: function(response) {
            const select = $('#' + targetId);
            select.empty();
            select.append('<option value="">Select Option</option>');
            
            if (response.data && response.data.length) {
                $.each(response.data, function(index, item) {
                    select.append(`<option value="${item[valueField]}">${item[textField]}</option>`);
                });
            }
        },
        error: function() {
            console.error('Failed to load dropdown');
        }
    });
}

// Auto-save form data
let autoSaveTimer;
function autoSave(formId, url, delay = 2000) {
    clearTimeout(autoSaveTimer);
    autoSaveTimer = setTimeout(function() {
        const form = $('#' + formId);
        const formData = form.serialize();
        
        $.ajax({
            url: url,
            method: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    $('.auto-save-status').html('<i class="fas fa-check-circle text-success"></i> Saved');
                    setTimeout(() => {
                        $('.auto-save-status').html('<i class="fas fa-save"></i> Auto-save');
                    }, 2000);
                }
            }
        });
    }, delay);
}

// Load more data (infinite scroll)
let isLoading = false;
let currentPage = 1;
function loadMoreData(url, containerId, hasMore) {
    if (isLoading) return;
    if (!hasMore) return;
    
    isLoading = true;
    currentPage++;
    
    $.ajax({
        url: url,
        method: 'GET',
        data: { page: currentPage },
        success: function(response) {
            if (response.html) {
                $('#' + containerId).append(response.html);
            }
            isLoading = false;
            
            if (!response.has_more) {
                $(window).off('scroll');
            }
        },
        error: function() {
            isLoading = false;
        }
    });
}

// Real-time search
function realTimeSearch(inputId, url, resultContainerId, delay = 300) {
    let searchTimer;
    
    $('#' + inputId).on('input', function() {
        clearTimeout(searchTimer);
        const query = $(this).val();
        
        searchTimer = setTimeout(function() {
            $.ajax({
                url: url,
                method: 'GET',
                data: { search: query },
                success: function(response) {
                    if (response.html) {
                        $('#' + resultContainerId).html(response.html);
                    }
                }
            });
        }, delay);
    });
}

// Notification polling
let notificationInterval;
function startNotificationPolling(url, interval = 30000) {
    if (notificationInterval) clearInterval(notificationInterval);
    
    notificationInterval = setInterval(function() {
        $.ajax({
            url: url,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.count && response.count > 0) {
                    $('.notification-badge').text(response.count > 9 ? '9+' : response.count).show();
                    
                    if (response.notifications && response.notifications.length) {
                        updateNotificationDropdown(response.notifications);
                    }
                }
            }
        });
    }, interval);
}

// Update notification dropdown
function updateNotificationDropdown(notifications) {
    let html = '';
    $.each(notifications, function(index, notif) {
        html += `
            <a href="${notif.link}" class="dropdown-item notification-item">
                <div class="d-flex">
                    <div class="flex-shrink-0">
                        <i class="fas ${notif.icon || 'fa-bell'}"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="notification-title">${notif.title}</div>
                        <div class="notification-message">${notif.message.substring(0, 50)}</div>
                        <div class="notification-time"><small>${notif.time_ago}</small></div>
                    </div>
                </div>
            </a>
        `;
    });
    
    $('.notification-dropdown .dropdown-menu').html(`
        <div class="dropdown-header">
            <strong>Notifications</strong>
            <a href="/dashboard/notifications.php" class="float-end">View All</a>
        </div>
        <div class="dropdown-divider"></div>
        ${html || '<div class="dropdown-item text-center text-muted">No new notifications</div>'}
    `);
}

// Chart data update via AJAX
function updateChart(chart, url, params) {
    $.ajax({
        url: url,
        method: 'GET',
        data: params,
        dataType: 'json',
        success: function(response) {
            if (response.data) {
                chart.data.datasets.forEach(dataset => {
                    dataset.data = response.data;
                });
                chart.update();
            }
        }
    });
}

// Export functionality
function exportData(url, params, format = 'csv') {
    const queryString = $.param(params);
    window.location.href = `${url}?${queryString}&format=${format}`;
}

// Bulk actions
function bulkAction(url, ids, action, callback) {
    $.ajax({
        url: url,
        method: 'POST',
        data: {
            ids: ids,
            action: action,
            csrf_token: $('meta[name="csrf-token"]').attr('content')
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showToast(response.message, 'success');
                if (callback) callback(response);
            } else {
                showToast(response.message, 'error');
            }
        },
        error: function() {
            showToast('Bulk action failed', 'error');
        }
    });
}