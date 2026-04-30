<?php
session_start();
include '../config/database.php';
include '../includes/header.php';
include '../includes/navigation.php';

$search         = validate($_GET['search']         ?? '');
$specialization = validate($_GET['specialization'] ?? '');

$where  = "WHERE u.user_type = 'doctor'";
$params = [];
$types  = '';

if (!empty($search)) {
    $where   .= " AND (u.full_name LIKE ? OR d.specialization LIKE ?)";
    $like     = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}
if (!empty($specialization)) {
    $where   .= " AND d.specialization = ?";
    $params[] = $specialization;
    $types   .= 's';
}

$sql = "SELECT u.user_id, u.full_name, d.doctor_id, d.specialization, d.qualification,
               d.experience_years, d.consultation_fee, d.about, d.profile_image,
               d.available_days, d.available_time_start, d.available_time_end,
               (SELECT AVG(rating) FROM reviews WHERE doctor_id = d.doctor_id) AS avg_rating,
               (SELECT COUNT(*) FROM reviews WHERE doctor_id = d.doctor_id) AS review_count
        FROM users u
        JOIN doctor_profiles d ON u.user_id = d.doctor_id
        $where
        ORDER BY u.full_name ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<style>
    .doctors-hero {
        background-image:
            linear-gradient(rgba(30,60,79,0.65), rgba(30,60,79,0.65)),
            url('/medicare_plus/assets/images/Doctors_BG.png');
        background-size: cover;
        background-position: center center;
        background-repeat: no-repeat;
        text-align: center;
        padding: 6rem 1rem 5rem;
    }
    .doctors-hero h1 { font-size: 2.8rem; font-weight: 800; color: white; margin-bottom: 1rem; }
    .doctors-hero p  { color: rgba(255,255,255,0.85); font-size: 1rem; max-width: 620px; margin: 0 auto; line-height: 1.75; }

    .search-section {
        background: #fff;
        padding: 2rem 0;
        border-bottom: 1px solid var(--border);
        box-shadow: 0 2px 8px rgba(0,0,0,.05);
    }
    .search-form {
        display: flex; gap: 1rem; flex-wrap: wrap;
        align-items: center; max-width: 860px; margin: 0 auto;
    }
    .search-input-wrap { position: relative; flex: 1; min-width: 220px; }
    .search-input-wrap i { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #adb5bd; font-size: .95rem; }
    .search-input-wrap input {
        width: 100%; padding: .8rem 1rem .8rem 2.75rem;
        border: 1.5px solid #dee2e6; border-radius: 10px;
        font-size: .92rem; background: #f8f9fa; transition: all .3s;
    }
    .search-input-wrap input:focus { outline: none; border-color: #dee2e6; background: white; box-shadow: none; }

    .search-select-wrap { position: relative; min-width: 210px; }
    .search-select-wrap i { position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); color: #adb5bd; pointer-events: none; }
    .search-select-wrap select {
        width: 100%; padding: .8rem 2.5rem .8rem 1rem;
        border: 1.5px solid #dee2e6; border-radius: 10px;
        font-size: .92rem; background: #f8f9fa; appearance: none; transition: all .3s; color: #333;
    }
    .search-select-wrap select:focus { outline: none; border-color: #1778F2; background: white; box-shadow: 0 0 0 3px rgba(23,120,242,.1); }

    .btn-search {
        padding: .8rem 1.75rem; background: #1778F2; color: white;
        border: none; border-radius: 10px; font-size: .92rem; font-weight: 600;
        cursor: pointer; display: flex; align-items: center; gap: .5rem;
        transition: all .3s; white-space: nowrap;
    }
    .btn-search:hover { background: #1060c9; transform: translateY(-1px); }

    .doctors-section { background: #f4f6f9; padding: 3rem 0 5rem; }
    .doctors-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem; }

    .doc-card {
        background: white; border-radius: 0px; overflow: hidden;
        box-shadow: 0 2px 12px rgba(0,0,0,.06); border: 1.5px solid #e0e0e0;
        display: flex; flex-direction: column;
    }
    .doc-card-img {
        width: 100%; height: 240px; object-fit: cover; object-position: top;
    }
    .doc-card-img-placeholder {
        width: 100%; height: 240px; background: #d0e8ff;
        display: flex; align-items: center; justify-content: center;
    }
    .doc-card-img-placeholder i { font-size: 4rem; color: #1778F2; }

    .doc-card-body { padding: 1.25rem; flex: 1; display: flex; flex-direction: column; }
    .doc-card-body h3 { font-size: 1.05rem; font-weight: 700; color: #1e3c4f; margin-bottom: .15rem; }
    .doc-specialization { font-size: .88rem; color: #1778F2; font-weight: 600; margin-bottom: .5rem; }

    .doc-qualification {
        font-size: .8rem; color: #555;
        margin-bottom: .35rem;
    }
    .doc-experience {
        font-size: .82rem; color: #555;
        margin-bottom: .35rem;
    }
    .doc-fee {
        font-size: .9rem; color: #555;
        font-weight: 700; margin-bottom: .35rem;
    }
    .doc-days {
        font-size: .78rem; color: #888;
        margin-bottom: .35rem;
        line-height: 1.5;
    }
    .doc-about {
        font-size: .8rem; color: #777;
        line-height: 1.55; margin-bottom: .65rem;
        flex: 1;
    }

    .doc-rating { display: flex; align-items: center; gap: .25rem; margin-bottom: .75rem; }
    .doc-rating i { font-size: .78rem; color: #f59e0b; }
    .doc-rating span { font-size: .8rem; color: #adb5bd; margin-left: .25rem; }

    .doc-actions { display: flex; gap: .5rem; margin-top: auto; }
    .btn-book-now {
        flex: 1; padding: .7rem;
        background: #1778F2; color: white;
        border: none; border-radius: 0px;
        font-size: .88rem; font-weight: 600;
        text-align: center; text-decoration: none;
        transition: all .3s;
        display: flex; align-items: center; justify-content: center;
    }
    .btn-book-now:hover { background: #1060c9; color: white; }

    .btn-view-profile {
        flex: 1; padding: .7rem;
        background: white; color: #1778F2;
        border: 1.5px solid #1778F2; border-radius: 8px;
        font-size: .88rem; font-weight: 600;
        text-align: center; text-decoration: none;
        transition: all .3s;
        display: flex; align-items: center; justify-content: center;
    }
    .btn-view-profile:hover { background: #e8f1fd; }

    .no-results { grid-column: 1 / -1; text-align: center; padding: 4rem 2rem; color: #adb5bd; font-size: 1rem; }
    .no-results i { font-size: 3rem; display: block; margin-bottom: 1rem; color: #dee2e6; }

    .results-info { font-size: .9rem; color: #6c757d; margin-bottom: 1.5rem; }
    .results-info strong { color: #1e3c4f; }

    .divider { border: none; border-top: 1px solid #f0f0f0; margin: .65rem 0; }
</style>

<!-- Hero -->
<section class="doctors-hero">
    <h1>Find Your Specialist</h1>
    <p>Our team of experienced doctors is dedicated to providing you with the highest quality of care. Find and book with the right doctor for your needs.</p>
</section>

<!-- Search -->
<div class="search-section">
    <div class="container">
        <form method="GET" class="search-form">
            <div class="search-input-wrap">
                <i class="fas fa-search"></i>
                <input type="text" name="search"
                       placeholder="Search by name or specialization"
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="search-select-wrap">
                <select name="specialization">
                    <option value="">All Specializations</option>
                    <option value="Cardiology"  <?php echo $specialization == 'Cardiology'  ? 'selected' : ''; ?>>Cardiology</option>
                    <option value="Pediatrics"  <?php echo $specialization == 'Pediatrics'  ? 'selected' : ''; ?>>Pediatrics</option>
                    <option value="Radiology"   <?php echo $specialization == 'Radiology'   ? 'selected' : ''; ?>>Radiology</option>
                    <option value="Orthopedics" <?php echo $specialization == 'Orthopedics' ? 'selected' : ''; ?>>Orthopedics</option>
                    <option value="Dermatology" <?php echo $specialization == 'Dermatology' ? 'selected' : ''; ?>>Dermatology</option>
                    <option value="Neurology"   <?php echo $specialization == 'Neurology'   ? 'selected' : ''; ?>>Neurology</option>
                </select>
                <i class="fas fa-chevron-down"></i>
            </div>
            <button type="submit" class="btn-search">
                <i class="fas fa-search"></i> Search
            </button>
            <?php if (!empty($search) || !empty($specialization)): ?>
                <a href="doctors.php" style="color:#adb5bd; font-size:.88rem; white-space:nowrap; text-decoration:none;">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Doctors List -->
<div class="doctors-section">
    <div class="container">
        <?php $count = $result->num_rows; ?>
        <p class="results-info">
            Showing <strong><?php echo $count; ?></strong> doctor<?php echo $count != 1 ? 's' : ''; ?>
            <?php if (!empty($search)): ?>for "<strong><?php echo htmlspecialchars($search); ?></strong>"<?php endif; ?>
            <?php if (!empty($specialization)): ?>in <strong><?php echo htmlspecialchars($specialization); ?></strong><?php endif; ?>
        </p>

        <div class="doctors-grid">
            <?php if ($count > 0): ?>
                <?php while ($doctor = $result->fetch_assoc()): ?>
                <div class="doc-card">

                    <!-- Photo -->
                    <?php if (!empty($doctor['profile_image'])): ?>
                        <img src="/medicare_plus/assets/images/doctors/<?= htmlspecialchars($doctor['profile_image']) ?>"
                             alt="Dr. <?= htmlspecialchars($doctor['full_name']) ?>"
                             class="doc-card-img">
                    <?php else: ?>
                        <div class="doc-card-img-placeholder"><i class="fas fa-user-md"></i></div>
                    <?php endif; ?>

                    <!-- Body -->
                    <div class="doc-card-body">
                        <h3>Dr. <?= htmlspecialchars($doctor['full_name']) ?></h3>
                        <p class="doc-specialization"><?= htmlspecialchars($doctor['specialization']) ?></p>

                        <?php if (!empty($doctor['qualification'])): ?>
                        <p class="doc-qualification">
                            <i class="fas fa-graduation-cap" style="color:#1778F2; margin-right:.3rem;"></i>
                            <?= htmlspecialchars($doctor['qualification']) ?>
                        </p>
                        <?php endif; ?>

                        <p class="doc-experience">
                            <i class="fas fa-briefcase" style="color:#1778F2; margin-right:.3rem;"></i>
                            <?= (int)$doctor['experience_years'] ?>+ years experience
                        </p>

                        <p class="doc-fee">
                            <i class="fas fa-money-bill-wave" style="margin-right:.3rem;"></i>
                            LKR <?= number_format((float)$doctor['consultation_fee']) ?>
                        </p>

                        <?php if (!empty($doctor['available_days'])): ?>
                        <p class="doc-days">
                            <i class="fas fa-calendar-alt" style="color:#1778F2; margin-right:.3rem;"></i>
                            <?= htmlspecialchars($doctor['available_days']) ?>
                        </p>
                        <?php endif; ?>

                        <?php if (!empty($doctor['available_time_start']) && !empty($doctor['available_time_end'])): ?>
                        <p class="doc-days">
                            <i class="fas fa-clock" style="color:#1778F2; margin-right:.3rem;"></i>
                            <?= date('h:i A', strtotime($doctor['available_time_start'])) ?>
                            – <?= date('h:i A', strtotime($doctor['available_time_end'])) ?>
                        </p>
                        <?php endif; ?>

                        <?php if (!empty($doctor['about'])): ?>
                        <hr class="divider">
                        <p class="doc-about">
                            <?= htmlspecialchars(substr($doctor['about'], 0, 90)) ?>...
                        </p>
                        <?php endif; ?>

                        <!-- Rating -->
                        <div class="doc-rating">
                            <?php
                            $rating = round((float)($doctor['avg_rating'] ?? 0));
                            for ($i = 1; $i <= 5; $i++):
                                if ($i <= $rating): ?>
                                    <i class="fas fa-star"></i>
                                <?php else: ?>
                                    <i class="far fa-star" style="color:#dee2e6;"></i>
                            <?php endif; endfor; ?>
                            <span>(<?= (int)$doctor['review_count'] ?> reviews)</span>
                        </div>

                        <!-- Buttons -->
                        <div class="doc-actions">
                            <a href="/medicare_plus/patient/book_appointment.php?doctor=<?= (int)$doctor['user_id'] ?>"
                               class="btn-book-now">
                                Book Appointment
                            </a>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-user-md"></i>
                    No doctors found matching your criteria.<br>
                    <a href="doctors.php" style="color:#1778F2; margin-top:.5rem; display:inline-block;">View all doctors</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>