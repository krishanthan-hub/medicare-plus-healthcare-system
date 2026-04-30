<?php
session_start();
include '../config/database.php';
$page_css = 'about.css';
include '../includes/header.php';
include '../includes/navigation.php';

$total_doctors  = $conn->query("SELECT COUNT(*) FROM users WHERE user_type='doctor'")->fetch_row()[0]  ?? 50;
$total_patients = $conn->query("SELECT COUNT(*) FROM users WHERE user_type='patient'")->fetch_row()[0] ?? 1000;
$total_appts    = $conn->query("SELECT COUNT(*) FROM appointments")->fetch_row()[0]                    ?? 5000;
?>

<!-- Hero -->
<section class="about-hero">
    <h1>Caring for You Since 2010</h1>
    <p>MediCare Plus is a leading healthcare provider dedicated to delivering exceptional medical care with compassion, expertise, and innovation. We are committed to improving the health and well-being of every patient we serve.</p>
</section>

<!-- Our Story -->
<section class="about-story">
    <div class="container">
        <div class="story-box">
            <span class="section-badge">Our Story</span>
            <h2>Building a Healthier Community</h2>
            <p>Founded in 2010, MediCare Plus began as a small clinic with a big vision — to make quality healthcare accessible to everyone. Over the years, we have grown into a comprehensive healthcare facility serving thousands of patients across Sri Lanka.</p>
            <p>Our team of over <?php echo $total_doctors; ?> specialist doctors, nurses, and healthcare professionals work tirelessly every day to ensure that every patient receives the highest standard of care in a compassionate and supportive environment.</p>
            <p>From routine checkups to complex surgical procedures, we offer a complete range of medical services under one roof, backed by state-of-the-art technology and evidence-based medicine.</p>
            <div class="story-highlights">
                <div class="highlight-item">
                    <div class="highlight-icon"><i class="fas fa-award"></i></div>
                    <div><h5>Accredited Facility</h5><p>Nationally recognised healthcare standards</p></div>
                </div>
                <div class="highlight-item">
                    <div class="highlight-icon"><i class="fas fa-clock"></i></div>
                    <div><h5>24/7 Emergency</h5><p>Round-the-clock emergency services</p></div>
                </div>
                <div class="highlight-item">
                    <div class="highlight-icon"><i class="fas fa-microscope"></i></div>
                    <div><h5>Modern Equipment</h5><p>Latest diagnostic & treatment technology</p></div>
                </div>
                <div class="highlight-item">
                    <div class="highlight-icon"><i class="fas fa-heart"></i></div>
                    <div><h5>Patient-Centred</h5><p>Your comfort and care is our priority</p></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Mission Vision Goals -->
<section class="mv-section">
    <div class="container">
        <div class="text-center">
            <h2 class="section-title mt-2">Our Mission & Vision</h2>
            <p class="section-subtitle">Every decision we make is guided by our core purpose to improve lives through exceptional healthcare.</p>
        </div>
        <div class="mv-grid" style="margin-top:2.5rem;">
            <div class="mv-card">
                <h3>Our Mission</h3>
                <p>To provide accessible, compassionate, and high-quality healthcare services that improve the health and well-being of individuals and communities. We are committed to treating every patient with dignity, respect, and excellence.</p>
            </div>
            <div class="mv-card">
                <h3>Our Vision</h3>
                <p>To be the most trusted and innovative healthcare provider in Sri Lanka, setting the benchmark for patient care, medical excellence, and community health outcomes through continuous improvement and technology-driven solutions.</p>
            </div>
            <div class="mv-card">
                <h3>Our Goals</h3>
                <p>To expand access to specialist care, invest in the latest medical technologies, develop our healthcare professionals through continuous education, and build a healthier, stronger community through preventive care and education.</p>
            </div>
        </div>
    </div>
</section>

<!-- Leadership Team -->
<section class="team-section">
    <div class="container">
        <div class="text-center">
            <h2 class="section-title mt-2">Meet Our Team</h2>
            <p class="section-subtitle">Led by experienced professionals dedicated to healthcare excellence and innovation.</p>
        </div>
        <div class="team-grid">
            <div class="team-card">
                <div class="team-card-img">
                    <img src="/medicare_plus/assets/images/team/Dr_Ashan.png" alt="Dr. Ashan Perera">
                </div>
                <div class="team-card-body">
                    <h4>Dr. Ashan Perera</h4>
                    <p>Chief Medical Officer</p>
                </div>
            </div>
            <div class="team-card">
                <div class="team-card-img">
                    <img src="/medicare_plus/assets/images/team/Dr_Nimal.png" alt="Dr. Nimal Fernando">
                </div>
                <div class="team-card-body">
                    <h4>Dr. Nimal Fernando</h4>
                    <p>Head of Cardiology</p>
                </div>
            </div>
            <div class="team-card">
                <div class="team-card-img">
                    <img src="/medicare_plus/assets/images/team/Dr_Priya.png" alt="Dr. Priya Silva">
                </div>
                <div class="team-card-body">
                    <h4>Dr. Priya Silva</h4>
                    <p>Head of Pediatrics</p>
                </div>
            </div>
            <div class="team-card">
                <div class="team-card-img">
                    <img src="/medicare_plus/assets/images/team/Dr_Kasun.png" alt="Mr. Kasun Jayawardena">
                </div>
                <div class="team-card-body">
                    <h4>Mr. Kasun Jayawardena</h4>
                    <p>Chief Executive Officer</p>
                </div>
            </div>
        </div>

        <div style="text-align:center; margin-top:2.5rem;">
            <a href="/medicare_plus/pages/doctors.php" class="btn-view-doctors">View All Doctors</a>
        </div>
    </div>
</section>

<script src="/medicare_plus/assets/js/GL_about_us.js"></script>
<?php include '../includes/footer.php'; ?>