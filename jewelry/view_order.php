<?php
// view_order.php
session_start();
require_once 'db_connect.php'; // PDO connection

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

$order_id = $_GET['id'] ?? null;
if (!$order_id || !is_numeric($order_id)) {
    header('Location: manage_orders.php');
    exit;
}

$order_summary = null;
$order_items = [];
$message = '';

try {
    // --- 1. Handle Status Update POST Request ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_status'])) {
        $new_status = $_POST['new_status'];
        
        $stmt_update = $pdo->prepare("UPDATE tbl_order SET Status = :status WHERE Order_ID = :id");
        $stmt_update->bindParam(':status', $new_status);
        $stmt_update->bindParam(':id', $order_id, PDO::PARAM_INT);
        $stmt_update->execute();
        
        $message = "Order #{$order_id} status successfully updated to '{$new_status}'.";
    }

    // --- 2. Fetch Order Summary (Order + Customer Info) ---
    $stmt_summary = $pdo->prepare("
        SELECT 
            o.Order_ID, 
            u.Name AS CustomerName, 
            u.Email AS CustomerEmail,
            o.Total_Amount, 
            o.Status AS OrderStatus,
            o.Quantity AS TotalItems,
            DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s') AS OrderDate -- Placeholder
        FROM tbl_order o
        JOIN user u ON o.User_ID = u.User_ID
        WHERE o.Order_ID = :id
    ");
    $stmt_summary->bindParam(':id', $order_id, PDO::PARAM_INT);
    $stmt_summary->execute();
    $order_summary = $stmt_summary->fetch(PDO::FETCH_ASSOC);

    if (!$order_summary) {
        $message = "Error: Order #{$order_id} not found.";
        // Set message and stop execution
        goto end_script; 
    }

    // --- 3. Fetch Order Items (Details + Product Info) ---
    $stmt_items = $pdo->prepare("
        SELECT 
            od.Quantity,
            od.Price,
            p.Name AS ProductName,
            p.Images
        FROM order_details od
        JOIN product p ON od.Product_ID = p.Product_ID
        WHERE od.Order_ID = :id
    ");
    $stmt_items->bindParam(':id', $order_id, PDO::PARAM_INT);
    $stmt_items->execute();
    $order_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $message = "Database Error: Could not load order details. " . $e->getMessage();
    error_log("View Order Error: " . $e->getMessage());
}

end_script:
// Standard available statuses
$available_statuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Order #<?= $order_id ?> - Jewellery Admin</title>

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
            background-color: #FBF9F6; 
            color: #4D4C48; 
        }
        .admin-sidebar {
            background-color: #4D4C48; 
        }
        .nav-link:hover {
            background-color: #8B7B61; 
            color: white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        .logout-btn {
            background-color: #DCCEB8; 
            color: #4D4C48; 
            font-weight: 600;
            border: 2px solid transparent;
            transition: all 0.2s;
        }
        .logout-btn:hover {
            background-color: #8B7B61; 
            color: white;
            border-color: #8B7B61;
        }
        .status-pill {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: capitalize;
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
            <li><a href="admin_dashboard.php" class="block p-3 rounded text-white nav-link hover:bg-brand-teal transition-all">Dashboard</a></li>
            <li><a href="manage_users.php" class="block p-3 rounded text-white nav-link hover:bg-brand-teal transition-all">Manage Users</a></li>
            <li><a href="manage_products.php" class="block p-3 rounded text-white nav-link hover:bg-brand-teal transition-all">Manage Products</a></li>
            <li><a href="manage_orders.php" class="block p-3 rounded text-white nav-link hover:bg-brand-teal transition-all nav-active">Manage Orders</a></li>
        </ul>
        
        <a href="logout.php" class="mt-auto block w-full text-center p-3 rounded-full logout-btn shadow-lg">
            <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
            Sign Out
        </a>
    </div>

    <div class="flex-1 ml-64 p-10">
        <div class="flex justify-between items-center mb-6 border-b border-gray-200 pb-4">
            <h2 class="font-serif text-4xl font-bold text-brand-dark">Order #<?= $order_id ?> Details</h2>
            <a href="manage_orders.php" class="font-semibold text-brand-subtext hover:text-brand-dark transition-colors flex items-center">
                &larr; Back to Order List
            </a>
        </div>

        <?php if ($message): ?>
        <div class="p-4 mb-4 rounded-xl bg-green-100 text-green-800 border border-green-300">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <?php if ($order_summary): ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-lg border-t-4 border-brand-teal">
                <h3 class="font-serif text-2xl font-bold text-brand-dark mb-4">Order Summary</h3>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <p class="text-brand-subtext">Order Date:</p>
                    <p class="font-medium text-brand-dark"><?= htmlspecialchars($order_summary['OrderDate']) ?></p>
                    
                    <p class="text-brand-subtext">Total Items:</p>
                    <p class="font-medium text-brand-dark"><?= htmlspecialchars($order_summary['TotalItems']) ?></p>
                    
                    <p class="text-brand-subtext">Order Total:</p>
                    <p class="font-bold text-xl text-brand-teal">₱<?= number_format($order_summary['Total_Amount'], 2) ?></p>

                    <p class="text-brand-subtext">Customer:</p>
                    <p class="font-medium text-brand-dark"><?= htmlspecialchars($order_summary['CustomerName']) ?> (<?= htmlspecialchars($order_summary['CustomerEmail']) ?>)</p>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-xl shadow-lg border-t-4 border-brand-teal">
                <h3 class="font-serif text-2xl font-bold text-brand-dark mb-4">Update Status</h3>
                
                <p class="text-sm font-semibold text-brand-subtext mb-2">Current Status:</p>
                <?php
                    $current_status = htmlspecialchars($order_summary['OrderStatus']);
                    $status_class = match ($current_status) {
                        'Pending' => 'bg-yellow-100 text-yellow-800',
                        'Processing' => 'bg-blue-100 text-blue-800',
                        'Shipped' => 'bg-indigo-100 text-indigo-800',
                        'Delivered' => 'bg-green-100 text-green-800',
                        default => 'bg-gray-100 text-gray-800',
                    };
                ?>
                <p class="status-pill <?= $status_class ?> mb-6"><?= $current_status ?></p>

                <form method="POST">
                    <label for="new_status" class="block text-sm font-medium text-brand-dark mb-2">Change To:</label>
                    <select id="new_status" name="new_status" required class="w-full px-3 py-2 border border-gray-300 rounded-lg mb-4 focus:ring-brand-teal focus:border-brand-teal">
                        <?php foreach ($available_statuses as $status_option): ?>
                            <option value="<?= $status_option ?>" <?= ($current_status === $status_option) ? 'selected' : '' ?>>
                                <?= $status_option ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="w-full bg-brand-teal text-white py-2.5 rounded-full font-semibold hover:bg-opacity-90 transition-colors">
                        Save Status Change
                    </button>
                </form>
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-lg">
            <h3 class="font-serif text-2xl font-bold text-brand-dark mb-4 border-b pb-2">Items Ordered (<?= count($order_items) ?> unique items)</h3>
            
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-brand-subtext uppercase tracking-wider">Product</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-brand-subtext uppercase tracking-wider">Unit Price</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-brand-subtext uppercase tracking-wider">Quantity</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-brand-subtext uppercase tracking-wider">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    <?php 
                    $order_subtotal = 0;
                    foreach ($order_items as $item): 
                        $item_subtotal = $item['Price'] * $item['Quantity'];
                        $order_subtotal += $item_subtotal;
                    ?>
                    <tr>
                        <td class="px-6 py-4">
                            <div class="flex items-center">
                                <img src="<?= htmlspecialchars($item['Images'] ?? 'https://placehold.co/40x40') ?>" alt="<?= htmlspecialchars($item['ProductName']) ?>" class="w-10 h-10 rounded-lg object-cover border mr-3">
                                <span class="font-medium text-brand-dark"><?= htmlspecialchars($item['ProductName']) ?></span>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-brand-subtext">₱<?= number_format($item['Price'], 2) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-brand-dark"><?= htmlspecialchars($item['Quantity']) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-semibold text-brand-dark">₱<?= number_format($item_subtotal, 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <tr>
                        <td colspan="3" class="px-6 py-4 text-right text-lg font-bold text-brand-dark">Order Total:</td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-xl font-extrabold text-brand-teal">₱<?= number_format($order_summary['Total_Amount'], 2) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <?php else: ?>
        <div class="p-6 text-center text-brand-subtext text-lg bg-white rounded-xl shadow-lg">
            Order details could not be loaded. Please check the Order ID or contact support.
        </div>
        <?php endif; ?>
    </div>
</body>
</html>