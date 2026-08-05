# 🚀 Office File Management CRM & Workflow Engine

A modern, high-performance, multi-employee, and workflow-driven **File Case Management System** built with **Core PHP, PDO, and MySQL/SQLite**. This system automates company workflows, allowing cases to be seamlessly forwarded step-by-step through custom stages with role-based access control (RBAC), automated SLA tracking, built-in document scanning, and messaging integration.

---

## ✨ Key Premium Features

*   **⚙️ Dynamic Drag & Drop Workflow Builder**  
    Admin can visually construct workflow pipelines (e.g., *Fiber Connection, Ownership Transfer, Upgrade Requests*), reorder steps via **SortableJS**, assign target employee roles, and define custom completion SLAs (Hours) for each stage.
*   **📂 Case & File Lifecycle Tracking**  
    Each file receives a unique system-generated folder code (e.g., `FIB-2026-00101`). Tracks customer data, priority status (`Low`, `Medium`, `High`, `Urgent`), attached files, comments, and full stage transitions.
*   **🛡️ Dynamic RBAC & Security Isolation**  
    Role-Based Access Control matrix lets administrators restrict module/button visibility. Secure password encryption using `bcrypt` and active session security enforces that **only the currently assigned employee** has access to process a file.
*   **📋 Drag & Drop Kanban Stage View**  
    Interact with active cases visually by dragging cards across stage columns on the Kanban board (powered by SortableJS & AJAX).
*   **📷 Integrated Web-Camera Document Scanner**  
    Employees can capture document snapshots directly from their mobile camera or desktop webcam using the HTML5 Camera API and save them as file attachments.
*   **📈 Dashboard & SLA Performance Analytics**  
    Admin overview analytics powered by **ApexCharts** (Work type breakdown, status distribution charts). Includes employee efficiency metrics, downloadable CSV logs, and printable PDF reports.
*   **📲 WhatsApp Cloud API & Click-to-Call Logs**  
    Click-to-Call template launcher and manual WhatsApp notification dispatcher with audit trail tracking.

---

## 🛠️ Tech Stack & Dependencies

*   **Backend:** Core PHP (PDO Wrapper)
*   **Database:** Dual Driver Support (MySQL/MariaDB + SQLite Fallback)
*   **Frontend:** HTML5, CSS3, Tailwind CSS (with soft gradient variables), JavaScript
*   **Libraries:** SortableJS, ApexCharts, FontAwesome 6, Bootstrap 5 (CSS/JS)

---

## 🚀 Easy Installation & Setup

No heavy software (like XAMPP/WAMP) is required to test! The application contains an automatic **SQLite database fallback** and self-seeding migration script.

### 1. Download PHP Standalone (2 Minutes)
*   Download portable **PHP for Windows** from [windows.php.net/download](https://windows.php.net/download).
*   Extract the ZIP archive directly into your `C:\` drive at **`C:\php`** (so `C:\php\php.exe` is available).

### 2. Configure `php.ini` (Enable Database Drivers)
Ensure your `C:\php\php.ini` file has the following configurations enabled:
```ini
extension_dir = "ext"
extension=pdo_sqlite
extension=sqlite3
extension=pdo_mysql
extension=mysqli
```

### 3. Run the Local Development Server
Open your command terminal inside the project directory (`c:\Users\Rahul\Pictures\File Wsorkflow`) and run:
```powershell
C:\php\php.exe -S localhost:8000
```
*(If using standard XAMPP instead, run: `C:\xampp\php\php.exe -S localhost:8000`)*

### 4. Access the CRM System
Open your web browser and go to:
👉 **[http://localhost:8000](http://localhost:8000)**

---

## ⚡ 1-Click Quick Demo Login Accounts

On the login gateway page, you can use the interactive shortcut buttons to sign in instantly with preset testing profiles:

| Account | Email | Password | Role |
| :--- | :--- | :--- | :--- |
| **Super Admin** | `admin@office.com` | `password123` | Full access, design workflows, user controls |
| **Front Desk** | `frontdesk@office.com` | `password123` | Case intake, upload documents, launch files |
| **Verification** | `verification@office.com` | `password123` | Document validation team member |
| **Accounts** | `accounts@office.com` | `password123` | Payment confirmation & billing steps |

---

## 📂 Codebase File Structure

```
/file-crm/
├── config.php                  # Global Base & Upload configuration settings
├── schema.sql                  # Production MySQL Database Schema & seed data
├── index.php                   # Sleek Login gateway
├── logout.php                  # Authentication teardown
├── includes/
│   ├── db.php                  # PDO wrapper with SQLite fallback auto-seeding
│   ├── auth.php                # Authentication helper, permissions & RBAC protection
│   ├── functions.php           # File helper utilities, status badge generator, loggers
│   ├── header.php              # Collapsible layout header & theme switcher
│   ├── sidebar.php             # Role-aware sidebar navigation links
│   └── footer.php              # Global JS assets loader
├── admin/
│   ├── dashboard.php           # ApexCharts metrics overview
│   ├── workflow-builder.php    # Visual Drag & Drop Workflow Template Builder
│   ├── users.php               # Employee database management
│   ├── roles.php               # Permission Matrix builder
│   └── reports.php             # Performance SLA reporter (Print / CSV export)
├── employee/
│   ├── dashboard.php           # Personal task inbox metrics
│   ├── my-files.php            # Active directory list view
│   └── kanban.php              # Interactive drag-drop board
├── modules/
│   ├── file/
│   │   ├── view.php            # Case details workspace & audit trail history
│   │   ├── forward.php         # Stage forwarding engine
│   │   └── document-upload.php # Document scanner & upload handler
│   ├── whatsapp/
│   │   └── send.php            # WhatsApp dispatch log manager
│   └── calling/
│       └── call-handler.php    # Click-to-Call tracker
└── assets/
    ├── css/style.css           # Premium corporate variables and light/dark styling
    └── js/                     # SortableJS integration, scanner and client controllers
```
