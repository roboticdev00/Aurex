<?php
// cart_handler.php
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

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$response = ['success' => false, 'message' => 'Invalid action.'];

try {
    // Helper function to get or create the Cart_ID for the user
    function get_cart_id($pdo, $user_id) {
        // Check if cart exists
        $stmt = $pdo->prepare("SELECT Cart_ID FROM cart WHERE User_ID = :user_id");
        $stmt->execute([':user_id' => $user_id]);
        $cart_id = $stmt->fetchColumn();

        if ($cart_id) {
            return $cart_id;
        }

        // If not found, create a new cart.
        // The cart table in this DB may not have AUTO_INCREMENT on Cart_ID,
        // so compute a new id explicitly to avoid DB errors.
        $newIdStmt = $pdo->query("SELECT COALESCE(MAX(Cart_ID), 0) + 1 AS new_id FROM cart");
        $new_id = (int)$newIdStmt->fetchColumn();

        $insert = $pdo->prepare("INSERT INTO cart (Cart_ID, User_ID) VALUES (:cart_id, :user_id)");
        $insert->execute([':cart_id' => $new_id, ':user_id' => $user_id]);

        return $new_id;
    }
    
    $cart_id = get_cart_id($pdo, $user_id);

    if ($action === 'fetch_cart') {
        $stmt = $pdo->prepare("
            SELECT ci.Cart_Item_ID, ci.Product_ID, ci.Quantity, p.Name, p.Price, p.Images
            FROM cart_item ci
            JOIN product p ON ci.Product_ID = p.Product_ID
            WHERE ci.Cart_ID = :cart_id
            ORDER BY ci.Cart_Item_ID DESC
        ");
        $stmt->execute([':cart_id' => $cart_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $response = ['success' => true, 'items' => $items];

    } elseif ($action === 'add_to_cart') {
        $product_id = (int)($_POST['product_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 1);

        if ($product_id <= 0 || $quantity <= 0) {
            $response = ['success' => false, 'message' => 'Invalid product or quantity.'];
        } else {
            // Get product stock
            $stock_stmt = $pdo->prepare("SELECT Stock FROM product WHERE Product_ID = :product_id");
            $stock_stmt->execute([':product_id' => $product_id]);
            $product = $stock_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                $response = ['success' => false, 'message' => 'Product not found.'];
            } else {
                $available_stock = (int)$product['Stock'];

                // Check if product already in cart
                $check_stmt = $pdo->prepare("
                    SELECT Cart_Item_ID, Quantity FROM cart_item 
                    WHERE Cart_ID = :cart_id AND Product_ID = :product_id
                ");
                $check_stmt->execute([':cart_id' => $cart_id, ':product_id' => $product_id]);
                $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    // Update quantity
                    $new_qty = $existing['Quantity'] + $quantity;
                    if ($new_qty > $available_stock) {
                        $response = ['success' => false, 'message' => 'Cannot add more items. Only ' . $available_stock . ' in stock.'];
                    } else {
                        $upd = $pdo->prepare("UPDATE cart_item SET Quantity = :qty WHERE Cart_Item_ID = :id");
                        $upd->execute([':qty' => $new_qty, ':id' => $existing['Cart_Item_ID']]);
                        $response = ['success' => true, 'message' => 'Item quantity updated.'];
                    }
                } else {
                    // Check for new item
                    if ($quantity > $available_stock) {
                        $response = ['success' => false, 'message' => 'Cannot add item. Only ' . $available_stock . ' in stock.'];
                    } else {
                        // Insert new item
                        // Compute Cart_Item_ID explicitly if the table lacks AUTO_INCREMENT
                        $newItemIdRow = $pdo->query("SELECT COALESCE(MAX(Cart_Item_ID), 0) + 1 AS new_id FROM cart_item")->fetch(PDO::FETCH_ASSOC);
                        $new_item_id = (int)($newItemIdRow['new_id'] ?? 1);

                        $ins = $pdo->prepare("
                            INSERT INTO cart_item (Cart_Item_ID, Cart_ID, Product_ID, Quantity) 
                            VALUES (:cart_item_id, :cart_id, :product_id, :quantity)
                        ");
                        $ins->execute([':cart_item_id' => $new_item_id, ':cart_id' => $cart_id, ':product_id' => $product_id, ':quantity' => $quantity]);
                        $response = ['success' => true, 'message' => 'Item added to cart.'];
                    }
                }
            }
        }

    } elseif ($action === 'update_quantity') {
        $cart_item_id = (int)($_POST['cart_item_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 1);

        if ($cart_item_id <= 0 || $quantity <= 0) {
            $response = ['success' => false, 'message' => 'Invalid quantity.'];
        } else {
            // Get product stock
            $stock_stmt = $pdo->prepare("
                SELECT p.Stock FROM cart_item ci 
                JOIN product p ON ci.Product_ID = p.Product_ID 
                WHERE ci.Cart_Item_ID = :cart_item_id AND ci.Cart_ID = :cart_id
            ");
            $stock_stmt->execute([':cart_item_id' => $cart_item_id, ':cart_id' => $cart_id]);
            $item = $stock_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                $response = ['success' => false, 'message' => 'Item not found.'];
            } elseif ($quantity > (int)$item['Stock']) {
                $response = ['success' => false, 'message' => 'Cannot update quantity. Only ' . $item['Stock'] . ' in stock.'];
            } else {
                $stmt = $pdo->prepare("UPDATE cart_item SET Quantity = :qty WHERE Cart_Item_ID = :id AND Cart_ID = :cart_id");
                $stmt->execute([':qty' => $quantity, ':id' => $cart_item_id, ':cart_id' => $cart_id]);
                $response = ['success' => true, 'message' => 'Quantity updated.'];
            }
        }

    } elseif ($action === 'remove_item') {
        $cart_item_id = (int)($_POST['cart_item_id'] ?? 0);

        if ($cart_item_id <= 0) {
            $response = ['success' => false, 'message' => 'Invalid cart item.'];
        } else {
            $stmt = $pdo->prepare("DELETE FROM cart_item WHERE Cart_Item_ID = :id AND Cart_ID = :cart_id");
            $stmt->execute([':id' => $cart_item_id, ':cart_id' => $cart_id]);
            $response = ['success' => true, 'message' => 'Item removed.'];
        }
    }

} catch (PDOException $e) {
    error_log("Cart Handler Error: " . $e->getMessage());
    http_response_code(500);
    $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
}

echo json_encode($response);
?>

