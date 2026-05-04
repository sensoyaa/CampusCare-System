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
    <link rel="icon" type="image/png" href="<?php echo $projectBaseUrl; ?>/php-frontend/assets/images/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0f4a86;
            --primary-dark: #0c3a6a;
            --bg-soft: #eef4fb;
            --text-main: #121b2b;
            --text-muted: #4f5f72;
            --card-bg: #ffffff;
            --transition: all 0.25s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Poppins", sans-serif;
        }

        body {
            background: #ffffff;
            color: var(--text-main);
            line-height: 1.5;
            scroll-behavior: smooth;
        }

        header {
            background: #ffffff;
            padding: 14px 200px;
            border-bottom: 1px solid #dfe6ef;
            box-shadow: 0 1px 6px rgba(16, 41, 74, 0.06);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-imgs {
            display: flex;
            align-items: center;
            gap: 0;
        }

        .logo-imgs img {
            height: 46px;
            width: auto;
            object-fit: contain;
        }

        .logo-imgs img:nth-child(2) {
            display: block;
            width: 46px;
            height: 46px;
            object-fit: cover;
            border-radius: 100%;
        }

        .logo-text {
            display: flex;
            flex-direction: column;
            line-height: 1.15;
        }

        .logo-text .title {
            font-size: 18px;
            font-weight: 700;
            color: #123f6b;
            letter-spacing: -0.2px;
            text-transform: none;
        }

        .logo-text .subtitle {
            font-size: 11px;
            color: #4c647b;
            font-weight: 500;
            text-transform: none;
            letter-spacing: 0;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 34px;
        }

        .nav-links a {
            text-decoration: none;
            color: #1d2430;
            font-size: 15px;
            font-weight: 500;
            transition: var(--transition);
        }

        .nav-links a:hover {
            color: var(--primary);
        }

        .btn {
            border-radius: 12px;
            padding: 12px 22px;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: var(--transition);
        }

        .btn-outline {
            background: linear-gradient(180deg, #185290 0%, #0f467f 100%);
            color: #ffffff;
            border: none;
            box-shadow: 0 4px 10px rgba(13, 63, 114, 0.22);
        }

        .btn-outline:hover {
            background: linear-gradient(180deg, #205d9d 0%, #14518c 100%);
            color: #ffffff;
            transform: translateY(-1px);
        }

        .nav-links .btn-outline {
            color: #ffffff;
            border: 1px solid transparent;
        }

        .nav-links .btn-outline:hover {
            background: #ffffff;
            color: var(--primary);
            border-color: var(--primary);
        }

        .btn-primary {
            background: linear-gradient(180deg, #185290 0%, #0f467f 100%);
            color: #ffffff;
            border: none;
            box-shadow: 0 4px 12px rgba(13, 63, 114, 0.24);
        }

        .btn-primary:hover {
            background: linear-gradient(180deg, #205d9d 0%, #14518c 100%);
            transform: translateY(-1px);
        }

        .hero {
            text-align: center;
            padding: 40px 16px 20px;
            background:
                linear-gradient(180deg, rgba(255,255,255,0) 65%, #ffffff 100%),
                url("<?php echo $projectBaseUrl; ?>/php-frontend/assets/images/bg/Landingheader.png") center top / cover no-repeat,
                linear-gradient(90deg, #dbeef8 0%, #fde9ec 100%);
            min-height: 420px;
            display: block;
        }

        .hero-content {
            max-width: 960px;
            margin: 0 auto;
        }

        .hero-tag {
            display: none;
        }

        .hero h1 {
            font-size: clamp(38px, 4.5vw, 58px);
            line-height: 1.18;
            font-weight: 700;
            color: #101722;
            margin-bottom: 16px;
        }

        .hero h1 span {
            color: inherit;
        }

        .hero p {
            font-size: 15px;
            line-height: 1.45;
            max-width: 900px;
            margin: 0 auto 22px;
            color: #182433;
        }

        .hero-actions {
            display: flex;
            justify-content: center;
            gap: 12px;
        }

        .hero-actions .btn-outline {
            display: none;
        }

        .hero-image {
            display: none;
        }

        .about {
            padding: 56px 24px;
            background: #ffffff;
        }

        .about .features-header {
            display: block;
            max-width: 920px;
            margin: 0 auto 28px;
            text-align: center;
        }

        .about .features-header h2 {
            font-size: 34px;
            font-weight: 700;
            color: #142235;
            margin-bottom: 8px;
        }

        .about .features-header p {
            font-size: 14px;
            color: #4f5f72;
        }

        .about-grid {
            max-width: 1140px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .about-card {
            background: #f7fbff;
            border: 1px solid #dce8f5;
            border-radius: 16px;
            padding: 20px 18px;
            box-shadow: 0 6px 16px rgba(24, 58, 102, 0.08);
        }

        .about-card h3 {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 20px;
            color: #123f6b;
            margin-bottom: 8px;
        }

        .about-card p {
            font-size: 13px;
            color: #2a3f57;
            line-height: 1.5;
        }

        .features {
            padding: 10px 24px 44px;
            background: #ffffff;
        }

        .features-header {
            display: none;
        }

        .features-grid {
            max-width: 1140px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
        }

        .feature-card {
            border-radius: 16px;
            border: 1px solid #e6edf5;
            background: #f6fbff;
            box-shadow: 0 8px 24px rgba(29, 49, 83, 0.12);
            padding: 20px 18px 18px;
            text-align: center;
            transition: var(--transition);
            overflow: hidden;
            min-height: 294px;
            position: relative;
        }

        .feature-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 14px 28px rgba(29, 49, 83, 0.16);
        }

        .feature-card:nth-child(1) {
            background: url("<?php echo $projectBaseUrl; ?>/php-frontend/assets/images/bg/containe1bg.png") center/cover no-repeat;
        }

        .feature-card:nth-child(2) {
            background: url("<?php echo $projectBaseUrl; ?>/php-frontend/assets/images/bg/container-2-bg.png") center/cover no-repeat;
        }

        .feature-card:nth-child(3) {
            background: url("<?php echo $projectBaseUrl; ?>/php-frontend/assets/images/bg/bg3.png") center/cover no-repeat;
        }

        .feature-card:nth-child(4) {
            background: url("<?php echo $projectBaseUrl; ?>/php-frontend/assets/images/bg/bg4.png") center/cover no-repeat;
        }

        .feature-card:nth-child(n+5) {
            display: none;
        }

        .feature-icon {
            width: 128px;
            height: 96px;
            margin: 0 auto 8px;
            background-position: center;
            background-repeat: no-repeat;
            background-size: contain;
        }

        .feature-icon svg {
            display: none;
        }

        .feature-card:nth-child(1) .feature-icon {
            background-image: url("<?php echo $projectBaseUrl; ?>/php-frontend/assets/images/icons/counseling.png");
        }

        .feature-card:nth-child(2) .feature-icon {
            background-image: url("<?php echo $projectBaseUrl; ?>/php-frontend/assets/images/icons/workshop.png");
        }

        .feature-card:nth-child(3) .feature-icon {
            background-image: url("<?php echo $projectBaseUrl; ?>/php-frontend/assets/images/icons/Mental-Test.png");
        }

        .feature-card:nth-child(4) .feature-icon {
            background-image: url("<?php echo $projectBaseUrl; ?>/php-frontend/assets/images/icons/sched.png");
        }

        .feature-card h3 {
            font-size: 17px;
            line-height: 1.1;
            margin-bottom: 8px;
            color: #111a29;
            font-weight: 700;
        }

        .feature-card p {
            font-size: 12px;
            line-height: 1.45;
            color: #172434;
            max-width: 260px;
            margin: 0 auto;
        }

        .service-list {
            display: none;
        }

        .team-section {
            padding: 56px 24px;
            background: #f8fbff;
        }

        .team-section .features-header {
            display: block;
            max-width: 920px;
            margin: 0 auto 28px;
            text-align: center;
        }

        .team-section .features-header h2 {
            font-size: 34px;
            font-weight: 700;
            color: #142235;
            margin-bottom: 8px;
        }

        .team-section .features-header p {
            font-size: 14px;
            color: #4f5f72;
        }

        .team-grid {
            max-width: 1140px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .team-member {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px;
            background: #ffffff;
            border: 1px solid #dbe6f3;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(24, 58, 102, 0.06);
            transition: var(--transition);
        }

        .team-member:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(24, 58, 102, 0.11);
        }

        .team-avatar {
            width: 48px;
            height: 48px;
            flex-shrink: 0;
            border-radius: 50%;
            background: #dfeeff;
            border: 1px solid #c6dfff;
            color: #123f6b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 700;
        }

        .team-info h4 {
            font-size: 14px;
            line-height: 1.35;
            color: #18324e;
            margin-bottom: 2px;
        }

        .team-info p {
            font-size: 12px;
            color: #5a6e84;
        }

        .peer-facilitators {
            grid-column: 1 / -1;
            background: linear-gradient(180deg, #185290 0%, #0f467f 100%);
            border-color: #185290;
            color: #ffffff;
            justify-content: center;
            text-align: center;
            padding: 16px;
        }

        .peer-facilitators .team-info h4,
        .peer-facilitators .team-info p {
            color: #ffffff;
            margin: 0;
        }

        footer {
            background: #eaf0f8;
            border-top: 1px solid #d9e1ec;
            padding: 10px 24px;
            color: #172332;
        }

        .footer-grid {
            max-width: 1140px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1.2fr 1fr 1fr 1fr;
            gap: 24px;
            align-items: center;
        }

        .footer-col {
            min-height: 56px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 6px;
        }

        .footer-col h4 {
            font-size: 12px;
            color: #1a2e48;
            font-weight: 700;
            margin: 0 0 6px;
            letter-spacing: 0.2px;
        }

        .footer-col p {
            margin: 0;
            font-size: 12px;
            line-height: 1.35;
            color: #22364d;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .footer-col p svg {
            width: 14px;
            height: 14px;
            flex-shrink: 0;
            color: #4b6078;
        }

        .footer-col a {
            color: #22364d;
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
            margin-right: 10px;
        }

        .footer-col a:hover {
            color: var(--primary);
        }

        .footer-col:last-child {
            align-items: flex-end;
            text-align: right;
        }

        .footer-bottom {
            max-width: 1140px;
            margin: 6px auto 0;
            border-top: 1px solid #d5deea;
            padding-top: 8px;
            text-align: center;
            font-size: 11px;
            color: #3f536d;
        }

        @media (max-width: 1024px) {
            .nav-links {
                gap: 18px;
            }

            .nav-links a {
                font-size: 14px;
            }

            .features-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .footer-grid {
                grid-template-columns: 1fr;
                gap: 14px;
            }

            .footer-col:last-child {
                justify-content: flex-start;
            }

            .about-grid,
            .team-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 768px) {
            header {
                padding: 12px 14px;
            }

            .logo-text .title {
                font-size: 22px;
            }

            .logo-text .subtitle {
                font-size: 8px;
            }

            .nav-links {
                display: none;
            }

            .hero {
                padding: 28px 14px 14px;
                min-height: 340px;
            }

            .hero p {
                font-size: 15px;
            }

            .features {
                padding: 8px 14px 28px;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }

            .about,
            .team-section {
                padding: 40px 14px;
            }

            .about-grid,
            .team-grid {
                grid-template-columns: 1fr;
            }

            .feature-card {
                min-height: 250px;
            }

            .feature-card h3 {
                font-size: 18px;
            }

            .feature-card p {
                font-size: 12px;
            }

            footer {
                padding: 10px 14px;
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
            <a href="#about">About Us</a>
            <a href="#services">Services</a>
            <a href="#team">Workshops</a>
            <a href="#contact">Resources</a>
            <a href="<?php echo $projectBaseUrl; ?>/php-frontend/pages/auth/index.php" class="btn btn-outline">Login to Dashboard</a>
        </nav>
    </header>

    <section class="hero">
        <div class="hero-content">
            <span class="hero-tag">Bukidnon State University</span>
            <h1>Your Path to a Balanced, Supported,<br>and Thriving Campus Life</h1>
            <p>CampusCare provides confidential mental health services, engaging workshops, and campus-wide support, all personalized for students.</p>
            <div class="hero-actions">
                <a href="#services" class="btn btn-primary">Explore Resources</a>
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
                <h3>Personal Counseling</h3>
                <p>Our 1-on-1 counseling services help students navigate stress, anxiety, and personal challenges with compassionate support.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                </div>
                <h3>Growth Workshops</h3>
                <p>Highlights upcoming workshops for resilience, study habits, communication, and personal development.</p>
                <ul class="service-list">
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
                <h3>Mental Health Tools</h3>
                <p>Self-assessments and practical guides to help students understand needs and access timely support.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                </div>
                <h3>Event Calendar</h3>
                <p>Stay updated with key dates, wellness events, consultation schedules, and student support activities.</p>
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
                <h4>WELLNESS TIP</h4>
                <p>"Small steps every day create a stronger, healthier you."</p>
            </div>
            <div class="footer-col">
                <h4>Featured Announcements</h4>
                <p>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                    Wellbeing Fair
                </p>
                <p>Apr 28 - 7AM</p>
            </div>
            <div class="footer-col">
                <h4>Quick Links</h4>
                <p>
                    <a href="#about">About Us</a>
                    <a href="#services">Services</a>
                </p>
                <p>
                    <a href="#team">Workshops</a>
                    <a href="#contact">Resources</a>
                </p>
            </div>
            <div class="footer-col">
                <h4>Connect</h4>
                <p>
                    <a href="https://www.facebook.com/profile.php?id=100064439169420" target="_blank">Facebook</a>
                    <a href="#">Twitter</a>
                    <a href="#">Instagram</a>
                </p>
                <p>
                    <img src="<?php echo $projectBaseUrl; ?>/php-frontend/assets/images/buksulogo.png" alt="CampusCare Logo" style="height: 28px; width: auto;">
                </p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date("Y"); ?> Student Welfare & Engagement Unit (SWEU) - Bukidnon State University. All rights reserved.</p>
        </div>
    </footer>

</body>
</html>
