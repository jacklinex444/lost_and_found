<?php
session_start();
require_once "../../config/auth_check.php";
require_once "../../config/db_connect.php";

checkLogin();

header('Content-Type: application/json');

// Handle different request methods
$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET':
        // Fetch found reports
        if(isset($_GET['id'])) {
            // Get single report
            $id = mysqli_real_escape_string($conn, $_GET['id']);
            $user_id = $_SESSION['user_id'];
            
            $query = "SELECT f.*, u.username 
                      FROM found_reports f
                      JOIN users u ON f.user_id = u.id
                      WHERE f.id = $id AND f.user_id = $user_id";
            $result = mysqli_query($conn, $query);
            
            if($report = mysqli_fetch_assoc($result)) {
                echo json_encode(['success' => true, 'data' => $report]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Report not found']);
            }
        } else {
            // Get all reports for current user with filters
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $offset = ($page - 1) * $limit;
            $status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
            
            $user_id = $_SESSION['user_id'];
            
            $where = "user_id = $user_id";
            if($status) {
                $where .= " AND status = '$status'";
            }
            
            // Get total count
            $count_query = "SELECT COUNT(*) as total FROM found_reports WHERE $where";
            $count_result = mysqli_query($conn, $count_query);
            $total = mysqli_fetch_assoc($count_result)['total'];
            
            // Get reports
            $query = "SELECT * FROM found_reports 
                      WHERE $where 
                      ORDER BY created_at DESC 
                      LIMIT $offset, $limit";
            $result = mysqli_query($conn, $query);
            
            $reports = [];
            while($row = mysqli_fetch_assoc($result)) {
                $reports[] = $row;
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
        }
        break;
        
    case 'POST':
        // Create new found report
        $user_id = $_SESSION['user_id'];
        $item_name = mysqli_real_escape_string($conn, $_POST['item_name']);
        $category = mysqli_real_escape_string($conn, $_POST['category']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        $location = mysqli_real_escape_string($conn, $_POST['location']);
        $date_found = mysqli_real_escape_string($conn, $_POST['date_found']);
        
        // Handle file upload if present
        $photo_path = null;
        if(isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['photo']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if(in_array($ext, $allowed)) {
                $upload_dir = "../../uploads/found_items/";
                if(!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $new_filename = uniqid() . '.' . $ext;
                $upload_path = $upload_dir . $new_filename;
                
                if(move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                    $photo_path = "uploads/found_items/" . $new_filename;
                }
            }
        }
        
        // Validate input
        $errors = [];
        if(empty($item_name)) $errors[] = "Item name is required";
        if(empty($category)) $errors[] = "Category is required";
        if(empty($location)) $errors[] = "Location is required";
        if(empty($date_found)) $errors[] = "Date found is required";
        
        if(empty($errors)) {
            $photo_field = $photo_path ? "'$photo_path'" : "NULL";
            $query = "INSERT INTO found_reports (user_id, item_name, category, description, location, date_found, photo, status) 
                      VALUES ('$user_id', '$item_name', '$category', '$description', '$location', '$date_found', $photo_field, 'pending')";
            
            if(mysqli_query($conn, $query)) {
                $report_id = mysqli_insert_id($conn);
                
                // Log activity
                $ip = $_SERVER['REMOTE_ADDR'];
                $activity_query = "INSERT INTO activity_logs (user_id, action, details, ip_address) 
                                  VALUES ('$user_id', 'CREATE_FOUND_REPORT', 'Created found report for item: $item_name', '$ip')";
                mysqli_query($conn, $activity_query);
                
                // Check for matching lost items
                $match_query = "SELECT l.*, u.username, u.email 
                               FROM lost_reports l
                               JOIN users u ON l.user_id = u.id
                               WHERE l.category = '$category' 
                               AND (l.item_name LIKE '%$item_name%' OR '$item_name' LIKE CONCAT('%', l.item_name, '%'))
                               ORDER BY l.created_at DESC LIMIT 5";
                $matches = mysqli_query($conn, $match_query);
                
                $matching_items = [];
                while($match = mysqli_fetch_assoc($matches)) {
                    $matching_items[] = $match;
                    
                    // Create notification for potential owners
                    $notify_query = "INSERT INTO notifications (user_id, title, message) 
                                    VALUES ('{$match['user_id']}', 'Potential Match Found', 
                                           'A found item matching your lost item \"{$match['item_name']}\" has been reported.')";
                    mysqli_query($conn, $notify_query);
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Found report submitted successfully and is pending approval',
                    'report_id' => $report_id,
                    'matches_found' => count($matching_items),
                    'matching_items' => $matching_items
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Validation failed', 'errors' => $errors]);
        }
        break;
        
    case 'PUT':
        // Update found report
        parse_str(file_get_contents("php://input"), $put_data);
        $id = mysqli_real_escape_string($conn, $put_data['id']);
        $user_id = $_SESSION['user_id'];
        
        // Check if report belongs to user and is pending
        $check_query = "SELECT id, status FROM found_reports WHERE id = $id AND user_id = $user_id AND status = 'pending'";
        $check_result = mysqli_query($conn, $check_query);
        
        if(mysqli_num_rows($check_result) > 0) {
            $item_name = mysqli_real_escape_string($conn, $put_data['item_name']);
            $category = mysqli_real_escape_string($conn, $put_data['category']);
            $description = mysqli_real_escape_string($conn, $put_data['description']);
            $location = mysqli_real_escape_string($conn, $put_data['location']);
            $date_found = mysqli_real_escape_string($conn, $put_data['date_found']);
            
            $query = "UPDATE found_reports 
                      SET item_name='$item_name', category='$category', description='$description', 
                          location='$location', date_found='$date_found'
                      WHERE id=$id AND user_id=$user_id";
            
            if(mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Report updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Update failed: ' . mysqli_error($conn)]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Unauthorized, report not found, or already processed']);
        }
        break;
        
    case 'DELETE':
        // Delete found report (move to recycle bin)
        parse_str(file_get_contents("php://input"), $delete_data);
        $id = mysqli_real_escape_string($conn, $delete_data['id']);
        $user_id = $_SESSION['user_id'];
        
        // Get report details before deletion
        $get_query = "SELECT * FROM found_reports WHERE id = $id AND user_id = $user_id AND status = 'pending'";
        $get_result = mysqli_query($conn, $get_query);
        
        if($report = mysqli_fetch_assoc($get_result)) {
            // Move to deleted_items
            $move_query = "INSERT INTO deleted_items (original_id, report_type, user_id, item_name, category, description, location, date_reported, deleted_by) 
                          VALUES ('{$report['id']}', 'found', '{$report['user_id']}', '{$report['item_name']}', 
                                 '{$report['category']}', '{$report['description']}', '{$report['location']}', 
                                 '{$report['date_found']}', 'user')";
            
            if(mysqli_query($conn, $move_query)) {
                // Delete original
                $delete_query = "DELETE FROM found_reports WHERE id = $id AND user_id = $user_id";
                mysqli_query($conn, $delete_query);
                
                echo json_encode(['success' => true, 'message' => 'Report moved to recycle bin']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete report']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Report not found, unauthorized, or already processed']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        break;
}
?>