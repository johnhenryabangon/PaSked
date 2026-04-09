<?php
include 'config/db_config.php';

// Validate and get court_id from URL
if (!isset($_GET['court_id']) || !is_numeric($_GET['court_id'])) {
    die("Invalid court ID.");
}
$court_id = intval($_GET['court_id']);

try {
    // Fetch court details from database
    $query = "SELECT court_name, court_location, court_image, hourly_rate, google_maps_url, description, sports 
             FROM courts WHERE court_id = :court_id LIMIT 1";
    $stmt = executeQuery($query, [':court_id' => $court_id]);
    $court = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$court) {
        die("Court not found.");
    }
} catch (Exception $e) {
    die("Error loading court details.");
}

// Get filename from DB column or use a default if null.
$image_filename_from_db = $court['court_image'] ?? 'court.jpg';

// Build the full path, ensuring the filename is safely encoded.
$court_image_path = 'assets/images/' . htmlspecialchars($image_filename_from_db);

// If the specific file doesn't exist, use the 'court.jpg' default.
if (!file_exists($court_image_path) || empty($court['court_image'])) {
    $court_image_path = 'assets/images/court.jpg'; 
}

// --- START: SCHEDULE LOGIC for Selected Week (Dynamic based on public_schedule.php) ---

// Handle week selection via GET parameter
$selected_week = $_GET['week'] ?? date('Y-m-d', strtotime('monday this week'));
$selected_date = new DateTime($selected_week);
// Ensure we start from Monday of the selected week
$selected_date->modify('monday this week');
$monday = $selected_date->format('Y-m-d');

// Calculate previous and next week for navigation
$prev_week = date('Y-m-d', strtotime($monday . ' -1 week'));
$next_week = date('Y-m-d', strtotime($monday . ' +1 week'));

// Display info for the week
$week_end_display = date('M j, Y', strtotime($monday . ' +6 day'));
$current_week_info = date('M j', strtotime($monday)) . ' - ' . $week_end_display;

$week_dates = [];
for ($i=0; $i<7; $i++) {
    $week_dates[] = date('Y-m-d', strtotime("$monday +$i day"));
}

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$time_slots = [];
// Time slots from 7 AM (7) to 11 PM (23), plus 12 AM (0) for completeness
for ($hour = 7; $hour <= 23; $hour++) { 
    $time_slots[] = $hour;
}
$time_slots[] = 0; // midnight 12AM

