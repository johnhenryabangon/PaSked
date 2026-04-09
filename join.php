<?php
include 'config/db_config.php';

// Fetch initial search query
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$params = [];

// Base SELECT statement, including the 'sports' column
$select_fields = "court_id, court_name, court_location, court_image, hourly_rate, google_maps_url, sports";
$base_query = "SELECT $select_fields FROM courts";

if ($search !== '') {
    $query = "$base_query 
              WHERE court_name LIKE :search 
              OR court_location LIKE :search 
              OR sports LIKE :search 
              ORDER BY court_name";
    $params[':search'] = '%'.$search.'%';
} else {
    $query = "$base_query ORDER BY court_name";
}

try {
    // Execute the final query
    $stmt = executeQuery($query, $params);
    $courts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = "Unable to load courts. Please try again later.";
    $courts = []; // Ensure courts is an empty array on error
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join PaSked</title>
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Commissioner:wght@400;700&display=swap" rel="stylesheet">
</head>
<body>
    <header class="header">
        <div class="nav-container">
            <a href="index.php" class="logo">
                <img class="logo-icon" src="assets/images/paskedlogo.png" alt="Logo">
                PaSked
            </a>
            <nav>
                <ul class="nav-menu">
                    <li><a href="index.php">Back to Courts</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container" style="padding-top: 3rem;">
        <br><br><br>
        <div class="page-title text-center">
            <h1 id="top">Join PaSked Sports Platform</h1>
            <p>Manage courts, simplify reservations, and grow your sports community.</p>
        </div>

        <section class="cards-section">
            <!-- System Features Overview -->
            <div class="card">
                <h2><span class="icon">💡</span> System Features Overview</h2>
                <p>
                    PaSked is a web-based court reservation platform built to make sports scheduling simple, organized, and accessible for everyone. It connects players with available basketball and volleyball courts across Metro Manila, allowing them to check rates, view locations, and reserve a timeslot without the need for an account. For court facilitators, PaSked provides an easy-to-use dashboard for managing bookings, viewing weekly schedules, and keeping operations smooth and conflict-free. Whether you’re a casual player, a team captain, or a facility owner, PaSked helps streamline the entire booking experience—making court access faster, easier, and more reliable.
                </p>
            </div>

            <!-- Invitation to Facilitors -->
            <div class="card">
                <h2><span class="icon">🤝</span> Invitation to Court Facilitators</h2>
                <p>
                    We invite all sports court facilitators across Metro Manila to be part of the PaSked platform. Whether you manage a barangay court or a private sports facility, PaSked provides a simple way to reach more players and streamline your reservation process.
                </p><br>
                <p>
                    To maintain service quality and ensure authenticity, every court undergoes a quick verification and approval process before being officially listed on the platform. If you're interested in joining PaSked or would like to know more, feel free to reach us through the following channels:
                </p><br>
                
                <div class="socials">
                    <div>
                        <img class="soc-icons" src="assets/images/gmail.png" alt="Gmail">
                        <p><a href="mailto:pasked.metromnl@gmail.com" target="_blank">pasked.metromnl@gmail.com</a></p>
                    </div>
                
                    <div>
                        <img class="soc-icons" src="assets/images/facebook.png" alt="Facebook">
                        <p><a href="https://web.facebook.com/nullhenry.abxngon" target="_blank">PaSked Metro Manila</a></p>
                    </div>

                    <div>
                        <img class="soc-icons" src="assets/images/phone.png" alt="Mobile">
                        <p><a href="tel:9123456789" target="_blank">+639123456789</a></p>
                    </div>
                </div>
            </div>


            <h2 style="margin-bottom: 2rem; color: var(--accent-yellow); text-align: center;">Why Choose PaSked?</h2>
            <div class="admin-stats">
                <div class="stat-card">
                    <div class="stat-number" style="color: var(--accent-blue);"><p>⚡</p></div>
                    <div class="stat-label">
                        <strong>Quick Booking</strong><br>
                        Book in under 2 minutes
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: var(--accent-green);"><p>🔒</p></div>
                    <div class="stat-label">
                        <strong>Secure</strong><br>
                        No account required
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: var(--accent-yellow);"><p>📱</p></div>
                    <div class="stat-label">
                        <strong>Mobile Friendly</strong><br>
                        Works on any device
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: var(--accent-red);"><p>🏆</p></div>
                    <div class="stat-label">
                        <strong>Authentic Courts</strong><br>
                        Top-quality facilities
                    </div>
                </div>
            </div>

            <div class="card">
                <h2>📜 Project Information</h2>
                <p>
                    <strong><em>PaSked</em></strong> is a project developed by <strong>John Henry Abangon</strong>, a Bachelor of Science in Computer Science student at the <strong>Technological University of the Philippines</strong>, for the <em>Project Management</em> course. This system is created as part of the academic requirements for the <strong>1st Semester, Academic Year 2025–2026</strong>. The project demonstrates the practical application of project planning, system development, and deployment processes while addressing a real-world need within the local sports community.
                </p><br>
                <p>
                    For inquiries, feedback, or project-related concerns, you may reach the developer through the following channels:
                </p><br>

                <div class="socials">
                    <div>
                        <img class="soc-icons" src="assets/images/linkedin.png" alt="LinkedIn">
                        <p><a href="https://www.linkedin.com/in/john-henry-abangon-9b2b48295/" target="_blank">John Henry Abangon</a></p>
                    </div>

                    <div>
                        <img class="soc-icons" src="assets/images/gmail.png" alt="Gmail">
                        <p><a href="mailto:pasked.metromnl@gmail.com" target="_blank">johnhenryabangon@gmail.com</a></p>
                    </div>

                    <div>
                        <img class="soc-icons" src="assets/images/github.png" alt="GitHub">
                        <p><a href="https://github.com/johnhenryabangon" target="_blank">johnhenryabangon</a></p>
                    </div>
                </div>
            </div>

            <div class="card">
                <h2>◀≫ Powered By</h2>
                <div class="powered-by">
                    <a title="php"><img class="powered-by-logo" src="assets/images/php.png" alt="php"></a>
                    <a title="JavaScript"><img class="powered-by-logo" src="assets/images/js.png" alt="javascript"></a>
                    <a title="CSS"><img class="powered-by-logo" src="assets/images/css.png" alt="css"></a>
                    <a title="HTML"><img class="powered-by-logo" src="assets/images/html.png" alt="html"></a>
                    <a title="mySQL"><img class="powered-by-logo" src="assets/images/sql.png" alt="mysql"></a>
                </div>
            </div>
        </section>

        <div style="margin-top: 2rem; text-align: center;">
            <a href="#top" class="btn btn-primary" style="
              position: fixed;
              bottom: 20px;
              right: 20px;
              z-index: 2;
              padding: 0.75rem 1rem;
              font-weight: 600;
              border-radius: 50%;
              box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <b>▲</b>
            </a>
        </div>
    </main>


    <footer style="background: var(--bg-secondary); margin-top: 4rem; border-top: 1px solid var(--border-color); height: 150px; padding-top: 1.5rem;">

        <div class="container" style="text-align: center; color: var(--text-secondary); height: 100%;">

            <p >&copy; 2025 PaSked - Sports Court Booking System for Metro Manila</p>

            <p style="font-size: 0.9rem;">Making court reservations easier for Filipino sports enthusiasts</p>

        </div>

    </footer>

    <script src="assets/js/main.js"></script>
</body>
</html>