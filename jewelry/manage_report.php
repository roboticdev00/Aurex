<?php
session_start();
require_once 'db_connect.php';
require_once 'middleware.php';

// --- RBAC CHECK ---
if (!isset($_SESSION['Role']) || $_SESSION['Role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// --- USER DETAILS ---
$userName = "Admin";
if (isset($_SESSION['User_ID'])) {
    $stmt = $pdo->prepare("SELECT Name FROM user WHERE User_ID = ?");
    $stmt->execute([$_SESSION['User_ID']]);
    $user = $stmt->fetch();
    if ($user) $userName = htmlspecialchars($user['Name']);
}

// --- FILTER LOGIC ---
// Changed default dates to current date (Today)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : 'All';

// Allowed statuses for dropdown
$allowed_statuses = ['All', 'Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];

if (!in_array($status_filter, $allowed_statuses)) {
    $status_filter = 'All';
}

if (strtotime($start_date) > strtotime($end_date)) {
    $temp = $start_date;
    $start_date = $end_date;
    $end_date = $temp;
}

// --- PREPARE SQL CONDITIONS ---

// 1. For Revenue/Stats (OVERALL): Include Pending, Processing, Shipped, Delivered ONLY
$revenueStatusCondition = "Status IN ('Pending', 'Processing', 'Shipped', 'Delivered')";

// 2. For Display Data (Table, Top Products, Current View): Use Dropdown selection
if ($status_filter === 'All') {
    // If 'All' is selected, we default to the same as Summary Stats (Active Orders)
    $displayStatusCondition = $revenueStatusCondition;
} else {
    $displayStatusCondition = "Status = :status_filter";
}

// --- DATA FETCHING ---

// 1. Stats for "Summary Stats" (Always Pending to Delivered only - OVERALL)
$stats = [];
try {
    $sql = "SELECT 
                COUNT(*) as total_orders,
                COALESCE(SUM(Total_Amount), 0) as total_revenue
            FROM orders 
            WHERE DATE(Order_Date) BETWEEN :start_date AND :end_date 
            AND " . $revenueStatusCondition;
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Report Stats Error: " . $e->getMessage());
}

// 1.5 Specific Pending Count for Summary Stats (OVERALL)
$pendingStats = [];
try {
    $sql = "SELECT COUNT(*) as pending_count
            FROM orders 
            WHERE DATE(Order_Date) BETWEEN :start_date AND :end_date 
            AND Status = 'Pending'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
    $pendingStats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pendingStats['pending_count'] = 0;
}

// 3. Top Products (Filtered by Dropdown Selection) - Now fetching Images
$topProducts = [];
try {
    $sql = "SELECT 
                p.Name,
                p.Product_ID,
                p.Images,
                SUM(oi.Quantity) as total_sold,
                SUM(oi.Quantity * oi.Price) as total_product_revenue
            FROM order_item oi
            JOIN orders o ON oi.Order_ID = o.Order_ID
            JOIN product p ON oi.Product_ID = p.Product_ID
            WHERE DATE(o.Order_Date) BETWEEN :start_date AND :end_date
            AND " . $displayStatusCondition . "
            GROUP BY p.Product_ID, p.Name, p.Images
            ORDER BY total_sold DESC
            LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $params = ['start_date' => $start_date, 'end_date' => $end_date];
    if ($status_filter !== 'All') {
        $params['status_filter'] = $status_filter;
    }
    $stmt->execute($params);
    $topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Top Products Error: " . $e->getMessage());
}

// 4. Sales Over Time Chart (Filtered by Dropdown Selection)
$salesOverTime = [];
try {
    $sql = "SELECT 
                DATE(Order_Date) as order_date,
                SUM(Total_Amount) as daily_revenue,
                COUNT(*) as daily_orders
            FROM orders 
            WHERE DATE(Order_Date) BETWEEN :start_date AND :end_date
            AND " . $displayStatusCondition . "
            GROUP BY DATE(Order_Date)
            ORDER BY order_date ASC";
    $stmt = $pdo->prepare($sql);
    $params = ['start_date' => $start_date, 'end_date' => $end_date];
    if ($status_filter !== 'All') {
        $params['status_filter'] = $status_filter;
    }
    $stmt->execute($params);
    $salesOverTime = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Sales Timeline Error: " . $e->getMessage());
}

// 5. Recent Orders Table (Filtered by Dropdown Selection - For Screen View)
$recentOrders = [];
try {
    $sql = "SELECT 
                o.Order_ID,
                o.Order_Date,
                u.Name as Customer_Name,
                o.Total_Amount,
                o.Status,
                (SELECT COUNT(*) FROM order_item WHERE Order_ID = o.Order_ID) as Item_Count
            FROM orders o
            JOIN user u ON o.User_ID = u.User_ID
            WHERE DATE(o.Order_Date) BETWEEN :start_date AND :end_date
            AND " . $displayStatusCondition . "
            ORDER BY o.Order_Date DESC
            LIMIT 50";
    $stmt = $pdo->prepare($sql);
    $params = ['start_date' => $start_date, 'end_date' => $end_date];
    if ($status_filter !== 'All') {
        $params['status_filter'] = $status_filter;
    }
    $stmt->execute($params);
    $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Recent Orders Error: " . $e->getMessage());
}

// 6. Data for "Current View" (Calculates stats based on selected dropdown filter)
$currentViewStats = [];
try {
    $sql = "SELECT 
                COUNT(*) as total_orders,
                COALESCE(SUM(Total_Amount), 0) as total_revenue
            FROM orders 
            WHERE DATE(Order_Date) BETWEEN :start_date AND :end_date 
            AND " . $displayStatusCondition;
    $stmt = $pdo->prepare($sql);
    $params = ['start_date' => $start_date, 'end_date' => $end_date];
    if ($status_filter !== 'All') {
        $params['status_filter'] = $status_filter;
    }
    $stmt->execute($params);
    $currentViewStats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Current View Stats Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Aurex Jewelry Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
    }

    .table-header {
        background-color: #8B7B61;
        color: white;
        font-weight: 600;
    }

    .logout-btn {
        background-color: #DCCEB8;
        color: #4D4C48;
        font-weight: 600;
        transition: all 0.2s;
    }

    .logout-btn:hover {
        background-color: #8B7B61;
        color: white;
    }

    .chart-container {
        position: relative;
        height: 350px;
        width: 100%;
    }

    /* --- PRINT STYLES --- */
    @media print {
        @page {
            size: landscape;
            margin: 0.5cm;
        }

        body {
            background-color: white !important;
            color: black !important;
            margin: 0;
            padding: 0;
            font-size: 11pt;
            print-color-adjust: exact;
        }

        /* Hide UI Elements */
        .no-print,
        .admin-sidebar,
        #printModal,
        .logout-btn,
        .chart-container {
            display: none !important;
        }

        /* Reset Layout */
        .flex-1 {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            margin-left: 0 !important;
        }

        /* Print Header */
        .print-only-header {
            display: block !important;
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid black;
            padding-bottom: 10px;
        }

        /* Overall Summary Section in Print */
        .print-overall-section {
            display: block !important;
            margin-bottom: 20px;
            border: 1px solid #000;
            padding: 10px;
            page-break-inside: avoid;
        }

        .print-overall-title {
            font-weight: bold;
            font-size: 12pt;
            text-transform: uppercase;
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 15px;
            display: block !important;
        }

        .print-stats-row {
            display: flex !important;
            justify-content: space-around;
            text-align: center;
        }

        .print-stat-item {
            flex: 1;
        }

        /* Unified Stats Style */
        .print-stat-label-box {
            border: 1px solid #ccc;
            background-color: #fafafa;
            padding: 10px;
            min-height: 100px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .print-stat-label {
            font-weight: bold;
            font-size: 9pt;
            display: block;
            margin-bottom: 8px;
            color: #555;
            text-transform: uppercase;
            border-bottom: 1px solid #999;
            padding-bottom: 4px;
            width: 100%;
            text-align: center;
        }

        .print-stat-value {
            font-size: 16pt;
            font-weight: bold;
        }

        .print-stat-sub {
            font-size: 8pt;
            font-style: italic;
            color: #777;
            margin-top: 4px;
        }

        /* Hide Charts in print because they are not tabular */
        .chart-container,
        canvas {
            display: none !important;
        }

        /* --- PRINT TABLE STYLES --- */
        #screen-table-section {
            display: block !important;
            border: 1px solid #000 !important;
            box-shadow: none !important;
            margin-top: 20px;
            page-break-inside: auto;
        }

        table {
            width: 100% !important;
            border-collapse: collapse !important;
            margin-bottom: 0;
        }

        th {
            background-color: #eee !important;
            /* Override teal to gray for B&W printing */
            color: black !important;
            border-bottom: 2px solid #000 !important;
            padding: 8px !important;
            font-size: 10pt;
            text-transform: uppercase;
        }

        td {
            border-bottom: 1px solid #ccc !important;
            padding: 8px !important;
            color: black !important;
        }

        /* Force badges to have borders for print */
        span[class*="rounded-full"] {
            border: 1px solid #999 !important;
            color: black !important;
            background-color: transparent !important;
            /* Remove color backgrounds for clean B&W print */
        }
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
            <li><a href="admin_dashboard.php"
                    class="block p-3 rounded text-white hover:bg-brand-teal hover:shadow-md transition-all">
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
            <li><a href="manage_report.php" class="block p-3 rounded text-white nav-active transition-all">
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

    <!-- Main Content -->
    <div class="flex-1 ml-64 p-10">

        <!-- PRINT ONLY HEADER -->
        <div class="print-only-header hidden">
            <h1 class="text-3xl font-bold uppercase tracking-wide">Aurex Jewelry Management System</h1>
            <p class="text-lg mt-2 font-semibold">Sales Report</p>
            <p class="text-sm">Date Range: <?= $start_date ?> to <?= $end_date ?></p>

            <!-- BLOCK: OVERALL SUMMARY (Pending to Delivered) -->
            <div class="print-overall-section">
                <span class="print-overall-title">Total Performance Summary</span>
                <div class="print-stats-row">
                    <div class="print-stat-item">
                        <div class="print-stat-label-box">
                            <span class="print-stat-label">Total Revenue</span>
                            <span class="print-stat-value">₱<?= number_format($stats['total_revenue'], 2) ?></span>
                            <span class="print-stat-sub">Net Sales (Pending to Delivered)</span>
                        </div>
                    </div>
                    <div class="print-stat-item">
                        <div class="print-stat-label-box">
                            <span class="print-stat-label">Total Orders</span>
                            <span class="print-stat-value"><?= $stats['total_orders'] ?></span>
                            <span class="print-stat-sub">Pending, Proc, Ship, Deliv</span>
                        </div>
                    </div>
                    <div class="print-stat-item">
                        <div class="print-stat-label-box">
                            <span class="print-stat-label">Pending Orders</span>
                            <span class="print-stat-value"><?= $pendingStats['pending_count'] ?></span>
                            <span class="print-stat-sub">Currently Pending</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Header (Screen) -->
        <div class="flex justify-between items-center mb-8 no-print">
            <div>
                <h2 class="font-serif text-4xl font-bold text-brand-dark">Sales Report</h2>
            </div>
            <a href="admin_dashboard.php"
                class="text-brand-teal font-semibold hover:text-brand-dark transition-colors">← Back</a>
        </div>

        <!-- Stats Cards (Summary) -->
        <div id="screen-summary-cards"
            class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 print-section-summary no-print">
            <div class="bg-white p-6 rounded-xl shadow-lg border border-gray-200">
                <p class="text-sm font-semibold text-brand-subtext uppercase">Total Revenue</p>
                <p class="font-serif text-3xl font-extrabold text-brand-teal mt-2">
                    ₱<?= number_format($stats['total_revenue'], 2) ?></p>
                <p class="text-xs text-gray-400 mt-2">Net Sales (Pending to Delivered)</p>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-lg border border-gray-200">
                <p class="text-sm font-semibold text-brand-subtext uppercase">Total Orders</p>
                <p class="font-serif text-3xl font-extrabold text-blue-600 mt-2"><?= $stats['total_orders'] ?></p>
                <div class="text-xs text-gray-500 mt-2">Pending, Proc, Ship, Deliv</div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-lg border border-gray-200">
                <p class="text-sm font-semibold text-brand-subtext uppercase">Pending Orders</p>
                <p class="font-serif text-3xl font-extrabold text-yellow-600 mt-2"><?= $pendingStats['pending_count'] ?>
                </p>
                <div class="text-xs text-gray-500 mt-2">Currently Pending</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white p-6 rounded-xl shadow-lg mb-8 no-print border border-gray-200">
            <form method="GET" action="manage_report.php" class="flex flex-col md:flex-row items-end gap-4">
                <div class="flex-1">
                    <label class="block text-sm font-semibold text-brand-dark mb-2">Start Date</label>
                    <input type="date" name="start_date" value="<?= $start_date ?>"
                        class="w-full p-2 border border-gray-300 rounded-lg">
                </div>
                <div class="flex-1">
                    <label class="block text-sm font-semibold text-brand-dark mb-2">End Date</label>
                    <input type="date" name="end_date" value="<?= $end_date ?>"
                        class="w-full p-2 border border-gray-300 rounded-lg">
                </div>
                <div class="flex-1">
                    <label class="block text-sm font-semibold text-brand-dark mb-2">Order Status</label>
                    <select name="status_filter" class="w-full p-2 border border-gray-300 rounded-lg">
                        <option value="All" <?= $status_filter == 'All' ? 'selected' : '' ?>>All Orders</option>
                        <option value="Pending" <?= $status_filter == 'Pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="Processing" <?= $status_filter == 'Processing' ? 'selected' : '' ?>>Processing
                        </option>
                        <option value="Shipped" <?= $status_filter == 'Shipped' ? 'selected' : '' ?>>Shipped</option>
                        <option value="Delivered" <?= $status_filter == 'Delivered' ? 'selected' : '' ?>>Delivered
                        </option>
                        <option value="Cancelled" <?= $status_filter == 'Cancelled' ? 'selected' : '' ?>>Cancelled
                        </option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit"
                        class="px-6 py-2 bg-brand-teal text-white rounded-lg font-semibold shadow-md">Apply
                        Filter</button>

                    <!-- PRINT BUTTON -->
                    <button type="button" onclick="window.print()"
                        class="px-6 py-2 bg-gray-800 text-white border border-black rounded-lg font-semibold shadow-md hover:bg-black transition-colors">
                        Print Report
                    </button>
                </div>
            </form>
        </div>

        <!-- Charts & Top Products -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8 print-section-summary no-print">
            <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-lg">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-serif text-xl font-bold text-brand-dark">Revenue Trend</h3>
                    <span class="text-xs font-semibold px-3 py-1 bg-gray-100 text-gray-600 rounded-full">
                        Showing: <?= $status_filter == 'All' ? 'All Orders' : htmlspecialchars($status_filter) ?>
                    </span>
                </div>
                <div class="chart-container">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-lg">
                <h3 class="font-serif text-xl font-bold text-brand-dark mb-4">Top Products</h3>
                <div class="space-y-4">
                    <?php if (empty($topProducts)): ?>
                    <p class="text-brand-subtext text-sm">No sales data for selected filters.</p>
                    <?php else: ?>
                    <?php foreach ($topProducts as $index => $prod): ?>
                    <div class="flex items-center gap-4 p-3 bg-gray-50 rounded-lg border border-gray-100">
                        <!-- Product Image -->
                        <div class="w-12 h-12 flex-shrink-0">
                            <img src="<?= !empty($prod['Images']) ? htmlspecialchars($prod['Images']) : 'https://placehold.co/100x100?text=No+Image' ?>"
                                alt="<?= htmlspecialchars($prod['Name']) ?>"
                                class="w-full h-full object-cover rounded-md border border-gray-200">
                        </div>
                        <!-- Product Info -->
                        <div class="flex-grow min-w-0">
                            <p class="text-sm font-semibold truncate text-brand-dark">
                                <?= htmlspecialchars($prod['Name']) ?></p>
                            <p class="text-xs text-gray-500">Sold: <?= $prod['total_sold'] ?></p>
                        </div>
                        <!-- Revenue -->
                        <div class="text-right">
                            <p class="text-sm font-bold text-brand-teal">
                                ₱<?= number_format($prod['total_product_revenue'], 2) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Detailed Orders Table -->
        <!-- REMOVED 'no-print' class from below div so it appears in print -->
        <div id="screen-table-section"
            class="bg-white rounded-xl shadow-lg overflow-hidden print-section-table border border-gray-200">
            <div class="p-6 border-b border-gray-200 no-print">
                <h3 class="text-xl font-semibold text-brand-dark">
                    Order Breakdown (<?= $status_filter == 'All' ? 'All Orders' : htmlspecialchars($status_filter) ?>)
                </h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="table-header">
                        <tr>
                            <th class="px-6 py-3 uppercase">Order ID</th>
                            <th class="px-6 py-3 uppercase">Date</th>
                            <th class="px-6 py-3 uppercase">Customer</th>
                            <th class="px-6 py-3 uppercase">Total</th>
                            <th class="px-6 py-3 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (empty($recentOrders)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-center text-brand-subtext">No orders found for
                                selected filters.</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($recentOrders as $order): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 font-semibold">#<?= $order['Order_ID'] ?></td>
                            <td class="px-6 py-4"><?= date('M d, Y', strtotime($order['Order_Date'])) ?></td>
                            <td class="px-6 py-4"><?= htmlspecialchars($order['Customer_Name']) ?></td>
                            <td class="px-6 py-4 font-semibold text-brand-teal">
                                ₱<?= number_format($order['Total_Amount'], 2) ?></td>
                            <td class="px-6 py-4">
                                <?php
                                        $statusColor = 'bg-gray-100 text-gray-800';
                                        if ($order['Status'] == 'Pending') $statusColor = 'bg-yellow-100 text-yellow-800';
                                        if ($order['Status'] == 'Processing') $statusColor = 'bg-blue-100 text-blue-800';
                                        if ($order['Status'] == 'Shipped') $statusColor = 'bg-purple-100 text-purple-800';
                                        if ($order['Status'] == 'Delivered') $statusColor = 'bg-green-100 text-green-800';
                                        if ($order['Status'] == 'Cancelled') $statusColor = 'bg-red-100 text-red-800';
                                        ?>
                                <span
                                    class="px-2 py-1 text-xs font-semibold rounded-full <?= $statusColor ?>"><?= htmlspecialchars($order['Status']) ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    // --- CHART.JS ---
    document.addEventListener('DOMContentLoaded', () => {
        const ctx = document.getElementById('salesChart').getContext('2d');

        // Data from PHP
        const rawLabels = <?= json_encode(array_column($salesOverTime, 'order_date')) ?>;
        const revenueData = <?= json_encode(array_column($salesOverTime, 'daily_revenue')) ?>;
        const orderData = <?= json_encode(array_column($salesOverTime, 'daily_orders')) ?>;

        // Function to format date to "Dec, 25 2025"
        const formatDate = (dateString) => {
            const date = new Date(dateString);
            // Options: Short Month (Dec), Day (25), Full Year (2025)
            return date.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            }).replace(',', ''); // Removes the comma usually present in default locale
        };

        // Apply formatting to labels
        const formattedLabels = rawLabels.map(formatDate);

        const gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(139, 123, 97, 0.5)');
        gradient.addColorStop(1, 'rgba(139, 123, 97, 0.0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: formattedLabels,
                datasets: [{
                    label: 'Daily Revenue (₱)',
                    data: revenueData,
                    borderColor: '#8B7B61',
                    backgroundColor: gradient,
                    borderWidth: 2,
                    pointBackgroundColor: '#8B7B61',
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y'
                }, {
                    label: 'Orders Count',
                    data: orderData,
                    type: 'bar',
                    backgroundColor: '#DCCEB8',
                    barThickness: 20,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        type: 'linear',
                        position: 'left',
                        grid: {
                            borderDash: [5, 5]
                        }
                    },
                    y1: {
                        type: 'linear',
                        position: 'right',
                        grid: {
                            display: false
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    });
    </script>
</body>

</html>