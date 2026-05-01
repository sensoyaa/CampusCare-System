<?php
// Base URL for the project
$projectBaseUrl = "/campuscare-api";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SWEU | Bukidnon State University</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #294c7b; /* BukSU Blue */
            --primary-dark: #1d3658;
            --secondary: #eab308; /* BukSU Gold/Yellow Accent */
            --bg-color: #f3f6fa;
            --text-main: #1e2f40;
            --text-muted: #5e6f80;
            --card-bg: #ffffff;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Poppins", sans-serif;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            line-height: 1.6;
            scroll-behavior: smooth;
        }

        header {
            background: var(--card-bg);
            padding: 15px 5%;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo-imgs {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-imgs img {
            height: 50px;
            width: auto;
            object-fit: contain;
        }

        .logo-text {
            display: flex;
            flex-direction: column;
            line-height: 1.1;
        }

        .logo-text .title {
            font-size: 20px;
            font-weight: 800;
            color: var(--primary);
            text-transform: uppercase;
        }

        .logo-text .subtitle {
            color: var(--text-muted);
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .nav-links {
            display: flex;
            gap: 25px;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text-main);
            font-weight: 500;
            font-size: 15px;
            transition: var(--transition);
        }

        .nav-links a:hover {
            color: var(--primary);
        }

        .btn {
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
            display: inline-block;
        }

        .btn-outline {
            border: 2px solid var(--primary);
            color: var(--primary);
            background: transparent;
        }

        .btn-outline:hover {
            background: var(--primary);
            color: white;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 10px rgba(41, 76, 123, 0.3);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        /* Hero Section */
        .hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 80px 5% 60px;
            gap: 40px;
            background: linear-gradient(135deg, rgba(243,246,250,1) 0%, rgba(225,234,244,1) 100%);
        }

        .hero-content {
            flex: 1;
            max-width: 650px;
        }

        .hero-tag {
            color: var(--primary);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-size: 14px;
            margin-bottom: 10px;
            display: block;
        }

        .hero h1 {
            font-size: 56px;
            line-height: 1.15;
            margin-bottom: 20px;
            color: var(--text-main);
            font-weight: 800;
        }

        .hero h1 span {
            color: var(--primary);
        }

        .hero p {
            font-size: 17px;
            color: var(--text-muted);
            margin-bottom: 30px;
            max-width: 580px;
        }

        .hero-actions {
            display: flex;
            gap: 15px;
        }

        .hero-image {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .hero-image img {
            max-width: 100%;
            height: auto;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.08);
            animation: float 6s ease-in-out infinite;
        }

        /* Core Values Section (Mission/Vision) */
        .about {
            padding: 80px 5%;
            background: var(--card-bg);
            text-align: center;
        }
        
        .about-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }

        .about-card {
            padding: 40px 30px;
            background: var(--bg-color);
            border-radius: 16px;
            border-top: 4px solid var(--secondary);
            box-shadow: 0 10px 20px rgba(0,0,0,0.03);
            text-align: left;
        }

        .about-card h3 {
            font-size: 22px;
            color: var(--primary);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .about-card p {
            color: var(--text-muted);
            font-size: 15px;
        }

        /* Features/Services Section */
        .features {
            padding: 80px 5%;
            background: var(--bg-color);
            text-align: center;
        }

        .features-header {
            margin-bottom: 50px;
        }

        .features-header h2 {
            font-size: 36px;
            color: var(--text-main);
            margin-bottom: 15px;
        }

        .features-header p {
            color: var(--text-muted);
            max-width: 700px;
            margin: 0 auto;
            font-size: 16px;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            text-align: left;
        }

        .feature-card {
            padding: 40px 30px;
            background: var(--card-bg);
            border-radius: 16px;
            transition: var(--transition);
            box-shadow: 0 5px 15px rgba(0,0,0,0.03);
            position: relative;
            overflow: hidden;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.08);
        }

        .feature-icon {
            width: 60px;
            height: 60px;
            background: rgba(41, 76, 123, 0.1);
            color: var(--primary);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            font-size: 28px;
        }

        .feature-icon svg {
            width: 30px;
            height: 30px;
        }

        .feature-card h3 {
            font-size: 20px;
            margin-bottom: 12px;
            color: var(--text-main);
        }

        .feature-card p {
            color: var(--text-muted);
            font-size: 14.5px;
            line-height: 1.6;
        }

        .service-list {
            margin-top: 15px;
            list-style: none;
            padding-left: 0;
            font-size: 14px;
            color: var(--text-muted);
        }
        .service-list li {
            position: relative;
            padding-left: 20px;
            margin-bottom: 6px;
        }
        .service-list li::before {
            content: "•";
            position: absolute;
            left: 0;
            color: var(--secondary);
            font-weight: bold;
            font-size: 18px;
            line-height: 1;
        }

        /* Footer */
        footer {
            background: var(--primary-dark);
            color: white;
            padding: 60px 5% 30px;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            margin-bottom: 40px;
        }

        .footer-col h4 {
            font-size: 20px;
            margin-bottom: 20px;
            color: #fff;
        }

        .footer-col p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 15px;
            margin-bottom: 10px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        
        .footer-col p svg {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
            margin-top: 3px;
        }

        .footer-col a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: var(--transition);
        }

        .footer-col a:hover {
            color: var(--secondary);
        }

        .footer-bottom {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.6);
            font-size: 14px;
        }

        /* Reach Out / Team Section */
        .team-section {
            padding: 80px 5%;
            background: var(--card-bg);
            text-align: center;
        }

        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-top: 40px;
        }

        .team-member {
            background: var(--bg-color);
            padding: 25px 20px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 20px;
            text-align: left;
            box-shadow: 0 4px 10px rgba(0,0,0,0.03);
            border: 1px solid rgba(0,0,0,0.02);
            transition: var(--transition);
        }

        .team-member:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(41, 76, 123, 0.08);
            border-color: rgba(41, 76, 123, 0.2);
        }

        .team-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 24px;
            font-weight: 700;
            border: 2px solid var(--secondary);
            flex-shrink: 0;
            overflow: hidden;
        }

        .team-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .team-info h4 {
            color: var(--primary-dark);
            font-size: 16px;
            margin-bottom: 2px;
            line-height: 1.3;
        }

        .team-info p {
            color: var(--text-muted);
            font-size: 13px;
            margin: 0;
            line-height: 1.4;
        }

        .peer-facilitators {
            grid-column: 1 / -1;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            text-align: center;
            padding: 30px;
            border-radius: 12px;
            margin-top: 15px;
        }

        .peer-facilitators h4 {
            font-size: 20px;
            margin-bottom: 5px;
            color: white;
        }

        .peer-facilitators p {
            font-size: 14px;
            color: rgba(255,255,255,0.8);
        }

        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
            100% { transform: translateY(0px); }
        }

        @media (max-width: 900px) {
            .hero {
                flex-direction: column;
                text-align: center;
                padding-top: 40px;
            }
            .hero h1 {
                font-size: 42px;
            }
            .hero-content {
                margin: 0 auto;
            }
            .hero p {
                margin-left: auto;
                margin-right: auto;
            }
            .hero-actions {
                justify-content: center;
            }
            .nav-links {
                display: none;
            }
        }
    </style>
