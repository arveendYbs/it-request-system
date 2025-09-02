<?php
/**
 * Authentication and Authorization System
 * includes/auth.php
 */

session_start();
require_once __DIR__ . '/../config/db.php';

class Auth {
    private $pdo;
    
    public function __construct($database_connection) {
        $this->pdo = $database_connection;
    }
    
    /**
     * Login user with email and password
     */
    public function login($email, $password) {
        $user = fetchOne($this->pdo, 
            "SELECT u.*, d.name as department_name, c.name as company_name, 
                    m.name as manager_name
             FROM users u 
             LEFT JOIN departments d ON u.department_id = d.id 
             LEFT JOIN companies c ON u.company_id = c.id 
             LEFT JOIN users m ON u.reporting_manager_id = m.id 
             WHERE u.email = ? AND u.is_active = 1", 
            [$email]
        );
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['department_id'] = $user['department_id'];
            $_SESSION['company_id'] = $user['company_id'];
            $_SESSION['reporting_manager_id'] = $user['reporting_manager_id'];
            $_SESSION['login_time'] = time();
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
    }
    
    /**
     * Get current user data
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'],
            'email' => $_SESSION['user_email'],
            'role' => $_SESSION['user_role'],
            'department_id' => $_SESSION['department_id'],
            'company_id' => $_SESSION['company_id'],
            'reporting_manager_id' => $_SESSION['reporting_manager_id']
        ];
    }
    
    /**
     * Check if user has specific role
     */
    public function hasRole($roles) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        if (is_string($roles)) {
            $roles = [$roles];
        }
        
        return in_array($_SESSION['user_role'], $roles);
    }
    
    /**
     * Check if user can manage other user (is their manager)
     */
    public function canManageUser($user_id) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        // Admin can manage anyone
        if ($_SESSION['user_role'] === ['Admin', 'IT Manager']) {
            return true;
        }
        
        // Check if current user is the reporting manager
        $user = fetchOne($this->pdo, 
            "SELECT reporting_manager_id FROM users WHERE id = ?", 
            [$user_id]
        );
        
        return $user && $user['reporting_manager_id'] == $_SESSION['user_id'];
    }
    
    /**
     * Check if user can edit request
     */
    public function canEditRequest($request_id) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        // Admin can edit any request
        if ($_SESSION['user_role'] === 'Admin') {
            return true;
        }
        
        // Get request details
        $request = fetchOne($this->pdo, 
            "SELECT user_id, status FROM requests WHERE id = ?", 
            [$request_id]
        );
        
        if (!$request) {
            return false;
        }
        
        // Owner can edit only if not yet approved/rejected
        if ($request['user_id'] == $_SESSION['user_id']) {
            return !in_array($request['status'], ['Approved', 'Rejected', 'Approved by Manager']);
        }
        
        return false;
    }
    
    /**
     * Check if user can approve request
     */
    public function canApproveRequest($request_id) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        // Admin can approve any request
        if ($_SESSION['user_role'] === 'Admin') {
            return true;
        }
        
        // Get request details with user info
        $request = fetchOne($this->pdo, 
            "SELECT r.*, u.reporting_manager_id 
             FROM requests r 
             JOIN users u ON r.user_id = u.id 
             WHERE r.id = ?", 
            [$request_id]
        );
        
        if (!$request) {
            return false;
        }
        
        // Manager can approve if they are the reporting manager and status is pending manager
        if ($_SESSION['user_role'] === 'Manager' && 
            $request['reporting_manager_id'] == $_SESSION['user_id'] &&
            $request['status'] === 'Pending HOD') {
            return true;
        }
        
        // IT Manager can approve if:
        // 1. Status is "Approved by Manager" or "Pending IT HOD", OR
        // 2. They are the direct reporting manager and status is "Pending IT HOD"
        if ($_SESSION['user_role'] === 'IT Manager') {
            if (in_array($request['status'], ['Approved by Manager', 'Pending IT HOD'])) {
                return true;
            }
            // IT Manager can also approve if they are the direct reporting manager
            if ($request['reporting_manager_id'] == $_SESSION['user_id'] && 
                $request['status'] === 'Pending IT HOD') {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Logout user
     */
    public function logout() {
        session_destroy();
        return true;
    }
    
    /**
     * Redirect to login if not authenticated
     */
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: login.php');
            exit();
        }
    }
    
    /**
     * Require specific role(s)
     */
    public function requireRole($roles) {
        $this->requireLogin();
        
        if (!$this->hasRole($roles)) {
            http_response_code(403);
            include __DIR__ . '/../403.php';
            exit();
        }
    }
}

// Global auth instance
$auth = new Auth($pdo);

// Helper functions
function getCurrentUser() {
    global $auth;
    return $auth->getCurrentUser();
}

function hasRole($roles) {
    global $auth;
    return $auth->hasRole($roles);
}

function requireLogin() {
    global $auth;
    $auth->requireLogin();
}

function requireRole($roles) {
    global $auth;
    $auth->requireRole($roles);
}
?>