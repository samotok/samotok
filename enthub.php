<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database connection
$servername = "sci-mysql";
$username = "coa123edb";
$password = "E4XujVcLcNPhwfBjx-";
$database = "coa123edb";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize filters
$questionnaire_specialty = null;
$filter_date = null;
$weekday = null;
$selected = '';
$symptoms = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_questionnaire'])) {
    // Map form slugs to DB values
    $map = [
        'otology' => 'Otology',
        'rhinology' => 'Rhinology',
        'laryngology' => 'Laryngology',
        'allergy' => 'Allergy',
        'paediatric' => 'Paediatric ENT',
        'han-surgery' => 'Head And Neck Surgery',
    ];

    // Specialty filter
    $selected = $_POST['specialist_type'] ?? '';
    if (isset($map[$selected])) {
        $questionnaire_specialty = $conn->real_escape_string($map[$selected]);
    }

    // Date filter
    $raw_date = $_POST['date'] ?? '';
    $d = DateTime::createFromFormat('Y-m-d', $raw_date);
    if ($d && $d->format('Y-m-d') === $raw_date) {
        $filter_date = $conn->real_escape_string($raw_date);
        $N = (int) $d->format('N'); // 1 (Mon) to 7 (Sun)
        $weekday = ($N + 6) % 7;
    }

    // Symptoms capture
    $symptoms = $_POST['symptoms'] ?? [];
}

// Build base SQL
$sql = "
SELECT
    c.id,
    c.name,
    c.consultation_fee,
    sp.speciality AS specialty,
    cl.name AS clinic_name,
    cl.latitude,
    cl.longitude,
    cl.car_parking,
    cl.disabled_access,
    ROUND(AVG(r.score), 1) AS avg_rating,
    ROUND(
        SUM(CASE WHEN r.recommend='yes' THEN 1 ELSE 0 END)
        / NULLIF(COUNT(r.id),0), 2
    ) AS recommend_ratio
FROM consultants c
JOIN specialities sp ON c.speciality_id = sp.id
JOIN clinics cl      ON c.clinic_id       = cl.id
LEFT JOIN reviews r  ON c.id              = r.consultant_id
";

// Date availability join
if ($weekday !== null) {
    $sql .= "
    JOIN consultant_schedule cs ON c.id = cs.consultant_id AND cs.weekday = {$weekday}
    LEFT JOIN bookings b       ON c.id = b.consultant_id AND b.booking_date = '{$filter_date}'
    ";
}

