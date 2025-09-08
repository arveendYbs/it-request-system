
<?php
// 6. Email Settings Management (Optional)
// admin/email_settings.php

/**
 * Email Settings Management Page
 * admin/email_settings.php
 */

require_once '../includes/auth.php';
requireRole(['Admin']);

$page_title = 'Email Settings';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'smtp_host' => $_POST['smtp_host'] ?? '',
        'smtp_port' => $_POST['smtp_port'] ?? '',
        'smtp_username' => $_POST['smtp_username'] ?? '',
        'smtp_password' => $_POST['smtp_password'] ?? '',
        'from_email' => $_POST['from_email'] ?? '',
        'from_name' => $_POST['from_name'] ?? '',
        'enable_notifications' => isset($_POST['enable_notifications']) ? 1 : 0
    ];
    
    // Save settings to database or config file
    // Implementation depends on your preference
    
    $_SESSION['success_message'] = 'Email settings updated successfully!';
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <h1 class="h2">Email Settings</h1>
    
    <div class="card">
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <h5>SMTP Configuration</h5>
                        
                        <div class="mb-3">
                            <label for="smtp_host" class="form-label">SMTP Host</label>
                            <input type="text" class="form-control" id="smtp_host" name="smtp_host" 
                                   value="smtp.office365.com" placeholder="smtp.office365.com">
                        </div>
                        
                        <div class="mb-3">
                            <label for="smtp_port" class="form-label">SMTP Port</label>
                            <input type="number" class="form-control" id="smtp_port" name="smtp_port" 
                                   value="587" placeholder="587">
                        </div>
                        
                        <div class="mb-3">
                            <label for="smtp_username" class="form-label">Username</label>
                            <input type="email" class="form-control" id="smtp_username" name="smtp_username" 
                                   placeholder="your-email@company.com">
                        </div>
                        
                        <div class="mb-3">
                            <label for="smtp_password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="smtp_password" name="smtp_password" 
                                   placeholder="Your email password">
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h5>Email Settings</h5>
                        
                        <div class="mb-3">
                            <label for="from_email" class="form-label">From Email</label>
                            <input type="email" class="form-control" id="from_email" name="from_email" 
                                   placeholder="noreply@company.com">
                        </div>
                        
                        <div class="mb-3">
                            <label for="from_name" class="form-label">From Name</label>
                            <input type="text" class="form-control" id="from_name" name="from_name" 
                                   value="IT Request System" placeholder="IT Request System">
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="enable_notifications" 
                                       name="enable_notifications" checked>
                                <label class="form-check-label" for="enable_notifications">
                                    Enable Email Notifications
                                </label>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <h6>Email Notifications are sent for:</h6>
                            <ul class="mb-0">
                                <li>Request creation confirmation</li>
                                <li>Manager approval notifications</li>
                                <li>IT approval notifications</li>
                                <li>Final approval confirmations</li>
                                <li>Rejection notifications</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Save Settings
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="testEmail()">
                        <i class="bi bi-envelope me-1"></i>Send Test Email
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function testEmail() {
    if (confirm('Send a test email to verify SMTP settings?')) {
        fetch('test_email.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Test email sent successfully!');
            } else {
                alert('Failed to send test email: ' + data.error);
            }
        })
        .catch(error => {
            alert('Error: ' + error);
        });
    }
}
</script>

<?php include '../includes/footer.php'; ?>
