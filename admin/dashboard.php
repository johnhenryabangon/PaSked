<?php
session_start();
// Define the base path for safe, absolute redirects
// You must change '/PaSked/admin/dashboard.php' if your project path is different
define('DASHBOARD_PATH', '/PaSked/admin/dashboard.php');
// You must change this if your login page is not one folder up (i.e., in /PaSked/)
define('LOGIN_PATH', '../login.php'); 

// Ensure the user is logged in as admin, otherwise redirect
if (!isset($_SESSION['admin_id'])) {
    header('Location: ' . LOGIN_PATH); 
    exit();
}

include '../config/db_config.php';

$admin_court_id = $_SESSION['admin_court_id'];
$success_message = '';
$error_message = '';

// --- CONFIGURATION ---
// Define the path where the IDs are stored
define('ID_UPLOAD_PATH', '../assets/images/id/'); 
// --- END CONFIGURATION ---

// 1. MESSAGE RETRIEVAL (PRG Implementation)
// Check for messages passed via GET after a redirect
if (isset($_GET['success'])) {
    $success_message = htmlspecialchars($_GET['success']);
}
if (isset($_GET['error'])) {
    $error_message = htmlspecialchars($_GET['error']);
}


// 2. FETCH COURT INFO (Needed for header display and POST handler)
try {
    $court_query = "SELECT * FROM courts WHERE court_id = ?";
    $court_stmt = executeQuery($court_query, [$admin_court_id]);
    $court = $court_stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Initialize $court with safe defaults if query fails
    $court = ['court_name' => 'Unknown Court', 'court_location' => 'Unknown Location', 'court_image' => '', 'hourly_rate' => 500, 'description' => '', 'google_maps_url' => '', 'sports' => ''];
}

// 3. HANDLE EDIT INFO FORM SUBMISSION (PRG Implemented)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_info'])) {
    $court_name = trim($_POST['court_name']);
    $court_location = trim($_POST['court_location']);
    $hourly_rate = floatval($_POST['hourly_rate']);
    $google_maps_url = trim($_POST['google_maps_url']);
    $description = trim($_POST['description']);
    $sports = trim($_POST['sports']);
    $current_error_message = ''; // Use a temporary variable for errors before redirect

    $court_image = $court['court_image'] ?? '';

    // Handle court image upload
    if (!empty($_FILES['court_image']['name'])) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (in_array($_FILES['court_image']['type'], $allowed_types)) {
            $upload_dir = '../assets/images/';
            $new_filename = time() . '_' . basename($_FILES['court_image']['name']);
            if (move_uploaded_file($_FILES['court_image']['tmp_name'], $upload_dir . $new_filename)) {
                $court_image = $new_filename;
            } else {
                $current_error_message = "Failed to upload court image.";
            }
        } else {
            $current_error_message = "Invalid image file type.";
        }
    }

    if (empty($current_error_message)) {
        try {
            $update_court_sql = "UPDATE courts SET court_name=?, court_location=?, court_image=?, hourly_rate=?, google_maps_url=?, description=?, sports=? WHERE court_id=?";
            executeQuery($update_court_sql, [$court_name, $court_location, $court_image, $hourly_rate, $google_maps_url, $description, $sports, $admin_court_id]);

            // Update admin password if provided
            if (!empty($_POST['password'])) {
                $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $update_admin_sql = "UPDATE admins SET password=? WHERE admin_id=?";
                executeQuery($update_admin_sql, [$hashed_password, $_SESSION['admin_id']]);
            }

            // Update admin username if provided
            if (!empty($_POST['admin_username'])) {
                $new_username = trim($_POST['admin_username']);
                $update_username_sql = "UPDATE admins SET username = ? WHERE admin_id = ?";
                executeQuery($update_username_sql, [$new_username, $_SESSION['admin_id']]);
                $_SESSION['admin_username'] = $new_username;
            }

            // PRG SUCCESS: Redirect to prevent form resubmission
            header('Location: ' . DASHBOARD_PATH . '?success=' . urlencode("Information updated successfully."));
            exit();

        } catch (Exception $e) {
            $current_error_message = "Failed to update info: " . $e->getMessage();
        }
    }
    
    // PRG FAILURE: Redirect with error message
    if (!empty($current_error_message)) {
        header('Location: ' . DASHBOARD_PATH . '?error=' . urlencode($current_error_message));
        exit();
    }
}


