import os

# ==============================
# CONFIGURATION
# ==============================

PROJECT_NAME = "careway"

HTDOCS_PATH = r"C:\xampp\htdocs"  # Change if needed

# ==============================
# FULL DIRECTORY STRUCTURE
# ==============================

structure = {
    "assets": ["css", "js", "images/avatars", "images/icons"],
    "includes": [],
    "templates": [],
    "pages": [],
    "dashboard": [],
    "modules": [
        "members",
        "contributions",
        "loans",
        "meetings",
        "elections",
        "reports",
        "accounts",
        "investments",
        "settings"
    ],
    "admin": [],
    "logs": [],
    "uploads": ["profiles", "documents", "reports"],
    "api": []
}

# ==============================
# FILE DEFINITIONS
# ==============================

files = {
    # Root
    "index.php": "<?php\n// Entry point\n?>",
    ".htaccess": "Options -Indexes",

    # Assets
    "assets/css/style.css": "",
    "assets/css/dashboard.css": "",
    "assets/css/responsive.css": "",

    "assets/js/main.js": "",
    "assets/js/ajax.js": "",
    "assets/js/charts.js": "",

    "assets/images/logo.png": "",

    # Includes
    "includes/config.php": "<?php\n// Constants\n?>",
    "includes/db.php": "<?php\n// Database connection\n?>",
    "includes/functions.php": "<?php\n// Helper functions\n?>",
    "includes/auth.php": "<?php\n// Authentication\n?>",
    "includes/middleware.php": "<?php\n// Access control\n?>",
    "includes/error_handler.php": "<?php\n// Error handling\n?>",
    "includes/permissions.php": "<?php\n// Permissions\n?>",
    "includes/session.php": "<?php\n// Session management\n?>",

    # Templates
    "templates/header.php": "<?php\n// Header\n?>",
    "templates/sidebar.php": "<?php\n// Sidebar\n?>",
    "templates/topbar.php": "<?php\n// Topbar\n?>",
    "templates/footer.php": "<?php\n// Footer\n?>",
    "templates/alerts.php": "<?php\n// Alerts\n?>",

    # Pages
    "pages/login.php": "<?php\n// Login\n?>",
    "pages/register.php": "<?php\n// Register\n?>",
    "pages/forgot_password.php": "<?php\n// Forgot Password\n?>",
    "pages/reset_password.php": "<?php\n// Reset Password\n?>",

    # Dashboard
    "dashboard/home.php": "<?php\n// Dashboard Home\n?>",
    "dashboard/profile.php": "<?php\n// Profile\n?>",
    "dashboard/notifications.php": "<?php\n// Notifications\n?>",

    # Members Module
    "modules/members/list.php": "",
    "modules/members/add.php": "",
    "modules/members/edit.php": "",
    "modules/members/view.php": "",
    "modules/members/delete.php": "",

    # Contributions
    "modules/contributions/list.php": "",
    "modules/contributions/record.php": "",
    "modules/contributions/edit.php": "",
    "modules/contributions/delete.php": "",

    # Loans
    "modules/loans/apply.php": "",
    "modules/loans/approve.php": "",
    "modules/loans/disburse.php": "",
    "modules/loans/repay.php": "",
    "modules/loans/list.php": "",
    "modules/loans/details.php": "",

    # Meetings
    "modules/meetings/create.php": "",
    "modules/meetings/attendance.php": "",
    "modules/meetings/list.php": "",
    "modules/meetings/report.php": "",

    # Elections
    "modules/elections/create.php": "",
    "modules/elections/candidates.php": "",
    "modules/elections/vote.php": "",
    "modules/elections/results.php": "",
    "modules/elections/ballot.php": "",

    # Reports
    "modules/reports/financial.php": "",
    "modules/reports/member_statement.php": "",
    "modules/reports/loan_reports.php": "",
    "modules/reports/attendance_reports.php": "",
    "modules/reports/election_reports.php": "",
    "modules/reports/export_pdf.php": "",

    # Accounts
    "modules/accounts/list.php": "",
    "modules/accounts/create.php": "",
    "modules/accounts/edit.php": "",
    "modules/accounts/delete.php": "",

    # Investments
    "modules/investments/list.php": "",
    "modules/investments/add.php": "",
    "modules/investments/performance.php": "",

    # Settings
    "modules/settings/general.php": "",
    "modules/settings/positions.php": "",
    "modules/settings/permissions.php": "",
    "modules/settings/subscription.php": "",

    # Admin
    "admin/dashboard.php": "",
    "admin/users.php": "",
    "admin/groups.php": "",
    "admin/subscriptions.php": "",
    "admin/payments.php": "",
    "admin/reports.php": "",
    "admin/settings.php": "",

    # Logs
    "logs/error_log.txt": "",
    "logs/activity_log.txt": "",

    # API
    "api/auth.php": "",
    "api/members.php": "",
    "api/loans.php": "",
    "api/reports.php": ""
}

# ==============================
# CREATE PROJECT FUNCTION
# ==============================

def create_project():
    base_path = os.path.join(HTDOCS_PATH, PROJECT_NAME)

    # Prevent nested careway/careway
    if os.path.basename(os.getcwd()) == PROJECT_NAME:
        base_path = os.getcwd()

    os.makedirs(base_path, exist_ok=True)

    # Create folders
    for folder, subfolders in structure.items():
        folder_path = os.path.join(base_path, folder)
        os.makedirs(folder_path, exist_ok=True)

        for sub in subfolders:
            os.makedirs(os.path.join(folder_path, sub), exist_ok=True)

    # Create files
    for file_path, content in files.items():
        full_path = os.path.join(base_path, file_path)

        if not os.path.exists(full_path):
            with open(full_path, "w", encoding="utf-8") as f:
                f.write(content)
            print(f"[✔] Created: {file_path}")
        else:
            print(f"[!] Exists: {file_path}")

    print("\n🚀 Careway full structure created successfully!")

# ==============================
# RUN SCRIPT
# ==============================

if __name__ == "__main__":
    create_project()