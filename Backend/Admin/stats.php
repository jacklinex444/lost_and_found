<?php
session_start();
require_once "../../config/auth_check.php";
require_once "../../config/db_connect.php";

checkAdmin();
header('Content-Type: application/json');

// Get all system statistics
$stats = [];

// User statistics
$query = "SELECT COUNT(*) as total FROM users";
$result = mysqli_query($conn, $query);
$stats['total_users'] = mysqli_fetch_assoc($result)['total'];

$query = "SELECT COUNT(*) as total FROM users WHERE role = 'admin'";
$result = mysqli_query($conn, $query);
$stats['total_admins'] = mysqli_fetch_assoc($result)['total'];

$query = "SELECT COUNT(*) as total FROM users WHERE role = 'user'";
$result = mysqli_query($conn, $query);
$stats['total_regular_users'] = mysqli_fetch_assoc($result)['total'];

// Report statistics
$query = "SELECT COUNT(*) as total FROM lost_reports";
$result = mysqli_query($conn, $query);
$stats['total_lost'] = mysqli_fetch_assoc($result)['total'];

$query = "SELECT COUNT(*) as total FROM found_reports";
$result = mysqli_query($conn, $query);
$stats['total_found'] = mysqli_fetch_assoc($result)['total'];

$query = "SELECT COUNT(*) as total FROM found_reports WHERE status = 'pending'";
$result = mysqli_query($conn, $query);
$stats['pending_approvals'] = mysqli_fetch_assoc($result)['total'];

$query = "SELECT COUNT(*) as total FROM found_reports WHERE status = 'approved'";
$result = mysqli_query($conn, $query);
$stats['approved_approvals'] = mysqli_fetch_assoc($result)['total'];

$query = "SELECT COUNT(*) as total FROM found_reports WHERE status = 'rejected'";
$result = mysqli_query($conn, $query);
$stats['rejected_approvals'] = mysqli_fetch_assoc($result)['total'];

// Claim statistics
$query = "SELECT COUNT(*) as total FROM claims";
$result = mysqli_query($conn, $query);
$stats['total_claims'] = mysqli_fetch_assoc($result)['total'];

$query = "SELECT COUNT(*) as total FROM claims WHERE status = 'pending'";
$result = mysqli_query($conn, $query);
$stats['pending_claims'] = mysqli_fetch_assoc($result)['total'];

$query = "SELECT COUNT(*) as total FROM claims WHERE status = 'approved'";
$result = mysqli_query($conn, $query);
$stats['approved_claims'] = mysqli_fetch_assoc($result)['total'];

$query = "SELECT COUNT(*) as total FROM claims WHERE status = 'rejected'";
$result = mysqli_query($conn, $query);
$stats['rejected_claims'] = mysqli_fetch_assoc($result)['total'];

// Recycle bin statistics
$query = "SELECT COUNT(*) as total FROM deleted_items";
$result = mysqli_query($conn, $query);
$stats['deleted_items'] = mysqli_fetch_assoc($result)['total'];

$query = "SELECT COUNT(*) as total FROM deleted_items WHERE report_type = 'lost'";
$result = mysqli_query($conn, $query);
$stats['deleted_lost'] = mysqli_fetch_assoc($result)['total'];

$query = "SELECT COUNT(*) as total FROM deleted_items WHERE report_type = 'found'";
$result = mysqli_query($conn, $query);
$stats['deleted_found'] = mysqli_fetch_assoc($result)['total'];

// Monthly trends for last 12 months
$monthly_trends = [];
$query = "SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as total_reports
          FROM (
              SELECT created_at FROM lost_reports
              UNION ALL
              SELECT created_at FROM found_reports
          ) as all_reports
          WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
          GROUP BY DATE_FORMAT(created_at, '%Y-%m')
          ORDER BY month ASC";
$result = mysqli_query($conn, $query);
while($row = mysqli_fetch_assoc($result)) {
    $monthly_trends[] = $row;
}
$stats['monthly_trends'] = $monthly_trends;

// Category distribution for lost items
$category_stats = [];
$query = "SELECT category, COUNT(*) as count FROM lost_reports GROUP BY category ORDER BY count DESC";
$result = mysqli_query($conn, $query);
while($row = mysqli_fetch_assoc($result)) {
    $category_stats['lost'][$row['category']] = $row['count'];
}

// Category distribution for found items
$query = "SELECT category, COUNT(*) as count FROM found_reports GROUP BY category ORDER BY count DESC";
$result = mysqli_query($conn, $query);
while($row = mysqli_fetch_assoc($result)) {
    $category_stats['found'][$row['category']] = $row['count'];
}
$stats['category_stats'] = $category_stats;

// Success rate (approved claims / total claims)
$stats['success_rate'] = ($stats['total_claims'] > 0) ? 
    round(($stats['approved_claims'] / $stats['total_claims']) * 100, 2) : 0;

// Response time (average time from report to claim)
$query = "SELECT AVG(TIMESTAMPDIFF(HOUR, l.created_at, c.created_at)) as avg_response_time
          FROM claims c
          JOIN lost_reports l ON c.lost_report_id = l.id
          WHERE c.status = 'approved'";
$result = mysqli_query($conn, $query);
$avg_time = mysqli_fetch_assoc($result);
$stats['avg_response_time'] = round($avg_time['avg_response_time'] ?? 0, 1);

// Most active users
$query = "SELECT u.username, COUNT(*) as total_reports
          FROM users u
          LEFT JOIN (
              SELECT user_id FROM lost_reports
              UNION ALL
              SELECT user_id FROM found_reports
          ) as reports ON u.id = reports.user_id
          GROUP BY u.id
          ORDER BY total_reports DESC
          LIMIT 5";
$result = mysqli_query($conn, $query);
$active_users = [];
while($row = mysqli_fetch_assoc($result)) {
    $active_users[] = $row;
}
$stats['active_users'] = $active_users;

// Recent activity log
$query = "SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 10";
$result = mysqli_query($conn, $query);
$recent_activity = [];
while($row = mysqli_fetch_assoc($result)) {
    $recent_activity[] = $row;
}
$stats['recent_activity'] = $recent_activity;

// Database size
$query = "SELECT 
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
          FROM information_schema.tables 
          WHERE table_schema = '$db_name'";
$result = mysqli_query($conn, $query);
$db_size = mysqli_fetch_assoc($result);
$stats['database_size_mb'] = $db_size['size_mb'] ?? 0;

echo json_encode($stats);
?>