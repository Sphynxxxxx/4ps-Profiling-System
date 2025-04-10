<?php
require_once "../../backend/connections/config.php";
require_once "../../backend/connections/database.php";

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in but don't restrict by role
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Initialize variables
$errors = [];
$success = false;

// Get barangays for dropdown
$barangays = [];
try {
    $db = new Database();
    $barangaysSql = "SELECT barangay_id, name FROM barangays ORDER BY name";
    $barangays = $db->fetchAll($barangaysSql);
    $db->closeConnection();
} catch (Exception $e) {
    error_log("Error fetching barangays: " . $e->getMessage());
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $age = trim($_POST['age'] ?? '');
    $yearLevel = trim($_POST['yearLevel'] ?? '');
    $phoneNumber = trim($_POST['phoneNumber'] ?? '');
    $barangayId = trim($_POST['barangay'] ?? '');
    
    // Validation
    if (empty($firstName)) {
        $errors[] = "First name is required";
    }
    
    if (empty($lastName)) {
        $errors[] = "Last name is required";
    }
    
    // Prevent registering with the current user's name
    if (strtolower($firstName) == strtolower($_SESSION['firstname']) && 
        strtolower($lastName) == strtolower($_SESSION['lastname'])) {
        $errors[] = "You cannot register yourself as a beneficiary";
    }
    
    if (empty($age)) {
        $errors[] = "Age is required";
    } elseif (!is_numeric($age) || $age < 0 || $age > 120) {
        $errors[] = "Age must be a valid number between 0 and 120";
    }
    
    if (empty($yearLevel)) {
        $errors[] = "Year level is required";
    }
    
    if (!empty($phoneNumber) && !preg_match('/^[0-9]{11}$/', $phoneNumber)) {
        $errors[] = "Phone number must be 11 digits";
    }
    
    if (empty($barangayId)) {
        $errors[] = "Barangay is required";
    }
    
    // If no errors, process the registration
    if (empty($errors)) {
        try {
            $db = new Database();
            $db->beginTransaction();

            // First check if the user exists
            $userCheck = $db->fetchOne("SELECT user_id FROM users WHERE user_id = ?", [$_SESSION['user_id']]);
            if (!$userCheck) {
                throw new Exception("Invalid user account");
            }

            // Insert beneficiary with user_id reference
            $beneficiaryInsertQuery = "
                INSERT INTO beneficiaries 
                (user_id, firstname, lastname, age, year_level, phone_number, 
                barangay_id, parent_leader_id, household_size) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $beneficiaryParams = [
                $_SESSION['user_id'], // Link to the user account
                $firstName,
                $lastName,
                $age,
                $yearLevel,
                $phoneNumber,
                $barangayId,
                $_SESSION['user_id'], // Assuming parent_leader_id is same as user_id
                1
            ];
            
            $beneficiaryId = $db->insert($beneficiaryInsertQuery, $beneficiaryParams);
            
            if ($beneficiaryId) {
                $db->commit();
                $success = true;
                $successMessage = "Beneficiary registered successfully";
            } else {
                $db->rollback();
                $errors[] = "Failed to create beneficiary record";
            }

            $db->closeConnection();
        } catch (Exception $e) {
            error_log("Error: " . $e->getMessage());
            $errors[] = "Database error occurred";
            if (isset($db) && $db->inTransaction()) {
                $db->rollback();
            }
        }
    }
}

// Fetch registered beneficiaries for the current parent leader
$beneficiariesList = [];
try {
    $db = new Database();
    $sql = "SELECT b.beneficiary_id, b.firstname, b.lastname, b.age, 
                    b.year_level, b.phone_number, 
                    ba.name as barangay_name, b.created_at,
                    u.user_id, u.email as user_email
                    FROM beneficiaries b
                    LEFT JOIN barangays ba ON b.barangay_id = ba.barangay_id
                    LEFT JOIN users u ON b.user_id = u.user_id
                    WHERE b.parent_leader_id = ?
                    ORDER BY b.created_at DESC";
    $beneficiariesList = $db->fetchAll($sql, [$_SESSION['user_id']]);
    $db->closeConnection();
} catch (Exception $e) {
    error_log("Error fetching beneficiaries: " . $e->getMessage());
}
?>

