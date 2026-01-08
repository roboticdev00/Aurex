<?php
// admin_dashboard.php
session_start();
require_once 'middleware.php';
require_once 'db_connect.php';

// --- CRITICAL RBAC CHECK ---
requireAdmin();

$user_id = $_SESSION['User_ID'];
$sql = "SELECT Name FROM user WHERE User_ID = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$userName = htmlspecialchars($user['Name']);

// Fetch comprehensive dashboard metrics
try {
    // User metrics
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM user WHERE Role != 'admin'")->fetchColumn();
    $activeUsers = $pdo->query("SELECT COUNT(*) FROM user WHERE Role != 'admin' AND Is_Logged_In = 1")->fetchColumn();

    // Product metrics
    $totalProducts = $pdo->query("SELECT COUNT(*) FROM product")->fetchColumn();
    $activeProducts = $pdo->query("SELECT COUNT(*) FROM product WHERE CAST(Stock AS UNSIGNED) > 0")->fetchColumn();
    $lowStockProducts = $pdo->query("SELECT COUNT(*) FROM product WHERE CAST(Stock AS UNSIGNED) <= 5 AND CAST(Stock AS UNSIGNED) > 0")->fetchColumn();

    // Order metrics
    $totalOrders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $pendingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE Status = 'Pending'")->fetchColumn();
    $processingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE Status = 'Processing'")->fetchColumn();
    $shippedOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE Status = 'Shipped'")->fetchColumn();
    $deliveredOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE Status = 'Delivered'")->fetchColumn();
    $cancelledOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE Status = 'Cancelled'")->fetchColumn();

    // Revenue metrics
    $totalRevenue = $pdo->query("SELECT COALESCE(SUM(Total_Amount), 0) FROM orders WHERE Status != 'Cancelled'")->fetchColumn();
    $monthlyRevenue = $pdo->query("SELECT COALESCE(SUM(Total_Amount), 0) FROM orders WHERE Status != 'Cancelled' AND MONTH(Order_Date) = MONTH(CURRENT_DATE()) AND YEAR(Order_Date) = YEAR(CURRENT_DATE())")->fetchColumn();

    // Recent activity
    $recentOrders = $pdo->query("SELECT o.Order_ID, o.Order_Date, o.Total_Amount, o.Status, u.Name as Customer_Name FROM orders o JOIN user u ON o.User_ID = u.User_ID ORDER BY o.Order_Date DESC LIMIT 5")->fetchAll();
    $recentUsers = $pdo->query("SELECT Name, Email FROM user WHERE Role != 'admin' ORDER BY User_ID DESC LIMIT 5")->fetchAll();

    // Top products (Updated to fetch Images)
    $topProducts = $pdo->query("
        SELECT p.Name, p.Images, SUM(oi.Quantity) as Total_Sold, SUM(oi.Quantity * oi.Price) as Revenue
        FROM order_item oi
        JOIN product p ON oi.Product_ID = p.Product_ID
        JOIN orders o ON oi.Order_ID = o.Order_ID
        WHERE o.Status != 'Cancelled'
        GROUP BY p.Product_ID, p.Name, p.Images
        ORDER BY Total_Sold DESC
        LIMIT 5
    ")->fetchAll();
} catch (PDOException $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    // Set defaults
    $totalUsers = $activeUsers = $totalProducts = $activeProducts = $lowStockProducts = 0;
    $totalOrders = $pendingOrders = $processingOrders = $shippedOrders = $deliveredOrders = $cancelledOrders = 0;
    $totalRevenue = $monthlyRevenue = 0;
    $recentOrders = $recentUsers = $topProducts = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Jewellery</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@700;800&display=swap"
        rel="stylesheet">

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

        .nav-active {
            background-color: #8B7B61;
            color: white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        /* Improved Card Design with Brown Border on Top and Left */
        .stat-card {
            background-color: white;
            border-top: 5px solid #8B7B61;
            /* Brown Top */
            border-left: 5px solid #8B7B61;
            /* Brown Left */
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        /* Custom Logout Button Style */
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
    </style>
</head>

<body class="flex antialiased">

    <div class="w-64 admin-sidebar h-screen p-6 fixed flex flex-col shadow-2xl z-20">
        <h1 class="font-serif text-white text-3xl font-bold mb-6 border-b border-gray-600 pb-4">Aurex</h1>

        <div class="text-white mb-4 border-b border-teal-light/50 pb-4">
            <p class="font-semibold text-sm text-teal-light uppercase tracking-wider">Store Administrator</p>
            <p class="text-lg font-bold text-white mt-1"><?= $userName ?></p>
        </div>

        <ul class="space-y-2 flex-grow">
            <li><a href="admin_dashboard.php" class="block p-3 rounded text-white nav-active transition-all">
                    <span class="font-medium">Dashboard</span>
                </a></li>
            <li><a href="manage_users.php"
                    class="block p-3 rounded text-white hover:bg-brand-teal hover:shadow-md transition-all">
                    <span class="font-medium">Manage Users</span>
                </a></li>
            <li><a href="manage_products.php"
                    class="block p-3 rounded text-white hover:bg-brand-teal hover:shadow-md transition-all">
                    <span class="font-medium">Manage Products</span>
                </a></li>
            <li><a href="manage_orders.php"
                    class="block p-3 rounded text-white hover:bg-brand-teal hover:shadow-md transition-all">
                    <span class="font-medium">Manage Orders</span>
                </a></li>
            <li><a href="manage_ratings.php"
                    class="block p-3 rounded text-white hover:bg-brand-teal hover:shadow-md transition-all">
                    <span class="font-medium">Manage Ratings</span>
                </a></li>
            <li><a href="manage_report.php"
                    class="block p-3 rounded text-white hover:bg-brand-teal hover:shadow-md transition-all">
                    <span class="font-medium">Sales Report</span>
                </a></li>
        </ul>

        <a href="logout.php" class="mt-auto block w-full text-center p-3 rounded-full logout-btn shadow-lg">
            <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1">
                </path>
            </svg>
            Sign Out
        </a>
    </div>

    <div class="flex-1 ml-64 p-10">
        <div class="flex justify-between items-center mb-10">
            <h2 class="font-serif text-4xl font-bold text-brand-dark">Welcome to Admin Panel, <?= $userName ?>!</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">

            <!-- Users Section -->
            <div class="stat-card p-6 relative overflow-hidden group">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm font-semibold text-brand-subtext uppercase tracking-wide">Total Customers</p>
                        <p class="font-serif text-3xl font-extrabold text-brand-teal mt-1">
                            <?= number_format($totalUsers) ?></p>
                    </div>
                    <div
                        class="p-3 bg-brand-beige rounded-lg text-brand-teal group-hover:bg-brand-teal group-hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 11a4.5 4.5 0 100-9 4.5 4.5 0 000 9zm7 9H5a7 7 0 0114 0v1H5v-1z">
                            </path>

                        </svg>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-100 flex items-center text-xs text-brand-subtext">
                    <svg class="w-4 h-4 text-green-500 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                    <span class="text-green-600 font-medium"><?= $activeUsers ?> active</span> currently logged in
                </div>
            </div>

            <!-- Products Section -->
            <div class="stat-card p-6 relative overflow-hidden group">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm font-semibold text-brand-subtext uppercase tracking-wide">Total Products</p>
                        <p class="font-serif text-3xl font-extrabold text-brand-teal mt-1">
                            <?= number_format($totalProducts) ?></p>
                    </div>
                    <div
                        class="p-3 bg-brand-beige rounded-lg text-brand-teal group-hover:bg-brand-teal group-hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-100 flex justify-between text-xs text-brand-subtext">
                    <span><span class="text-green-600 font-medium"><?= $activeProducts ?></span> in stock</span>
                    <span><span class="text-red-500 font-medium"><?= $lowStockProducts ?></span> low stock</span>
                </div>
            </div>

            <!-- Orders Section -->
            <div class="stat-card p-6 relative overflow-hidden group">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm font-semibold text-brand-subtext uppercase tracking-wide">Total Orders</p>
                        <p class="font-serif text-3xl font-extrabold text-brand-teal mt-1">
                            <?= number_format($totalOrders) ?></p>
                    </div>
                    <div
                        class="p-3 bg-brand-beige rounded-lg text-brand-teal group-hover:bg-brand-teal group-hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                            </path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-100 flex justify-between text-xs text-brand-subtext">
                    <span><span class="text-orange-600 font-medium"><?= $pendingOrders ?></span> pending</span>
                    <span><span class="text-blue-600 font-medium"><?= $processingOrders ?></span> processing</span>
                </div>
            </div>

            <!-- Revenue Section -->
            <div class="stat-card p-6 relative overflow-hidden group">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm font-semibold text-brand-subtext uppercase tracking-wide">Total Revenue</p>
                        <p class="font-serif text-2xl font-extrabold text-brand-teal mt-2 leading-none">
                            ₱<?= number_format($totalRevenue, 2) ?></p>
                    </div>
                    <div
                        class="p-3 bg-brand-beige rounded-lg text-brand-teal group-hover:bg-brand-teal group-hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                            </path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-100 flex items-center text-xs text-brand-subtext">
                    <span class="text-green-600 font-medium">₱<?= number_format($monthlyRevenue, 2) ?></span>
                    <span class="ml-1">this month</span>
                </div>
            </div>
        </div>

        <!-- Order Status Overview -->
        <div class="bg-white p-6 rounded-xl shadow-lg mb-8">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-serif text-xl font-bold text-brand-dark">Order Status Overview</h3>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div
                    class="text-center p-4 bg-orange-50 rounded-lg border border-orange-100 hover:shadow-md transition-shadow">
                    <p class="text-2xl font-bold text-orange-600"><?= $pendingOrders ?></p>
                    <p class="text-sm text-orange-800 font-medium">Pending</p>
                </div>
                <div
                    class="text-center p-4 bg-blue-50 rounded-lg border border-blue-100 hover:shadow-md transition-shadow">
                    <p class="text-2xl font-bold text-blue-600"><?= $processingOrders ?></p>
                    <p class="text-sm text-blue-800 font-medium">Processing</p>
                </div>
                <div
                    class="text-center p-4 bg-purple-50 rounded-lg border border-purple-100 hover:shadow-md transition-shadow">
                    <p class="text-2xl font-bold text-purple-600"><?= $shippedOrders ?></p>
                    <p class="text-sm text-purple-800 font-medium">Shipped</p>
                </div>
                <div
                    class="text-center p-4 bg-green-50 rounded-lg border border-green-100 hover:shadow-md transition-shadow">
                    <p class="text-2xl font-bold text-green-600"><?= $deliveredOrders ?></p>
                    <p class="text-sm text-green-800 font-medium">Delivered</p>
                </div>
                <div
                    class="text-center p-4 bg-red-50 rounded-lg border border-red-100 hover:shadow-md transition-shadow">
                    <p class="text-2xl font-bold text-red-600"><?= $cancelledOrders ?></p>
                    <p class="text-sm text-red-800 font-medium">Cancelled</p>
                </div>
            </div>
        </div>

        <!-- Recent Activity and Top Products -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">

            <!-- Recent Orders -->
            <div class="bg-white p-6 rounded-xl shadow-lg">
                <h3 class="font-serif text-xl font-bold text-brand-dark mb-4 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-brand-teal" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                        </path>
                    </svg>
                    Recent Orders
                </h3>
                <div class="space-y-3">
                    <?php if (empty($recentOrders)): ?>
                        <p class="text-brand-subtext text-sm">No recent orders</p>
                    <?php else: ?>
                        <?php foreach ($recentOrders as $order): ?>
                            <div
                                class="flex justify-between items-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors border border-gray-100">
                                <div>
                                    <p class="font-bold text-brand-dark text-sm">#<?= $order['Order_ID'] ?> -
                                        <?= htmlspecialchars($order['Customer_Name']) ?></p>
                                    <p class="text-xs text-brand-subtext mt-1">
                                        <?= date('M d, Y h:i A', strtotime($order['Order_Date'])) ?></p>
                                </div>
                                <div class="text-right flex flex-col items-end">
                                    <p class="font-bold text-brand-teal text-sm">
                                        ₱<?= number_format($order['Total_Amount'], 2) ?></p>
                                    <span class="px-2 py-0.5 text-xs rounded-full font-semibold mt-1 
                                    <?php
                                    switch ($order['Status']) {
                                        case 'Pending':
                                            echo 'bg-orange-100 text-orange-800';
                                            break;
                                        case 'Processing':
                                            echo 'bg-blue-100 text-blue-800';
                                            break;
                                        case 'Shipped':
                                            echo 'bg-purple-100 text-purple-800';
                                            break;
                                        case 'Delivered':
                                            echo 'bg-green-100 text-green-800';
                                            break;
                                        case 'Cancelled':
                                            echo 'bg-red-100 text-red-800';
                                            break;
                                        default:
                                            echo 'bg-gray-100 text-gray-800';
                                    }
                                    ?>">
                                        <?= $order['Status'] ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Top Selling Products -->
            <div class="bg-white p-6 rounded-xl shadow-lg">
                <h3 class="font-serif text-xl font-bold text-brand-dark mb-4 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-brand-teal" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                    Top Selling Products
                </h3>
                <div class="space-y-3">
                    <?php if (empty($topProducts)): ?>
                        <p class="text-brand-subtext text-sm">No sales data available</p>
                    <?php else: ?>
                        <?php foreach ($topProducts as $index => $product):
                            // Determine image source, use placeholder if empty
                            $product_image = !empty($product['Images']) ? htmlspecialchars($product['Images']) : 'https://placehold.co/50x50/F3F4F6/8B7B61?text=Jewelry';
                        ?>
                            <div
                                class="flex justify-between items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors border border-gray-100">
                                <div class="flex items-center min-w-0">
                                    <span
                                        class="flex-shrink-0 w-6 h-6 bg-brand-teal text-white text-[10px] font-bold rounded-full flex items-center justify-center mr-3">
                                        <?= $index + 1 ?>
                                    </span>
                                    <img src="<?= $product_image ?>" alt="<?= htmlspecialchars($product['Name']) ?>"
                                        class="flex-shrink-0 w-10 h-10 rounded object-cover mr-3 border border-gray-200 shadow-sm">
                                    <div class="min-w-0">
                                        <p class="font-semibold text-brand-dark text-sm truncate">
                                            <?= htmlspecialchars($product['Name']) ?></p>
                                        <p class="text-xs text-brand-subtext">Sold: <span
                                                class="font-medium text-brand-dark"><?= $product['Total_Sold'] ?></span></p>
                                    </div>
                                </div>
                                <div class="flex-shrink-0 text-right pl-2">
                                    <p class="font-bold text-brand-teal text-sm">₱<?= number_format($product['Revenue'], 2) ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <h3 class="font-serif text-2xl font-bold text-brand-dark mb-5 border-b border-gray-200 pb-3">Management Modules
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <a href="manage_users.php"
                class="bg-white p-6 rounded-xl shadow-md hover:shadow-xl hover:bg-brand-beige text-center border border-gray-200 transition-all group">
                <div class="mb-4">
                    <svg class="w-12 h-12 mx-auto text-brand-teal group-hover:scale-110 transition-transform"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 11a4.5 4.5 0 100-9 4.5 4.5 0 000 9zm7 9H5a7 7 0 0114 0v1H5v-1z">
                        </path>
                    </svg>
                </div>
                <h4 class="font-bold text-brand-dark mb-2">Manage Users</h4>
                <p class="text-sm text-brand-subtext">View and manage customer accounts, roles, and permissions</p>
            </a>

            <a href="manage_products.php"
                class="bg-white p-6 rounded-xl shadow-md hover:shadow-xl hover:bg-brand-beige text-center border border-gray-200 transition-all group">
                <div class="mb-4">
                    <svg class="w-12 h-12 mx-auto text-brand-teal group-hover:scale-110 transition-transform"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                </div>
                <h4 class="font-bold text-brand-dark mb-2">Manage Products</h4>
                <p class="text-sm text-brand-subtext">Add, edit, and monitor product inventory and pricing</p>
            </a>

            <a href="manage_orders.php"
                class="bg-white p-6 rounded-xl shadow-md hover:shadow-xl hover:bg-brand-beige text-center border border-gray-200 transition-all group">
                <div class="mb-4">
                    <svg class="w-12 h-12 mx-auto text-brand-teal group-hover:scale-110 transition-transform"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                        </path>
                    </svg>
                </div>
                <h4 class="font-bold text-brand-dark mb-2">Manage Orders</h4>
                <p class="text-sm text-brand-subtext">Process orders, update statuses, and handle customer requests</p>
            </a>

            <a href="manage_ratings.php"
                class="bg-white p-6 rounded-xl shadow-md hover:shadow-xl hover:bg-brand-beige text-center border border-gray-200 transition-all group">
                <div class="mb-4">
                    <svg class="w-12 h-12 mx-auto text-brand-teal group-hover:scale-110 transition-transform"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z">
                        </path>
                    </svg>
                </div>
                <h4 class="font-bold text-brand-dark mb-2">Manage Ratings</h4>
                <p class="text-sm text-brand-subtext">View and moderate customer reviews and ratings</p>
            </a>
        </div>
    </div>
</body>

</html>