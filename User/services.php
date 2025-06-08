<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services - 4P's Profiling System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="css/login_register.css" rel="stylesheet">
    
    <style>
        .services-container {
            max-width: 1000px;
            margin: 50px auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 40px;
            text-align: center;
        }
        
        .services-icon {
            font-size: 4rem;
            color: #0033a0;
            margin-bottom: 20px;
        }
        
        .services-title {
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
        
        .services-intro {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .services-intro p {
            font-size: 1.1rem;
            line-height: 1.8;
            color: #666;
            margin-bottom: 20px;
        }
        
        .service-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 30px 25px;
            margin-bottom: 25px;
            border-left: 5px solid #0033a0;
            transition: all 0.3s ease;
            text-align: left;
            position: relative;
            overflow: hidden;
        }
        
        .service-card::before {
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
        
        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 51, 160, 0.15);
            border-left-color: #ce1126;
        }
        
        .service-card:hover::before {
            opacity: 1;
        }
        
        .service-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }
        
        .service-icon {
            font-size: 2.5rem;
            color: #0033a0;
            margin-right: 20px;
            min-width: 60px;
            text-align: center;
        }
        
        .service-card:hover .service-icon {
            color: #ce1126;
        }
        
        .service-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0033a0;
            margin: 0;
        }
        
        .service-description {
            color: #444;
            line-height: 1.7;
            margin-bottom: 20px;
            font-size: 1rem;
            position: relative;
            z-index: 1;
        }
        
        .service-features {
            position: relative;
            z-index: 1;
        }
        
        .service-features ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .service-features li {
            padding: 8px 0;
            color: #555;
            position: relative;
            padding-left: 25px;
        }
        
        .service-features li::before {
            content: '✓';
            position: absolute;
            left: 0;
            top: 8px;
            color: #0033a0;
            font-weight: bold;
            font-size: 1.1rem;
        }
        
        .highlight-text {
            color: #0033a0;
            font-weight: 600;
        }
        
        .cta-section {
            background: linear-gradient(135deg, #0033a0, rgba(0, 51, 160, 0.8));
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin: 40px 0;
            text-align: center;
        }
        
        .cta-section h3 {
            font-weight: 700;
            margin-bottom: 15px;
            font-size: 1.8rem;
        }
        
        .cta-section p {
            font-size: 1.1rem;
            margin-bottom: 25px;
            opacity: 0.9;
        }
        
        .cta-button {
            background: white;
            color: #0033a0;
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
        }
        
        .cta-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 255, 255, 0.3);
            color: #0033a0;
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
        
        .stats-banner {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
            padding: 20px;
            background: linear-gradient(45deg, #f8f9fa, #e9ecef);
            border-radius: 15px;
        }
        
        .stat-item {
            text-align: center;
            padding: 15px;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #0033a0;
            display: block;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 5px;
        }
        
        @media (max-width: 768px) {
            .services-container {
                margin: 20px;
                padding: 30px 20px;
            }
            
            .services-title {
                font-size: 2rem;
            }
            
            .service-header {
                flex-direction: column;
                text-align: center;
            }
            
            .service-icon {
                margin-right: 0;
                margin-bottom: 15px;
            }
            
            .stats-banner {
                grid-template-columns: 1fr;
                gap: 15px;
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
                        <a class="nav-link active" href="services.php">Services</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php">Contact</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Services Content -->
    <div class="container">
        <div class="services-container">
            <div class="services-icon">
                <i class="fas fa-cogs"></i>
            </div>
            
            <div class="program-badge">System Services</div>
            
            <h1 class="services-title">Our Services</h1>
            
            <!-- Introduction -->
            <div class="services-intro">
                <p>
                    The <span class="highlight-text">4P's Parent Leader Profiling System</span> offers comprehensive digital services designed to streamline the management and monitoring of Parent Leaders under the Pantawid Pamilyang Pilipino Program.
                </p>
            </div>
            
            <!-- Stats Banner -->
            <div class="stats-banner">
                <div class="stat-item">
                    <span class="stat-number">4</span>
                    <div class="stat-label">Core Services</div>
                </div>
                <div class="stat-item">
                    <span class="stat-number">100%</span>
                    <div class="stat-label">Digital Platform</div>
                </div>
                <div class="stat-item">
                    <span class="stat-number">24/7</span>
                    <div class="stat-label">System Access</div>
                </div>
                <div class="stat-item">
                    <span class="stat-number">Real-time</span>
                    <div class="stat-label">Data Updates</div>
                </div>
            </div>
            
            <!-- Service Cards -->
            <div class="row">
                <div class="col-12">
                    <!-- Profile Management Service -->
                    <div class="service-card">
                        <div class="service-header">
                            <div class="service-icon">
                                <i class="fas fa-user-edit"></i>
                            </div>
                            <h3 class="service-title">Profile Management</h3>
                        </div>
                        <div class="service-description">
                            Comprehensive profile management system that allows you to add, view, and update detailed Parent Leader profiles with complete demographic information, household data, and program involvement tracking.
                        </div>
                        <div class="service-features">
                            <ul>
                                <li>Add new Parent Leader profiles with detailed demographic information</li>
                                <li>View comprehensive household data and family composition</li>
                                <li>Update program involvement and participation status</li>
                                <li>Track educational background and skills development</li>
                                <li>Manage contact information and address details</li>
                                <li>Store and update identification documents</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="col-12">
                    <!-- Data Monitoring Service -->
                    <div class="service-card">
                        <div class="service-header">
                            <div class="service-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <h3 class="service-title">Data Monitoring</h3>
                        </div>
                        <div class="service-description">
                            Advanced monitoring system that tracks the activities, performance, and community contributions of each Parent Leader in real-time, providing valuable insights for program improvement.
                        </div>
                        <div class="service-features">
                            <ul>
                                <li>Track daily activities and community engagement</li>
                                <li>Monitor performance metrics and goal achievements</li>
                                <li>Record community contributions and impact assessments</li>
                                <li>Real-time dashboard for activity monitoring</li>
                                <li>Performance analytics and trend analysis</li>
                                <li>Automated alerts for important milestones</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="col-12">
                    <!-- Reports Generation Service -->
                    <div class="service-card">
                        <div class="service-header">
                            <div class="service-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <h3 class="service-title">Reports Generation</h3>
                        </div>
                        <div class="service-description">
                            Powerful reporting engine that creates structured reports for evaluation, auditing, and planning purposes, helping stakeholders make informed decisions based on comprehensive data analysis.
                        </div>
                        <div class="service-features">
                            <ul>
                                <li>Generate detailed evaluation reports for individual Parent Leaders</li>
                                <li>Create comprehensive auditing reports for compliance</li>
                                <li>Produce planning reports for program development</li>
                                <li>Custom report templates and formats</li>
                                <li>Export reports in multiple formats (PDF, Excel, CSV)</li>
                                <li>Scheduled automatic report generation</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="col-12">
                    <!-- Search & Filter Tools Service -->
                    <div class="service-card">
                        <div class="service-header">
                            <div class="service-icon">
                                <i class="fas fa-search"></i>
                            </div>
                            <h3 class="service-title">Search & Filter Tools</h3>
                        </div>
                        <div class="service-description">
                            Sophisticated search and filtering system that allows users to easily locate specific Parent Leaders using customizable search criteria and advanced filter options for efficient data retrieval.
                        </div>
                        <div class="service-features">
                            <ul>
                                <li>Advanced search functionality with multiple criteria</li>
                                <li>Customizable filter options for refined results</li>
                                <li>Quick search by name, ID, or location</li>
                                <li>Filter by program status, performance level, and demographics</li>
                                <li>Save frequently used search and filter combinations</li>
                                <li>Export filtered results for further analysis</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Call to Action -->
            <div class="cta-section">
                <h3><i class="fas fa-rocket me-2"></i>Ready to Get Started?</h3>
                <p>
                    Experience the power of comprehensive Parent Leader management with our advanced profiling system. 
                    Join thousands of users who trust our platform for efficient 4P's program administration.
                </p>
                <a href="login.php" class="cta-button">
                    <i class="fas fa-sign-in-alt"></i>
                    Access System
                </a>
            </div>
            
            
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>