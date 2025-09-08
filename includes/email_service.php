
<?php
// 3. Email Service Class
// includes/email_service.php
/**
 * Email Service Class
 * includes/email_service.php
 */

require_once __DIR__ . '/../vendor/autoload.php'; // If using Composer
// OR if manual installation:
// require_once __DIR__ . '/../PHPMailer/src/Exception.php';
// require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
// require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

require_once '../config/email.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

class EmailService {
    private $mail;
    private $pdo;
    
    public function __construct($database_connection) {
        $this->pdo = $database_connection;
        $this->mail = new PHPMailer(true);
        $this->setupSMTP();
    }
    
    private function setupSMTP() {
        try {
            // Server settings
            $this->mail->isSMTP();
            $this->mail->Host = EmailConfig::SMTP_HOST;
            $this->mail->SMTPAuth = true;
            $this->mail->Username = EmailConfig::SMTP_USERNAME;
            $this->mail->Password = EmailConfig::SMTP_PASSWORD;
            $this->mail->SMTPSecure = EmailConfig::SMTP_ENCRYPTION;
            $this->mail->Port = EmailConfig::SMTP_PORT;
            $this->mail->SMTPDebug = EmailConfig::SMTPDebug;
            $this->mail->Debugoutput = EmailConfig::Debugoutput;

            // Default sender
            $this->mail->setFrom(EmailConfig::FROM_EMAIL, EmailConfig::FROM_NAME);
            
            // Optional: Enable debug for testing
            // $this->mail->SMTPDebug = SMTP::DEBUG_SERVER;
            
        } catch (Exception $e) {
            error_log("SMTP Setup Error: " . $e->getMessage());
        }
    }
    
