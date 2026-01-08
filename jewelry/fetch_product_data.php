<?php
// ... Existing session and user retrieval code (in index_user.php) ...

// --- PRODUCT DATA FETCH ---
require_once 'db_connect.php'; // Assuming this provides the PDO $pdo object

$dynamic_products = [];
try {
    $stmt_products = $pdo->prepare("
        SELECT 
            p.Product_ID, 
            p.Name AS ProductName, 
            p.Price, 
            p.Images, 
            c.Category_Name 
        FROM product p
        JOIN category c ON p.Category_ID = c.Category_ID
        WHERE p.Availability = 'In Stock' OR p.Availability = 'Low Stock'
        ORDER BY p.Product_ID DESC
    ");
    $stmt_products->execute();
    $dynamic_products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // In a production environment, you would log this error, not display it to the user.
    error_log("Product Fetch Error: " . $e->getMessage());
    $error_message = "Failed to load jewelry products.";
}
// --- END PRODUCT DATA FETCH ---

?>