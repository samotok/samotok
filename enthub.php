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
$need_parking = false;
$need_disabled_access = false;

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

    $need_parking = isset($_POST['parking']);
    $need_disabled_access = isset($_POST['disabled_access']);
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

if ($need_parking) {
    $where[] = "cl.car_parking = 1";
}
if ($need_disabled_access) {
    $where[] = "cl.disabled_access = 1";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ENT Care Hub</title>
    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:400,600&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <!-- Choices.js -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />

    <style>
        :root {
            --primary-color: #007b7f;
            --secondary-color: #e6f2f2;
            --accent-color: #005f63;
            --text-color: #333333;
        }

        body {
            font-family: 'Open Sans', sans-serif;
            background-color: var(--secondary-color);
            color: var(--text-color);
        }

        .navbar {
            background-color: var(--primary-color) !important;
        }

        .navbar-brand {
            font-weight: 600;
        }

        .card.medical-card {
            border: 1px solid var(--accent-color);
            border-radius: 0.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
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

        .form-label {
            font-weight: 600;
            color: var(--accent-color);
        }

        .form-select,
        .form-control {
            border-radius: 0.5rem;
            border: 1px solid #ccc;
        }

        .btn-primary {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
        }

        .btn-secondary {
            background-color: #gray;
            border-color: #gray;
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
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="enthub.php">ENT Care Hub</a>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Search & Symptoms Form -->
        <div class="card medical-card shadow-lg animate__fadeIn mb-4">
            <div class="card-body">
                <h2 class="card-title text-center mb-4">Find the Right ENT Specialist</h2>
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label">Specialty</label>
                            <select id="specialty-select" name="specialist_type" class="form-select medical-card"
                                required>
                                <option value="">Choose...</option>
                                <option value="otology" <?= $selected === 'otology' ? 'selected' : '' ?>>Otology</option>
                                <option value="rhinology" <?= $selected === 'rhinology' ? 'selected' : '' ?>>Rhinology
                                </option>
                                <option value="laryngology" <?= $selected === 'laryngology' ? 'selected' : '' ?>>
                                    Laryngology</option>
                                <option value="allergy" <?= $selected === 'allergy' ? 'selected' : '' ?>>Allergy</option>
                                <option value="paediatric" <?= $selected === 'paediatric' ? 'selected' : '' ?>>Paediatric
                                    ENT</option>
                                <option value="han-surgery" <?= $selected === 'han-surgery' ? 'selected' : '' ?>>Head &
                                    Neck Surgery</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Date</label>
                            <input type="date" name="date" class="form-control medical-card"
                                value="<?= htmlspecialchars($filter_date) ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <div class="form-check me-3">
                                <input class="form-check-input" type="checkbox" id="parking" name="parking"
                                    <?= $need_parking ? 'checked' : '' ?> />
                                <label class="form-check-label" for="parking">Parking</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="disabled" name="disabled"
                                    <?= $need_disabled_access ? 'checked' : '' ?> />
                                <label class="form-check-label" for="disabled">Disabled Access</label>
                            </div>
                        </div>
                        <div class="col-md-2 d-grid align-self-end">
                            <input type="hidden" name="submit_questionnaire" value="1">
                            <button type="submit" class="btn btn-primary">Search</button>
                            <button type="button" id="show-all-btn" class="btn btn-secondary mt-2">Show All</button>
                        </div>
                    </div>
                    <div class="row mt-4">
                        <!-- Symptoms Blocks -->
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

                        <div id="rhinology-symptoms" class="symptom-block d-none mb-3">
                            <label class="fw-semibold">Nose Symptoms:</label>
                            <?php foreach (['nasal_congestion' => 'Nasal Congestion', 'nose_bleeding' => 'Nose Bleeding', 'sinus_pressure' => 'Sinus Pressure'] as $val => $label): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="symptoms[]" value="<?= $val ?>"
                                        id="<?= $val ?>" <?= in_array($val, $symptoms) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="<?= $val ?>"><?= $label ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div id="laryngology-symptoms" class="symptom-block d-none mb-3">
                            <label class="fw-semibold">Throat Symptoms:</label>
                            <?php foreach (['sore_throat' => 'Sore Throat', 'hoarseness' => 'Hoarseness', 'swallowing_difficulty' => 'Swallowing Difficulty'] as $val => $label): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="symptoms[]" value="<?= $val ?>"
                                        id="<?= $val ?>" <?= in_array($val, $symptoms) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="<?= $val ?>"><?= $label ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div id="allergy-symptoms" class="symptom-block d-none mb-3">
                            <label class="fw-semibold">Allergy Symptoms:</label>
                            <?php foreach (['sneezing' => 'Sneezing', 'itchy_eyes' => 'Itchy Eyes', 'runny_nose' => 'Runny Nose'] as $val => $label): ?>
                                <div class="form-check">
                                    <input class="form-checkq-input" type="checkbox" name="symptoms[]" value="<?= $val ?>"
                                        id="<?= $val ?>" <?= in_array($val, $symptoms) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="<?= $val ?>"><?= $label ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div id="paediatric-symptoms" class="symptom-block d-none mb-3">
                            <label class="fw-semibold">Paediatric Symptoms:</label>
                            <?php foreach (['ear_infection' => 'Ear Infection', 'sore_throat' => 'Sore Throat', 'hoarseness' => 'Hoarseness'] as $val => $label): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="symptoms[]" value="<?= $val ?>"
                                        id="<?= $val ?>" <?= in_array($val, $symptoms) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="<?= $val ?>"><?= $label ?></label> 
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div id="han-surgery-symptoms" class="symptom-block d-none mb-3">
                            <label class="fw-semibold">Head & Neck Symptoms:</label>
                            <?php foreach (['lump' => 'Lump', 'pain' => 'Pain', 'swelling' => 'Swelling'] as $val => $label): ?>
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

        <!-- Sort Controls -->
        <div class="d-flex justify-content-end mb-3">
            <label for="sort-select" class="me-2">Sort by:</label>
            <select id="sort-select" class="form-select w-auto medical-card">
                <option value="">Choose filter</option>
                <option value="price_desc">Price</option>
                <option value="rating_desc">Rating</option>
                <option value="distance">Distance</option>
            </select>
        </div>

        <!-- Consultant Cards -->
        <div class="row mt-4" id="doctor-list">
            <?php if (empty($consultants)): ?>
                <p class="text-muted">No consultants found.</p>
            <?php endif; ?>
            <?php foreach ($consultants as $c): ?>
                <div class="col-md-3 mb-4 card-wrapper medical-card h-100" data-price="<?= $c['consultation_fee'] ?>"
                    data-rating="<?= $c['avg_rating'] ?>" data-lat="<?= $c['latitude'] ?>"
                    data-lng="<?= $c['longitude'] ?>">
                    <div class="card consultant-card">
                        <img src="assets/doctor-placeholder.jpg" class="card-img-top" alt="Doctor">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?= htmlspecialchars($c['name']) ?></h5>
                            <p class="card-text"><?= htmlspecialchars($c['specialty']) ?></p>
                            <p class="text-muted mb-1"><?= htmlspecialchars($c['clinic_name']) ?></p>
                            <p class="text-muted mb-1">⭐ <?= $c['avg_rating'] ?? 'No reviews' ?></p>
                            <p class="text-muted mb-3">£<?= htmlspecialchars($c['consultation_fee']) ?></p>
                            <?php if ($filter_date): ?>
                                <p class="text-success mb-3">Available <?= htmlspecialchars($filter_date) ?></p>
                            <?php endif; ?>
                            <a href="doctorPage.php?id=<?= urlencode($c['id']) ?>" class="btn btn-primary mt-auto">View
                                Profile</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <footer class="bg-white text-center py-3 border-top">
        &copy; <?= date('Y') ?> ENT Care Hub
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        new Choices('#specialty-select');

        document.getElementById('specialty-select').addEventListener('change', function () {
            document.querySelectorAll('.symptom-block').forEach(el => el.classList.add('d-none'));
            const sel = this.value;
            if (sel) document.getElementById(sel + '-symptoms').classList.remove('d-none');
        });

        document.getElementById('show-all-btn').addEventListener('click', function () {
            document.querySelector('form').reset();
            window.location.href = window.location.pathname;
        });

        document.getElementById('sort-select').addEventListener('change', function () {
            const choice = this.value;
            const container = document.getElementById('doctor-list');
            let cards = Array.from(container.getElementsByClassName('card-wrapper'));
            if (!choice) return window.location.reload();
            if (choice === 'price_desc' || choice === 'rating_desc') {
                const key = choice.split('_')[0];
                cards.sort((a, b) => parseFloat(b.dataset[key]) - parseFloat(a.dataset[key]));
                cards.forEach(c => container.appendChild(c));
            }
            if (choice === 'distance') {
                if (!navigator.geolocation) return alert('Geolocation not supported');
                navigator.geolocation.getCurrentPosition(pos => {
                    const uLat = pos.coords.latitude;
                    const uLon = pos.coords.longitude;
                    cards.forEach(card => {
                        const lat = parseFloat(card.dataset.lat);
                        const lng = parseFloat(card.dataset.lng);
                        card.dataset.distance = haversine(uLat, uLon, lat, lng);
                    });
                    cards.sort((a, b) => parseFloat(a.dataset.distance) - parseFloat(b.dataset.distance));
                    cards.forEach(c => container.appendChild(c));
                }, err => alert('Location error: ' + err.message));
            }
        });

        function haversine(lat1, lon1, lat2, lon2) {
            const toRad = v => v * Math.PI / 180;
            const R = 6371;
            const dLat = toRad(lat2 - lat1);
            const dLon = toRad(lon2 - lon1);
            const a = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) ** 2;
            return R * 2 * Math.asin(Math.sqrt(a));
        }
    </script>
</body>

</html>