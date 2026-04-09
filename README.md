# PaSked - Sports Court Booking System Setup Guide

## Introduction
PaSked is a web-based reservation and booking system for sports courts in Metro Manila. This system allows users to easily book sports courts without registration, while providing court admins with a dashboard to manage bookings.

## Requirements
- XAMPP (with Apache, PHP 7.4+, MySQL)
- Web browser (Chrome, Firefox, etc.)
- Text editor (optional, for customization)

## Step-by-Step Setup Instructions

### 1. Install and Configure XAMPP

1. Download and install XAMPP from https://www.apachefriends.org/
2. Start XAMPP Control Panel
3. Configure MySQL to run on port 3307:
   - Click "Config" next to MySQL
   - Select "my.ini"
   - Find the line `port=3306` and change it to `port=3307`
   - Find `[client]` section and change `port=3306` to `port=3307`
   - Save the file
   - Start Apache and MySQL services

### 2. Configure phpMyAdmin for Port 3307

1. Navigate to `C:\xampp\phpMyAdmin\`
2. Open `config.inc.php` file
3. Find the line: `$cfg['Servers'][$i]['host'] = '127.0.0.1';`
4. Change it to: `$cfg['Servers'][$i]['host'] = '127.0.0.1:3307';`
5. Or add this line: `$cfg['Servers'][$i]['port'] = '3307';`
6. Save the file

### 3. Set Up the Database

1. Open your browser and go to `http://localhost/phpmyadmin`
2. Create a new database named `pasked`
3. Click on the `pasked` database
4. Go to the "Import" tab
5. Choose the `database_setup.sql` file from the project folder
6. Click "Go" to execute the SQL commands

### 4. Install the Project Files

1. Copy the entire `PaSked` folder to your XAMPP's `htdocs` directory
   - Default path: `C:\xampp\htdocs\PaSked\`
2. Make sure all files are in the correct structure:
   ```
   htdocs/PaSked/
   ├── index.php
   ├── booking.php
   ├── database_setup.sql
   ├── admin/
   │   ├── login.php
   │   ├── dashboard.php
   │   └── logout.php
   ├── assets/
   │   ├── css/
   │   │   └── style.css
   │   ├── js/
   │   │   └── main.js
   │   └── images/
   │       ├── paskedlogo.png (placeholder)
   │       ├── favicon.ico (placeholder)
   │       └── court.jpg (placeholder)
   └── config/
       └── db_config.php
   ```

### 5. Test the Installation

1. Open your browser and go to `http://localhost/PaSked/`
2. You should see the homepage with available courts
3. Try clicking "Schedule Now" on any court to test the booking form
4. Test admin login at `http://localhost/PaSked/admin/login.php`

## Demo Admin Accounts

The system comes with 5 pre-configured admin accounts:
- Username: `admin_power` | Password: `admin123`
- Username: `admin_elite` | Password: `admin123`
- Username: `admin_champions` | Password: `admin123`
- Username: `admin_victory` | Password: `admin123`
- Username: `admin_metro` | Password: `admin123`

Each admin manages bookings for their respective court.

## Features Overview

### Public Features (No Login Required)
- View all available courts
- Submit booking requests
- Responsive design (mobile-friendly)
- Form validation and security

### Admin Features
- Secure login system
- View court-specific bookings
- Approve or decline booking requests
- Dashboard with statistics
- Search and filter bookings

## File Structure Explanation

- `index.php` - Homepage showing all courts
- `booking.php` - Booking form for individual courts
- `config/db_config.php` - Database connection configuration
- `admin/` - Admin panel files
- `assets/css/style.css` - Dark theme styles
- `assets/js/main.js` - Interactive JavaScript features
- `database_setup.sql` - Database schema and sample data

## Customization Tips

### Adding Court Images
1. Place court images in `assets/images/`
2. Update the court records in the database with the correct image filenames

### Changing Colors
1. Edit `assets/css/style.css`
2. Modify the CSS custom properties (variables) at the top of the file:
   - `--accent-red`: Main red accent
   - `--accent-blue`: Main blue accent
   - `--accent-yellow`: Main yellow accent

### Adding More Courts
1. Go to phpMyAdmin
2. Insert new records in the `courts` table
3. Create corresponding admin accounts in the `admins` table

### Modifying Event Types
1. Edit the dropdown in `booking.php`
2. Update the database ENUM values in the `bookings` table

## Troubleshooting

### Common Issues

1. **"Database connection failed"**
   - Check if MySQL is running on port 3307
   - Verify database name is `pasked`
   - Check credentials in `config/db_config.php`

2. **"Court not found"**
   - Make sure you've imported the SQL file
   - Check if courts exist in the database

3. **Admin login not working**
   - Verify admin accounts exist in the database
   - Password is `admin123` for all demo accounts
   - Check session configuration

4. **Styles not loading**
   - Check if the CSS file path is correct
   - Clear browser cache
   - Verify XAMPP is serving files properly

## Security Notes

- Change default admin passwords in production
- Use HTTPS in production environment
- Regularly update PHP and MySQL
- Consider adding CAPTCHA for booking forms
- Implement rate limiting for form submissions

## Next Steps

1. Test all functionality thoroughly
2. Add your actual court images
3. Customize the design as needed
4. Set up email notifications (optional)
5. Consider adding payment integration (future feature)

## Support

If you encounter issues:
1. Check the troubleshooting section
2. Verify all setup steps were completed
3. Test with different browsers
4. Check XAMPP error logs

Enjoy using PaSked for your sports court bookings!
