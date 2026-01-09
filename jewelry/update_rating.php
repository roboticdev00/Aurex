<?php
// update_rating.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['User_ID'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'You must be logged in to update a rating']);
    exit;
}

// Get POST data
 $data = json_decode(file_get_contents('php://input'), true);
 $order_id = $data['order_id'] ?? null;
 $rating = $data['rating'] ?? null;
 $review_text = $data['review_text'] ?? '';
 $review_image = $data['review_image'] ?? null; // Base64 encoded image

// Validate input
if (!$order_id || !$rating || $rating < 1 || $rating > 5) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid rating data']);
    exit;
}

 $user_id = $_SESSION['User_ID'];

// Connect to database
require_once 'db_connect.php';

try {
    // Check if order belongs to user
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE Order_ID = :order_id AND User_ID = :user_id");
    $stmt->execute([':order_id' => $order_id, ':user_id' => $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }
    
    // Process image if provided
    $image_path = null;
    if ($review_image) {
        // Decode base64 image
        $image_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $review_image));
        
        // Generate unique filename
        $filename = 'review_' . $order_id . '_' . $user_id . '_' . time() . '.jpg';
        $upload_dir = 'images/reviews/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Save image
        $image_path = $upload_dir . $filename;
        file_put_contents($image_path, $image_data);
    }
    
    // Get products in this order
    $stmt = $pdo->prepare("SELECT Product_ID FROM order_item WHERE Order_ID = :order_id");
    $stmt->execute([':order_id' => $order_id]);
    $order_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($order_products)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No products found in this order']);
        exit;
    }
    
    // For each product in the order, update the rating
    foreach ($order_products as $product) {
        $product_id = $product['Product_ID'];
        
        // Check if rating exists
        $stmt = $pdo->prepare("SELECT * FROM order_ratings WHERE Order_ID = :order_id AND User_ID = :user_id AND Product_ID = :product_id");
        $stmt->execute([':order_id' => $order_id, ':user_id' => $user_id, ':product_id' => $product_id]);
        $existing_rating = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_rating) {
            // Update existing rating
            $update_fields = [
                ':rating' => $rating, 
                ':review_text' => $review_text, 
                ':order_id' => $order_id, 
                ':user_id' => $user_id,
                ':product_id' => $product_id
            ];
            
            // Only update image if a new one was provided
            if ($image_path) {
                $update_fields[':review_image'] = $image_path;
                $stmt = $pdo->prepare("
                    UPDATE order_ratings 
                    SET Rating = :rating, Review_Text = :review_text, Review_Image = :review_image, Rating_Date = CURRENT_TIMESTAMP 
                    WHERE Order_ID = :order_id AND User_ID = :user_id AND Product_ID = :product_id
                ");
            } else {
                $stmt = $pdo->prepare("
                    UPDATE order_ratings 
                    SET Rating = :rating, Review_Text = :review_text, Rating_Date = CURRENT_TIMESTAMP 
                    WHERE Order_ID = :order_id AND User_ID = :user_id AND Product_ID = :product_id
                ");
            }
            
            $stmt->execute($update_fields);
        }
    }
    
    // Update product ratings
    foreach ($order_products as $product) {
        $product_id = $product['Product_ID'];
        
        // Calculate new average rating for this product
        $stmt = $pdo->prepare("
            SELECT AVG(r.Rating) as avg_rating, COUNT(r.Rating_ID) as rating_count
            FROM order_ratings r
            WHERE r.Product_ID = :product_id
        ");
        $stmt->execute([':product_id' => $product_id]);
        $rating_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($rating_data && $rating_data['avg_rating'] > 0) {
            $avg_rating = round($rating_data['avg_rating'], 1);
            $rating_count = $rating_data['rating_count'];
            
            // Update product rating
            $stmt = $pdo->prepare("
                UPDATE product 
                SET Avg_Rating = :avg_rating, Rating_Count = :rating_count
                WHERE Product_ID = :product_id
            ");
            $stmt->execute([
                ':avg_rating' => $avg_rating,
                ':rating_count' => $rating_count,
                ':product_id' => $product_id
            ]);
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Rating updated successfully']);
    
} catch (PDOException $e) {
    error_log("Rating update error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>