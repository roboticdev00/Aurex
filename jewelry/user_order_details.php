<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['User_ID'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

$user_id = $_SESSION['User_ID'];

$order_id = $_GET['order_id'] ?? null;

if (!$order_id || !is_numeric($order_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

try {
    // Fetch order summary
    $stmt = $pdo->prepare("
        SELECT 
            o.Order_ID,
            o.User_ID,
            o.Order_Date,
            o.Total_Amount,
            o.Status,
            o.Shipping_Address,
            o.Phone_Number
        FROM `orders` o
        WHERE o.Order_ID = :order_id AND o.User_ID = :user_id
    ");
    $stmt->execute([':order_id' => $order_id, ':user_id' => $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }

    // Fetch order items
    $stmt_items = $pdo->prepare("
        SELECT 
            oi.Quantity,
            oi.Price,
            p.Name as Name,
            p.Images as Images
        FROM `order_item` oi
        LEFT JOIN `product` p ON oi.Product_ID = p.Product_ID
        WHERE oi.Order_ID = :order_id
    ");
    $stmt_items->execute([':order_id' => $order_id]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'order' => $order,
        'items' => $items
    ]);

} catch (PDOException $e) {
    error_log("User Get Order Details Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>