// WHERE clauses
$where = [];
if ($questionnaire_specialty) {
    $where[] = "sp.speciality = '{$questionnaire_specialty}'";
}
if ($weekday !== null) {
    $where[] = "b.consultant_id IS NULL";
}
if (count($where) > 0) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= " GROUP BY c.id";
$result = $conn->query($sql);
if (!$result) {
    die("Query error: " . $conn->error);
}
$consultants = [];
while ($row = $result->fetch_assoc()) {
    $consultants[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ENT Care Hub</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
        }

        .consultant-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            border-radius: 10px;
            overflow: hidden;
        }

        .consultant-card:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .card-img-top {
            height: 180px;
            object-fit: cover;
        }

        .symptom-block {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 1rem;
            background: #fff;
        }
    </style>
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">ENT Care Hub</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="nav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="#">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Find a Consultant</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">About Us</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="card shadow-lg animate__fadeIn">
            <div class="card-body">
                <h2 class="card-title text-center mb-4">Find the Right ENT Specialist</h2>
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label">Specialty</label>
                            <select id="main-symptom" name="specialist_type" class="form-select" required>
                                <option value="">Choose...</option>
                                <option value="otology" <?= $selected === 'otology' ? 'selected' : '' ?>>Otology</option>
                                <option value="rhinology" <?= $selected === 'rhinology' ? 'selected' : '' ?>>Rhinology
                                </option>
                                <option value="laryngology" <?= $selected === 'laryngology' ? 'selected' : '' ?>>Laryngology
                                </option>
                                <option value="allergy" <?= $selected === 'allergy' ? 'selected' : '' ?>>Allergy</option>
                                <option value="paediatric" <?= $selected === 'paediatric' ? 'selected' : '' ?>>Paediatric ENT
                                </option>
                                <option value="han-surgery" <?= $selected === 'han-surgery' ? 'selected' : '' ?>>Head & Neck
                                    Surgery</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Date</label>
                            <input type="date" name="date" class="form-control"
                                value="<?= htmlspecialchars($filter_date) ?>">
                        </div>
                        <div class="col-md-2 d-grid align-self-end">
                            <input type="hidden" name="submit_questionnaire" value="1">
                            <button class="btn btn-primary">Search</button>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <!-- Otology -->
                        <div id="otology-symptoms" class="symptom-block d-none mb-3">
                            <label class="fw-semibold">Ear Symptoms:</label>
                            <?php foreach (['hearing_loss' => 'Hearing Loss', 'ear_pain' => 'Ear Pain', 'tinnitus' => 'Tinnitus', 'ear_infection' => 'Ear Infection'] as $val => $label): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="symptoms[]" value="<?= $val ?>"
                                        id="<?= $val ?>" <?= in_array($val, $symptoms) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="<?= $val ?>"><?= $label ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <!-- Rhinology -->
                        <div id="rhinology-symptoms" class="symptom-block d-none mb-3">
                            <label class="fw-semibold">Nose Symptoms:</label>
                            <?php foreach (['blocked_nose' => 'Blocked Nose', 'smell_loss' => 'Loss of Smell', 'nose_bleed' => 'Nose Bleeds', 'rhinitus' => 'Rhinitis'] as $val => $label): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="symptoms[]" value="<?= $val ?>"
                                        id="<?= $val ?>" <?= in_array($val, $symptoms) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="<?= $val ?>"><?= $label ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <!-- Laryngology -->
                        <div id="laryngology-symptoms" class="symptom-block d-none mb-3">
                            <label class="fw-semibold">Throat Symptoms:</label>
                            <?php foreach (['swallow_pain' => 'Pain on Swallowing', 'cough' => 'Frequent Cough', 'voice_change' => 'Voice Change', 'throat_pain' => 'Throat Pain'] as $val => $label): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="symptoms[]" value="<?= $val ?>"
                                        id="<?= $val ?>" <?= in_array($val, $symptoms) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="<?= $val ?>"><?= $label ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <!-- Allergy -->
                        <div id="allergy-symptoms" class="symptom-block d-none mb-3">
                            <label class="fw-semibold">Allergy Symptoms:</label>
                            <?php foreach (['sneezing' => 'Sneezing/Itchy Nose', 'seasonal' => 'Seasonal Allergy', 'allergic_cough' => 'Allergic Cough', 'skin_manifestations' => 'Skin Manifestations'] as $val => $label): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="symptoms[]" value="<?= $val ?>"
                                        id="<?= $val ?>" <?= in_array($val, $symptoms) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="<?= $val ?>"><?= $label ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <!-- Paediatric ENT -->
                        <div id="paediatric-symptoms" class="symptom-block d-none mb-3">
                            <label class="fw-semibold">Child Symptoms:</label>
                            <?php foreach (['otitus' => 'Frequent Otitis', 'tonsillitus' => 'Tonsillitis', 'snoring' => 'Snoring', 'breathing' => 'Trouble Breathing'] as $val => $label): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="symptoms[]" value="<?= $val ?>"
                                        id="<?= $val ?>" <?= in_array($val, $symptoms) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="<?= $val ?>"><?= $label ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Consultant Cards -->
        <div class="row mt-4">
            <?php if (empty($consultants)): ?>
                <p class="text-muted">No consultants found.</p>
            <?php endif; ?>
            <?php foreach ($consultants as $c): ?>
                <div class="col-md-3 mb-4">
                    <div class="card consultant-card h-100">
                        <img src="https://via.placeholder.com/300x180" class="card-img-top" alt="Doctor">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?= htmlspecialchars($c['name']) ?></h5>
                            <p class="card-text"><?= htmlspecialchars($c['specialty']) ?></p>
                            <p class="text-muted mb-1"><?= htmlspecialchars($c['clinic_name']) ?></p>
                            <p class="text-muted mb-1">⭐ <?= $c['avg_rating'] ?? 'N/A' ?></p>
                            <p class="text-muted mb-3">£<?= htmlspecialchars($c['consultation_fee']) ?></p>
                            <?php if ($filter_date): ?>
                                <p class="text-success mb-3">Available <?= htmlspecialchars($filter_date) ?></p>
                            <?php endif; ?>
                            <a href="#" class="btn btn-primary mt-auto">View Profile</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <footer class="bg-dark text-white text-center py-3 mt-5">
        © Made by F431412 
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
    <script>
        new Choices('#main-symptom');
        document.getElementById('main-symptom').addEventListener('change', function () {
            const sel = this.value;
            document.querySelectorAll('.symptom-block').forEach(el => el.classList.add('d-none'));
            if (sel) document.getElementById(sel + '-symptoms')?.classList.remove('d-none');
        });
    </script>
</body>

</html>