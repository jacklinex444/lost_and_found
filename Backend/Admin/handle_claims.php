<?php
session_start();
require_once "../../config/auth_check.php";
require_once "../../config/db_connect.php";

checkAdmin();

function logActivity($conn, $user_id, $action, $details) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $query = "INSERT INTO activity_logs (user_id, action, details, ip_address) 
              VALUES ('$user_id', '$action', '$details', '$ip')";
    mysqli_query($conn, $query);
}

function sendNotification($conn, $user_id, $title, $message) {
    $query = "INSERT INTO notifications (user_id, title, message) 
              VALUES ('$user_id', '$title', '$message')";
    return mysqli_query($conn, $query);
}

if(isset($_GET['action']) && isset($_GET['id']) && isset($_GET['status'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    $status = mysqli_real_escape_string($conn, $_GET['status']);
    $admin_id = $_SESSION['user_id'];
    $admin_name = $_SESSION['username'];
    
    // Get claim details before updating
    $get_claim = "SELECT c.*, l.item_name as lost_item, f.item_name as found_item, 
                         u.username as claimant_name, u.email as claimant_email, u.id as claimant_id
                  FROM claims c
                  JOIN lost_reports l ON c.lost_report_id = l.id
                  JOIN found_reports f ON c.found_report_id = f.id
                  JOIN users u ON c.claimant_id = u.id
                  WHERE c.id = $id";
    $claim_result = mysqli_query($conn, $get_claim);
    $claim = mysqli_fetch_assoc($claim_result);
    
    if($claim) {
        // Update claim status
        $query = "UPDATE claims SET status = '$status', updated_at = NOW() WHERE id = $id";
        
        if(mysqli_query($conn, $query)) {
            // Log activity
            logActivity($conn, $admin_id, strtoupper($status) . '_CLAIM', 
                       ucfirst($status) . " claim ID: $id for item '{$claim['lost_item']}'");
            
            // Send notification to claimant
            if($status == 'approved') {
                $message = "Your claim for '{$claim['lost_item']}' has been approved! 
                           Please contact the admin to arrange collection of your item.";
                $title = "Claim Approved";
                
                // Also mark the found item as claimed
                $update_found = "UPDATE found_reports SET status = 'claimed' WHERE id = {$claim['found_report_id']}";
                mysqli_query($conn, $update_found);
                
                // Mark lost report as resolved
                $update_lost = "UPDATE lost_reports SET status = 'resolved' WHERE id = {$claim['lost_report_id']}";
                mysqli_query($conn, $update_lost);
            } else {
                $message = "We regret to inform you that your claim for '{$claim['lost_item']}' has been rejected. 
                           Please contact support if you have any questions.";
                $title = "Claim Rejected";
            }
            
            sendNotification($conn, $claim['claimant_id'], $title, $message);
            
            // Add admin notes if provided
            if(isset($_GET['notes'])) {
                $notes = mysqli_real_escape_string($conn, $_GET['notes']);
                $update_notes = "UPDATE claims SET admin_notes = '$notes' WHERE id = $id";
                mysqli_query($conn, $update_notes);
            }
            
            header("Location: ../../Frontend/Admin/handle_claims.php?msg=updated&status=$status");
        } else {
            header("Location: ../../Frontend/Admin/handle_claims.php?error=" . urlencode(mysqli_error($conn)));
        }
    } else {
        header("Location: ../../Frontend/Admin/handle_claims.php?error=Claim not found");
    }
}
elseif(isset($_GET['action']) && $_GET['action'] == 'bulk') {
    // Handle bulk claim updates
    $ids = isset($_GET['ids']) ? explode(',', $_GET['ids']) : [];
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    $admin_id = $_SESSION['user_id'];
    
    if(!empty($ids) && $status) {
        $ids_imploded = implode(',', array_map('intval', $ids));
        $query = "UPDATE claims SET status = '$status', updated_at = NOW() WHERE id IN ($ids_imploded)";
        
        if(mysqli_query($conn, $query)) {
            $count = mysqli_affected_rows($conn);
            logActivity($conn, $admin_id, 'BULK_' . strtoupper($status) . '_CLAIMS', 
                       "Bulk $status $count claims");
            
            // Get all affected claimants to notify them
            $get_claimants = "SELECT DISTINCT claimant_id, lost_report_id 
                             FROM claims WHERE id IN ($ids_imploded)";
            $claimants = mysqli_query($conn, $get_claimants);
            
            while($claimant = mysqli_fetch_assoc($claimants)) {
                $title = $status == 'approved' ? "Claim Approved" : "Claim Rejected";
                $message = $status == 'approved' ? 
                    "Your claim has been approved! Please contact admin to arrange collection." :
                    "Your claim has been reviewed and rejected. Contact support for more information.";
                sendNotification($conn, $claimant['claimant_id'], $title, $message);
            }
            
            header("Location: ../../Frontend/Admin/handle_claims.php?msg=bulk_updated&count=$count&status=$status");
        } else {
            header("Location: ../../Frontend/Admin/handle_claims.php?error=" . urlencode(mysqli_error($conn)));
        }
    } else {
        header("Location: ../../Frontend/Admin/handle_claims.php?error=No claims selected");
    }
}
elseif(isset($_GET['action']) && $_GET['action'] == 'export') {
    // Export claims to CSV
    $status = isset($_GET['status']) ? $_GET['status'] : 'all';
    
    $where = "1=1";
    if($status != 'all') {
        $where .= " AND c.status = '$status'";
    }
    
    $query = "SELECT 
                c.id as claim_id,
                c.status,
                c.created_at as claim_date,
                c.updated_at as processed_date,
                l.item_name as lost_item,
                l.category as lost_category,
                l.location as lost_location,
                l.date_lost,
                f.item_name as found_item,
                f.location as found_location,
                f.date_found,
                u.username as claimant_name,
                u.email as claimant_email,
                c.admin_notes
              FROM claims c
              JOIN lost_reports l ON c.lost_report_id = l.id
              JOIN found_reports f ON c.found_report_id = f.id
              JOIN users u ON c.claimant_id = u.id
              WHERE $where
              ORDER BY c.created_at DESC";
    
    $result = mysqli_query($conn, $query);
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="claims_export_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add headers
    fputcsv($output, [
        'Claim ID', 'Status', 'Claim Date', 'Processed Date',
        'Lost Item', 'Lost Category', 'Lost Location', 'Date Lost',
        'Found Item', 'Found Location', 'Date Found',
        'Claimant Name', 'Claimant Email', 'Admin Notes'
    ]);
    
    // Add data
    while($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit();
}
elseif($_SERVER["REQUEST_METHOD"] == "POST") {
    // Handle AJAX requests
    $response = ['success' => false, 'message' => 'Invalid request'];
    
    if(isset($_POST['action']) && $_POST['action'] == 'get_claim_details') {
        $id = mysqli_real_escape_string($conn, $_POST['id']);
        $query = "SELECT c.*, 
                         l.item_name as lost_item, l.description as lost_description, 
                         l.location as lost_location, l.date_lost,
                         f.item_name as found_item, f.description as found_description,
                         f.location as found_location, f.date_found,
                         u.username as claimant_name, u.email as claimant_email
                  FROM claims c
                  JOIN lost_reports l ON c.lost_report_id = l.id
                  JOIN found_reports f ON c.found_report_id = f.id
                  JOIN users u ON c.claimant_id = u.id
                  WHERE c.id = $id";
        $result = mysqli_query($conn, $query);
        
        if($claim = mysqli_fetch_assoc($result)) {
            $response['success'] = true;
            $response['data'] = $claim;
        } else {
            $response['message'] = 'Claim not found';
        }
    }
    elseif(isset($_POST['action']) && $_POST['action'] == 'add_notes') {
        $id = mysqli_real_escape_string($conn, $_POST['id']);
        $notes = mysqli_real_escape_string($conn, $_POST['notes']);
        
        $query = "UPDATE claims SET admin_notes = CONCAT(IFNULL(admin_notes, ''), '\n---\n', NOW(), ': ', '$notes') 
                  WHERE id = $id";
        
        if(mysqli_query($conn, $query)) {
            $response['success'] = true;
            $response['message'] = 'Notes added successfully';
        } else {
            $response['message'] = 'Failed to add notes';
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
else {
    header("Location: ../../Frontend/Admin/handle_claims.php");
}
exit();
?>