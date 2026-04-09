<?php
include 'config/db_config.php'; // Assumes executeQuery() and sanitizeInput() are defined here

// --- File Upload Configuration and Setup ---
// WARNING: Storing IDs in a publicly accessible folder (assets/images) is risky. 
// Ensure you have an .htaccess file inside assets/images/id/ with 'deny from all' to block direct access.
define('UPLOAD_DIR', __DIR__ . '/assets/images/id/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Ensure upload directory exists
if (!is_dir(UPLOAD_DIR)) {
    // Attempt to create the directory recursively
    if (!mkdir(UPLOAD_DIR, 0755, true)) {
        // Handle critical error if directory creation fails
        die("Fatal Error: Could not create upload directory " . UPLOAD_DIR);
    }
}
// --- End Configuration ---

$success_message = '';
$error_message = '';

// Get court ID from URL
$court_id = isset($_GET['court_id']) ? (int)$_GET['court_id'] : 0;

if ($court_id <= 0) {
    header('Location: index.php');
    exit();
}

// Fetch court information
try {
    $query = "SELECT court_id, court_name, court_location, court_image, hourly_rate, google_maps_url, sports 
              FROM courts WHERE court_id = ?";
    $stmt = executeQuery($query, [$court_id]);
    $court = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$court) {
        header('Location: index.php');
        exit();
    }

    // Parse sports string to array for dropdown
    $sportsOptions = [];
    if (!empty($court['sports'])) {
        $sportsOptions = array_map('trim', explode(',', $court['sports']));
    }

} catch (Exception $e) {
    $error_message = "Unable to load court information.";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input data
    $name = sanitizeInput($_POST['name']);
    $contact_number = sanitizeInput($_POST['contact_number']);
    $email = sanitizeInput($_POST['email']);
    $schedule_date = sanitizeInput($_POST['schedule_date']);
    $start_time = sanitizeInput($_POST['start_time']);
    $end_time = sanitizeInput($_POST['end_time']);
    $event_type = sanitizeInput($_POST['event_type']);
    $id_filename = null; // Will store the final filename if upload succeeds

    // --- Input Validation ---
    $errors = [];

    if (empty($name) || strlen($name) < 2) {
        $errors[] = "Name must be at least 2 characters long.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    if (!preg_match('/^(09|\+639)\d{9}$/', str_replace(' ', '', $contact_number))) {
        $errors[] = "Please enter a valid Philippine mobile number (e.g., 09XXXXXXXXX).";
    }

    if (empty($schedule_date) || $schedule_date < date('Y-m-d')) {
        $errors[] = "Please select a valid future date.";
    }

    // Server-side check for whole-hour time selection
    if (isset($start_time) && date('i', strtotime($start_time)) != '00') {
         $errors[] = "Start Time must be selected on the hour (e.g., 7:00, not 7:30).";
    }
    if (isset($end_time) && date('i', strtotime($end_time)) != '00') {
         $errors[] = "End Time must be selected on the hour (e.g., 8:00, not 7:30).";
    }

    if (empty($start_time) || empty($end_time) || strtotime($start_time) >= strtotime($end_time)) {
        $errors[] = "Please select valid start and end times, where start time is earlier than end time.";
    }

    // --- ID Upload and Validation ---
    if (isset($_FILES['id_file']) && $_FILES['id_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['id_file'];
        
        // 1. Check File Size
        if ($file['size'] > MAX_FILE_SIZE) {
            $errors[] = "The uploaded ID file is too large (max 5MB).";
        }

        // 2. Check File Type (Security: only JPG and PNG)
        // IMPORTANT: Removed 'application/pdf'
        $allowed_types = ['image/jpeg', 'image/png']; 
        if (!in_array($file['type'], $allowed_types)) {
            $errors[] = "Only JPG and PNG image files are allowed for the ID.";
        }

        // 3. Generate Unique File Name
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);

        // 1. Sanitize the user's name heavily (e.g., convert to lowercase, replace spaces with underscores, remove all non-alphanumeric)
        $sanitized_name = preg_replace('/[^a-z0-9]/', '_', strtolower($name));
        $sanitized_name = trim($sanitized_name, '_');

        // 2. Combine the sanitized name with the unique ID
        $safe_filename = uniqid('id_', true) . '_' . $sanitized_name . '.' . $file_extension;
        $target_path = UPLOAD_DIR . $safe_filename;

        // 4. Move File to Final Location
        if (empty($errors)) {
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                $id_filename = $safe_filename; // Store this unique filename in the DB
            } else {
                // Error moving file (server permissions/disk space)
                $errors[] = "Failed to save the ID file on the server. Please try again. Check server permissions.";
            }
        }

    } else if (isset($_FILES['id_file']) && $_FILES['id_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        // Other upload errors (e.g., server limits, partial upload)
        $errors[] = "An error occurred during file upload. Error code: " . $_FILES['id_file']['error'];
    } else {
        // Required check
        $errors[] = "Please upload a valid ID."; 
    }
    // --- End of ID Upload Block ---


    if (empty($errors)) {
        try {
            // Check for scheduling conflicts (Existing Code)
            $conflict_query = "SELECT start_time, end_time FROM bookings
                WHERE court_id = ? AND schedule_date = ?
                AND status != 'Declined'
                AND (
                    (start_time < ? AND end_time > ?)    -- overlaps
                    OR (start_time >= ? AND start_time < ?)
                    OR (end_time > ? AND end_time <= ?)
                )";
            $conflict_stmt = executeQuery($conflict_query, [
                $court_id, $schedule_date,
                $end_time, $start_time,   // overlap check
                $start_time, $end_time,   // starts inside
                $start_time, $end_time    // ends inside
            ]);
            $conflicting_bookings = $conflict_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($conflicting_bookings && count($conflicting_bookings) > 0) {
                // Remove the uploaded ID file as the booking failed conflict check
                if ($id_filename && file_exists(UPLOAD_DIR . $id_filename)) {
                    unlink(UPLOAD_DIR . $id_filename);
                }
                
                $messages = [];
                foreach ($conflicting_bookings as $b) {
                    $messages[] = "Booked from <strong>" .
                        date('g:i A', strtotime($b['start_time'])) .
                        "</strong> to <strong>" . date('g:i A', strtotime($b['end_time'])) . "</strong>";
                }
                if (count($messages) === 1) {
                    $error_message = "Sorry, your selected time overlaps an existing booking:<br>" . $messages[0];
                } else {
                    $error_message = "Sorry, your selected time overlaps these bookings:<ul><li>" .
                        implode("</li><li>", $messages) .
                        "</li></ul>";
                }
            } else {
                // No conflicts, insert booking
                // NOTE: Added id_filename to the INSERT query. You MUST update your database table.
                $insert_query = "INSERT INTO bookings (court_id, name, contact_number, email, schedule_date, start_time, end_time, event_type, status, id_filename) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?)";

                executeQuery($insert_query, [
                    $court_id, $name, $contact_number, $email, 
                    $schedule_date, $start_time, $end_time, $event_type, $id_filename // Pass the filename
                ]);
                

                // Calculate total amount based on time duration
                $start_hour = strtotime($start_time);
                $end_hour = strtotime($end_time);
                $duration_hours = ($end_hour - $start_hour) / 3600; // Convert seconds to hours

                // Get the court's hourly rate (assuming you have this in your court data)
                $hourly_rate = isset($court['hourly_rate']) ? $court['hourly_rate'] : 500; // Default rate if not set
                $total_amount = $duration_hours * $hourly_rate;

                // Create detailed success message with total amount
                $success_message = "<strong>🎉 Booking Submitted Successfully!</strong><br><br>
                            
                    <div style='
                        background: var(--bg-tertiary); 
                        padding: 1rem; 
                        border-radius: 8px; 
                        margin: 1rem 0;
                    '>
                            
                        <div style='
                            background: rgba(255, 223, 0, 0.2); 
                            padding: 0.7rem; 
                            border-radius: 6px; 
                            margin-bottom: 1rem;
                            font-size: 0.9rem;
                            color: var(--text-primary);
                        '>
                            📸 <strong>Please screenshot and save this receipt.</strong><br>
                            This will serve as your proof of booking.
                        </div>
                            
                        <strong>📋 Booking Details:</strong><br>
                        📅 Date: " . date('F j, Y', strtotime($schedule_date)) . "<br>
                        ⏰ Time: " . date('g:i A', strtotime($start_time)) . " - " . date('g:i A', strtotime($end_time)) . "<br>
                        ⏱️ Duration: " . number_format($duration_hours, 1) . " hour(s)<br>
                        💰 Rate: ₱" . number_format($hourly_rate, 2) . "/hour<br>
                        <br>
                            
                        <div style='
                            font-size: 1.2rem; 
                            color: var(--accent-yellow); 
                            font-weight: bold;
                        '>
                            💵 <strong>Total Amount Due: ₱" . number_format($total_amount, 2) . "</strong>
                        </div>
                            
                        <br>
                        <small style='color: var(--text-secondary);'>
                            💡 Please bring the exact amount in <strong>cash</strong> on your scheduled date.<br>
                            📋 Your booking is pending approval from the court admin.<br>
                            📧 You will receive confirmation via email or text message once approved.
                        </small>
                    </div>";

                // Clear form data
                $_POST = [];
            }

        } catch (Exception $e) {
            // Remove the uploaded ID file if the database insert failed
            if ($id_filename && file_exists(UPLOAD_DIR . $id_filename)) {
                unlink(UPLOAD_DIR . $id_filename);
            }
            $error_message = "Failed to submit booking. Please try again. Database Error: " . $e->getMessage();
        }
    } else {
        // If validation errors occurred, remove the uploaded file (if it reached the move_uploaded_file step)
        if ($id_filename && file_exists(UPLOAD_DIR . $id_filename)) {
            unlink(UPLOAD_DIR . $id_filename);
        }
        $error_message = implode('<br>', $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book <?php echo htmlspecialchars($court['court_name'] ?? ''); ?> - PaSked</title>
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

    <main class="container">
        <?php if ($court): ?>
            <div class="page-title">
                <br><br><br><br>
                <h1>Book <?php echo htmlspecialchars($court['court_name']); ?></h1>
                <p>📍 <?php echo htmlspecialchars($court['court_location']); ?></p>
                <p style="color: var(--accent-yellow); font-size: 1.2rem; font-weight: bold;">
                    💰 ₱<?php echo number_format($court['hourly_rate'], 2); ?> per hour
                </p>
            </div>


            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-error" style="margin-top: 3rem;">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <div class="form-container">
                <div style="text-align: right;">
                    <a href="public_schedule.php?court_id=<?= htmlspecialchars($court_id) ?>" class="btn btn-control" style="margin-top: 1rem;">
                       ↗View Available Slot
                    </a>
                </div>
                    
                <form id="bookingForm" method="POST" action="" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="name" class="form-label">Name *</label>
                        <input type="text" id="name" name="name" class="form-input" placeholder="Full Name"
                               value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" 
                               required>
                    </div>

                    <div class="form-group">
                        <label for="contact_number" class="form-label">Contact Number *</label>
                        <input type="tel" id="contact_number" name="contact_number" class="form-input" 
                               placeholder="09XXXXXXXXX" 
                               value="<?php echo htmlspecialchars($_POST['contact_number'] ?? ''); ?>" 
                               required>
                    </div>

                    <div class="form-group">
                        <label for="email" class="form-label">Email Address *</label>
                        <input type="email" id="email" name="email" class="form-input" placeholder="your_email@gmail.com"
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                               required>
                    </div>

                    <div class="form-group">
                        <label for="schedule_date" class="form-label">Schedule Date *</label>
                        <input type="date" id="schedule_date" name="schedule_date" class="form-input" 
                               value="<?php echo htmlspecialchars($_POST['schedule_date'] ?? ''); ?>" 
                               min="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="start_time" class="form-label">Start Time *</label>
                            <input type="time" id="start_time" name="start_time" class="form-input" 
                                   value="<?php echo htmlspecialchars($_POST['start_time'] ?? ''); ?>" 
                                   required step="3600">
                        </div>

                        <div class="form-group">
                            <label for="end_time" class="form-label">End Time *</label>
                            <input type="time" id="end_time" name="end_time" class="form-input" 
                                   value="<?php echo htmlspecialchars($_POST['end_time'] ?? ''); ?>" 
                                   required step="3600">
                        </div>
                    </div>

                    <div class="form-group">
					    <label for="event_type" class="form-label">Type of Event</label>
					    <select name="event_type" id="event_type" class="form-select" required>
						    <option value="">Select event type</option>
						    <?php foreach ($sportsOptions as $sport): ?>
						        <option value="<?php echo htmlspecialchars($sport); ?>"
						          <?php echo (isset($_POST['event_type']) && $_POST['event_type'] === $sport) ? 'selected' : ''; ?>>
						          <?php echo htmlspecialchars($sport); ?>
						        </option>
						    <?php endforeach; ?>
						    <option value="Other" <?php echo (isset($_POST['event_type']) && $_POST['event_type'] === 'Other') ? 'selected' : ''; ?>>Other</option>
						</select>
						
					</div>
                    
                    <div class="form-group">
                        <label for="id_file" class="form-label" >Upload Valid ID <small>(e.g. Brgy. ID, School ID, Driver's License)</small> *</label>
                        <input type="file" id="id_file" name="id_file" class="form-input" style="color: var(--text-primary); border-color: var(--text-primary);" 
                               accept="image/jpeg,image/png"
                               required>
                        <small style="color: var(--text-secondary);">Max size: 5MB. Formats: JPG, PNG only.</small><br>
                        <small style="color:#ffd700;"><i>Note:</i>  The uploaded ID must correspond to the person who made this reservation. Failure to comply with this requirement will result in the immediate decline or cancellation of the booking.</small>
                    </div>


                    <div class="form-group" style="margin-top: 2rem;">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            Submit Booking Request
                        </button>
                    </div>

                    <div style="margin-top: 1rem; font-size: 0.9rem; color: var(--text-secondary); text-align: center;">
                        <p>* Required fields</p>
                        <p>Your booking will be reviewed by the court admin. You will receive a confirmation via email or text message.</p>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="alert alert-error">
                <p>Court not found. <a href="index.php" style="color: var(--accent-blue);">Return to homepage</a></p>
            </div>
        <?php endif; ?>
    </main>

    <script src="assets/js/main.js"></script>
</body>
</html>