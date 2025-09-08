<?php
/**
 * Email Configuration
 * config/email.php
 */

class EmailConfig {
    // SMTP Configuration
    const SMTP_HOST = 'smtp.office365.com';
    const SMTP_PORT = 587;
    const SMTP_USERNAME = 'arveend@ybsinternational.com'; // Your email
    const SMTP_PASSWORD = 'Ybs@2025!'; 
    const SMTP_ENCRYPTION = 'tls'; // or 'ssl'
    const SMTPDebug = 2; // or 3 for more detail
    const Debugoutput = 'html';
    // Default sender information
    const FROM_EMAIL = 'arveend@ybsinternational.com';
    const FROM_NAME = 'IT Request System';
    
    // System URLs
    const SYSTEM_URL = 'http://localhost/it-request-system'; // Update this to your actual URL
    
    // Email templates directory
    const TEMPLATE_DIR = __DIR__ . '/../email_templates/';
}
?>
