# Training Laboratory Schedule System

A web-based training laboratory schedule management system with role-based access control.

## Features

- **Public Landing Page**: View approved training schedules without authentication
- **Fully Responsive Design**: Automatically adapts to any device (mobile, tablet, desktop)
- **Modern Green Theme**: Professional, clean interface with smooth animations
- **Role-Based Access Control**:
  - **Requestor**: Submit schedule requests and track their status
  - **Admin**: Review, approve, reject, and manage schedule requests
  - **Superadmin**: Full system access including user account management
- **Schedule Management**: Create, edit, and delete training schedules
- **Notifications**: Automatic notifications for request status changes
- **Data Validation**: Comprehensive validation for dates, times, and required fields
- **Touch-Friendly Interface**: Optimized for mobile devices with proper touch targets
- **Accessibility**: WCAG compliant with keyboard navigation and screen reader support

## Technology Stack

- **Frontend**: HTML, CSS, JavaScript
- **Backend**: PHP
- **Database**: MySQL (via XAMPP)
- **Server**: Apache (XAMPP)

## Installation

### Prerequisites

- XAMPP installed on your system
- Web browser

### Setup Instructions

1. **Start XAMPP Services**
   - Open XAMPP Control Panel
   - Start Apache and MySQL services

2. **Create Database**
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Click on "SQL" tab
   - Copy and paste the contents of `database/setup.sql`
   - Click "Go" to execute the SQL script
   - This will create the `traininglab` database with all necessary tables and sample users

3. **Access the Application**
   - Open your web browser
   - Navigate to: `http://localhost/TraininglabSchedule/`

## Default User Accounts

After running the database setup script, you'll have these default accounts:

| Username    | Password   | Role        |
|-------------|------------|-------------|
| superadmin  | deped1234  | Superadmin  |
| admin       | deped1234  | Admin       |
| requestor   | deped1234  | Requestor   |

**Important**: Change these default passwords after first login!

## Directory Structure

```
TraininglabSchedule/
├── admin/                  # Admin dashboard and features
├── assets/
│   └── css/               # Stylesheets
├── config/                # Configuration files
│   ├── database.php       # Database connection
│   └── session.php        # Session management
├── database/              # Database setup scripts
│   └── setup.sql          # Database schema and initial data
├── requestor/             # Requestor dashboard and features
├── superadmin/            # Superadmin user management
├── index.php              # Public landing page
├── login.php              # Login page
├── logout.php             # Logout handler
└── unauthorized.php       # Access denied page
```

## User Roles and Permissions

### Requestor
- Submit schedule requests
- View own request history
- Track request status (pending, approved, rejected)
- Receive notifications on request status changes

### Admin
- View all pending schedule requests
- Approve or reject requests with reasons
- Edit request details before approval
- Manage approved schedules (edit, delete)
- All Requestor permissions

### Superadmin
- Create, edit, and delete user accounts
- Assign and change user roles
- Activate/deactivate user accounts
- All Admin permissions

## Database Schema

### Tables

1. **users**: User accounts and authentication
2. **schedule_requests**: Submitted schedule requests
3. **approved_schedules**: Approved and published schedules
4. **notifications**: User notifications

## Usage Guide

### For Requestors

1. Login with your credentials
2. Click "Submit New Request"
3. Fill in all required fields:
   - Start Date
   - Training Title
   - Start Time and End Time
   - Participants
   - Program Owner
   - Office
4. Submit the request
5. Track status in "My Requests"

### For Admins

1. Login with admin credentials
2. View pending requests on the dashboard
3. Click "Review" on any pending request
4. Edit details if needed
5. Approve or reject with a reason
6. Manage approved schedules from "Manage Schedules"

### For Superadmins

1. Login with superadmin credentials
2. Access "Manage Users" from the navigation
3. Create new users with assigned roles
4. Edit existing users (change role, reset password)
5. Activate/deactivate user accounts
6. Delete users if necessary

## Security Features

- Password hashing using PHP's `password_hash()`
- Session-based authentication
- Role-based access control
- SQL injection prevention using prepared statements
- XSS protection with `htmlspecialchars()`
- Input validation on both client and server side

## Responsive Design

The system is **fully responsive** and automatically adapts to any device:

- 📱 **Mobile phones** (320px and up) - Card-based layouts, hamburger menu
- 📱 **Tablets** (768px and up) - Hybrid layouts, touch-optimized
- 💻 **Laptops** (1024px and up) - Full features, hover effects
- 🖥️ **Desktop monitors** (1200px and up) - Complete interface

### Key Responsive Features
- ✅ Automatic device detection and adaptation
- ✅ Touch-friendly interface (44x44px minimum touch targets)
- ✅ Mobile-optimized tables (automatic card conversion)
- ✅ Hamburger menu with slide-out navigation
- ✅ Adaptive typography and spacing
- ✅ Smooth animations and transitions
- ✅ Network status monitoring (online/offline)
- ✅ Back-to-top button on long pages
- ✅ WCAG 2.1 AA accessibility compliant
- ✅ Print-friendly layouts

### Testing the Responsive Design
1. Open `responsive-test.html` in your browser
2. Press `F12` to open DevTools
3. Press `Ctrl+Shift+M` to toggle device toolbar
4. Test different devices from the dropdown

### Documentation
- **[RESPONSIVE_SUMMARY.md](RESPONSIVE_SUMMARY.md)** - Quick overview and what's included
- **[RESPONSIVE_IMPLEMENTATION.md](RESPONSIVE_IMPLEMENTATION.md)** - Complete technical guide
- **[RESPONSIVE_QUICK_START.md](RESPONSIVE_QUICK_START.md)** - Quick reference for developers
- **[RESPONSIVE_FEATURES.md](RESPONSIVE_FEATURES.md)** - Detailed feature list

### Transfer to Another Device
**No configuration needed!** Simply transfer all files to the new device and the system will automatically adapt to:
- Different screen sizes
- Touch vs mouse input
- Network conditions
- Device capabilities

The responsive system works out of the box on any device! 🚀

## Troubleshooting

### Cannot connect to database
- Ensure MySQL service is running in XAMPP
- Check database credentials in `config/database.php`
- Verify database name is `traininglab`

### Login not working
- Clear browser cookies and cache
- Verify user exists in database
- Check if user status is 'active'

### Pages not loading
- Ensure Apache service is running in XAMPP
- Check file permissions
- Verify correct URL path

## Future Enhancements

- Email notifications
- Calendar view for schedules
- Export schedules to PDF/Excel
- Advanced search and filtering
- Schedule conflict detection
- Mobile responsive improvements
- Password reset functionality
- Activity logs and audit trail

## Support

For issues or questions, please contact your system administrator.

## License

This project is developed for internal use.
