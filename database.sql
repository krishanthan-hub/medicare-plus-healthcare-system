-- MediCare Plus Database Schema
-- Run setup_database.php once to initialize

CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    user_type ENUM('admin','doctor','patient') NOT NULL DEFAULT 'patient',
    profile_image VARCHAR(255) DEFAULT NULL,
    last_login DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS doctor_profiles (
    profile_id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    specialization VARCHAR(100) NOT NULL,
    qualification VARCHAR(255),
    experience_years INT DEFAULT 0,
    consultation_fee DECIMAL(10,2) DEFAULT 0.00,
    about TEXT,
    profile_image VARCHAR(255) DEFAULT NULL,
    available_days VARCHAR(100) DEFAULT 'Monday,Tuesday,Wednesday,Thursday,Friday',
    available_time_start TIME DEFAULT '09:00:00',
    available_time_end TIME DEFAULT '17:00:00',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS patient_profiles (
    profile_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    date_of_birth DATE DEFAULT NULL,
    gender ENUM('male','female','other') DEFAULT NULL,
    blood_group VARCHAR(5) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    emergency_contact VARCHAR(20) DEFAULT NULL,
    allergies TEXT DEFAULT NULL,
    medical_history TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS appointments (
    appointment_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    symptoms TEXT,
    status ENUM('pending','confirmed','completed','cancelled') DEFAULT 'pending',
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS medical_records (
    record_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_id INT DEFAULT NULL,
    record_type ENUM('prescription','lab_report','diagnosis','visit_summary') NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file_path VARCHAR(255) DEFAULT NULL,
    record_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS reviews (
    review_id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    patient_id INT NOT NULL,
    appointment_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    review_text TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS services (
    service_id INT AUTO_INCREMENT PRIMARY KEY,
    service_name VARCHAR(100) NOT NULL,
    description TEXT,
    icon_class VARCHAR(50) DEFAULT 'fa-hospital',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS doctor_schedules (
    schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    day_of_week ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    slot_duration INT DEFAULT 30,
    is_available TINYINT(1) DEFAULT 1,
    FOREIGN KEY (doctor_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Default admin user (password: password)
INSERT IGNORE INTO users (full_name, email, password, phone, user_type)
VALUES ('Admin', 'admin@medicareplus.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+94112345678', 'admin');

-- Sample services
INSERT IGNORE INTO services (service_name, description, icon_class) VALUES
('Cardiology', 'Expert heart care and cardiovascular treatments', 'fa-heartbeat'),
('Neurology', 'Comprehensive neurological diagnosis and treatment', 'fa-brain'),
('Orthopedics', 'Bone, joint and muscle care', 'fa-bone'),
('Pediatrics', 'Dedicated healthcare for children', 'fa-baby'),
('Dermatology', 'Skin care and treatment', 'fa-allergies'),
('General Medicine', 'Primary healthcare and general consultations', 'fa-stethoscope');

-- ── Insert doctor users ────────────────────────────────────────
INSERT IGNORE INTO users (full_name, email, password, phone, user_type) VALUES
('Ashan Perera', 'ashan@medicareplus.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0771234567', 'doctor'),
('Nimal Fernando', 'nimal@medicareplus.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0772345678', 'doctor'),
('Suresh Bandara', 'suresh@medicareplus.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0773456789', 'doctor'),
('Priya Silva', 'priya@medicareplus.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0774567890', 'doctor'),
('Chamara Wickramasinghe', 'chamara@medicareplus.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0775678901', 'doctor'),
('Kasun Jayawardena', 'kasun@medicareplus.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0776789012', 'doctor'),
('Dilini Rathnayake', 'dilini@medicareplus.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0777890123', 'doctor'),
('Sachini Perera', 'sachini@medicareplus.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0778901234', 'doctor'),
('Roshan Mendis', 'roshan@medicareplus.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0779012345', 'doctor'),
('Tharaka Gunasekara', 'tharaka@medicareplus.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0770123456', 'doctor');

-- ── Insert doctor profiles ─────────────────────────────────────
INSERT IGNORE INTO doctor_profiles (doctor_id, specialization, experience_years, consultation_fee, about) VALUES
((SELECT user_id FROM users WHERE email='ashan@medicareplus.com'), 'Cardiology', 12, 2500.00, 'Specialist in heart care with over 12 years of experience in ECG and cardiac catheterization.'),
((SELECT user_id FROM users WHERE email='nimal@medicareplus.com'), 'Cardiology', 15, 3000.00, 'Expert cardiologist specializing in heart failure management and echocardiography.'),
((SELECT user_id FROM users WHERE email='suresh@medicareplus.com'), 'Cardiology', 8, 2800.00, 'Experienced in stress testing and interventional cardiology procedures.'),
((SELECT user_id FROM users WHERE email='priya@medicareplus.com'), 'Neurology', 10, 3000.00, 'Expert in brain and nervous system disorders including epilepsy and stroke prevention.'),
((SELECT user_id FROM users WHERE email='chamara@medicareplus.com'), 'Neurology', 7, 2800.00, 'Specialist in headache treatment and movement disorders.'),
((SELECT user_id FROM users WHERE email='kasun@medicareplus.com'), 'Pediatrics', 9, 2000.00, 'Dedicated to the health of infants, children and adolescents with focus on growth and development.'),
((SELECT user_id FROM users WHERE email='dilini@medicareplus.com'), 'Orthopedics', 14, 3500.00, 'Specialist in joint replacement, sports medicine and fracture care.'),
((SELECT user_id FROM users WHERE email='sachini@medicareplus.com'), 'Dermatology', 6, 2200.00, 'Expert in skin cancer screening, acne treatment and cosmetic procedures.'),
((SELECT user_id FROM users WHERE email='roshan@medicareplus.com'), 'Dermatology', 8, 2400.00, 'Specialist in laser therapy and medical dermatology treatments.'),
((SELECT user_id FROM users WHERE email='tharaka@medicareplus.com'), 'Radiology', 11, 2800.00, 'Expert in diagnostic imaging including X-ray, MRI, CT scan and ultrasound.');