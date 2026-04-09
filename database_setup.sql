-- Create database (run this first)
-- CREATE DATABASE pasked CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE pasked;

-- Drop existing tables if they exist (for clean reinstall)
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS admins;
DROP TABLE IF EXISTS courts;

-- Create tables
CREATE TABLE courts (
    court_id INT AUTO_INCREMENT PRIMARY KEY,
    court_name VARCHAR(255) NOT NULL,
    court_location VARCHAR(255) NOT NULL,
    court_image VARCHAR(255) DEFAULT 'court.jpg',
    hourly_rate DECIMAL(10,2) DEFAULT 0.00,
    google_maps_url VARCHAR(500) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE admins (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    court_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (court_id) REFERENCES courts(court_id) ON DELETE CASCADE
);

CREATE TABLE bookings (
    booking_id INT AUTO_INCREMENT PRIMARY KEY,
    court_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    contact_number VARCHAR(20) NOT NULL,
    email VARCHAR(255) NOT NULL,
    schedule_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    event_type ENUM('Basketball', 'Volleyball', 'Event', 'Other') NOT NULL,
    status ENUM('Pending', 'Confirmed', 'Declined') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (court_id) REFERENCES courts(court_id) ON DELETE CASCADE
);

-- Insert sample courts
INSERT INTO courts (court_name, court_location, court_image, hourly_rate, google_maps_url) VALUES
('Power Court Manila', 'Malate, Manila', 'court.jpg', 500.00, ''),
('Elite Basketball Center', 'Quezon City', 'court.jpg', 750.00, ''),
('Champions Court Makati', 'Makati City', 'court.jpg', 1000.00, ''),
('Victory Sports Complex', 'Pasig City', 'court.jpg', 600.00, ''),
('Metro Arena Taguig', 'Taguig City', 'court.jpg', 800.00, '');

-- Insert admin accounts with hashed password ('admin123')
INSERT INTO admins (username, password, court_id) VALUES
('admin_power', '$2y$10$dxC6OoTJJfThlyZwhd6lAuq9ee2EvRRx8MzDpWIN2PwGsAPHAKpna', 1),
('admin_elite', '$2y$10$dxC6OoTJJfThlyZwhd6lAuq9ee2EvRRx8MzDpWIN2PwGsAPHAKpna', 2),
('admin_champions', '$2y$10$dxC6OoTJJfThlyZwhd6lAuq9ee2EvRRx8MzDpWIN2PwGsAPHAKpna', 3),
('admin_victory', '$2y$10$dxC6OoTJJfThlyZwhd6lAuq9ee2EvRRx8MzDpWIN2PwGsAPHAKpna', 4),
('admin_metro', '$2y$10$dxC6OoTJJfThlyZwhd6lAuq9ee2EvRRx8MzDpWIN2PwGsAPHAKpna', 5);

-- Insert sample bookings
INSERT INTO bookings (court_id, name, contact_number, email, schedule_date, start_time, end_time, event_type, status) VALUES
(1, 'Juan Carlos', '09171234567', 'juan@email.com', '2025-10-15', '10:00:00', '12:00:00', 'Basketball', 'Pending'),
(1, 'Maria Santos', '09181234567', 'maria@email.com', '2025-10-16', '14:00:00', '16:00:00', 'Volleyball', 'Pending'),
(1, 'Team Alpha', '09191234567', 'alpha@email.com', '2025-10-17', '18:00:00', '20:00:00', 'Event', 'Confirmed'),

(2, 'Carlos Mendoza', '09271234567', 'carlos@email.com', '2025-10-18', '09:00:00', '11:00:00', 'Basketball', 'Pending'),
(2, 'Lisa Garcia', '09281234567', 'lisa@email.com', '2025-10-19', '15:00:00', '17:00:00', 'Volleyball', 'Pending'),
(2, 'Warriors Team', '09291234567', 'warriors@email.com', '2025-10-20', '19:00:00', '21:00:00', 'Basketball', 'Declined'),

(3, 'Roberto Silva', '09371234567', 'roberto@email.com', '2025-10-21', '08:00:00', '10:00:00', 'Basketball', 'Pending'),
(3, 'Diana Cruz', '09381234567', 'diana@email.com', '2025-10-22', '16:00:00', '18:00:00', 'Other', 'Pending'),
(3, 'Phoenix Squad', '09391234567', 'phoenix@email.com', '2025-10-23', '20:00:00', '22:00:00', 'Event', 'Confirmed'),

(4, 'Antonio Reyes', '09471234567', 'antonio@email.com', '2025-10-24', '07:00:00', '09:00:00', 'Basketball', 'Pending'),
(4, 'Sofia Torres', '09481234567', 'sofia@email.com', '2025-10-25', '13:00:00', '15:00:00', 'Volleyball', 'Pending'),
(4, 'Lightning Bolts', '09491234567', 'lightning@email.com', '2025-10-26', '17:00:00', '19:00:00', 'Basketball', 'Confirmed'),

(5, 'Miguel Santos', '09571234567', 'miguel@email.com', '2025-10-27', '11:00:00', '13:00:00', 'Basketball', 'Pending'),
(5, 'Carmen Flores', '09581234567', 'carmen@email.com', '2025-10-28', '14:00:00', '16:00:00', 'Volleyball', 'Pending'),
(5, 'Thunder United', '09591234567', 'thunder@email.com', '2025-10-29', '18:00:00', '20:00:00', 'Event', 'Declined');

-- Verify data (optional)
SELECT 'COURTS' AS table_name, court_id, court_name, court_location FROM courts
UNION ALL
SELECT 'ADMINS', admin_id, username, CONCAT('Court ', court_id) FROM admins
UNION ALL
SELECT 'BOOKINGS', booking_id, CONCAT(name, ' (Court ', court_id, ')'), status FROM bookings
ORDER BY table_name, court_id;
