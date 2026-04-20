<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <meta name="theme-color" content="#667eea">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo APP_NAME; ?> - <?php echo $page_title ?? 'Dashboard'; ?></title>
    
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #667eea;
            --primary-dark: #5a67d8;
            --secondary-color: #764ba2;
            --success-color: #48bb78;
            --danger-color: #f56565;
            --warning-color: #ed8936;
            --info-color: #4299e1;
            --dark-color: #1a202c;
            --light-color: #f7fafc;
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 80px;
            --header-height: 70px;
            --transition-speed: 0.3s;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--light-color);
            overflow-x: hidden;
            color: #2d3748;
        }
        
        /* Dark Mode */
        body.dark-mode {
            background: #1a1a2e;
            color: #e2e8f0;
        }
        
        body.dark-mode .card,
        body.dark-mode .modal-content,
        body.dark-mode .dropdown-menu,
        body.dark-mode .topbar,
        body.dark-mode .table,
        body.dark-mode .dataTables_wrapper {
            background: #16213e;
            color: #e2e8f0;
        }
        
        body.dark-mode .table td,
        body.dark-mode .table th {
            border-color: #2d3748;
            color: #e2e8f0;
        }
        
        body.dark-mode .text-muted {
            color: #a0aec0 !important;
        }
        
        /* Main Content Area */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: margin var(--transition-speed);
        }
        
        .main-content.expanded {
            margin-left: 0;
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }
        
        /* Loading Spinner */
        .spinner-wrapper {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        
        .spinner-wrapper.active {
            display: flex;
        }
        
        .spinner-border {
            width: 3rem;
            height: 3rem;
        }
        
        /* Utility Classes */
        .text-gradient {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .bg-gradient {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        }
        
        .btn-gradient {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border: none;
            transition: all 0.3s;
        }
        
        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
            color: white;
        }
        
        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }
        
        /* Card Styles */
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s, box-shadow 0.3s;
            margin-bottom: 20px;
            background: white;
        }
        
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .card-header {
            background: transparent;
            border-bottom: 1px solid #e2e8f0;
            padding: 1.25rem 1.5rem;
            font-weight: 600;
        }
        
        body.dark-mode .card-header {
            border-bottom-color: #2d3748;
        }
        
        /* Table Styles */
        .table {
            margin-bottom: 0;
        }
        
        .table thead th {
            border-bottom: 2px solid #e2e8f0;
            font-weight: 600;
            color: #4a5568;
        }
        
        body.dark-mode .table thead th {
            border-bottom-color: #2d3748;
            color: #cbd5e0;
        }
        
        .table tbody tr:hover {
            background-color: #f7fafc;
        }
        
        body.dark-mode .table tbody tr:hover {
            background-color: #1a2744;
        }
        
        /* Form Styles */
        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            padding: 0.6rem 1rem;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        
        body.dark-mode .form-control,
        body.dark-mode .form-select {
            background: #2d3748;
            border-color: #4a5568;
            color: #e2e8f0;
        }
        
        /* Badge Styles */
        .badge {
            padding: 0.35rem 0.65rem;
            border-radius: 8px;
            font-weight: 500;
        }
        
        /* Alert Styles */
        .alert {
            border-radius: 12px;
            border: none;
        }
        
        /* Button Styles */
        .btn {
            border-radius: 10px;
            padding: 0.5rem 1.25rem;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-sm {
            padding: 0.25rem 0.75rem;
            border-radius: 8px;
        }
        
        /* Modal Styles */
        .modal-content {
            border-radius: 20px;
            border: none;
        }
        
        .modal-header {
            border-bottom: 1px solid #e2e8f0;
            padding: 1.25rem 1.5rem;
        }
        
        body.dark-mode .modal-header {
            border-bottom-color: #2d3748;
        }
        
        /* Progress Bar */
        .progress {
            border-radius: 10px;
            background-color: #edf2f7;
        }
        
        body.dark-mode .progress {
            background-color: #2d3748;
        }
        
        /* Sidebar Overlay */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1040;
            display: none;
        }
        
        .sidebar-overlay.show {
            display: block;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            
            .container-fluid {
                padding: 0.75rem;
            }
            
            .card-body {
                padding: 1rem;
            }
            
            h1, .h1 { font-size: 1.5rem; }
            h2, .h2 { font-size: 1.25rem; }
            h3, .h3 { font-size: 1.1rem; }
        }
        
        /* Print Styles */
        @media print {
            .sidebar, .topbar, .btn, .no-print {
                display: none !important;
            }
            .main-content {
                margin: 0 !important;
                padding: 0 !important;
            }
            .card {
                break-inside: avoid;
                page-break-inside: avoid;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="spinner-wrapper" id="globalSpinner">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>