-- IT Request Management System Database Schema
-- Import this into phpMyAdmin

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Database: it_request_system
CREATE DATABASE IF NOT EXISTS `it_request_system` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `it_request_system`;

-- Table structure for table `companies`
CREATE TABLE `companies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `departments`
CREATE TABLE `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  `company_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_dept_company` (`company_id`),
  CONSTRAINT `fk_dept_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `categories`
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `subcategories`
CREATE TABLE `subcategories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  `category_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_subcat_category` (`category_id`),
  CONSTRAINT `fk_subcat_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `users`
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Admin','Manager','IT Manager','User') NOT NULL DEFAULT 'User',
  `department_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `reporting_manager_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `fk_user_department` (`department_id`),
  KEY `fk_user_company` (`company_id`),
  KEY `fk_user_manager` (`reporting_manager_id`),
  CONSTRAINT `fk_user_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_user_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  CONSTRAINT `fk_user_manager` FOREIGN KEY (`reporting_manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `requests`
CREATE TABLE `requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `category_id` int(11) NOT NULL,
  `subcategory_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('Pending Manager','Approved by Manager','Pending IT HOD','Approved','Rejected') NOT NULL DEFAULT 'Pending Manager',
  `approved_by_manager_id` int(11) DEFAULT NULL,
  `approved_by_manager_date` timestamp NULL DEFAULT NULL,
  `approved_by_it_manager_id` int(11) DEFAULT NULL,
  `approved_by_it_manager_date` timestamp NULL DEFAULT NULL,
  `rejection_remarks` text,
  `rejected_by_id` int(11) DEFAULT NULL,
  `rejected_date` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_request_category` (`category_id`),
  KEY `fk_request_subcategory` (`subcategory_id`),
  KEY `fk_request_user` (`user_id`),
  KEY `fk_request_manager` (`approved_by_manager_id`),
  KEY `fk_request_it_manager` (`approved_by_it_manager_id`),
  KEY `fk_request_rejected_by` (`rejected_by_id`),
  CONSTRAINT `fk_request_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  CONSTRAINT `fk_request_subcategory` FOREIGN KEY (`subcategory_id`) REFERENCES `subcategories` (`id`),
  CONSTRAINT `fk_request_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_request_manager` FOREIGN KEY (`approved_by_manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_request_it_manager` FOREIGN KEY (`approved_by_it_manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_request_rejected_by` FOREIGN KEY (`rejected_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `request_attachments`
CREATE TABLE `request_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `stored_filename` varchar(255) NOT NULL,
  `file_size` int(11) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_attachment_request` (`request_id`),
  CONSTRAINT `fk_attachment_request` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sample data for companies
INSERT INTO `companies` (`name`, `description`) VALUES
('Facebook', 'Social media platform company'),
('Instagram', 'Photo and video sharing platform'),
('WhatsApp', 'Messaging application company');

-- Sample data for departments
INSERT INTO `departments` (`name`, `description`, `company_id`) VALUES
('IT', 'Information Technology Department', 1),
('HR', 'Human Resources Department', 1),
('Marketing', 'Marketing Department', 1),
('IT', 'Information Technology Department', 2),
('Content', 'Content Creation Department', 2),
('IT', 'Information Technology Department', 3);

-- Sample data for categories
INSERT INTO `categories` (`name`, `description`) VALUES
('Hardware', 'Hardware related requests'),
('Software', 'Software related requests'),
('Network', 'Network and connectivity issues'),
('Access Control', 'User access and permissions'),
('General', 'General IT support requests');

-- Sample data for subcategories
INSERT INTO `subcategories` (`name`, `description`, `category_id`) VALUES
('Laptop/Desktop', 'Computer hardware issues', 1),
('Printer', 'Printer related issues', 1),
('Monitor', 'Display related issues', 1),
('Software Installation', 'New software installation requests', 2),
('Software Update', 'Software update requests', 2),
('License', 'Software licensing issues', 2),
('Internet Connectivity', 'Internet connection problems', 3),
('VPN Access', 'VPN setup and issues', 3),
('WiFi Issues', 'Wireless network problems', 3),
('User Account', 'User account creation/modification', 4),
('System Access', 'System access requests', 4),
('Password Reset', 'Password reset requests', 4),
('General Support', 'General IT support', 5),
('Training', 'IT training requests', 5);

-- Sample admin user (password: admin123)
INSERT INTO `users` (`name`, `email`, `password`, `role`, `department_id`, `company_id`) VALUES
('System Administrator', 'admin@facebook.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 1, 1);

-- Sample IT Manager (password: manager123)
INSERT INTO `users` (`name`, `email`, `password`, `role`, `department_id`, `company_id`) VALUES
('IT Manager', 'itmanager@facebook.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'IT Manager', 1, 1);

-- Sample Manager (password: manager123)
INSERT INTO `users` (`name`, `email`, `password`, `role`, `department_id`, `company_id`, `reporting_manager_id`) VALUES
('Department Manager', 'manager@facebook.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Manager', 2, 1, 2);

-- Sample User (password: user123)
INSERT INTO `users` (`name`, `email`, `password`, `role`, `department_id`, `company_id`, `reporting_manager_id`) VALUES
('John Doe', 'john@facebook.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'User', 2, 1, 3);

COMMIT;