<?php
// get_product_reviews.php
require_once 'db_connect.php';

header('Content-Type: application/json');

 $product_id = $_GET['product_id'] ?? null;

if (!$product_id) {
    echo json_encode(['success' => false, 'message' => 'Product ID is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            r.Rating, 
            r.Review_Text, 
            r.Rating_Date,
            u.Name as UserName
        FROM order_ratings r
        INNER JOIN user u ON r.User_ID = u.User_ID
        WHERE r.Product_ID = :product_id
        ORDER BY r.Rating_Date DESC
        LIMIT 10
    ");
    
    $stmt->execute([':product_id' => $product_id]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'reviews' => $reviews]);
} catch (PDOException $e) {
    error_log("Error fetching product reviews: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>