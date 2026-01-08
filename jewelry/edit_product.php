<?php
// edit_product.php
session_start();
require_once 'db_connect.php'; // PDO connection
require_once 'utilities.php';

// --- CRITICAL RBAC CHECK ---
if (!isset($_SESSION['User_ID']) || ($_SESSION['Role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

// Get the current admin user's name for the sidebar greeting
 $userName = '';
if (isset($_SESSION['User_ID'])) {
    $user_id = $_SESSION['User_ID'];
    $stmt_name = $pdo->prepare("SELECT Name FROM user WHERE User_ID = :user_id");
    $stmt_name->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_name->execute();
    $result_name = $stmt_name->fetch();
    if ($result_name) {
        $userName = htmlspecialchars($result_name['Name']);
    }
}

 $product_id = $_GET['id'] ?? null;
 $is_editing = $product_id !== null;
 $product = [];
 $categories = [];
 $message = '';
 $message_type = 'success';

// --- IMAGE UPLOAD CONFIGURATION ---
 $upload_dir = 'images/products/'; 
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}
 $max_file_size = 5 * 1024 * 1024; // 5MB

// --- PDO Database Operations for CRUD ---
try {
    // 1. Fetch Categories for Dropdown
    $stmt_cat = $pdo->query("SELECT Category_ID, Category_Name FROM category ORDER BY Category_Name");
    $categories = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch Existing Product Data (if editing)
    if ($is_editing) {
        $stmt_product = $pdo->prepare("SELECT * FROM product WHERE Product_ID = :id");
        $stmt_product->bindParam(':id', $product_id, PDO::PARAM_INT);
        $stmt_product->execute();
        $product = $stmt_product->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $message = "Error: Product ID {$product_id} not found.";
            $message_type = 'error';
            $is_editing = false;
        }
    }

    // 3. Handle Form Submission (INSERT or UPDATE)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $price = floatval($_POST['price']);
        $stock = intval($_POST['stock'] ?? 0);
        $add_stock = intval($_POST['add_stock'] ?? 0);
        // Availability is automatic based on stock
        $category_id = intval($_POST['category_id']);
        
        // Use existing image path as default
        $image_path = $product['Images'] ?? ''; 

        // --- Handle File Upload using 'images' name from HTML input ---
        if (isset($_FILES['images']) && $_FILES['images']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['images'];
            $file_size = $file['size'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

            // Validation checks
            if ($file_size > $max_file_size) {
                $message = "Error: File size exceeds limit of " . ($max_file_size / 1024 / 1024) . "MB.";
                $message_type = 'error';
            } elseif (!in_array($file_ext, $allowed_extensions)) {
                $message = "Error: Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.";
                $message_type = 'error';
            } else {
                // Generate a unique filename and set destination
                $new_file_name = uniqid('prod_', true) . '.' . $file_ext;
                $destination = $upload_dir . $new_file_name;

                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    // Delete old image if it exists and is valid
                    if ($is_editing && !empty($product['Images']) && file_exists($product['Images'])) {
                         unlink($product['Images']);
                    }
                    $image_path = $destination; // Update path for database
                } else {
                    $message = "Error: Failed to move uploaded file.";
                    $message_type = 'error';
                }
            }
        }
        
        // Continue with database operation only if no fatal file upload error occurred
        if ($message_type !== 'error') {
            if ($is_editing) {
                // --- UPDATE operation (Removed Rating) ---
                $sql = "UPDATE product SET 
                        Name = :name, 
                        Description = :description, 
                        Price = :price, 
                        Category_ID = :category_id,
                        Images = :images
                        WHERE Product_ID = :id";
                
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id', $product_id, PDO::PARAM_INT);
                $redirect_msg = 'updated';

            } else {
                // --- INSERT operation (Add New Product) (Removed Rating) ---
                $sql = "INSERT INTO product 
                        (Name, Description, Price, Stock, Category_ID, Images) 
                        VALUES 
                         (:name, :description, :price, :stock, :category_id, :images)";
                
                $stmt = $pdo->prepare($sql);
                $redirect_msg = 'added';
            }

            // Bind common parameters
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':price', $price);
            if (!$is_editing) {
                $stmt->bindParam(':stock', $stock, PDO::PARAM_INT);
            }
            $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
            $stmt->bindParam(':images', $image_path); // Use new/old path
            // Removed bindParam for ':rating'

            if ($stmt->execute()) {
                // Get product ID for availability update
                $current_product_id = $is_editing ? $product_id : $pdo->lastInsertId();
                
                // If editing and adding stock, update stock first
                if ($is_editing && $add_stock > 0) {
                    $stmt_stock = $pdo->prepare("UPDATE product SET Stock = Stock + :add_stock WHERE Product_ID = :id");
                    $stmt_stock->execute([':add_stock' => $add_stock, ':id' => $current_product_id]);
                }
                
                // Get current stock after operations
                $stmt_stock = $pdo->prepare("SELECT Stock FROM product WHERE Product_ID = :id");
                $stmt_stock->execute([':id' => $current_product_id]);
                $current_stock = $stmt_stock->fetchColumn();
                
                // Update availability based on stock
                $availability = getAvailabilityFromStock($current_stock);
                $stmt_avail = $pdo->prepare("UPDATE product SET Availability = :availability WHERE Product_ID = :id");
                $stmt_avail->execute([':availability' => $availability, ':id' => $current_product_id]);
                
                // If it was an update and path changed, refresh local product data
                if ($is_editing && isset($product['Images']) && $product['Images'] !== $image_path) {
                    $product['Images'] = $image_path;
                }
                // Redirect back to product list with a success message
                header("Location: manage_products.php?msg=" . $redirect_msg);
                exit;
            } else {
                $message = "Error: Database failed to save product.";
                $message_type = 'error';
            }
        }
    }

} catch (PDOException $e) {
    $message = "Database Error: " . $e->getMessage();
    $message_type = 'error';
    error_log("Product CRUD Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $is_editing ? 'Edit Product' : 'Add New Product' ?> - Jewellery Admin</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">

    <script>
    tailwind.config = {
    theme: {
    extend: {
    fontFamily: {
    sans: ['Inter', 'sans-serif'],
    serif: ['Playfair Display', 'serif'],
    },
    colors: {
    'brand-beige': '#FBF9F6',
    'brand-dark': '#4D4C48',
    'brand-teal': '#8B7B61',
    'brand-text': '#4D4C48',
    'brand-subtext': '#7A7977',
    'teal-light': '#DCCEB8', 
    'teal-darker': '#3F3F3C', 
    }
    }
    }
    }
    </script>

    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #FBF9F6; /* brand-beige */
            color: #4D4C48; /* brand-dark */
        }
        .admin-sidebar {
            background-color: #4D4C48; /* brand-dark */
        }
        .nav-link:hover {
            background-color: #8B7B61; /* brand-teal */
            color: white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        .logout-btn:hover {
            background-color: #8B7B61; 
            color: white;
            border-color: #8B7B61;
        }
    </style>
</head>
<body class="flex antialiased">
    
    <div class="w-64 admin-sidebar h-screen p-6 fixed flex flex-col shadow-2xl">
        <h1 class="font-serif text-white text-3xl font-bold mb-10 border-b border-gray-600 pb-4">jewellery</h1>
        
        <div class="text-white mb-8 border-b border-teal-light/50 pb-4">
            <p class="font-semibold text-sm text-teal-light uppercase tracking-wider">Store Administrator</p>
            <p class="text-lg font-bold text-white mt-1"><?= $userName ?></p>
        </div>
        
        <ul class="space-y-2 flex-grow">
            <li><a href="admin_dashboard.php" class="block p-3 rounded text-white nav-link hover:bg-brand-teal transition-all">
                <span class="font-medium">Dashboard</span>
            </a></li>
            <li><a href="manage_users.php" class="block p-3 rounded text-white nav-link hover:bg-brand-teal transition-all">
                <span class="font-medium">Manage Users</span>
            </a></li>
            <li><a href="manage_products.php" class="block p-3 rounded text-white nav-link hover:bg-brand-teal transition-all">
                <span class="font-medium">Manage Products</span>
            </a></li>
            <li><a href="manage_orders.php" class="block p-3 rounded text-white nav-link hover:bg-brand-teal transition-all">
                <span class="font-medium">Manage Orders</span>
            </a></li>
             <li><a href="manage_report.php" class="block p-3 rounded text-white hover:bg-brand-teal hover:shadow-md transition-all">
                <span class="font-medium">Sales Report</span>
            </a></li>
        </ul>
        
        <a href="logout.php" class="mt-auto block w-full text-center p-3 rounded-full logout-btn shadow-lg">
            <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
            Sign Out
        </a>
    </div>

    <div class="flex-1 ml-64 p-10">
        <div class="flex justify-between items-center mb-6 border-b border-gray-200 pb-4">
            <h2 class="font-serif text-4xl font-bold text-brand-dark"><?= $is_editing ? 'Edit Product' : 'Add New Product' ?></h2>
            <a href="manage_products.php" class="font-semibold text-brand-subtext hover:text-brand-dark transition-colors flex items-center">
                &larr; Back to Product List
            </a>
        </div>

        <?php if ($message): ?>
        <div class="p-4 mb-4 rounded-xl <?= $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?> border border-gray-200">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <div class="bg-white p-8 rounded-xl shadow-lg max-w-4xl mx-auto">
            <form method="POST" enctype="multipart/form-data">
                
                <?php if ($is_editing): ?>
                    <input type="hidden" name="product_id" value="<?= htmlspecialchars($product['Product_ID']) ?>">
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="mb-4">
                        <label for="name" class="block text-sm font-medium text-brand-dark mb-1">Product Name</label>
                        <input type="text" id="name" name="name" value="<?= htmlspecialchars($product['Name'] ?? '') ?>" required 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-brand-teal focus:border-brand-teal transition">
                    </div>

                    <div class="mb-4">
                        <label for="category_id" class="block text-sm font-medium text-brand-dark mb-1">Category</label>
                        <select id="category_id" name="category_id" required 
                                 class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-brand-teal focus:border-brand-teal transition">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['Category_ID'] ?>" 
                                    <?= (isset($product['Category_ID']) && $product['Category_ID'] == $cat['Category_ID']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['Category_Name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label for="price" class="block text-sm font-medium text-brand-dark mb-1">Price (₱)</label>
                        <input type="number" step="0.01" id="price" name="price" value="<?= htmlspecialchars($product['Price'] ?? '') ?>" required 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-brand-teal focus:border-brand-teal transition">
                    </div>

                    <?php if (!$is_editing): ?>
                    <div class="mb-4">
                        <label for="stock" class="block text-sm font-medium text-brand-dark mb-1">Initial Stock Quantity</label>
                        <input type="number" id="stock" name="stock" value="" required 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-brand-teal focus:border-brand-teal transition">
                    </div>
                    <?php else: ?>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-brand-dark mb-1">Current Stock Quantity</label>
                        <input type="text" value="<?= htmlspecialchars($product['Stock'] ?? 0) ?>" readonly 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-600">
                    </div>
                    <div class="mb-4">
                        <label for="add_stock" class="block text-sm font-medium text-brand-dark mb-1">Add to Stock (Resupply)</label>
                        <input type="number" id="add_stock" name="add_stock" value="0" min="0" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-brand-teal focus:border-brand-teal transition">
                        <p class="text-xs text-brand-subtext mt-1">Enter the quantity to add to current stock. Leave as 0 if not resupplying.</p>
                    </div>
                    <?php endif; ?>
                </div> 
                
                <div class="mb-4">
                    <label for="description" class="block text-sm font-medium text-brand-dark mb-1">Description</label>
                    <textarea id="description" name="description" rows="4" required 
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-brand-teal focus:border-brand-teal transition"><?= htmlspecialchars($product['Description'] ?? '') ?></textarea>
                </div>

                <div class="mb-6">
                    <label for="images" class="block text-sm font-medium text-brand-dark mb-1">Product Image</label>
                    
                    <input type="file" id="images" name="images" accept="image/*"
                        class="block w-full text-sm text-brand-dark
                              file:mr-4 file:py-2 file:px-4
                              file:rounded-full file:border-0
                              file:text-sm file:font-semibold
                              file:bg-brand-teal file:text-white
                              hover:file:bg-brand-dark
                              transition">
                    <p class="text-xs text-brand-subtext mt-1">Choose a product image (JPG, PNG, etc.). Max size: 5MB. *Uploading a new image will replace the old one.*</p>
                    
                    <?php if (!empty($product['Images'])): ?>
                        <div class="mt-4">
                             <p class="text-sm font-medium text-brand-dark mb-1">Current Image:</p>
                             <img src="<?= htmlspecialchars($product['Images']) ?>" alt="Product Image" class="rounded-lg max-h-32 object-cover border border-gray-200 shadow-sm">
                        </div>
                    <?php endif; ?>
                </div>

                <div class="flex justify-end gap-4 mt-6">
                    <a href="manage_products.php" class="bg-gray-200 text-brand-dark px-6 py-2.5 rounded-full font-semibold hover:bg-gray-300 transition-colors">
                        Cancel
                    </a>
                    <button type="submit" class="bg-brand-teal text-white px-8 py-2.5 rounded-full font-semibold hover:bg-opacity-90 transition-colors shadow-md">
                        <?= $is_editing ? 'Update Product' : 'Add Product' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>