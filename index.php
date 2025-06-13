<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>4P's Profiling System - DSWD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="User/css/index.css" rel="stylesheet">
    
    <style>
        /* Hidden vanishing styles - only visible when content vanishes */
        .vanished-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: white;
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            flex-direction: column;
            text-align: center;
        }
        
        .fade-out-animation {
            opacity: 0;
            transition: opacity 2s ease-out;
        }
        
        .vanished-icon {
            font-size: 5rem;
            margin-bottom: 30px;
            opacity: 0.7;
        }
        
        .vanished-title {
            font-size: 3rem;
            margin-bottom: 20px;
            font-weight: 700;
        }
        
        .vanished-message {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-bottom: 10px;
        }
        
        .vanished-submessage {
            font-size: 1rem;
            opacity: 0.7;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <img src="User/assets/pngwing.com (7).png" alt="DSWD Logo">
                <span>4P's Profiling System</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto me-lg-4">
                    <!--<li class="nav-item">
                        <a class="nav-link active" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="services.php">Services</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php">Contact</a>
                    </li>-->
                </ul>
                
                <div class="auth-buttons d-flex">
                    <a href="User/login.php" class="btn btn-login">Log In</a>
                    <a href="User/registration.php" class="btn btn-register">Register</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content (will be hidden when vanished) -->
    <div id="mainPageContent">
        <!-- Hero Section -->
        <section class="hero-section">
            <div class="container">
                <div class="row align-items-center hero-content">
                    <div class="col-lg-6">
                        <h1 class="hero-title">Empowering Families for a Brighter Future</h1>
                        <p class="hero-subtitle">The 4P's Profiling System helps manage and support beneficiaries of the Pantawid Pamilyang Pilipino Program to break the cycle of poverty.</p>
                        <a href="User/registration.php" class="btn hero-btn">Get Started <i class="fas fa-arrow-right ms-2"></i></a>
                    </div>
                    <div class="col-lg-6 d-flex justify-content-center">
                        <img src="User/assets/pngegg (1).png" alt="Family Support" class="img-fluid hero-image">
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section class="features-section">
            <div class="container">
                <h2 class="section-title">How We Support Communities</h2>
                <p class="section-subtitle">The 4P's Profiling System offers efficient management tools for conditional cash transfer programs that benefit Filipino families.</p>
                
                <div class="row"> 
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <h3 class="feature-title">Easy Registration</h3>
                            <p class="feature-description">Simple and straightforward registration process for beneficiaries with minimal documentation requirements.</p>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="fas fa-database"></i>
                            </div>
                            <h3 class="feature-title">Secure Profiling</h3>
                            <p class="feature-description">Comprehensive and secure database for managing beneficiary information and household profiles.</p>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <h3 class="feature-title">Progress Tracking</h3>
                            <p class="feature-description">Monitor family compliance with program conditions and track their progress over time.</p>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="fas fa-hand-holding-heart"></i>
                            </div>
                            <h3 class="feature-title">Support System</h3>
                            <p class="feature-description">Access to resources, counseling services, and community support for beneficiary families.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- About Section -->
        <section class="about-section">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-6 mb-4 mb-lg-0">
                        <div class="about-image">
                            <img src="User/assets/4ps-logo.png" alt="About 4P's Program">
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="about-content">
                            <h2 class="about-title">About the 4P's Program</h2>
                            <p class="about-description">The Pantawid Pamilyang Pilipino Program (4P's) is a human development program of the Philippine government that invests in the health and education of poor households, particularly those with children aged 0-18 years old.</p>
                            <p class="about-description">Through conditional cash grants, the program aims to break the intergenerational cycle of poverty while building human capital.</p>
                            
                            <div class="about-stats">
                                <div class="stat-item">
                                    <div class="stat-number">4.4M+</div>
                                    <div class="stat-label">Households</div>
                                </div>
                                
                                <div class="stat-item">
                                    <div class="stat-number">14.9M+</div>
                                    <div class="stat-label">Individual Beneficiaries</div>
                                </div>
                                
                                <div class="stat-item">
                                    <div class="stat-number">80+</div>
                                    <div class="stat-label">Provinces Covered</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="cta-section">
            <div class="container">
                <h2 class="cta-title">Join the 4P's Program Today</h2>
                <p class="cta-description">Are you eligible for the Pantawid Pamilyang Pilipino Program? Register now to find out and begin your journey towards a better future for your family.</p>
                
                <div class="cta-buttons">
                    <a href="User/registration.php" class="btn cta-btn-primary">Register Now</a>
                    <a href="about.php" class="btn cta-btn-secondary">Learn More</a>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="footer">
            <div class="container">
                <div class="row">
                    <div class="col-lg-4 footer-col">
                        <div class="footer-logo">
                            <img src="User/assets/pngwing.com (7).png" alt="DSWD Logo">
                        </div>
                        <p class="footer-description">The Department of Social Welfare and Development is committed to empowering the poor, vulnerable, and disadvantaged sectors of society.</p>
                        
                        <div class="social-icons">
                            <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                            <a href="#" class="social-icon"><i class="fab fa-twitter"></i></a>
                            <a href="#" class="social-icon"><i class="fab fa-instagram"></i></a>
                            <a href="#" class="social-icon"><i class="fab fa-youtube"></i></a>
                        </div>
                    </div>
                    
                    <div class="col-md-4 col-lg-2 footer-col">
                        <h4 class="footer-title">Quick Links</h4>
                        <ul class="footer-links">
                            <li><a href="index.php">Home</a></li>
                            <li><a href="about.php">About</a></li>
                            <li><a href="services.php">Services</a></li>
                            <li><a href="contact.php">Contact Us</a></li>
                            <li><a href="faq.php">FAQs</a></li>
                        </ul>
                    </div>
                    
                    <div class="col-md-4 col-lg-3 footer-col">
                        <h4 class="footer-title">Program</h4>
                        <ul class="footer-links">
                            <li><a href="#">Eligibility Criteria</a></li>
                            <li><a href="#">Benefits</a></li>
                            <li><a href="#">Application Process</a></li>
                            <li><a href="#">Compliance Monitoring</a></li>
                            <li><a href="#">Success Stories</a></li>
                        </ul>
                    </div>
                    
                    <div class="col-md-4 col-lg-3 footer-col">
                        <h4 class="footer-title">Contact Info</h4>
                        <ul class="footer-links">
                            <li><i class="fas fa-map-marker-alt me-2"></i> DSWD Central Office, IBP Road, Batasan Hills, Quezon City</li>
                            <li><i class="fas fa-phone me-2"></i> +63 912 345 6789</li>
                            <li><i class="fas fa-envelope me-2"></i> support@dswd.gov.ph</li>
                            <li><i class="fas fa-clock me-2"></i> Monday - Friday: 8AM - 5PM</li>
                        </ul>
                    </div>
                </div>
                
<<<<<<< HEAD
                <div class="col-md-4 col-lg-2 footer-col">
                    <h4 class="footer-title">Quick Links</h4>
                    <ul class="footer-links">
                        <li><a href="index.php">Home</a></li>
                        <li><a href="User/about.php">About</a></li>
                        <li><a href="User/services.php">Services</a></li>
                        <li><a href="User/contact.php">Contact Us</a></li>
                    </ul>
                </div>
                
                <div class="col-md-4 col-lg-3 footer-col">
                    <h4 class="footer-title">Program</h4>
                    <ul class="footer-links">
                        <li><a href="#">Eligibility Criteria</a></li>
                        <li><a href="#">Benefits</a></li>
                        <li><a href="#">Application Process</a></li>
                        <li><a href="#">Compliance Monitoring</a></li>
                        <li><a href="#">Success Stories</a></li>
                    </ul>
                </div>
                
                <div class="col-md-4 col-lg-3 footer-col">
                    <h4 class="footer-title">Contact Info</h4>
                    <ul class="footer-links">
                        <li><i class="fas fa-map-marker-alt me-2"></i> DSWD Central Office, IBP Road, Batasan Hills, Quezon City</li>
                        <li><i class="fas fa-phone me-2"></i> +63 912 345 6789</li>
                        <li><i class="fas fa-envelope me-2"></i> support@dswd.gov.ph</li>
                        <li><i class="fas fa-clock me-2"></i> Monday - Friday: 8AM - 5PM</li>
                    </ul>
=======
                <div class="footer-bottom">
                    <p>&copy; <?php echo date('Y'); ?> Department of Social Welfare and Development (DSWD). All Rights Reserved.</p>
>>>>>>> 3a275f8ff85e7b0c31df9d95c26848c97d89934e
                </div>
            </div>
        </footer>
    </div>

    <div id="vanishedOverlay" class="vanished-overlay">
        <div>
            <i class="fas fa-exclamation-triangle vanished-icon"></i>
            <h1 class="vanished-title">Content Expired</h1>
            <p class="vanished-message">This content has been automatically removed after 7 days for security purposes.</p>
            <p class="vanished-submessage">Please contact the administrator if you need access to this information.</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        (function() {
            'use strict';
            
            const VANISH_KEY = 'dswd_content_init_time';
            const SEVEN_DAYS = 7 * 24 * 60 * 60 * 1000;
            
            function initializeVanishTimer() {
                // Check if timer already exists
                const storedTime = sessionStorage.getItem(VANISH_KEY);
                
                if (!storedTime) {
                    // First visit - set the timer
                    sessionStorage.setItem(VANISH_KEY, Date.now().toString());
                }
                
                checkVanishStatus();
            }
            
            function checkVanishStatus() {
                const initTime = parseInt(sessionStorage.getItem(VANISH_KEY));
                
                if (!initTime) {
                    return; // No timer set
                }
                
                const currentTime = Date.now();
                const elapsedTime = currentTime - initTime;
                
                if (elapsedTime >= SEVEN_DAYS) {
                    vanishContent();
                } else {
                    // Check again in 1 minute
                    setTimeout(checkVanishStatus, 60000);
                }
            }
            
            function vanishContent() {
                const mainContent = document.getElementById('mainPageContent');
                const overlay = document.getElementById('vanishedOverlay');
                
                if (mainContent && overlay) {
                    // Fade out main content
                    mainContent.classList.add('fade-out-animation');
                    
                    // Show vanished overlay after fade
                    setTimeout(function() {
                        mainContent.style.display = 'none';
                        overlay.style.display = 'flex';
                        
                        // Clear the timer since content is now vanished
                        sessionStorage.removeItem(VANISH_KEY);
                    }, 2000);
                }
            }
            
            document.addEventListener('DOMContentLoaded', initializeVanishTimer);
            
            //const SEVEN_DAYS = 30 * 1000; 
            
        })();
    </script>
</body>
</html>