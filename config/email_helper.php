<?php
/**
 * Email Helper Functions
 * Handles sending email notifications for schedule events
 */

require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/email_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Initialize PHPMailer with SMTP configuration
 */
function getMailer() {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        
        // Sender
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        
        // Debug
        $mail->SMTPDebug = MAIL_DEBUG;
        
        // Disable SSL verification for localhost testing (remove in production)
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        return $mail;
    } catch (Exception $e) {
        error_log("Mailer initialization error: {$mail->ErrorInfo}");
        return null;
    }
}

/**
 * Send email notification when a schedule is added
 */
function sendScheduleAddedEmail($recipientEmail, $scheduleData) {
    $mail = getMailer();
    if (!$mail) return false;
    
    try {
        $mail->addAddress($recipientEmail);
        $mail->isHTML(true);
        $mail->Subject = 'Training Laboratory Schedule Confirmation';
        
        $mail->Body = "
        <html>
        <head>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 650px; margin: 0 auto; background: #ffffff; }
                .header { background: linear-gradient(135deg, #1e3a5f, #2e5984); color: white; padding: 30px 40px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
                .header p { margin: 8px 0 0 0; font-size: 14px; opacity: 0.9; }
                .content { padding: 40px; background: #ffffff; }
                .greeting { font-size: 16px; color: #333; margin-bottom: 20px; }
                .message { font-size: 15px; color: #555; line-height: 1.8; margin-bottom: 25px; }
                .details-box { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 25px; margin: 25px 0; }
                .details-title { font-size: 16px; font-weight: 600; color: #1e3a5f; margin-bottom: 15px; border-bottom: 2px solid #4CAF50; padding-bottom: 8px; }
                .detail-row { display: flex; padding: 10px 0; border-bottom: 1px solid #e9ecef; }
                .detail-row:last-child { border-bottom: none; }
                .detail-label { font-weight: 600; color: #495057; min-width: 160px; }
                .detail-value { color: #212529; flex: 1; }
                .status-badge { display: inline-block; background: #d4edda; color: #155724; padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; margin: 15px 0; }
                .footer { background: #f8f9fa; padding: 25px 40px; text-align: center; border-top: 1px solid #dee2e6; }
                .footer-text { font-size: 13px; color: #6c757d; margin: 5px 0; }
                .footer-note { font-size: 12px; color: #868e96; margin-top: 15px; font-style: italic; }
                .divider { height: 1px; background: #dee2e6; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Training Laboratory Schedule System</h1>
                    <p>Department of Education</p>
                </div>
                <div class='content'>
                    <div class='greeting'>Dear Requestor,</div>
                    
                    <div class='message'>
                        We are pleased to inform you that your training laboratory schedule request has been successfully confirmed and added to the calendar.
                    </div>
                    
                    <div class='status-badge'>✓ CONFIRMED</div>
                    
                    <div class='details-box'>
                        <div class='details-title'>Schedule Details</div>
                        <div class='detail-row'>
                            <span class='detail-label'>Title:</span>
                            <span class='detail-value'><strong>{$scheduleData['title']}</strong></span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Date:</span>
                            <span class='detail-value'>" . date('l, F d, Y', strtotime($scheduleData['start_date'])) . "</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Time:</span>
                            <span class='detail-value'>" . date('h:i A', strtotime($scheduleData['start_time'])) . " - " . date('h:i A', strtotime($scheduleData['end_time'])) . "</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Number of Participants:</span>
                            <span class='detail-value'>{$scheduleData['participants']}</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Program Owner:</span>
                            <span class='detail-value'>{$scheduleData['program_owner']}</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Office/Division:</span>
                            <span class='detail-value'>{$scheduleData['office']}</span>
                        </div>
                    </div>
                    
                    <div class='message'>
                        Your schedule is now visible on the training laboratory calendar. Please ensure all necessary preparations are completed prior to your scheduled session.
                    </div>
                    
                    <div class='message'>
                        Should you have any questions or require assistance, please contact the laboratory administrator.
                    </div>
                    
                    <div class='message' style='margin-top: 25px;'>
                        Thank you for using the Training Laboratory Schedule System.
                    </div>
                </div>
                <div class='footer'>
                    <div class='footer-text'><strong>Training Laboratory Schedule System</strong></div>
                    <div class='footer-text'>Department of Education</div>
                    <div class='footer-note'>This is an automated message. Please do not reply to this email.</div>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->AltBody = "TRAINING LABORATORY SCHEDULE CONFIRMATION\n\n" .
                        "Dear Requestor,\n\n" .
                        "We are pleased to inform you that your training laboratory schedule request has been successfully confirmed.\n\n" .
                        "SCHEDULE DETAILS:\n" .
                        "Title: {$scheduleData['title']}\n" .
                        "Date: " . date('l, F d, Y', strtotime($scheduleData['start_date'])) . "\n" .
                        "Time: " . date('h:i A', strtotime($scheduleData['start_time'])) . " - " . date('h:i A', strtotime($scheduleData['end_time'])) . "\n" .
                        "Number of Participants: {$scheduleData['participants']}\n" .
                        "Program Owner: {$scheduleData['program_owner']}\n" .
                        "Office/Division: {$scheduleData['office']}\n\n" .
                        "Your schedule is now visible on the training laboratory calendar.\n\n" .
                        "Thank you for using the Training Laboratory Schedule System.\n\n" .
                        "Department of Education\n" .
                        "---\n" .
                        "This is an automated message. Please do not reply to this email.";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email send error: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Send email notification when a schedule is pulled out
 */
function sendSchedulePulloutEmail($recipientEmail, $scheduleData, $reason = '') {
    $mail = getMailer();
    if (!$mail) return false;
    
    try {
        $mail->addAddress($recipientEmail);
        $mail->isHTML(true);
        $mail->Subject = 'Training Laboratory Schedule Cancellation Notice';
        
        $reasonHtml = $reason ? "
            <div class='reason-box'>
                <div class='reason-title'>Reason for Cancellation:</div>
                <div class='reason-text'>" . htmlspecialchars($reason) . "</div>
            </div>" : "";
        
        $mail->Body = "
        <html>
        <head>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 650px; margin: 0 auto; background: #ffffff; }
                .header { background: linear-gradient(135deg, #dc3545, #c82333); color: white; padding: 30px 40px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
                .header p { margin: 8px 0 0 0; font-size: 14px; opacity: 0.9; }
                .content { padding: 40px; background: #ffffff; }
                .greeting { font-size: 16px; color: #333; margin-bottom: 20px; }
                .message { font-size: 15px; color: #555; line-height: 1.8; margin-bottom: 25px; }
                .notice-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; margin: 25px 0; border-radius: 4px; }
                .notice-title { font-weight: 600; color: #856404; margin-bottom: 8px; font-size: 15px; }
                .notice-text { color: #856404; font-size: 14px; }
                .details-box { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 25px; margin: 25px 0; }
                .details-title { font-size: 16px; font-weight: 600; color: #dc3545; margin-bottom: 15px; border-bottom: 2px solid #dc3545; padding-bottom: 8px; }
                .detail-row { display: flex; padding: 10px 0; border-bottom: 1px solid #e9ecef; }
                .detail-row:last-child { border-bottom: none; }
                .detail-label { font-weight: 600; color: #495057; min-width: 160px; }
                .detail-value { color: #212529; flex: 1; }
                .reason-box { background: #f8d7da; border-left: 4px solid #dc3545; padding: 20px; margin: 25px 0; border-radius: 4px; }
                .reason-title { font-weight: 600; color: #721c24; margin-bottom: 8px; font-size: 15px; }
                .reason-text { color: #721c24; font-size: 14px; line-height: 1.6; }
                .status-badge { display: inline-block; background: #f8d7da; color: #721c24; padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; margin: 15px 0; }
                .footer { background: #f8f9fa; padding: 25px 40px; text-align: center; border-top: 1px solid #dee2e6; }
                .footer-text { font-size: 13px; color: #6c757d; margin: 5px 0; }
                .footer-note { font-size: 12px; color: #868e96; margin-top: 15px; font-style: italic; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Training Laboratory Schedule System</h1>
                    <p>Department of Education</p>
                </div>
                <div class='content'>
                    <div class='greeting'>Dear Requestor,</div>
                    
                    <div class='message'>
                        We regret to inform you that your training laboratory schedule has been cancelled by the administrator.
                    </div>
                    
                    <div class='status-badge'>✕ CANCELLED</div>
                    
                    {$reasonHtml}
                    
                    <div class='details-box'>
                        <div class='details-title'>Cancelled Schedule Details</div>
                        <div class='detail-row'>
                            <span class='detail-label'>Title:</span>
                            <span class='detail-value'><strong>{$scheduleData['title']}</strong></span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Date:</span>
                            <span class='detail-value'>" . date('l, F d, Y', strtotime($scheduleData['start_date'])) . "</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Time:</span>
                            <span class='detail-value'>" . date('h:i A', strtotime($scheduleData['start_time'])) . " - " . date('h:i A', strtotime($scheduleData['end_time'])) . "</span>
                        </div>
                    </div>
                    
                    <div class='notice-box'>
                        <div class='notice-title'>Next Steps:</div>
                        <div class='notice-text'>
                            If you wish to reschedule, please submit a new request through the Training Laboratory Schedule System. 
                            For any questions or concerns regarding this cancellation, please contact the laboratory administrator.
                        </div>
                    </div>
                    
                    <div class='message'>
                        We apologize for any inconvenience this may cause and appreciate your understanding.
                    </div>
                    
                    <div class='message' style='margin-top: 25px;'>
                        Thank you for your cooperation.
                    </div>
                </div>
                <div class='footer'>
                    <div class='footer-text'><strong>Training Laboratory Schedule System</strong></div>
                    <div class='footer-text'>Department of Education</div>
                    <div class='footer-note'>This is an automated message. Please do not reply to this email.</div>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->AltBody = "TRAINING LABORATORY SCHEDULE CANCELLATION NOTICE\n\n" .
                        "Dear Requestor,\n\n" .
                        "We regret to inform you that your training laboratory schedule has been cancelled by the administrator.\n\n" .
                        ($reason ? "REASON FOR CANCELLATION:\n$reason\n\n" : "") .
                        "CANCELLED SCHEDULE DETAILS:\n" .
                        "Title: {$scheduleData['title']}\n" .
                        "Date: " . date('l, F d, Y', strtotime($scheduleData['start_date'])) . "\n" .
                        "Time: " . date('h:i A', strtotime($scheduleData['start_time'])) . " - " . date('h:i A', strtotime($scheduleData['end_time'])) . "\n\n" .
                        "If you wish to reschedule, please submit a new request through the system.\n\n" .
                        "We apologize for any inconvenience this may cause.\n\n" .
                        "Thank you for your cooperation.\n\n" .
                        "Department of Education\n" .
                        "---\n" .
                        "This is an automated message. Please do not reply to this email.";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email send error: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Send email notification when a schedule is rescheduled
 */
function sendScheduleRescheduleEmail($recipientEmail, $oldScheduleData, $newScheduleData, $reason = '') {
    $mail = getMailer();
    if (!$mail) return false;
    
    try {
        $mail->addAddress($recipientEmail);
        $mail->isHTML(true);
        $mail->Subject = 'Training Laboratory Schedule Rescheduling Notice';
        
        $reasonHtml = $reason ? "
            <div class='reason-box'>
                <div class='reason-title'>Reason for Rescheduling:</div>
                <div class='reason-text'>" . htmlspecialchars($reason) . "</div>
            </div>" : "";
        
        $mail->Body = "
        <html>
        <head>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 650px; margin: 0 auto; background: #ffffff; }
                .header { background: linear-gradient(135deg, #1e3a5f, #2e5984); color: white; padding: 30px 40px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
                .header p { margin: 8px 0 0 0; font-size: 14px; opacity: 0.9; }
                .content { padding: 40px; background: #ffffff; }
                .greeting { font-size: 16px; color: #333; margin-bottom: 20px; }
                .message { font-size: 15px; color: #555; line-height: 1.8; margin-bottom: 25px; }
                .notice-box { background: #e3f2fd; border-left: 4px solid #2196F3; padding: 20px; margin: 25px 0; border-radius: 4px; }
                .notice-title { font-weight: 600; color: #0d47a1; margin-bottom: 8px; font-size: 15px; }
                .notice-text { color: #1565c0; font-size: 14px; }
                .schedule-comparison { margin: 25px 0; }
                .schedule-box { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 20px; margin: 15px 0; }
                .schedule-box.old { border-left: 4px solid #dc3545; }
                .schedule-box.new { border-left: 4px solid #28a745; }
                .schedule-label { font-size: 14px; font-weight: 600; color: #6c757d; text-transform: uppercase; margin-bottom: 12px; letter-spacing: 0.5px; }
                .schedule-label.old { color: #dc3545; }
                .schedule-label.new { color: #28a745; }
                .schedule-row { padding: 8px 0; display: flex; }
                .schedule-row-label { font-weight: 600; color: #495057; min-width: 80px; }
                .schedule-row-value { color: #212529; }
                .details-box { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 25px; margin: 25px 0; }
                .details-title { font-size: 16px; font-weight: 600; color: #1e3a5f; margin-bottom: 15px; border-bottom: 2px solid #2196F3; padding-bottom: 8px; }
                .detail-row { display: flex; padding: 10px 0; border-bottom: 1px solid #e9ecef; }
                .detail-row:last-child { border-bottom: none; }
                .detail-label { font-weight: 600; color: #495057; min-width: 160px; }
                .detail-value { color: #212529; flex: 1; }
                .reason-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; margin: 25px 0; border-radius: 4px; }
                .reason-title { font-weight: 600; color: #856404; margin-bottom: 8px; font-size: 15px; }
                .reason-text { color: #856404; font-size: 14px; line-height: 1.6; }
                .status-badge { display: inline-block; background: #fff3cd; color: #856404; padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; margin: 15px 0; }
                .footer { background: #f8f9fa; padding: 25px 40px; text-align: center; border-top: 1px solid #dee2e6; }
                .footer-text { font-size: 13px; color: #6c757d; margin: 5px 0; }
                .footer-note { font-size: 12px; color: #868e96; margin-top: 15px; font-style: italic; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Training Laboratory Schedule System</h1>
                    <p>Department of Education</p>
                </div>
                <div class='content'>
                    <div class='greeting'>Dear Requestor,</div>
                    
                    <div class='message'>
                        We wish to inform you that your training laboratory schedule has been rescheduled by the administrator. Please take note of the updated date and time below.
                    </div>
                    
                    <div class='status-badge'>⟳ RESCHEDULED</div>
                    
                    {$reasonHtml}
                    
                    <div class='schedule-comparison'>
                        <div class='schedule-box old'>
                            <div class='schedule-label old'>✕ Previous Schedule</div>
                            <div class='schedule-row'>
                                <span class='schedule-row-label'>Date:</span>
                                <span class='schedule-row-value'>" . date('l, F d, Y', strtotime($oldScheduleData['start_date'])) . "</span>
                            </div>
                            <div class='schedule-row'>
                                <span class='schedule-row-label'>Time:</span>
                                <span class='schedule-row-value'>" . date('h:i A', strtotime($oldScheduleData['start_time'])) . " - " . date('h:i A', strtotime($oldScheduleData['end_time'])) . "</span>
                            </div>
                        </div>
                        
                        <div class='schedule-box new'>
                            <div class='schedule-label new'>✓ New Schedule</div>
                            <div class='schedule-row'>
                                <span class='schedule-row-label'>Date:</span>
                                <span class='schedule-row-value'><strong>" . date('l, F d, Y', strtotime($newScheduleData['start_date'])) . "</strong></span>
                            </div>
                            <div class='schedule-row'>
                                <span class='schedule-row-label'>Time:</span>
                                <span class='schedule-row-value'><strong>" . date('h:i A', strtotime($newScheduleData['start_time'])) . " - " . date('h:i A', strtotime($newScheduleData['end_time'])) . "</strong></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class='details-box'>
                        <div class='details-title'>Complete Schedule Information</div>
                        <div class='detail-row'>
                            <span class='detail-label'>Title:</span>
                            <span class='detail-value'><strong>{$newScheduleData['title']}</strong></span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Number of Participants:</span>
                            <span class='detail-value'>{$newScheduleData['participants']}</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Program Owner:</span>
                            <span class='detail-value'>{$newScheduleData['program_owner']}</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Office/Division:</span>
                            <span class='detail-value'>{$newScheduleData['office']}</span>
                        </div>
                    </div>
                    
                    <div class='notice-box'>
                        <div class='notice-title'>Important Reminder:</div>
                        <div class='notice-text'>
                            Please ensure all participants are informed of the new schedule. Make necessary adjustments to your preparations accordingly.
                        </div>
                    </div>
                    
                    <div class='message'>
                        Should you have any questions or concerns regarding this rescheduling, please contact the laboratory administrator.
                    </div>
                    
                    <div class='message' style='margin-top: 25px;'>
                        Thank you for your understanding and cooperation.
                    </div>
                </div>
                <div class='footer'>
                    <div class='footer-text'><strong>Training Laboratory Schedule System</strong></div>
                    <div class='footer-text'>Department of Education</div>
                    <div class='footer-note'>This is an automated message. Please do not reply to this email.</div>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->AltBody = "TRAINING LABORATORY SCHEDULE RESCHEDULING NOTICE\n\n" .
                        "Dear Requestor,\n\n" .
                        "We wish to inform you that your training laboratory schedule has been rescheduled.\n\n" .
                        ($reason ? "REASON FOR RESCHEDULING:\n$reason\n\n" : "") .
                        "PREVIOUS SCHEDULE:\n" .
                        "Date: " . date('l, F d, Y', strtotime($oldScheduleData['start_date'])) . "\n" .
                        "Time: " . date('h:i A', strtotime($oldScheduleData['start_time'])) . " - " . date('h:i A', strtotime($oldScheduleData['end_time'])) . "\n\n" .
                        "NEW SCHEDULE:\n" .
                        "Date: " . date('l, F d, Y', strtotime($newScheduleData['start_date'])) . "\n" .
                        "Time: " . date('h:i A', strtotime($newScheduleData['start_time'])) . " - " . date('h:i A', strtotime($newScheduleData['end_time'])) . "\n\n" .
                        "COMPLETE SCHEDULE INFORMATION:\n" .
                        "Title: {$newScheduleData['title']}\n" .
                        "Number of Participants: {$newScheduleData['participants']}\n" .
                        "Program Owner: {$newScheduleData['program_owner']}\n" .
                        "Office/Division: {$newScheduleData['office']}\n\n" .
                        "Please ensure all participants are informed of the new schedule.\n\n" .
                        "Thank you for your understanding and cooperation.\n\n" .
                        "Department of Education\n" .
                        "---\n" .
                        "This is an automated message. Please do not reply to this email.";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email send error: {$mail->ErrorInfo}");
        return false;
    }
}
