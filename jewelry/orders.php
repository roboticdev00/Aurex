<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['User_ID'])) {
    header('Location: login.php');
    exit;
}

require_once 'db_connect.php';
require_once 'utilities.php';

 $user_id = $_SESSION['User_ID'];
 $userName = $_SESSION['Name'] ?? 'Customer';
 $greetingName = htmlspecialchars($userName);

// Default filter to 'To Pay' if no status is set
 $status_filter = $_GET['status'] ?? 'To Pay';

 $orders = [];
 $error_message = '';
 $order_counts = [
    'All' => 0,
    'To Pay' => 0,
    'To Ship' => 0,
    'To Receive' => 0,
    'Completed' => 0,
    'Cancelled' => 0,
    'View Ratings' => 0
];

try {
    // Get counts for each status
    $stmt_counts = $pdo->prepare("
        SELECT Status, COUNT(*) as count
        FROM orders
        WHERE User_ID = :user_id
        GROUP BY Status
    ");
    $stmt_counts->execute([':user_id' => $user_id]);
    $counts = $stmt_counts->fetchAll(PDO::FETCH_KEY_PAIR);

    // Map database status to display status
    $status_mapping = [
        'Pending' => 'To Pay',
        'Processing' => 'To Ship',
        'Shipped' => 'To Receive',
        'Delivered' => 'Completed',
        'Cancelled' => 'Cancelled'
    ];

    foreach ($counts as $db_status => $count) {
        $display_status = $status_mapping[$db_status] ?? $db_status;
        $order_counts[$display_status] = $count;
        $order_counts['All'] += $count;
    }

    // Get count of orders with ratings
    $stmt_rating_count = $pdo->prepare("
        SELECT COUNT(DISTINCT o.Order_ID) as count
        FROM orders o
        INNER JOIN order_ratings r ON o.Order_ID = r.Order_ID
        WHERE o.User_ID = :user_id
    ");
    $stmt_rating_count->execute([':user_id' => $user_id]);
    $rating_count = $stmt_rating_count->fetch(PDO::FETCH_ASSOC);
    $order_counts['View Ratings'] = $rating_count['count'];
    
    // Subtract rated orders from completed count
    $order_counts['Completed'] -= $order_counts['View Ratings'];

    // Fetch user's orders based on filter
    if ($status_filter === 'View Ratings') {
        // Special query for View Ratings - get all orders with ratings
        $stmt = $pdo->prepare("
            SELECT o.Order_ID, o.Order_Date, o.Total_Amount, o.Status, o.Shipping_Address, o.Phone_Number, o.Email,
            r.Rating, r.Review_Text, r.Review_Image, r.Rating_Date, p.Name as ProductName, p.Product_ID
            FROM orders o
            INNER JOIN order_ratings r ON o.Order_ID = r.Order_ID
            INNER JOIN product p ON r.Product_ID = p.Product_ID
            WHERE o.User_ID = :user_id
            ORDER BY r.Rating_Date DESC
        ");
        $stmt->execute([':user_id' => $user_id]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Reverse map display status to database status
        $reverse_mapping = array_flip($status_mapping);
        // Ensure we use the correct DB status for the selected filter.
        $db_status_to_filter = $reverse_mapping[$status_filter] ?? $status_filter;

        // Query runs for the specific status filter (e.g., 'To Pay', 'To Ship', etc.)
        // For Completed status, exclude orders that have ratings
        if ($status_filter === 'Completed') {
            $stmt = $pdo->prepare("
                SELECT o.Order_ID, o.Order_Date, o.Total_Amount, o.Status, o.Shipping_Address, o.Phone_Number, o.Email
                FROM orders o
                WHERE o.User_ID = :user_id AND o.Status = :status
                AND o.Order_ID NOT IN (
                    SELECT DISTINCT Order_ID FROM order_ratings WHERE User_ID = :user_id
                )
                ORDER BY o.Order_Date DESC
            ");
        } else {
            $stmt = $pdo->prepare("
                SELECT o.Order_ID, o.Order_Date, o.Total_Amount, o.Status, o.Shipping_Address, o.Phone_Number, o.Email
                FROM orders o
                WHERE o.User_ID = :user_id AND o.Status = :status
                ORDER BY o.Order_Date DESC
            ");
        }
        $stmt->execute([':user_id' => $user_id, ':status' => $db_status_to_filter]);

        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // For each order, check if it has a rating
        foreach ($orders as &$order) {
            $stmt_rating = $pdo->prepare("
                SELECT r.Rating, r.Review_Text, r.Review_Image, r.Rating_Date, p.Name as ProductName, p.Product_ID
                FROM order_ratings r
                INNER JOIN product p ON r.Product_ID = p.Product_ID
                WHERE r.Order_ID = :order_id
            ");
            $stmt_rating->execute([':order_id' => $order['Order_ID']]);
            $rating_data = $stmt_rating->fetch(PDO::FETCH_ASSOC);
            $order['Rating'] = $rating_data ? $rating_data['Rating'] : null;
            $order['Review_Text'] = $rating_data ? $rating_data['Review_Text'] : null;
            $order['Review_Image'] = $rating_data ? $rating_data['Review_Image'] : null;
            $order['Rating_Date'] = $rating_data ? $rating_data['Rating_Date'] : null;
            $order['ProductName'] = $rating_data ? $rating_data['ProductName'] : null;
            $order['Product_ID'] = $rating_data ? $rating_data['Product_ID'] : null;
        }
    }

} catch (PDOException $e) {
    error_log("Orders Fetch Error: " . $e->getMessage());
    $error_message = "Failed to load orders: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Jewelry</title>

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
            background-image: radial-gradient(at 80% 40%, #ffffff 0%, #FBF9F6 60%);
            scroll-behavior: smooth;
        }

        .order-card {
            border-radius: 1.25rem;
            border: 2px solid #E5E7EB;
            background: linear-gradient(to bottom right, #ffffff, #fefefe);
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            padding: 1.1rem 0.8rem !important;
            font-size: 0.97rem;
            width: 100%;
            margin: 2rem 0; 
            display: flex;
            flex-direction: column;
        }

        .order-card-content {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding-bottom: 0.5rem;
        }

        .order-card .order-total-box {
            padding: 0.7rem 0.5rem !important;
            font-size: 1rem !important;
            border-radius: 0.8rem;
        }

        .order-card .order-total-box p.text-5xl {
            font-size: 1.2rem !important;
            font-weight: 800;
        }

        .cancel-order-btn {
            padding: 0.7rem 1.2rem !important;
            font-size: 1rem !important;
            border-radius: 0.7rem !important;
            font-weight: 700 !important;
            background: #ef4444 !important;
            color: #fff !important;
            border: 2px solid #b91c1c !important;
            box-shadow: 0 2px 8px rgba(239,68,68,0.08);
        }

        .cancel-order-btn:hover {
            background: #b91c1c !important;
            color: #fff !important;
        }

        .rate-order-btn {
            padding: 0.7rem 1.2rem !important;
            font-size: 1rem !important;
            border-radius: 0.7rem !important;
            font-weight: 700 !important;
            background: #f59e0b !important;
            color: #fff !important;
            border: 2px solid #d97706 !important;
            box-shadow: 0 2px 8px rgba(245,158,11,0.08);
        }

        .rate-order-btn:hover {
            background: #d97706 !important;
            color: #fff !important;
        }

        .edit-rating-btn {
            padding: 0.7rem 1.2rem !important;
            font-size: 1rem !important;
            border-radius: 0.7rem !important;
            font-weight: 700 !important;
            background: #3b82f6 !important;
            color: #fff !important;
            border: 2px solid #2563eb !important;
            box-shadow: 0 2px 8px rgba(59,130,246,0.08);
        }

        .edit-rating-btn:hover {
            background: #2563eb !important;
            color: #fff !important;
        }

        .view-rating-btn {
            padding: 0.7rem 1.2rem !important;
            font-size: 1rem !important;
            border-radius: 0.7rem !important;
            font-weight: 700 !important;
            background: #10b981 !important;
            color: #fff !important;
            border: 2px solid #059669 !important;
            box-shadow: 0 2px 8px rgba(16,185,129,0.08);
        }

        .view-rating-btn:hover {
            background: #059669 !important;
            color: #fff !important;
        }

        .star-rating {
            color: #fbbf24;
            font-size: 1.2rem;
            display: inline-flex;
            align-items: center;
            margin-left: 0.5rem;
        }

        .star-rating .empty {
            color: #d1d5db;
        }

        .order-card .info-badge {
            background: linear-gradient(135deg, #8B7B61 0%, #4D4C48 100%);
            color: #fff;
            padding: 0.28rem 0.9rem;
            border-radius: 0.6rem;
            font-size: 1.02rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            box-shadow: 0 2px 8px rgba(139,123,97,0.08);
            margin-bottom: 0.5rem;
        }

        @media (max-width: 640px) {
            .order-card {
                padding: 0.6rem 0.2rem !important;
                font-size: 0.93rem;
            }
            .order-card .info-badge {
                font-size: 0.93rem;
                padding: 0.15rem 0.6rem;
            }
        }

        .modal-panel {
            max-width: 500px !important;
            padding: 1.2rem 0.7rem !important;
        }

        .order-card:hover {
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
            transform: translateY(-4px);
            border-color: #8B7B61;
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

        .tab-button {
            position: relative;
            padding: 1.25rem 2rem;
            color: #7A7977;
            font-weight: 600;
            transition: all 0.2s;
            border-bottom: 4px solid transparent;
            white-space: nowrap;
        }

        .tab-button:hover {
            color: #ef4444;
            background-color: rgba(239, 68, 68, 0.08);
        }

        .tab-button.active {
            color: #ef4444;
            border-bottom-color: #ef4444;
            font-weight: 700;
            background-color: rgba(239, 68, 68, 0.05);
        }

        .tab-count {
            display: inline-block;
            margin-left: 0.75rem;
            padding: 0.25rem 0.75rem;
            background-color: #ef4444;
            color: white;
            border-radius: 9999px;
            font-size: 0.8125rem;
            font-weight: 700;
            min-width: 1.75rem;
            text-align: center;
        }

        .tab-button:not(.active) .tab-count {
            background-color: #ef4444;
            color: #ffffff;
        }

        .progress-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 2rem 0;
            position: relative;
        }

        .progress-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            position: relative;
            z-index: 2;
        }

        .progress-icon {
            width: 3.5rem;
            height: 3.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #E5E7EB;
            color: #9CA3AF;
            font-weight: 600;
            font-size: 1.5rem;
            margin-bottom: 0.75rem;
            transition: all 0.3s;
            border: 3px solid #E5E7EB;
        }

        .progress-step.active .progress-icon,
        .progress-step.completed .progress-icon {
            background-color: #8B7B61;
            color: white;
            border-color: #8B7B61;
            transform: scale(1.1);
        }

        .progress-line {
            position: absolute;
            top: 1.75rem;
            left: 0;
            right: 0;
            height: 4px;
            background-color: #E5E7EB;
            z-index: 1;
        }

        .progress-line-fill {
            height: 100%;
            background-color: #8B7B61;
            transition: width 0.8s ease;
        }

        .rating-modal {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .star-rating-input {
            display: flex;
            font-size: 3rem;
            margin: 1rem 0;
        }

        .star-rating-input .star {
            cursor: pointer;
            color: #d1d5db;
            transition: color 0.2s;
        }

        .star-rating-input .star:hover,
        .star-rating-input .star.active {
            color: #fbbf24;
        }

        .review-textarea {
            width: 100%;
            min-height: 100px;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            font-family: inherit;
            resize: vertical;
            margin: 1rem 0;
        }

        .submit-rating-btn {
            background-color: #8B7B61;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 0.5rem;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .submit-rating-btn:hover {
            background-color: #4D4C48;
        }

        .product-link {
            color: #8B7B61;
            font-weight: 600;
            text-decoration: underline;
        }

        .product-link:hover {
            color: #4D4C48;
        }

        .review-image {
            max-width: 300px;
            max-height: 300px;
            border-radius: 0.5rem;
            margin-top: 1rem;
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

<body class="antialiased">

    <header class="py-6 sticky top-0 z-40 bg-brand-teal/95 backdrop-blur-sm shadow-md transition-all">
        <nav class="flex justify-between items-center max-w-screen-2xl mx-auto px-4 md:px-8 lg:px-16">
            <a href="index_user.php" class="font-serif text-3xl font-bold text-white">Aurex</a>

            <div class="flex items-center gap-5">
                <a href="index_user.php" class="bg-white text-brand-teal px-6 py-2 rounded-full text-sm font-semibold hover:bg-gray-100 transition-colors shadow-md">
                    Back to Home
                </a>
            </div>
        </nav>
    </header>

    <div class="max-w-screen-2xl mx-auto px-4 md:px-8 lg:px-16 py-8">

        <div class="mb-10">
            <h1 class="font-serif text-5xl md:text-6xl font-bold text-brand-dark mb-3 tracking-tight">My Orders</h1>
            <p class="text-brand-subtext text-lg">Track and manage all your jewelry orders</p>
        </div>

        <?php if ($error_message): ?>
            <div class="bg-red-50 border-2 border-red-300 text-red-900 px-6 py-5 rounded-xl mb-8 flex items-start gap-3 shadow-sm">
                <svg class="w-6 h-6 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                <span class="font-semibold"><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-2xl shadow-lg mb-8 overflow-hidden border-2 border-gray-100">
            <div class="flex overflow-x-auto">
                <a href="?status=To Pay" class="tab-button <?php echo $status_filter === 'To Pay' ? 'active' : ''; ?>">
                    To Pay
                    <?php if ($order_counts['To Pay'] > 0): ?>
                        <span class="tab-count"><?php echo $order_counts['To Pay']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="?status=To Ship" class="tab-button <?php echo $status_filter === 'To Ship' ? 'active' : ''; ?>">
                    To Ship
                    <?php if ($order_counts['To Ship'] > 0): ?>
                        <span class="tab-count"><?php echo $order_counts['To Ship']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="?status=To Receive" class="tab-button <?php echo $status_filter === 'To Receive' ? 'active' : ''; ?>">
                    To Receive
                    <?php if ($order_counts['To Receive'] > 0): ?>
                        <span class="tab-count"><?php echo $order_counts['To Receive']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="?status=Completed" class="tab-button <?php echo $status_filter === 'Completed' ? 'active' : ''; ?>">
                    Completed
                    <?php if ($order_counts['Completed'] > 0): ?>
                        <span class="tab-count"><?php echo $order_counts['Completed']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="?status=Cancelled" class="tab-button <?php echo $status_filter === 'Cancelled' ? 'active' : ''; ?>">
                    Cancelled
                    <?php if ($order_counts['Cancelled'] > 0): ?>
                        <span class="tab-count"><?php echo $order_counts['Cancelled']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="?status=View Ratings" class="tab-button <?php echo $status_filter === 'View Ratings' ? 'active' : ''; ?>">
                    View Ratings
                    <?php if ($order_counts['View Ratings'] > 0): ?>
                        <span class="tab-count"><?php echo $order_counts['View Ratings']; ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>

        <?php if (empty($orders)): ?>
            <div class="bg-white rounded-2xl shadow-lg p-20 text-center border-2 border-gray-100">
                <svg class="w-40 h-40 mx-auto text-gray-300 mb-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
                <p class="text-3xl font-bold text-brand-dark mb-4">No Orders Found</p>
                <p class="text-brand-subtext text-lg mb-10 max-w-md mx-auto">
                    <?php 
                    if ($status_filter === 'To Pay' && $order_counts['All'] == 0) {
                        echo "You haven't placed any orders yet. Start shopping now!";
                    } elseif ($status_filter === 'View Ratings') {
                        echo "You haven't rated any orders yet.";
                    } else {
                        echo "You don't have any orders currently under the status: <strong>" . htmlspecialchars($status_filter) . "</strong>";
                    }
                    ?>
                </p>
            </div>
        <?php else: ?>

            <div class="space-y-6">
                <?php foreach ($orders as $order): 
                    $display_status = getStatusDisplay($order['Status']);
                ?>
                    <div class="order-card p-8">
                        <div class="order-card-content"> 
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between pb-6 border-b-2 border-gray-200 mb-6">
                                <div class="mb-4 md:mb-0">
                                    <span class="info-badge">Order #<?php echo htmlspecialchars($order['Order_ID']); ?></span>
                                    <p class="text-sm text-brand-subtext font-medium">📅 <?php echo date('F d, Y \a\t h:i A', strtotime($order['Order_Date'])); ?></p>
                                    <?php if ($status_filter === 'View Ratings' && isset($order['ProductName'])): ?>
                                        <p class="text-sm text-brand-subtext font-medium mt-1">
                                            Product: <a href="index_user.php?product_id=<?php echo $order['Product_ID']; ?>#section-products" class="product-link"><?php echo htmlspecialchars($order['ProductName']); ?></a>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <?php echo getStatusBadge($order['Status']); ?>
                                    <?php if ($order['Rating']): ?>
                                        <div class="star-rating mt-2">
                                            <?php 
                                            for ($i = 1; $i <= 5; $i++) {
                                                if ($i <= $order['Rating']) {
                                                    echo '★';
                                                } else {
                                                    echo '<span class="empty">★</span>';
                                                }
                                            }
                                            ?>
                                            <span class="text-sm text-brand-subtext ml-2"><?php echo $order['Status'] === 'Cancelled' ? 'Cancellation Experience' : 'Your Rating'; ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8"> 
                                <div class="lg:col-span-2">
                                    <h3 class="text-lg font-bold text-brand-dark mb-4 flex items-center">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        </svg>
                                        Shipping Information
                                    </h3>
                                    <div class="bg-gradient-to-br from-brand-beige to-white p-6 rounded-xl border-2 border-gray-200 shadow-sm min-h-[150px]"> 
                                        <div class="space-y-3">
                                            <p class="text-base text-brand-dark font-semibold flex items-start">
                                                <svg class="w-5 h-5 mr-3 mt-0.5 flex-shrink-0 text-brand-teal" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                                </svg>
                                                <span><?php echo htmlspecialchars($order['Shipping_Address']); ?></span>
                                            </p>
                                            <p class="text-base text-brand-subtext font-medium flex items-center">
                                                <svg class="w-5 h-5 mr-3 flex-shrink-0 text-brand-teal" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                                </svg>
                                                <?php echo htmlspecialchars($order['Phone_Number']); ?>
                                            </p>
                                            <p class="text-base text-brand-subtext font-medium flex items-center">
                                                <svg class="w-5 h-5 mr-3 flex-shrink-0 text-brand-teal" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                                </svg>
                                                <?php echo htmlspecialchars($order['Email']); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <h3 class="text-lg font-bold text-brand-dark mb-4 flex items-center">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        Order Total
                                    </h3>
                                    <div class="order-total-box bg-gradient-to-br from-brand-teal via-brand-dark to-black p-8 rounded-xl text-white shadow-xl transform hover:scale-105 transition-all">
                                        <p class="text-sm opacity-90 mb-3 font-semibold tracking-wide">TOTAL AMOUNT</p>
                                        <p class="text-5xl font-black tracking-tight">₱<?php echo number_format($order['Total_Amount'], 2); ?></p>
                                    </div>
                                </div>
                            </div> 
                        </div> 
                        <div class="pt-6 border-t-2 border-gray-200">
                            <div class="flex flex-wrap gap-4">
                                <?php if ($order['Status'] === 'Shipped'): ?>
                                    <button class="confirm-receive-btn flex-1 md:flex-none px-8 py-3.5 rounded-xl font-bold text-base border-3 border-green-500 text-green-700 bg-green-50 hover:bg-green-500 hover:text-white transition-all shadow-lg transform hover:scale-105" data-order-id="<?php echo $order['Order_ID']; ?>">
                                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Order Received
                                    </button>
                                <?php endif; ?>

                                <?php if ($order['Status'] === 'Delivered'): ?>
                                    <?php if ($order['Rating']): ?>
                                        <button class="edit-rating-btn flex-1 md:flex-none px-8 py-3.5 rounded-xl font-bold text-base transition-all shadow-lg transform hover:scale-105" data-order-id="<?php echo $order['Order_ID']; ?>">
                                            <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v10a2 2 0 002 2h7a2 2 0 002-2V7a2 2 0 00-2-2z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 9l2 2m0 0l4-4m0 0l4 4"></path>
                                            </svg>
                                            Edit Rating
                                        </button>
                                    <?php else: ?>
                                        <button class="rate-order-btn flex-1 md:flex-none px-8 py-3.5 rounded-xl font-bold text-base transition-all shadow-lg transform hover:scale-105" data-order-id="<?php echo $order['Order_ID']; ?>">
                                            <svg class="w-5 h-5 inline mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                            </svg>
                                            Rate Order
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php if ($order['Status'] === 'Pending'): ?>
                                    <button class="cancel-order-btn flex-1 md:flex-none px-8 py-3.5 rounded-xl font-bold text-base border-3 border-red-500 text-red-700 bg-red-50 hover:bg-red-500 hover:text-white transition-all shadow-lg transform hover:scale-105" data-order-id="<?php echo $order['Order_ID']; ?>">
                                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                        Cancel Order
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($status_filter === 'View Ratings'): ?>
                                    <a href="view_rating.php?order_id=<?php echo $order['Order_ID']; ?>" class="view-rating-btn flex-1 md:flex-none px-8 py-3.5 rounded-xl font-bold text-base transition-all shadow-lg transform hover:scale-105 inline-flex items-center justify-center">
                                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                        View Rating
                                    </a>
                
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>

    </div>

    <div id="order-details-modal" class="modal hidden fixed inset-0 bg-black bg-opacity-70 backdrop-blur-md z-50 flex items-center justify-center p-4">
        <div class="modal-panel relative w-full max-w-4xl bg-white rounded-2xl shadow-2xl p-10 max-h-[90vh] overflow-y-auto border-4 border-brand-teal">
            <button id="close-modal-btn" class="absolute top-6 right-6 text-gray-400 hover:text-gray-800 transition-colors">
                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>

            <h2 class="text-4xl font-serif font-black text-brand-dark mb-8">📦 Order Details</h2>

            <div id="modal-content" class="space-y-6">
            </div>

            <button id="close-modal-btn-bottom" class="w-full mt-8 bg-brand-teal text-white py-4 rounded-xl text-lg font-bold hover:bg-brand-dark transition-all shadow-lg transform hover:scale-105">
                Close
            </button>
        </div>
    </div>

    <!-- Rating Modal -->
    <div id="rating-modal" class="modal hidden fixed inset-0 bg-black bg-opacity-70 backdrop-blur-md z-50 flex items-center justify-center p-4">
        <div class="modal-panel relative w-full max-w-lg bg-white rounded-2xl shadow-2xl p-8 border-4 border-amber-500">
            <button id="close-rating-modal-btn" class="absolute top-6 right-6 text-gray-400 hover:text-gray-800 transition-colors">
                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>

            <h2 class="text-3xl font-serif font-black text-brand-dark mb-6">Rate Your Order</h2>

            <div class="rating-modal">
                <p class="text-brand-subtext mb-4">How would you rate your experience with this order?</p>
                <div class="star-rating-input" id="star-rating-input">
                    <span class="star" data-rating="1">★</span>
                    <span class="star" data-rating="2">★</span>
                    <span class="star" data-rating="3">★</span>
                    <span class="star" data-rating="4">★</span>
                    <span class="star" data-rating="5">★</span>
                </div>
                
                <textarea id="review-text" class="review-textarea" placeholder="Share your experience with this order (optional)"></textarea>
                
                <!-- Image Upload Section -->
                <div class="mb-4">
                    <label for="review-image" class="block text-sm font-medium text-brand-dark mb-2">Add a Photo (Optional)</label>
                    <div class="flex items-center justify-center w-full">
                        <label for="review-image" class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100">
                            <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                <svg class="w-8 h-8 mb-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                                <p class="mb-2 text-sm text-gray-500"><span class="font-semibold">Click to upload</span> or drag and drop</p>
                                <p class="text-xs text-gray-500">PNG, JPG, GIF up to 10MB</p>
                            </div>
                            <input id="review-image" type="file" class="hidden" accept="image/*" />
                        </label>
                    </div>
                    <div id="image-preview" class="mt-4 hidden">
                        <img id="preview-img" src="" alt="Preview" class="h-32 w-32 object-cover rounded-lg">
                        <button id="remove-image" type="button" class="ml-2 text-red-500 hover:text-red-700">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <button id="submit-rating-btn" class="submit-rating-btn">Submit Rating</button>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="image-modal">
        <span class="close">&times;</span>
        <img class="modal-content" id="img01">
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const orderDetailsModal = document.getElementById('order-details-modal');
            const closeModalBtn = document.getElementById('close-modal-btn');
            const closeModalBtnBottom = document.getElementById('close-modal-btn-bottom');
            const modalContent = document.getElementById('modal-content');
            const ratingModal = document.getElementById('rating-modal');
            const closeRatingModalBtn = document.getElementById('close-rating-modal-btn');
            const starRatingInput = document.getElementById('star-rating-input');
            const reviewText = document.getElementById('review-text');
            const submitRatingBtn = document.getElementById('submit-rating-btn');
            let currentOrderId = null;
            let selectedRating = 0;

            // Image upload handling
            const reviewImageInput = document.getElementById('review-image');
            const imagePreview = document.getElementById('image-preview');
            const previewImg = document.getElementById('preview-img');
            const removeImageBtn = document.getElementById('remove-image');
            let imageData = null;
            
            reviewImageInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        imageData = event.target.result;
                        previewImg.src = imageData;
                        imagePreview.classList.remove('hidden');
                    };
                    reader.readAsDataURL(file);
                }
            });
            
            removeImageBtn.addEventListener('click', function() {
                imageData = null;
                reviewImageInput.value = '';
                imagePreview.classList.add('hidden');
            });

            // Get progress percentage based on status
            function getProgressPercentage(status) {
                const progress = {
                    'Pending': 0,
                    'Processing': 33,
                    'Shipped': 66,
                    'Delivered': 100,
                    'Cancelled': 0
                };
                return progress[status] || 0;
            }

            // Get progress steps HTML
            function getProgressSteps(status) {
                const steps = [
                    { label: 'Order Placed', icon: '📦', db_status: 'Pending' },
                    { label: 'Processing', icon: '⚙️', db_status: 'Processing' },
                    { label: 'Shipped', icon: '🚚', db_status: 'Shipped' },
                    { label: 'Delivered', icon: '✅', db_status: 'Delivered' }
                ];

                const statusOrder = ['Pending', 'Processing', 'Shipped', 'Delivered'];
                const currentIndex = statusOrder.indexOf(status);
                const progress = getProgressPercentage(status);

                let html = '<div class="progress-bar">';
                html += '<div class="progress-line"><div class="progress-line-fill" style="width: ' + progress + '%"></div></div>';

                steps.forEach((step, index) => {
                    const isActive = index === currentIndex;
                    const isCompleted = index < currentIndex;
                    const stateClass = isCompleted ? 'completed' : (isActive ? 'active' : '');

                    html += `
                        <div class="progress-step ${stateClass}">
                            <div class="progress-icon">${step.icon}</div>
                            <span class="text-sm font-bold text-center">${step.label}</span>
                        </div>
                    `;
                });

                html += '</div>';
                return html;
            }

            // View Order Details
            document.querySelectorAll('.view-order-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const orderId = btn.dataset.orderId;
                    window.location.href = `order_tracking.php?order_id=${orderId}`;
                });
            });

            // Confirm Order Received
            document.querySelectorAll('.confirm-receive-btn').forEach(btn => {
                btn.addEventListener('click', async () => {
                    if (!confirm('✅ Confirm that you have received this order?')) return;

                    const orderId = btn.dataset.orderId;
                    btn.disabled = true;
                    btn.textContent = 'Processing...';

                    try {
                        const response = await fetch('confirm_received.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ order_id: orderId })
                        });
                        const data = await response.json();

                        if (data.success) {
                            alert('✅ Order marked as received successfully!');
                            location.reload();
                        } else {
                            alert('❌ Failed to update order: ' + data.message);
                            btn.disabled = false;
                            btn.innerHTML = '<svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>✅ Order Received';
                        }
                    } catch (error) {
                        console.error('Error confirming order:', error);
                        alert('⚠️ An error occurred');
                        btn.disabled = false;
                        btn.innerHTML = '<svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>✅ Order Received';
                    }
                });
            });

            // Cancel Order
            document.querySelectorAll('.cancel-order-btn').forEach(btn => {
                btn.addEventListener('click', async () => {
                    if (!confirm('⚠️ Are you sure you want to cancel this order? This action cannot be undone.')) return;

                    const orderId = btn.dataset.orderId;
                    btn.disabled = true;
                    btn.textContent = 'Cancelling...';

                    try {
                        const response = await fetch('cancel_order.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ order_id: orderId })
                        });
                        const data = await response.json();

                        if (data.success) {
                            alert('✅ Order cancelled successfully!');
                            location.reload();
                        } else {
                            alert('❌ Failed to cancel order: ' + data.message);
                            btn.disabled = false;
                            btn.innerHTML = '<svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>❌ Cancel Order';
                        }
                    } catch (error) {
                        console.error('Error cancelling order:', error);
                        alert('⚠️ An error occurred while cancelling the order');
                        btn.disabled = false;
                        btn.innerHTML = '<svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>❌ Cancel Order';
                    }
                });
            });

            // Rate Order
            document.querySelectorAll('.rate-order-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    currentOrderId = btn.dataset.orderId;
                    ratingModal.classList.remove('hidden');
                    selectedRating = 0;
                    reviewText.value = '';
                    imageData = null;
                    imagePreview.classList.add('hidden');
                    reviewImageInput.value = '';
                    updateStarDisplay();
                });
            });

            // Edit Rating
            document.querySelectorAll('.edit-rating-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const orderId = btn.dataset.orderId;
                    window.location.href = `edit_rating.php?order_id=${orderId}`;
                });
            });

            // Star rating interaction
            document.querySelectorAll('.star').forEach(star => {
                star.addEventListener('click', () => {
                    selectedRating = parseInt(star.dataset.rating);
                    updateStarDisplay();
                });
            });

            function updateStarDisplay() {
                document.querySelectorAll('.star').forEach((star, index) => {
                    if (index < selectedRating) {
                        star.classList.add('active');
                    } else {
                        star.classList.remove('active');
                    }
                });
            }

            // Submit rating
            submitRatingBtn.addEventListener('click', async () => {
                if (selectedRating === 0) {
                    alert('Please select a rating before submitting.');
                    return;
                }
                
                submitRatingBtn.disabled = true;
                submitRatingBtn.textContent = 'Submitting...';
                
                try {
                    const response = await fetch('submit_rating.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ 
                            order_id: currentOrderId, 
                            rating: selectedRating, 
                            review_text: reviewText.value,
                            review_image: imageData
                        })
                    });
                    const data = await response.json();
                    
                    if (data.success) {
                        alert('✅ Thank you for your rating!');
                        ratingModal.classList.add('hidden');
                        location.reload();
                    } else {
                        alert('❌ Failed to submit rating: ' + data.message);
                        submitRatingBtn.disabled = false;
                        submitRatingBtn.textContent = 'Submit Rating';
                    }
                } catch (error) {
                    console.error('Error submitting rating:', error);
                    alert('⚠️ An error occurred while submitting your rating');
                    submitRatingBtn.disabled = false;
                    submitRatingBtn.textContent = 'Submit Rating';
                }
            });

            // Close modals
            closeModalBtn.addEventListener('click', () => {
                orderDetailsModal.classList.add('hidden');
            });

            closeModalBtnBottom.addEventListener('click', () => {
                orderDetailsModal.classList.add('hidden');
            });

            closeRatingModalBtn.addEventListener('click', () => {
                ratingModal.classList.add('hidden');
            });

            orderDetailsModal.addEventListener('click', (e) => {
                if (e.target === orderDetailsModal) {
                    orderDetailsModal.classList.add('hidden');
                }
            });

            ratingModal.addEventListener('click', (e) => {
                if (e.target === ratingModal) {
                    ratingModal.classList.add('hidden');
                }
            });

            // Image Modal functionality
            const modal = document.getElementById('imageModal');
            const modalImg = document.getElementById("img01");
            const closeBtn = document.getElementsByClassName("close")[0];
            
            window.openImageModal = function(src) {
                modal.style.display = "block";
                modalImg.src = src;
            }
            
            closeBtn.onclick = function() {
                modal.style.display = "none";
            }
            
            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = "none";
                }
            }
        });
    </script>
</body>
</html>