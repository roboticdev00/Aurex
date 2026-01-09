<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['User_ID'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

require_once 'db_connect.php';

$data = json_decode(file_get_contents('php://input'), true);
$order_id = $data['order_id'] ?? null;
$user_id = $_SESSION['User_ID'];

if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'Order ID required']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Verify order belongs to user and check if it can be cancelled
    $stmt = $pdo->prepare("SELECT Order_ID, Status FROM orders WHERE Order_ID = :order_id AND User_ID = :user_id");
    $stmt->execute([':order_id' => $order_id, ':user_id' => $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }
    
    // Only allow cancellation of Pending orders
    if ($order['Status'] !== 'Pending') {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Only pending orders can be cancelled']);
        exit;
    }
    
    // Update order status to Cancelled
    $stmt = $pdo->prepare("UPDATE orders SET Status = 'Cancelled' WHERE Order_ID = :order_id");
    $stmt->execute([':order_id' => $order_id]);
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Order cancelled successfully']);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Cancel Order Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>