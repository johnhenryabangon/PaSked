<?php
session_start();
include '../config/db_config.php';

// Force Court ID 1 for testing
$court_id = 1;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interactive Schedule Chart Test</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { 
            background: #0d1117; 
            color: #f0f6fc; 
            padding: 2rem; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .chart-container {
            background: #161b22;
            padding: 2rem;
            border-radius: 12px;
            border: 1px solid #30363d;
            margin-bottom: 2rem;
        }

        .chart-header {
            margin-bottom: 2rem;
            text-align: center;
        }

        .schedule-grid {
            display: grid;
            grid-template-columns: 100px repeat(7, 1fr);
            gap: 3px;
            max-width: 100%;
            overflow-x: auto;
            background: #21262d;
            padding: 1rem;
            border-radius: 8px;
        }

        .grid-header {
            background: #30363d;
            padding: 0.75rem 0.5rem;
            text-align: center;
            font-weight: 600;
            color: #58a6ff;
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .time-slot {
            background: #30363d;
            padding: 0.5rem;
            text-align: center;
            font-size: 0.8rem;
            color: #f9c23c;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
        }

        .schedule-cell {
            min-height: 40px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        /* Status Colors */
        .available {
            background: #3fb950; /* Green */
        }

        .pending {
            background: #f9c23c; /* Yellow */
        }

        .confirmed {
            background: #f85149; /* Red */
        }

        .declined {
            background: #6e7681; /* Gray */
            opacity: 0.6;
        }

        .schedule-cell:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            z-index: 10;
        }

        /* Tooltip */
        .tooltip {
            position: absolute;
            background: #21262d;
            color: #f0f6fc;
            padding: 1rem;
            border-radius: 8px;
            font-size: 0.85rem;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.4);
            pointer-events: none;
            z-index: 1000;
            display: none;
            max-width: 250px;
            border: 1px solid #30363d;
        }

        .tooltip-name {
            color: #58a6ff;
            font-weight: bold;
            margin-bottom: 0.25rem;
        }

        .tooltip-event {
            color: #f9c23c;
            margin-bottom: 0.25rem;
        }

        .tooltip-time {
            color: #8b949e;
            font-size: 0.8rem;
            margin-bottom: 0.25rem;
        }

        .tooltip-amount {
            color: #3fb950;
            font-weight: bold;
            font-size: 0.9rem;
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
        }

        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
        }

        .test-info {
            background: #21262d;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            border: 1px solid #30363d;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="test-info">
        <h1>🧪 Interactive Schedule Chart Test</h1>
        <p><strong>Testing with Court ID:</strong> <?php echo $court_id; ?></p>
        <p>Hover over colored cells to see booking details!</p>
    </div>

    <div class="chart-container">
        <div class="chart-header">
            <h2>📅 Weekly Schedule Overview</h2>
            <p>Week of: <?php echo date('M j', strtotime('monday this week')) . ' - ' . date('M j, Y', strtotime('sunday this week')); ?></p>
        </div>

        <div class="schedule-grid" id="scheduleGrid">
            <!-- Grid will be populated by PHP -->
            <?php
            try {
                // Get Monday of this week
                $monday = date('Y-m-d', strtotime('monday this week'));

                // Days of the week
                $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

                // Time slots (7 AM to 10 PM)
                $time_slots = [];
                for ($hour = 7; $hour <= 22; $hour++) {
                    $time_slots[] = $hour;
                }

                // Get week dates
                $week_dates = [];
                for ($i = 0; $i < 7; $i++) {
                    $week_dates[] = date('Y-m-d', strtotime($monday . " + $i day"));
                }

                // Get all bookings for this week
                $bookings_query = "SELECT b.*, c.hourly_rate 
                                  FROM bookings b 
                                  LEFT JOIN courts c ON b.court_id = c.court_id
                                  WHERE b.court_id = ? AND b.schedule_date BETWEEN ? AND ?
                                  ORDER BY b.schedule_date, b.start_time";

                $stmt = executeQuery($bookings_query, [$court_id, $week_dates[0], $week_dates[6]]);
                $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Group bookings by date and hour
                $schedule_data = [];
                foreach ($bookings as $booking) {
                    $date = $booking['schedule_date'];
                    $start_hour = (int)date('H', strtotime($booking['start_time']));
                    $end_hour = (int)date('H', strtotime($booking['end_time']));

                    // Calculate amount
                    $duration = ($end_hour - $start_hour);
                    $hourly_rate = $booking['hourly_rate'] ?? 500;
                    $amount = $duration * $hourly_rate;

                    // Fill all hours for this booking
                    for ($h = $start_hour; $h < $end_hour; $h++) {
                        $schedule_data[$date][$h] = [
                            'status' => strtolower($booking['status']),
                            'name' => $booking['name'],
                            'event' => $booking['event_type'],
                            'start_time' => date('g:i A', strtotime($booking['start_time'])),
                            'end_time' => date('g:i A', strtotime($booking['end_time'])),
                            'amount' => $amount
                        ];
                    }
                }

                // Generate grid header
                echo '<div class="grid-header">Time</div>';
                foreach ($days as $day) {
                    echo '<div class="grid-header">' . substr($day, 0, 3) . '</div>';
                }

                // Generate grid rows
                foreach ($time_slots as $hour) {
                    // Time slot label
                    $time_display = ($hour == 12) ? '12 PM' : (($hour > 12) ? ($hour - 12) . ' PM' : $hour . ' AM');
                    echo '<div class="time-slot">' . $time_display . '</div>';

                    // Cells for each day
                    foreach ($week_dates as $date) {
                        $booking_data = $schedule_data[$date][$hour] ?? null;

                        if ($booking_data) {
                            $status = $booking_data['status'];
                            $tooltip_data = htmlspecialchars(json_encode([
                                'name' => $booking_data['name'],
                                'event' => $booking_data['event'],
                                'time' => $booking_data['start_time'] . ' - ' . $booking_data['end_time'],
                                'amount' => number_format($booking_data['amount'], 2)
                            ]));

                            echo '<div class="schedule-cell ' . $status . '" data-tooltip=\'' . $tooltip_data . '\'></div>';
                        } else {
                            echo '<div class="schedule-cell available"></div>';
                        }
                    }
                }

            } catch (Exception $e) {
                echo '<div style="grid-column: 1 / -1; text-align: center; color: #f85149;">Error: ' . $e->getMessage() . '</div>';
            }
            ?>
        </div>

        <!-- Legend -->
        <div class="legend">
            <div class="legend-item">
                <div class="legend-color available"></div>
                <span>Available</span>
            </div>
            <div class="legend-item">
                <div class="legend-color pending"></div>
                <span>Pending</span>
            </div>
            <div class="legend-item">
                <div class="legend-color confirmed"></div>
                <span>Confirmed</span>
            </div>
        </div>
    </div>

    <!-- Tooltip -->
    <div id="tooltip" class="tooltip"></div>

    <div style="text-align: center; margin-top: 2rem;">
        <a href="dashboard.php" style="color: #58a6ff; text-decoration: none;">← Back to Dashboard</a>
    </div>

    <script>
        // Tooltip functionality
        const tooltip = document.getElementById('tooltip');
        const cells = document.querySelectorAll('.schedule-cell[data-tooltip]');

        cells.forEach(cell => {
            cell.addEventListener('mouseenter', function(e) {
                const data = JSON.parse(this.getAttribute('data-tooltip'));

                tooltip.innerHTML = `
                    <div class="tooltip-name">${data.name}</div>
                    <div class="tooltip-event">${data.event}</div>
                    <div class="tooltip-time">${data.time}</div>
                    <div class="tooltip-amount">Amount: ₱${data.amount}</div>
                `;

                tooltip.style.display = 'block';
                updateTooltipPosition(e);
            });

            cell.addEventListener('mousemove', updateTooltipPosition);

            cell.addEventListener('mouseleave', function() {
                tooltip.style.display = 'none';
            });
        });

        function updateTooltipPosition(e) {
            tooltip.style.left = (e.pageX + 15) + 'px';
            tooltip.style.top = (e.pageY - 15) + 'px';
        }
    </script>
</body>
</html>