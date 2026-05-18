<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/email_helper.php';

requireAnyRole(['admin', 'superadmin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_id'])) {
    $schedule_id  = intval($_POST['schedule_id']);
    $new_date     = $_POST['new_date']       ?? '';
    $new_start    = $_POST['new_start_time'] ?? '';
    $new_end      = $_POST['new_end_time']   ?? '';
    $reason       = trim($_POST['reason']    ?? '');

    $errors = [];
    if (empty($new_date) || empty($new_start) || empty($new_end)) {
        $errors[] = 'All date and time fields are required.';
    }
    if (!empty($new_date) && strtotime($new_date) < strtotime(date('Y-m-d'))) {
        $errors[] = 'New date cannot be in the past.';
    }
    if (!empty($new_start) && !empty($new_end) && strtotime($new_start) >= strtotime($new_end)) {
        $errors[] = 'The end time must be later than the start time. Please adjust the schedule timing.';
    }

    if (!empty($errors)) {
        $_SESSION['flash_message'] = implode(' ', $errors);
        $_SESSION['flash_type'] = 'error';
        header('Location: approved_schedules.php');
        exit();
    }

    $conn = getDBConnection();

    // Conflict check (exclude current schedule)
    $chk = $conn->prepare("
        SELECT COUNT(*) AS cnt FROM approved_schedules
        WHERE schedule_id != ? AND start_date = ?
        AND (
            (start_time < ? AND end_time > ?) OR
            (start_time < ? AND end_time > ?) OR
            (start_time >= ? AND end_time <= ?)
        )
    ");
    $chk->bind_param("isssssss",
        $schedule_id, $new_date,
        $new_end, $new_start,
        $new_end, $new_end,
        $new_start, $new_end
    );
    $chk->execute();
    $conflict = $chk->get_result()->fetch_assoc()['cnt'];
    $chk->close();

    if ($conflict > 0) {
        $_SESSION['flash_message'] = 'Reschedule operation cancelled: The selected time slot conflicts with an existing approved schedule. Please choose a different time.';
        $_SESSION['flash_type'] = 'error';
        closeDBConnection($conn);
        header('Location: approved_schedules.php');
        exit();
    }

    $conn->begin_transaction();
    try {
        // Fetch current schedule + requestor
        $stmt = $conn->prepare("
            SELECT a.*, sr.requestor_id
            FROM approved_schedules a
            LEFT JOIN schedule_requests sr ON a.request_id = sr.request_id
            WHERE a.schedule_id = ?
        ");
        $stmt->bind_param("i", $schedule_id);
        $stmt->execute();
        $schedule = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$schedule) throw new Exception("Schedule not found.");

        // Update the schedule
        $upd = $conn->prepare("
            UPDATE approved_schedules SET start_date = ?, start_time = ?, end_time = ?
            WHERE schedule_id = ?
        ");
        $upd->bind_param("sssi", $new_date, $new_start, $new_end, $schedule_id);
        $upd->execute();
        $upd->close();

        // Send email notification if deped_email exists
        if (!empty($schedule['deped_email'])) {
            $oldScheduleData = [
                'start_date' => $schedule['start_date'],
                'start_time' => $schedule['start_time'],
                'end_time' => $schedule['end_time']
            ];
            $newScheduleData = [
                'title' => $schedule['title'],
                'start_date' => $new_date,
                'start_time' => $new_start,
                'end_time' => $new_end,
                'participants' => $schedule['participants'],
                'program_owner' => $schedule['program_owner'],
                'office' => $schedule['office']
            ];
            sendScheduleRescheduleEmail($schedule['deped_email'], $oldScheduleData, $newScheduleData, $reason);
        }

        // Notify requestor
        if ($schedule['requestor_id']) {
            $fmt_date  = date('F d, Y', strtotime($new_date));
            $fmt_start = date('h:i A',  strtotime($new_start));
            $fmt_end   = date('h:i A',  strtotime($new_end));
            $notif_msg = "Your training laboratory schedule '{$schedule['title']}' has been rescheduled by the administrator to $fmt_date from $fmt_start to $fmt_end. Please adjust your preparations accordingly.";
            if (!empty($reason)) $notif_msg .= " Reason for rescheduling: $reason";

            $notif = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'schedule_modified')");
            $notif->bind_param("is", $schedule['requestor_id'], $notif_msg);
            $notif->execute();
            $notif->close();
        }

        $conn->commit();
        $_SESSION['flash_message'] = "Training laboratory schedule '{$schedule['title']}' has been successfully rescheduled and updated in the calendar.";
        $_SESSION['flash_type'] = 'success';

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['flash_message'] = 'Unable to reschedule the training laboratory session. Please verify the details and try again.';
        $_SESSION['flash_type'] = 'error';
    }

    closeDBConnection($conn);
}

header('Location: approved_schedules.php');
exit();
?>
