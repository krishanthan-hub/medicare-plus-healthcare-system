<?php
require_once 'config.php';
include 'config/database.php';
include 'includes/header.php';
include 'includes/navigation.php';

// Fetch featured doctors (latest 3)
$featured_doctors = $conn->query("
    SELECT u.user_id, u.full_name, d.specialization, d.experience_years, d.profile_image
    FROM   users u
    JOIN   doctor_profiles d ON u.user_id = d.doctor_id
    WHERE  u.user_type = 'doctor'
    ORDER  BY u.user_id DESC
    LIMIT  3
")->fetch_all(MYSQLI_ASSOC);
?>

<link rel="stylesheet" href="/medicare_plus/assets/css/home.css">

<!-- HERO -->
<section class="hero">
    <div class="container">
        <div style="max-width:640px; margin:0 auto; text-align:center;">
            <h1 class="hero-title mt-2" style="color:#1e3c4f;">
                Your Health, Our Priority
            </h1>
            <p class="hero-subtitle">
                Comprehensive healthcare solutions for you and your family.
                Book appointments, consult expert doctors, and manage your health — all in one place.
            </p>
            <div class="d-flex flex-wrap gap-2 mt-3" style="justify-content:center;">
                <a href="/medicare_plus/patient/book_appointment.php" class="btn btn-lg"
                   style="background:#1778F2; color:white; border:2px solid #1778F2; border-radius:10px;">
                    Book Appointment
                </a>
                <a href="/medicare_plus/pages/services.php" class="btn btn-lg"
                   style="background:#1778F2; color:white; border:2px solid #1778F2; border-radius:10px;">
                    Explore Services
                </a>
            </div>
        </div>
    </div>
</section>

<!-- SERVICES -->
<section style="background:var(--light); padding:5rem 0;">
    <div class="container">
        <div class="text-center mb-4">
            <h2 class="section-title mt-2">Our Services</h2>
            <p class="section-subtitle">
                From expert consultations to seamless booking and secure health records,
                everything you need in one place.
            </p>
        </div>
        <div class="services-home-grid">
            <div class="service-home-card">
                <h4>Cardiology</h4>
                <p style="margin-top:.5rem; font-size:.88rem;">Comprehensive heart care including ECG, echocardiography, and cardiac catheterization and more.</p>
            </div>
            <div class="service-home-card">
                <h4>Pediatrics</h4>
                <p style="margin-top:.5rem; font-size:.88rem;">Complete healthcare for infants, children, and adolescents with a focus on growth and development.</p>
            </div>
            <div class="service-home-card">
                <h4>Orthopedics</h4>
                <p style="margin-top:.5rem; font-size:.88rem;">Expert care for bones, joints, ligaments, and muscles from fractures to arthritis management.</p>
            </div>
            <div class="service-home-card">
                <h4>Radiology</h4>
                <p style="margin-top:.5rem; font-size:.88rem;">Advanced imaging services for accurate diagnosis and treatment planning.</p>
            </div>
        </div>
        <div style="text-align:center;">
            <a href="/medicare_plus/pages/services.php" class="btn-load-more">Load More -></a>
        </div>
    </div>
</section>

<!-- STATS -->
<section class="stats-section">
    <div class="container">
        <div class="text-center mb-4" style="margin-bottom: 2rem;">
            <h2 style="font-size: 2rem; font-weight: 800; color: #1e3c4f; margin-bottom: .5rem;">Why Choose MediCare Plus?</h2>
            <p style="color: #6c757d; font-size: .97rem;">Trusted by thousands of patients across Sri Lanka for quality healthcare.</p>
        </div>
        <div class="stats-home-grid">
            <div class="stat-card">
                <div class="stat-value">150+</div>
                <div class="stat-label">Expert Doctors</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">5,000+</div>
                <div class="stat-label">Patients Served</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">24/7</div>
                <div class="stat-label">Emergency Care</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">15+</div>
                <div class="stat-label">Years Experience</div>
            </div>
        </div>
    </div>
</section>

<!-- TESTIMONIALS -->
<section class="testimonials-section">
    <div class="container">
        <div class="text-center">
            <h2 class="section-title mt-2">What Our Patients Say</h2>
            <p class="section-subtitle">
                Read what our patients have to say about their experience with our healthcare services and medical professionals.
            </p>
        </div>
        <div class="testimonials-grid">
            <div class="testimonial-card">
                <p class="testimonial-text">The care I received at MediCare Plus was exceptional. The doctor took the time to explain everything and made me feel comfortable throughout my treatment.</p>
                <div class="testimonial-author">
                    <div>
                        <div class="testimonial-author-name">Dilshi Perera</div>
                        <div class="testimonial-author-role">Patient</div>
                    </div>
                </div>
            </div>
            <div class="testimonial-card">
                <p class="testimonial-text">I've been a patient here for years and have always received top-notch care. The staff is friendly, professional, and the facilities are modern and clean.</p>
                <div class="testimonial-author">
                    <div>
                        <div class="testimonial-author-name">Supun Fernando</div>
                        <div class="testimonial-author-role">Patient</div>
                    </div>
                </div>
            </div>
            <div class="testimonial-card">
                <p class="testimonial-text">The online booking system made it so easy to schedule my appointment. The wait time was minimal and the doctor was incredibly thorough with my checkup.</p>
                <div class="testimonial-author">
                    <div>
                        <div class="testimonial-author-name">De Silva</div>
                        <div class="testimonial-author-role">Patient</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA BANNER -->
<section class="cta-section">
    <div class="container text-center">
        <h2>Ready to Get Started?</h2>
        <p>Join thousands of patients who trust MediCare Plus for their healthcare needs.</p>
        <div class="d-flex justify-center flex-wrap gap-2">
            <a href="/medicare_plus/pages/register.php" class="btn btn-lg btn-cta">Create Account</a>
            <a href="/medicare_plus/pages/contact.php"  class="btn btn-lg btn-cta">Contact Us</a>
        </div>
    </div>
</section>

<script src="/medicare_plus/assets/js/home.js"></script>
<?php include 'includes/footer.php'; ?>