<?php
/**
 * Excel Export for Requests
 * reports/export.php
 */

require_once '../includes/auth.php';
requireLogin();

$current_user = getCurrentUser();

// Build query based on user role and filters
$where_conditions = [];
$params = [];

// Role-based filtering
if ($current_user['role'] === 'User') {
    $where_conditions[] = "r.user_id = ?";
    $params[] = $current_user['id'];
} elseif ($current_user['role'] === 'Manager') {
    // Show requests from reporting employees + own requests
    $where_conditions[] = "(u.reporting_manager_id = ? OR r.user_id = ?)";
    $params[] = $current_user['id'];
    $params[] = $current_user['id'];
}

// Apply filters from query parameters
$filters = [
    'status' => $_GET['status'] ?? '',
    'category' => $_GET['category'] ?? '',
    'company' => $_GET['company'] ?? '',
    'department' => $_GET['department'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'search' => $_GET['search'] ?? ''
];

if (!empty($filters['status'])) {
    $where_conditions[] = "r.status = ?";
    $params[] = $filters['status'];
}

if (!empty($filters['category'])) {
    $where_conditions[] = "r.category_id = ?";
    $params[] = $filters['category'];
}

if (!empty($filters['company'])) {
    $where_conditions[] = "u.company_id = ?";
    $params[] = $filters['company'];
}

if (!empty($filters['department'])) {
    $where_conditions[] = "u.department_id = ?";
    $params[] = $filters['department'];
}

if (!empty($filters['date_from'])) {
    $where_conditions[] = "DATE(r.created_at) >= ?";
    $params[] = $filters['date_from'];
}

if (!empty($filters['date_to'])) {
    $where_conditions[] = "DATE(r.created_at) <= ?";
    $params[] = $filters['date_to'];
}

if (!empty($filters['search'])) {
    $where_conditions[] = "(r.title LIKE ? OR r.description LIKE ? OR u.name LIKE ?)";
    $search_term = '%' . $filters['search'] . '%';
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

// Build WHERE clause
$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get requests for export
$export_query = "
    SELECT r.id, r.title, r.description, r.status, r.created_at, r.updated_at,
           r.approved_by_manager_date, r.approved_by_it_manager_date, 
           r.rejected_date, r.rejection_remarks,
           u.name as requester_name, u.email as requester_email,
           c.name as category_name, sc.name as subcategory_name,
           co.name as company_name, d.name as department_name,
           am.name as approved_by_manager_name,
           aim.name as approved_by_it_manager_name,
           rb.name as rejected_by_name
    FROM requests r
    JOIN users u ON r.user_id = u.id
    JOIN categories c ON r.category_id = c.id
    JOIN subcategories sc ON r.subcategory_id = sc.id
    JOIN companies co ON u.company_id = co.id
    JOIN departments d ON u.department_id = d.id
    LEFT JOIN users am ON r.approved_by_manager_id = am.id
    LEFT JOIN users aim ON r.approved_by_it_manager_id = aim.id
    LEFT JOIN users rb ON r.rejected_by_id = rb.id
    $where_clause
    ORDER BY r.created_at DESC
";

$requests = fetchAll($pdo, $export_query, $params);

// Generate filename
$filename = 'IT_Requests_Export_' . date('Y-m-d_H-i-s') . '.csv';

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Expires: 0');

// Open output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// CSV Headers
$headers = [
    'Request ID',
    'Title',
    'Description',
    'Category',
    'Subcategory',
    'Status',
    'Requester Name',
    'Requester Email',
    'Department',
    'Company',
    'Created Date',
    'Updated Date',
    'Manager Approved By',
    'Manager Approved Date',
    'IT Manager Approved By',
    'IT Manager Approved Date',
    'Rejected By',
    'Rejected Date',
    'Rejection Remarks'
];

// Write headers
fputcsv($output, $headers);

// Write data rows
foreach ($requests as $request) {
    $row = [
        $request['id'],
        $request['title'],
        $request['description'],
        $request['category_name'],
        $request['subcategory_name'],
        $request['status'],
        $request['requester_name'],
        $request['requester_email'],
        $request['department_name'],
        $request['company_name'],
        $request['created_at'] ? date('Y-m-d H:i:s', strtotime($request['created_at'])) : '',
        $request['updated_at'] ? date('Y-m-d H:i:s', strtotime($request['updated_at'])) : '',
        $request['approved_by_manager_name'] ?? '',
        $request['approved_by_manager_date'] ? date('Y-m-d H:i:s', strtotime($request['approved_by_manager_date'])) : '',
        $request['approved_by_it_manager_name'] ?? '',
        $request['approved_by_it_manager_date'] ? date('Y-m-d H:i:s', strtotime($request['approved_by_it_manager_date'])) : '',
        $request['rejected_by_name'] ?? '',
        $request['rejected_date'] ? date('Y-m-d H:i:s', strtotime($request['rejected_date'])) : '',
        $request['rejection_remarks'] ?? ''
    ];
    
    fputcsv($output, $row);
}

// Close output stream
fclose($output);
exit();
?>