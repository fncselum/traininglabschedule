# Email Notification Setup Guide

## Overview
The Training Lab Schedule System now sends automated email notifications for:
- ✅ **Schedule Added** - When admin adds a walk-in schedule
- ⚠️ **Schedule Pulled Out** - When admin cancels a schedule
- 📅 **Schedule Rescheduled** - When admin changes schedule date/time

## Files Created

### 1. `config/email_config.php`
Contains SMTP configuration settings:
- SMTP host, port, username, password
- Sender email and name
- Debug settings

### 2. `config/email_helper.php`
Contains email sending functions:
- `sendScheduleAddedEmail()` - Sends confirmation email
- `sendSchedulePulloutEmail()` - Sends cancellation email
- `sendScheduleRescheduleEmail()` - Sends reschedule notification

## Setup Instructions

### Step 1: Configure Email Settings

Edit `config/email_config.php` and update these values:

```php
define('SMTP_HOST', 'smtp.gmail.com');           // Your SMTP server
define('SMTP_PORT', 587);                         // SMTP port (587 for TLS)
define('SMTP_USERNAME', 'your-email@gmail.com'); // Your email
define('SMTP_PASSWORD', 'your-app-password');    // App password
define('SMTP_ENCRYPTION', 'tls');                // 'tls' or 'ssl'
```

### Step 2: Gmail Setup (if using Gmail)

1. **Enable 2-Step Verification**
   - Go to: https://myaccount.google.com/security
   - Enable 2-Step Verification

2. **Generate App Password**
   - Go to: https://myaccount.google.com/apppasswords
   - Select "Mail" and "Windows Computer"
   - Copy the 16-character password
   - Use this as `SMTP_PASSWORD` in config

3. **Update Config**
   ```php
   define('SMTP_HOST', 'smtp.gmail.com');
   define('SMTP_PORT', 587);
   define('SMTP_USERNAME', 'youremail@gmail.com');
   define('SMTP_PASSWORD', 'xxxx xxxx xxxx xxxx'); // 16-char app password
   define('SMTP_ENCRYPTION', 'tls');
   ```

### Step 3: Other Email Providers

#### Microsoft Outlook/Office 365
```php
define('SMTP_HOST', 'smtp.office365.com');
define('SMTP_PORT', 587);
define('SMTP_ENCRYPTION', 'tls');
```

#### Yahoo Mail
```php
define('SMTP_HOST', 'smtp.mail.yahoo.com');
define('SMTP_PORT', 587);
define('SMTP_ENCRYPTION', 'tls');
```

#### Custom SMTP Server
```php
define('SMTP_HOST', 'mail.yourdomain.com');
define('SMTP_PORT', 587); // or 465 for SSL
define('SMTP_ENCRYPTION', 'tls'); // or 'ssl'
```

## Database Changes

The `approved_schedules` table now uses `deped_email` column to store recipient email addresses.

### Migration Command
Run this SQL to update existing databases:
```sql
ALTER TABLE approved_schedules CHANGE COLUMN requestor_email deped_email VARCHAR(100) DEFAULT NULL;
```

## Email Templates

### 1. Schedule Added Email
- **Subject:** Schedule Confirmed - Training Laboratory
- **Content:** Confirmation with all schedule details
- **Trigger:** When admin adds walk-in schedule

### 2. Schedule Pulled Out Email
- **Subject:** Schedule Cancelled - Training Laboratory
- **Content:** Cancellation notice with reason (if provided)
- **Trigger:** When admin pulls out a schedule

### 3. Schedule Rescheduled Email
- **Subject:** Schedule Rescheduled - Training Laboratory
- **Content:** Shows old vs new schedule with reason
- **Trigger:** When admin reschedules a booking

## Testing

### Test Email Configuration
1. Add a walk-in schedule with your email address
2. Check if you receive the confirmation email
3. Check spam/junk folder if not received

### Debug Mode
Enable debug mode in `config/email_config.php`:
```php
define('MAIL_DEBUG', 2); // 0=off, 1=client, 2=client+server
```

### Common Issues

**Problem:** Emails not sending
- Check SMTP credentials are correct
- Verify port and encryption settings
- Check if firewall blocks SMTP port
- Enable "Less secure app access" (if required by provider)

**Problem:** Emails go to spam
- Use a verified sender email
- Add SPF/DKIM records to your domain
- Ask recipients to whitelist your email

**Problem:** Gmail "Less secure app" error
- Use App Password instead of regular password
- Enable 2-Step Verification first

## Files Modified

1. ✅ `admin/add_schedule.php` - Added email notification on schedule add
2. ✅ `admin/pullout_schedule.php` - Added email notification on pullout
3. ✅ `admin/reschedule.php` - Added email notification on reschedule
4. ✅ `database/setup.sql` - Updated to use `deped_email` column

## Security Notes

- Never commit `email_config.php` with real credentials to version control
- Use environment variables for production
- Keep PHPMailer library updated
- Use TLS encryption for secure email transmission

## Support

For issues or questions:
1. Check error logs in PHP error log
2. Enable debug mode to see SMTP communication
3. Verify email credentials and server settings
4. Test with a simple email client first
