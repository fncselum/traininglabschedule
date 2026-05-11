# Requirements Document

## Introduction

The Training Laboratory Schedule System is a web-based application that manages the scheduling of training laboratory sessions. The system provides a public-facing landing page displaying approved schedules, and a role-based workflow for submitting, reviewing, and approving schedule requests. The system supports three user roles with distinct permissions: Requestors who submit schedule requests, Admins who approve or reject requests and set final schedules, and Superadmins who manage user accounts and system configuration.

## Glossary

- **System**: The Training Laboratory Schedule System
- **Landing_Page**: The public-facing page displaying approved schedules
- **Schedule**: A training laboratory session with start date, title, start time, end time, participants, program owner, and office
- **Schedule_Request**: A proposed schedule submitted by a Requestor awaiting Admin review
- **Requestor**: A user role authorized to submit schedule requests
- **Admin**: A user role authorized to approve, reject, or modify schedule requests and publish final schedules
- **Superadmin**: A user role authorized to manage user accounts, assign roles, and perform administrative functions
- **Approved_Schedule**: A schedule that has been approved by an Admin and is visible on the Landing_Page
- **User_Account**: An authenticated user profile with assigned role and permissions

## Requirements

### Requirement 1: Public Schedule Display

**User Story:** As a public visitor, I want to view approved training laboratory schedules, so that I can see when training sessions are scheduled.

#### Acceptance Criteria

1. THE Landing_Page SHALL display all Approved_Schedules in a table format
2. THE Landing_Page SHALL display the start date for each Schedule
3. THE Landing_Page SHALL display the title for each Schedule
4. THE Landing_Page SHALL display the start time for each Schedule
5. THE Landing_Page SHALL display the end time for each Schedule
6. THE Landing_Page SHALL display the participants for each Schedule
7. THE Landing_Page SHALL display the program owner for each Schedule
8. THE Landing_Page SHALL display the office for each Schedule
9. THE Landing_Page SHALL be accessible without authentication

### Requirement 2: User Authentication

**User Story:** As a user, I want to authenticate with the system, so that I can access role-specific features.

#### Acceptance Criteria

1. WHEN a user provides valid credentials, THE System SHALL authenticate the user and grant access
2. WHEN a user provides invalid credentials, THE System SHALL reject authentication and display an error message
3. THE System SHALL maintain user session state after successful authentication
4. WHEN a user logs out, THE System SHALL terminate the user session

### Requirement 3: Schedule Request Submission

**User Story:** As a Requestor, I want to submit schedule requests, so that I can propose new training laboratory sessions.

#### Acceptance Criteria

1. WHERE a user has the Requestor role, THE System SHALL provide access to schedule request submission
2. WHEN a Requestor submits a schedule request, THE System SHALL require a start date
3. WHEN a Requestor submits a schedule request, THE System SHALL require a title
4. WHEN a Requestor submits a schedule request, THE System SHALL require a start time
5. WHEN a Requestor submits a schedule request, THE System SHALL require an end time
6. WHEN a Requestor submits a schedule request, THE System SHALL require participants information
7. WHEN a Requestor submits a schedule request, THE System SHALL require a program owner
8. WHEN a Requestor submits a schedule request, THE System SHALL require an office
9. WHEN a Requestor submits a complete schedule request, THE System SHALL save the Schedule_Request with pending status
10. WHEN a Requestor submits a schedule request, THE System SHALL notify Admins of the new request

### Requirement 4: Schedule Request Review

**User Story:** As an Admin, I want to review schedule requests, so that I can approve or reject proposed training sessions.

#### Acceptance Criteria

1. WHERE a user has the Admin role, THE System SHALL provide access to pending Schedule_Requests
2. THE System SHALL display all pending Schedule_Requests to Admins
3. WHEN an Admin approves a Schedule_Request, THE System SHALL convert it to an Approved_Schedule
4. WHEN an Admin approves a Schedule_Request, THE System SHALL display the schedule on the Landing_Page
5. WHEN an Admin rejects a Schedule_Request, THE System SHALL mark the request as rejected
6. WHEN an Admin rejects a Schedule_Request, THE System SHALL notify the Requestor of the rejection
7. WHERE an Admin modifies a Schedule_Request, THE System SHALL allow editing of all schedule fields before approval

### Requirement 5: User Account Management

