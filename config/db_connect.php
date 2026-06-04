<?php
// Database configuration
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "lost_and_found_db";

// Create connection
$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

// Check connection
if(!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset to UTF-8
mysqli_set_charset($conn, "utf8");

// Create tables if they don't exist
// Users table
$users_table = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    reset_token VARCHAR(255) NULL,
    reset_expires DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// Lost reports table
$lost_reports_table = "CREATE TABLE IF NOT EXISTS lost_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    description TEXT,
    location VARCHAR(255) NOT NULL,
    date_lost DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

// Found reports table
$found_reports_table = "CREATE TABLE IF NOT EXISTS found_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    description TEXT,
    location VARCHAR(255) NOT NULL,
    date_found DATE NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

// Claims table
$claims_table = "CREATE TABLE IF NOT EXISTS claims (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lost_report_id INT NOT NULL,
    found_report_id INT NOT NULL,
    claimant_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (lost_report_id) REFERENCES lost_reports(id) ON DELETE CASCADE,
    FOREIGN KEY (found_report_id) REFERENCES found_reports(id) ON DELETE CASCADE,
    FOREIGN KEY (claimant_id) REFERENCES users(id) ON DELETE CASCADE
)";

// Deleted items table (recycle bin)
$deleted_items_table = "CREATE TABLE IF NOT EXISTS deleted_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_id INT NOT NULL,
    report_type ENUM('lost', 'found') NOT NULL,
    user_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    description TEXT,
    location VARCHAR(255) NOT NULL,
    date_reported DATE NOT NULL,
    deleted_by ENUM('admin', 'user') NOT NULL,
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// Execute table creation
mysqli_query($conn, $users_table);
mysqli_query($conn, $lost_reports_table);
mysqli_query($conn, $found_reports_table);
mysqli_query($conn, $claims_table);
mysqli_query($conn, $deleted_items_table);

// Check if admin exists, if not create default admin
$check_admin = "SELECT * FROM users WHERE role = 'admin' LIMIT 1";
$admin_result = mysqli_query($conn, $check_admin);

if(mysqli_num_rows($admin_result) == 0) {
    $admin_password = password_hash("admin123", PASSWORD_DEFAULT);
    $insert_admin = "INSERT INTO users (username, email, password, role) VALUES ('Admin', 'admin@example.com', '$admin_password', 'admin')";
    mysqli_query($conn, $insert_admin);
}
?>