// 4. BOOKING CONFIRMATION EMAIL FUNCTION (No change needed)
function sendBookingConfirmationEmail($booking) {
    global $db_config; 
    
    $start_hour = strtotime($booking['start_time']);
    $end_hour = strtotime($booking['end_time']);
    $duration_hours = ($end_hour - $start_hour) / 3600;
    $hourly_rate = $booking['hourly_rate'] ?? 500;
    $total_amount = $duration_hours * $hourly_rate;
    
    $to = $booking['email'];
    $subject = "Booking Confirmed - " . $booking['court_name'];
    
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
    
    $headers = "From: noreply@pasked.com\r\n";
    $headers .= "Reply-To: noreply@pasked.com\r\n";
    
    if (mail($to, $subject, $message, $headers)) {
        return true;
    } else {
        return false;
    }
}

// 5. HANDLE BOOKING STATUS UPDATES (PRG Implemented)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $booking_id = (int)$_POST['booking_id'];
    $action = $_POST['action'];
    $current_message = '';

    if (in_array($action, ['confirm', 'decline', 'delete'])) {
        try {
            // Verify the booking belongs to this admin's court
            $verify_query = "SELECT booking_id FROM bookings WHERE booking_id = ? AND court_id = ?";
            $verify_stmt = executeQuery($verify_query, [$booking_id, $admin_court_id]);

           if ($verify_stmt->rowCount() > 0) {
                
                if ($action === 'delete') {
                    // --- DELETE LOGIC ---
                    $delete_query = "DELETE FROM bookings WHERE booking_id = ?";
                    executeQuery($delete_query, [$booking_id]);
                    $current_message = "Booking deleted successfully, the slot is now available.";

                } else {
                    // Existing confirm/decline logic
                    $new_status = ($action === 'confirm') ? 'Confirmed' : 'Declined';

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

                    $current_message = "Booking " . strtolower($new_status) . " successfully!";
                }
                
                // PRG SUCCESS: Redirect to prevent form resubmission
                header('Location: ' . DASHBOARD_PATH . '?success=' . urlencode($current_message));
                exit();
            }
            else {
                $current_message = "Invalid booking or access denied.";
                // PRG FAILURE: Redirect with error message
                header('Location: ' . DASHBOARD_PATH . '?error=' . urlencode($current_message));
                exit();
            }
        } catch (Exception $e) {
            $current_message = "Failed to update booking status or delete booking: " . $e->getMessage();
            // PRG EXCEPTION: Redirect with error message
            header('Location: ' . DASHBOARD_PATH . '?error=' . urlencode($current_message));
            exit();
        }
    }
}


// 6. DATA FETCHING (No change needed)

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

// Fetch recent bookings - MODIFIED TO INCLUDE ID_FILENAME
try {
    $bookings_query = "SELECT b.booking_id, b.name, b.contact_number, b.email, b.schedule_date, b.start_time, b.end_time, b.event_type, b.status, b.created_at, b.id_filename, c.hourly_rate
                    FROM bookings b
                    JOIN courts c ON b.court_id = c.court_id
                    WHERE b.court_id = ? 
                    ORDER BY b.created_at DESC";
    $bookings_stmt = executeQuery($bookings_query, [$admin_court_id]);
    $bookings = $bookings_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $bookings = [];
}

