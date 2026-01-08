<?php
// manage_orders.php
session_start();
require_once 'middleware.php';
require_once 'db_connect.php';
require_once 'utilities.php';

// --- RBAC CHECK ---
requireAdmin();

$user_id = $_SESSION['User_ID'];

try {
    $stmt = $pdo->prepare("SELECT Name FROM user WHERE User_ID = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_destroy();
        header('Location: login.php');
        exit;
    }

    $userName = htmlspecialchars($user['Name']);
} catch (PDOException $e) {
    error_log("RBAC Check Error: " . $e->getMessage());
    header('Location: login.php');
    exit;
}

// --- FILTER LOGIC ---
$filterStatus = isset($_GET['status']) ? $_GET['status'] : null;
$allowedStatuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];

// Validate filter input
if ($filterStatus && !in_array($filterStatus, $allowedStatuses)) {
    $filterStatus = null; // Reset if invalid value provided
}

// Fetch all orders with user details (Filtered)
$orders = [];
$error_message = '';

try {
    $sql = "
        SELECT 
            o.Order_ID,
            o.User_ID,
            u.Name as Customer_Name,
            u.Email as Customer_Email,
            o.Order_Date,
            o.Total_Amount,
            o.Status,
            o.Shipping_Address,
            o.Phone_Number,
            COUNT(oi.Order_Item_ID) as Item_Count
        FROM orders o
        LEFT JOIN user u ON o.User_ID = u.User_ID
        LEFT JOIN order_item oi ON o.Order_ID = oi.Order_ID
    ";

    // Append WHERE clause if filtering
    if ($filterStatus) {
        $sql .= " WHERE o.Status = :status ";
    }

    $sql .= " GROUP BY o.Order_ID ORDER BY o.Order_Date DESC";

    $stmt = $pdo->prepare($sql);

    if ($filterStatus) {
        $stmt->execute([':status' => $filterStatus]);
    } else {
        $stmt->execute();
    }

    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Orders Fetch Error: " . $e->getMessage());
    $error_message = "Failed to load orders: " . $e->getMessage();
}