**User Story:** As a Superadmin, I want to manage user accounts, so that I can control who has access to the system and their permissions.

#### Acceptance Criteria

1. WHERE a user has the Superadmin role, THE System SHALL provide access to user account management
2. WHEN a Superadmin creates a User_Account, THE System SHALL require a username
3. WHEN a Superadmin creates a User_Account, THE System SHALL require a password
4. WHEN a Superadmin creates a User_Account, THE System SHALL require a role assignment
5. THE System SHALL support assignment of Requestor role to User_Accounts
6. THE System SHALL support assignment of Admin role to User_Accounts
7. THE System SHALL support assignment of Superadmin role to User_Accounts
8. WHEN a Superadmin modifies a User_Account, THE System SHALL allow changing the assigned role
9. WHEN a Superadmin deactivates a User_Account, THE System SHALL prevent authentication for that account
10. WHEN a Superadmin reactivates a User_Account, THE System SHALL restore authentication for that account

### Requirement 6: Role-Based Access Control

**User Story:** As a system administrator, I want role-based access control enforced, so that users can only access features appropriate to their role.

#### Acceptance Criteria

1. WHERE a user has the Requestor role, THE System SHALL grant access to schedule request submission
2. WHERE a user has the Requestor role, THE System SHALL deny access to schedule approval functions
3. WHERE a user has the Requestor role, THE System SHALL deny access to user account management
4. WHERE a user has the Admin role, THE System SHALL grant access to schedule request review and approval
5. WHERE a user has the Admin role, THE System SHALL grant access to schedule request submission
6. WHERE a user has the Admin role, THE System SHALL deny access to user account management
7. WHERE a user has the Superadmin role, THE System SHALL grant access to all system functions
8. WHEN a user attempts to access a function not permitted by their role, THE System SHALL deny access and display an error message

### Requirement 7: Schedule Request Status Tracking

**User Story:** As a Requestor, I want to track the status of my schedule requests, so that I know whether they have been approved or rejected.

#### Acceptance Criteria

1. THE System SHALL maintain status for each Schedule_Request
2. THE System SHALL support pending status for newly submitted Schedule_Requests
3. THE System SHALL support approved status for Schedule_Requests accepted by Admins
4. THE System SHALL support rejected status for Schedule_Requests declined by Admins
5. WHERE a user has the Requestor role, THE System SHALL display all Schedule_Requests submitted by that user
6. WHERE a user has the Requestor role, THE System SHALL display the current status for each of their Schedule_Requests
7. WHEN a Schedule_Request status changes, THE System SHALL notify the Requestor who submitted it

### Requirement 8: Schedule Data Validation

**User Story:** As an Admin, I want schedule data to be validated, so that only valid schedules are displayed on the Landing_Page.

#### Acceptance Criteria

1. WHEN a schedule start time is after the end time, THE System SHALL reject the schedule and display a validation error
2. WHEN a schedule start date is in the past, THE System SHALL reject the schedule and display a validation error
3. WHEN required schedule fields are empty, THE System SHALL reject the schedule and display a validation error
4. THE System SHALL validate that start date is in a valid date format
5. THE System SHALL validate that start time is in a valid time format
6. THE System SHALL validate that end time is in a valid time format

### Requirement 9: Schedule Modification

**User Story:** As an Admin, I want to modify approved schedules, so that I can update schedule details when changes are needed.

#### Acceptance Criteria

1. WHERE a user has the Admin role, THE System SHALL provide access to modify Approved_Schedules
2. WHEN an Admin modifies an Approved_Schedule, THE System SHALL allow editing of all schedule fields
3. WHEN an Admin saves modifications to an Approved_Schedule, THE System SHALL update the schedule on the Landing_Page
4. WHEN an Admin modifies an Approved_Schedule, THE System SHALL validate the modified data before saving

### Requirement 10: Schedule Deletion

**User Story:** As an Admin, I want to delete schedules, so that I can remove cancelled or incorrect training sessions.

#### Acceptance Criteria

1. WHERE a user has the Admin role, THE System SHALL provide the ability to delete Approved_Schedules
2. WHEN an Admin deletes an Approved_Schedule, THE System SHALL remove it from the Landing_Page
3. WHEN an Admin deletes an Approved_Schedule, THE System SHALL require confirmation before deletion
4. WHERE a user has the Admin role, THE System SHALL provide the ability to delete pending Schedule_Requests
