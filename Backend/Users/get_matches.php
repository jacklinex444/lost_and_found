<?php
session_start();
require_once "../../config/auth_check.php";
require_once "../../config/db_connect.php";

checkLogin();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$report_id = isset($_GET['report_id']) ? (int)$_GET['report_id'] : 0;
$type = isset($_GET['type']) ? $_GET['type'] : '';

if($type == 'lost') {
    // Get lost report details
    $lost_query = "SELECT * FROM lost_reports WHERE id = $report_id AND user_id = $user_id";
    $lost_result = mysqli_query($conn, $lost_query);
    
    if($lost = mysqli_fetch_assoc($lost_result)) {
        // Find matching found items
        $match_query = "SELECT f.*, u.username, u.email 
                       FROM found_reports f
                       JOIN users u ON f.user_id = u.id
                       WHERE f.category = '{$lost['category']}' 
                       AND f.status = 'approved'
                       AND (f.item_name LIKE '%{$lost['item_name']}%' OR '{$lost['item_name']}' LIKE CONCAT('%', f.item_name, '%'))
                       ORDER BY f.created_at DESC";
        $matches = mysqli_query($conn, $match_query);
        
        $matching_items = [];
        while($match = mysqli_fetch_assoc($matches)) {
            $matching_items[] = $match;
        }
        
        echo json_encode([
            'success' => true,
            'lost_item' => $lost,
            'matches' => $matching_items,
            'count' => count($matching_items)
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lost report not found']);
    }
}
elseif($type == 'found') {
    // Get found report details
    $found_query = "SELECT * FROM found_reports WHERE id = $report_id AND user_id = $user_id";
    $found_result = mysqli_query($conn, $found_query);
    
    if($found = mysqli_fetch_assoc($found_result)) {
        // Find matching lost items
        $match_query = "SELECT l.*, u.username, u.email 
                       FROM lost_reports l
                       JOIN users u ON l.user_id = u.id
                       WHERE l.category = '{$found['category']}'
                       AND (l.item_name LIKE '%{$found['item_name']}%' OR '{$found['item_name']}' LIKE CONCAT('%', l.item_name, '%'))
                       ORDER BY l.created_at DESC";
        $matches = mysqli_query($conn, $match_query);
        
        $matching_items = [];
        while($match = mysqli_fetch_assoc($matches)) {
            $matching_items[] = $match;
        }
        
        echo json_encode([
            'success' => true,
            'found_item' => $found,
            'matches' => $matching_items,
            'count' => count($matching_items)
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Found report not found']);
    }
}
else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>