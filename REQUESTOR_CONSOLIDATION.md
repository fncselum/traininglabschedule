# Requestor Portal Consolidation

## Overview
The requestor portal has been streamlined and consolidated into a single entry point (`requestor/index.php`). This simplifies navigation and improves user experience.

## Changes Made

### 1. **Consolidated Files**
- **Removed**: `requestor/dashboard.php` and `requestor/submit_request.php`
- **Created**: `requestor/index.php` - A unified dashboard combining all functionality

### 2. **Single Entry Point**
When a requestor logs in, they are now automatically redirected to `requestor/` (which serves `requestor/index.php`) instead of `requestor/dashboard.php`.

**Updated Files:**
- `login.php` - Changed redirect from `requestor/dashboard.php` to `requestor/`
- `index.php` - Updated dashboard link for requestors to point to `requestor/`

### 3. **Unified Dashboard Features**
The new `requestor/index.php` includes:

#### Tab 1: My Requests
- View all submitted schedule requests
- See request status (pending, approved, rejected)
- View submission date, start date, time, and actions
- Quick link to view full request details

#### Tab 2: New Request
- Submit new schedule requests directly from the dashboard
- All form fields in one place:
  - Start Date
  - DepEd Email (with validation)
  - Training Title
  - Start & End Time
  - Participants
  - Program Owner
  - Office
  - Remarks

### 4. **Email Notification System**
When a schedule request is submitted, the requestor receives email notifications:

#### Auto-Approved Requests
- **Trigger**: No time conflicts detected
- **Email Subject**: "Schedule Request Approved - Training Laboratory"
- **Email Content**:
  - Confirmation of approval
  - Training date and time
  - Notification that no conflicts were detected
  - System signature

#### Pending Requests (Conflict Detected)
- **Trigger**: Time conflict with existing approved schedule
- **In-App Notification**: Sent to all admins and superadmins
- **Status**: Request marked as "pending" for admin review

### 5. **Remaining Files**
The following files remain in the `requestor/` folder:
- `index.php` - Main dashboard (NEW - consolidated)
- `profile.php` - User profile and password management
- `view_request.php` - View individual request details
- `view_schedule.php` - View approved schedules
- `mark_notification_read.php` - Mark notifications as read

## User Flow

### For Requestors:
1. **Login** → Automatically redirected to `requestor/`
2. **Dashboard** → View all requests and notifications
3. **Submit Request** → Switch to "New Request" tab and fill form
4. **Receive Email** → Confirmation email sent to DepEd email address
5. **Track Status** → View request status in "My Requests" tab
6. **View Details** → Click "View" to see full request information

### For Admins:
- Receive in-app notifications for pending requests (conflicts detected)
- Can approve/reject requests from admin dashboard
- Requestors receive email notifications of approval/rejection

## Email Configuration
The system uses PHP's `mail()` function. Ensure your server has mail configured:
- **From Address**: `noreply@traininglabschedule.local`
- **Email Sent To**: Requestor's DepEd email address

## Benefits
✅ **Simplified Navigation** - Single entry point for requestors
✅ **Improved UX** - All features accessible from one page
✅ **Email Notifications** - Requestors stay informed via email
✅ **Reduced Clutter** - Fewer files to maintain
✅ **Better Organization** - Clear tab-based interface

## Technical Details

### Database Queries
- Fetches user's schedule requests
- Checks for time conflicts before auto-approval
- Manages notifications
- Tracks request status

### Validation
- DepEd email format validation (@deped.gov.ph)
- Date cannot be in the past
- End time must be after start time
- All required fields must be filled

### Auto-Approval Logic
- If no time conflicts: Request auto-approved and added to approved_schedules
- If conflicts exist: Request marked as pending for admin review
- Email sent to requestor in both cases

## Notes
- The consolidated `index.php` maintains all original functionality
- Notification system works with existing database schema
- Email notifications are sent immediately upon request submission
- Requestors can still access their profile and view schedules separately
