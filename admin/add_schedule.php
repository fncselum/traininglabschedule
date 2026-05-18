<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/email_helper.php';

requireAnyRole(['admin', 'superadmin']);

// Resolve and whitelist redirect once
$allowed_redirects = ['approved_schedules.php', '../index.php'];
$redirect = $_POST['redirect'] ?? 'approved_schedules.php';
if (!in_array($redirect, $allowed_redirects)) {
    $redirect = 'approved_schedules.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start_date       = trim($_POST['start_date']       ?? '');
    $title            = trim($_POST['title']            ?? '');
    $start_time       = trim($_POST['start_time']       ?? '');
    $end_time         = trim($_POST['end_time']         ?? '');
    $participants     = trim($_POST['participants']     ?? '');
    $program_owner    = trim($_POST['program_owner']    ?? '');
    $office           = trim($_POST['office']           ?? '');
    $deped_email      = trim($_POST['deped_email']      ?? '');
    $remarks          = trim($_POST['remarks']          ?? '');

    // Validate
    $errors = [];
    if (empty($start_date))       $errors[] = 'Date is required.';
    if (empty($title))             $errors[] = 'Title is required.';
    if (empty($deped_email))       $errors[] = 'Email is required.';
    elseif (!filter_var($deped_email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please provide a valid email address for notifications.';
    if (empty($start_time))        $errors[] = 'Start time is required.';
    if (empty($end_time))          $errors[] = 'End time is required.';
    if (empty($participants))      $errors[] = 'Number of participants is required.';
    if (empty($program_owner))     $errors[] = 'Program owner is required.';
    if (empty($office))            $errors[] = 'Office is required.';
    if (!empty($start_time) && !empty($end_time) && strtotime($start_time) >= strtotime($end_time)) {
        $errors[] = 'The end time must be later than the start time. Please adjust the schedule timing.';
    }

    if (!empty($errors)) {
        $_SESSION['flash_message'] = implode(' ', $errors);
        $_SESSION['flash_type']    = 'error';
    } else {
        $conn = getDBConnection();

        // Conflict check — any existing schedule overlapping the requested slot
        $chk = $conn->prepare("
            SELECT COUNT(*) AS cnt FROM approved_schedules
            WHERE start_date = ? AND (start_time < ? AND end_time > ?)
        ");
        $chk->bind_param("sss", $start_date, $end_time, $start_time);
        $chk->execute();
        $conflict = $chk->get_result()->fetch_assoc()['cnt'];
        $chk->close();

        if ($conflict > 0) {
            $_SESSION['flash_message'] = 'Schedule creation cancelled: The selected time slot conflicts with an existing approved schedule. Please choose a different time.';
            $_SESSION['flash_type']    = 'error';
        } else {
            $admin_id  = $_SESSION['user_id'] ?? null;
            $rem_val   = !empty($remarks) ? $remarks : null;

            $stmt = $conn->prepare("
                INSERT INTO approved_schedules
                    (request_id, start_date, title, start_time, end_time,
                     participants, program_owner, office, deped_email, approved_by, approved_at)
                VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param("ssssssssi",
                $start_date, $title, $start_time, $end_time,
                $participants, $program_owner, $office, $deped_email, $admin_id
            );

            if ($stmt->execute()) {
                $_SESSION['flash_message'] = "Walk-in training laboratory schedule \"$title\" has been successfully added to the calendar.";
                $_SESSION['flash_type']    = 'success';
                
                // Send email notification
                $emailData = [
                    'title' => $title,
                    'start_date' => $start_date,
                    'start_time' => $start_time,
                    'end_time' => $end_time,
                    'participants' => $participants,
                    'program_owner' => $program_owner,
                    'office' => $office
                ];
                sendScheduleAddedEmail($deped_email, $emailData);
            } else {
                $_SESSION['flash_message'] = 'Unable to add the training laboratory schedule. Please verify the details and try again.';
                $_SESSION['flash_type']    = 'error';
            }
            $stmt->close();
        }

        closeDBConnection($conn);
    }
}

header("Location: $redirect");
exit();
?>