<!-- Rest of the HTML remains the same -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Beneficiary - DSWD 4P's Profiling System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <style>
        .form-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .required-field::after {
            content: " *";
            color: red;
        }
        
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
        }
        
        .status-pending {
            background-color: #ffc107;
            color: #212529;
        }
        
        .status-active {
            background-color: #198754;
            color: white;
        }
        
        .status-suspended {
            background-color: #dc3545;
            color: white;
        }
        
        .status-deactivated {
            background-color: #6c757d;
            color: white;
        }
    </style>
</head>
<body>
    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
        <div class="container-fluid">
            <button class="btn btn-primary sidebar-toggle d-lg-none" type="button">
                <i class="bi bi-list"></i>
            </button>
            
            <a class="navbar-brand d-flex align-items-center" href="../dashboard.php">
                <img src="../assets/pngwing.com (7).png" alt="DSWD Logo">
                <span class="ms-3 text-white">4P's Profiling System</span>
            </a>
            
            <div class="d-flex align-items-center">
                <div class="dropdown">
                    <a class="text-white dropdown-toggle text-decoration-none" href="#" role="button" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle fs-5 me-1"></i>
                        <span class="d-none d-md-inline-block"><?php echo $_SESSION['firstname'] ?? 'User'; ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
                        <li><a class="dropdown-item" href="../profile.php"><i class="bi bi-person me-2"></i> My Profile</a></li>
                        <li><a class="dropdown-item" href="../settings.php"><i class="bi bi-gear me-2"></i> Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../control/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content Container -->
    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- Sidebar -->
            <div class="sidebar" id="sidebar">
                <!-- Navigation Menu -->
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="../dashboard.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../profile.php">
                            <i class="bi bi-person"></i> Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../members.php">
                            <i class="bi bi-people"></i> Members
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../messages.php">
                            <i class="bi bi-chat-left-text"></i> Messages
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../calendar.php">
                            <i class="bi bi-calendar3"></i> Calendar
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../reports.php">
                            <i class="bi bi-file-earmark-text"></i> Reports
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../settings.php">
                            <i class="bi bi-gear"></i> Settings
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="main-content">
                <div class="container-fluid">
                    <!-- Page Title -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="h3 mb-0 text-gray-800">
                            <i class="bi bi-person-plus me-2"></i> Register New Beneficiary
                        </h1>
                        <a href="../dashboard.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
                        </a>
                    </div>

                    <!-- Form Container -->
                    <div class="form-container mb-5">
                        <div class="card dashboard-card">
                            <div class="card-header bg-primary text-white py-3">
                                <h5 class="mb-0"><i class="bi bi-person-plus-fill me-2"></i> Beneficiary Registration Form</h5>
                            </div>
                            <div class="card-body p-4">
                                
                                <?php if ($success): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="bi bi-check-circle-fill me-2"></i> <?php echo $successMessage; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <strong><i class="bi bi-exclamation-triangle-fill me-2"></i> Please fix the following errors:</strong>
                                    <ul class="mb-0 mt-2">
                                        <?php foreach ($errors as $error): ?>
                                        <li><?php echo $error; ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                <?php endif; ?>
                                
                                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" novalidate>
                                    <div class="row g-3">
                                        <!-- Personal Information -->
                                        <div class="col-md-6">
                                            <label for="firstName" class="form-label required-field">First Name</label>
                                            <input type="text" class="form-control" id="firstName" name="firstName" value="<?php echo htmlspecialchars($firstName ?? ''); ?>" required>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="lastName" class="form-label required-field">Last Name</label>
                                            <input type="text" class="form-control" id="lastName" name="lastName" value="<?php echo htmlspecialchars($lastName ?? ''); ?>" required>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="age" class="form-label required-field">Age</label>
                                            <input type="number" class="form-control" id="age" name="age" min="0" max="120" value="<?php echo htmlspecialchars($age ?? ''); ?>" required>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="yearLevel" class="form-label required-field">Year Level</label>
                                            <select class="form-select" id="yearLevel" name="yearLevel" required>
                                                <option value="" disabled <?php echo empty($yearLevel) ? 'selected' : ''; ?>>Select Year Level</option>
                                                <option value="Not in School" <?php echo ($yearLevel ?? '') === 'Not in School' ? 'selected' : ''; ?>>Not in School</option>
                                                <option value="Elementary - Grade 1" <?php echo ($yearLevel ?? '') === 'Elementary - Grade 1' ? 'selected' : ''; ?>>Elementary - Grade 1</option>
                                                <option value="Elementary - Grade 2" <?php echo ($yearLevel ?? '') === 'Elementary - Grade 2' ? 'selected' : ''; ?>>Elementary - Grade 2</option>
                                                <option value="Elementary - Grade 3" <?php echo ($yearLevel ?? '') === 'Elementary - Grade 3' ? 'selected' : ''; ?>>Elementary - Grade 3</option>
                                                <option value="Elementary - Grade 4" <?php echo ($yearLevel ?? '') === 'Elementary - Grade 4' ? 'selected' : ''; ?>>Elementary - Grade 4</option>
                                                <option value="Elementary - Grade 5" <?php echo ($yearLevel ?? '') === 'Elementary - Grade 5' ? 'selected' : ''; ?>>Elementary - Grade 5</option>
                                                <option value="Elementary - Grade 6" <?php echo ($yearLevel ?? '') === 'Elementary - Grade 6' ? 'selected' : ''; ?>>Elementary - Grade 6</option>
                                                <option value="High School - Grade 7" <?php echo ($yearLevel ?? '') === 'High School - Grade 7' ? 'selected' : ''; ?>>High School - Grade 7</option>
                                                <option value="High School - Grade 8" <?php echo ($yearLevel ?? '') === 'High School - Grade 8' ? 'selected' : ''; ?>>High School - Grade 8</option>
                                                <option value="High School - Grade 9" <?php echo ($yearLevel ?? '') === 'High School - Grade 9' ? 'selected' : ''; ?>>High School - Grade 9</option>
                                                <option value="High School - Grade 10" <?php echo ($yearLevel ?? '') === 'High School - Grade 10' ? 'selected' : ''; ?>>High School - Grade 10</option>
                                                <option value="Senior High - Grade 11" <?php echo ($yearLevel ?? '') === 'Senior High - Grade 11' ? 'selected' : ''; ?>>Senior High - Grade 11</option>
                                                <option value="Senior High - Grade 12" <?php echo ($yearLevel ?? '') === 'Senior High - Grade 12' ? 'selected' : ''; ?>>Senior High - Grade 12</option>
                                                <option value="College" <?php echo ($yearLevel ?? '') === 'College' ? 'selected' : ''; ?>>College</option>
                                                <option value="Graduate School" <?php echo ($yearLevel ?? '') === 'Graduate School' ? 'selected' : ''; ?>>Graduate School</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="phoneNumber" class="form-label">Phone Number (Optional)</label>
                                            <div class="input-group">
                                                <span class="input-group-text">+63</span>
                                                <input type="text" class="form-control" id="phoneNumber" name="phoneNumber" placeholder="9XXXXXXXXX" value="<?php echo htmlspecialchars($phoneNumber ?? ''); ?>" maxlength="11">
                                            </div>
                                            <div class="form-text">Enter 11-digit phone number if available</div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="barangay" class="form-label required-field">Barangay</label>
                                            <select class="form-select" id="barangay" name="barangay" required>
                                                <option value="" disabled <?php echo empty($barangayId) ? 'selected' : ''; ?>>Select Barangay</option>
                                                <?php foreach ($barangays as $barangay): ?>
                                                <option value="<?php echo $barangay['barangay_id']; ?>" <?php echo ($barangayId ?? '') == $barangay['barangay_id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($barangay['name']); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-12 mt-4">
                                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                                <button type="reset" class="btn btn-outline-secondary">
                                                    <i class="bi bi-x-circle me-1"></i> Clear Form
                                                </button>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="bi bi-person-plus me-1"></i> Register Beneficiary
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Beneficiaries List -->
                    <div class="card dashboard-card mt-4">
                        <div class="card-header bg-secondary text-white py-3 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-people-fill me-2"></i> Registered Beneficiaries</h5>
                            <div class="d-flex">
                                <input type="text" id="searchBeneficiary" class="form-control form-control-sm me-2" placeholder="Search beneficiary...">
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0" id="beneficiariesTable">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Name</th>
                                            <th>Age</th>
                                            <th>Phone Number</th>
                                            <th>Barangay</th>
                                            <th>Registration Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($beneficiariesList)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4">No beneficiaries registered yet.</td>
                                        </tr>
                                        <?php else: ?>
                                            <?php foreach ($beneficiariesList as $beneficiary): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($beneficiary['firstname'] . ' ' . $beneficiary['lastname']); ?></td>
                                                <td><?php echo htmlspecialchars($beneficiary['age']); ?></td>
                                                <td><?php echo htmlspecialchars($beneficiary['phone_number']); ?></td>
                                                <td><?php echo htmlspecialchars($beneficiary['barangay_name']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($beneficiary['created_at'])); ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <a href="view_beneficiary.php?id=<?php echo $beneficiary['beneficiary_id']; ?>" class="btn btn-info" title="View"><i class="bi bi-eye"></i></a>
                                                        <a href="edit_beneficiary.php?id=<?php echo $beneficiary['beneficiary_id']; ?>" class="btn btn-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                                        <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $beneficiary['beneficiary_id']; ?>)" class="btn btn-danger" title="Delete"><i class="bi bi-trash"></i></a>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Mobile sidebar toggle
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.querySelector('.sidebar-toggle');
            
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                });
            }
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                if (window.innerWidth < 992) {
                    const isClickInsideSidebar = sidebar.contains(event.target);
                    const isClickOnToggle = sidebarToggle.contains(event.target);
                    
                    if (!isClickInsideSidebar && !isClickOnToggle && sidebar.classList.contains('show')) {
                        sidebar.classList.remove('show');
                    }
                }
            });

            // Search functionality
            const searchInput = document.getElementById('searchBeneficiary');
            const table = document.getElementById('beneficiariesTable');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

            function filterTable() {
                const searchText = searchInput.value.toLowerCase();
                const statusValue = statusFilter.value.toLowerCase();

                for (let i = 0; i < rows.length; i++) {
                    const row = rows[i];
                    
                    // Skip the "No beneficiaries" row
                    if (row.cells.length === 1) continue;
                    
                    const name = row.cells[0].textContent.toLowerCase();
                    const statusBadge = row.cells[4].querySelector('.status-badge');
                    const status = statusBadge ? statusBadge.textContent.trim().toLowerCase() : '';
                    
                    const nameMatch = name.includes(searchText);
                    const statusMatch = statusValue === 'all' || status === statusValue;
                    
                    if (nameMatch && statusMatch) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                }
            }

            if (searchInput) {
                searchInput.addEventListener('keyup', filterTable);
            }
            
            if (statusFilter) {
                statusFilter.addEventListener('change', filterTable);
            }
        });

        function confirmDelete(id) {
            if (confirm('Are you sure you want to delete this beneficiary? This action cannot be undone.')) {
                window.location.href = 'delete_beneficiary.php?id=' + id;
            }
        }
    </script>
</body>
</html>