// Get status distribution
$statusCounts = [];
try {
    $stmt = $pdo->query("
        SELECT Status, COUNT(*) as count
        FROM orders
        GROUP BY Status
    ");
    $statusCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    error_log("Status Count Error: " . $e->getMessage());
}

// Calculate Total Orders for the "All" card
$allOrdersCount = array_sum($statusCounts);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Orders - Jewellery Admin</title>

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
                    'brand-brown': '#8B7B61',
                    'brand-text': '#4D4C48',
                    'brand-subtext': '#7A7977',

                    // Status Colors for Icons
                    'status-pending': '#F59E0B', // Amber
                    'status-processing': '#3B82F6', // Blue
                    'status-shipped': '#8B5CF6', // Violet
                    'status-delivered': '#10B981', // Emerald
                    'status-cancelled': '#EF4444', // Red
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

    .modal {
        transition: opacity 0.3s ease-in-out;
    }

    .modal-panel {
        transition: all 0.3s ease-in-out;
        transform: translateY(20px) scale(0.95);
        opacity: 0;
    }

    .modal:not(.hidden) .modal-panel {
        transform: translateY(0) scale(1);
        opacity: 1;
    }

    /* --- Premium Stats Card Design (Brown Side + Colored Icon) --- */
    .stats-card {
        background-color: white;
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: block;
        text-decoration: none !important;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        position: relative;
        overflow: hidden;
    }

    .stats-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        text-decoration: none !important;
    }

    /* Active State */
    .stats-card.active {
        background-color: #FBF9F6;
        /* brand-beige */
        /* Brown Border on the Left */
        border-left: 6px solid #8B7B61;
        box-shadow: 0 10px 15px -3px rgba(139, 123, 97, 0.1);
        transform: translateY(-2px);
    }

    /* Icon Styling */
    .stats-icon-wrapper {
        width: 56px;
        height: 56px;
        border-radius: 9999px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }

    .stats-icon-wrapper svg {
        width: 28px;
        height: 28px;
        stroke-width: 1.5;
    }

    /* Default (Inactive) Styles */
    .stats-card .stats-icon-wrapper {
        background-color: #f3f4f6;
        color: #9ca3af;
    }

    .stats-card:hover .stats-icon-wrapper {
        background-color: #f0efe9;
        color: #8B7B61;
    }

    /* Active Icon Logic - Matches the brown left border logic */

    /* All Orders - Icon is Brown */
    .stats-card[data-status="all"].active .stats-icon-wrapper {
        background-color: #8B7B61;
        color: white;
    }

    /* Pending - Icon is Yellow */
    .stats-card[data-status="Pending"].active .stats-icon-wrapper {
        background-color: #F59E0B;
        color: white;
    }

    /* Processing - Icon is Blue */
    .stats-card[data-status="Processing"].active .stats-icon-wrapper {
        background-color: #3B82F6;
        color: white;
    }

    /* Shipped - Icon is Purple */
    .stats-card[data-status="Shipped"].active .stats-icon-wrapper {
        background-color: #8B5CF6;
        color: white;
    }

    /* Delivered - Icon is Green */
    .stats-card[data-status="Delivered"].active .stats-icon-wrapper {
        background-color: #10B981;
        color: white;
    }

    /* Cancelled - Icon is Red */
    .stats-card[data-status="Cancelled"].active .stats-icon-wrapper {
        background-color: #EF4444;
        color: white;
    }

    /* Text Styling */
    .stats-number {
        font-family: 'Playfair Display', serif;
        font-size: 2.25rem;
        line-height: 1;
        transition: color 0.3s ease;
        color: #4D4C48;
    }

    .stats-label {
        font-family: 'Inter', sans-serif;
        letter-spacing: 0.05em;
        font-size: 0.85rem;
        color: #6B7280;
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
            <li><a href="manage_orders.php" class="block p-3 rounded text-white nav-active transition-all">
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

        <a href="logout.php"
            class="mt-auto block w-full text-center p-3 rounded-full logout-btn shadow-lg bg-[#dcceb8] font-semibold">
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
        <div class="flex justify-between items-center mb-6 border-b border-gray-200 pb-4">
            <h2 class="font-serif text-4xl font-bold text-brand-dark">Manage Orders</h2>

        </div>

        <?php if ($error_message): ?>
        <div class="bg-red-100 text-red-800 px-6 py-4 rounded-lg mb-8">
            <?= htmlspecialchars($error_message) ?>
        </div>
        <?php endif; ?>

        <!-- Status Overview Cards (Brown Side + Color Icons) -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-8">

            <!-- All Orders Card -->
            <a href="manage_orders.php" data-status="all"
                class="stats-card p-6 cursor-pointer relative overflow-hidden group <?= $filterStatus === null ? 'active' : '' ?>">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <h3
                            class="stats-label font-semibold uppercase mb-2 group-hover:text-brand-brown transition-colors">
                            All Orders</h3>
                        <div class="stats-number"><?= $allOrdersCount ?></div>
                    </div>
                    <!-- <div class="stats-icon-wrapper shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div> -->
                </div>
            </a>

            <!-- Pending Card -->
            <a href="?status=Pending" data-status="Pending"
                class="stats-card p-6 cursor-pointer relative overflow-hidden group <?= $filterStatus === 'Pending' ? 'active' : '' ?>">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <h3
                            class="stats-label font-semibold uppercase mb-2 group-hover:text-brand-brown transition-colors">
                            Pending</h3>
                        <div class="stats-number"><?= $statusCounts['Pending'] ?? 0 ?></div>
                    </div>
                    <!-- <div class="stats-icon-wrapper shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div> -->
                </div>
            </a>

            <!-- Processing Card -->
            <a href="?status=Processing" data-status="Processing"
                class="stats-card p-6 cursor-pointer relative overflow-hidden group <?= $filterStatus === 'Processing' ? 'active' : '' ?>">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <h3
                            class="stats-label font-semibold uppercase mb-2 group-hover:text-brand-brown transition-colors">
                            Processing</h3>
                        <div class="stats-number"><?= $statusCounts['Processing'] ?? 0 ?></div>
                    </div>
                    <!-- <div class="stats-icon-wrapper shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    </div> -->
                </div>
            </a>

            <!-- Shipped Card -->
            <a href="?status=Shipped" data-status="Shipped"
                class="stats-card p-6 cursor-pointer relative overflow-hidden group <?= $filterStatus === 'Shipped' ? 'active' : '' ?>">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <h3
                            class="stats-label font-semibold uppercase mb-2 group-hover:text-brand-brown transition-colors">
                            Shipped</h3>
                        <div class="stats-number"><?= $statusCounts['Shipped'] ?? 0 ?></div>
                    </div>
                    <!-- <div class="stats-icon-wrapper shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                    </div> -->
                </div>
            </a>

            <!-- Delivered Card -->
            <a href="?status=Delivered" data-status="Delivered"
                class="stats-card p-6 cursor-pointer relative overflow-hidden group <?= $filterStatus === 'Delivered' ? 'active' : '' ?>">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <h3
                            class="stats-label font-semibold uppercase mb-2 group-hover:text-brand-brown transition-colors">
                            Delivered</h3>
                        <div class="stats-number"><?= $statusCounts['Delivered'] ?? 0 ?></div>
                    </div>
                    <!-- <div class="stats-icon-wrapper shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div> -->
                </div>
            </a>

            <!-- Cancelled Card -->
            <a href="?status=Cancelled" data-status="Cancelled"
                class="stats-card p-6 cursor-pointer relative overflow-hidden group <?= $filterStatus === 'Cancelled' ? 'active' : '' ?>">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <h3
                            class="stats-label font-semibold uppercase mb-2 group-hover:text-brand-brown transition-colors">
                            Cancelled</h3>
                        <div class="stats-number"><?= $statusCounts['Cancelled'] ?? 0 ?></div>
                    </div>
                    <!-- <div class="stats-icon-wrapper shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div> -->
                </div>
            </a>
        </div>

        <!-- Orders Table -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                <div>
                    <h3 class="text-xl font-semibold text-brand-dark">
                        <?= $filterStatus ? "Orders: $filterStatus" : "All Orders" ?>
                    </h3>
                    <?php if ($filterStatus): ?>
                    <span class="text-sm text-gray-500">Filter applied. <a href="manage_orders.php"
                            class="text-brand-brown hover:underline">Show all orders</a></span>
                    <?php endif; ?>
                </div>
                <?php if ($filterStatus): ?>
                <a href="manage_orders.php"
                    class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-medium transition-colors">
                    Clear Filter
                </a>
                <?php endif; ?>
            </div>

            <?php if (empty($orders)): ?>
            <div class="p-8 text-center">
                <?php if ($filterStatus): ?>
                <p class="text-gray-500 text-lg">No orders found with status "<?= htmlspecialchars($filterStatus) ?>".
                </p>
                <a href="manage_orders.php" class="text-brand-brown hover:underline mt-2 inline-block">View all
                    orders</a>
                <?php else: ?>
                <p class="text-gray-500 text-lg">No orders found.</p>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Order ID</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Total</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Items</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($orders as $order): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 font-semibold text-brand-dark">#<?= $order['Order_ID'] ?></td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-semibold text-brand-dark">
                                    <?= htmlspecialchars($order['Customer_Name']) ?></div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($order['Customer_Email']) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <?= date('M d, Y', strtotime($order['Order_Date'])) ?></td>
                            <td class="px-6 py-4 font-semibold text-brand-brown">
                                ₱<?= number_format($order['Total_Amount'], 2) ?></td>
                            <td class="px-6 py-4 text-center text-sm font-semibold"><?= $order['Item_Count'] ?></td>
                            <td class="px-6 py-4"><?= getStatusBadge($order['Status'], true) ?></td>
                            <td class="gap-2 flex flex-col px-4 py-3">
                                <button
                                    class="view-order-btn px-3 py-1 bg-brand-brown text-white rounded text-xs font-semibold hover:bg-opacity-90 transition-colors"
                                    data-order-id="<?= $order['Order_ID'] ?>">
                                    View
                                </button>
                                <button
                                    class="update-status-btn px-3 py-1 bg-blue-500 text-white rounded text-xs font-semibold hover:bg-opacity-90 transition-colors"
                                    data-order-id="<?= $order['Order_ID'] ?>"
                                    data-current-status="<?= $order['Status'] ?>">
                                    Update
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div id="order-details-modal"
        class="modal hidden fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="modal-panel relative w-full max-w-2xl bg-white rounded-lg shadow-xl p-8">
            <button id="close-modal-btn"
                class="absolute -top-10 right-0 text-white hover:text-gray-300 text-4xl">&times;</button>
            <h2 class="text-3xl font-serif font-bold text-brand-dark mb-6">Order Details</h2>
            <div id="modal-content" class="space-y-6">
                <!-- Content loaded via AJAX -->
            </div>
            <button id="close-modal-bottom"
                class="w-full mt-6 bg-brand-brown text-white py-2.5 rounded-full font-semibold hover:bg-opacity-90 transition-colors">Close</button>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div id="update-status-modal"
        class="modal hidden fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="modal-panel relative w-full max-w-sm bg-white rounded-lg shadow-xl p-6">
            <h2 class="text-2xl font-semibold text-brand-dark mb-4">Update Order Status</h2>
            <form id="status-form">
                <input type="hidden" id="status-order-id" value="">
                <div class="mb-6">
                    <label for="new-status" class="block text-sm font-semibold text-brand-dark mb-2">New Status</label>
                    <select id="new-status"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-brown">
                        <option value="">Select Status</option>
                        <option value="Pending">Pending</option>
                        <option value="Processing">Processing</option>
                        <option value="Shipped">Shipped</option>
                        <option value="Delivered">Delivered</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="flex gap-3">
                    <button type="submit"
                        class="flex-1 bg-brand-brown text-white py-2 rounded-lg font-semibold hover:bg-opacity-90 transition-colors">Update</button>
                    <button type="button" id="close-status-modal"
                        class="flex-1 bg-gray-300 text-gray-800 py-2 rounded-lg font-semibold hover:bg-gray-400 transition-colors">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const orderDetailsModal = document.getElementById('order-details-modal');
        const updateStatusModal = document.getElementById('update-status-modal');
        const closeModalBtn = document.getElementById('close-modal-btn');
        const closeModalBottom = document.getElementById('close-modal-bottom');
        const closeStatusModalBtn = document.getElementById('close-status-modal');
        const modalContent = document.getElementById('modal-content');
        const statusForm = document.getElementById('status-form');

        // View Order Details
        document.querySelectorAll('.view-order-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                const orderId = btn.dataset.orderId;
                try {
                    const response = await fetch(
                        `get_order_details.php?order_id=${orderId}`);
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    const data = await response.json();

                    if (data.success) {
                        modalContent.innerHTML = data.html;
                        orderDetailsModal.classList.remove('hidden');
                    } else {
                        alert('❌ Failed to load order details: ' + (data.message ||
                            'Unknown error'));
                    }
                } catch (error) {
                    alert('Error loading order details: ' + error.message);
                }
            });
        });

        // Update Status
        document.querySelectorAll('.update-status-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.getElementById('status-order-id').value = btn.dataset.orderId;
                document.getElementById('new-status').value = btn.dataset.currentStatus;
                updateStatusModal.classList.remove('hidden');
            });
        });

        statusForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const orderId = document.getElementById('status-order-id').value;
            const newStatus = document.getElementById('new-status').value;

            if (!newStatus) {
                alert('Please select a status');
                return;
            }

            try {
                const response = await fetch('update_order_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        order_id: parseInt(orderId),
                        status: newStatus
                    })
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();

                if (data.success) {
                    alert('Order status updated successfully');
                    updateStatusModal.classList.add('hidden');
                    // Reload page to reflect changes (maintaining filter)
                    window.location.href = window.location.href;
                } else {
                    alert('Failed to update status: ' + (data.message || 'Unknown error'));
                    console.error('Error response:', data);
                }
            } catch (error) {
                console.error('Error updating order:', error);
                alert('Network error: ' + error.message);
            }
        });

        // Modal Controls
        closeModalBtn.addEventListener('click', () => orderDetailsModal.classList.add('hidden'));
        closeModalBottom.addEventListener('click', () => orderDetailsModal.classList.add('hidden'));
        closeStatusModalBtn.addEventListener('click', () => updateStatusModal.classList.add('hidden'));

        orderDetailsModal.addEventListener('click', (e) => {
            if (e.target === orderDetailsModal) orderDetailsModal.classList.add('hidden');
        });
        updateStatusModal.addEventListener('click', (e) => {
            if (e.target === updateStatusModal) updateStatusModal.classList.add('hidden');
        });
    });
    </script>

</body>

</html>