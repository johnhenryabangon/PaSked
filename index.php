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
    <title>PaSked - Sports Court Platform</title>
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
                    <li><a href="admin/login.php">Admin</a></li>
                    <li><a href="join.php">Join Us</a></li>
                </ul>
            </nav>
        </div>
    </header>
    <main class="container">
        <br><br><br>
        <div class="page-title">
            <h1 id="top">Available Sports Courts in Metro Manila</h1>
            <p>Book your favorite court easily. Pa-iSked na!</p>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
            
        <form method="get" action="index.php" class="search-form" style="margin-bottom: 2rem; margin-top: 50px; text-align:center;">
            <input type="text" name="q" class="form-input" placeholder="Search courts by name, location, or sport..." value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>" style="padding:8px;width:70%;">
            <button type="submit" style="padding:8px 16px;">Search</button>
        </form>


        <div class="courts-grid">
            <?php if (!empty($courts)): ?>
                <?php foreach ($courts as $court): ?>
                    <div class="court-card">
                        <div class="court-image">
                            <img class="courtpic" 
                                src="assets/images/<?php echo htmlspecialchars($court['court_image'] ?? 'court.jpg'); ?>" 
                                alt="<?php echo htmlspecialchars($court['court_name']); ?>">
                        </div>
                        <div class="court-info">
                            <h3><?php echo htmlspecialchars($court['court_name']); ?></h3>
                            <div class="court-location">
                                ⚲ <?php echo htmlspecialchars($court['court_location']); ?>
                            </div>
                            <div class="court-sports">
                                <?php echo htmlspecialchars($court['sports']); ?>
                            </div>
                            
                            <div class="court-rate">
                                ₱<?php echo number_format($court['hourly_rate'], 2); ?>/hour
                            </div>
                            
                            <div class="court-actions">
                                <a href="booking.php?court_id=<?php echo $court['court_id']; ?>" class="btn btn-primary">
                                    Schedule Now
                                </a>
                                
                                <?php if (!empty($court['google_maps_url'])): ?>
                                    <a href="<?php echo htmlspecialchars($court['google_maps_url']); ?>" 
                                       target="_blank" class="btn btn-secondary">
                                        ➢ Directions
                                    </a>
                                <?php else: ?>
                                    <span class="btn btn-disabled">
                                        ➢ Map Directions (Coming Soon)
                                    </span>
                                <?php endif; ?>
                                    
                                <a href="about.php?court_id=<?php echo $court['court_id']; ?>" class="btn btn-info">
                                    About
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-error">
                    <p>No courts available at the moment. Please check back later.</p>
                </div>
            <?php endif; ?>
        </div>


        <section style="margin-top: 4rem; text-align: center;">
            <h2 style="margin-bottom: 2rem; color: var(--accent-yellow);">Why Choose PaSked?</h2>
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