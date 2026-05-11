-- Training Laboratory Schedule System Database Setup
-- Database: traininglab

CREATE DATABASE IF NOT EXISTS traininglab;
USE traininglab;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('requestor', 'admin', 'superadmin') NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Schedule requests table
CREATE TABLE IF NOT EXISTS schedule_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    requestor_id INT NOT NULL,
    start_date DATE NOT NULL,
    title VARCHAR(255) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    participants TEXT NOT NULL,
    program_owner VARCHAR(100) NOT NULL,
    office VARCHAR(100) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    rejection_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (requestor_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Approved schedules table
CREATE TABLE IF NOT EXISTS approved_schedules (
    schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT,
    start_date DATE NOT NULL,
    title VARCHAR(255) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    participants TEXT NOT NULL,
    program_owner VARCHAR(100) NOT NULL,
    office VARCHAR(100) NOT NULL,
    approved_by INT NOT NULL,
    approved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES schedule_requests(request_id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    type ENUM('request_submitted', 'request_approved', 'request_rejected', 'schedule_modified') NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Insert default superadmin account (password: deped1234)
INSERT INTO users (username, password, role, status) 
VALUES ('superadmin', '$2y$10$oWPWbO6upFUlSDppC/HJxeezD7Mb4roNGzvh7VRVvyM5ef3TlkAVi', 'superadmin', 'active');

-- Insert sample admin account (password: deped1234)
INSERT INTO users (username, password, role, status) 
VALUES ('admin', '$2y$10$oWPWbO6upFUlSDppC/HJxeezD7Mb4roNGzvh7VRVvyM5ef3TlkAVi', 'admin', 'active');

-- Insert sample requestor account (password: deped1234)
INSERT INTO users (username, password, role, status) 
VALUES ('requestor', '$2y$10$oWPWbO6upFUlSDppC/HJxeezD7Mb4roNGzvh7VRVvyM5ef3TlkAVi', 'requestor', 'active');