// Fetch admin username for edit modal display
try {
    $admin_query = "SELECT username FROM admins WHERE admin_id = ?";
    $admin_stmt = executeQuery($admin_query, [$_SESSION['admin_id']]);
    $admin_user = $admin_stmt->fetch(PDO::FETCH_ASSOC);
    $admin_username = $admin_user['username'] ?? '';
} catch (Exception $e) {
    $admin_username = '';
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin Dashboard - PaSked</title>
    <link rel="icon" href="../assets/images/favicon.ico" />
    <link rel="stylesheet" href="../assets/css/style.css" />
    <link href="https://fonts.googleapis.com/css2?family=Commissioner:wght@400;700&display=swap" rel="stylesheet" />

    <style>
        /* [Existing Styles remain here...] */
        .form-label {
          display: block;
          margin-bottom: var(--space-8);
          font-weight: var(--font-weight-medium);
          font-size: var(--font-size-sm);
        }

        label {
          display: block;
          width: 100%;
          margin-bottom: var(--space-8);
          font-size: var(--font-size-sm);
          font-weight: var(--font-weight-medium);
          color: var(--color-text);
        }

        input, select, textarea {
          display: block;
          width: 100%;
          min-height: 30px;
          padding: 5px 10px;
          margin-bottom: var(--space-12);
          border: 1px solid var(--color-border);
          border-radius: 10px;
          box-sizing: border-box;
          font-family: var(--font-family-base);
          font-size: var(--font-size-base);
          background-color: #FAF9F6;
          color: #000;
          transition: border-color var(--duration-fast) var(--ease-standard), box-shadow var(--duration-fast) var(--ease-standard);
        }

        textarea {
          font-family: var(--font-family-base);
          resize: vertical;
          min-height: 80px;
        }
    </style>

</head>
<body>
    <header class="admin-header">
        <div class="header-content-wrapper">
            <div class="header-info">
                <h1 class="header-title"><?php echo htmlspecialchars($court['court_name']); ?> - Admin Dashboard</h1>
                <p class="header-location">⚲ <?php echo htmlspecialchars($court['court_location']); ?></p>
            </div>

            <div class="header-controls">
                <span class="welcome-text">
                    Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>
                </span>

                <button id="openEditModal" class="btn btn-control" title="Admin Settings">🌣 Settings</button>
                <a href="logout.php" class="btn btn-control" title="Logout">↪ Logout</a>
            </div>
        </div>
    </header>

    <main class="container">
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>
        
        <div class="quick-actions" id="top" style="background: rgba(89, 89, 171, 0.1); -webkit-backdrop-filter: blur(17px);backdrop-filter: blur(17px); padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem; border: 1px solid var(--border-color); display: column;  text-align: center; align-items: center; justify-content: center;">
            <h3 style="margin-bottom: 1rem; color: var(--text-primary);">Quick Actions</h3>
            
            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <a href="schedule.php" class="btn btn-secondary" style="display: inline-flex; align-items: center;">
                    &ensp;&ensp;&ensp;🗓 Weekly Schedule&ensp;&ensp;&ensp;
                </a>

                <a href="../index.php" class="btn btn-info">
                    View Court on Homepage
                </a>
            </div>
        </div>
        

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

        <div style="background: rgba(89, 89, 171, 0.1);
    -webkit-backdrop-filter: blur(17px);
    backdrop-filter: blur(17px); padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem; border: 1px solid var(--border-color);">
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
                <button onclick="window.location.href = 'dashboard.php';" class="btn btn-primary">
    Refresh
</button>

            </div>
        </div>


        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Customer Details</th>
                        <th>Schedule</th>
                        <th>Event Type</th>
                        <th>Amount Due</th>
                        <th>Status</th>
                        <th>ID Proof</th> 
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($bookings)): ?>
                        <?php foreach ($bookings as $booking): ?>
                            <tr class="booking-row" data-status="<?php echo strtolower($booking['status']); ?>">
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
                                    <strong style="color: var(--accent-yellow); font-size:13px;">₱<?php echo number_format($total_amount, 2); ?></strong><br>
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
                                    <?php 
                                        $id_file = !empty($booking['id_filename']) 
                                            ? htmlspecialchars($booking['id_filename']) 
                                            : 'id_sample.jpg';
                                        // Construct the full URL path for the JavaScript function
                                        $id_url = ID_UPLOAD_PATH . $id_file;
                                    ?>

                                    <button type="button" 
                                       onclick="openIdImageModal('<?= $id_url ?>', '<?= $id_file ?>')"
                                       class="btn btn-info"
                                       style="font-size: 0.65rem; padding: 0.3rem 0.6rem;">
                                        View ID📄
                                    </button>
                                </td>

                                <td>
                                    <div style="display: flex; gap: 0.2rem; flex-wrap: wrap;">
                                                                    
                                        <?php if ($booking['status'] === 'Pending'): ?>
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('Confirm this booking?')">
                                                <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                                <input type="hidden" name="action" value="confirm">
                                                <button type="submit" class="btn btn-success" style="font-size: 0.65rem; padding: 0.4rem 0.5rem;">
                                                    Confirm
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('Decline this booking?')">
                                                <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                                <input type="hidden" name="action" value="decline">
                                                <button type="submit" class="btn btn-danger" style="font-size: 0.65rem; padding: 0.4rem 0.5rem;">
                                                    Decline
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color: var(--text-secondary); font-size: 0.65rem; padding: 0.4rem 0.5rem;">
                                                <?php echo ($booking['status'] === 'Confirmed') ? '✓ Confirmed' : '✗ Declined'; ?>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('WARNING: Are you sure you want to PERMANENTLY DELETE this booking? This action cannot be undone.')">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <a title="Delete Booking"><button type="submit" class="btn btn-control" style="font-size: 1.3rem; padding: 1px 0; margin-left: 0.5rem;">
                                                🗑
                                            </button></a>
                                        </form>
                                        </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-secondary);">
                                No bookings found for your court yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

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

        <div id="editModal" class="modal" style="display:none; position: fixed; z-index:3; top:0; left:0; width:100vw; height:100vh; background: rgba(0,0,0,0.8); overflow-y: auto;">
            <div class="modal-content">
                <span id="closeEditModal" style="position: absolute;  top: 10px; right: 15px; font-weight:bold; font-size: 28px; cursor: pointer;">&times;</span>
                <h2 style="margin-bottom: 2rem; text-align: center;">✎ Edit Court & Admin Info</h2>

                <form method="POST" enctype="multipart/form-data" onsubmit="return confirm('Update court and admin information?');">

                    <input type="hidden" name="update_info" value="1" />

                    <label for="court_name">Court Name </label>
                    <input type="text" id="court_name" name="court_name" value="<?= htmlspecialchars($court['court_name'] ?? '') ?>" required />
                    
                    <br>
                    <label for="court_location">Court Location </label>
                    <input type="text" id="court_location" placeholder="Street, City" name="court_location" value="<?= htmlspecialchars($court['court_location'] ?? '') ?>" required />
                    
                    <br>
                    <label for="court_image">Court Image (upload to replace) </label>
                    <input type="file" id="court_image" name="court_image" accept="image/*" />
                    <?php if (!empty($court['court_image'])): ?>
                        <p>Current Image:<br /><img src="../assets/images/<?= htmlspecialchars($court['court_image']) ?>" alt="Court Image" width="100" /></p>
                    <?php endif; ?>
                    
                    <br>
                    <label for="hourly_rate">Hourly Rate </label>
                    <input type="number" id="hourly_rate" name="hourly_rate" step="0.01" value="<?= htmlspecialchars($court['hourly_rate'] ?? '') ?>" required />
                    
                    <br>
                    <label for="google_maps_url">Google Maps URL</label>
                    <input type="url" id="google_maps_url" name="google_maps_url" value="<?= htmlspecialchars($court['google_maps_url'] ?? '') ?>" />

                    <br>
                    <label for="description">Description</label>
                    <textarea id="description" name="description"><?= htmlspecialchars($court['description'] ?? '') ?></textarea>

                    <br>
                    <label for="sports">Sports Offered</label>
                    <input type="text" id="sports" name="sports" value="<?= htmlspecialchars($court['sports'] ?? '') ?>" />
                    
                    <br>
                    <label for="admin_username">Admin Username</label>
                    <input type="text" id="admin_username" name="admin_username" value="<?= htmlspecialchars($admin_username) ?>" required /><br>


                    
                    <label for="password">New Password</label>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            placeholder="Leave it blank to keep current password" 
                            style="flex: 1;"
                        />
                        <button 
                            type="button" 
                            id="togglePassword" 
                            class="btn btn-secondary" 
                            style="padding: 6px 10px; font-size: 0.8rem;"
                        >
                            Show
                        </button>
                    </div>

                    <br>
                    <button type="submit" class="btn btn-primary" style="margin-top: 1rem;">Update Info</button>
                </form>
            </div>
        </div>

        <div id="idImageModal" class="modal" style="display:none; position: fixed; z-index:4; top:50%; left:50%; transform: translate(-50%, -50%); width:100vw; height:100vh; background: rgba(0,0,0,0.7); overflow: auto;">
            <div class="modal-content" style="max-width: 90vw; max-height: 90vh; width: auto;  margin-top: 30px; box-shadow: none; padding: 0;">
                <span id="closeIdImageModal" style="position: absolute; top: 10px; right: 30px; color: #fff; font-size: 40px; font-weight: bold; cursor: pointer; text-shadow: 0 0 5px rgba(0,0,0,0.5); ">&times;</span>
                <img id="modalIdImage" src="" alt="Customer ID Proof" class="id_img" style="display: block; width: auto; max-width: 100vh; max-height: 80vh; background: none; margin: 5vh auto 0; padding:3rem 0; border-radius: 8px;">
                <p id="modalIdFilename" style="text-align: center; color: #fff; padding-bottom: 30px;"></p>
            </div>
        </div>
    </main>

    <script src="../assets/js/main.js"></script>
    <script>
        // Modal logic
        document.getElementById("openEditModal").addEventListener("click", function() {
            document.getElementById("editModal").style.display = "block";
        });
        document.getElementById("closeEditModal").addEventListener("click", function() {
            document.getElementById("editModal").style.display = "none";
        });
        window.addEventListener("click", function(event) {
            if (event.target == document.getElementById("editModal")) {
                document.getElementById("editModal").style.display = "none";
            }
        });

        // Password visibility toggle
        const passwordInput = document.getElementById('password');
        const togglePasswordBtn = document.getElementById('togglePassword');

        if (passwordInput && togglePasswordBtn) {
          togglePasswordBtn.addEventListener('click', function () {
            const isHidden = passwordInput.type === 'password';
            passwordInput.type = isHidden ? 'text' : 'password';
            togglePasswordBtn.textContent = isHidden ? 'Hide' : 'Show';
          });
        }
        
        // --- Search and Filter Logic (from previous version, assuming it's in main.js or here) ---
        function searchBookings() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const table = document.querySelector('.table-container table');
            const tr = table.getElementsByTagName('tr');

            // Start from 1 to skip the header row (tr[0])
            for (let i = 1; i < tr.length; i++) {
                const row = tr[i];
                // Concatenate the text content of the first three data cells (Customer, Schedule, Event Type)
                const customer_text = row.cells[0].textContent.toLowerCase();
                const event_text = row.cells[2].textContent.toLowerCase();
                
                if (customer_text.includes(filter) || event_text.includes(filter)) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            }
        }
        
        function filterByStatus(status) {
            const rows = document.querySelectorAll('.booking-row');
            rows.forEach(row => {
                const rowStatus = row.getAttribute('data-status');
                if (status === 'all' || rowStatus === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        // --- End Search and Filter Logic ---

        // --- ID Image Preview Modal Logic ---
        const idImageModal = document.getElementById('idImageModal');
        const modalIdImage = document.getElementById('modalIdImage');
        const modalIdFilename = document.getElementById('modalIdFilename');
        const closeIdImageModal = document.getElementById('closeIdImageModal');

        /**
         * Opens the ID image preview modal.
         * @param {string} imageUrl The full path to the ID image.
         * @param {string} filename The original filename of the ID.
         */
        function openIdImageModal(imageUrl, filename) {
            modalIdImage.src = imageUrl;
            modalIdFilename.textContent = `File: ${filename}`;
            idImageModal.style.display = 'block';
        }

        // Close modal when clicking the 'x'
        closeIdImageModal.onclick = function() {
            idImageModal.style.display = 'none';
        };

        // Close modal when clicking outside the image container
        window.addEventListener("click", function(event) {
            if (event.target == idImageModal) {
                idImageModal.style.display = "none";
            }
        });
        // --- End ID Image Preview Modal Logic ---
    </script>
</body>
</html>