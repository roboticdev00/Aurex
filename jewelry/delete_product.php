<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

// RBAC Check
if (!isset($_SESSION['User_ID'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

$user_id = $_SESSION['User_ID'];

try {
    $stmt = $pdo->prepare("SELECT Role FROM user WHERE User_ID = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $user = $stmt->fetch();

    if (!$user || $user['Role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Admin access only']);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Authorization error']);
    exit;
}

// Get product ID from POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);
$product_id = $data['product_id'] ?? null;

if (!$product_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit;
}

try {
    // First, delete from order_item if product is in any orders
    $stmt = $pdo->prepare("DELETE FROM order_item WHERE Product_ID = :id");
    $stmt->execute([':id' => (int)$product_id]);

    // Delete from cart_item if product is in any carts
    $stmt = $pdo->prepare("DELETE FROM cart_item WHERE Product_ID = :id");
    $stmt->execute([':id' => (int)$product_id]);

    // Finally, delete the product
    $stmt = $pdo->prepare("DELETE FROM product WHERE Product_ID = :id");
    $result = $stmt->execute([':id' => (int)$product_id]);

    if ($result && $stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
    }
} catch (PDOException $e) {
    error_log("Delete Product Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>