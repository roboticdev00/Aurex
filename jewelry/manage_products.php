<?php
// manage_products.php
session_start();
require_once 'db_connect.php'; // PDO connection

// --- CRITICAL RBAC CHECK (Security First) ---
if (!isset($_SESSION['User_ID'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['User_ID'];
$stmt = $pdo->prepare("SELECT Name, Role FROM user WHERE User_ID = :user_id");
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch();
$userName = htmlspecialchars($user['Name'] ?? 'Admin');

if (($user['Role'] ?? '') !== 'admin') {
    header('Location: jewelry_landing.php');
    exit;
}

$products = [];

// 1. Fetch Products and Categories using a JOIN
$stmt_products = $pdo->prepare("
  SELECT
      p.Product_ID,
      p.Name AS ProductName,
      p.Description,
      p.Price,
      p.Stock,
      p.Availability,
      p.Images,
      c.Category_Name
  FROM product p
  JOIN category c ON p.Category_ID = c.Category_ID
  ORDER BY p.Product_ID DESC
");

$stmt_products->execute();
$products = $stmt_products->fetchAll();

// Count products by category (For the Filters)
$allProductsCount = count($products);
$earringsCount = count(array_filter($products, function ($p) {
    return strtolower($p['Category_Name']) === 'earrings';
}));
$braceletCount = count(array_filter($products, function ($p) {
    return strtolower($p['Category_Name']) === 'bracelet';
}));
$necklaceCount = count(array_filter($products, function ($p) {
    return strtolower($p['Category_Name']) === 'necklace';
}));
$ringsCount = count(array_filter($products, function ($p) {
    return strtolower($p['Category_Name']) === 'rings';
}));

// Count Stock Status (For the Pie Chart)
$inStockCount = 0;
$lowStockCount = 0;
$outOfStockCount = 0;

foreach ($products as $p) {
    if ($p['Stock'] > 5) {
        $inStockCount++;
    } elseif ($p['Stock'] > 0 && $p['Stock'] <= 5) {
        $lowStockCount++;
    } else {
        $outOfStockCount++;
    }
}

// Check for success/error messages
$message = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $message = "Product successfully deleted.";
} elseif (isset($_GET['msg']) && $_GET['msg'] === 'added') {
    $message = "New product added successfully!";
} elseif (isset($_GET['msg']) && $_GET['msg'] === 'updated') {
    $message = "Product updated successfully!";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Products - Jewellery Admin</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chart.js for the Pie Chart -->
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
                        'teal-light': '#DCCEB8',
                        'teal-darker': '#3F3F3C',
                    },
                    keyframes: {
                        'pulse-red-strong': {
                            '0%, 100%': {
                                backgroundColor: '#FFFFFF'
                            },
                            '50%': {
                                backgroundColor: '#FECACA'
                            }, // red-200 (Strong Red)
                        }
                    },
                    animation: {
                        'pulse-red-strong': 'pulse-red-strong 2s cubic-bezier(0.4, 0, 0.6, 1) infinite',
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

        .table-header {
            background-color: #8B7B61;
            color: white;
            font-weight: 600;
        }

        /* Compact Filter List Item Styling */
        .filter-item {
            background-color: white;
            border-radius: 6px;
            padding: 0.5rem 0.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            border-left: 4px solid transparent;
            margin-bottom: 0.5rem;
        }

        .filter-item:hover {
            transform: translateX(2px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .filter-item.active {
            background-color: #8B7B61;
            /* brand-teal */
            color: white;
            border-left-color: #DCCEB8;
            /* teal-light */
        }

        .filter-item .count-badge {
            background-color: #F3F4F6;
            color: #4D4C48;
            padding: 1px 6px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .filter-item.active .count-badge {
            background-color: white;
            color: #8B7B61;
        }

        .filter-item.hidden-rings {
            display: none;
        }

        .filter-text {
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Chart Container - Made Smaller */
        .chart-wrapper {
            position: relative;
            height: 200px;
            /* Reduced from 280px */
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        /* Center Text Overlay */
        .chart-center-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            pointer-events: none;
        }

        .chart-number {
            font-size: 1.8rem;
            /* Reduced to fit smaller graph */
            font-weight: 800;
            color: #8B7B61;
            line-height: 1;
        }

        .chart-label {
            font-size: 0.7rem;
            /* Smaller label */
            text-transform: uppercase;
            color: #7A7977;
            letter-spacing: 0.05em;
            margin-top: 2px;
        }

        /* Legend */
        .chart-legend {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 0.5rem;
            padding-top: 0.5rem;
            border-top: 1px solid #F3F4F6;
        }

        .legend-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.7rem;
            font-weight: 600;
            color: #4D4C48;
            cursor: pointer;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            transition: background 0.2s;
            min-width: 70px;
        }

        .legend-item:hover {
            background-color: #F3F4F6;
        }

        .legend-header {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            margin-bottom: 2px;
        }

        .legend-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .legend-value {
            font-size: 1rem;
            font-weight: 700;
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
            <li><a href="manage_products.php" class="block p-3 rounded text-white nav-active transition-all">
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
        <div class="flex justify-between items-center mb-6 border-b border-gray-200 pb-4">
            <h2 class="font-serif text-4xl font-bold text-brand-dark">Manage Products</h2>

            <a href="edit_product.php"
                class="bg-brand-teal text-white px-6 py-2 rounded-full font-semibold hover:bg-opacity-90 transition-colors shadow-md">
                + Add New Product
            </a>
        </div>

        <?php if ($message): ?>
            <div class="p-4 mb-4 rounded-xl bg-brand-teal/10 text-brand-dark border border-brand-teal">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Section: Chart + Filters -->
        <div class="grid grid-cols-1 md:grid-cols-12 gap-6 mb-8 items-start">

            <!-- Pie Chart Column -->
            <div class="md:col-span-9 bg-white p-4 rounded-xl shadow-lg">
                <div class="chart-wrapper">
                    <canvas id="stockPieChart"></canvas>

                    <!-- Center Text Overlay -->
                    <div class="chart-center-text">
                        <div class="chart-number" id="chartNumber"><?= $allProductsCount ?></div>
                        <div class="chart-label" id="chartLabel">Total Items</div>
                    </div>
                </div>

                <!-- Custom Legend with Values -->
                <div class="chart-legend">
                    <div class="legend-item" onclick="resetFilter()">
                        <div class="legend-header">
                            <div class="legend-dot" style="background-color: #8B7B61;"></div>
                            <span>In Stock</span>
                        </div>
                        <div class="legend-value text-brand-teal"><?= $inStockCount ?></div>
                    </div>
                    <div class="legend-item" onclick="resetFilter()">
                        <div class="legend-header">
                            <div class="legend-dot" style="background-color: #DCCEB8;"></div>
                            <span>Low Stock</span>
                        </div>
                        <div class="legend-value text-brand-teal"><?= $lowStockCount ?></div>
                    </div>
                    <div class="legend-item" onclick="resetFilter()">
                        <div class="legend-header">
                            <div class="legend-dot" style="background-color: #E5E5E5;"></div>
                            <span>Out</span>
                        </div>
                        <div class="legend-value text-gray-500"><?= $outOfStockCount ?></div>
                    </div>
                </div>
            </div>

            <!-- Filter List Column -->
            <div class="md:col-span-3 flex flex-col gap-2 pt-2">
                <h3 class="font-serif text-lg font-bold text-brand-dark mb-2 px-1">Categories</h3>

                <!-- All Products -->
                <div class="filter-item active" data-filter="all">
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-current" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <span class="filter-text">All Products</span>
                    </div>
                    <span class="count-badge"><?= $allProductsCount ?></span>
                </div>

                <!-- Earrings -->
                <div class="filter-item" data-filter="earrings">
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-current" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                        </svg>
                        <span class="filter-text">Earrings</span>
                    </div>
                    <span class="count-badge"><?= $earringsCount ?></span>
                </div>

                <!-- Bracelet -->
                <div class="filter-item" data-filter="bracelet">
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-current" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="filter-text">Bracelet</span>
                    </div>
                    <span class="count-badge"><?= $braceletCount ?></span>
                </div>

                <!-- Necklace -->
                <div class="filter-item" data-filter="necklace">
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-current" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                        <span class="filter-text">Necklace</span>
                    </div>
                    <span class="count-badge"><?= $necklaceCount ?></span>
                </div>

                <!-- Rings (Hidden but functional) -->
                <div class="filter-item hidden-rings" data-filter="rings">
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-current" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="filter-text">Rings</span>
                    </div>
                    <span class="count-badge"><?= $ringsCount ?></span>
                </div>
            </div>
        </div>

        <!-- Products Table -->
        <div class="bg-white p-6 rounded-xl shadow-lg overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="table-header">
                    <tr>
                        <th class="px-6 py-3 text-left text-sm uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3 text-left text-sm uppercase tracking-wider">Image</th>
                        <!-- New Image Column -->
                        <th class="px-6 py-3 text-left text-sm uppercase tracking-wider">Product Name</th>
                        <th class="px-6 py-3 text-left text-sm uppercase tracking-wider">Category</th>
                        <th class="px-6 py-3 text-left text-sm uppercase tracking-wider">Price (₱)</th>
                        <th class="px-6 py-3 text-left text-sm uppercase tracking-wider">Stock</th>
                        <th class="px-6 py-3 text-left text-sm uppercase tracking-wider">Availability</th>
                        <th class="px-6 py-3 text-center text-sm uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100" id="productTableBody">
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="8" class="px-6 py-10 text-center text-brand-subtext text-lg">No products found in
                                the catalog. Start by adding a new one!</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>

                            <?php
                            // Determine Row Styling based on Stock
                            $stockClass = '';
                            $stockLabel = htmlspecialchars($product['Stock']);
                            $stockTextClass = 'text-brand-subtext';
                            $rowClass = '';
                            $showDangerIcon = false;

                            if ($product['Stock'] > 5) {
                                $stockClass = 'bg-green-100 text-green-800';
                            } elseif ($product['Stock'] > 0 && $product['Stock'] <= 5) {
                                $stockClass = 'bg-yellow-100 text-yellow-800';
                            } else {
                                $stockClass = 'bg-red-600 text-white'; // Stronger Red Badge
                                $stockLabel = 'Out of Stock';
                                $rowClass = 'row-out-of-stock animate-pulse-red-strong'; // Moving Strong Red Animation
                                $stockTextClass = 'text-red-700 font-bold';
                                $showDangerIcon = true; // Trigger Danger Sign
                            }

                            // Handle Image
                            $product_image_url = !empty($product['Images']) ? htmlspecialchars($product['Images']) : 'https://placehold.co/50x50/F3F4F6/3A3A3A?text=No+Img';
                            ?>

                            <tr data-category="<?= strtolower(htmlspecialchars($product['Category_Name'])) ?>"
                                data-stock="<?= htmlspecialchars($product['Stock']) ?>" class="<?= $rowClass ?>">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-brand-dark">
                                    <?= htmlspecialchars($product['Product_ID']) ?></td>

                                <!-- New Image Column -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <img src="<?= $product_image_url ?>" alt="<?= htmlspecialchars($product['ProductName']) ?>"
                                        class="h-10 w-10 rounded-md object-cover shadow-sm border border-gray-200">
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-brand-dark">
                                    <?= htmlspecialchars($product['ProductName']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-brand-subtext">
                                    <?= htmlspecialchars($product['Category_Name']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-brand-teal">
                                    ₱<?= number_format($product['Price'], 2) ?></td>

                                <td class="px-6 py-4 whitespace-nowrap text-sm <?= $stockTextClass ?>">
                                    <div class="flex items-center gap-2">
                                        <?php if ($showDangerIcon): ?>
                                            <!-- Danger Icon SVG -->
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-600" viewBox="0 0 20 20"
                                                fill="currentColor">
                                                <path fill-rule="evenodd"
                                                    d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        <?php endif; ?>

                                        <span
                                            class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $stockClass ?>">
                                            <?= $stockLabel ?>
                                        </span>
                                    </div>
                                </td>

                                <td
                                    class="px-6 py-4 whitespace-nowrap text-sm text-brand-subtext capitalize <?= ($product['Stock'] == 0) ? 'text-red-600 font-bold' : '' ?>">
                                    <?= htmlspecialchars($product['Availability']) ?>
                                </td>

                                <!-- Added space-x-3 here to separate buttons -->
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium space-x-3">
                                    <a href="edit_product.php?id=<?= $product['Product_ID'] ?>"
                                        class="px-3 py-1 bg-blue-500 text-white rounded text-xs font-semibold hover:bg-blue-600 transition-colors">
                                        Edit
                                    </a>
                                    <button
                                        class="delete-product-btn px-3 py-1 bg-red-500 text-white rounded text-xs font-semibold hover:bg-red-600 transition-colors"
                                        data-product-id="<?= $product['Product_ID'] ?>"
                                        data-product-name="<?= htmlspecialchars($product['ProductName']) ?>">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {

            // --- 1. Chart.js Initialization (Stock Status) ---
            const ctx = document.getElementById('stockPieChart').getContext('2d');

            const stockData = {
                inStock: <?= $inStockCount ?>,
                lowStock: <?= $lowStockCount ?>,
                outOfStock: <?= $outOfStockCount ?>
            };

            const chartNumberEl = document.getElementById('chartNumber');
            const chartLabelEl = document.getElementById('chartLabel');

            const stockPieChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['In Stock', 'Low Stock', 'Out of Stock'],
                    datasets: [{
                        data: [stockData.inStock, stockData.lowStock, stockData.outOfStock],
                        backgroundColor: [
                            '#8B7B61',
                            '#DCCEB8',
                            '#E5E5E5'
                        ],
                        borderWidth: 0,
                        hoverOffset: 10
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%', // Reduced hole size (thicker ring, less white space)
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: '#4D4C48',
                            bodyFont: {
                                family: 'Inter',
                                size: 14
                            },
                            padding: 12,
                            displayColors: true,
                            callbacks: {
                                label: function(context) {
                                    return ' ' + context.label + ': ' + context.raw;
                                }
                            }
                        }
                    },
                    onClick: (evt, activeElements) => {
                        if (activeElements.length > 0) {
                            const index = activeElements[0].index;
                            const label = stockPieChart.data.labels[index];

                            // Filter by Stock Status
                            filterByStock(label, index);

                            // Update Center Text
                            const value = stockPieChart.data.datasets[0].data[index];
                            chartNumberEl.innerText = value;
                            chartLabelEl.innerText = label;
                        }
                    }
                }
            });

            // --- 2. Filter Logic ---
            const tableRows = document.querySelectorAll('#productTableBody tr');
            const filterItems = document.querySelectorAll('.filter-item');

            function resetFilter() {
                // Reset Visuals
                filterItems.forEach(i => i.classList.remove('active'));
                document.querySelector('[data-filter="all"]').classList.add('active');

                // Reset Table
                tableRows.forEach(row => {
                    row.style.display = '';
                });

                // Reset Center Text
                chartNumberEl.innerText = "<?= $allProductsCount ?>";
                chartLabelEl.innerText = "Total Items";
            }

            function filterByStock(label, index) {
                // Determine stock threshold based on chart index
                let minStock = 0;
                let maxStock = 999999;

                if (label === 'In Stock') {
                    minStock = 6;
                } else if (label === 'Low Stock') {
                    minStock = 1;
                    maxStock = 5;
                } else if (label === 'Out of Stock') {
                    minStock = 0;
                    maxStock = 0;
                }

                // Reset Category Filter UI visuals
                filterItems.forEach(i => i.classList.remove('active'));

                // Filter Table
                tableRows.forEach(row => {
                    if (row.dataset.stock === undefined) return;

                    const stockVal = parseInt(row.dataset.stock);
                    if (stockVal >= minStock && stockVal <= maxStock) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }

            // --- 3. Category Filter Items ---
            filterItems.forEach(item => {
                item.addEventListener('click', function() {
                    // Reset Center Text to Total
                    chartNumberEl.innerText = "<?= $allProductsCount ?>";
                    chartLabelEl.innerText = "Total Items";

                    // UI Toggle
                    filterItems.forEach(i => i.classList.remove('active'));
                    this.classList.add('active');

                    const filter = this.getAttribute('data-filter');

                    // Filter Table Logic
                    tableRows.forEach(row => {
                        if (row.dataset.category === undefined) {
                            row.style.display = (filter === 'all') ? '' : 'none';
                            return;
                        }

                        if (filter === 'all') {
                            row.style.display = '';
                        } else {
                            const category = row.getAttribute('data-category');
                            row.style.display = category === filter ? '' : 'none';
                        }
                    });
                });
            });

            // --- 4. Delete Product Functionality ---
            document.querySelectorAll('.delete-product-btn').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    e.preventDefault();
                    const productId = btn.dataset.productId;
                    const productName = btn.dataset.productName;

                    if (!confirm(
                            `Are you sure you want to delete "${productName}"? This action cannot be undone.`
                        )) {
                        return;
                    }

                    try {
                        const response = await fetch('delete_product.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                product_id: parseInt(productId)
                            })
                        });

                        const data = await response.json();

                        if (data.success) {
                            window.location.href = 'manage_products.php?msg=deleted';
                        } else {
                            alert('Failed to delete product: ' + data.message);
                        }
                    } catch (error) {
                        console.error('Delete error:', error);
                        alert('An error occurred while deleting the product.');
                    }
                });
            });
        });
    </script>
</body>

</html>