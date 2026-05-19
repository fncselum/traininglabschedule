<?php
/**
 * Email Configuration for Training Lab Schedule System
 * Configure your SMTP settings here
 */

// SMTP Configuration
define('SMTP_HOST', 'smtp.gmail.com');           // Your SMTP server (e.g., smtp.gmail.com for Gmail)
define('SMTP_PORT', 587);                         // SMTP port (587 for TLS, 465 for SSL)
define('SMTP_USERNAME', 'example@gmail.com'); // Your email address
define('SMTP_PASSWORD', 'your google app password');    // Your email password or app-specific password
define('SMTP_ENCRYPTION', 'tls');                // Encryption type: 'tls' or 'ssl'

// Sender Information Q
define('MAIL_FROM_EMAIL', 'example@gmail.com');
define('MAIL_FROM_NAME', 'Training Lab Schedule System');

// Email Settings
define('MAIL_DEBUG', 0); // 0 = off, 1 = client messages, 2 = client and server messages

/**
 * IMPORTANT SETUP INSTRUCTIONS:
 * 
 * For Gmail:
 * 1. Enable 2-Step Verification in your Google Account
 * 2. Generate an App Password: https://myaccount.google.com/apppasswords
 * 3. Use the generated 16-character password as SMTP_PASSWORD
 * 
 * For other email providers:
 * - Update SMTP_HOST, SMTP_PORT according to your provider's settings
 * - Ensure "Less secure app access" is enabled if required
 */
