<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About - 4P's Profiling System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="css/login_register.css" rel="stylesheet">

    <style>
        .about-container {
            max-width: 900px;
            margin: 50px auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 40px;
            text-align: center;
        }
        
        .about-icon {
            font-size: 4rem;
            color: #0033a0;
            margin-bottom: 20px;
        }
        
        .about-title {
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
        
        .about-content {
            text-align: left;
            margin-bottom: 40px;
        }
        
        .about-content p {
            font-size: 1.1rem;
            line-height: 1.8;
            color: #333;
            margin-bottom: 20px;
        }
        
        .highlight-text {
            color: #0033a0;
            font-weight: 600;
        }
        
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin: 40px 0;
        }
        
        .feature-item {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px 20px;
            text-align: center;
            border-left: 4px solid #0033a0;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .feature-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 51, 160, 0.15);
        }
        
        .feature-item i {
            font-size: 2.5rem;
            color: #0033a0;
            margin-bottom: 15px;
        }
        
        .feature-item h4 {
            color: #0033a0;
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 1.2rem;
        }
        
        .feature-item p {
            color: #666;
            font-size: 0.95rem;
            line-height: 1.6;
            margin: 0;
        }
        
        .mission-section {
            background: linear-gradient(135deg, #0033a0, rgba(0, 51, 160, 0.8));
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin: 40px 0;
        }
        
        .mission-section h3 {
            font-weight: 700;
            margin-bottom: 20px;
            font-size: 1.8rem;
        }
        
        .mission-section p {
            font-size: 1.1rem;
            line-height: 1.7;
            margin: 0;
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
        
        .stats-row {
            display: flex;
            justify-content: space-around;
            margin: 30px 0;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .stat-item {
            text-align: center;
            min-width: 120px;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #0033a0;
            display: block;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        @media (max-width: 768px) {
            .about-container {
                margin: 20px;
                padding: 30px 20px;
            }
            
            .about-title {
                font-size: 2rem;
            }
            
            .feature-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .stats-row {
                flex-direction: column;
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
                        <a class="nav-link active" href="about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="services.php">Services</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php">Contact</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- About Content -->
    <div class="container">
        <div class="about-container">
            <div class="about-icon">
                <i class="fas fa-info-circle"></i>
            </div>
            
            <div class="program-badge">Pantawid Pamilyang Pilipino Program</div>
            
            <h1 class="about-title">4P's Parent Leader Profiling System</h1>
            
            <!-- Stats Row -->
            <div class="stats-row">
                <div class="stat-item">
                    <span class="stat-number">4P's</span>
                    <div class="stat-label">Program</div>
                </div>
                <div class="stat-item">
                    <span class="stat-number">100%</span>
                    <div class="stat-label">Digital</div>
                </div>
                <div class="stat-item">
                    <span class="stat-number">24/7</span>
                    <div class="stat-label">Access</div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="about-content">
                <p>
                    The <span class="highlight-text">4P's Parent Leader Profiling System</span> is a comprehensive digital tool designed to help streamline the management, monitoring, and evaluation of Parent Leaders under the Pantawid Pamilyang Pilipino Program (4P's).
                </p>
                
                <p>
                    This innovative system enables stakeholders to store, track, and access updated information about Parent Leaders' profiles, responsibilities, and community participation, promoting more efficient service delivery and data-driven decision-making.
                </p>
            </div>
            
            <!-- Features Grid -->
            <div class="feature-grid">
                <div class="feature-item">
                    <i class="fas fa-database"></i>
                    <h4>Data Management</h4>
                    <p>Centralized storage and management of Parent Leader profiles with easy access to updated information.</p>
                </div>
                
                <div class="feature-item">
                    <i class="fas fa-chart-line"></i>
                    <h4>Monitoring & Tracking</h4>
                    <p>Real-time monitoring of Parent Leader activities and community participation.</p>
                </div>
                
                <div class="feature-item">
                    <i class="fas fa-clipboard-check"></i>
                    <h4>Evaluation Tools</h4>
                    <p>Comprehensive evaluation mechanisms to assess Parent Leader performance and impact.</p>
                </div>
                
                <div class="feature-item">
                    <i class="fas fa-users-cog"></i>
                    <h4>Service Delivery</h4>
                    <p>Enhanced service delivery through streamlined processes and better coordination.</p>
                </div>
                
                <div class="feature-item">
                    <i class="fas fa-analytics"></i>
                    <h4>Data-Driven Decisions</h4>
                    <p>Facilitate informed decision-making through comprehensive data analysis and insights.</p>
                </div>
                
                <div class="feature-item">
                    <i class="fas fa-shield-alt"></i>
                    <h4>Secure & Reliable</h4>
                    <p>Built with security and reliability in mind, ensuring data protection and system stability.</p>
                </div>
            </div>
            
            <!-- Mission Section -->
            <div class="mission-section">
                <h3><i class="fas fa-bullseye me-2"></i>Our Mission</h3>
                <p>
                    To empower communities by providing an efficient, reliable, and user-friendly platform that enhances the management and support of Parent Leaders within the 4P's program, ultimately contributing to the improvement of family welfare and community development across the Philippines.
                </p>
            </div>
            
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>