<?php
/**
 * Path Configuration Helper
 * config/paths.php
 * 
 * This file helps determine the correct paths based on your installation directory
 */

// Get the base path of the application
function getBasePath() {
    // Get the document root and current script directory
    $documentRoot = $_SERVER['DOCUMENT_ROOT'];
    $scriptDir = dirname($_SERVER['SCRIPT_FILENAME']);
    
    // Calculate the relative path from document root
    $relativePath = str_replace($documentRoot, '', $scriptDir);
    
    // Find the application root (look for config directory)
    $pathParts = explode('/', trim($relativePath, '/'));
    $baseParts = [];
    
    // Go up directories until we find the application root
    $currentPath = $scriptDir;
    while ($currentPath !== $documentRoot && !empty($currentPath)) {
        if (file_exists($currentPath . '/config/db.php')) {
            // Found the application root
            $appRoot = str_replace($documentRoot, '', $currentPath);
            return rtrim($appRoot, '/') . '/';
        }
        $currentPath = dirname($currentPath);
        if ($currentPath === dirname($currentPath)) break; // Prevent infinite loop
    }
    
    // Fallback: try to detect from script name
    $scriptName = $_SERVER['SCRIPT_NAME'];
    if (strpos($scriptName, '/') !== false) {
        $parts = explode('/', trim($scriptName, '/'));
        
        // Look for common subdirectories that indicate we're not in root
        foreach (['requests', 'users', 'companies', 'departments', 'categories', 'reports'] as $subdir) {
            $key = array_search($subdir, $parts);
            if ($key !== false) {
                // Found a subdirectory, so base path is everything before it
                $baseParts = array_slice($parts, 0, $key);
                return '/' . implode('/', $baseParts) . (count($baseParts) > 0 ? '/' : '');
            }
        }
        
        // If we have multiple parts but didn't find a known subdir, 
        // assume the last part is the filename and everything else is the path
        if (count($parts) > 1) {
            array_pop($parts); // Remove filename
            return '/' . implode('/', $parts) . '/';
        }
    }
    
    return '/';
}

// Get URL helper function  
function url($path = '') {
    static $basePath = null;
    
    if ($basePath === null) {
        $basePath = getBasePath();
    }
    
    // Remove leading slash from path if present
    $path = ltrim($path, '/');
    
    // Return the full path
    return $basePath . $path;
}

// Define the base path constant
if (!defined('BASE_PATH')) {
    define('BASE_PATH', getBasePath());
}

// Define commonly used URLs
define('DASHBOARD_URL', url('dashboard.php'));
define('LOGIN_URL', url('login.php'));
define('LOGOUT_URL', url('logout.php'));
define('REQUESTS_URL', url('requests/'));
define('USERS_URL', url('users/'));
define('COMPANIES_URL', url('companies/'));
define('DEPARTMENTS_URL', url('departments/'));
define('CATEGORIES_URL', url('categories/'));
define('REPORTS_URL', url('reports/'));

// Asset helper function
function asset($path) {
    return url($path);
}
?>