<?php
/**
 * Email Test Script
 * Run this to test if email configuration is working
 */

require_once 'config/email_helper.php';

echo "<h2>Testing Email Configuration</h2>";
echo "<pre>";

// Test email data
$testEmail = 'navaresmarcel04@gmail.com'; // Change this to your test email
$testData = [
    'title' => 'Test Schedule',
    'start_date' => date('Y-m-d'),
    'start_time' => '09:00:00',
    'end_time' => '10:00:00',
    'participants' => '25',
    'program_owner' => 'Test Owner',
    'office' => 'Test Office'
];

echo "Attempting to send test email to: $testEmail\n";
echo "SMTP Host: " . SMTP_HOST . "\n";
echo "SMTP Port: " . SMTP_PORT . "\n";
echo "SMTP User: " . SMTP_USERNAME . "\n";
echo "From Email: " . MAIL_FROM_EMAIL . "\n\n";

echo "Sending email...\n\n";

$result = sendScheduleAddedEmail($testEmail, $testData);

if ($result) {
    echo "\n✅ SUCCESS! Email sent successfully.\n";
    echo "Check your inbox at: $testEmail\n";
} else {
    echo "\n❌ FAILED! Email was not sent.\n";
    echo "Check the error messages above.\n";
    echo "\nCommon issues:\n";
    echo "1. Gmail App Password not generated or incorrect\n";
    echo "2. 2-Step Verification not enabled on Gmail account\n";
    echo "3. Firewall blocking SMTP port 587\n";
    echo "4. Internet connection issue\n";
}

echo "</pre>";
?>
