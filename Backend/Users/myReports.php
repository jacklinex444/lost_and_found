<?php
session_start();
require_once "../../config/auth_check.php";
require_once "../../config/db_connect.php";

checkLogin();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET':
        // Get all reports for the current user
        $type = isset($_GET['type']) ? $_GET['type'] : 'all';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;
        
        $reports = [];
        $total = 0;
        
        if($type == 'lost' || $type == 'all') {
            $lost_query = "SELECT *, 'lost' as report_type FROM lost_reports WHERE user_id = $user_id";
            if($type == 'lost') {
                $lost_query .= " LIMIT $offset, $limit";
                $count_query = "SELECT COUNT(*) as total FROM lost_reports WHERE user_id = $user_id";
                $count_result = mysqli_query($conn, $count_query);
                $total = mysqli_fetch_assoc($count_result)['total'];
            }
            $lost_result = mysqli_query($conn, $lost_query);
            while($row = mysqli_fetch_assoc($lost_result)) {
                $row['can_edit'] = true;
                $row['can_delete'] = true;
                $reports[] = $row;
            }
        }
        
        if($type == 'found' || $type == 'all') {
            $found_query = "SELECT *, 'found' as report_type FROM found_reports WHERE user_id = $user_id";
            if($type == 'found') {
                $found_query .= " LIMIT $offset, $limit";
                $count_query = "SELECT COUNT(*) as total FROM found_reports WHERE user_id = $user_id";
                $count_result = mysqli_query($conn, $count_query);
                $total = mysqli_fetch_assoc($count_result)['total'];
            }
            $found_result = mysqli_query($conn, $found_query);
            while($row = mysqli_fetch_assoc($found_result)) {
                $row['can_edit'] = ($row['status'] == 'pending');
                $row['can_delete'] = ($row['status'] == 'pending');
                $reports[] = $row;
            }
        }
        
        // Sort by created_at DESC
        usort($reports, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        // Apply pagination for 'all' type
        if($type == 'all') {
            $total = count($reports);
            $reports = array_slice($reports, $offset, $limit);
        }
        
        echo json_encode([
            'success' => true,
            'data' => $reports,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($total / $limit),
                'total_records' => $total,
                'per_page' => $limit
            ]
        ]);
        break;
        
    case 'DELETE':
        // Delete a report (move to recycle bin)
        $delete_data = json_decode(file_get_contents("php://input"), true);
        $id = mysqli_real_escape_string($conn, $delete_data['id']);
        $type = mysqli_real_escape_string($conn, $delete_data['type']);
        
        if($type == 'lost') {
            // Get lost report details
            $get_query = "SELECT * FROM lost_reports WHERE id = $id AND user_id = $user_id";
            $get_result = mysqli_query($conn, $get_query);
            
            if($report = mysqli_fetch_assoc($get_result)) {
                // Move to deleted_items
                $move_query = "INSERT INTO deleted_items (original_id, report_type, user_id, item_name, category, description, location, date_reported, deleted_by) 
                              VALUES ('{$report['id']}', 'lost', '{$report['user_id']}', '{$report['item_name']}', 
                                     '{$report['category']}', '{$report['description']}', '{$report['location']}', 
                                     '{$report['date_lost']}', 'user')";
                
                if(mysqli_query($conn, $move_query)) {
                    $delete_query = "DELETE FROM lost_reports WHERE id = $id AND user_id = $user_id";
                    mysqli_query($conn, $delete_query);
                    echo json_encode(['success' => true, 'message' => 'Report moved to recycle bin']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete report']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Report not found or unauthorized']);
            }
        } 
        elseif($type == 'found') {
            // Get found report details
            $get_query = "SELECT * FROM found_reports WHERE id = $id AND user_id = $user_id AND status = 'pending'";
            $get_result = mysqli_query($conn, $get_query);
            
            if($report = mysqli_fetch_assoc($get_result)) {
                $move_query = "INSERT INTO deleted_items (original_id, report_type, user_id, item_name, category, description, location, date_reported, deleted_by) 
                              VALUES ('{$report['id']}', 'found', '{$report['user_id']}', '{$report['item_name']}', 
                                     '{$report['category']}', '{$report['description']}', '{$report['location']}', 
                                     '{$report['date_found']}', 'user')";
                
                if(mysqli_query($conn, $move_query)) {
                    $delete_query = "DELETE FROM found_reports WHERE id = $id AND user_id = $user_id";
                    mysqli_query($conn, $delete_query);
                    echo json_encode(['success' => true, 'message' => 'Report moved to recycle bin']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete report']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Report not found, unauthorized, or already processed']);
            }
        }
        else {
            echo json_encode(['success' => false, 'message' => 'Invalid report type']);
        }
        break;
        
    case 'POST':
        // Submit a claim for a found item
        if(isset($_POST['action']) && $_POST['action'] == 'submit_claim') {
            $lost_report_id = mysqli_real_escape_string($conn, $_POST['lost_report_id']);
            $found_report_id = mysqli_real_escape_string($conn, $_POST['found_report_id']);
            
            // Verify lost report belongs to user
            $check_lost = "SELECT id FROM lost_reports WHERE id = $lost_report_id AND user_id = $user_id";
            $lost_result = mysqli_query($conn, $check_lost);
            
            // Verify found report exists and is approved
            $check_found = "SELECT id, user_id FROM found_reports WHERE id = $found_report_id AND status = 'approved'";
            $found_result = mysqli_query($conn, $check_found);
            
            if(mysqli_num_rows($lost_result) > 0 && mysqli_num_rows($found_result) > 0) {
                $found = mysqli_fetch_assoc($found_result);
                
                // Check if claim already exists
                $check_claim = "SELECT id FROM claims WHERE lost_report_id = $lost_report_id AND found_report_id = $found_report_id";
                $claim_result = mysqli_query($conn, $check_claim);
                
                if(mysqli_num_rows($claim_result) == 0) {
                    $query = "INSERT INTO claims (lost_report_id, found_report_id, claimant_id, status) 
                              VALUES ('$lost_report_id', '$found_report_id', '$user_id', 'pending')";
                    
                    if(mysqli_query($conn, $query)) {
                        // Notify the finder
                        $notify_query = "INSERT INTO notifications (user_id, title, message) 
                                        VALUES ('{$found['user_id']}', 'New Claim Submitted', 
                                               'Someone has claimed an item you found. Please wait for admin review.')";
                        mysqli_query($conn, $notify_query);
                        
                        echo json_encode(['success' => true, 'message' => 'Claim submitted successfully! Admin will review your claim.']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to submit claim']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Claim already exists for this item']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid report IDs or unauthorized']);
            }
        }
        else {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        break;
}
?>