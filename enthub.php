<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

//query that fetches all necessary data from the db to the backend so doctor cards can be created
// a configuration file establishing a connection to a database which is used in every other file
$servername = "sci-mysql";
$username = "coa123edb";
$password = "E4XujVcLcNPhwfBjx-";
$database = "coa123edb";

// creating a connection
$conn = new mysqli($servername, $username, $password, $database);

// checking the connection
if ($conn->connect_error) {
    die("connection failed: " . $conn->connect_error);
}

$questionnaire_specialty = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_questionnaire'])) {
    $symptoms = $_POST['symptoms'] ?? [];
    $age_group = $_POST['age_group'] ?? 'adult';

    $questionnaire_specialty = 'ENT Surgeon'; // default specialty

    if (in_array('hearing', $symptoms)) {
        $questionnaire_specialty = 'Audiologist';

    }
    
    if ($age_group === 'child') {
        $questionnaire_specialty = 'Pediatric ENT'; // for kids
    }

    // modify the query later

}

$consultant_card_data = "
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
    ROUND(SUM(CASE WHEN r.recommend = 'yes' THEN 1 ELSE 0 END) / NULLIF(COUNT(r.id), 0), 2) AS recommend_ratio
FROM consultants c
JOIN specialities sp ON c.speciality_id = sp.id
JOIN clinics cl ON c.clinic_id = cl.id
LEFT JOIN reviews r ON c.id = r.consultant_id
";

if ($questionnaire_specialty) {
    $consultant_card_data .= " WHERE sp.speciality = '" . $conn->real_escape_string($questionnaire_specialty) . "'";
}

$consultant_card_data .= " GROUP BY c.id";


$result = $conn->query($consultant_card_data);
$consultants = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $consultants[] = $row;
    }
} else {
    die("Query error: " . $conn->error);
}




