<?php
require_once "../../backend/connections/config.php";
require_once "../../backend/connections/database.php";

// Initialize variables
$error_message = '';
$success_message = '';

try {
    $db = new Database();

    // Get barangay list for select dropdown
    $barangaySql = "SELECT barangay_id, name FROM barangays ORDER BY name";
    $barangays = $db->fetchAll($barangaySql);

    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_beneficiary'])) {
        // [Existing validation logic remains the same]
    }
} catch (Exception $e) {
    error_log("Registration Error: " . $e->getMessage());
    $error_message = 'An error occurred while registering the beneficiary. Please try again later.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Beneficiary - 4P's Profiling System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #0033a0;
            --secondary-color: #ce1126;
            --light-blue: #e6f2ff;
        }

        body {
            background-color: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .register-container {
            max-width: 700px;
            margin: 2rem auto;
            padding: 2rem;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .form-header {
            background-color: var(--primary-color);
            color: white;
            padding: 1rem;
            border-radius: 8px 8px 0 0;
            margin: -2rem -2rem 2rem;
            display: flex;
            align-items: center;
        }

        .form-header h2 {
            margin: 0;
            font-size: 1.5rem;
        }

        .form-header i {
            margin-right: 1rem;
            font-size: 1.8rem;
        }

        .form-label {
            font-weight: 600;
            color: #495057;
        }

        .form-control, .form-select {
            padding: 0.75rem;
            border-color: #ced4da;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0,51,160,0.25);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #002080;
            border-color: #002080;
        }

        .profile-image-preview {
            max-width: 200px;
            max-height: 200px;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid var(--primary-color);
            margin-bottom: 1rem;
        }

        .image-upload-container {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .custom-file-upload {
            display: inline-block;
            padding: 6px 12px;
            cursor: pointer;
            background-color: var(--light-blue);
            border: 1px solid var(--primary-color);
            border-radius: 4px;
            color: var(--primary-color);
        }

        .custom-file-upload:hover {
            background-color: var(--primary-color);
            color: white;
        }

        .alert {
            display: flex;
            align-items: center;
        }

        .alert i {
            margin-right: 0.75rem;
            font-size: 1.25rem;
        }

        @media (max-width: 768px) {
            .register-container {
                margin: 1rem;
                padding: 1rem;
            }

            .form-header {
                margin: -1rem -1rem 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-container">
            <div class="form-header">
                <i class="bi bi-person-plus"></i>
                <h2>Register New Beneficiary</h2>
            </div>

            <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> 
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i> 
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <form method="post" action="" enctype="multipart/form-data">
                <input type="hidden" name="register_beneficiary" value="1">
                
                <div class="image-upload-container">
                    <img id="profile-preview" src="assets/images/profile-placeholder.png" class="profile-image-preview" alt="Profile Image">
                    <div>
                        <input type="file" id="profile_image" name="profile_image" accept="image/*" style="display:none;">
                        <label for="profile_image" class="custom-file-upload">
                            <i class="bi bi-upload me-2"></i>Upload Profile Picture
                        </label>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="full_name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="phone" name="phone">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="household_size" class="form-label">Household Size</label>
                        <input type="number" class="form-control" id="household_size" name="household_size" min="1" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="address" class="form-label">Address</label>
                    <textarea class="form-control" id="address" name="address" rows="3"></textarea>
                </div>

                <div class="mb-3">
                    <label for="barangay_id" class="form-label">Barangay</label>
                    <select class="form-select" id="barangay_id" name="barangay_id" required>
                        <option value="">Select Barangay</option>
                        <?php foreach ($barangays as $barangay): ?>
                        <option value="<?php echo $barangay['barangay_id']; ?>">
                            <?php echo htmlspecialchars($barangay['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-save me-2"></i>Register Beneficiary
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Profile image preview
        document.getElementById('profile_image').addEventListener('change', function(event) {
            const file = event.target.files[0];
            const preview = document.getElementById('profile-preview');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });

        // Form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                var validation = Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();
    </script>
</body>
</html>