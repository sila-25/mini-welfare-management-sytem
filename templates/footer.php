        </div> <!-- Close container-fluid -->
    </div> <!-- Close main-content -->
    
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Global AJAX setup
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': '<?php echo generateCSRFToken(); ?>'
            }
        });
        
        // Toggle Sidebar
        $('#toggleSidebar').click(function() {
            $('#sidebar').toggleClass('show');
            $('#sidebarOverlay').toggleClass('show');
            
            if (window.innerWidth > 768) {
                $('#sidebar').toggleClass('collapsed');
                $('.main-content').toggleClass('expanded');
            }
        });
        
        // Sidebar overlay for mobile
        $('#sidebarOverlay').click(function() {
            $('#sidebar').removeClass('show');
            $(this).removeClass('show');
        });
        
        // Dark Mode Toggle
        if (localStorage.getItem('darkMode') === 'enabled') {
            $('body').addClass('dark-mode');
            $('#darkModeToggle i').removeClass('fa-moon').addClass('fa-sun');
        }
        
        $('#darkModeToggle').click(function() {
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
        
        // Show/Hide Global Spinner
        function showSpinner() {
            $('#globalSpinner').addClass('active');
        }
        
        function hideSpinner() {
            $('#globalSpinner').removeClass('active');
        }
        
        // Initialize DataTables
        $(document).ready(function() {
            $('.datatable').each(function() {
                if (!$.fn.DataTable.isDataTable(this)) {
                    $(this).DataTable({
                        responsive: true,
                        language: {
                            search: "_INPUT_",
                            searchPlaceholder: "Search...",
                            lengthMenu: "Show _MENU_ entries",
                            info: "Showing _START_ to _END_ of _TOTAL_ entries",
                            infoEmpty: "Showing 0 to 0 of 0 entries",
                            infoFiltered: "(filtered from _MAX_ total entries)",
                            zeroRecords: "No matching records found"
                        },
                        pageLength: 25,
                        order: []
                    });
                }
            });
            
            // Initialize Select2
            $('.select2').each(function() {
                $(this).select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    placeholder: $(this).data('placeholder') || 'Select an option',
                    allowClear: $(this).data('allow-clear') || false
                });
            });
        });
        
        // Confirm Delete
        window.confirmDelete = function(url, message = 'Are you sure you want to delete this?') {
            Swal.fire({
                title: 'Confirm Delete',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
            return false;
        };
        
        // Show Toast Message
        window.showToast = function(message, type = 'success') {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
            
            Toast.fire({
                icon: type,
                title: message
            });
        };
        
        // Format Currency
        window.formatCurrency = function(amount) {
            return new Intl.NumberFormat('en-KE', {
                style: 'currency',
                currency: 'KES',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(amount);
        };
        
        // Format Date
        window.formatDate = function(date, format = 'YYYY-MM-DD') {
            if (!date) return 'N/A';
            const d = new Date(date);
            if (format === 'YYYY-MM-DD') {
                return d.toISOString().split('T')[0];
            } else if (format === 'DD/MM/YYYY') {
                return d.getDate().toString().padStart(2, '0') + '/' + 
                       (d.getMonth() + 1).toString().padStart(2, '0') + '/' + 
                       d.getFullYear();
            } else {
                return d.toLocaleDateString();
            }
        };
        
        // Copy to clipboard
        window.copyToClipboard = function(text) {
            navigator.clipboard.writeText(text).then(() => {
                showToast('Copied to clipboard!', 'success');
            }).catch(() => {
                showToast('Failed to copy', 'error');
            });
        };
        
        // Export to CSV
        window.exportToCSV = function(data, filename) {
            const csv = data.map(row => Object.values(row).join(',')).join('\n');
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename + '.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        };
        
        // Print element
        window.printElement = function(elementId) {
            const printContent = document.getElementById(elementId).innerHTML;
            const originalContent = document.body.innerHTML;
            document.body.innerHTML = printContent;
            window.print();
            document.body.innerHTML = originalContent;
            location.reload();
        };
        
        // Time ago function
        window.timeAgo = function(date) {
            const seconds = Math.floor((new Date() - new Date(date)) / 1000);
            let interval = seconds / 31536000;
            if (interval > 1) return Math.floor(interval) + ' years ago';
            interval = seconds / 2592000;
            if (interval > 1) return Math.floor(interval) + ' months ago';
            interval = seconds / 86400;
            if (interval > 1) return Math.floor(interval) + ' days ago';
            interval = seconds / 3600;
            if (interval > 1) return Math.floor(interval) + ' hours ago';
            interval = seconds / 60;
            if (interval > 1) return Math.floor(interval) + ' minutes ago';
            return Math.floor(seconds) + ' seconds ago';
        };
    </script>
    
    <!-- Page Specific Scripts -->
    <?php if (isset($page_scripts)): ?>
        <?php echo $page_scripts; ?>
    <?php endif; ?>
</body>
</html>