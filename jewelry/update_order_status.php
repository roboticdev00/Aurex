<?php
session_start();
require_once 'db_connect.php';
require_once 'utilities.php';

header('Content-Type: application/json');

if (!isset($_SESSION['User_ID'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

$user_id = $_SESSION['User_ID'];

// Verify user is admin
try {
    $stmt = $pdo->prepare("SELECT Role FROM user WHERE User_ID = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || $user['Role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Admin access only']);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Authorization error']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$order_id = $data['order_id'] ?? null;
$status = $data['status'] ?? null;

if (!$order_id || !$status) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

// Validate status
$validStatuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
if (!in_array($status, $validStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    // First, get the current status to check if we need to update stock
    $stmt_current = $pdo->prepare("SELECT Status FROM orders WHERE Order_ID = :order_id");
    $stmt_current->execute([':order_id' => $order_id]);
    $current_order = $stmt_current->fetch(PDO::FETCH_ASSOC);

    if (!$current_order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }

    $old_status = $current_order['Status'];

    // Update the order status
    $stmt = $pdo->prepare("UPDATE orders SET Status = :status WHERE Order_ID = :order_id");
    $result = $stmt->execute([':status' => $status, ':order_id' => $order_id]);

    if ($result) {
        // If status changed to "Shipped" and wasn't already, decrement stock
        if ($status === 'Shipped' && $old_status !== 'Shipped') {
            // Get order items
            $stmt_items = $pdo->prepare("SELECT Product_ID, Quantity FROM order_item WHERE Order_ID = :order_id");
            $stmt_items->execute([':order_id' => $order_id]);
            $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

            foreach ($items as $item) {
                // Decrement stock, but ensure it doesn't go negative
                $stmt_stock = $pdo->prepare("UPDATE product SET Stock = GREATEST(0, Stock - :quantity) WHERE Product_ID = :product_id");
                $stmt_stock->execute([
                    ':quantity' => $item['Quantity'],
                    ':product_id' => $item['Product_ID']
                ]);
                
                // Update availability based on new stock
                $stmt_new_stock = $pdo->prepare("SELECT Stock FROM product WHERE Product_ID = :product_id");
                $stmt_new_stock->execute([':product_id' => $item['Product_ID']]);
                $new_stock = $stmt_new_stock->fetchColumn();
                
                $availability = getAvailabilityFromStock($new_stock);
                $stmt_avail = $pdo->prepare("UPDATE product SET Availability = :availability WHERE Product_ID = :product_id");
                $stmt_avail->execute([':availability' => $availability, ':product_id' => $item['Product_ID']]);
            }
        }

        echo json_encode(['success' => true, 'message' => 'Order updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update order']);
    }
} catch (PDOException $e) {
    error_log("Update Order Status Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>