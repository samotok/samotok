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

// Get consultant ID from query string
$consultant_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($consultant_id <= 0) {
    die('Invalid consultant ID.');
}

// Main consultant query with aggregates
$sql = "
SELECT
    c.id,
    c.id,
    c.clinic_id,
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
WHERE c.id = ?
GROUP BY
    c.id, c.name, c.consultation_fee,
    sp.speciality, cl.name,
    cl.latitude, cl.longitude,
    cl.car_parking, cl.disabled_access
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $consultant_id);
$stmt->execute();
$result = $stmt->get_result();
$consultant = $result->fetch_assoc();
$stmt->close();

if (!$consultant) {
    die('Consultant not found.');
}

// Build rating histogram counts
$ratingCounts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
$stmt = $conn->prepare(
    "SELECT score, COUNT(*) AS count
    FROM reviews
    WHERE consultant_id = ?
    GROUP BY score"
);
$stmt->bind_param('i', $consultant_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $ratingCounts[(int) $row['score']] = (int) $row['count'];
}
$stmt->close();
$ratingJson = json_encode(array_values($ratingCounts));



// Fetch working weekdays (0=Mon ... 6=Sun)
$stmt = $conn->prepare(
    "SELECT weekday
    FROM consultant_schedule
    WHERE consultant_id = ?"
);
$stmt->bind_param('i', $consultant_id);
$stmt->execute();
$schRes = $stmt->get_result();
$schedule = [];
while ($row = $schRes->fetch_assoc()) {
    $schedule[] = (int) $row['weekday'];
}
$stmt->close();

// Calculate days off
$allDays = [0, 1, 2, 3, 4, 5, 6];
$daysOff = array_values(array_diff($allDays, $schedule));

// Fetch booked dates
$stmt = $conn->prepare(
    "SELECT DISTINCT booking_date
    FROM bookings
    WHERE consultant_id = ?"
);
$stmt->bind_param('i', $consultant_id);
$stmt->execute();
$bkRes = $stmt->get_result();
$booked = [];
while ($row = $bkRes->fetch_assoc()) {
    $booked[] = $row['booking_date'];
}
$stmt->close();

// Fetch clinic location
$stmt = $conn->prepare(
    "SELECT latitude, longitude
    FROM clinics
    WHERE id = ?"
);
$stmt->bind_param('i', $consultant['clinic_id']);
$stmt->execute();

$stmt->bind_result($latitude, $longitude);

if (!$stmt->fetch()) {
    die('Clinic location not found.');
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($consultant['name']) ?> - Profile</title>

    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:400,600&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <!-- Flatpickr -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <!-- Turf.js -->
    <script src="https://cdn.jsdelivr.net/npm/@turf/turf@6.5.0/turf.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

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

        .profile-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .profile-header h1 {
            font-weight: 600;
            color: var(--accent-color);
        }

        .profile-header p {
            margin: 0;
            color: var(--primary-color);
        }

        .profile-section {
            margin-bottom: 2rem;
        }

        .flatpickr-day.flatpickr-disabled {
            background: #f5f5f5;
            color: #999;
            cursor: not-allowed;
        }
    </style>

    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="enthub.php">ENT Care Hub</a>

        </div>
    </nav>

    <div class="container my-5">
        <!-- Profile Header -->
        <div class="profile-header profile-section card medical-card p-4">
            <img src="assets/doctor-placeholder.jpg" alt="Doctor" class="rounded-circle mb-3" width="120" height="120">
            <h1><?= htmlspecialchars($consultant['name']) ?></h1>
            <p class="mb-1"><strong>Specialty:</strong> <?= htmlspecialchars($consultant['specialty']) ?></p>
            <a class="mb-1" href="clinicPage.php?id=<?= urlencode($consultant['clinic_id']) ?>">
                <strong>Clinic:</strong> <?= htmlspecialchars($consultant['clinic_name']) ?>
            </a>
            <p class="mb-0">Rating: <?= $consultant['avg_rating'] ?? 'No reviews' ?> / 5</p>
            <p class="mb-0">Consultation Fee: £<?= htmlspecialchars($consultant['consultation_fee']) ?></p>
        </div>

        <!-- Rating Histogram -->
        <div class="profile-section card medical-card p-4">
            <h2 class="h5 mb-3">Rating Distribution</h2>
            <canvas id="ratingHistogram" width="400" height="200"></canvas>
        </div>

        <script>
            const ratingData = <?= $ratingJson ?>;
            const ctx = document.getElementById('ratingHistogram').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['1★', '2★', '3★', '4★', '5★'],
                    datasets: [{ data: ratingData, backgroundColor: 'rgba(0,123,127,0.6)', borderColor: 'rgba(0,123,127,1)', borderWidth: 1 }]
                },
                options: { scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }, plugins: { legend: { display: false } } }
            });
        </script>

        <!-- Availability Calendar -->
        <div class="profile-section card medical-card p-4">
            <h2 class="h5 mb-3">Availability Calendar</h2>
            <input id="availabilityCalendar" type="text" class="form-control" readonly>
        </div>

        <script>
            const daysOff = <?= json_encode($daysOff) ?>;
            const bookedDates = <?= json_encode($booked) ?>;
            flatpickr("#availabilityCalendar", {
                inline: true,
                disable: [
                    date => daysOff.includes(date.getDay()),
                    ...bookedDates
                ],
                locale: { firstDayOfWeek: 1 }
            });
        </script>

        <div id="map" style="height: 400px; width: 100%;"></div>
        <p id="distance">Distance: <span id="dist-value">–</span> km</p>

        <script>
            const clinic = {
                lat: <?= json_encode($latitude) ?>,
                lng: <?= json_encode($longitude) ?>
            };

            const map = L.map('map').setView([clinic.lat, clinic.lng], 13);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '© OpenStreetMap'
            }).addTo(map);




            const clinicMarker = L.marker([clinic.lat, clinic.lng])
                .addTo(map)
                .bindPopup('Clinic Location')
                .openPopup();

            // Get the user's location
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(position => {
                    const userLocation = {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude
                    };
                    const userIcon = L.icon({
                        iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x-blue.png',
                        iconSize: [25, 41],
                        iconAnchor: [12, 41],
                        popupAnchor: [1, -34],
                        shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
                        shadowSize: [41, 41]
                    });


                    const userMarker = L.marker([userLocation.lat, userLocation.lng], { icon: userIcon })
                        .addTo(map)
                        .bindPopup('Your Location')
                        .openPopup();

                    // Calculate distance
                    const distance = turf.distance(
                        turf.point([userLocation.lng, userLocation.lat]),
                        turf.point([clinic.lng, clinic.lat]),
                        { units: 'kilometers' }
                    );
                    document.getElementById('dist-value').textContent = distance.toFixed(2);
                });
            } else {
                alert("Geolocation is not supported by this browser.");
            }
        </script>

        <!-- Book Appointment Button -->
        <div class="profile-actions text-center profile-section">
            <a href="bookingPage.php?consultant_id=<?= $consultant['id'] ?>" class="btn btn-lg btn-primary">Book
                Appointment</a>
        </div>
    </div>

    <footer class="bg-white text-center py-3 border-top">
        &copy; <?= date('Y') ?> ENT Care Hub
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>