<?php
session_start();
include '../config/database.php';
include '../includes/header.php';
include '../includes/navigation.php';
?>

<style>
    .services-page {
        background: #f0f4f8;
        min-height: 100vh;
    }

    .services-hero {
    background:
        linear-gradient(rgba(240,249,250,0.75), rgba(230,247,248,0.75)),
        url('/medicare_plus/assets/images/Services_BG.png');
    background-size: cover;
    background-position: center;
    text-align: center;
    padding: 5rem 2rem 3rem;
}

    .services-hero .badge {
        display: inline-block;
        background: #e8f7f8;
        color: #1a9ca6;
        padding: 0.35rem 1rem;
        border-radius: 20px;
        font-size: 0.88rem;
        font-weight: 600;
        margin-bottom: 1.25rem;
        border: 1px solid #c8eef0;
    }

    .services-hero h1 {
        font-size: 2.8rem;
        font-weight: 800;
        color: #1e3c4f;
        margin-bottom: 1rem;
        line-height: 1.2;
    }

    .services-hero p {
        color: #6c757d;
        font-size: 1rem;
        max-width: 600px;
        margin: 0 auto;
        line-height: 1.7;
    }

    .services-section {
        padding: 3rem 0;
    }

    .services-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1.5rem;
    }

    @media (max-width: 768px) {
        .services-grid { grid-template-columns: 1fr; }
        .services-hero h1 { font-size: 2rem; }
    }

    .service-card {
        background: white;
        border-radius: 0px;
        padding: 2rem 1.5rem;
        box-shadow: 0 2px 12px rgba(0,0,0,0.05);
        border: 1.5px solid #e0e0e0;
    }

    .service-card h3 {
        font-size: 1.2rem;
        font-weight: 700;
        color: #1e3c4f;
        margin-bottom: 0.4rem;
    }

    .service-card > p {
        color: #6c757d;
        font-size: 0.88rem;
        line-height: 1.6;
        margin: 0 0 1rem;
    }

    .service-features {
        list-style: none;
        padding: 0;
        margin: 0 0 1.25rem;
    }

    .service-features li {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        color: #444;
        font-size: 0.9rem;
        margin-bottom: 0.6rem;
    }

    .service-features li i {
        color: #1778F2;
        font-size: 0.85rem;
        flex-shrink: 0;
    }

    .btn-book-service {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.6rem 1.25rem;
        border: 1.5px solid #1778F2;
        border-radius: 8px;
        background: white;
        color: #1778F2;
        font-size: 0.88rem;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.3s;
        cursor: pointer;
    }

    .btn-book-service:hover {
        border-color: #1060c9;
        color: white;
        background: #1778F2;
    }

    .btn-book-service i { color: #1778F2; }
</style>

<div class="services-page">

    <!-- Hero -->
    <div class="services-hero">
        <h1>Comprehensive Healthcare<br>Services</h1>
        <p>We offer a wide range of medical services designed to meet all your healthcare needs. From preventive care to specialized treatments, we're here for you.</p>
    </div>

    <!-- Services Cards -->
    <div class="services-section">
        <div class="container">
            <div class="services-grid">

                <!-- Cardiology -->
                <div class="service-card">
                    <h3>Cardiology</h3>
                    <p>Comprehensive heart care including ECG, echocardiography, and cardiac catheterization.</p>
                    <ul class="service-features">
                        <li><i class="fas fa-check"></i> ECG &amp; Stress Testing</li>
                        <li><i class="fas fa-check"></i> Echocardiography</li>
                        <li><i class="fas fa-check"></i> Cardiac Catheterization</li>
                        <li><i class="fas fa-check"></i> Heart Failure Management</li>
                    </ul>
                    <a href="/medicare_plus/patient/book_appointment.php" class="btn-book-service">
                        <i class="fas fa-calendar-alt"></i> Book Appointment
                    </a>
                </div>

                <!-- Pediatrics -->
                <div class="service-card">
                    <h3>Pediatrics</h3>
                    <p>Complete healthcare for infants, children, and adolescents with a focus on growth and development.</p>
                    <ul class="service-features">
                        <li><i class="fas fa-check"></i> Well-Child Visits</li>
                        <li><i class="fas fa-check"></i> Vaccinations</li>
                        <li><i class="fas fa-check"></i> Growth Monitoring</li>
                        <li><i class="fas fa-check"></i> Developmental Assessments</li>
                    </ul>
                    <a href="/medicare_plus/patient/book_appointment.php" class="btn-book-service">
                        <i class="fas fa-calendar-alt"></i> Book Appointment
                    </a>
                </div>

                <!-- Radiology -->
                <div class="service-card">
                    <h3>Radiology</h3>
                    <p>State-of-the-art diagnostic imaging services including X-ray, MRI, CT scan, and ultrasound.</p>
                    <ul class="service-features">
                        <li><i class="fas fa-check"></i> X-Ray Imaging</li>
                        <li><i class="fas fa-check"></i> MRI Scans</li>
                        <li><i class="fas fa-check"></i> CT Scans</li>
                        <li><i class="fas fa-check"></i> Ultrasound</li>
                    </ul>
                    <a href="/medicare_plus/patient/book_appointment.php" class="btn-book-service">
                        <i class="fas fa-calendar-alt"></i> Book Appointment
                    </a>
                </div>

                <!-- Orthopedics -->
                <div class="service-card">
                    <h3>Orthopedics</h3>
                    <p>Expert care for bones, joints, and muscles including surgery and rehabilitation.</p>
                    <ul class="service-features">
                        <li><i class="fas fa-check"></i> Joint Replacement</li>
                        <li><i class="fas fa-check"></i> Sports Medicine</li>
                        <li><i class="fas fa-check"></i> Fracture Care</li>
                        <li><i class="fas fa-check"></i> Physical Therapy</li>
                    </ul>
                    <a href="/medicare_plus/patient/book_appointment.php" class="btn-book-service">
                        <i class="fas fa-calendar-alt"></i> Book Appointment
                    </a>
                </div>

                <!-- Dermatology -->
                <div class="service-card">
                    <h3>Dermatology</h3>
                    <p>Medical and cosmetic skin care including treatment for skin conditions and aesthetic procedures.</p>
                    <ul class="service-features">
                        <li><i class="fas fa-check"></i> Skin Cancer Screening</li>
                        <li><i class="fas fa-check"></i> Acne Treatment</li>
                        <li><i class="fas fa-check"></i> Cosmetic Procedures</li>
                        <li><i class="fas fa-check"></i> Laser Therapy</li>
                    </ul>
                    <a href="/medicare_plus/patient/book_appointment.php" class="btn-book-service">
                        <i class="fas fa-calendar-alt"></i> Book Appointment
                    </a>
                </div>

                <!-- Neurology -->
                <div class="service-card">
                    <h3>Neurology</h3>
                    <p>Diagnosis and treatment of disorders affecting the brain, spinal cord, and nervous system.</p>
                    <ul class="service-features">
                        <li><i class="fas fa-check"></i> Headache Treatment</li>
                        <li><i class="fas fa-check"></i> Epilepsy Care</li>
                        <li><i class="fas fa-check"></i> Stroke Prevention</li>
                        <li><i class="fas fa-check"></i> Movement Disorders</li>
                    </ul>
                    <a href="/medicare_plus/patient/book_appointment.php" class="btn-book-service">
                        <i class="fas fa-calendar-alt"></i> Book Appointment
                    </a>
                </div>

                <!-- General Medicine -->
                <div class="service-card">
                    <h3>General Medicine</h3>
                    <p>Primary healthcare services for general health concerns and preventive care.</p>
                    <ul class="service-features">
                        <li><i class="fas fa-check"></i> Annual Checkups</li>
                        <li><i class="fas fa-check"></i> Chronic Disease Management</li>
                        <li><i class="fas fa-check"></i> Preventive Care</li>
                        <li><i class="fas fa-check"></i> Health Screenings</li>
                    </ul>
                    <a href="/medicare_plus/patient/book_appointment.php" class="btn-book-service">
                        <i class="fas fa-calendar-alt"></i> Book Appointment
                    </a>
                </div>

                <!-- Emergency Care -->
                <div class="service-card">
                    <h3>Emergency Care</h3>
                    <p>24/7 emergency medical services with rapid response and critical care capabilities.</p>
                    <ul class="service-features">
                        <li><i class="fas fa-check"></i> 24/7 Availability</li>
                        <li><i class="fas fa-check"></i> Trauma Care</li>
                        <li><i class="fas fa-check"></i> Critical Care</li>
                        <li><i class="fas fa-check"></i> Rapid Response</li>
                    </ul>
                    <a href="/medicare_plus/pages/contact.php" class="btn-book-service">
                        <i class="fas fa-phone"></i> Contact Us
                    </a>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>