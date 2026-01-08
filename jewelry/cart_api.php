<?php
// cart_api.php
session_start();
require_once 'db_connect.php'; // For PDO connection ($pdo)

header('Content-Type: application/json');

// Check for user session (Authorization)
if (!isset($_SESSION['User_ID'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$userId = $_SESSION['User_ID'];
$action = $_GET['action'] ?? '';

// Use file_get_contents for POST data
$data = file_get_contents('php://input') ? json_decode(file_get_contents('php://input'), true) : [];

$response = ['success' => false, 'message' => 'Invalid action.'];

try {
    // Helper function to get the user's Cart_ID, creating one if it doesn't exist
    function getOrCreateCartId($pdo, $userId) {
        $stmt = $pdo->prepare("SELECT Cart_ID FROM cart WHERE User_ID = :user_id");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $cart_id = $stmt->fetchColumn();

        if ($cart_id) {
            return $cart_id;
        }

        // Create new cart entry. Compute Cart_ID explicitly in case table has no AUTO_INCREMENT.
        $newIdStmt = $pdo->query("SELECT COALESCE(MAX(Cart_ID), 0) + 1 AS new_id FROM cart");
        $new_id = (int)$newIdStmt->fetchColumn();

        $stmt = $pdo->prepare("INSERT INTO cart (Cart_ID, User_ID) VALUES (:cart_id, :user_id)");
        $stmt->bindParam(':cart_id', $new_id, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $new_id;
    }

    $cart_id = getOrCreateCartId($pdo, $userId);

    switch ($action) {
        case 'fetch_cart':
            // Fetch all items in user's cart
            $stmt = $pdo->prepare("
                SELECT ci.Cart_Item_ID, ci.Product_ID, ci.Quantity, p.Name, p.Price, p.Images
                FROM cart_item ci
                JOIN product p ON ci.Product_ID = p.Product_ID
                WHERE ci.Cart_ID = :cart_id
                ORDER BY ci.Cart_Item_ID DESC
            ");
            $stmt->bindParam(':cart_id', $cart_id, PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response = ['success' => true, 'items' => $items];
            break;

        case 'add_to_cart':
            // Add product to cart (or increment quantity if already exists)
            $product_id = (int)($data['product_id'] ?? 0);
            $quantity = (int)($data['quantity'] ?? 1);

            if ($product_id <= 0 || $quantity <= 0) {
                $response = ['success' => false, 'message' => 'Invalid product or quantity.'];
                break;
            }

            // Get product stock
            $stock_stmt = $pdo->prepare("SELECT Stock FROM product WHERE Product_ID = :product_id");
            $stock_stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
            $stock_stmt->execute();
            $product = $stock_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                $response = ['success' => false, 'message' => 'Product not found.'];
                break;
            }

            $available_stock = (int)$product['Stock'];

            // Check if product exists in cart
            $stmt = $pdo->prepare("
                SELECT Cart_Item_ID, Quantity FROM cart_item 
                WHERE Cart_ID = :cart_id AND Product_ID = :product_id
            ");
            $stmt->bindParam(':cart_id', $cart_id, PDO::PARAM_INT);
            $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
            $stmt->execute();
            $existing_item = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing_item) {
                // Update quantity
                $new_qty = $existing_item['Quantity'] + $quantity;
                if ($new_qty > $available_stock) {
                    $response = ['success' => false, 'message' => 'Cannot add more items. Only ' . $available_stock . ' in stock.'];
                    break;
                }
                $update_stmt = $pdo->prepare("
                    UPDATE cart_item SET Quantity = :quantity 
                    WHERE Cart_Item_ID = :cart_item_id
                ");
                $update_stmt->bindParam(':quantity', $new_qty, PDO::PARAM_INT);
                $update_stmt->bindParam(':cart_item_id', $existing_item['Cart_Item_ID'], PDO::PARAM_INT);
                $update_stmt->execute();
                $response = ['success' => true, 'message' => 'Item quantity updated.'];
            } else {
                // Check for new item
                if ($quantity > $available_stock) {
                    $response = ['success' => false, 'message' => 'Cannot add item. Only ' . $available_stock . ' in stock.'];
                    break;
                }
                // Compute Cart_Item_ID explicitly in case no AUTO_INCREMENT
                $row = $pdo->query("SELECT COALESCE(MAX(Cart_Item_ID), 0) + 1 AS new_id FROM cart_item")->fetch(PDO::FETCH_ASSOC);
                $new_item_id = (int)($row['new_id'] ?? 1);

                // Insert new item with explicit id
                $insert_stmt = $pdo->prepare("
                    INSERT INTO cart_item (Cart_Item_ID, Cart_ID, Product_ID, Quantity) 
                    VALUES (:cart_item_id, :cart_id, :product_id, :quantity)
                ");
                $insert_stmt->bindParam(':cart_item_id', $new_item_id, PDO::PARAM_INT);
                $insert_stmt->bindParam(':cart_id', $cart_id, PDO::PARAM_INT);
                $insert_stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
                $insert_stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
                $insert_stmt->execute();
                $response = ['success' => true, 'message' => 'Item added to cart.'];
            }
            break;

        case 'update_quantity':
            $cart_item_id = (int)($data['cart_item_id'] ?? 0);
            $delta = (int)($data['delta'] ?? 0);

            if ($cart_item_id <= 0) {
                $response = ['success' => false, 'message' => 'Invalid cart item.'];
                break;
            }

            // Get current quantity and product_id
            $stmt = $pdo->prepare("
                SELECT ci.Quantity, ci.Product_ID, p.Stock 
                FROM cart_item ci 
                JOIN product p ON ci.Product_ID = p.Product_ID 
                WHERE ci.Cart_Item_ID = :cart_item_id AND ci.Cart_ID = :cart_id
            ");
            $stmt->bindParam(':cart_item_id', $cart_item_id, PDO::PARAM_INT);
            $stmt->bindParam(':cart_id', $cart_id, PDO::PARAM_INT);
            $stmt->execute();
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                $response = ['success' => false, 'message' => 'Item not found in cart.'];
                break;
            }

            $new_qty = max(1, (int)$item['Quantity'] + $delta);

            if ($new_qty > (int)$item['Stock']) {
                $response = ['success' => false, 'message' => 'Cannot update quantity. Only ' . $item['Stock'] . ' in stock.'];
                break;
            }

            $update_stmt = $pdo->prepare("UPDATE cart_item SET Quantity = :quantity WHERE Cart_Item_ID = :cart_item_id");
            $update_stmt->bindParam(':quantity', $new_qty, PDO::PARAM_INT);
            $update_stmt->bindParam(':cart_item_id', $cart_item_id, PDO::PARAM_INT);
            $update_stmt->execute();
            $response = ['success' => true, 'message' => 'Quantity updated.'];
            break;

        case 'remove_item':
            $cart_item_id = (int)($data['cart_item_id'] ?? 0);

            if ($cart_item_id <= 0) {
                $response = ['success' => false, 'message' => 'Invalid cart item.'];
                break;
            }

            $stmt = $pdo->prepare("DELETE FROM cart_item WHERE Cart_Item_ID = :cart_item_id AND Cart_ID = :cart_id");
            $stmt->bindParam(':cart_item_id', $cart_item_id, PDO::PARAM_INT);
            $stmt->bindParam(':cart_id', $cart_id, PDO::PARAM_INT);
            $stmt->execute();
            $response = ['success' => true, 'message' => 'Item removed from cart.'];
            break;

        default:
            $response = ['success' => false, 'message' => 'Unknown action.'];
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Cart API Database error: " . $e->getMessage());
    $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
}

echo json_encode($response);
?>