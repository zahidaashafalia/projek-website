<?php
session_start();
include '../config.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_phone'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userPhone = $_SESSION['user_phone'];
$action = $_GET['action'] ?? '';

switch($action) {
    case 'get_unread':
        $notifications = getUnreadNotifications($conn, $userPhone);
        echo json_encode(['success' => true, 'notifications' => $notifications]);
        break;
    
    case 'get_all':
        $notifications = getUserNotifications($conn, $userPhone);
        echo json_encode(['success' => true, 'notifications' => $notifications]);
        break;
    
    case 'mark_read':
        $id = $_POST['id'] ?? 0;
        if(markNotificationAsRead($conn, $id, $userPhone)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to mark as read']);
        }
        break;
    
    case 'mark_all_read':
        if(markAllNotificationsAsRead($conn, $userPhone)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed']);
        }
        break;
    
    case 'delete':
        $id = $_POST['id'] ?? 0;
        if(deleteNotification($conn, $id, $userPhone)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete']);
        }
        break;
    
    case 'count':
        $count = countUnreadNotifications($conn, $userPhone);
        echo json_encode(['success' => true, 'count' => $count]);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>