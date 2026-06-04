<?php
session_start();
require_once "../../config/auth_check.php";
require_once "../../config/db_connect.php";

checkLogin();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];

// Get user statistics
$stats = [];

// Count lost reports
$lost_query = "SELECT COUNT(*) as total FROM lost_reports WHERE user_id = $user_id";
$lost_result = mysqli_query($conn, $lost_query);
$stats['lost_reports'] = mysqli_fetch_assoc($lost_result)['total'];

// Count found reports
$found_query = "SELECT COUNT(*) as total FROM found_reports WHERE user_id = $user_id";
$found_result = mysqli_query($conn, $found_query);
$stats['found_reports'] = mysqli_fetch_assoc($found_result)['total'];

// Count pending found reports
$pending_query = "SELECT COUNT(*) as total FROM found_reports WHERE user_id = $user_id AND status = 'pending'";
$pending_result = mysqli_query($conn, $pending_query);
$stats['pending_reports'] = mysqli_fetch_assoc($pending_result)['total'];

// Count approved found reports
$approved_query = "SELECT COUNT(*) as total FROM found_reports WHERE user_id = $user_id AND status = 'approved'";
$approved_result = mysqli_query($conn, $approved_query);
$stats['approved_reports'] = mysqli_fetch_assoc($approved_result)['total'];

// Count claims made
$claims_query = "SELECT COUNT(*) as total FROM claims WHERE claimant_id = $user_id";
$claims_result = mysqli_query($conn, $claims_query);
$stats['claims_made'] = mysqli_fetch_assoc($claims_result)['total'];

// Count approved claims
$approved_claims_query = "SELECT COUNT(*) as total FROM claims WHERE claimant_id = $user_id AND status = 'approved'";
$approved_claims_result = mysqli_query($conn, $approved_claims_query);
$stats['approved_claims'] = mysqli_fetch_assoc($approved_claims_result)['total'];

// Get recent activity
$recent_query = "SELECT * FROM activity_logs WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 10";
$recent_result = mysqli_query($conn, $recent_query);
$recent_activity = [];
while($row = mysqli_fetch_assoc($recent_result)) {
    $recent_activity[] = $row;
}
$stats['recent_activity'] = $recent_activity;

// Get monthly report trend
$trend_query = "SELECT 
                  DATE_FORMAT(created_at, '%Y-%m') as month,
                  COUNT(*) as count
                FROM (
                    SELECT created_at FROM lost_reports WHERE user_id = $user_id
                    UNION ALL
                    SELECT created_at FROM found_reports WHERE user_id = $user_id
                ) as reports
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month ASC";
$trend_result = mysqli_query($conn, $trend_query);
$monthly_trend = [];
while($row = mysqli_fetch_assoc($trend_result)) {
    $monthly_trend[] = $row;
}
$stats['monthly_trend'] = $monthly_trend;

echo json_encode(['success' => true, 'stats' => $stats]);
?>