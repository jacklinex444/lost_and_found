<?php
session_start();
require_once "../../config/auth_check.php";
require_once "../../config/db_connect.php";

checkAdmin();

// Function to log activity
function logActivity($conn, $user_id, $action, $details) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $query = "INSERT INTO activity_logs (user_id, action, details, ip_address) 
              VALUES ('$user_id', '$action', '$details', '$ip')";
    mysqli_query($conn, $query);
}

// Function to send notification
function sendNotification($conn, $user_id, $title, $message) {
    $query = "INSERT INTO notifications (user_id, title, message) 
              VALUES ('$user_id', '$title', '$message')";
    return mysqli_query($conn, $query);
}

if(isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    $admin_id = $_SESSION['user_id'];
    $admin_name = $_SESSION['username'];
    
    if($action == 'approve') {
        // Get item details before updating
        $get_item = "SELECT f.*, u.username, u.email, u.id as user_id 
                     FROM found_reports f 
                     JOIN users u ON f.user_id = u.id 
                     WHERE f.id = $id";
        $item_result = mysqli_query($conn, $get_item);
        $item = mysqli_fetch_assoc($item_result);
        
        if($item) {
            // Update status
            $query = "UPDATE found_reports SET status = 'approved' WHERE id = $id";
            if(mysqli_query($conn, $query)) {
                // Log activity
                logActivity($conn, $admin_id, 'APPROVE_FOUND_ITEM', 
                           "Approved found item ID: $id - {$item['item_name']}");
                
                // Send notification to reporter
                sendNotification($conn, $item['user_id'], 
                    'Item Approved', 
                    "Your found item '{$item['item_name']}' has been approved and is now visible to users looking for lost items.");
                
                // Check for matching lost items and notify potential owners
                $match_query = "SELECT l.*, u.id as owner_id, u.username as owner_name 
                               FROM lost_reports l 
                               JOIN users u ON l.user_id = u.id 
                               WHERE l.category = '{$item['category']}' 
                               AND l.item_name LIKE '%{$item['item_name']}%'";
                $matches = mysqli_query($conn, $match_query);
                
                while($match = mysqli_fetch_assoc($matches)) {
                    sendNotification($conn, $match['owner_id'],
                        'Potential Match Found',
                        "A found item matching your lost item '{$match['item_name']}' has been reported. Check your dashboard for details.");
                }
                
                header("Location: ../../Frontend/Admin/approve.php?msg=approved&id=$id");
            } else {
                header("Location: ../../Frontend/Admin/approve.php?error=" . urlencode(mysqli_error($conn)));
            }
        }
    } 
    elseif($action == 'reject') {
        // Get item details
        $get_item = "SELECT f.*, u.username, u.email, u.id as user_id 
                     FROM found_reports f 
                     JOIN users u ON f.user_id = u.id 
                     WHERE f.id = $id";
        $item_result = mysqli_query($conn, $get_item);
        $item = mysqli_fetch_assoc($item_result);
        
        if($item) {
            // Update status
            $query = "UPDATE found_reports SET status = 'rejected' WHERE id = $id";
            if(mysqli_query($conn, $query)) {
                // Log activity
                logActivity($conn, $admin_id, 'REJECT_FOUND_ITEM', 
                           "Rejected found item ID: $id - {$item['item_name']}");
                
                // Send notification to reporter
                $reject_reason = isset($_GET['reason']) ? $_GET['reason'] : 'Item does not meet our guidelines or contains inappropriate content.';
                sendNotification($conn, $item['user_id'],
                    'Item Rejected',
                    "Your found item '{$item['item_name']}' has been rejected. Reason: $reject_reason");
                
                header("Location: ../../Frontend/Admin/approve.php?msg=rejected&id=$id");
            } else {
                header("Location: ../../Frontend/Admin/approve.php?error=" . urlencode(mysqli_error($conn)));
            }
        }
    }
    elseif($action == 'bulk') {
        // Handle bulk actions
        $ids = isset($_GET['ids']) ? explode(',', $_GET['ids']) : [];
        $bulk_action = isset($_GET['bulk_action']) ? $_GET['bulk_action'] : '';
        
        if(!empty($ids) && $bulk_action) {
            $ids_imploded = implode(',', array_map('intval', $ids));
            
            if($bulk_action == 'approve') {
                $query = "UPDATE found_reports SET status = 'approved' WHERE id IN ($ids_imploded)";
                if(mysqli_query($conn, $query)) {
                    logActivity($conn, $admin_id, 'BULK_APPROVE', 
                               "Bulk approved " . count($ids) . " found items");
                    header("Location: ../../Frontend/Admin/approve.php?msg=bulk_approved&count=" . count($ids));
                }
            } 
            elseif($bulk_action == 'reject') {
                $query = "UPDATE found_reports SET status = 'rejected' WHERE id IN ($ids_imploded)";
                if(mysqli_query($conn, $query)) {
                    logActivity($conn, $admin_id, 'BULK_REJECT', 
                               "Bulk rejected " . count($ids) . " found items");
                    header("Location: ../../Frontend/Admin/approve.php?msg=bulk_rejected&count=" . count($ids));
                }
            }
        }
    }
} 
elseif($_SERVER["REQUEST_METHOD"] == "POST") {
    // Handle AJAX requests
    $response = ['success' => false, 'message' => 'Invalid request'];
    
    if(isset($_POST['action']) && $_POST['action'] == 'get_item_details') {
        $id = mysqli_real_escape_string($conn, $_POST['id']);
        $query = "SELECT f.*, u.username, u.email 
                  FROM found_reports f 
                  JOIN users u ON f.user_id = u.id 
                  WHERE f.id = $id";
        $result = mysqli_query($conn, $query);
        
        if($item = mysqli_fetch_assoc($result)) {
            $response['success'] = true;
            $response['data'] = $item;
        } else {
            $response['message'] = 'Item not found';
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
else {
    header("Location: ../../Frontend/Admin/approve.php");
}
exit();
?>