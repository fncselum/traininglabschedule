<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/email_helper.php';

requireAnyRole(['admin', 'superadmin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_id'])) {
    $schedule_id = intval($_POST['schedule_id']);
    $reason = trim($_POST['reason'] ?? 'Schedule was pulled out by the administrator.');
    if (empty($reason)) $reason = 'Schedule was pulled out by the administrator.';

    $conn = getDBConnection();
    $conn->begin_transaction();

    try {
        // Get schedule info + linked requestor
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

        // Remove from approved_schedules (removes from public calendar)
        $del = $conn->prepare("DELETE FROM approved_schedules WHERE schedule_id = ?");
        $del->bind_param("i", $schedule_id);
        $del->execute();
        $del->close();

        // Update original request status back to pending so requestor can resubmit
        if ($schedule['request_id']) {
            $upd = $conn->prepare("UPDATE schedule_requests SET status = 'rejected', rejection_reason = ? WHERE request_id = ?");
            $pull_reason = "PULLED OUT: $reason";
            $upd->bind_param("si", $pull_reason, $schedule['request_id']);
            $upd->execute();
            $upd->close();
        }

        // Notify requestor
        if ($schedule['requestor_id']) {
            $notif_msg = "Your training laboratory schedule '{$schedule['title']}' has been cancelled by the administrator. Reason: $reason. You may submit a new schedule request if needed.";
            $notif = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'request_rejected')");
            $notif->bind_param("is", $schedule['requestor_id'], $notif_msg);
            $notif->execute();
            $notif->close();
        }

        $conn->commit();
        
        // Send email notification if deped_email exists
        if (!empty($schedule['deped_email'])) {
            $emailData = [
                'title' => $schedule['title'],
                'start_date' => $schedule['start_date'],
                'start_time' => $schedule['start_time'],
                'end_time' => $schedule['end_time']
            ];
            sendSchedulePulloutEmail($schedule['deped_email'], $emailData, $reason);
        }
        
        $_SESSION['flash_message'] = "Training laboratory schedule '{$schedule['title']}' has been successfully cancelled and removed from the calendar.";
        $_SESSION['flash_type'] = 'success';

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['flash_message'] = 'Unable to cancel the schedule. Please try again or contact system support.';
        $_SESSION['flash_type'] = 'error';
    }

    closeDBConnection($conn);
}

header('Location: approved_schedules.php');
exit();
?>
