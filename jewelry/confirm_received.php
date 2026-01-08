<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['User_ID'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

$user_id = $_SESSION['User_ID'];

$data = json_decode(file_get_contents('php://input'), true);
$order_id = $data['order_id'] ?? null;

if (!$order_id || !is_numeric($order_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

try {
    // Check if order belongs to user and is in 'Shipped' status
    $stmt = $pdo->prepare("
        SELECT Status FROM orders 
        WHERE Order_ID = :order_id AND User_ID = :user_id
    ");
    $stmt->execute([':order_id' => $order_id, ':user_id' => $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found or access denied']);
        exit;
    }

    if ($order['Status'] !== 'Shipped') {
        echo json_encode(['success' => false, 'message' => 'Order must be shipped before marking as received']);
        exit;
    }

    // Update status to Delivered
    $stmt_update = $pdo->prepare("UPDATE orders SET Status = 'Delivered' WHERE Order_ID = :order_id");
    $result = $stmt_update->execute([':order_id' => $order_id]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Order marked as received']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update order']);
    }

} catch (PDOException $e) {
    error_log("Confirm Received Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>