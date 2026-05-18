-- Fix for cancellation requests table
USE traininglab;

-- Create the cancellation_requests table if it doesn't exist
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

-- Verify the table was created
SELECT 'Table created successfully!' as status;
SHOW TABLES LIKE 'cancellation_requests';