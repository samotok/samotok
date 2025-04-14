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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ENT Care Hub</title>

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
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

<div class="container mt-4 p-4 bg-white shadow rounded">
    <h4 class="mb-3">Tell us your symptoms</h4>
    <form action="questionnaire_handler.php" method="post">
        <div class="mb-3">
            <label class="form-label">What is concerning you right now?</label><br>
            <input type="checkbox" name="symptoms[]" value="ear">Ears<br>
            <input type="checkbox" name="symptoms[]" value="nose">Nose<br>
            <input type="checkbox" name="symptoms[]" value="throat">Throat<br>
            <input type="checkbox" name="symptoms[]" value="hearing">Hearing<br>
        </div>
        <div class="mb-3">
            <label for="age_group" class="form-label">What age are you?</label>
            <select name="age_group" class="form-select" id="age_group">
                <option value="adult">Adult</option>
                <option value="child">Child</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Let's choose a doctor for you</button>
    </form>
</div>

<!-- Search Filters -->
<div class="container mt-4 p-4 bg-white shadow rounded">
    <h4 class="mb-3">Search for Consultants</h4>
    <div class="row g-3">
        <div class="col-md-4">
            <label for="specialty" class="form-label">Specialty</label>
            <select id="specialty" class="form-select">
                <option value="">Select</option>
                <option value="ENT Surgeon">ENT Surgeon</option>
                <option value="Audiologist">Audiologist</option>
            </select>
        </div>
        <div class="col-md-4">
            <label for="date" class="form-label">Available Date</label>
            <input type="date" id="date" class="form-control">
        </div>  
        <div class="col-md-4 d-flex align-items-end">
            <button id="search-btn" class="btn btn-primary w-100">Search</button>
        </div>
    </div>
</div>

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
