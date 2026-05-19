-- Transaction History Table
-- This table tracks all schedule-related actions for audit purposes

CREATE TABLE IF NOT EXISTS schedule_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    action_type ENUM('approved', 'cancelled', 'pullout', 'rescheduled', 'requested') NOT NULL,
    schedule_id INT NULL COMMENT 'Reference to approved_schedules',
    request_id INT NULL COMMENT 'Reference to schedule_requests',
    title VARCHAR(255) NOT NULL,
    event_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    program_owner VARCHAR(100) NOT NULL,
    office VARCHAR(100) NOT NULL,
    deped_email VARCHAR(100) NULL,
    performed_by INT NULL COMMENT 'User who performed the action',
    reason TEXT NULL COMMENT 'Reason for action (cancellation, pullout, reschedule)',
    old_date DATE NULL COMMENT 'For reschedules - original date',
    old_start_time TIME NULL COMMENT 'For reschedules - original start time',
    old_end_time TIME NULL COMMENT 'For reschedules - original end time',
    action_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (performed_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_action_type (action_type),
    INDEX idx_action_date (action_date),
    INDEX idx_event_date (event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
