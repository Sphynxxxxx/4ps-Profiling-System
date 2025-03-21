<?php
// Initialize database connection
require_once "backend/connections/config.php";
require_once "backend/connections/database.php";

try {
    $db = new Database();
    
    // Fetch statistics from database
    $totalBeneficiariesQuery = "SELECT COUNT(*) as total FROM beneficiaries";
    $result = $db->fetchOne($totalBeneficiariesQuery);
    $total_beneficiaries = $result ? $result['total'] : 0;
    
    $pendingVerificationsQuery = "SELECT COUNT(*) as pending FROM verification_requests WHERE status = 'pending'";
    $result = $db->fetchOne($pendingVerificationsQuery);
    $pending_verifications = $result ? $result['pending'] : 0;
    
    $upcomingEventsQuery = "SELECT COUNT(*) as upcoming FROM events WHERE event_date >= CURDATE()";
    $result = $db->fetchOne($upcomingEventsQuery);
    $upcoming_events = $result ? $result['upcoming'] : 0;
    
    $unreadMessagesQuery = "SELECT COUNT(*) as unread FROM messages WHERE status = 'unread'";
    $result = $db->fetchOne($unreadMessagesQuery);
    $unread_messages = $result ? $result['unread'] : 0;
    
    // Get active and inactive beneficiaries
    $activeBeneficiariesQuery = "SELECT COUNT(*) as active FROM beneficiaries WHERE status = 'active'";
    $result = $db->fetchOne($activeBeneficiariesQuery);
    $active_beneficiaries = $result ? $result['active'] : 0;
    
    $inactiveBeneficiariesQuery = "SELECT COUNT(*) as inactive FROM beneficiaries WHERE status = 'inactive'";
    $result = $db->fetchOne($inactiveBeneficiariesQuery);
    $inactive_beneficiaries = $result ? $result['inactive'] : 0;
    
    // Get compliance rates
    $healthComplianceQuery = "SELECT ROUND(AVG(health_compliance) * 100, 0) as rate FROM compliance_records";
    $result = $db->fetchOne($healthComplianceQuery);
    $health_compliance = $result ? $result['rate'] : 0;
    
    $educationComplianceQuery = "SELECT ROUND(AVG(education_compliance) * 100, 0) as rate FROM compliance_records";
    $result = $db->fetchOne($educationComplianceQuery);
    $education_compliance = $result ? $result['rate'] : 0;
    
    $fdsComplianceQuery = "SELECT ROUND(AVG(fds_compliance) * 100, 0) as rate FROM compliance_records";
    $result = $db->fetchOne($fdsComplianceQuery);
    $fds_compliance = $result ? $result['rate'] : 0;
    
    $overallComplianceQuery = "SELECT ROUND(AVG((health_compliance + education_compliance + fds_compliance) / 3) * 100, 0) as rate FROM compliance_records";
    $result = $db->fetchOne($overallComplianceQuery);
    $overall_compliance = $result ? $result['rate'] : 0;
    
    // Get barangay information
    $barangaysQuery = "SELECT b.barangay_id, b.name, b.captain_name, COUNT(ben.beneficiary_id) as total_beneficiaries, 
                      b.image_path FROM barangays b 
                      LEFT JOIN beneficiaries ben ON b.barangay_id = ben.barangay_id 
                      GROUP BY b.barangay_id LIMIT 3";
    $barangays = $db->fetchAll($barangaysQuery);
    
    // Get system statistics
    $systemVersionQuery = "SELECT value FROM system_settings WHERE setting_name = 'version'";
    $result = $db->fetchOne($systemVersionQuery);
    $system_version = $result ? $result['value'] : 'v1.0.0';
    
    $databaseSizeQuery = "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024 / 1024, 1) as size 
                         FROM information_schema.TABLES WHERE table_schema = '" . DB_NAME . "'";
    $result = $db->fetchOne($databaseSizeQuery);
    $database_size = $result ? $result['size'] : 0;
    
    $lastBackupQuery = "SELECT value FROM system_settings WHERE setting_name = 'last_backup'";
    $result = $db->fetchOne($lastBackupQuery);
    $last_backup = $result ? $result['value'] : 'Never';
    
    // Get recent activities
    $activitiesQuery = "SELECT a.activity_type, a.description, a.created_at, u.full_name 
                      FROM activity_logs a 
                      LEFT JOIN users u ON a.user_id = u.user_id 
                      ORDER BY a.created_at DESC LIMIT 7";
    $activities = $db->fetchAll($activitiesQuery);
    
    // Close database connection
    $db->closeConnection();
    
} catch (Exception $e) {
    // Handle database errors
    error_log("Admin Dashboard Error: " . $e->getMessage());
    
    // Set default values in case of errors
    $total_beneficiaries = 0;
    $pending_verifications = 0;
    $upcoming_events = 0;
    $unread_messages = 0;
    $active_beneficiaries = 0;
    $inactive_beneficiaries = 0;
    $health_compliance = 0;
    $education_compliance = 0;
    $fds_compliance = 0;
    $overall_compliance = 0;
    $system_version = 'v1.0.0';
    $database_size = 0;
    $last_backup = 'Never';
    $barangays = [];
    $activities = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - 4P's Profiling System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="Admin/css/admin.css" rel="stylesheet">
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="menu-toggle" id="menuToggle">
        <i class="bi bi-list"></i>
    </button>
    
    <!-- Header -->
    <header class="page-header">
        <div class="logo-container">
            <img src="User/assets/pngwing.com (7).png" alt="DSWD Logo">
            <h1>4P's Profiling System ADMIN DASHBOARD</h1>
        </div>
    </header>
    
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="admin.php">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="participant_verification.php">
                    <i class="bi bi-person-check"></i> Participant Verification
                    <?php if($pending_verifications > 0): ?>
                    <span class="badge bg-danger rounded-pill ms-auto"><?php echo $pending_verifications; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="beneficiaries.php">
                    <i class="bi bi-people"></i> Beneficiaries
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="calendar.php">
                    <i class="bi bi-calendar3"></i> Calendar
                    <?php if($upcoming_events > 0): ?>
                    <span class="badge bg-primary rounded-pill ms-auto"><?php echo $upcoming_events; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="messages.php">
                    <i class="bi bi-chat-left-text"></i> Messages
                    <?php if($unread_messages > 0): ?>
                    <span class="badge bg-danger rounded-pill ms-auto"><?php echo $unread_messages; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="reports.php">
                    <i class="bi bi-file-earmark-text"></i> Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="settings.php">
                    <i class="bi bi-gear"></i> System Settings
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </li>
        </ul>
    </div>
    
    
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Welcome Message -->
        <div class="alert alert-primary mb-4" role="alert">
            <h4 class="alert-heading"><i class="bi bi-info-circle me-2"></i>System Summary</h4>
            <p>You have <strong><?php echo $pending_verifications; ?></strong> pending verifications, <strong><?php echo $upcoming_events; ?></strong> upcoming events, and <strong><?php echo $unread_messages; ?></strong> unread messages.</p>
            <hr>
            <p class="mb-0">Today is <?php echo date("l, F j, Y"); ?></p>
        </div>
        
        <!-- Statistics -->
        <div class="stats-container">
            <div class="row">
                <div class="col-md-3 col-6 mb-4">
                    <div class="stat-card">
                        <div class="icon">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <h3><?php echo number_format($total_beneficiaries); ?></h3>
                        <p>Total Beneficiaries</p>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-4">
                    <div class="stat-card">
                        <div class="icon">
                            <i class="bi bi-clipboard-check"></i>
                        </div>
                        <h3><?php echo $pending_verifications; ?></h3>
                        <p>Pending Verifications</p>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-4">
                    <div class="stat-card">
                        <div class="icon">
                            <i class="bi bi-calendar-event"></i>
                        </div>
                        <h3><?php echo $upcoming_events; ?></h3>
                        <p>Upcoming Events</p>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-4">
                    <div class="stat-card">
                        <div class="icon">
                            <i class="bi bi-envelope"></i>
                        </div>
                        <h3><?php echo $unread_messages; ?></h3>
                        <p>Unread Messages</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Access -->
        <div class="quick-access mb-5">
            <div class="row">
                <div class="col-md-3 col-6">
                    <a href="add_beneficiary.php" class="quick-btn">
                        <i class="bi bi-person-plus"></i> Add Beneficiary
                    </a>
                </div>
                <div class="col-md-3 col-6">
                    <a href="generate_report.php" class="quick-btn">
                        <i class="bi bi-file-earmark-text"></i> Generate Reports
                    </a>
                </div>
                <div class="col-md-3 col-6">
                    <a href="add_event.php" class="quick-btn">
                        <i class="bi bi-calendar-plus"></i> Schedule Event
                    </a>
                </div>
                <div class="col-md-3 col-6">
                    <a href="system_backup.php" class="quick-btn">
                        <i class="bi bi-cloud-arrow-up"></i> Backup System
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Barangay Cards -->
        <h2 class="mb-4 text-center text-primary">Manage Barangay Beneficiaries</h2>
        <div class="row">
            <?php if(empty($barangays)): ?>
                <div class="col-12 text-center">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i> No barangay data available. Please add barangays to the system.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach($barangays as $barangay): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="feature-card">
                            <div class="card-img-container">
                                <img src="<?php echo !empty($barangay['image_path']) ? $barangay['image_path'] : 'assets/images/barangay-default.jpg'; ?>" alt="<?php echo htmlspecialchars($barangay['name']); ?>">
                            </div>
                            <div class="card-content">
                                <h3><?php echo htmlspecialchars(strtoupper($barangay['name'])); ?></h3>
                                <p class="caption"><?php echo htmlspecialchars($barangay['captain_name']); ?></p>
                                <p>Total beneficiaries: <?php echo number_format($barangay['total_beneficiaries']); ?></p>
                                <div class="d-grid gap-2">
                                    <a href="barangay_details.php?id=<?php echo $barangay['barangay_id']; ?>" class="btn btn-primary">Manage Beneficiaries</a>
                                    <a href="barangay_reports.php?id=<?php echo $barangay['barangay_id']; ?>" class="btn btn-outline-secondary">View Reports</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="row mt-4">
            <!-- Recent Activities -->
            <div class="col-lg-6 mb-4">
                <div class="activities-container">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2><i class="bi bi-activity me-2"></i>Recent Activities</h2>
                        <a href="activity_logs.php" class="btn btn-outline-primary btn-sm">View All</a>
                    </div>
                    <div class="activity-list">
                        <?php if(empty($activities)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-exclamation-circle fs-1 text-muted"></i>
                                <p class="mt-2 text-muted">No recent activities found.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach($activities as $activity): ?>
                                <div class="activity-item">
                                    <div class="time"><?php echo date('F j, Y, g:i a', strtotime($activity['created_at'])); ?></div>
                                    <div class="text"><strong><?php echo htmlspecialchars($activity['full_name'] ?? 'System'); ?></strong> <?php echo htmlspecialchars($activity['description']); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- System Summary -->
            <div class="col-lg-6 mb-4">
                <div class="dashboard-card">
                    <div class="card-header bg-primary text-white">
                        <h3><i class="bi bi-info-circle me-2"></i>System Overview</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title"><i class="bi bi-people me-2"></i>Beneficiary Statistics</h5>
                                        <ul class="list-group list-group list-group-flush">
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                Total Registered
                                                <span class="badge bg-primary rounded-pill"><?php echo number_format($total_beneficiaries); ?></span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                Active Beneficiaries
                                                <span class="badge bg-success rounded-pill"><?php echo number_format($active_beneficiaries); ?></span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                Pending Approval
                                                <span class="badge bg-warning rounded-pill"><?php echo number_format($pending_verifications); ?></span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                Inactive
                                                <span class="badge bg-danger rounded-pill"><?php echo number_format($inactive_beneficiaries); ?></span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title"><i class="bi bi-calendar-check me-2"></i>Program Compliance</h5>
                                        <ul class="list-group list-group-flush">
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                Health Check Compliance
                                                <span class="badge bg-<?php echo $health_compliance >= 90 ? 'success' : ($health_compliance >= 75 ? 'primary' : ($health_compliance >= 60 ? 'warning' : 'danger')); ?> rounded-pill"><?php echo $health_compliance; ?>%</span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                Education Attendance
                                                <span class="badge bg-<?php echo $education_compliance >= 90 ? 'success' : ($education_compliance >= 75 ? 'primary' : ($education_compliance >= 60 ? 'warning' : 'danger')); ?> rounded-pill"><?php echo $education_compliance; ?>%</span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                FDS Attendance
                                                <span class="badge bg-<?php echo $fds_compliance >= 90 ? 'success' : ($fds_compliance >= 75 ? 'primary' : ($fds_compliance >= 60 ? 'warning' : 'danger')); ?> rounded-pill"><?php echo $fds_compliance; ?>%</span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                Overall Compliance
                                                <span class="badge bg-<?php echo $overall_compliance >= 90 ? 'success' : ($overall_compliance >= 75 ? 'primary' : ($overall_compliance >= 60 ? 'warning' : 'danger')); ?> rounded-pill"><?php echo $overall_compliance; ?>%</span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-12 mt-3">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title"><i class="bi bi-graph-up me-2"></i>System Status</h5>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <ul class="list-group list-group-flush">
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        Database Size
                                                        <span class="badge bg-info rounded-pill"><?php echo $database_size; ?> GB</span>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        Last Backup
                                                        <span class="badge bg-success rounded-pill"><?php echo $last_backup; ?></span>
                                                    </li>
                                                </ul>
                                            </div>
                                            <div class="col-md-6">
                                                <ul class="list-group list-group-flush">
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        System Version
                                                        <span class="badge bg-primary rounded-pill"><?php echo $system_version; ?></span>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        Server Status
                                                        <span class="badge bg-success rounded-pill">Online</span>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="system_reports.php" class="btn btn-primary">View Detailed System Reports</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date("Y"); ?> Department of Social Welfare and Development - 4P's Profiling System</p>
        </div>
    </footer>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.getElementById('sidebar');
            
            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('show');
            });
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                if (window.innerWidth < 992) {
                    const isClickInsideSidebar = sidebar.contains(event.target);
                    const isClickOnToggle = menuToggle.contains(event.target);
                    
                    if (!isClickInsideSidebar && !isClickOnToggle && sidebar.classList.contains('show')) {
                        sidebar.classList.remove('show');
                    }
                }
            });
            
            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 992) {
                    sidebar.classList.remove('show');
                }
            });
            
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });
        });
    </script>
</body>
</html>