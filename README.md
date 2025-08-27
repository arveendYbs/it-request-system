# IT Request Management System

A comprehensive web-based IT request management system built with PHP, MySQL, and Bootstrap 5. This system allows employees to submit IT-related requests that go through a structured approval workflow.

## Features

### 🔐 User Roles & Permissions
- **Admin**: Full system access, user management, reports
- **IT Manager**: Approve/reject requests, view all requests
- **Manager**: Approve requests from reporting employees
- **User**: Create and manage own requests

### 📋 Request Management
- Create, read, update, delete requests
- File attachments (PDF, images, max 3 files, 5MB each)
- Structured approval workflow
- Status tracking and audit trail
- Categories and subcategories

### 🔄 Approval Workflow
1. User creates request → **Pending Manager**
2. Manager approves → **Approved by Manager**
3. IT Manager approves → **Approved**
4. Rejection at any stage with remarks

### 📊 Dashboard & Reports
- Interactive charts (Chart.js)
- Request statistics by status/category
- Export to Excel/CSV
- Advanced filtering and search
- Real-time status updates

### 🏢 Organization Management
- Companies and departments
- User hierarchy with reporting managers
- Role-based access control

## System Requirements

- **PHP**: 7.4 or higher
- **MySQL**: 5.7 or higher
- **Web Server**: Apache with mod_rewrite
- **Browser**: Modern browsers (Chrome, Firefox, Safari, Edge)

## Installation

### 1. Download & Extract
Extract all files to your web server directory (e.g., `/var/www/html/it-requests/`)

### 2. Database Setup
1. Open phpMyAdmin
2. Create a new database named `it_request_system`
3. Import the provided SQL schema file: `schema.sql`

### 3. Database Configuration
Edit `config/db.php` with your database credentials:

```php
private $host = 'localhost';
private $db_name = 'it_request_system';
private $