    /**
     * Send email notification when request is created
     */
    public function sendRequestCreatedNotification($request_id) {
        try {
            $request = $this->getRequestDetails($request_id);
            if (!$request) return false;
            
            // Send confirmation to requester
            $this->sendToRequester($request, 'request_created');
            
            // Send notification to appropriate approver
            if ($request['status'] === 'Pending HOD') {
                $this->sendToManager($request, 'pending_manager_approval');
            } elseif ($request['status'] === 'Pending IT HOD') {
                $this->sendToITManager($request, 'pending_it_approval');
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Email notification error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email notification when request is approved by manager
     */
    public function sendManagerApprovalNotification($request_id) {
        try {
            $request = $this->getRequestDetails($request_id);
            if (!$request) return false;
            
            // Notify requester of manager approval
            $this->sendToRequester($request, 'manager_approved');
            
            // Notify IT Manager for final approval
            $this->sendToITManager($request, 'pending_it_approval');
            
            return true;
        } catch (Exception $e) {
            error_log("Manager approval email error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email notification when request is approved by IT Manager
     */
    public function sendITApprovalNotification($request_id) {
        try {
            $request = $this->getRequestDetails($request_id);
            if (!$request) return false;
            
            // Notify requester of final approval
            $this->sendToRequester($request, 'request_approved');
            
            return true;
        } catch (Exception $e) {
            error_log("IT approval email error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email notification when request is rejected
     */
    public function sendRejectionNotification($request_id) {
        try {
            $request = $this->getRequestDetails($request_id);
            if (!$request) return false;
            
            // Notify requester of rejection
            $this->sendToRequester($request, 'request_rejected');
            
            return true;
        } catch (Exception $e) {
            error_log("Rejection email error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email to requester
     */
    private function sendToRequester($request, $template) {
        $this->mail->clearAddresses();
        $this->mail->addAddress($request['user_email'], $request['user_name']);
        
        $subject = $this->getEmailSubject($template, $request);
        $body = $this->getEmailBody($template, $request);
        
        $this->mail->Subject = $subject;
        $this->mail->Body = $body;
        $this->mail->isHTML(true);
        
        return $this->mail->send();
    }
    
    /**
     * Send email to manager
     */
    private function sendToManager($request, $template) {
        if (!$request['manager_email']) return false;
        
        $this->mail->clearAddresses();
        $this->mail->addAddress($request['manager_email'], $request['manager_name']);
        
        $subject = $this->getEmailSubject($template, $request);
        $body = $this->getEmailBody($template, $request);
        
        $this->mail->Subject = $subject;
        $this->mail->Body = $body;
        $this->mail->isHTML(true);
        
        return $this->mail->send();
    }
    
    /**
     * Send email to IT Manager
     */
    private function sendToITManager($request, $template) {
        // Get all IT Managers
        $it_managers = fetchAll($this->pdo, "
            SELECT email, name FROM users 
            WHERE role = 'IT Manager' AND is_active = 1
        ");
        
        foreach ($it_managers as $manager) {
            $this->mail->clearAddresses();
            $this->mail->addAddress($manager['email'], $manager['name']);
            
            $subject = $this->getEmailSubject($template, $request);
            $body = $this->getEmailBody($template, $request);
            
            $this->mail->Subject = $subject;
            $this->mail->Body = $body;
            $this->mail->isHTML(true);
            
            $this->mail->send();
        }
        
        return true;
    }
    
    /**
     * Get request details with all related information
     */
    private function getRequestDetails($request_id) {
        return fetchOne($this->pdo, "
            SELECT r.*, 
                   u.name as user_name, u.email as user_email,
                   c.name as category_name, sc.name as subcategory_name,
                   co.name as company_name, d.name as department_name,
                   m.name as manager_name, m.email as manager_email,
                   s.name as site_name
            FROM requests r
            JOIN users u ON r.user_id = u.id
            JOIN categories c ON r.category_id = c.id
            JOIN subcategories sc ON r.subcategory_id = sc.id
            JOIN companies co ON u.company_id = co.id
            JOIN departments d ON u.department_id = d.id
            LEFT JOIN users m ON u.reporting_manager_id = m.id
            LEFT JOIN sites s ON r.site_id = s.id
            WHERE r.id = ?
        ", [$request_id]);
    }
    
    /**
     * Get email subject based on template
     */
    private function getEmailSubject($template, $request) {
        switch ($template) {
            case 'request_created':
                return "Request #{$request['id']} Created - {$request['title']}";
            case 'pending_manager_approval':
                return "Action Required: Approve Request #{$request['id']} - {$request['title']}";
            case 'manager_approved':
                return "Request #{$request['id']} Approved by Manager - {$request['title']}";
            case 'pending_it_approval':
                return "Action Required: IT Approval for Request #{$request['id']} - {$request['title']}";
            case 'request_approved':
                return "Request #{$request['id']} Approved - {$request['title']}";
            case 'request_rejected':
                return "Request #{$request['id']} Rejected - {$request['title']}";
            default:
                return "IT Request Notification - {$request['title']}";
        }
    }
    
    /**
     * Get email body based on template
     */
    private function getEmailBody($template, $request) {
        $request_url = EmailConfig::SYSTEM_URL . "/requests/view.php?id=" . $request['id'];
        $approve_url = EmailConfig::SYSTEM_URL . "/requests/approve.php?id=" . $request['id'];
        
        switch ($template) {
            case 'request_created':
                return "
                <html>
                <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                        <h2 style='color: #007bff;'>Request Created Successfully</h2>
                        <p>Dear {$request['user_name']},</p>
                        <p>Your IT request has been created successfully and is now in the approval process.</p>
                        
                        <div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                            <h3>Request Details:</h3>
                            <p><strong>Request ID:</strong> #{$request['id']}</p>
                            <p><strong>Title:</strong> {$request['title']}</p>
                            <p><strong>Category:</strong> {$request['category_name']} - {$request['subcategory_name']}</p>
                            <p><strong>Status:</strong> {$request['status']}</p>
                            <p><strong>Created:</strong> " . date('M j, Y g:i A', strtotime($request['created_at'])) . "</p>
                        </div>
                        
                        <p><a href='{$request_url}' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View Request</a></p>
                        
                        <p>You will receive updates as your request progresses through the approval process.</p>
                        
                        <hr style='margin: 30px 0;'>
                        <p style='font-size: 12px; color: #666;'>IT Request Management System</p>
                    </div>
                </body>
                </html>";
                
            case 'pending_manager_approval':
                return "
                <html>
                <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                        <h2 style='color: #ffc107;'>Action Required: Request Approval</h2>
                        <p>Dear {$request['manager_name']},</p>
                        <p>A new IT request requires your approval.</p>
                        
                        <div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #ffc107;'>
                            <h3>Request Details:</h3>
                            <p><strong>Request ID:</strong> #{$request['id']}</p>
                            <p><strong>Title:</strong> {$request['title']}</p>
                            <p><strong>Requester:</strong> {$request['user_name']} ({$request['department_name']})</p>
                            <p><strong>Category:</strong> {$request['category_name']} - {$request['subcategory_name']}</p>
                            <p><strong>Description:</strong> {$request['description']}</p>
                        </div>
                        
                        <p>
                            <a href='{$approve_url}' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Review & Approve</a>
                            <a href='{$request_url}' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View Details</a>
                        </p>
                        
                        <p>Please review and take action on this request at your earliest convenience.</p>
                        
                        <hr style='margin: 30px 0;'>
                        <p style='font-size: 12px; color: #666;'>IT Request Management System</p>
                    </div>
                </body>
                </html>";
                
            case 'manager_approved':
                return "
                <html>
                <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                        <h2 style='color: #28a745;'>Request Approved by Manager</h2>
                        <p>Dear {$request['user_name']},</p>
                        <p>Good news! Your IT request has been approved by your manager and forwarded to the IT team for final approval.</p>
                        
                        <div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #28a745;'>
                            <h3>Request Details:</h3>
                            <p><strong>Request ID:</strong> #{$request['id']}</p>
                            <p><strong>Title:</strong> {$request['title']}</p>
                            <p><strong>Approved by:</strong> {$request['manager_name']}</p>
                            <p><strong>Current Status:</strong> {$request['status']}</p>
                        </div>
                        
                        <p><a href='{$request_url}' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View Request</a></p>
                        
                        <p>Your request is now awaiting final approval from the IT team. You'll receive another notification once it's been processed.</p>
                        
                        <hr style='margin: 30px 0;'>
                        <p style='font-size: 12px; color: #666;'>IT Request Management System</p>
                    </div>
                </body>
                </html>";
                
            case 'pending_it_approval':
                return "
                <html>
                <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                        <h2 style='color: #fd7e14;'>Action Required: IT Approval Needed</h2>
                        <p>Dear IT Manager,</p>
                        <p>A request requires your final approval.</p>
                        
                        <div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #fd7e14;'>
                            <h3>Request Details:</h3>
                            <p><strong>Request ID:</strong> #{$request['id']}</p>
                            <p><strong>Title:</strong> {$request['title']}</p>
                            <p><strong>Requester:</strong> {$request['user_name']} ({$request['department_name']})</p>
                            <p><strong>Category:</strong> {$request['category_name']} - {$request['subcategory_name']}</p>
                            <p><strong>Manager Approved:</strong> " . ($request['manager_name'] ? "Yes, by {$request['manager_name']}" : "N/A") . "</p>
                            <p><strong>Description:</strong> {$request['description']}</p>
                        </div>
                        
                        <p>
                            <a href='{$approve_url}' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Review & Approve</a>
                            <a href='{$request_url}' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View Details</a>
                        </p>
                        
                        <p>Please review and provide final approval for this request.</p>
                        
                        <hr style='margin: 30px 0;'>
                        <p style='font-size: 12px; color: #666;'>IT Request Management System</p>
                    </div>
                </body>
                </html>";
                
            case 'request_approved':
                return "
                <html>
                <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                        <h2 style='color: #28a745;'>🎉 Request Approved!</h2>
                        <p>Dear {$request['user_name']},</p>
                        <p>Excellent news! Your IT request has been fully approved and will be processed by the IT team.</p>
                        
                        <div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #28a745;'>
                            <h3>Request Details:</h3>
                            <p><strong>Request ID:</strong> #{$request['id']}</p>
                            <p><strong>Title:</strong> {$request['title']}</p>
                            <p><strong>Status:</strong> Approved ✅</p>
                            <p><strong>Final Approval Date:</strong> " . date('M j, Y g:i A') . "</p>
                        </div>
                        
                        <p><a href='{$request_url}' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View Request</a></p>
                        
                        <p>The IT team will now work on fulfilling your request. You may be contacted for additional details if needed.</p>
                        
                        <hr style='margin: 30px 0;'>
                        <p style='font-size: 12px; color: #666;'>IT Request Management System</p>
                    </div>
                </body>
                </html>";
                
            case 'request_rejected':
                return "
                <html>
                <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                        <h2 style='color: #dc3545;'>Request Rejected</h2>
                        <p>Dear {$request['user_name']},</p>
                        <p>We regret to inform you that your IT request has been rejected.</p>
                        
                        <div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #dc3545;'>
                            <h3>Request Details:</h3>
                            <p><strong>Request ID:</strong> #{$request['id']}</p>
                            <p><strong>Title:</strong> {$request['title']}</p>
                            <p><strong>Status:</strong> Rejected ❌</p>
                            " . (!empty($request['rejection_remarks']) ? "<p><strong>Rejection Reason:</strong> {$request['rejection_remarks']}</p>" : "") . "
                        </div>
                        
                        <p><a href='{$request_url}' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View Request Details</a></p>
                        
                        <p>If you have questions about this decision or would like to submit a revised request, please contact your manager or the IT team.</p>
                        
                        <hr style='margin: 30px 0;'>
                        <p style='font-size: 12px; color: #666;'>IT Request Management System</p>
                    </div>
                </body>
                </html>";
                
            default:
                return "<p>IT Request notification for Request #{$request['id']}</p>";
        }
    }
}
?>