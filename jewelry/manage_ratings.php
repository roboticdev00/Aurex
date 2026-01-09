<?php
    // manage_ratings.php
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

    // Fetch products (Used for Filtering Dropdown)
    $products = [];
    try {
        $stmt = $pdo->query("SELECT Product_ID, Name FROM product ORDER BY Name");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching products: " . $e->getMessage());
    }

    // Fetch ALL ratings (No filters, no pagination)
    try {
        // Get total count
        $count_sql = "
            SELECT COUNT(*) 
            FROM order_ratings r
            JOIN product p ON r.Product_ID = p.Product_ID
            JOIN user u ON r.User_ID = u.User_ID
        ";
        $total_ratings = $pdo->query($count_sql)->fetchColumn();
        
        // Get ratings data
        $sql = "
            SELECT 
                r.Rating_ID,
                r.Rating, 
                r.Review_Text, 
                r.Rating_Date,
                r.Review_Image,
                u.Name as UserName,
                u.User_ID,
                u.Email,
                p.Name as ProductName,
                p.Product_ID,
                o.Order_ID,
                o.Status as OrderStatus
            FROM order_ratings r
            JOIN product p ON r.Product_ID = p.Product_ID
            JOIN user u ON r.User_ID = u.User_ID
            JOIN orders o ON r.Order_ID = o.Order_ID
            ORDER BY r.Rating_Date DESC
        ";
        
        $stmt = $pdo->query($sql);
        $ratings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error fetching ratings: " . $e->getMessage());
        $ratings = [];
        $total_ratings = 0;
    }

    // Calculate rating statistics
    try {
        $stats_sql = "
            SELECT 
                COUNT(*) as total_reviews,
                AVG(Rating) as avg_rating,
                COUNT(CASE WHEN Rating = 5 THEN 1 END) as five_star,
                COUNT(CASE WHEN Rating = 4 THEN 1 END) as four_star,
                COUNT(CASE WHEN Rating = 3 THEN 1 END) as three_star,
                COUNT(CASE WHEN Rating = 2 THEN 1 END) as two_star,
                COUNT(CASE WHEN Rating = 1 THEN 1 END) as one_star
            FROM order_ratings
        ";
        $stats_stmt = $pdo->query($stats_sql);
        $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching rating statistics: " . $e->getMessage());
        $stats = [
            'total_reviews' => 0,
            'avg_rating' => 0,
            'five_star' => 0,
            'four_star' => 0,
            'three_star' => 0,
            'two_star' => 0,
            'one_star' => 0
        ];
    }

    // Get top rated products
    try {
        $top_products_sql = "
            SELECT 
                p.Product_ID,
                p.Name,
                p.Images,
                COUNT(r.Rating_ID) as review_count,
                AVG(r.Rating) as avg_rating
            FROM product p
            LEFT JOIN order_ratings r ON p.Product_ID = r.Product_ID
            GROUP BY p.Product_ID, p.Name, p.Images
            HAVING review_count > 0
            ORDER BY avg_rating DESC, review_count DESC
            LIMIT 5
        ";
        $top_products_stmt = $pdo->query($top_products_sql);
        $top_products = $top_products_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching top rated products: " . $e->getMessage());
        $top_products = [];
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Manage Ratings - Aurex Jewelry</title>

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
                        // Custom colors for dashboard contrast
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
            .nav-active {
                background-color: #8B7B61; /* brand-teal */
                color: white;
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
            
            .star-rating {
                color: #fbbf24;
                font-size: 1.2rem;
                display: inline-flex;
                align-items: center;
            }
            .star-rating .empty {
                color: #d1d5db;
            }
            
            /* MODAL ANIMATIONS */
            .modal {
                transition: opacity 0.3s ease-in-out;
            }
            .modal.hidden {
                pointer-events: none;
                opacity: 0;
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

            /* HOVER EFFECTS (Movement/Lift) */
            .hover-lift-card {
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                border-radius: 1rem;
                overflow: hidden;
                border: 1px solid #F3F4F6;
                background-color: #ffffff;
            }
            .hover-lift-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            }

            .review-image {
                max-width: 150px;
                max-height: 150px;
                border-radius: 0.5rem;
                cursor: pointer;
                transition: transform 0.2s;
            }
            .review-image:hover {
                transform: scale(1.05);
            }
            .image-modal {
                display: none;
                position: fixed;
                z-index: 1000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.9);
                cursor: pointer;
            }
            .modal-content {
                margin: auto;
                display: block;
                width: 80%;
                max-width: 700px;
                max-height: 80%;
                margin-top: 5%;
            }
            .close {
                position: absolute;
                top: 15px;
                right: 35px;
                color: #f1f1f1;
                font-size: 40px;
                font-weight: bold;
                transition: 0.3s;
            }
            .close:hover,
            .close:focus {
                color: #bbb;
                text-decoration: none;
                cursor: pointer;
            }
        </style>
    </head>
    <body class="flex antialiased">
        
        <!-- Sidebar -->
        <div class="w-64 admin-sidebar h-screen p-6 fixed flex flex-col shadow-2xl z-50">
            <!-- CHANGED: Updated Title to Aurex matching manage_report -->
            <h1 class="font-serif text-white text-3xl font-bold mb-10 border-b border-gray-600 pb-4">Aurex</h1>
            
            <div class="text-white mb-8 border-b border-teal-light/50 pb-4">
                <p class="font-semibold text-sm text-teal-light uppercase tracking-wider">Store Administrator</p>
                <p class="text-lg font-bold text-white mt-1"><?= $userName ?></p>
            </div>
            
            <ul class="space-y-2 flex-grow">
                <li><a href="admin_dashboard.php" class="block p-3 rounded text-white hover:bg-brand-teal transition-all">Dashboard</a></li>
                <li><a href="manage_users.php" class="block p-3 rounded text-white hover:bg-brand-teal transition-all">Manage Users</a></li>
                <li><a href="manage_products.php" class="block p-3 rounded text-white hover:bg-brand-teal transition-all">Manage Products</a></li>
                <li><a href="manage_orders.php" class="block p-3 rounded text-white hover:bg-brand-teal transition-all">Manage Orders</a></li>
                <!-- ADDED: Active class matching report style -->
                <li class="nav-active"><a href="manage_ratings.php" class="block p-3 rounded text-white nav-active transition-all">Manage Ratings</a></li>
                <li><a href="manage_report.php" class="block p-3 rounded text-white hover:bg-brand-teal transition-all">Sales Report</a></li>
            </ul>
            
            <a href="logout.php" class="mt-auto block w-full text-center p-3 rounded-full logout-btn shadow-lg">
                <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                Sign Out
            </a>
        </div>

        <!-- Main Content -->
        <div class="flex-1 ml-64 p-10">
            <div class="flex justify-between items-center mb-8">
                <h2 class="font-serif text-4xl font-bold text-brand-dark">Manage Ratings</h2>
                <a href="admin_dashboard.php" class="text-brand-teal font-semibold hover:text-brand-dark transition-colors">← Back</a>
            </div>

            <!-- Rating Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white p-6 rounded-xl shadow-lg border-l-4 border-brand-teal">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <p class="text-sm font-semibold text-brand-subtext uppercase">Total Reviews</p>
                            <p class="font-serif text-3xl font-extrabold text-brand-teal mt-1"><?= number_format($stats['total_reviews']) ?></p>
                        </div>
                        <svg class="w-8 h-8 text-brand-teal" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                        </svg>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-xl shadow-lg border-l-4 border-brand-teal">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <p class="text-sm font-semibold text-brand-subtext uppercase">Average Rating</p>
                            <p class="font-serif text-3xl font-extrabold text-brand-teal mt-1"><?= number_format($stats['avg_rating'], 1) ?></p>
                        </div>
                        <div class="star-rating">
                            <?php 
                            $avg_rating = $stats['avg_rating'] ?? 0;
                            for ($i = 1; $i <= 5; $i++) {
                                if ($i <= round($avg_rating)) {
                                    echo '★';
                                } else {
                                    echo '<span class="empty">★</span>';
                                }
                            }
                            ?>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-xl shadow-lg border-l-4 border-brand-teal">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <p class="text-sm font-semibold text-brand-subtext uppercase">5-Star Reviews</p>
                            <p class="font-serif text-3xl font-extrabold text-brand-teal mt-1"><?= number_format($stats['five_star']) ?></p>
                        </div>
                        <div class="star-rating">
                            ★★★★★
                        </div>
                    </div>
                    <div class="text-xs text-brand-subtext">
                        <?= $stats['total_reviews'] > 0 ? round(($stats['five_star'] / $stats['total_reviews']) * 100, 1) : 0 ?>% of all reviews
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-xl shadow-lg border-l-4 border-brand-teal">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <p class="text-sm font-semibold text-brand-subtext uppercase">1-Star Reviews</p>
                            <p class="font-serif text-3xl font-extrabold text-brand-teal mt-1"><?= number_format($stats['one_star']) ?></p>
                        </div>
                        <div class="star-rating">
                            <span class="empty">★★★★</span>★
                        </div>
                    </div>
                    <div class="text-xs text-brand-subtext">
                        <?= $stats['total_reviews'] > 0 ? round(($stats['one_star'] / $stats['total_reviews']) * 100, 1) : 0 ?>% of all reviews
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Rating Distribution -->
                <div class="bg-white p-6 rounded-xl shadow-lg border border-gray-200">
                    <h3 class="font-serif text-xl font-bold text-brand-dark mb-4">Rating Distribution</h3>
                    <div class="space-y-3">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <?php 
                            $count = $stats[strtolower(["five", "four", "three", "two", "one"][5-$i]) . "_star"];
                            $percentage = $stats['total_reviews'] > 0 ? ($count / $stats['total_reviews']) * 100 : 0;
                            ?>
                            <div class="flex items-center gap-3">
                                <div class="flex items-center gap-1 w-16">
                                    <span><?= $i ?></span>
                                    <span class="text-yellow-500">★</span>
                                </div>
                                <div class="flex-1">
                                    <div class="h-8 bg-gray-200 rounded-lg overflow-hidden">
                                        <div class="h-full bg-yellow-500 rounded-lg" style="width: <?= $percentage ?>%"></div>
                                    </div>
                                </div>
                                <div class="w-12 text-right text-brand-subtext">
                                    <?= $count ?>
                                </div>
                                <div class="w-12 text-right text-brand-subtext">
                                    <?= number_format($percentage, 1) ?>%
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Top Rated Products -->
                <div class="bg-white p-6 rounded-xl shadow-lg border border-gray-200">
                    <h3 class="font-serif text-xl font-bold text-brand-dark mb-4">Top Rated Products</h3>
                    <div class="space-y-3">
                        <?php if (empty($top_products)): ?>
                            <p class="text-brand-subtext text-sm">No rated products yet</p>
                        <?php else: ?>
                            <?php foreach ($top_products as $index => $product): ?>
                                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                    <span class="w-6 h-6 bg-brand-teal text-white text-xs font-bold rounded-full flex items-center justify-center">
                                        <?= $index + 1 ?>
                                    </span>
                                    <img src="<?= htmlspecialchars($product['Images']) ?>" alt="<?= htmlspecialchars($product['Name']) ?>" class="w-10 h-10 object-cover rounded">
                                    <div class="flex-grow">
                                        <p class="font-semibold text-brand-dark text-sm"><?= htmlspecialchars($product['Name']) ?></p>
                                        <div class="flex items-center gap-2">
                                            <div class="star-rating text-sm">
                                                <?php 
                                                for ($i = 1; $i <= 5; $i++) {
                                                    if ($i <= round($product['avg_rating'])) {
                                                        echo '★';
                                                    } else {
                                                        echo '<span class="empty">★</span>';
                                                    }
                                                }
                                                ?>
                                            </div>
                                            <span class="text-xs text-brand-subtext"><?= number_format($product['avg_rating'], 1) ?> (<?= $product['review_count'] ?>)</span>
                                        </div>
                                    </div>
                                    <button onclick="viewProduct(<?= $product['Product_ID'] ?>)" class="px-3 py-1 text-sm bg-brand-teal text-white rounded hover:bg-opacity-90 transition-colors">
                                        View
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- FILTER SECTION -->
            <div class="bg-white p-6 rounded-xl shadow-lg mb-6 border border-gray-200">
                <h3 class="font-serif text-xl font-bold text-brand-dark mb-4">Filter Reviews</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                    <!-- Product Filter -->
                    <div>
                        <label class="block text-sm font-semibold text-brand-dark mb-2">Product</label>
                        <select id="filterProduct" class="w-full p-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-teal bg-white">
                            <option value="all">All Products</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?= $product['Product_ID'] ?>"><?= htmlspecialchars($product['Name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Rating Filter -->
                    <div>
                        <label class="block text-sm font-semibold text-brand-dark mb-2">Rating</label>
                        <select id="filterRating" class="w-full p-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-teal bg-white">
                            <option value="all">All Ratings</option>
                            <option value="5">5 Stars</option>
                            <option value="4">4 Stars</option>
                            <option value="3">3 Stars</option>
                            <option value="2">2 Stars</option>
                            <option value="1">1 Star</option>
                        </select>
                    </div>

                    <!-- Apply Button -->
                    <div>
                        <button onclick="applyFilters()" class="w-full bg-brand-teal text-white font-bold py-2 px-4 rounded-lg hover:bg-brand-dark transition-colors shadow-md">
                            Apply Filter
                        </button>
                    </div>
                </div>
            </div>

            <!-- Ratings List -->
            <div class="bg-white p-6 rounded-xl shadow-lg border border-gray-200">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="font-serif text-xl font-bold text-brand-dark">Customer Reviews</h3>
                    <p class="text-sm text-brand-subtext">
                        Showing <span id="visibleCount"><?= count($ratings) ?></span> of <span id="totalCount"><?= $total_ratings ?></span> reviews
                    </p>
                </div>
                
                <?php if (empty($ratings)): ?>
                    <div class="text-center py-12">
                        <svg class="w-24 h-24 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                        </svg>
                        <h3 class="text-xl font-semibold text-brand-dark mb-2">No Reviews Found</h3>
                        <p class="text-brand-subtext">There are currently no reviews in the database.</p>
                    </div>
                <?php else: ?>
                    <div id="ratingsList" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($ratings as $rating): ?>
                            <!-- CARD STYLE: Added hover-lift-card class for movement -->
                            <div class="rating-item hover-lift-card p-4 bg-white"
                                 data-product-id="<?= $rating['Product_ID'] ?>"
                                 data-rating="<?= $rating['Rating'] ?>">
                                <div class="flex justify-between items-start mb-3">
                                    <div>
                                        <h4 class="font-bold text-brand-dark text-sm mb-1 truncate"><?= htmlspecialchars($rating['ProductName']) ?></h4>
                                        <p class="text-xs text-brand-subtext truncate">by <?= htmlspecialchars($rating['UserName']) ?></p>
                                    </div>
                                    <div class="text-right">
                                        <div class="star-rating text-sm mb-1">
                                            <?php 
                                            for ($i = 1; $i <= 5; $i++) {
                                                if ($i <= $rating['Rating']) {
                                                    echo '★';
                                                } else {
                                                    echo '<span class="empty">★</span>';
                                                }
                                            }
                                            ?>
                                        </div>
                                        <p class="text-[10px] text-brand-subtext"><?= date('M d, Y', strtotime($rating['Rating_Date'])) ?></p>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <p class="text-brand-text text-sm line-clamp-3"><?= htmlspecialchars($rating['Review_Text'] ?? 'No review text provided.') ?></p>
                                </div>
                                
                                <?php if (!empty($rating['Review_Image'])): ?>
                                    <div class="mb-3">
                                        <img src="<?= htmlspecialchars($rating['Review_Image']) ?>" 
                                            alt="Review Image" 
                                            class="review-image w-full h-32 object-cover"
                                            onclick="openImageModal(this.src)">
                                    </div>
                                <?php endif; ?>
                                
                                <div class="flex flex-wrap gap-2 items-center">
                                    <span class="px-2 py-1 text-[10px] rounded-full bg-gray-100 text-brand-subtext">
                                        ID: <?= $rating['Rating_ID'] ?>
                                    </span>
                                    <span class="px-2 py-1 text-[10px] rounded-full 
                                        <?php 
                                        switch($rating['OrderStatus']) {
                                            case 'Pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                            case 'Processing': echo 'bg-blue-100 text-blue-800'; break;
                                            case 'Shipped': echo 'bg-purple-100 text-purple-800'; break;
                                            case 'Delivered': echo 'bg-green-100 text-green-800'; break;
                                            case 'Cancelled': echo 'bg-red-100 text-red-800'; break;
                                            default: echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                        <?= $rating['OrderStatus'] ?>
                                    </span>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-2 mt-4">
                                    <button onclick="viewProduct(<?= $rating['Product_ID'] ?>)" class="text-xs bg-gray-100 text-brand-dark py-2 rounded-lg hover:bg-gray-200 transition-colors">
                                        View Product
                                    </button>
                                    <!-- View Order Button -->
                                    <button
                                        class="view-order-btn text-xs bg-brand-teal text-white py-2 rounded-lg hover:bg-opacity-90 transition-colors"
                                        data-order-id="<?= $rating['Order_ID'] ?>">
                                        View Order
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <!-- Message to show if no filters match -->
                    <div id="noResults" class="hidden text-center py-12 col-span-full">
                        <h3 class="text-xl font-semibold text-brand-dark mb-2">No matching reviews</h3>
                        <p class="text-brand-subtext">Try changing your filter criteria.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Image Modal -->
        <div id="imageModal" class="image-modal">
            <span class="close" onclick="closeImageModal()">&times;</span>
            <img class="modal-content" id="img01">
        </div>

        <!-- Order Details Modal (Smaller, Compact) -->
        <div id="order-details-modal"
            class="modal hidden fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <!-- Made smaller: max-w-lg, p-5, compact layout -->
            <div class="modal-panel relative w-full max-w-lg bg-white rounded-lg shadow-xl p-5 overflow-hidden">
                <button id="close-modal-btn"
                    class="absolute top-3 right-3 text-gray-400 hover:text-gray-600 z-10">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
                
                <div class="mb-4 border-b pb-3">
                    <h2 class="text-lg font-bold text-brand-dark">Order Details</h2>
                </div>
                
                <div id="modal-content" class="max-h-[60vh] overflow-y-auto space-y-4 pr-2">
                    <!-- Content loaded via AJAX -->
                </div>
                
                <button id="close-modal-bottom"
                    class="w-full mt-5 bg-brand-teal text-white py-2 rounded-lg font-semibold hover:bg-opacity-90 transition-colors text-sm">
                    Close
                </button>
            </div>
        </div>

        <script>
            // Image Modal functionality
            function openImageModal(src) {
                document.getElementById('imageModal').style.display = "block";
                document.getElementById('img01').src = src;
            }
            
            function closeImageModal() {
                document.getElementById('imageModal').style.display = "none";
            }
            
            window.onclick = function(event) {
                if (event.target == document.getElementById('imageModal')) {
                    closeImageModal();
                }
            }
            
            // View Product function
            function viewProduct(productId) {
                window.open('view_review_admin.php?product_id=' + productId, '_blank');
            }

            // --- FILTER LOGIC ---
            function applyFilters() {
                const productId = document.getElementById('filterProduct').value;
                const ratingValue = document.getElementById('filterRating').value;
                const reviews = document.querySelectorAll('.rating-item');
                let visibleCount = 0;

                reviews.forEach(review => {
                    const reviewProductId = review.getAttribute('data-product-id');
                    const reviewRating = review.getAttribute('data-rating');

                    const productMatch = (productId === 'all') || (reviewProductId === productId);
                    const ratingMatch = (ratingValue === 'all') || (reviewRating === ratingValue);

                    if (productMatch && ratingMatch) {
                        review.style.display = 'block';
                        visibleCount++;
                    } else {
                        review.style.display = 'none';
                    }
                });

                // Update counters
                document.getElementById('visibleCount').innerText = visibleCount;

                // Show/Hide "No Results" message
                const noResultsDiv = document.getElementById('noResults');
                if (visibleCount === 0) {
                    noResultsDiv.classList.remove('hidden');
                } else {
                    noResultsDiv.classList.add('hidden');
                }
            }

            // --- VIEW ORDER MODAL LOGIC ---
            document.addEventListener('DOMContentLoaded', () => {
                const orderDetailsModal = document.getElementById('order-details-modal');
                const closeModalBtn = document.getElementById('close-modal-btn');
                const closeModalBottom = document.getElementById('close-modal-bottom');
                const modalContent = document.getElementById('modal-content');

                // View Order Details
                document.querySelectorAll('.view-order-btn').forEach(btn => {
                    btn.addEventListener('click', async () => {
                        const orderId = btn.dataset.orderId;
                        try {
                            const response = await fetch(`get_order_details.php?order_id=${orderId}`);
                            if (!response.ok) {
                                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                            }
                            const data = await response.json();

                            if (data.success) {
                                modalContent.innerHTML = data.html;
                                orderDetailsModal.classList.remove('hidden');
                            } else {
                                alert('❌ Failed to load order details: ' + (data.message || 'Unknown error'));
                            }
                        } catch (error) {
                            alert('Error loading order details: ' + error.message);
                        }
                    });
                });

                // Modal Controls
                closeModalBtn.addEventListener('click', () => orderDetailsModal.classList.add('hidden'));
                closeModalBottom.addEventListener('click', () => orderDetailsModal.classList.add('hidden'));

                orderDetailsModal.addEventListener('click', (e) => {
                    if (e.target === orderDetailsModal) orderDetailsModal.classList.add('hidden');
                });
            });
        </script>
    </body>
    </html>