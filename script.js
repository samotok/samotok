$(document).ready(function () {
    $('#search-btn').click(function () {
        let specialty = $('#specialty').val();
        let date = $('#date').val();

        $.get('search.php', { specialty: specialty, date: date }, function (data) {
            let results = JSON.parse(data);
            let output = "";

            if (results.length > 0) {
                results.forEach(c => {
                    output += `
                    <div class="col-md-4 mb-4">
                        <div class="card consultant-card shadow-sm h-100">
                            <img src="assets/doctor-placeholder.jpg" class="card-img-top" alt="Doctor Image">
                            <div class="card-body">
                                <h5 class="card-title fw-bold">${c.name}</h5>
                                <p class="card-text"><strong>Specialty:</strong> ${c.specialty}</p>
                                <p class="text-muted mb-2">⭐ Rating: ${c.rating} / 5</p>
                                <p class="text-muted mb-3">📅 Available on: ${c.availability}</p>
                                <a href="consultant.php?id=${c.id}" class="btn btn-primary w-100">View Profile</a>
                            </div>
                        </div>
                    </div>`;
                });
            } else {
                output = "<p class='text-danger'>No consultants found.</p>";
            }

            $('#results').html(output);
        });
    });
});


document.getElementById("main-symptom").addEventListener("change", function () {
    const selected = this.value;
    document.querySelectorAll(".symptom-block").forEach(el => el.classList.add("d-none"));

    switch (selected) {
        case "hearing":
            document.getElementById("otology-symptoms").classList.remove("d-none");
            break;
        case "throat":
            document.getElementById("rhinology-symptoms").classList.remove("d-none");
            break;
        case "nose":
            document.getElementById("laryngology-symptoms").classList.remove("d-none");
            break;
        case "dizziness":
            document.getElementById("paediatric-symptoms").classList.remove("d-none");
            break;
        case "sleep":
            document.getElementById("neck-head-symptoms").classList.remove("d-none");
            break;
        case "allergy":
            document.getElementById("allergy-symptoms").classList.remove("d-none");
            break;
    }
});


