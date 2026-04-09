<?php
session_start();
include '../config/db_config.php';

function sendBookingConfirmationEmail($booking) {
    // Calculate total amount
    $start_hour = strtotime($booking['start_time']);
    $end_hour = strtotime($booking['end_time']);
    $duration_hours = ($end_hour - $start_hour) / 3600;
    $hourly_rate = $booking['hourly_rate'] ?? 500;
    $total_amount = $duration_hours * $hourly_rate;
    
    // Email details
    $to = $booking['email'];
    $subject = "Booking Confirmed - " . $booking['court_name'];
    
    // Email message
    $message = "
    Dear " . $booking['name'] . ",
    
    Great news! Your booking has been CONFIRMED.
    
    BOOKING DETAILS:
    • Court: " . $booking['court_name'] . "
    • Location: " . $booking['court_location'] . "
    • Date: " . date('F j, Y', strtotime($booking['schedule_date'])) . "
    • Time: " . date('g:i A', strtotime($booking['start_time'])) . " - " . date('g:i A', strtotime($booking['end_time'])) . "
    • Duration: " . number_format($duration_hours, 1) . " hours
    • Event: " . $booking['event_type'] . "
    
    PAYMENT INFORMATION:
    • Total Amount: ₱" . number_format($total_amount, 2) . "
    • Please bring EXACT CASH on your booking date
    
    IMPORTANT REMINDERS:
    • Arrive 15 minutes before your scheduled time
    • Bring valid ID for verification
    • Payment is due upon arrival
    
    Thank you for choosing our court!
    
    Best regards,
    PaSked Court Booking System
    ";
    
    // Send email
    $headers = "From: noreply@pasked.com\r\n";
    $headers .= "Reply-To: noreply@pasked.com\r\n";
    
    if (mail($to, $subject, $message, $headers)) {
        // Email sent successfully
        return true;
    } else {
        // Email failed
        return false;
    }
}


// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$admin_court_id = $_SESSION['admin_court_id'];
$success_message = '';
$error_message = '';

// Handle booking status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $booking_id = (int)$_POST['booking_id'];
    $action = $_POST['action'];

    if (in_array($action, ['confirm', 'decline'])) {
        $new_status = ($action === 'confirm') ? 'Confirmed' : 'Declined';

        try {
            // Verify the booking belongs to this admin's court
            $verify_query = "SELECT booking_id FROM bookings WHERE booking_id = ? AND court_id = ?";
            $verify_stmt = executeQuery($verify_query, [$booking_id, $admin_court_id]);

           if ($verify_stmt->rowCount() > 0) {
                // Get booking details for email
                $booking_query = "SELECT b.*, c.court_name, c.court_location, c.hourly_rate 
                                  FROM bookings b 
                                  JOIN courts c ON b.court_id = c.court_id 
                                  WHERE b.booking_id = ?";
                $booking_stmt = executeQuery($booking_query, [$booking_id]);
                $booking_details = $booking_stmt->fetch(PDO::FETCH_ASSOC);

                // Update booking status
                $update_query = "UPDATE bookings SET status = ? WHERE booking_id = ?";
                executeQuery($update_query, [$new_status, $booking_id]);

                // Send email notification
                if ($new_status === 'Confirmed' && $booking_details) {
                    sendBookingConfirmationEmail($booking_details);
                }

                $success_message = "Booking " . strtolower($new_status) . " successfully!";
            }
            else {
                $error_message = "Invalid booking or access denied.";
            }
        } catch (Exception $e) {
            $error_message = "Failed to update booking status.";
        }
    }
}

// Fetch court information
try {
    $court_query = "SELECT court_name, court_location FROM courts WHERE court_id = ?";
    $court_stmt = executeQuery($court_query, [$admin_court_id]);
    $court = $court_stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $court = ['court_name' => 'Unknown Court', 'court_location' => 'Unknown Location'];
}

// Fetch booking statistics
try {
    $stats_query = "SELECT 
                      SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
                      SUM(CASE WHEN status = 'Confirmed' THEN 1 ELSE 0 END) as confirmed,
                      SUM(CASE WHEN status = 'Declined' THEN 1 ELSE 0 END) as declined,
                      COUNT(*) as total
                    FROM bookings WHERE court_id = ?";
    $stats_stmt = executeQuery($stats_query, [$admin_court_id]);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $stats = ['pending' => 0, 'confirmed' => 0, 'declined' => 0, 'total' => 0];
}

