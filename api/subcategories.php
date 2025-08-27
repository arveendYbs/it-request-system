<?php
/**
 * Subcategories API
 * api/subcategories.php
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

$category_id = $_GET['category_id'] ?? '';

if (empty($category_id) || !is_numeric($category_id)) {
    echo json_encode([]);
    exit();
}

try {
    $subcategories = fetchAll($pdo, 
        "SELECT id, name, description FROM subcategories WHERE category_id = ? ORDER BY name",
        [$category_id]
    );
    
    // Debug: log the query and results
    error_log("Subcategories API: category_id=$category_id, found=" . count($subcategories));
    
    echo json_encode($subcategories);
} catch (Exception $e) {
    error_log("Subcategories API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load subcategories', 'message' => $e->getMessage()]);
}
?>