<?php
/**
 * Password Hash Generator
 * Run this script to generate correct password hashes for the default users
 */

// Generate hash for "admin123"
$password = 'admin123';
$password_1 = 'user123';
$hash = password_hash($password, PASSWORD_DEFAULT);
$hash_1 = password_hash($password_1, PASSWORD_DEFAULT);
echo "Password: " . $password . "\n";
echo "Hash: " . $hash . "\n\n";
echo "Password2: " . $password_1. "\n";
echo "Hash2: " . $hash_1 . "\n\n";
// SQL to update the users table with correct hashes
echo "Run this SQL to fix the password hashes:\n\n";
echo "UPDATE users SET password = '$hash' WHERE email = 'admin@facebook.com';\n";
echo "UPDATE users SET password = '$hash_1' WHERE email = 'john@facebook.com';\n\n";

echo "Or delete and recreate the users:\n\n";
echo "DELETE FROM users WHERE email IN ('admin@company.com', 'user@company.com');\n";
echo "INSERT INTO users (username, email, password, role) VALUES \n";
echo "('admin', 'admin@company.com', '$hash', 'Admin'),\n";
echo "('user', 'user@company.com', '$hash', 'User');\n";
?>
`password`