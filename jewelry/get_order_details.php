<?php
session_start();
require_once 'db_connect.php';

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
            u.Name as Customer_Name,
            u.Email as Customer_Email,
            o.Order_Date,
            o.Total_Amount,
            o.Status,
            o.Shipping_Address,
            o.Phone_Number
        FROM `orders` o
        LEFT JOIN `user` u ON o.User_ID = u.User_ID
        WHERE o.Order_ID = :order_id
    ");
    $stmt->execute([':order_id' => $order_id]);
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
            p.Name as Product_Name,
            p.Images as Product_Image
        FROM `order_item` oi
        LEFT JOIN `product` p ON oi.Product_ID = p.Product_ID
        WHERE oi.Order_ID = :order_id
    ");
    $stmt_items->execute([':order_id' => $order_id]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    // Build HTML
    $html = '
        <div class="space-y-6">
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="font-semibold text-lg text-brand-dark mb-2">Order Information</h3>
                <p><strong>Order ID:</strong> #' . htmlspecialchars($order['Order_ID']) . '</p>
                <p><strong>Customer:</strong> ' . htmlspecialchars($order['Customer_Name']) . '</p>
                <p><strong>Email:</strong> ' . htmlspecialchars($order['Customer_Email']) . '</p>
                <p><strong>Date:</strong> ' . date('F d, Y H:i', strtotime($order['Order_Date'])) . '</p>
                <p><strong>Status:</strong> ' . htmlspecialchars($order['Status']) . '</p>
                <p><strong>Total:</strong> ₱' . number_format($order['Total_Amount'], 2) . '</p>
            </div>

            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="font-semibold text-lg text-brand-dark mb-2">Shipping Information</h3>
                <p><strong>Address:</strong> ' . htmlspecialchars($order['Shipping_Address']) . '</p>
                <p><strong>Phone:</strong> ' . htmlspecialchars($order['Phone_Number']) . '</p>
            </div>

            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="font-semibold text-lg text-brand-dark mb-2">Order Items</h3>
                <div class="space-y-2">';

    foreach ($items as $item) {
        $html .= '
                    <div class="flex items-center space-x-4 p-2 bg-white rounded">
                        <img src="' . htmlspecialchars($item['Product_Image'] ?? 'images/products/default.jpg') . '" alt="' . htmlspecialchars($item['Product_Name']) . '" class="w-12 h-12 object-cover rounded">
                        <div class="flex-1">
                            <p class="font-semibold">' . htmlspecialchars($item['Product_Name']) . '</p>
                            <p class="text-sm text-gray-600">Qty: ' . $item['Quantity'] . ' × ₱' . number_format($item['Price'], 2) . '</p>
                        </div>
                        <p class="font-semibold">₱' . number_format($item['Quantity'] * $item['Price'], 2) . '</p>
                    </div>';
    }

    $html .= '
                </div>
            </div>
        </div>
    ';

    echo json_encode(['success' => true, 'html' => $html]);

} catch (PDOException $e) {
    error_log("Get Order Details Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>