// Fetch recent bookings
try {
    $bookings_query = "SELECT b.booking_id, b.name, b.contact_number, b.email, b.schedule_date, b.start_time, b.end_time, b.event_type, b.status, b.created_at, c.hourly_rate
                   FROM bookings b
                   JOIN courts c ON b.court_id = c.court_id
                   WHERE b.court_id = ? 
                   ORDER BY b.created_at DESC";
    $bookings_stmt = executeQuery($bookings_query, [$admin_court_id]);
    $bookings = $bookings_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $bookings = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - PaSked</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Commissioner:wght@400;700&display=swap" rel="stylesheet">s
</head>
<body>
    <!-- Header -->
    <header class="admin-header">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1><?php echo htmlspecialchars($court['court_name']); ?> - Admin Dashboard</h1>
                <p style="color: var(--text-secondary);">📍 <?php echo htmlspecialchars($court['court_location']); ?></p>
            </div>
            <div>
                <span style="color: var(--text-secondary); margin-right: 1rem;">
                    Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>
                </span>
                <a href="logout.php" class="btn btn-danger">Logout</a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container">
        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="admin-stats">
            <div class="stat-card">
                <div class="stat-number pending"><?php echo $stats['pending']; ?></div>
                <div class="stat-label">Pending Bookings</div>
            </div>
            <div class="stat-card">
                <div class="stat-number confirmed"><?php echo $stats['confirmed']; ?></div>
                <div class="stat-label">Confirmed Bookings</div>
            </div>
            <div class="stat-card">
                <div class="stat-number declined"><?php echo $stats['declined']; ?></div>
                <div class="stat-label">Declined Bookings</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: var(--accent-blue);"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Bookings</div>
            </div>
        </div>

        <!-- Search and Filter -->
        <div style="background: var(--bg-secondary); padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem; border: 1px solid var(--border-color);">
            <div style="display: grid; grid-template-columns: 1fr auto auto; gap: 1rem; align-items: end;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="searchInput" class="form-label">Search Bookings</label>
                    <input type="text" id="searchInput" class="form-input" 
                           placeholder="Search by name, email, or event type..." 
                           onkeyup="searchBookings()">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="statusFilter" class="form-label">Filter by Status</label>
                    <select id="statusFilter" class="form-select" onchange="filterByStatus(this.value)">
                        <option value="all">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="declined">Declined</option>
                    </select>
                </div>
                <button onclick="location.reload()" class="btn btn-primary">Refresh</button>
            </div>
        </div>

        <!-- Quick Actions Section -->
        <div class="quick-actions" style="background: var(--bg-secondary); padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem; border: 1px solid var(--border-color); text-align: center;">
            <h3 style="margin-bottom: 1rem; color: var(--text-primary);">📊 Quick Actions</h3>
            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <a href="schedule.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                    📅 View Weekly Schedule
                </a>
                <button onclick="location.reload()" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                    🔄 Refresh Dashboard
                </button>
            </div>
        </div>


        <!-- Bookings Table -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Customer Details</th>
                        <th>Schedule</th>
                        <th>Event Type</th>
                        <th>Amount Due</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($bookings)): ?>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($booking['name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($booking['contact_number']); ?></small><br>
                                    <small><?php echo htmlspecialchars($booking['email']); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo date('M j, Y', strtotime($booking['schedule_date'])); ?></strong><br>
                                    <small><?php echo date('g:i A', strtotime($booking['start_time'])); ?> - <?php echo date('g:i A', strtotime($booking['end_time'])); ?></small><br>
                                    <small style="color: var(--text-secondary);">Booked: <?php echo date('M j, g:i A', strtotime($booking['created_at'])); ?></small>
                                </td>
                                <td>
                                    <span style="padding: 0.25rem 0.5rem; background: var(--bg-tertiary); border-radius: 4px; font-size: 0.8rem;">
                                        <?php echo htmlspecialchars($booking['event_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    // Calculate amount
                                    $start_hour = strtotime($booking['start_time']);
                                    $end_hour = strtotime($booking['end_time']);
                                    $duration_hours = ($end_hour - $start_hour) / 3600;
                                    $hourly_rate = $booking['hourly_rate'] ?? 500;
                                    $total_amount = $duration_hours * $hourly_rate;
                                    ?>
                                    <strong style="color: var(--accent-yellow);">₱<?php echo number_format($total_amount, 2); ?></strong><br>
                                    <small style="color: var(--text-secondary);">
                                        <?php echo number_format($duration_hours, 1); ?>h × ₱<?php echo number_format($hourly_rate, 2); ?>/h
                                    </small>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($booking['status']); ?>">
                                        <?php echo htmlspecialchars($booking['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($booking['status'] === 'Pending'): ?>
                                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('Confirm this booking?')">
                                                <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                                <input type="hidden" name="action" value="confirm">
                                                <button type="submit" class="btn btn-success" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;">
                                                    Confirm
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('Decline this booking?')">
                                                <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                                <input type="hidden" name="action" value="decline">
                                                <button type="submit" class="btn btn-danger" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;">
                                                    Decline
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: var(--text-secondary); font-size: 0.8rem;">
                                            <?php echo ($booking['status'] === 'Confirmed') ? '✓ Confirmed' : '✗ Declined'; ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 3rem; color: var(--text-secondary);">
                                No bookings found for your court yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Quick Actions -->
        <div style="margin-top: 2rem; text-align: center;">
            <a href="../index.php" class="btn btn-primary" style="margin-right: 1rem;">
                View Court on Main Site
            </a>
            <a href="logout.php" class="btn btn-danger">
                Logout
            </a>
        </div>
    </main>

    <script src="../assets/js/main.js"></script>
</body>
</html>