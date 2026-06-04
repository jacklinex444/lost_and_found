<?php
session_start();
require_once "../../config/auth_check.php";
require_once "../../config/db_connect.php";

checkLogin();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$action = isset($_GET['action']) ? $_GET['action'] : 'get';

if($action == 'get') {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    
    $query = "SELECT * FROM notifications 
              WHERE user_id = $user_id 
              ORDER BY created_at DESC 
              LIMIT $limit";
    $result = mysqli_query($conn, $query);
    
    $notifications = [];
    while($row = mysqli_fetch_assoc($result)) {
        $notifications[] = $row;
    }
    
    // Get unread count
    $unread_query = "SELECT COUNT(*) as unread FROM notifications 
                     WHERE user_id = $user_id AND is_read = 0";
    $unread_result = mysqli_query($conn, $unread_query);
    $unread_count = mysqli_fetch_assoc($unread_result)['unread'];
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => $unread_count
    ]);
}
elseif($action == 'mark_read') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if($id == 0) {
        // Mark all as read
        $query = "UPDATE notifications SET is_read = 1 WHERE user_id = $user_id";
    } else {
        // Mark specific notification as read
        $query = "UPDATE notifications SET is_read = 1 WHERE id = $id AND user_id = $user_id";
    }
    
    if(mysqli_query($conn, $query)) {
        echo json_encode(['success' => true, 'message' => 'Notifications marked as read']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update notifications']);
    }
}
elseif($action == 'delete') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    $query = "DELETE FROM notifications WHERE id = $id AND user_id = $user_id";
    
    if(mysqli_query($conn, $query)) {
        echo json_encode(['success' => true, 'message' => 'Notification deleted']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete notification']);
    }
}
else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>