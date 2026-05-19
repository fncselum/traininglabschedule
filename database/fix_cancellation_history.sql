-- Fix cancellation_requests to preserve history when schedules are deleted
-- This allows us to keep cancellation records even after the schedule is removed

-- Step 1: Add columns to store schedule details in cancellation_requests
ALTER TABLE cancellation_requests 
ADD COLUMN IF NOT EXISTS title VARCHAR(255) NULL AFTER schedule_id,
ADD COLUMN IF NOT EXISTS start_date DATE NULL AFTER title,
ADD COLUMN IF NOT EXISTS start_time TIME NULL AFTER start_date,
ADD COLUMN IF NOT EXISTS end_time TIME NULL AFTER start_time,
ADD COLUMN IF NOT EXISTS participants TEXT NULL AFTER end_time,
ADD COLUMN IF NOT EXISTS program_owner VARCHAR(100) NULL AFTER participants,
ADD COLUMN IF NOT EXISTS office VARCHAR(100) NULL AFTER program_owner,
ADD COLUMN IF NOT EXISTS deped_email VARCHAR(100) NULL AFTER office;

-- Step 2: Copy existing schedule data into cancellation_requests
UPDATE cancellation_requests cr
JOIN approved_schedules a ON cr.schedule_id = a.schedule_id
SET 
    cr.title = a.title,
    cr.start_date = a.start_date,
    cr.start_time = a.start_time,
    cr.end_time = a.end_time,
    cr.participants = a.participants,
    cr.program_owner = a.program_owner,
    cr.office = a.office,
    cr.deped_email = a.deped_email
WHERE cr.title IS NULL;

-- Step 3: Drop the existing foreign key constraint
ALTER TABLE cancellation_requests DROP FOREIGN KEY cancellation_requests_ibfk_1;

-- Step 4: Make schedule_id nullable (so it can be NULL after schedule is deleted)
ALTER TABLE cancellation_requests MODIFY COLUMN schedule_id INT NULL;

-- Step 5: Add new foreign key with ON DELETE SET NULL
ALTER TABLE cancellation_requests 
ADD CONSTRAINT fk_cancellation_schedule 
FOREIGN KEY (schedule_id) REFERENCES approved_schedules(schedule_id) ON DELETE SET NULL;

-- Now when a schedule is deleted, the cancellation_request remains with schedule_id = NULL
-- but all the schedule details are preserved in the cancellation_requests table itself
