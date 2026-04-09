<?php
session_start();
include 'config/db_config.php';

// No login check for public schedule

// Get court ID from query or default (you can customize)
$court_id = $_GET['court_id'] ?? null;

if (!$court_id) {
    echo "Court not specified.";
    exit();
}

// Get court information
try {
    $court_query = "SELECT court_name, court_location, hourly_rate FROM courts WHERE court_id = ?";
    $court_stmt = executeQuery($court_query, [$court_id]);
    $court = $court_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$court) {
        echo "Court not found.";
        exit();
    }
} catch (Exception $e) {
    $court = ['court_name' => 'Unknown Court', 'court_location' => 'Unknown Location', 'hourly_rate' => 500];
}

// Handle week selection
$selected_week = $_GET['week'] ?? date('Y-m-d', strtotime('monday this week'));
$selected_date = new DateTime($selected_week);
$selected_date->modify('monday this week');
$monday = $selected_date->format('Y-m-d');

$prev_week = date('Y-m-d', strtotime($monday . ' -1 week'));
$next_week = date('Y-m-d', strtotime($monday . ' +1 week'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Public Weekly Schedule - <?php echo htmlspecialchars($court['court_name']); ?></title>
    <link rel="icon" href="assets/images/favicon.ico" />
    <link rel="stylesheet" href="assets/css/style.css" />
    <link href="https://fonts.googleapis.com/css2?family=Commissioner:wght@400;700&display=swap" rel="stylesheet" />

    <style>
        .schedule-page {
            background: var(--bg-primary);
            min-height: 100vh;
        }

        .schedule-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .schedule-header {
            background: rgba(89, 89, 171, 0.1);
            -webkit-backdrop-filter: blur(17px);
            backdrop-filter: blur(17px);
            padding: 2rem;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
            text-align: center;
        }

        .week-navigation {
            background: rgba(89, 89, 171, 0.1);
            -webkit-backdrop-filter: blur(17px);
            backdrop-filter: blur(17px);
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .week-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .week-picker {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .week-picker input[type="week"] {
            padding: 0.5rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background: var(--bg-tertiary);
            color: var(--text-primary);
            font-size: 0.9rem;
        }

        .week-picker input[type="date"] {
            padding: 0.5rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background: var(--bg-tertiary);
            color: var(--text-primary);
            font-size: 0.9rem;
        }

        .nav-button {
            padding: 0.5rem 1rem;
            background: var(--accent-blue);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }

        .nav-button:hover {
            background: #4a90e2;
            transform: translateY(-1px);
        }

        .nav-button.secondary {
            background: var(--text-secondary);
        }

        .nav-button.secondary:hover {
            background: #8b949e;
        }

        .current-week-info {
            text-align: center;
            color: var(--accent-yellow);
            font-weight: 600;
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

        .tooltip-name {
            color: var(--accent-blue);
            font-weight: bold;
            margin-bottom: 0.25rem;
            font-size: 0.9rem;
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

        .tooltip-amount {
            color: var(--accent-green);
            font-weight: bold;
            font-size: 0.95rem;
            border-top: 1px solid var(--border-color);
            padding-top: 0.5rem;
            margin-top: 0.5rem;
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

        .navigation-buttons {
            background: rgba(89, 89, 171, 0.1);
            -webkit-backdrop-filter: blur(17px);
            backdrop-filter: blur(17px);
            display: flex;
            gap: 1rem;
            justify-content: space-between;
            margin: 1.5rem 0;
            padding: 1rem 0;
            flex-wrap: wrap;
            width: 100%;
            border-radius: 6px;
            border: 1px solid var(--border-color);
        }

        .week-summary {
            background: rgba(89, 89, 171, 0.1);
            -webkit-backdrop-filter: blur(17px);
            backdrop-filter: blur(17px);
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            margin-top: 2rem;
            text-align: center;
        }

        @media (max-width: 768px) {
            .schedule-container {
                padding: 0 1rem;
            }

            .week-navigation {
                flex-direction: column;
                text-align: center;
            }

            .week-controls {
                justify-content: center;
            }

            .schedule-grid {
                font-size: 0.8rem;
                gap: 2px;
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
<body class="schedule-page">

    <div class="schedule-container">

        <!-- Navigation -->
        <div class="navigation-buttons">
            <a href="javascript:history.back()" class="btn btn-control" title="Back to Courts">
                ◀ Back
            </a>
            <button onclick="location.reload()" class="btn btn-control" title="Refresh">
                ⭮ Refresh
            </button>
        </div>

        <div class="schedule-header">
            <h1>🗒 Weekly Schedule Overview</h1>
            <h2><?php echo htmlspecialchars($court['court_name']); ?></h2>
            <p style="color: var(--text-secondary); margin-bottom: 0;">
                ⚲ <?php echo htmlspecialchars($court['court_location']); ?>
            </p>
        </div>

        <div class="week-navigation">
            <div class="week-controls">
                <a href="?court_id=<?= $court_id ?>&week=<?php echo $prev_week; ?>" class="nav-button">
                    ← Previous Week
                </a>
                <a href="?court_id=<?= $court_id ?>&week=<?php echo date('Y-m-d', strtotime('monday this week')); ?>" class="nav-button secondary">
                    This Week
                </a>
                <a href="?court_id=<?= $court_id ?>&week=<?php echo $next_week; ?>" class="nav-button">
                    Next Week →
                </a>
            </div>

            <div class="week-picker">
                <label for="weekSelect" style="color: var(--text-primary); font-size: 0.9rem;">Jump to week:</label>
                <input type="date" id="weekSelect" value="<?php echo $monday; ?>" onchange="jumpToWeek(this.value)">
            </div>

            <div class="current-week-info">
                <?php 
                $week_end = date('M j, Y', strtotime($monday . ' +6 day'));
                echo date('M j', strtotime($monday)) . ' - ' . $week_end;
                ?>
            </div>
        </div>

         <div class="legend">
            <div class="legend-item"><div class="legend-color" style="background: var(--accent-green);"></div><span>Available</span></div>
            <div class="legend-item"><div class="legend-color" style="background: var(--accent-yellow);"></div><span>Pending</span></div>
            <div class="legend-item"><div class="legend-color" style="background: var(--accent-red);"></div><span>Confirmed</span></div>
            <div class="legend-item"><div class="legend-color" style="background: #515860;"></div><span>Declined</span></div>
        </div>

        <div style="text-align:center; margin-top:1rem; color: var(--text-secondary); padding-bottom: 2rem;">
            <p><strong>Hover over colored cells</strong> to see booking details</p>
        </div>

        <div class="schedule-grid" id="scheduleGrid" style="margin-bottom: 10rem;">
            <?php
            try {
                $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                $time_slots = [];
                for ($hour = 7; $hour <= 23; $hour++) { // 7AM to 11PM
                    $time_slots[] = $hour;
                }
                $time_slots[] = 0; // midnight 12AM

                $week_dates = [];
                for ($i=0; $i<7; $i++) {
                    $week_dates[] = date('Y-m-d', strtotime("$monday +$i day"));
                }

                $bookings_query = "SELECT b.*, c.hourly_rate 
                    FROM bookings b 
                    LEFT JOIN courts c ON b.court_id = c.court_id
                    WHERE b.court_id = ? AND b.schedule_date BETWEEN ? AND ?
                    ORDER BY b.schedule_date, b.start_time";
                $stmt = executeQuery($bookings_query, [$court_id, $week_dates[0], $week_dates[6]]);
                $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $schedule_data = [];
                $week_stats = ['total_bookings'=>0, 'confirmed'=>0, 'pending'=>0, 'declined'=>0];

                foreach ($bookings as $booking) {
                    $week_stats['total_bookings']++;
                    $week_stats[strtolower($booking['status'])]++;

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
                                'time'=>$booking_data['start_time'].' - '.$booking_data['end_time'],
                                'amount'=>number_format($booking_data['amount'], 2)
                            ]));

                            echo '<div class="schedule-cell '.$status.'" data-tooltip=\''.$tooltip_data.'\'></div>';
                        } else {
                            echo '<div class="schedule-cell available" title="Available"></div>';
                        }
                    }
                }
            } catch (Exception $e) {
                echo '<div style="grid-column:1/-1; text-align:center; color: var(--accent-red); padding: 2rem;">Error loading schedule: '.$e->getMessage().'</div>';
            }
            ?>
        </div>
    </div>

    <div id="tooltip" class="tooltip"></div>

    <script>
        function jumpToWeek(selectedDate) {
            let date = new Date(selectedDate);
            let day = date.getDay();
            let diff = date.getDate() - day + (day === 0 ? -6 : 1);
            let monday = new Date(date.setDate(diff));
            let mondayStr = monday.toISOString().split('T')[0];
            window.location.href = '?court_id=<?= $court_id ?>&week=' + mondayStr;
        }

        const tooltip = document.getElementById('tooltip');
        const cells = document.querySelectorAll('.schedule-cell[data-tooltip]');
        cells.forEach(cell => {
            cell.addEventListener('mouseenter', function(e) {
                const data = JSON.parse(this.getAttribute('data-tooltip'));
                tooltip.innerHTML = `
                    <div class="tooltip-event">${data.event}</div>
                    <div class="tooltip-time">${data.time}</div>
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
            const tooltipRect = tooltip.getBoundingClientRect();
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;

            let left = e.pageX + 15;
            let top = e.pageY - 15;

            if (left + tooltipRect.width > viewportWidth) {
                left = e.pageX - tooltipRect.width - 15;
            }
            if (top + tooltipRect.height > viewportHeight) {
                top = e.pageY - tooltipRect.height - 15;
            }
            tooltip.style.left = left + 'px';
            tooltip.style.top = top + 'px';
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowLeft') {
                document.querySelector('[href*="week=<?= $prev_week; ?>"]').click();
            } else if (e.key === 'ArrowRight') {
                document.querySelector('[href*="week=<?= $next_week; ?>"]').click();
            }
        });
    </script>
</body>
</html>
