<?php
// File: helpers.php

/**
 * Checks if an activity belongs to the user's barangay
 * 
 * @param int|null $activityBarangayId The barangay ID of the activity
 * @param int|null $userBarangayId The user's barangay ID
 * @return bool True if the activity belongs to the user's barangay
 */
function isUserBarangayActivity($activityBarangayId, $userBarangayId) {
    if (!$userBarangayId) {
        return false;
    }
    return $activityBarangayId == $userBarangayId;
}

/**
 * Determines the activity status based on start and end dates
 * 
 * @param string $startDate The activity start date
 * @param string $endDate The activity end date
 * @return array An array with status, class, and label
 */
if (!function_exists('getActivityStatus')) {
    function getActivityStatus($startDate, $endDate) {
        $today = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime($startDate));
        $endDate = date('Y-m-d', strtotime($endDate));
        
        if ($today < $startDate) {
            return ['status' => 'upcoming', 'class' => 'info', 'label' => 'Upcoming'];
        } elseif ($today > $endDate) {
            return ['status' => 'completed', 'class' => 'secondary', 'label' => 'Completed'];
        } else {
            return ['status' => 'active', 'class' => 'success', 'label' => 'Active'];
        }
    }
}

/**
 * Formats a date in a standardized way
 * 
 * @param string $date The date to format
 * @return string The formatted date
 */
function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

/**
 * Formats activity type for display
 * 
 * @param string $type The activity type
 * @return array An array with name, class, and icon
 */
if (!function_exists('formatActivityType')) {
    function formatActivityType($type) {
        switch ($type) {
            case 'health_check':
                return ['name' => 'Health Check', 'class' => 'success', 'icon' => 'heart-pulse'];
            case 'education':
                return ['name' => 'Education', 'class' => 'info', 'icon' => 'book'];
            case 'family_development_session':
                return ['name' => 'Family Development Session', 'class' => 'warning', 'icon' => 'people'];
            case 'community_meeting':
                return ['name' => 'Community Meeting', 'class' => 'primary', 'icon' => 'chat-square-text'];
            case 'other':
                return ['name' => 'Other', 'class' => 'secondary', 'icon' => 'grid'];
            default:
                return ['name' => ucfirst(str_replace('_', ' ', $type)), 'class' => 'secondary', 'icon' => 'grid'];
        }
    }
}