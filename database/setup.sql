-- Training Laboratory Schedule System Database Setup
-- Database: traininglab

CREATE DATABASE IF NOT EXISTS traininglab;
USE traininglab;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE,
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
    deped_email VARCHAR(100) NOT NULL COMMENT 'Requestor email address',
    start_date DATE NOT NULL,
    title VARCHAR(255) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    participants TEXT NOT NULL,
    program_owner VARCHAR(100) NOT NULL,
    office VARCHAR(100) NOT NULL,
    remarks TEXT,
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
    deped_email VARCHAR(100) DEFAULT NULL COMMENT 'Requestor email address for notifications',
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

-- Cancellation requests table
CREATE TABLE IF NOT EXISTS cancellation_requests (
    cancellation_id INT AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT NOT NULL,
    requestor_id INT NOT NULL,
    reason TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    processed_by INT NULL,
    FOREIGN KEY (schedule_id) REFERENCES approved_schedules(schedule_id) ON DELETE CASCADE,
    FOREIGN KEY (requestor_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Insert default superadmin account (password: deped1234)
INSERT INTO users (username, email, password, role, status) 
VALUES ('superadmin', 'superadmin@traininglab.edu', '$2y$10$oWPWbO6upFUlSDppC/HJxeezD7Mb4roNGzvh7VRVvyM5ef3TlkAVi', 'superadmin', 'active');

-- Insert sample admin account (password: deped1234)
INSERT INTO users (username, email, password, role, status) 
VALUES ('admin', 'admin@traininglab.edu', '$2y$10$oWPWbO6upFUlSDppC/HJxeezD7Mb4roNGzvh7VRVvyM5ef3TlkAVi', 'admin', 'active');

-- Insert sample requestor account (password: deped1234)
INSERT INTO users (username, email, password, role, status) 
VALUES ('requestor', 'requestor@traininglab.edu', '$2y$10$oWPWbO6upFUlSDppC/HJxeezD7Mb4roNGzvh7VRVvyM5ef3TlkAVi', 'requestor', 'active');

-- ============================================
-- MIGRATION SCRIPT FOR EXISTING DATABASES
-- Run these commands if you already have the database set up
-- ============================================

-- Add email column to users table if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS email VARCHAR(100) UNIQUE AFTER username;

-- Add remarks column to schedule_requests table if it doesn't exist
ALTER TABLE schedule_requests ADD COLUMN IF NOT EXISTS remarks TEXT AFTER office;

-- Add deped_email column to schedule_requests table if it doesn't exist
ALTER TABLE schedule_requests ADD COLUMN IF NOT EXISTS deped_email VARCHAR(100) NOT NULL AFTER requestor_id;

-- Update existing users with default email addresses (modify as needed)
UPDATE users SET email = CONCAT(username, '@traininglab.edu') WHERE email IS NULL;

-- Add requestor_email column to approved_schedules (for walk-in schedules)
ALTER TABLE approved_schedules ADD COLUMN IF NOT EXISTS requestor_email VARCHAR(100) DEFAULT NULL AFTER office;

-- Rename requestor_email to deped_email in approved_schedules for consistency
ALTER TABLE approved_schedules CHANGE COLUMN requestor_email deped_email VARCHAR(100) DEFAULT NULL;
