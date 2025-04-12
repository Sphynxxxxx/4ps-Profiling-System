<?php
// File: helpers.php

function isUserBarangayActivity($activityBarangayId, $userBarangayId) {
    if (!$userBarangayId) {
        return false;
    }
    return $activityBarangayId == $userBarangayId;
}

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

function formatDate($date) {
    return date('M d, Y', strtotime($date));
}