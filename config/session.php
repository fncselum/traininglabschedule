<?php
// Session management
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

// Check user role
function hasRole($role) {
    return isLoggedIn() && $_SESSION['role'] === $role;
}

// Check if user has any of the specified roles
function hasAnyRole($roles) {
    if (!isLoggedIn()) {
        return false;
    }
    return in_array($_SESSION['role'], $roles);
}

// Require login
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /TraininglabSchedule/login.php');
        exit();
    }
}

// Require specific role
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        header('Location: /TraininglabSchedule/unauthorized.php');
        exit();
    }
}

// Require any of the specified roles
function requireAnyRole($roles) {
    requireLogin();
    if (!hasAnyRole($roles)) {
        header('Location: /TraininglabSchedule/unauthorized.php');
        exit();
    }
}

// Logout user
function logout() {
    session_unset();
    session_destroy();
    header('Location: /TraininglabSchedule/login.php');
    exit();
}
