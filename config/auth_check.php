<?php
// Function to check if user is logged in
function checkLogin() {
    if(!isset($_SESSION['user_id'])) {
        header("Location: ../auth/login.php");
        exit();
    }
}

// Function to check if user is admin
function checkAdmin() {
    checkLogin();
    if($_SESSION['role'] != 'admin') {
        header("Location: ../Frontend/Public_Users/lost_reports.php");
        exit();
    }
}

// Function to check if user is regular user
function checkUser() {
    checkLogin();
    if($_SESSION['role'] != 'user') {
        header("Location: ../Frontend/Admin/dashboard.php");
        exit();
    }
}

// Function to get user data
function getUserData($user_id) {
    global $conn;
    $query = "SELECT * FROM users WHERE id = $user_id";
    $result = mysqli_query($conn, $query);
    return mysqli_fetch_assoc($result);
}

// Function to sanitize input
function sanitize($data) {
    global $conn;
    return mysqli_real_escape_string($conn, htmlspecialchars(strip_tags($data)));
}
?>