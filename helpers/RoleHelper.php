<?php
/**
 * Role & Permission Helper
 * Handles role-based access control and permission checking
 */

class RoleHelper {

    /**
     * Check if user is authenticated
     */
    public static function isAuthenticated() {
        return isset($_SESSION['admin_id']) && isset($_SESSION['role']);
    }

    /**
     * Check if user is admin
     */
    public static function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }

    /**
     * Check if user is teacher
     */
    public static function isTeacher() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'teacher';
    }

    /**
     * Get current user role
     */
    public static function getRole() {
        return $_SESSION['role'] ?? null;
    }

    /**
     * Get current user ID
     */
    public static function getUserId() {
        return $_SESSION['admin_id'] ?? null;
    }

    /**
     * Get current user email
     */
    public static function getUserEmail() {
        return $_SESSION['admin_email'] ?? null;
    }

    /**
     * Get assigned section for teacher (if applicable)
     */
    public static function getTeacherSection() {
        return $_SESSION['teacher_section'] ?? null;
    }

    /**
     * Require admin access - redirect if not admin
     */
    public static function requireAdmin() {
        if (!self::isAdmin()) {
            header('Location: ../index.php?error=Unauthorized access');
            exit;
        }
    }

    /**
     * Require teacher access - redirect if not teacher
     */
    public static function requireTeacher() {
        if (!self::isTeacher()) {
            header('Location: ../index.php?error=Unauthorized access');
            exit;
        }
    }

    /**
     * Require any authentication - redirect if not logged in
     */
    public static function requireAuth() {
        if (!self::isAuthenticated()) {
            header('Location: ../index.php?error=Session expired');
            exit;
        }
    }
}
?>
