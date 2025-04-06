<?php
require_once "backend/connections/config.php";
require_once "backend/connections/database.php";

try {
    $db = new Database();
    
    // Updated query to get barangays based on registered users
    $barangaysQuery = "SELECT 
        b.barangay_id, 
        b.name, 
        COALESCE(b.captain_name, 'No Captain Assigned') as captain_name,
        COUNT(DISTINCT ben.beneficiary_id) as total_beneficiaries,
        COUNT(DISTINCT CASE WHEN u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN u.user_id END) as new_registrations,
        COUNT(DISTINCT CASE WHEN u.account_status = 'pending' THEN u.user_id END) as pending_verifications
    FROM barangays b 
    LEFT JOIN users u ON u.barangay = b.name
    LEFT JOIN beneficiaries ben ON ben.user_id = u.user_id AND ben.barangay_id = b.barangay_id
    GROUP BY b.barangay_id, b.name
    ORDER BY total_beneficiaries DESC";
    
    $barangays = $db->fetchAll($barangaysQuery);
    
    // Get total system-wide statistics
    $totalBeneficiariesQuery = "SELECT COUNT(*) as total FROM beneficiaries";
    $totalBeneficiaries = $db->fetchOne($totalBeneficiariesQuery)['total'] ?? 0;
    
    $pendingVerificationsQuery = "SELECT COUNT(*) as pending FROM users WHERE account_status = 'pending' AND role = 'resident'";
    $pendingVerifications = $db->fetchOne($pendingVerificationsQuery)['pending'] ?? 0;
    
    $db->closeConnection();
    
} catch (Exception $e) {
    // Handle database errors
    error_log("Admin Error: " . $e->getMessage());
    $barangays = [];
    $totalBeneficiaries = 0;
    $pendingVerifications = 0;
}

// If no barangays in database, show default options from registration
if (empty($barangays)) {
    $barangays = [
        ['barangay_id' => 1, 'name' => 'Barangay 1', 'captain_name' => 'Not Assigned', 'total_beneficiaries' => 0, 'new_registrations' => 0, 'pending_verifications' => 0],
        ['barangay_id' => 2, 'name' => 'Barangay 2', 'captain_name' => 'Not Assigned', 'total_beneficiaries' => 0, 'new_registrations' => 0, 'pending_verifications' => 0],
        ['barangay_id' => 3, 'name' => 'Barangay 3', 'captain_name' => 'Not Assigned', 'total_beneficiaries' => 0, 'new_registrations' => 0, 'pending_verifications' => 0]
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>4P's Profiling System - Barangay Selection</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --dswd-blue: #0033a0;
            --dswd-red: #ce1126;
        }
        
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f0f0;
            padding: 0;
            margin: 0;
        }
        
        .header {
            background-color: white;
            padding: 15px 0;
            text-align: center;
            border-bottom: 3px solid var(--dswd-red);
        }
        
        .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .logo {
            height: 80px;
            margin-right: 20px;
        }
        
        .title {
            color: var(--dswd-blue);
            font-size: 3rem;
            font-weight: bold;
            margin: 0;
        }
        
        .admin-title {
            text-align: center;
            color: var(--dswd-blue);
            font-size: 2.5rem;
            font-weight: bold;
            margin: 30px 0;
        }
        
        .barangay-grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 50px;
            padding: 20px;
            max-width: 100%; 
            margin: 0 auto;
        }
        
        .barangay-card {
            border: 2px solid var(--dswd-blue);
            border-radius: 10px;
            overflow: hidden;
            width: 400px;
            height: 50vh;
            background-color: white;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            text-decoration: none;
            color: inherit;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            position: relative;
        }
        
        .barangay-card:hover {
            transform: scale(1.05);
        }
        
        .barangay-image {
            width: 100%;
            height: 350px;
            flex-shrink: 0;
            object-fit: cover;
        }
        
        .barangay-content {
            padding: 20px;
            text-align: center;
            flex-grow: 1; 
            display: flex;
            flex-direction: column;
            justify-content: space-between; 
        }
        
        .barangay-name {
            color: var(--dswd-blue);
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .barangay-captain {
            color: #666;
            font-size: 1rem;
            margin-bottom: 10px;
        }
        
        .registered-count {
            display: inline-block;
            background-color: var(--dswd-blue);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: bold;
            margin-bottom: 15px;
        }
        
        .new-registration-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: var(--dswd-red);
            color: white;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: bold;
            z-index: 1;
        }

        .pending-verification-badge {
            position: absolute;
            top: 40px;
            right: 10px;
            background-color: #ffc107;
            color: black;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: bold;
            z-index: 1;
        }
        
        .system-summary {
            background-color: var(--dswd-blue);
            color: white;
            padding: 20px;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .footer {
            background-color: var(--dswd-blue);
            color: white;
            text-align: center;
            padding: 15px 0;
            margin-top: 50px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .title {
                font-size: 2rem;
            }
            
            .admin-title {
                font-size: 2rem;
                margin: 20px 0;
            }
            
            .barangay-grid {
                padding: 10px;
            }
            
            .barangay-card {
                width: 100%;
                max-width: 350px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="logo-container">
            <img src="User/assets/pngwing.com (7).png" alt="DSWD Logo" class="logo">
            <h1 class="title">4P's Profiling System</h1>
        </div>
    </header>
    
    <!-- System Summary -->
    <div class="system-summary">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h3><?php echo number_format($totalBeneficiaries); ?></h3>
                    <p>Total Beneficiaries</p>
                </div>
                <div class="col-md-4">
                    <h3><?php echo number_format($pendingVerifications); ?></h3>
                    <p>Pending Verifications</p>
                </div>
                <div class="col-md-4">
                    <h3><?php echo count($barangays); ?></h3>
                    <p>Total Barangays</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="container-fluid">
        <h2 class="admin-title">BARANGAY SELECTION</h2>
        
        <!-- Barangay Selection Cards -->
        <div class="barangay-grid">
            <?php foreach($barangays as $index => $barangay): ?>
                <?php 
                $imageNumber = ($index % 3) + 1;
                ?>
                <a href="Admin/admin_dashboard.php?barangay_id=<?php echo $barangay['barangay_id']; ?>" class="barangay-card">
                    <?php if ($barangay['new_registrations'] > 0): ?>
                        <span class="new-registration-badge"><?php echo $barangay['new_registrations']; ?></span>
                    <?php endif; ?>

                    <?php if ($barangay['pending_verifications'] > 0): ?>
                        <span class="pending-verification-badge"><?php echo $barangay['pending_verifications']; ?></span>
                    <?php endif; ?>
                    
                    <img src="img/barangay<?php echo $imageNumber; ?>.jpg" alt="<?php echo htmlspecialchars($barangay['name']); ?>" class="barangay-image" onerror="this.src='Admin/assets/brgy 1.jpg'">
                    <div class="barangay-content">
                        <h3 class="barangay-name"><?php echo htmlspecialchars(strtoupper($barangay['name'])); ?></h3>
                        <p class="barangay-captain"><?php echo htmlspecialchars($barangay['captain_name']); ?></p>
                        <div class="registered-count">
                            <i class="bi bi-people-fill me-2"></i>
                            <?php echo number_format($barangay['total_residents']); ?> Total Residents
                        </div>
                        <?php if ($barangay['last_registration_date']): ?>
                            <small class="text-muted">
                                Last Registration: <?php echo date('M d, Y', strtotime($barangay['last_registration_date'])); ?>
                            </small>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date("Y"); ?> Department of Social Welfare and Development - 4P's Profiling System</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>