?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ENT Care Hub</title>

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <!-- Choices.js -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- JQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <style>
        body {
            background-color: #f8f9fa;
        }
        .consultant-card {
            transition: transform 0.3s ease-in-out, box-shadow 0.3s ease-in-out;
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

        .card-body {
            padding: 1.25rem;
        }

        .card-title {
            font-size: 1.1rem;
            margin-bottom: 0.75rem;
        }

        .card-text {
            font-size: 0.9rem;
            margin-bottom: 0.4rem;
        }

        .text-muted {
            font-size: 0.85rem;
        }

        .btn-primary {
            background-color: #0d6efd;
            border: none;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }

        .btn-primary:hover {
            background-color: #0b5ed7;
        }

        .footer {
            background-color: #343a40;
            color: white;
            text-align: center;
            padding: 15px;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="#">ENT Care Hub</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="#">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="#">Find a Consultant</a></li>
                <li class="nav-item"><a class="nav-link" href="#">About Us</a></li>
            </ul>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<div class="container mt-4">
    <div class="text-center">
        <h1 class="fw-bold">Find the Right ENT Specialist</h1>
        <p class="lead">Search for consultants by specialty and availability.</p>
    </div>
</div>

<div class="container mt-5 animate__animated animate__fadeIn">
    <div class="card shadow-lg">
        <div class="card-body">
            <h2 class="card-title text-center mb-4">Let us help you find the right specialist</h2>
            <form id="questionnaire-form" method="POST" action="">
                <div class="mb-3">
                    <label for="main-symptom" class="form-label">Which type of treatment do you need?</label>
                    <select id="main-symptom" class="form-select" name="specialist_type" required>
                        <option value="">Choose one...</option>
                        <option value="otology">Otology</option>
                        <option value="rhinology">Rhinology</option>
                        <option value="laryngology">Laryngology</option>
                        <option value="allergy">Allergy</option>
                        <option value="paediatric">Paediatric ENT</option>
                        <option value="han-surgery">Head And Neck Surgery</option>
                    </select>
                    <input type="hidden" name="submit_questionnaire" value="1">
                </div>
            </form>


                <div id="otology-symptoms" class="symptom-block d-none mt-3">

                    <label class="form-label">Do you experience any of the following symptoms?</label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="hearing_loss" id="hearing_loss">
                        <label class="form-check-label" for="hearing_loss">Hearing loss</label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="ear_pain" id="ear_pain">
                        <label class="form-check-label" for="ear_pain">Ear pain</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="tinnitus" id="tinnitus">
                        <label class="form-check-label" for="tinnitus">Tinnitus</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="ear_infection" id="ear_infection">
                        <label class="form-check-label" for="ear_infection">Ear infection</label>
                    </div>
                    
                </div>

                <div id="rhinology-symptoms" class="symptom-block d-none mt-3">

                    <label class="form-label">Do you experience any of the following symptoms?</label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="blocked_nose" id="blocked_nose">
                        <label class="form-check-label" for="blocked_nose">Chronically blocked nose</label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="smell_loss" id="smell_loss">
                        <label class="form-check-label" for="smell_loss">Loss of smell</label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="nose_bleed" is="nose_bleed">
                        <label class="form-check-label" for="nose_bleed">Frequent nose bleedings</label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="rhinitus" is="rhinitus">
                        <label class="form-check-label" for="rhinitus">Allergic rhinitus</label>
                    </div>

                </div>

                <div id="laryngology-symptoms" class="symptom-block d-none mt-3">

                    <label class="form-label">Do you experience any of the following symptoms?</label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="swallow_pain" is="swallow_pain">
                        <label class="form-check-label" for="swallow_pain">Pain when swallowing</label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="cough" is="cough">
                        <label class="form-check-label" for="cough">Frequent coughing</label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="voice_change" is="voice_change">
                        <label class="form-check-label" for="voice_change">Change in voice</label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="throat_pain" is="throat_pain">
                        <label class="form-check-label" for="throat_pain">Throat pain</label>
                    </div>

                </div>


                <div id="paediatric-symptoms" class="symptom-block d-none mt-3">

                    <label class="form-label">Does your child experience any of the following symptoms?</label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="otitus" is="otitus">
                        <label class="form-check-label" for="swallow_pain">Frequent otitus</label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="tonsillitus" is="tonsillitus">
                        <label class="form-check-label" for="tonsillitus">Tonsillitus</label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="snoring" is="snoring">
                        <label class="form-check-label" for="snoring">Snoring</label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="breathing" is="breathing">
                        <label class="form-check-label" for="breathing">Trouble breathing</label>
                    </div>

                </div>

                <div id="neck-head-symptoms" class="symptom-block d-none mt-3">

                    <label class="form-label">Do you experience any of the following symptoms?</label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="swallow_trouble" is="swallow_trouble">
                        <label class="form-check-label" for="swallow_trouble">Trouble swallowing</label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="neck_pain" is="neck_pain">
                        <label class="form-check-label" for="neck_pain">Neck pain</label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="neoplasms" is="neoplasms">
                        <label class="form-check-label" for="neoplasms">Neoplasms in neck area</label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="onco_suspicion" is="onco_suspicion">
                        <label class="form-check-label" for="onco_suspicion">Oncological suspicions</label>
                    </div>

                </div>

                <div id="allergy-symptoms" class="symptom-block d-none mt-3">

                    <label class="form-label">Do you experience any of the following symptoms?</label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="sneezing" is="sneezing">
                        <label class="form-check-label" for="sneezing">Sneezing, itchy nose</label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="seasonal" is="seasonal">
                        <label class="form-check-label" for="seasonal">Seasonal allergy</label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="allergic_cough" is="allergic_cough">
                        <label class="form-check-label" for="allergic_cough">Allergic cough</label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="skin_manifestations" is="skin_manifestations">
                        <label class="form-check-label" for="skin_manifestations">Skin manifestations</label>
                    </div>

                </div>

                <div class="mb-3">
                    <label class="form-label">How long have you had this issue?</label>
                    <select class="form-select" required>
                        <option value="">Select</option>
                        <option value="less-week">Less than a week</option>
                        <option value="1-4-weeks">1–4 weeks</option>
                        <option value="1-6-months">1–6 months</option>
                        <option value="6plus-months">More than 6 months</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Rate the severity of your symptom:</label>
                    <input type="range" class="form-range" min="1" max="10" step="1" id="severity" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Have you seen any specialist before?</label>
                    <div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="seen-specialist" id="seen-yes" value="yes" required>
                            <label class="form-check-label" for="seen-yes">Yes</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="seen-specialist" id="seen-no" value="no">
                            <label class="form-check-label" for="seen-no">No</label>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Do you prefer a clinic with:</label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="parking" id="parking">
                        <label class="form-check-label" for="parking">Car Parking</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="disabled" id="disabled">
                        <label class="form-check-label" for="disabled">Disabled Access</label>
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Find My Specialist</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JS Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>

<script>
    new Choices('#main-symptom');

    document.getElementById('questionnaire-form').addEventListener('submit', function(e) {
        e.preventDefault();

        Swal.fire({
            title: 'Thanks for your answers!',
            text: 'We are matching you with the most suitable ENT consultant...',
            icon: 'success',
            confirmButtonText: 'Continue'
        }).then(() => {
            window.location.href = 'enthub.php'; // or another result/summary page
        });
    });
</script>

<!-- Consultant Cards Section -->
<div class="container mt-4">
    <h4 class="mb-3">Our Specialists</h4>
    <div class="row" id="doctor-list">
        <?php foreach ($consultants as $c): ?>
        <div class="col-md-3">
            <div class="card consultant-card shadow-sm mb-4">
                <img src="https://via.placeholder.com/300x180" class="card-img-top" alt="Doctor Image">
                <div class="card-body">
                    <h5 class="card-title"><?= htmlspecialchars($c['name']) ?></h5>
                    <p class="card-text"><?= htmlspecialchars($c['specialty']) ?></p>
                    <p class="card-text text-muted"><?= htmlspecialchars($c['clinic_name']) ?></p>
                    <p class="text-muted">Rating: <?= $c['avg_rating'] ?? 'N/A' ?></p>
                    <p class="text-muted">Fee: £<?= $c['consultation_fee'] ?></p>
                    <a href="#" class="btn btn-primary">View Profile</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>


<!-- Footer -->
<footer class="footer mt-5">
    <p>&copy; Made by F431412 || All rights reserved</p>
</footer>



</body>
</html>
