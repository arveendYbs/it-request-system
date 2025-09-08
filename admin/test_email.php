<?php
require_once '../config/db.php';
require_once '../includes/email_service.php';

// Test email sending
try {
    echo "<h2>Email Test</h2>";
    
    // Get a sample request (use an existing request ID)
    $request_id = 40; // Change this to an existing request ID
    
    $emailService = new EmailService($pdo);
    
    echo "<p>Testing email notification for request #$request_id...</p>";
    
    $result = $emailService->sendRequestCreatedNotification($request_id);
    
    if ($result) {
        echo "<p style='color: green;'>✅ Email sent successfully!</p>";
    } else {
        echo "<p style='color: red;'>❌ Email failed to send. Check error logs.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>