</head>
<body>

    <header>
        <div class="logo">
            <div class="logo-imgs">
                <!-- Replace with proper paths if images move. Assumes images are in php-frontend/assets/images/ -->
                <img src="<?php echo $projectBaseUrl; ?>/php-frontend/assets/images/buksulogo.png" alt="BukSU Logo">
                <img src="<?php echo $projectBaseUrl; ?>/php-frontend/assets/images/sweulogo.jpg" alt="SWEU Logo">
            </div>
            <div class="logo-text">
                <div class="title">CampusCare</div>
                <div class="subtitle">SWEU &bull; BukSU</div>
            </div>
        </div>
        <nav class="nav-links">
            <a href="#about">About</a>
            <a href="#services">Services</a>
            <a href="#team">Reach Out</a>
            <a href="#contact">Contact</a>
            <a href="<?php echo $projectBaseUrl; ?>/php-frontend/pages/auth/index.php" class="btn btn-outline">Portal Login</a>
        </nav>
    </header>

    <section class="hero">
        <div class="hero-content">
            <span class="hero-tag">Bukidnon State University</span>
            <h1>Student Welfare & <span>Engagement Unit</span></h1>
            <p>Welcome to SWEU, a vital component of the University's student services dedicated to ensuring the overall well-being and engagement of students through academic, personal, and social support.</p>
            <div class="hero-actions">
                <a href="<?php echo $projectBaseUrl; ?>/php-frontend/pages/auth/index.php" class="btn btn-primary">Access CampusCare</a>
                <a href="#services" class="btn btn-outline">Explore Services</a>
            </div>
        </div>
        <div class="hero-image">
            <img src="<?php echo $projectBaseUrl; ?>/php-frontend/assets/images/LoginCover.png" alt="Student Wellness">
        </div>
    </section>

    <section id="about" class="about">
        <div class="features-header">
            <h2>Our Core Principles</h2>
            <p>We provide a range of services and programs that cater to the diverse needs of students.</p>
        </div>
        <div class="about-grid">
            <div class="about-card">
                <h3>
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                    Vision
                </h3>
                <p>The guidance center envisions to develop well-adjusted individuals through quality guidance programs and services.</p>
            </div>
            <div class="about-card">
                <h3>
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path><line x1="4" y1="22" x2="4" y2="15"></line></svg>
                    Mission
                </h3>
                <p>It is our mission to provide holistic service to the students to prepare them to become mature individuals who are mentally, emotionally, morally, and socially responsible members of the society.</p>
            </div>
            <div class="about-card">
                <h3>
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                    Goal
                </h3>
                <p>The goal of the guidance and counseling program is to facilitate the student's academic, personal, social and career development.</p>
            </div>
        </div>
    </section>

    <section id="services" class="features">
        <div class="features-header">
            <h2>Services Offered</h2>
            <p>Our multidisciplinary team of specialists are here to ensure that your specific needs are addressed.</p>
        </div>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                </div>
                <h3>1. Info & Orientation</h3>
                <p>Vital to student welfare at BukSU, providing essential resources and activities to help students naturally adjust to university life.</p>
            </div>
            
            <div class="feature-card" style="grid-column: span 2;">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                </div>
                <h3>2. Guidance & Counseling</h3>
                <p>We provide a confidential space to address personal and psychological challenges, facilitating positive change.</p>
                <ul class="service-list" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 20px;">
                    <li><strong>Counseling:</strong> Direct emotional support space.</li>
                    <li><strong>Psychological Testing:</strong> Data collection to understand strengths & potential.</li>
                    <li><strong>Follow-up Services:</strong> Systematic monitoring post-counseling.</li>
                    <li><strong>Referral Services:</strong> Linking students needing specialized help to professionals.</li>
                </ul>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                </div>
                <h3>3. Career & Placement</h3>
                <p>Assistance provided for vocational and occupational fitness. Our resources aim to help individuals in their pursuit of suitable employment opportunities.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                </div>
                <h3>4. Economic Enterprise</h3>
                <p>Programs catering to the economic needs of students, including student cooperatives, entrepreneurial projects, income-generating plans, and savings.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                </div>
                <h3>5. Handbook Development</h3>
                <p>Developing the crucial document outlining rights, responsibilities, and procedures—serving as a comprehensive guide to positive campus life.</p>
            </div>
        </div>
    </section>

    <section id="team" class="team-section">
        <div class="features-header">
            <h2>Reach Out To Us</h2>
            <p>Our dedicated team of guidance counselors, psychometricians, and administrative staff are here to support your journey.</p>
        </div>
        
        <div class="team-grid">
            <div class="team-member">
                <div class="team-avatar">LA</div>
                <div class="team-info">
                    <h4>Dr. Lora E. Añar, RGC</h4>
                    <p>Director, SWEU</p>
                </div>
            </div>
            <div class="team-member">
                <div class="team-avatar">ED</div>
                <div class="team-info">
                    <h4>Evangeline C. Digamon, RGC</h4>
                    <p>College Guidance Designate</p>
                </div>
            </div>
            <div class="team-member">
                <div class="team-avatar">JC</div>
                <div class="team-info">
                    <h4>Jennylyn B. Castiño, RGC</h4>
                    <p>College Guidance Designate</p>
                </div>
            </div>
            <div class="team-member">
                <div class="team-avatar">GT</div>
                <div class="team-info">
                    <h4>Gloria S. Tuban</h4>
                    <p>College Guidance Designate</p>
                </div>
            </div>
            <div class="team-member">
                <div class="team-avatar">HB</div>
                <div class="team-info">
                    <h4>Hannah D. Bascon</h4>
                    <p>College Guidance Designate</p>
                </div>
            </div>
            <div class="team-member">
                <div class="team-avatar">EB</div>
                <div class="team-info">
                    <h4>Ebbeth Joy H. Bercero, RPm</h4>
                    <p>College Guidance Designate</p>
                </div>
            </div>
            <div class="team-member">
                <div class="team-avatar">HC</div>
                <div class="team-info">
                    <h4>Hananeel Jay A. Cabiling, RPm</h4>
                    <p>College Guidance Designate</p>
                </div>
            </div>
            <div class="team-member">
                <div class="team-avatar">SD</div>
                <div class="team-info">
                    <h4>Saint Mary Flordeliz B. Dampal, RPm</h4>
                    <p>Psychometrician</p>
                </div>
            </div>
            <div class="team-member">
                <div class="team-avatar">MM</div>
                <div class="team-info">
                    <h4>Mae Ann S. Mepiesa, RPm</h4>
                    <p>Psychometrician</p>
                </div>
            </div>
            <div class="team-member">
                <div class="team-avatar">SS</div>
                <div class="team-info">
                    <h4>Sunshine Kay G. Sicalan, RPm</h4>
                    <p>Administrative Aide III</p>
                </div>
            </div>
            
            <div class="team-member peer-facilitators">
                <div class="team-info" style="text-align: center; width: 100%;">
                    <h4>Student Peer Facilitators</h4>
                    <p>Connecting students through peer-to-peer guidance and advocacy.</p>
                </div>
            </div>
        </div>
    </section>

    <footer id="contact">
        <div class="footer-grid">
            <div class="footer-col">
                <h4>Bukidnon State University</h4>
                <p>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                    Fortich Street, Malaybalay City, Bukidnon
                </p>
            </div>
            <div class="footer-col">
                <h4>Contact SWEU</h4>
                <p>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                    <a href="mailto:guidancecenter@buksu.edu.ph">guidancecenter@buksu.edu.ph</a>
                </p>
                <p>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                    <a href="mailto:testingcenter@buksu.edu.ph">testingcenter@buksu.edu.ph</a>
                </p>
            </div>
            <div class="footer-col">
                <h4>Connect With Us</h4>
                <p>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg>
                    <a href="https://www.facebook.com/profile.php?id=100064439169420" target="_blank">BukSU - University Guidance Center</a>
                </p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date("Y"); ?> Student Welfare & Engagement Unit (SWEU) - Bukidnon State University. All rights reserved.</p>
        </div>
    </footer>

</body>
</html>
