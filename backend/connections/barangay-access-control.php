<?php
function getUserRole($db, $userId) {
    $userSql = "SELECT role FROM users WHERE user_id = ?";
    $user = $db->fetchOne($userSql, [$userId]);
    return $user['role'] ?? 'resident';
}

function checkBarangayAccess($db, $userId, $requestedBarangayId = null) {
    $userBarangayId = getUserBarangay($db, $userId);
    $userRole = getUserRole($db, $userId);

    // Store barangay in session for consistent access
    $_SESSION['user_barangay_id'] = $userBarangayId;
    $_SESSION['user_role'] = $userRole;

    // Admin and staff can access all barangays
    if ($userRole == 'admin' || $userRole == 'staff') {
        return $userBarangayId;
    }

    // If no specific barangay requested, use user's barangay
    if ($requestedBarangayId === null) {
        return $userBarangayId;
    }

    // Regular users can only access their own barangay
    if ($userBarangayId != $requestedBarangayId) {
        // Log unauthorized access attempt
        error_log("Unauthorized barangay access attempt by user $userId for barangay $requestedBarangayId");
        
        // Redirect to user's own barangay dashboard
        header("Location: dashboard.php?barangay_id=$userBarangayId");
        exit();
    }

    return $userBarangayId;
}

// Function to generate barangay-aware navigation links
function generateNavLink($href, $icon, $label) {
    $barangayId = $_SESSION['user_barangay_id'] ?? '';
    $separator = strpos($href, '?') === false ? '?' : '&';
    $navLink = $href . $separator . "barangay_id=" . $barangayId;
    
    return sprintf(
        '<li class="nav-item">
            <a class="nav-link" href="%s">
                <i class="%s"></i> %s
            </a>
        </li>',
        htmlspecialchars($navLink),
        htmlspecialchars($icon),
        htmlspecialchars($label)
    );
}

// Usage in sidebar or navigation
function renderSidebar() {
    $navLinks = [
        ['dashboard.php', 'bi bi-speedometer2', 'Dashboard'],
        ['profile.php', 'bi bi-person', 'Profile'],
        ['activities.php', 'bi bi-calendar2-check', 'Activities'],
        ['members.php', 'bi bi-people', 'Members'],
        ['messages.php', 'bi bi-chat-left-text', 'Messages'],
        ['reports.php', 'bi bi-file-earmark-text', 'Reports'],
        ['settings.php', 'bi bi-gear', 'Settings']
    ];

    echo '<div class="sidebar" id="sidebar">
            <ul class="nav flex-column">';
    
    foreach ($navLinks as $link) {
        echo generateNavLink($link[0], $link[1], $link[2]);
    }
    
    echo '</ul>
          </div>';
}

// At the top of each page that requires barangay access control
function initializePageAccess($db) {
    // Ensure user is logged in
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }

    // Get barangay ID from request or use default
    $requestedBarangayId = $_GET['barangay_id'] ?? null;
    
    // Check and potentially modify barangay access
    checkBarangayAccess($db, $_SESSION['user_id'], $requestedBarangayId);
}
?>