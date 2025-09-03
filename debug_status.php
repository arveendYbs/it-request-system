<?php
/**
 * Debug Status Script
 * Place this in your root directory and access via browser to debug status logic
 * Access: http://localhost/it-request-system/debug_status.php
 */

require_once 'includes/auth.php';
requireLogin();

$current_user = getCurrentUser();

echo "<h1>🔍 Request Status Debug</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .debug-section { background: #f8f9fa; padding: 15px; margin: 10px 0; border-left: 4px solid #007bff; }
    .error { background: #f8d7da; border-left-color: #dc3545; }
    .success { background: #d4edda; border-left-color: #28a745; }
    .warning { background: #fff3cd; border-left-color: #ffc107; }
    pre { background: #e9ecef; padding: 10px; }
</style>";

// 1. Current User Info
echo "<div class='debug-section'>";
echo "<h2>1. 👤 Current User Info</h2>";
echo "<pre>";
print_r($current_user);
echo "</pre>";
echo "</div>";

// 2. Database Query Test
echo "<div class='debug-section'>";
echo "<h2>2. 🔍 Manager Lookup Query</h2>";
$user_info = fetchOne($pdo, "
    SELECT u.id, u.name, u.reporting_manager_id, m.name as manager_name, m.role as manager_role
    FROM users u
    LEFT JOIN users m ON u.reporting_manager_id = m.id
    WHERE u.id = ?
", [$current_user['id']]);

echo "<strong>Query Result:</strong><br>";
echo "<pre>";
print_r($user_info);
echo "</pre>";
echo "</div>";

// 3. Logic Simulation
echo "<div class='debug-section'>";
echo "<h2>3. 🧠 Status Logic Simulation</h2>";

if (!$user_info['reporting_manager_id']) {
    echo "✅ <strong>No reporting manager</strong> → Status should be: <span style='color: orange;'><strong>Pending IT HOD</strong></span><br>";
    $expected_status = 'Pending IT HOD';
} elseif ($user_info['manager_role'] === 'IT Manager') {
    echo "✅ <strong>Manager is IT Manager</strong> → Status should be: <span style='color: orange;'><strong>Pending IT HOD</strong></span><br>";
    $expected_status = 'Pending IT HOD';
} else {
    echo "✅ <strong>Manager is regular Manager</strong> → Status should be: <span style='color: blue;'><strong>Pending HOD</strong></span><br>";
    $expected_status = 'Pending HOD';
}

echo "<br><strong>Expected Status:</strong> <span style='background: yellow; padding: 5px;'>{$expected_status}</span>";
echo "</div>";

// 4. All Managers List
echo "<div class='debug-section'>";
echo "<h2>4. 👥 All Users with Manager Roles</h2>";
$managers = fetchAll($pdo, "SELECT id, name, role FROM users WHERE role IN ('Manager', 'IT Manager') ORDER BY role, name");
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>ID</th><th>Name</th><th>Role</th></tr>";
foreach ($managers as $mgr) {
    $highlight = ($mgr['id'] == $user_info['reporting_manager_id']) ? 'background: yellow;' : '';
    echo "<tr style='{$highlight}'><td>{$mgr['id']}</td><td>{$mgr['name']}</td><td>{$mgr['role']}</td></tr>";
}
echo "</table>";
echo "</div>";

// 5. Recent Requests by This User
echo "<div class='debug-section'>";
echo "<h2>5. 📋 Your Recent Requests</h2>";
$recent_requests = fetchAll($pdo, "
    SELECT id, title, status, created_at 
    FROM requests 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
", [$current_user['id']]);

if (empty($recent_requests)) {
    echo "<p><em>No requests found. Create a request to test the status logic.</em></p>";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Title</th><th>Status</th><th>Created</th></tr>";
    foreach ($recent_requests as $req) {
        $status_color = ($req['status'] === $expected_status) ? 'color: green;' : 'color: red;';
        echo "<tr>";
        echo "<td>#{$req['id']}</td>";
        echo "<td>" . htmlspecialchars($req['title']) . "</td>";
        echo "<td style='{$status_color}'><strong>{$req['status']}</strong></td>";
        echo "<td>" . date('M j, Y g:i A', strtotime($req['created_at'])) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}
echo "</div>";

// 6. Recommendations
echo "<div class='debug-section warning'>";
echo "<h2>6. 💡 What to Check</h2>";
echo "<ol>";
echo "<li><strong>Check your user's reporting_manager_id:</strong> Is it set correctly in the database?</li>";
echo "<li><strong>Check the manager's role:</strong> Is the manager's role set to 'Manager' or 'IT Manager'?</li>";
echo "<li><strong>Create a test request</strong> and see if the status matches the expected status above.</li>";
echo "<li><strong>If status is wrong:</strong> Check the request creation code logic.</li>";
echo "</ol>";
echo "</div>";

// 7. Test Button
echo "<div class='debug-section success'>";
echo "<h2>7. 🧪 Quick Test</h2>";
echo "<p>Click below to go create a test request and see what status it gets:</p>";
echo "<a href='requests/create.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Create Test Request</a>";
echo "</div>";

echo "<hr><p><small>Debug script - Delete this file when done testing!</small></p>";
?>