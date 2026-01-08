<?php
// checkout_handler.php
session_start();
header('Content-Type: application/json');

// NOTE: Ensure 'db_connect.php' exists and provides the PDO connection $pdo
require_once 'db_connect.php'; 

$user_id = $_SESSION['User_ID'] ?? null;

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// Input sanitation and validation
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');
$payment_method = trim($_POST['payment_method'] ?? '');
$latitude = isset($_POST['latitude']) ? floatval($_POST['latitude']) : null;
$longitude = isset($_POST['longitude']) ? floatval($_POST['longitude']) : null;

if (empty($name) || empty($email) || empty($address) || empty($payment_method)) {
    echo json_encode(['success' => false, 'message' => 'Missing required order details.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Get user order history for discounts
    $stmt = $pdo->prepare("SELECT COUNT(*) as order_count, COALESCE(SUM(Total_Amount), 0) as total_spent FROM orders WHERE User_ID = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $history = $stmt->fetch(PDO::FETCH_ASSOC);
    $order_count = $history['order_count'];
    $total_spent = $history['total_spent'];

    // Get cart items and calculate subtotal
    $stmt = $pdo->prepare("
        SELECT ci.Product_ID, ci.Quantity, p.Price
        FROM cart_item ci
        JOIN product p ON ci.Product_ID = p.Product_ID
        WHERE ci.Cart_ID = (SELECT Cart_ID FROM cart WHERE User_ID = :user_id)
    ");
    $stmt->execute([':user_id' => $user_id]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($cart_items)) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Cart is empty']);
        exit;
    }

    $subtotal = 0;
    foreach ($cart_items as $item) {
        $subtotal += $item['Price'] * $item['Quantity'];
    }

    // Calculate shipping and discounts
    $shipping = 150; // Fixed shipping cost
    $discount = 0;

    // Frequent buyer: >3 orders, 10% discount
    if ($order_count > 3) {
        $discount += 0.1 * $subtotal;
    }

    // Big spender: total spent >3000, free shipping
    if ($total_spent > 3000) {
        $shipping = 0;
    }

    $total_amount = $subtotal + $shipping - $discount;

    if ($total_amount <= 0) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Invalid total amount after discounts.']);
        exit;
    }

    // 1. Create order
    $order_date = date('Y-m-d H:i:s');
    $order_status = ($payment_method === 'Bank Transfer') ? 'Processing' : 'Pending';

    $stmt = $pdo->prepare("
        INSERT INTO orders (User_ID, Order_Date, Total_Amount, Status, Shipping_Address, Phone_Number, Email, Latitude, Longitude)
        VALUES (:user_id, :order_date, :total_amount, :status, :shipping_address, :phone_number, :email, :latitude, :longitude)
    ");
    $stmt->execute([
        ':user_id' => $user_id,
        ':order_date' => $order_date,
        ':total_amount' => $total_amount,
        ':status' => $order_status,
        ':shipping_address' => $address,
        ':phone_number' => $phone,
        ':email' => $email,
        ':latitude' => $latitude,
        ':longitude' => $longitude
    ]);

    $order_id = $pdo->lastInsertId();

    // 2. Create order items
    $stmt = $pdo->prepare("
        INSERT INTO order_item (Order_ID, Product_ID, Quantity, Price)
        VALUES (:order_id, :product_id, :quantity, :price)
    ");

    foreach ($cart_items as $item) {
        $stmt->execute([
            ':order_id' => $order_id,
            ':product_id' => $item['Product_ID'],
            ':quantity' => $item['Quantity'],
            ':price' => $item['Price']
        ]);
    }

    // 3. Handle payment if Bank Transfer
    if ($payment_method === 'Bank Transfer') {
        $cardholder_name = $_POST['cardholder_name'] ?? '';
        $card_number = $_POST['card_number'] ?? '';
        $expiry_date = $_POST['expiry_date'] ?? '';
        $cvv = $_POST['cvv'] ?? '';

        // Store encrypted payment info (in production, use proper encryption)
        $stmt = $pdo->prepare("
            INSERT INTO payment (Order_ID, Payment_Method, Cardholder_Name, Card_Number, Expiry_Date, CVV, Payment_Status)
            VALUES (:order_id, :payment_method, :cardholder_name, :card_number, :expiry_date, :cvv, :payment_status)
        ");
        $stmt->execute([
            ':order_id' => $order_id,
            ':payment_method' => $payment_method,
            ':cardholder_name' => $cardholder_name,
            ':card_number' => substr($card_number, -4), // Store only last 4 digits for security
            ':expiry_date' => $expiry_date,
            ':cvv' => $cvv, // In production, encrypt this
            ':payment_status' => 'Completed'
        ]);
    }

    // 4. Clear user's cart
    $stmt = $pdo->prepare("
        DELETE FROM cart_item
        WHERE Cart_ID = (SELECT Cart_ID FROM cart WHERE User_ID = :user_id)
    ");
    $stmt->execute([':user_id' => $user_id]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Order placed successfully',
        'order_id' => $order_id,
        'subtotal' => $subtotal,
        'shipping' => $shipping,
        'discount' => $discount,
        'total_amount' => $total_amount
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Checkout error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>