$schedule_data = [];
$schedule_error = null;
try {
    $bookings_query = "SELECT b.*, c.hourly_rate 
        FROM bookings b 
        LEFT JOIN courts c ON b.court_id = c.court_id
        WHERE b.court_id = ? AND b.schedule_date BETWEEN ? AND ?
        ORDER BY b.schedule_date, b.start_time";
    $stmt = executeQuery($bookings_query, [$court_id, $week_dates[0], $week_dates[6]]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($bookings as $booking) {
        $date = $booking['schedule_date'];
        $start_hour = (int) date('H', strtotime($booking['start_time']));
        $end_hour = (int) date('H', strtotime($booking['end_time']));
        if ($end_hour == 0) $end_hour = 24;

        $duration = ($end_hour - $start_hour);
        $hourly_rate = $booking['hourly_rate'] ?? $court['hourly_rate'];
        $amount = $duration * $hourly_rate;

        for ($h = $start_hour; $h < $end_hour; $h++) {
            $hour_key = ($h == 24) ? 0 : $h;
            $schedule_data[$date][$hour_key] = [
                'status'=>strtolower($booking['status']),
                'event'=>$booking['event_type'],
                'start_time'=>date('g:i A', strtotime($booking['start_time'])),
                'end_time'=>date('g:i A', strtotime($booking['end_time'])),
                'amount'=>$amount
            ];
        }
    }
} catch (Exception $e) {
    $schedule_error = 'Error loading schedule: ' . $e->getMessage();
}
// --- END: SCHEDULE LOGIC ---
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="icon" href="assets/images/favicon.ico" />
    <title>About <?php echo htmlspecialchars($court['court_name']); ?> - PaSked</title>
    <link rel="stylesheet" href="assets/css/style.css" />
    <link href="https://fonts.googleapis.com/css2?family=Commissioner:wght@400;700&display=swap" rel="stylesheet">
    
    <style>
        .schedule-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .schedule-header-schedule {
            background: rgba(89, 89, 171, 0.1);
            -webkit-backdrop-filter: blur(17px);
            backdrop-filter: blur(17px);
            padding: 2rem;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
            text-align: center;
        }

        .schedule-grid {
            display: grid;
            grid-template-columns: 100px repeat(7, 1fr);
            gap: 3px;
            background: var(--bg-secondary);
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            overflow-x: auto;
            margin-bottom: 2rem;
        }

        .grid-header {
            background: var(--bg-tertiary);
            padding: 0.75rem 0.5rem;
            text-align: center;
            font-weight: 600;
            color: var(--accent-blue);
            border-radius: 6px;
            font-size: 0.9rem;
        }

        .time-slot {
            background: var(--bg-tertiary);
            padding: 0.5rem;
            text-align: center;
            font-size: 0.8rem;
            color: var(--accent-yellow);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 45px;
            font-weight: 600;
        }

        .schedule-cell {
            min-height: 45px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        /* Status Colors */
        .available {
            background: var(--accent-green);
        }

        .pending {
            background: var(--accent-yellow);
        }

        .confirmed {
            background: var(--accent-red);
        }

        .declined {
            background: var(--text-secondary);
            opacity: 0.5;
        }

        .schedule-cell:hover {
            transform: scale(1.08);
            box-shadow: var(--shadow);
            z-index: 10;
        }

        .schedule-cell.available:hover {
            background: #4ac15a;
        }

        .schedule-cell.pending:hover {
            background: #fac84a;
        }

        .schedule-cell.confirmed:hover {
            background: #fa6155;
        }

        /* Tooltip */
        .tooltip {
            position: absolute;
            background: var(--bg-tertiary);
            color: var(--text-primary);
            padding: 1rem;
            border-radius: 8px;
            font-size: 0.85rem;
            box-shadow: var(--shadow);
            pointer-events: none;
            z-index: 1000;
            display: none;
            max-width: 280px;
            border: 1px solid var(--border-color);
        }

        .tooltip-event {
            color: var(--accent-yellow);
            margin-bottom: 0.25rem;
            font-style: italic;
        }

        .tooltip-time {
            color: var(--text-secondary);
            font-size: 0.8rem;
            margin-bottom: 0.25rem;
        }

        /* Legend */
        .legend {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
            background: rgba(89, 89, 171, 0.1);
            -webkit-backdrop-filter: blur(17px);
            backdrop-filter: blur(17px);
            border-radius: 6px;
            border: 1px solid var(--border-color);
        }

        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
        }

        @media (max-width: 768px) {
            .schedule-grid {
                font-size: 0.8rem;
                gap: 2px;
            }

            .week-navigation a {
                font-size: 0.6rem;
            }

            .week-navigation {
                flex-direction: row;
                flex-wrap: wrap;
                text-align: center;
            }

            .time-slot, .grid-header {
                font-size: 0.75rem;
                padding: 0.4rem 0.3rem;
            }

            .schedule-cell {
                min-height: 35px;
            }

            .legend {
                gap: 1rem;
            }
        }
    </style>
    </head>
<body>
    <header class="header">
        <div class="nav-container">
            <a href="index.php" class="logo">
                <img class="logo-icon" src="assets/images/paskedlogo.png" alt="PaSked Logo">
                PaSked
            </a>
            <nav>
                <ul class="nav-menu">
                    <li><a href="index.php">Back to Courts</a></li>
                </ul>
            </nav>
        </div>
    </header>
    <br><br><br>
    <main class="container" style="margin-top: 3rem;">
        <center>
        <h1><?php echo htmlspecialchars($court['court_name']); ?></h1><br>
        <img src="<?php echo $court_image_path; ?>" 
             alt="<?php echo htmlspecialchars($court['court_name']); ?>" 
             style="max-width:50%; height:auto; border-radius: 8px; box-shadow: 0 0 8px #aaa;" />
        </center>
        <p style="padding-top: 30px;"><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($court['description'])); ?></p>

        <p><strong>Sports Offered:</strong> <?php echo htmlspecialchars($court['sports']); ?></p>

        <p><strong>Location:</strong> <?php echo htmlspecialchars($court['court_location']); ?></p>
            
        <p><strong>Rate:</strong> ₱<?php echo number_format($court['hourly_rate'], 2); ?> per hour</p>
            

        <div style="margin: 2rem 0;">
            <?php if (!empty($court['google_maps_url'])): ?>
                <a href="<?php echo htmlspecialchars($court['google_maps_url']); ?>" 
                    target="_blank" class="btn btn-secondary" style="margin-right: 1rem;">
                    ➢ Get Directions
                </a>
            <?php else: ?>
                <span class="btn btn-disabled" style="margin-right: 1rem;">➤ Directions (Coming Soon)</span>
            <?php endif; ?>

            <a href="booking.php?court_id=<?php echo $court_id; ?>" class="btn btn-primary">
                🗒 Schedule Now
            </a>
        </div>

        <hr style="margin-top: 3rem; margin-bottom: 3rem; border-color: var(--border-color);">
        
        <div class="schedule-container" style="padding: 0 0;">
            <div class="schedule-header-schedule" style="padding: 1rem; margin-bottom: 1rem;">
                <h3>Weekly Schedule Overview</h3>
                
                <div class="week-navigation" style="display: flex; justify-content: center; align-items: center; gap: 2rem; margin-top: 1rem;">
                    
                    <p style="color: var(--accent-yellow); font-weight: 600; margin-bottom: 0; font-size: 1.2rem;">
                        <?php echo $current_week_info; ?>
                    </p>

                    <div class="week-picker">
                        <label for="weekSelect" style="color: var(--text-primary); font-size: 0.9rem;">Jump to date:</label>
                        <input type="date" id="weekSelect" value="<?php echo $monday; ?>" onchange="jumpToWeek(this.value, <?php echo $court_id; ?>)">
                    </div>

                    <a href="about.php?court_id=<?php echo $court_id; ?>&week=<?php echo $prev_week; ?>" 
                       class="nav-button" style="padding: 0.5rem 1rem;">
                        ← Previous Week
                    </a>

                    <a href="about.php?court_id=<?php echo $court_id; ?>&week=<?php echo date('Y-m-d', strtotime('monday this week')); ?>" 
                        class="nav-button secondary" style="padding: 0.5rem 1rem;">
                        This Week
                    </a>
                    
                    <a href="about.php?court_id=<?php echo $court_id; ?>&week=<?php echo $next_week; ?>" 
                       class="nav-button" style="padding: 0.5rem 1rem;">
                        Next Week →
                    </a>
                </div>
            </div>
            
             <div class="legend">
                <div class="legend-item"><div class="legend-color available"></div><span>Available</span></div>
                <div class="legend-item"><div class="legend-color pending" style="background: var(--accent-yellow);"></div><span>Pending</span></div>
                <div class="legend-item"><div class="legend-color confirmed" style="background: var(--accent-red);"></div><span>Confirmed</span></div>
                <div class="legend-item"><div class="legend-color declined" style="background: #515860;"></div><span>Declined</span></div>
            </div>

            <div style="text-align:center; margin-top:1rem; color: var(--text-secondary); padding-bottom: 1rem;">
                <p><strong>Hover over colored cells</strong> to see booking details</p>
            </div>

            <div class="schedule-grid" id="scheduleGrid" style="margin-bottom: 10rem;">
                <?php if ($schedule_error): ?>
                    <div style="grid-column:1/-1; text-align:center; color: var(--accent-red); padding: 2rem;">
                        <?php echo $schedule_error; ?>
                    </div>
                <?php else: ?>
                    <?php
                    // Grid header time labels
                    echo '<div class="grid-header">Time</div>';
                    for ($i=0; $i<7; $i++) {
                        $day_short = substr($days[$i],0,3);
                        $date_display = date('M j', strtotime($week_dates[$i]));
                        $is_today = $week_dates[$i] == date('Y-m-d') ? ' (Today)' : '';
                        echo '<div class="grid-header">'.$day_short.'<br><small style="font-size:0.7rem;opacity:0.8;">'.$date_display.$is_today.'</small></div>';
                    }

                    // Grid rows for each time slot
                    foreach ($time_slots as $hour) {
                        if ($hour == 0) $time_display = '12 AM';
                        elseif ($hour == 12) $time_display = '12 PM';
                        elseif ($hour > 12) $time_display = ($hour-12).' PM';
                        else $time_display = $hour.' AM';

                        echo '<div class="time-slot">'.$time_display.'</div>';

                        foreach ($week_dates as $date) {
                            $booking_data = $schedule_data[$date][$hour] ?? null;

                            if ($booking_data) {
                                $status = $booking_data['status'];
                                $tooltip_data = htmlspecialchars(json_encode([
                                    'event'=>$booking_data['event'],
                                    'time'=>$booking_data['start_time'].' - '.$booking_data['end_time']
                                ]));

                                echo '<div class="schedule-cell '.$status.'" data-tooltip=\''.$tooltip_data.'\'></div>';
                            } else {
                                echo '<div class="schedule-cell available" title="Available"></div>';
                            }
                        }
                    }
                    ?>
                <?php endif; ?>
            </div>
        </div>

    </main>
    
    <div id="tooltip" class="tooltip"></div>

    <footer style="background: var(--bg-secondary); padding: 2rem 0; margin-top: 4rem; border-top: 1px solid var(--border-color); text-align: center; color: var(--text-secondary);">
        &copy; 2025 PaSked - Sports Court Booking System for Metro Manila
    </footer>

    <script>
        // CORRECTED: Added the missing jumpToWeek function
        function jumpToWeek(date, courtId) {
            if (date) {
                // Redirects to the current page with the new week date and court ID
                window.location.href = `about.php?court_id=${courtId}&week=${date}`;
            }
        }
        
        const tooltip = document.getElementById('tooltip');
        // Select cells that have booking data
        const cells = document.querySelectorAll('.schedule-cell[data-tooltip]'); 
        
        cells.forEach(cell => {
            cell.addEventListener('mouseenter', function(e) {
                // Only show tooltip for non-available cells
                if (!this.classList.contains('available')) {
                    const data = JSON.parse(this.getAttribute('data-tooltip'));
                    tooltip.innerHTML = `
                        <div class="tooltip-event">${data.event}</div>
                        <div class="tooltip-time">${data.time}</div>
                    `;
                    tooltip.style.display = 'block';
                    updateTooltipPosition(e);
                }
            });
            cell.addEventListener('mousemove', updateTooltipPosition);
            cell.addEventListener('mouseleave', function() {
                tooltip.style.display = 'none';
            });
        });

        function updateTooltipPosition(e) {
            const tooltipRect = tooltip.getBoundingClientRect();
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;

            let left = e.pageX + 15;
            let top = e.pageY - 15;

            // Adjust position if it goes off the right edge
            if (left + tooltipRect.width > viewportWidth) {
                left = e.pageX - tooltipRect.width - 15;
            }
            // Adjust position if it goes off the bottom edge
            if (top + tooltipRect.height > viewportHeight) {
                top = e.pageY - tooltipRect.height - 15;
            }
            tooltip.style.left = left + 'px';
            tooltip.style.top = top + 'px';
        }
    </script>
</body>
</html>