<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact - 4P's Profiling System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="css/login_register.css" rel="stylesheet">
    
    <style>
        .contact-container {
            max-width: 1000px;
            margin: 50px auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 40px;
            text-align: center;
        }
        
        .contact-icon {
            font-size: 4rem;
            color: #0033a0;
            margin-bottom: 20px;
        }
        
        .contact-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #0033a0;
            margin-bottom: 30px;
            font-family: 'Poppins', sans-serif;
        }
        
        .program-badge {
            background: linear-gradient(45deg, #0033a0, #ce1126);
            color: white;
            padding: 8px 25px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
            margin-bottom: 25px;
        }
        
        .contact-intro {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .contact-intro p {
            font-size: 1.1rem;
            line-height: 1.8;
            color: #666;
        }
        
        .contact-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            margin: 40px 0;
        }
        
        .contact-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 30px 25px;
            text-align: center;
            border-top: 4px solid #0033a0;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .contact-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(0, 51, 160, 0.02), rgba(206, 17, 38, 0.02));
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .contact-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 51, 160, 0.15);
            border-top-color: #ce1126;
        }
        
        .contact-card:hover::before {
            opacity: 1;
        }
        
        .contact-card i {
            font-size: 3rem;
            color: #0033a0;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }
        
        .contact-card:hover i {
            color: #ce1126;
        }
        
        .contact-card h4 {
            color: #0033a0;
            font-weight: 600;
            margin-bottom: 15px;
            font-size: 1.3rem;
            position: relative;
            z-index: 1;
        }
        
        .contact-card p {
            color: #555;
            margin: 10px 0;
            font-size: 1rem;
            position: relative;
            z-index: 1;
        }
        
        .contact-link {
            color: #0033a0;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        
        .contact-link:hover {
            color: #ce1126;
        }
        
        .contact-form-section {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 15px;
            padding: 40px;
            margin: 40px 0;
            text-align: left;
        }
        
        .form-title {
            color: #0033a0;
            font-weight: 700;
            font-size: 1.8rem;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #0033a0;
            box-shadow: 0 0 0 0.2rem rgba(0, 51, 160, 0.15);
        }
        
        .form-label {
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .btn-submit {
            background: linear-gradient(45deg, #0033a0, #ce1126);
            color: white;
            border: none;
            padding: 12px 40px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            display: block;
            margin: 20px auto 0;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 51, 160, 0.3);
            color: white;
        }
        
        .back-button {
            background: linear-gradient(45deg, #0033a0, #ce1126);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            font-size: 1rem;
            margin-top: 20px;
        }
        
        .back-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 51, 160, 0.3);
            color: white;
        }
        
        .highlight-text {
            color: #0033a0;
            font-weight: 600;
        }
        
        .office-hours {
            background: linear-gradient(135deg, #0033a0, rgba(0, 51, 160, 0.8));
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin: 30px 0;
            text-align: center;
        }
        
        .office-hours h4 {
            font-weight: 700;
            margin-bottom: 15px;
        }
        
        .office-hours p {
            margin: 5px 0;
            opacity: 0.9;
        }
        
        .alert-info {
            background: linear-gradient(45deg, rgba(0, 51, 160, 0.1), rgba(206, 17, 38, 0.1));
            border: 1px solid rgba(0, 51, 160, 0.2);
            color: #0033a0;
            border-radius: 10px;
            padding: 15px;
            margin: 20px 0;
        }
        
        @media (max-width: 768px) {
            .contact-container {
                margin: 20px;
                padding: 30px 20px;
            }
            
            .contact-title {
                font-size: 2rem;
            }
            
            .contact-methods {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .contact-form-section {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="../index.php">
                <img src="assets/pngwing.com (7).png" alt="DSWD Logo">
                <span>4P's Profiling System</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="services.php">Services</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="contact.php">Contact</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Contact Content -->
    <div class="container">
        <div class="contact-container">
            <div class="contact-icon">
                <i class="fas fa-envelope"></i>
            </div>
            
            <div class="program-badge">Get In Touch</div>
            
            <h1 class="contact-title">Contact Us</h1>
            
            <!-- Introduction -->
            <div class="contact-intro">
                <p>
                    For questions, support, or collaboration regarding the <span class="highlight-text">4P's Parent Leader Profiling System</span>, 
                    please don't hesitate to reach out to us. We're here to help and support your needs.
                </p>
            </div>
            
            <!-- Contact Methods -->
            <div class="contact-methods">
                <!-- Email Contact -->
                <div class="contact-card">
                    <i class="fas fa-envelope"></i>
                    <h4>Email Support</h4>
                    <p>Send us your questions or concerns</p>
                    <p><a href="mailto:4psprofiling.support@example.com" class="contact-link">4psprofiling.support@example.com</a></p>
                    <p class="mt-2"><small>Response within 24-48 hours</small></p>
                </div>
                
                <!-- Phone Contact -->
                <div class="contact-card">
                    <i class="fas fa-phone"></i>
                    <h4>Phone Support</h4>
                    <p>Call us for immediate assistance</p>
                    <p><a href="tel:+639123456789" class="contact-link">+63 912 345 6789</a></p>
                    <p class="mt-2"><small>Available during office hours</small></p>
                </div>
                
                <!-- Office Address -->
                <div class="contact-card">
                    <i class="fas fa-map-marker-alt"></i>
                    <h4>Office Address</h4>
                    <p>Visit us at our office location</p>
                    <p class="contact-link">DSWD Office<br>[Insert Local Office Address or Barangay]</p>
                    <p class="mt-2"><small>Monday to Friday</small></p>
                </div>
            </div>
            
            <!-- Office Hours -->
            <div class="office-hours">
                <h4><i class="fas fa-clock me-2"></i>Office Hours</h4>
                <p><strong>Monday - Friday:</strong> 8:00 AM - 5:00 PM</p>
                <p><strong>Saturday:</strong> 8:00 AM - 12:00 PM</p>
                <p><strong>Sunday:</strong> Closed</p>
                <p class="mt-2"><small>Emergency support available 24/7 through email</small></p>
            </div>
            
            <!-- Additional Contact Info -->
            <div class="alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Additional Support Options:</strong> You may also reach out through our in-system messaging or feedback form once you're logged into the platform.
            </div>
            
            <!-- Contact Form -->
            <div class="contact-form-section">
                <h3 class="form-title"><i class="fas fa-paper-plane me-2"></i>Send us a Message</h3>
                
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">First Name *</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Last Name *</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email Address *</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="subject" class="form-label">Subject *</label>
                        <select class="form-control" id="subject" name="subject" required>
                            <option value="">Select a subject</option>
                            <option value="technical_support">Technical Support</option>
                            <option value="account_help">Account Help</option>
                            <option value="feature_request">Feature Request</option>
                            <option value="bug_report">Bug Report</option>
                            <option value="general_inquiry">General Inquiry</option>
                            <option value="collaboration">Collaboration</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="message" class="form-label">Message *</label>
                        <textarea class="form-control" id="message" name="message" rows="5" placeholder="Please describe your question or concern in detail..." required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="organization" class="form-label">Organization/Office (Optional)</label>
                        <input type="text" class="form-control" id="organization" name="organization" placeholder="DSWD Office, Barangay, etc.">
                    </div>
                    
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-paper-plane me-2"></i>Send Message
                    </button>
                </form>
            </div>
            
            <!-- Additional Information -->
            <div class="alert-info">
                <i class="fas fa-shield-alt me-2"></i>
                <strong>Privacy Notice:</strong> Your personal information is protected and will only be used to respond to your inquiry. We do not share your information with third parties.
            </div>
            
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Form validation and enhancement
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const submitBtn = document.querySelector('.btn-submit');
            
            // Add form submission handling
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Basic validation
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        isValid = false;
                        field.classList.add('is-invalid');
                    } else {
                        field.classList.remove('is-invalid');
                    }
                });
                
                if (isValid) {
                    // Show loading state
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';
                    submitBtn.disabled = true;
                    
                    // Simulate form submission (replace with actual form handling)
                    setTimeout(() => {
                        alert('Thank you for your message! We will get back to you within 24-48 hours.');
                        form.reset();
                        submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Send Message';
                        submitBtn.disabled = false;
                    }, 2000);
                }
            });
            
            // Remove validation classes on input
            const inputs = form.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    this.classList.remove('is-invalid');
                });
            });
        });
    </script>
</body>
</html>