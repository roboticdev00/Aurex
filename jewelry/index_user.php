<?php
// index_user.php

session_start();
// Check if user is logged in (optional for browsing)
 $isLoggedIn = isset($_SESSION['User_ID']);
 $userName = $_SESSION['Name'] ?? '';
 $userId = $_SESSION['User_ID'] ?? null;
 $userRole = $_SESSION['Role'] ?? 'customer';

// Split the name for the greeting
 $greetingName = htmlspecialchars($userName);

// --- START: PRODUCT DATA FETCH (Database Connection and Filtering) ---
require_once 'db_connect.php';

 $dynamic_products = [];
 $category_counts = [];
 $all_categories = [];
 $error_message = '';
 $category_filter_name = $_GET['cat'] ?? null;
 $bind_value = null;
 $product_section_title = 'Bestseller Products'; // Default Title

// Check if a specific product is requested
 $specific_product_id = $_GET['product_id'] ?? null;

// Fetch order count for logged-in users
 $order_count = 0;
 if ($isLoggedIn) {
    try {
        $stmt_order_count = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM orders
            WHERE User_ID = :user_id
        ");
        $stmt_order_count->execute([':user_id' => $userId]);
        $order_result = $stmt_order_count->fetch(PDO::FETCH_ASSOC);
        $order_count = $order_result['count'] ?? 0;
    } catch (PDOException $e) {
        error_log("Order Count Error: " . $e->getMessage());
        // Keep order_count as 0 if there's an error
    }
 }

try {
    // If a specific product is requested, fetch that product
    if ($specific_product_id) {
        $stmt_product = $pdo->prepare("
            SELECT 
                p.Product_ID, 
                p.Name AS ProductName, 
                p.Price, 
                p.Images, 
                p.Stock,
                p.Avg_Rating,
                p.Rating_Count,
                p.Description,
                c.Category_Name
            FROM product p
            LEFT JOIN category c ON p.Category_ID = c.Category_ID
            WHERE p.Product_ID = :product_id
        ");
        $stmt_product->execute([':product_id' => $specific_product_id]);
        $product = $stmt_product->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            $dynamic_products = [$product];
            $product_section_title = $product['ProductName'];
        }
    } else {
        // Normal product fetching logic
        $sql_select = "
        SELECT 
            p.Product_ID, 
            p.Name AS ProductName, 
            p.Price, 
            p.Images, 
            p.Stock,
            p.Avg_Rating,
            p.Rating_Count,
            p.Description,
            c.Category_Name
        FROM product p
        LEFT JOIN category c ON p.Category_ID = c.Category_ID
        WHERE (p.Availability = 'In Stock' OR p.Availability = 'Low Stock')
        ";

        $sql_where = "";
        if ($category_filter_name) {
            $product_section_title = htmlspecialchars($category_filter_name) . ' Collection';
            $bind_value = $category_filter_name;
            $sql_where = " AND c.Category_Name = :cat_name";
        }

        // Using ORDER BY p.Product_ID DESC LIMIT 16 for a simple bestseller/latest logic
        $sql_order = " ORDER BY p.Product_ID DESC LIMIT 16";

        $final_sql = $sql_select . $sql_where . $sql_order;

        $stmt_products = $pdo->prepare($final_sql);

        if ($category_filter_name) {
            $stmt_products->bindParam(':cat_name', $bind_value);
        }

        $stmt_products->execute();
        $dynamic_products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);
    }

    // 2. Fetch all unique category names and store them for the collection section
    $stmt_all_categories = $pdo->query("
    SELECT Category_Name 
    FROM category
  ");
    $all_categories = $stmt_all_categories->fetchAll(PDO::FETCH_COLUMN);

    // 3. Fetch Category Counts for the Collection Section
    $stmt_counts = $pdo->query("
    SELECT c.Category_Name, COUNT(p.Product_ID) as count 
    FROM category c 
    LEFT JOIN product p ON p.Category_ID = c.Category_ID
    GROUP BY c.Category_Name
    HAVING COUNT(p.Product_ID) > 0
  ");
    $category_counts = $stmt_counts->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    error_log("Product Fetch Error: " . $e->getMessage());
    $error_message = "Failed to load jewelry products due to a database error.";
}
// --- END: PRODUCT DATA FETCH ---
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jewelery - Find Your Perfect Piece</title>

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

        #cart-modal {
            transition: opacity 0.3s ease-in-out;
        }

        #cart-panel {
            transition: transform 0.3s ease-in-out;
        }

        #cart-modal.hidden {
            opacity: 0;
            pointer-events: none;
        }

        #cart-modal:not(.hidden) #cart-panel {
            transform: translateX(0);
        }

        /* Modal Animations */
        .modal {
            animation: modalFadeIn 0.3s ease-out forwards;
        }

        .modal.hidden {
            animation: modalFadeOut 0.3s ease-in forwards;
        }

        .modal-panel {
            animation: modalSlideIn 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
            transform: translateY(100px) scale(0.8);
            opacity: 0;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal.hidden .modal-panel {
            animation: modalSlideOut 0.4s ease-in forwards;
        }

        .modal-panel .faded-background {
            min-height: 500px;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @keyframes modalFadeOut {
            from {
                opacity: 1;
            }
            to {
                opacity: 0;
            }
        }

        @keyframes modalSlideIn {
            0% {
                transform: translateY(100px) scale(0.8);
                opacity: 0;
            }
            60% {
                transform: translateY(-10px) scale(1.02);
                opacity: 0.8;
            }
            100% {
                transform: translateY(0) scale(1);
                opacity: 1;
            }
        }

        @keyframes modalSlideOut {
            from {
                transform: translateY(0) scale(1);
                opacity: 1;
            }
            to {
                transform: translateY(50px) scale(0.95);
                opacity: 0;
            }
        }

        .play-button {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 80px;
            height: 80px;
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease;
        }

        .play-button:hover {
            transform: translate(-50%, -50%) scale(1.1);
            background-color: white;
        }

        .play-button-icon {
            width: 0;
            height: 0;
            border-top: 14px solid transparent;
            border-bottom: 14px solid transparent;
            border-left: 22px solid #8B7B61;
            margin-left: 6px;
        }

        .shop-now-link {
            position: relative;
            text-decoration: none;
            padding-bottom: 4px;
        }

        .shop-now-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: #4D4C48;
            transition: width 0.3s ease;
        }

        .shop-now-link:hover::after {
            width: 0%;
        }

        .cart-item-checkbox {
            width: 1.25rem;
            height: 1.25rem;
            border-radius: 0.25rem;
            border: 2px solid #D1D5DB;
            transition: all 0.2s;
            flex-shrink: 0;
            appearance: none;
            cursor: pointer;
        }

        .cart-item-checkbox:checked {
            background-color: #8B7B61;
            border-color: #8B7B61;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='white'%3E%3Cpath fill-rule='evenodd' d='M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z' clip-rule='evenodd' /%3E%3C/svg%3E");
        }

        .btn-secondary {
            background-color: white;
            color: #4D4C48;
            border: 2px solid #4D4C48;
            transition: all 0.2s;
        }

        .btn-secondary:hover {
            background-color: #4D4C48;
            color: white;
        }

        .btn-primary {
            background-color: #8B7B61;
            color: white;
            border: 2px solid #8B7B61;
            transition: all 0.2s;
        }

        .btn-primary:hover {
            background-color: #7a6c54;
            border-color: #7a6c54;
        }

        #quantity-input[type=number]::-webkit-inner-spin-button,
        #quantity-input[type=number]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        #quantity-input::-webkit-outer-spin-button,
        #quantity-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0; 
        }

        #quantity-input[type=number] {
            -moz-appearance: textfield; 
            appearance: textfield;
        }

        #quantity-decrease,
        #quantity-increase {
            transition: background-color 0.2s;
        }

        #quantity-decrease:hover,
        #quantity-increase:hover {
            background-color: #e0e0e0;
        }

        .cart-quantity-btn {
            width: 1.75rem;
            height: 1.75rem;
            border-radius: 0.25rem;
            font-weight: 500;
            transition: background-color 0.2s;
        }

        .cart-quantity-btn:hover {
            background-color: #e0e0e0;
        }

        #mobile-menu a {
            position: relative;
            padding-bottom: 4px;
        }

        #mobile-menu a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background-color: #8B7B61;
            transition: width 0.3s ease;
        }

        #mobile-menu a:hover::after {
            width: 100%;
        }

        footer a {
            transition: color 0.2s;
        }

        .product-card {
            border-radius: 1rem;
            overflow: hidden;
            border: 1px solid #F3F4F6;
            background-color: #ffffff;
        }

        .product-card-image-wrapper {
            overflow: hidden;
            border-bottom: 1px solid #F3F4F6;
        }

        #toast-notification {
            opacity: 0;
            transform: translate(-50%, 20px);
            pointer-events: none;
            transition: all 0.3s ease-in-out;
        }

        #toast-notification.show {
            opacity: 1;
            transform: translate(-50%, 0);
            pointer-events: auto;
        }

        @media (max-width: 1023px) {
            #mobile-menu {
                position: absolute;
                top: 80px;
                left: 0;
                right: 0;
                background-color: white;
                box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
                padding: 1rem 2rem;
                flex-direction: column;
                gap: 1rem;
                z-index: 40;
            }
        }

        /* Faded background for modal left side */
        .faded-background {
            background-image: radial-gradient(at 100% 0%, rgba(139, 123, 97, 0.63) 0%, transparent 100%),
                radial-gradient(at 0% 100%, #FBF9F6 0%, #FBF9F6 100%);
            background-color: #FBF9F6;
        }

        /* Single category color for all categories */
        .category-circle {
            background-color: #8B7B61;
            border-color: #8B7B61;
        }

        /* Hero section background */
        .hero-bg {
            background-image: url('https://image2url.com/images/1765726949322-65c74010-1306-4b5b-82c8-9f68f47952cc.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            position: relative;
            width: 100vw;
            margin-left: calc(-50vw + 50%);
        }

        .shop-now-btn {
            background: linear-gradient(135deg, #8B7B61 0%, #7a6c54 100%);
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(139, 123, 97, 0.3);
        }

        .shop-now-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(139, 123, 97, 0.4);
        }
        
        /* Inline error message styles */
        .error-message {
            color: #dc2626;
            font-size: 0.875rem;
            margin-top: 0.25rem;
            display: none;
        }
        
        .error-message.show {
            display: block;
        }
        
        .input-error {
            border-color: #dc2626 !important;
        }
        
        /* Product rating display */
        .product-rating {
            display: flex;
            align-items: center;
            margin-top: 0.5rem;
        }
        
        .product-rating .stars {
            color: #fbbf24;
            font-size: 1rem;
            margin-right: 0.5rem;
        }
        
        .product-rating .stars .empty {
            color: #d1d5db;
        }
        
        .product-rating .rating-count {
            font-size: 0.875rem;
            color: #7A7977;
        }
        
        /* Product details modal */
        .product-details-modal {
            max-width: 800px;
        }
        
        .product-details-image {
            max-height: 400px;
            object-fit: contain;
        }
        
        .product-reviews {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .review-item {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .review-item:last-child {
            border-bottom: none;
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .review-rating {
            color: #fbbf24;
        }
        
        .review-date {
            font-size: 0.875rem;
            color: #7A7977;
        }
        
        .review-text {
            margin-top: 0.5rem;
        }
    </style>
</head>

<body class="antialiased">

    <div id="cart-modal"
        class="modal hidden fixed inset-0 bg-black bg-opacity-60 z-50 flex justify-end">
        <div id="cart-backdrop" class="absolute inset-0"></div>
        <div id="cart-panel"
            class="relative w-full max-w-md h-full bg-white shadow-xl transform translate-x-full flex flex-col">
            <div class="flex justify-between items-center p-6 border-b">
                <h2 class="text-2xl font-serif font-bold text-brand-dark">Your Cart</h2>
                <button id="close-cart-btn" class="text-gray-500 hover:text-gray-900">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>
            <div id="cart-items" class="flex-grow p-6 overflow-y-auto space-y-4">
                <p id="cart-empty-msg" class="text-brand-subtext">Your cart is empty.</p>
            </div>
            <div class="p-6 border-t bg-gray-50">
                <div class="flex justify-between items-center mb-4">
                    <span class="text-lg font-semibold text-brand-dark">Subtotal</span>
                    <span id="cart-total" class="text-xl font-bold text-brand-dark">₱0.00</span>
                </div>
                <button id="checkout-btn"
                    class="w-full bg-brand-teal text-white py-3 rounded-full font-semibold hover:bg-opacity-90 transition-colors">
                    Continue to Checkout
                </button>
            </div>
        </div>
    </div>

    <div id="video-modal"
        class="modal hidden fixed inset-0 bg-black bg-opacity-70 z-50 flex items-center justify-center p-4">
        <div class="modal-panel relative w-full max-w-3xl bg-black rounded-lg shadow-xl overflow-hidden">
            <button id="close-video-modal-btn"
                class="absolute -top-10 right-0 text-white text-4xl opacity-80 hover:opacity-100">&times;</button>
            <div class="aspect-w-16 aspect-h-9">
                <iframe id="video-iframe" src="https://www.youtube.com/embed/nVOpm-f1vuA?si=m-T0iFq31lDPMi-7"
                    title="YouTube video player" frameborder="0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen>
                </iframe>
            </div>
        </div>
    </div>

    <div id="message-modal"
        class="modal hidden fixed inset-0 bg-black bg-opacity-60 z-50 flex items-center justify-center p-4">
        <div class="modal-panel relative w-full max-w-sm bg-white rounded-lg shadow-xl p-6 text-center">
            <h3 id="message-modal-title" class="text-2xl font-serif font-bold text-brand-dark mb-3">Success!</h3>
            <p id="message-modal-text" class="text-brand-subtext mb-6">Your message goes here.</p>
            <button id="close-message-modal-btn"
                class="w-full bg-brand-teal text-white py-2.5 rounded-full font-semibold hover:bg-opacity-90 transition-colors">
                Got it
            </button>
        </div>
    </div>

    <div id="quantity-modal"
        class="modal hidden fixed inset-0 bg-black bg-opacity-60 z-50 flex items-center justify-center p-4">
        <div class="modal-panel relative w-full max-w-sm bg-white rounded-lg shadow-xl p-6">
            <h3 class="text-2xl font-serif font-bold text-brand-dark mb-4">Add to Cart</h3>
            <div class="flex gap-4 mb-6">
                <img id="quantity-modal-img" src="https://placehold.co/80x80" alt="Product"
                    class="w-20 h-20 rounded-lg object-cover border">
                <div class="flex-grow">
                    <h4 id="quantity-modal-name" class="font-semibold text-brand-dark">Product Name</h4>
                    <p id="quantity-modal-price" class="text-sm text-brand-subtext">₱0.00</p>
                </div>
            </div>
            <div class="mb-6">
                <label for="quantity-input" class="block text-sm font-medium text-brand-dark mb-2">Quantity</label>
                <div class="flex items-center">
                    <button id="quantity-decrease"
                        class="w-10 h-10 border rounded-l-md bg-gray-100 text-lg font-bold text-brand-dark">-</button>
                    <input id="quantity-input" type="number" value="1" min="1"
                        class="w-16 h-10 text-center border-t border-b focus:outline-none">
                    <button id="quantity-increase"
                        class="w-10 h-10 border rounded-r-md bg-gray-100 text-lg font-bold text-brand-dark">+</button>
                </div>
            </div>
            <div class="flex flex-col gap-3">
                <button id="quantity-confirm-btn"
                    class="w-full bg-brand-teal text-white py-2.5 rounded-full font-semibold hover:bg-opacity-90 transition-colors">
                    Add to Cart
                </button>
                <button id="quantity-cancel-btn"
                    class="w-full bg-white text-brand-dark border border-gray-300 py-2.5 rounded-full font-semibold hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- Product Details Modal -->
    <div id="product-details-modal"
        class="modal hidden fixed inset-0 bg-black bg-opacity-60 z-50 flex items-center justify-center p-4">
        <div class="modal-panel product-details-modal relative w-full bg-white rounded-lg shadow-xl overflow-hidden">
            <button id="close-product-details-modal"
                class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 z-10">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6">
                <div>
                    <img id="product-details-image" src="" alt="Product" class="w-full product-details-image">
                </div>
                <div>
                    <h2 id="product-details-name" class="text-2xl font-bold text-brand-dark mb-2"></h2>
                    <p id="product-details-price" class="text-xl font-semibold text-brand-teal mb-4"></p>
                    <div id="product-details-rating" class="product-rating mb-4"></div>
                    <p id="product-details-description" class="text-brand-subtext mb-6"></p>
                    <div class="flex gap-3">
                        <button id="product-details-add-to-cart"
                            class="flex-1 bg-brand-teal text-white py-2.5 rounded-full font-semibold hover:bg-opacity-90 transition-colors">
                            Add to Cart
                        </button>
                        <button id="product-details-buy-now"
                            class="flex-1 bg-white text-brand-dark border border-gray-300 py-2.5 rounded-full font-semibold hover:bg-gray-50 transition-colors">
                            Buy Now
                        </button>
                    </div>
                </div>
            </div>
            <div class="border-t p-6">
                <h3 class="text-xl font-bold text-brand-dark mb-4">Customer Reviews</h3>
                <div id="product-reviews" class="product-reviews">
                    <p class="text-brand-subtext text-center">Loading reviews...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Login Modal -->
    <div id="login-modal"
        class="modal hidden fixed inset-0 bg-black bg-opacity-60 z-50 flex items-center justify-center p-4">
        <div class="modal-panel relative w-full max-w-4xl bg-white rounded-lg shadow-xl overflow-hidden">
            <div class="flex flex-col lg:flex-row min-h-[500px]">
                <!-- Left side - Gradient background with Aurex -->
                <div class="hidden lg:block lg:w-1/2 relative p-8 faded-background">
                    <div class="absolute inset-0 bg-cover bg-center opacity-10"
                        style="background-image: url('https://placehold.co/1000x1200/DCCEB8/3A3A3A?text=Aurex');"></div>

                    <div class="relative flex flex-col justify-center items-start h-full p-8 text-left">
                        <h1 class="font-serif text-4xl font-extrabold text-brand-dark leading-snug">
                            Exclusive Access
                        </h1>
                        <p class="mt-4 text-brand-subtext text-lg max-w-sm">
                            Log in quickly to manage your orders and view your wishlist.
                        </p>
                    </div>
                </div>

                <!-- Right side - Login form -->
                <div class="w-full lg:w-1/2 bg-white flex justify-center items-center p-8">
                    <div class="w-full max-w-sm">
                        <div class="text-center mb-6 lg:text-left">
                            <h2 class="text-3xl font-serif font-bold text-brand-dark mb-2">Welcome Back</h2>
                            <p class="text-brand-subtext">Sign in to your account</p>
                        </div>

                        <form id="login-form" class="space-y-4">
                            <div>
                                <label for="login-email" class="block text-sm font-medium text-brand-dark mb-2">Email</label>
                                <input type="email" id="login-email" name="email" required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-teal focus:border-transparent">
                                <div id="login-email-error" class="error-message"></div>
                            </div>
                            <div>
                                <label for="login-password" class="block text-sm font-medium text-brand-dark mb-2">Password</label>
                                <input type="password" id="login-password" name="password" required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-teal focus:border-transparent">
                                <div id="login-password-error" class="error-message"></div>
                            </div>
                            <button type="submit"
                                class="w-full bg-brand-teal text-white py-3 rounded-lg font-semibold hover:bg-opacity-90 transition-colors">
                                Sign In
                            </button>
                        </form>

                        <div class="mt-6 text-center">
                            <p class="text-brand-subtext">Don't have an account?
                                <button id="switch-to-register" class="text-brand-teal font-semibold hover:underline">Sign up</button>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <button id="close-login-modal"
                class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 z-10">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
    </div>

    <!-- Register Modal -->
    <div id="register-modal"
        class="modal hidden fixed inset-0 bg-black bg-opacity-60 z-50 flex items-center justify-center p-4">
        <div class="modal-panel relative w-full max-w-4xl bg-white rounded-lg shadow-xl overflow-hidden">
            <div class="flex flex-col lg:flex-row min-h-[500px]">
                <!-- Left side - Gradient background with Aurex -->
                <div class="hidden lg:block lg:w-1/2 relative p-8 faded-background">
                    <div class="absolute inset-0 bg-cover bg-center opacity-10"
                        style="background-image: url('https://placehold.co/1000x1200/DCCEB8/3A3A3A?text=Aurex');"></div>

                    <div class="relative flex flex-col justify-center items-start h-full p-8 text-left">
                        <h1 class="font-serif text-4xl font-extrabold text-brand-dark leading-snug">
                            Adorn Yourself in Elegance
                        </h1>
                        <p class="mt-4 text-brand-subtext text-lg max-w-sm">
                            Sign up to discover exclusive collections and personal styling advice.
                        </p>
                        <p class="mt-8 text-sm text-brand-teal font-semibold border border-brand-teal/50 px-4 py-2 rounded-full">
                            New Arrivals Every Week.
                        </p>
                    </div>
                </div>

                <!-- Right side - Register form -->
                <div class="w-full lg:w-1/2 bg-white flex justify-center items-center p-8">
                    <div class="w-full max-w-sm">
                        <div class="text-center mb-6 lg:text-left">
                            <h2 class="text-3xl font-serif font-bold text-brand-dark mb-2">Create Account</h2>
                            <p class="text-brand-subtext">Join us to start shopping</p>
                        </div>

                        <form id="register-form" class="space-y-4">
                            <div>
                                <label for="register-name" class="block text-sm font-medium text-brand-dark mb-2">Full Name</label>
                                <input type="text" id="register-name" name="name" required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-teal focus:border-transparent">
                                <div id="register-name-error" class="error-message"></div>
                            </div>
                            <div>
                                <label for="register-email" class="block text-sm font-medium text-brand-dark mb-2">Email</label>
                                <input type="email" id="register-email" name="email" required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-teal focus:border-transparent">
                                <div id="register-email-error" class="error-message"></div>
                            </div>
                            <div>
                                <label for="register-phone" class="block text-sm font-medium text-brand-dark mb-2">Phone (Optional)</label>
                                <input type="tel" id="register-phone" name="phone"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-teal focus:border-transparent">
                            </div>
                            <div>
                                <label for="register-password" class="block text-sm font-medium text-brand-dark mb-2">Password</label>
                                <input type="password" id="register-password" name="password" required minlength="6"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-teal focus:border-transparent">
                                <div id="register-password-error" class="error-message"></div>
                                <p class="text-xs text-gray-500 mt-1">Password must be at least 6 characters long</p>
                            </div>
                            <button type="submit"
                                class="w-full bg-brand-teal text-white py-3 rounded-lg font-semibold hover:bg-opacity-90 transition-colors">
                                Create Account
                            </button>
                        </form>

                        <div class="mt-6 text-center">
                            <p class="text-brand-subtext">Already have an account?
                                <button id="switch-to-login" class="text-brand-teal font-semibold hover:underline">Sign in</button>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <button id="close-register-modal"
                class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 z-10">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
    </div>

    <header class="py-6 sticky top-0 z-40 bg-brand-teal shadow-md transition-all">
        <nav class="flex justify-between items-center max-w-screen-2xl mx-auto px-4 md:px-8 lg:px-16">
            <a href="#" class="font-serif text-3xl font-bold text-white flex items-center gap-2">
                <!-- Elegant jewelry icon for Aurex -->
                <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z" fill="white"/>
                    <path d="M12 2l1.5 4.5L18 8l-4.5 1.5L12 14l-1.5-4.5L6 8l4.5-1.5L12 2z" fill="white"/>
                </svg>
                Aurex
            </a>

            <ul id="mobile-menu" class="hidden lg:flex gap-10">
                <li><a href="#" class="font-medium text-gray-100 hover:text-white transition-colors">Home</a></li>
                <li><a id="nav-collection" href="#section-collection"
                        class="font-medium text-gray-100 hover:text-white transition-colors">Collection</a></li>
                <li><a id="nav-products" href="#section-products"
                        class="font-medium text-gray-100 hover:text-white transition-colors">Products</a></li>
                <li><a id="nav-contact" href="#section-contact"
                        class="font-medium text-gray-100 hover:text-white transition-colors">Contact</a></li>
            </ul>

            <div class="flex items-center gap-5">
                <?php if ($isLoggedIn): ?>
                    <!-- Logged-in user navigation -->
                    <span class="text-white font-semibold text-base hidden md:inline-block">
                        Welcome, <?php echo $greetingName; ?>!
                    </span>
                    <a href="orders.php" class="relative text-gray-100 hover:text-white transition-colors" title="My Orders">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                            </path>
                        </svg>
                        <span id="order-count-badge"
                            class="<?php echo $order_count > 0 ? '' : 'hidden'; ?> absolute -top-2 -right-2 bg-red-600 text-white text-xs w-5 h-5 rounded-full flex items-center justify-center font-bold"><?php echo $order_count > 99 ? '99+' : $order_count; ?></span>
                    </a>

                    <a href="edit_profile.php" class="text-gray-100 hover:text-white transition-colors" title="Edit Profile">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </a>
                    <button id="open-cart-btn" class="relative text-gray-100 hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                        </svg>
                        <span id="cart-count-badge"
                            class="hidden absolute -top-2 -right-2 bg-red-600 text-white text-xs w-5 h-5 rounded-full flex items-center justify-center font-bold">0</span>
                    </button>
                    <a href="logout.php"
                        class="bg-white text-brand-teal px-6 py-2 rounded-full text-sm font-semibold hover:bg-gray-100 transition-colors">Logout</a>
                <?php else: ?>
                    <!-- Guest user navigation -->
                    <button id="login-btn"
                        class="bg-white text-brand-teal px-6 py-2 rounded-full text-sm font-semibold hover:bg-gray-100 transition-colors">Login</button>
                    <button id="register-btn"
                        class="bg-brand-teal border-2 border-white text-white px-6 py-2 rounded-full text-sm font-semibold hover:bg-white hover:text-brand-teal transition-colors">Register</button>
                <?php endif; ?>

                <button id="mobile-menu-btn" class="lg:hidden text-gray-100">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16m-7 6h7"></path>
                    </svg>
                </button>
            </div>
        </nav>
    </header>

    <main id="hero-section" class="hero-bg min-h-screen flex items-center">
        <section class="relative z-10 max-w-screen-2xl mx-auto px-4 md:px-8 lg:px-16">
            <div class="grid grid-cols-1 lg:grid-cols-2 items-center gap-12 py-16 sm:py-24">
                <div class="text-white">
                    <h1 class="font-serif text-5xl sm:text-6xl md:text-7xl font-bold leading-tight mb-6">
                        Find Your Perfect Piece
                    </h1>
                    <p class="text-lg md:text-xl mb-10 max-w-lg">
                        Discover Timeless, Elegant & Classic Style with Our Exquisite Jewelry Collection.
                    </p>
                    <a href="#section-collection" 
                       class="shop-now-btn inline-block px-10 py-4 rounded-full font-semibold text-lg uppercase tracking-wider text-white">
                        Shop Now
                    </a>
                </div>
                <div class="hidden lg:block"></div>
            </div>
        </section>
    </main>

    <div class="max-w-screen-2xl mx-auto px-4 md:px-8 lg:px-16">

        <section id="section-collection" class="py-16 sm:py-24">
            <h2 class="font-serif text-4xl font-bold text-brand-dark text-center mb-14">Shop by Category</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 lg:gap-10">

                <?php
                // Define category images
                $category_images = [
                    'Earrings' => 'https://image2url.com/images/1765720646088-7daca6e2-dbc1-4a0d-8c80-dfcd7d862111.png',
                    'Bracelet' => 'https://image2url.com/images/1765719960039-163785f6-8a3f-47b1-92f1-1814bfb90e39.png',
                    'Necklace' => 'https://image2url.com/images/1765720517846-93305feb-b654-470f-918d-fe73e71d347c.png',
                    'Rings' => 'https://image2url.com/images/1765719925402-606aef47-7d0e-4279-b74c-af332ebe05a1.png',
                    'default' => 'https://placehold.co/300x300/F3F4F6/3A3A3A?text=Jewelry'
                ];

                foreach ($all_categories as $category_name):
                    // Standardize category name for count lookup and URL parameter
                    $safe_cat_name = urlencode($category_name);
                    $display_cat_name = htmlspecialchars($category_name);
                    $product_count = $category_counts[$category_name] ?? 0;
                    
                    // Get category image or use default
                    $category_image = $category_images[$category_name] ?? $category_images['default'];

                    // Only display categories that have products
                    if ($product_count > 0):
                ?>
                        <a href="?cat=<?php echo $safe_cat_name; ?>#section-products"
                            class="category-filter-link text-center group"
                            data-category="<?php echo strtolower($display_cat_name); ?>">
                            <div
                                class="w-full aspect-square rounded-full overflow-hidden border-4 border-white shadow-lg transform transition-transform duration-300 group-hover:scale-105 category-circle">
                                <img src="<?php echo $category_image; ?>"
                                    alt="<?php echo $display_cat_name; ?>" class="w-full h-full object-cover">
                            </div>
                            <h3 class="mt-5 text-xl font-semibold text-brand-dark"><?php echo $display_cat_name; ?></h3>
                            <p id="<?php echo strtolower($display_cat_name); ?>-count" class="text-sm text-brand-subtext">
                                <?php echo $product_count; ?> Products</p>
                        </a>
                <?php
                    endif;
                endforeach;
                ?>
            </div>
            <div class="text-center mt-16">
                <a id="view-all-categories-btn" href="index_user.php#section-products"
                    class="inline-block px-10 py-3 border border-stone-400 text-brand-subtext rounded-full font-semibold hover:bg-white hover:text-brand-dark hover:border-brand-dark transition-all">
                    View All Products
                </a>
            </div>
        </section>

        <section id="section-products" class="py-16 sm:py-24">
            <button id="back-to-home-btn"
                class="hidden mb-8 text-brand-dark font-semibold group flex items-center gap-2 hover:text-brand-teal transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 transition-transform group-hover:-translate-x-1"
                    viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd"
                        d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z"
                        clip-rule="evenodd" />
                </svg>
                Back to Home
            </button>
            <h2 id="products-section-title" class="font-serif text-4xl font-bold text-brand-dark text-center mb-10"
                data-active-category="<?php echo $category_filter_name ? strtolower($category_filter_name) : 'all'; ?>">
                <?php echo $product_section_title; ?></h2>

            <div class="mb-14 px-4 sm:px-0">
                <input type="search" id="search-bar" placeholder="Search products in this collection..."
                    class="w-full max-w-lg mx-auto block px-5 py-3 rounded-full border border-gray-300 focus:outline-none focus:ring-2 focus:ring-brand-teal shadow-sm transition">
            </div>

            <div id="product-grid"
                class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-x-6 gap-y-12">
                <?php
                if (empty($dynamic_products)) {
                    echo '<p id="no-products-msg" class="text-center text-brand-subtext col-span-full text-lg py-10">No products found in this collection.</p>';
                } else {
                    // Loop through the fetched products
                    foreach ($dynamic_products as $product):
                        $product_image_url = !empty($product['Images']) ? htmlspecialchars($product['Images']) : 'https://placehold.co/300x300/F3F4F6/3A3A3A?text=' . urlencode(substr($product['ProductName'], 0, 4));
                        $product_name_clean = htmlspecialchars($product['ProductName']);
                        $product_price_formatted = number_format($product['Price'], 2);
                        $product_category_lower = strtolower(htmlspecialchars($product['Category_Name']));
                        $avg_rating = $product['Avg_Rating'] ?? 0;
                        $rating_count = $product['Rating_Count'] ?? 0;
                        $product_description = htmlspecialchars($product['Description'] ?? 'No description available.');
                ?>
                        <div class="product-card group relative transition-all duration-300 shadow-lg hover:shadow-xl hover:-translate-y-1"
                            data-category="<?php echo $product_category_lower; ?>">
                            <span
                                class="absolute top-4 left-4 bg-white/90 text-brand-dark text-xs font-semibold px-3 py-1 rounded-full capitalize z-10"><?php echo $product['Category_Name']; ?></span>
                            <div class="product-card-image-wrapper w-full aspect-square">
                                <img src="<?php echo $product_image_url; ?>" alt="<?php echo $product_name_clean; ?>"
                                    class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105">
                            </div>
                            <div class="p-4 pt-2">
                                <h3 class="text-lg font-semibold text-brand-dark"><?php echo $product_name_clean; ?></h3>
                                <p class="text-brand-subtext mb-1">₱<?php echo $product_price_formatted; ?></p>
                                
                                <!-- Product Description Display -->
                                <p class="text-sm text-brand-subtext mt-2 line-clamp-2 h-10 overflow-hidden" title="<?php echo $product_description; ?>">
                                    <?php echo $product_description; ?>
                                </p>
                                
                                <p class="text-sm text-gray-500 mt-2"><?php echo $product['Stock'] > 0 ? 'In Stock: ' . $product['Stock'] : 'Out of Stock'; ?></p>
                                
                                <!-- Product Rating Display -->
                                <div class="product-rating">
                                    <div class="stars">
                                        <?php 
                                        for ($i = 1; $i <= 5; $i++) {
                                            if ($i <= round($avg_rating)) {
                                                echo '★';
                                            } else {
                                                echo '<span class="empty">★</span>';
                                            }
                                        }
                                        ?>
                                    </div>
                                    <span class="rating-count"><?php echo $avg_rating; ?> (<?php echo $rating_count; ?> reviews)</span>
                                </div>
                                
                                <div class="flex gap-2 mt-3">
                                    <button
                                        class="add-to-cart-btn w-1/2 py-2.5 rounded-full font-semibold text-sm btn-secondary <?php echo $product['Stock'] <= 0 ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                                        data-id="<?php echo $product['Product_ID']; ?>"
                                        data-name="<?php echo $product_name_clean; ?>"
                                        data-price="<?php echo $product['Price']; ?>"
                                        data-img="https://placehold.co/80x80/D4C9BC/3A3A3A?text=<?php echo urlencode(substr($product['ProductName'], 0, 4)); ?>"
                                        <?php echo $product['Stock'] <= 0 ? 'disabled' : ''; ?>>
                                        Add to Cart
                                    </button>
                                    <button class="buy-now-btn w-1/2 py-2.5 rounded-full font-semibold text-sm btn-primary <?php echo $product['Stock'] <= 0 ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                                        data-id="<?php echo $product['Product_ID']; ?>"
                                        data-name="<?php echo $product_name_clean; ?>"
                                        data-price="<?php echo $product['Price']; ?>"
                                        data-img="https://placehold.co/80x80/D4C9BC/3A3A3A?text=<?php echo urlencode(substr($product['ProductName'], 0, 4)); ?>"
                                        <?php echo $product['Stock'] <= 0 ? 'disabled' : ''; ?>>
                                        Buy Now
                                    </button>
                                </div>
                                
                                <!-- View All Reviews Button -->
                                <button class="view-all-reviews w-full mt-2 py-2 rounded-full font-semibold text-sm bg-gray-100 text-brand-dark hover:bg-gray-200 transition-colors"
                                    data-id="<?php echo $product['Product_ID']; ?>">
                                    View All Reviews
                                </button>
                            </div>
                        </div>
                <?php
                    endforeach;
                }
                ?>

                <p id="no-products-msg" class="hidden text-center text-brand-subtext col-span-full text-lg py-10">No
                    products found.</p>

            </div>
        </section>

        <section id="promo-section" class="my-16 sm:my-24">
            <div class="grid grid-cols-1 lg:grid-cols-2 items-center bg-white rounded-2xl shadow-xl overflow-hidden">
                <div class="p-10 md:p-16 lg:p-20 order-2 lg:order-1">
                    <h2 class="font-serif text-4xl font-bold text-brand-dark leading-tight mb-5">Creates Lasting
                        Memories of Each Occasion</h2>
                    <p class="text-brand-subtext text-lg mb-8">Our master artisans blend traditional techniques with
                        modern design to forge pieces that are not just accessories, but heirlooms.</p>
                    <a href="#"
                        class="inline-block bg-brand-teal text-white px-8 py-3 rounded-full font-semibold hover:bg-opacity-90 transition-colors">
                        Explore Our Story
                    </a>
                </div>
                <div class="w-full h-64 sm:h-96 lg:h-full order-1 lg:order-2">
                    <img src="https://image2url.com/images/1765700598578-a3226f1e-5eaa-4f67-a3da-32cd033807a6.png"
                        alt="Artisan crafting jewellery" class="w-full h-full object-cover">
                </div>
            </div>
        </section>

    </div>
    <!--  -->
    <footer id="section-contact" class="bg-white mt-16 sm:mt-24 border-t border-gray-100">
        <div class="max-w-screen-2xl mx-auto px-4 md:px-8 lg:px-16 py-16">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12">
                <div>
                    <a href="#" class="font-serif text-3xl font-bold text-brand-dark mb-4 inline-block flex items-center gap-2">
                        <!-- Elegant jewelry icon for Aurex -->
                        <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z" fill="#8B7B61"/>
                            <path d="M12 2l1.5 4.5L18 8l-4.5 1.5L12 14l-1.5-4.5L6 8l4.5-1.5L12 2z" fill="#8B7B61"/>
                        </svg>
                        Aurex
                    </a>
                    <p class="text-brand-subtext mb-5">Sign up for our newsletter to get the latest arrivals and
                        exclusive offers.</p>
                    <form id="newsletter-form" class="flex">
                        <input id="newsletter-email" type="email" placeholder="Your email"
                            class="flex-grow px-4 py-2 border border-gray-300 rounded-l-full focus:outline-none focus:ring-2 focus:ring-brand-teal">
                        <button type="submit"
                            class="bg-brand-teal text-white px-5 py-2 rounded-r-full font-semibold hover:bg-opacity-90">&rarr;</button>
                    </form>
                </div>

                <div>
                    <h4 class="font-bold text-lg text-brand-dark mb-4">Shop</h4>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-brand-subtext hover:text-brand-dark">All Products</a></li>
                        <li><a href="#" class="text-brand-subtext hover:text-brand-dark">Earrings</a></li>
                        <li><a href="#" class="text-brand-subtext hover:text-brand-dark">Necklaces</a></li>
                        <li><a href="#" class="text-brand-subtext hover:text-brand-dark">Bracelets</a></li>
                        <li><a href="#" class="text-brand-subtext hover:text-brand-dark">Rings</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="font-bold text-lg text-brand-dark mb-4">Company</h4>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-brand-subtext hover:text-brand-dark">Our Story</a></li>
                        <li><a href="#" class="text-brand-subtext hover:text-brand-dark">Craftsmanship</a></li>
                        <li><a href="#" class="text-brand-subtext hover:text-brand-dark">Contact Us</a></li>
                        <li><a href="#" class="text-brand-subtext hover:text-brand-dark">FAQ</a></li>
                        <li><a href="#" class="text-brand-subtext hover:text-brand-dark">Store Locator</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="font-bold text-lg text-brand-dark mb-4">Follow Us</h4>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-brand-subtext hover:text-brand-dark">Instagram</a></li>
                        <li><a href="#" class="text-brand-subtext hover:text-brand-dark">Pinterest</a></li>
                        <li><a href="#" class="text-brand-subtext hover:text-brand-dark">Facebook</a></li>
                        <li><a href="#" class="text-brand-subtext hover:text-brand-dark">Twitter</a></li>
                    </ul>
                </div>
            </div>

            <div class="mt-16 pt-8 border-t border-gray-200 text-center text-brand-subtext text-sm">
                <p>&copy; 2024 Aurex. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <div id="toast-notification"
        class="fixed bottom-10 left-1/2 -translate-x-1/2 bg-brand-dark text-white px-6 py-3 rounded-full shadow-lg z-50">
        Item added to cart!
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        console.log('DOMContentLoaded fired - JavaScript is running');

        // === LOGIN/REGISTER MODAL FUNCTIONALITY ===
        const loginBtn = document.getElementById('login-btn');
        const registerBtn = document.getElementById('register-btn');
        const loginModal = document.getElementById('login-modal');
        const registerModal = document.getElementById('register-modal');
        const closeLoginModal = document.getElementById('close-login-modal');
        const closeRegisterModal = document.getElementById('close-register-modal');
        const switchToRegister = document.getElementById('switch-to-register');
        const switchToLogin = document.getElementById('switch-to-login');
        const loginForm = document.getElementById('login-form');
        const registerForm = document.getElementById('register-form');

        // Check if user is logged in
        const isLoggedIn = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
        const orderCount = <?php echo $order_count; ?>;

        // Login modal handlers
        if (loginBtn) {
            loginBtn.addEventListener('click', () => {
                if (loginModal) {
                    loginModal.classList.remove('hidden');
                }
            });
        }

        if (registerBtn) {
            registerBtn.addEventListener('click', () => {
                if (registerModal) {
                    registerModal.classList.remove('hidden');
                }
            });
        }

        // Close modal handlers
        if (closeLoginModal) {
            closeLoginModal.addEventListener('click', () => {
                loginModal.classList.add('hidden');
            });
        }

        if (closeRegisterModal) {
            closeRegisterModal.addEventListener('click', () => {
                registerModal.classList.add('hidden');
            });
        }

        // Switch between modals
        if (switchToRegister) {
            switchToRegister.addEventListener('click', () => {
                loginModal.classList.add('hidden');
                registerModal.classList.remove('hidden');
            });
        }

        if (switchToLogin) {
            switchToLogin.addEventListener('click', () => {
                registerModal.classList.add('hidden');
                loginModal.classList.remove('hidden');
            });
        }

        // Close modals when clicking backdrop
        if (loginModal) {
            loginModal.addEventListener('click', (e) => {
                if (e.target === loginModal) loginModal.classList.add('hidden');
            });
        }

        if (registerModal) {
            registerModal.addEventListener('click', (e) => {
                if (e.target === registerModal) registerModal.classList.add('hidden');
            });
        }

        // Helper function to show inline error messages
        function showInlineError(inputId, errorId, message) {
            const input = document.getElementById(inputId);
            const error = document.getElementById(errorId);
            
            if (input && error) {
                input.classList.add('input-error');
                error.textContent = message;
                error.classList.add('show');
            }
        }

        // Helper function to clear inline error messages
        function clearInlineErrors(formId) {
            const form = document.getElementById(formId);
            if (!form) return;
            
            const inputs = form.querySelectorAll('input');
            const errors = form.querySelectorAll('.error-message');
            
            inputs.forEach(input => input.classList.remove('input-error'));
            errors.forEach(error => {
                error.textContent = '';
                error.classList.remove('show');
            });
        }

        // Login form submission
        if (loginForm) {
            loginForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                // Clear previous errors
                clearInlineErrors('login-form');
                
                const formData = new FormData(loginForm);
                const email = formData.get('email');
                const password = formData.get('password');
                
                // Client-side validation
                let hasError = false;
                
                if (!email) {
                    showInlineError('login-email', 'login-email-error', 'Email is required');
                    hasError = true;
                } else if (!/^\S+@\S+\.\S+$/.test(email)) {
                    showInlineError('login-email', 'login-email-error', 'Please enter a valid email address');
                    hasError = true;
                }
                
                if (!password) {
                    showInlineError('login-password', 'login-password-error', 'Password is required');
                    hasError = true;
                }
                
                if (hasError) return;

                try {
                    const response = await fetch('login_process.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        // Success - redirect to appropriate page
                        window.location.href = result.redirect;
                    } else {
                        // Handle different error cases
                        if (result.redirect === 'verify_2fa.php') {
                            window.location.href = 'verify_2fa.php';
                        } else {
                            // Parse the error message to determine which field to highlight
                            const errorMessage = result.message || 'Login failed. Please try again.';
                            
                            // Check if the error message contains specific keywords
                            if (errorMessage.toLowerCase().includes('email') || errorMessage.toLowerCase().includes('account')) {
                                showInlineError('login-email', 'login-email-error', errorMessage);
                            } else if (errorMessage.toLowerCase().includes('password')) {
                                showInlineError('login-password', 'login-password-error', errorMessage);
                            } else {
                                // If the error message doesn't contain specific keywords, show it as a general error
                                showInlineError('login-email', 'login-email-error', errorMessage);
                            }
                        }
                    }
                } catch (error) {
                    console.error('Login error:', error);
                    showInlineError('login-email', 'login-email-error', 'Login failed. Please try again.');
                }
            });
        }

        // Register form submission
        if (registerForm) {
            registerForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                // Clear previous errors
                clearInlineErrors('register-form');
                
                const formData = new FormData(registerForm);
                const name = formData.get('name');
                const email = formData.get('email');
                const password = formData.get('password');
                
                // Client-side validation
                let hasError = false;
                
                if (!name) {
                    showInlineError('register-name', 'register-name-error', 'Name is required');
                    hasError = true;
                }
                
                if (!email) {
                    showInlineError('register-email', 'register-email-error', 'Email is required');
                    hasError = true;
                } else if (!/^\S+@\S+\.\S+$/.test(email)) {
                    showInlineError('register-email', 'register-email-error', 'Please enter a valid email address');
                    hasError = true;
                }
                
                if (!password) {
                    showInlineError('register-password', 'register-password-error', 'Password is required');
                    hasError = true;
                } else if (password.length < 6) {
                    showInlineError('register-password', 'register-password-error', 'Password must be at least 6 characters long');
                    hasError = true;
                }
                
                if (hasError) return;

                try {
                    const response = await fetch('signup_process.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        // Success - show message and switch to login
                        showMessage('Success', 'Account created successfully! Please check your email to set up 2FA.');
                        registerModal.classList.add('hidden');
                        loginModal.classList.remove('hidden');
                    } else {
                        // Show specific error messages
                        const errorMessage = result.message || 'Registration failed. Please try again.';
                        
                        if (errorMessage.toLowerCase().includes('password')) {
                            showInlineError('register-password', 'register-password-error', errorMessage);
                        } else if (errorMessage.toLowerCase().includes('email')) {
                            showInlineError('register-email', 'register-email-error', errorMessage);
                        } else if (errorMessage.toLowerCase().includes('name')) {
                            showInlineError('register-name', 'register-name-error', errorMessage);
                        } else {
                            showInlineError('register-email', 'register-email-error', errorMessage);
                        }
                    }
                } catch (error) {
                    console.error('Registration error:', error);
                    showInlineError('register-email', 'register-email-error', 'Registration failed. Please try again.');
                }
            });
        }

        // DOM elements
        const quantityModal = document.getElementById('quantity-modal');
        const quantityModalImg = document.getElementById('quantity-modal-img');
        const quantityModalName = document.getElementById('quantity-modal-name');
        const quantityModalPrice = document.getElementById('quantity-modal-price');
        const quantityInput = document.getElementById('quantity-input');
        const quantityDecrease = document.getElementById('quantity-decrease');
        const quantityIncrease = document.getElementById('quantity-increase');
        const quantityConfirmBtn = document.getElementById('quantity-confirm-btn');
        const quantityCancelBtn = document.getElementById('quantity-cancel-btn');

        const messageModal = document.getElementById('message-modal');
        const messageModalTitle = document.getElementById('message-modal-title');
        const messageModalText = document.getElementById('message-modal-text');
        const closeMessageModalBtn = document.getElementById('close-message-modal-btn');

        const cartModal = document.getElementById('cart-modal');
        const cartPanel = document.getElementById('cart-panel');
        const cartItemsContainer = document.getElementById('cart-items');
        const cartTotalEl = document.getElementById('cart-total');

        const openCartBtn = document.getElementById('open-cart-btn');

        // Product details modal elements
        const productDetailsModal = document.getElementById('product-details-modal');
        const closeProductDetailsModal = document.getElementById('close-product-details-modal');
        const productDetailsImage = document.getElementById('product-details-image');
        const productDetailsName = document.getElementById('product-details-name');
        const productDetailsPrice = document.getElementById('product-details-price');
        const productDetailsRating = document.getElementById('product-details-rating');
        const productDetailsDescription = document.getElementById('product-details-description');
        const productDetailsAddToCart = document.getElementById('product-details-add-to-cart');
        const productDetailsBuyNow = document.getElementById('product-details-buy-now');
        const productReviews = document.getElementById('product-reviews');

        // Safety: remove duplicate #no-products-msg if server accidentally rendered twice
        (function dedupeNoProductsMsg() {
            const elems = document.querySelectorAll('#no-products-msg');
            if (elems.length > 1) Array.from(elems).slice(1).forEach(e => e.remove());
        })();

        // --- Quantity controls ---
        function clampQuantity() {
            let v = parseInt(quantityInput.value, 10);
            if (!Number.isFinite(v) || v < 1) v = 1;
            quantityInput.value = String(v);
            return v;
        }

        quantityInput.addEventListener('input', () => {
            quantityInput.value = quantityInput.value.replace(/[^\d]/g, '');
        });
        quantityInput.addEventListener('blur', clampQuantity);
        quantityDecrease.addEventListener('click', (e) => {
            e.preventDefault();
            let v = clampQuantity();
            if (v > 1) quantityInput.value = String(v - 1);
        });
        quantityIncrease.addEventListener('click', (e) => {
            e.preventDefault();
            quantityInput.value = String(clampQuantity() + 1);
        });

        // --- Simple message modal ---
        function showMessage(title = 'Notice', text = '') {
            if (!messageModal) return alert((title ? title + ': ' : '') + text);
            messageModalTitle.textContent = title;
            messageModalText.textContent = text;
            messageModal.classList.remove('hidden');
        }
        closeMessageModalBtn && closeMessageModalBtn.addEventListener('click', () => messageModal.classList.add('hidden'));
        messageModal && messageModal.addEventListener('click', (e) => {
            if (e.target === messageModal) messageModal.classList.add('hidden');
        });

        // --- Cart API calls ---
        async function addToCartAPI(productId, quantity) {
            try {
                const res = await fetch('cart_api.php?action=add_to_cart', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        product_id: Number(productId),
                        quantity: Number(quantity)
                    })
                });
                const json = await res.json();
                if (!res.ok || !json.success) throw new Error(json.message || 'Add to cart failed');
                return json;
            } catch (err) {
                console.error('Add to cart error:', err);
                throw err;
            }
        }

        // Add helper function to sanitize HTML
        function sanitize(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        async function fetchCart() {
            try {
                const res = await fetch('cart_api.php?action=fetch_cart');
                const json = await res.json();
                if (!res.ok || !json.success) throw new Error(json.message || 'Failed to fetch cart');
                renderCart(json.items || []);
            } catch (err) {
                console.error('Fetch cart error:', err);
                showMessage('Cart Error', 'Unable to load cart right now.');
            }
        }

        function renderCart(items) {
            cartItemsContainer.innerHTML = '';
            const cartCountBadge = document.getElementById('cart-count-badge');

            if (!items.length) {
                cartItemsContainer.innerHTML =
                    '<p id="cart-empty-msg" class="text-brand-subtext">Your cart is empty.</p>';
                cartTotalEl.textContent = '₱0.00';
                // Hide badge if no items
                if (cartCountBadge) {
                    cartCountBadge.classList.add('hidden');
                }
                return;
            }

            let total = 0;
            let itemCount = 0;

            items.forEach(it => {
                const price = parseFloat(it.Price) || 0;
                const qty = parseInt(it.Quantity, 10) || 0;
                total += price * qty;
                itemCount += qty;
                const div = document.createElement('div');
                div.className = 'flex items-center gap-4 pb-4 border-b';
                div.innerHTML = `
    <img src="${it.Images || 'https://placehold.co/60x60'}" class="w-14 h-14 object-cover rounded" alt="">
    <div class="flex-grow">
      <div class="font-semibold">${sanitize(it.Name)}</div>
      <div class="text-sm text-gray-500">₱${price.toFixed(2)}</div>
    </div>
    <div class="flex flex-col items-end gap-2">
      <div class="flex items-center gap-2">
        <button class="cart-qty-decrease w-6 h-6 bg-gray-200 text-sm rounded hover:bg-gray-300" data-cart-item-id="${it.Cart_Item_ID}">−</button>
        <span class="cart-qty-display w-6 text-center">${qty}</span>
        <button class="cart-qty-increase w-6 h-6 bg-gray-200 text-sm rounded hover:bg-gray-300" data-cart-item-id="${it.Cart_Item_ID}">+</button>
      </div>
      <button class="cart-remove-btn text-gray-400 hover:text-red-600 transition-colors" data-cart-item-id="${it.Cart_Item_ID}" title="Remove item">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
      </button>
    </div>
  `;
                cartItemsContainer.appendChild(div);
            });

            cartTotalEl.textContent = `₱${total.toFixed(2)}`;

            // Update badge with item count
            if (cartCountBadge) {
                if (itemCount > 0) {
                    cartCountBadge.textContent = itemCount > 99 ? '99+' : itemCount;
                    cartCountBadge.classList.remove('hidden');
                } else {
                    cartCountBadge.classList.add('hidden');
                }
            }

            // Wire up cart item controls
            document.querySelectorAll('.cart-qty-decrease').forEach(btn => {
                btn.addEventListener('click', () => updateCartQuantity(btn.dataset.cartItemId, -1));
            });
            document.querySelectorAll('.cart-qty-increase').forEach(btn => {
                btn.addEventListener('click', () => updateCartQuantity(btn.dataset.cartItemId, 1));
            });
            document.querySelectorAll('.cart-remove-btn').forEach(btn => {
                btn.addEventListener('click', () => removeFromCart(btn.dataset.cartItemId));
            });
        }

        async function updateCartQuantity(cartItemId, delta) {
            try {
                const res = await fetch('cart_api.php?action=update_quantity', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        cart_item_id: Number(cartItemId),
                        delta: Number(delta)
                    })
                });
                const json = await res.json();
                if (!res.ok || !json.success) throw new Error(json.message || 'Update failed');
                await fetchCart();
            } catch (err) {
                console.error('Update quantity error:', err);
                showMessage('Error', err.message || 'Failed to update quantity.');
            }
        }

        async function removeFromCart(cartItemId) {
            try {
                const res = await fetch('cart_api.php?action=remove_item', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        cart_item_id: Number(cartItemId)
                    })
                });
                const json = await res.json();
                if (!res.ok || !json.success) throw new Error(json.message || 'Removal failed');
                await fetchCart();
            } catch (err) {
                console.error('Remove item error:', err);
                showMessage('Error', err.message || 'Failed to remove item.');
            }
        }

        // --- Quantity modal confirm wiring ---
        quantityConfirmBtn.addEventListener('click', async () => {
            const pid = quantityConfirmBtn.dataset.id;
            const qty = clampQuantity();
            const isBuyNow = quantityConfirmBtn.dataset.buynow === '1';
            
            if (!pid) return showMessage('Error', 'Product not selected.');
            
            // Disable button to prevent double-clicks
            quantityConfirmBtn.disabled = true;
            const originalText = quantityConfirmBtn.textContent;
            quantityConfirmBtn.textContent = 'Processing...';
            
            try {
                if (isBuyNow) {
                    // Buy Now: Store product info in session storage and redirect to checkout
                    // WITHOUT adding to cart
                    const productData = {
                        product_id: pid,
                        quantity: qty,
                        name: quantityModalName.textContent,
                        price: quantityConfirmBtn.dataset.price,
                        image: quantityModalImg.src
                    };
                    
                    // Store in sessionStorage for checkout page
                    sessionStorage.setItem('buyNowProduct', JSON.stringify(productData));
                    
                    // Redirect to checkout
                    window.location.href = 'shipping.php?buynow=1';
                } else {
                    // Add to Cart: Normal flow
                    await addToCartAPI(pid, qty);
                    await fetchCart();
                    quantityModal.classList.add('hidden');
                    showMessage('Added', 'Item added to cart.');
                }
            } catch (err) {
                // Re-enable button on error
                quantityConfirmBtn.disabled = false;
                quantityConfirmBtn.textContent = originalText;
                showMessage('Error', err.message || 'Failed to process. Please try again.');
            }
        });

        // Wire "Add to Cart" buttons to open modal
        document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                if (!isLoggedIn) {
                    e.preventDefault();
                    if (loginModal) {
                        loginModal.classList.remove('hidden');
                    }
                    return;
                }

                const d = btn.dataset;
                const modalTitle = quantityModal.querySelector('h3');
                modalTitle.textContent = 'Add to Cart';
                quantityConfirmBtn.textContent = 'Add to Cart';
                quantityConfirmBtn.dataset.buynow = '0';
                
                window.openQuantityModal({
                    id: d.id,
                    name: d.name,
                    price: d.price,
                    img: d.img
                });
            });
        });

        // Wire "Buy Now" buttons to add to cart and open cart modal
        document.querySelectorAll('.buy-now-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                if (!isLoggedIn) {
                    e.preventDefault();
                    if (loginModal) {
                        loginModal.classList.remove('hidden');
                    }
                    return;
                }

                const d = btn.dataset;
                if (!d.id) return;

                try {
                    await addToCartAPI(d.id, 1);
                    await fetchCart();
                    // Open cart modal
                    cartModal.classList.remove('hidden');
                    cartPanel.classList.remove('translate-x-full');
                    showMessage('Added', 'Item added to cart.');
                } catch (err) {
                    showMessage('Error', err.message || 'Failed to add item to cart.');
                }
            });
        });

        // Wire "View Product Details" buttons
        document.querySelectorAll('.view-product-details').forEach(btn => {
            btn.addEventListener('click', () => {
                const productId = btn.dataset.id;
                if (productId) {
                    showProductDetails(productId);
                }
            });
        });

        // Wire "View All Reviews" buttons
        document.querySelectorAll('.view-all-reviews').forEach(btn => {
            btn.addEventListener('click', () => {
                const productId = btn.dataset.id;
                if (productId) {
                    window.location.href = `view_review.php?product_id=${productId}`;
                }
            });
        });

        // Function to show product details
        async function showProductDetails(productId) {
            try {
                const response = await fetch(`get_product_details.php?product_id=${productId}`);
                const data = await response.json();
                
                if (data.success) {
                    const product = data.product;
                    
                    // Set product details
                    productDetailsImage.src = product.Images || 'https://placehold.co/400x400';
                    productDetailsName.textContent = product.Name;
                    productDetailsPrice.textContent = `₱${parseFloat(product.Price).toFixed(2)}`;
                    productDetailsDescription.textContent = product.Description || 'No description available.';
                    
                    // Set rating
                    let ratingHtml = '<div class="stars">';
                    for (let i = 1; i <= 5; i++) {
                        if (i <= Math.round(product.Avg_Rating || 0)) {
                            ratingHtml += '★';
                        } else {
                            ratingHtml += '<span class="empty">★</span>';
                        }
                    }
                    ratingHtml += `</div><span class="rating-count">${product.Avg_Rating || 0} (${product.Rating_Count || 0} reviews)</span>`;
                    productDetailsRating.innerHTML = ratingHtml;
                    
                    // Set up buttons
                    productDetailsAddToCart.dataset.id = product.Product_ID;
                    productDetailsAddToCart.dataset.name = product.Name;
                    productDetailsAddToCart.dataset.price = product.Price;
                    productDetailsAddToCart.dataset.img = product.Images || 'https://placehold.co/80x80';
                    
                    productDetailsBuyNow.dataset.id = product.Product_ID;
                    productDetailsBuyNow.dataset.name = product.Name;
                    productDetailsBuyNow.dataset.price = product.Price;
                    productDetailsBuyNow.dataset.img = product.Images || 'https://placehold.co/80x80';
                    
                    // Load reviews
                    loadProductReviews(productId);
                    
                    // Show modal
                    productDetailsModal.classList.remove('hidden');
                } else {
                    showMessage('Error', data.message || 'Failed to load product details.');
                }
            } catch (error) {
                console.error('Error loading product details:', error);
                showMessage('Error', 'Failed to load product details.');
            }
        }

        // Function to load product reviews
        async function loadProductReviews(productId) {
            try {
                const response = await fetch(`get_product_reviews.php?product_id=${productId}`);
                const data = await response.json();
                
                if (data.success) {
                    const reviews = data.reviews;
                    
                    if (reviews.length === 0) {
                        productReviews.innerHTML = '<p class="text-brand-subtext text-center">No reviews yet for this product.</p>';
                    } else {
                        let reviewsHtml = '';
                        reviews.forEach(review => {
                            let ratingStars = '';
                            for (let i = 1; i <= 5; i++) {
                                if (i <= review.Rating) {
                                    ratingStars += '★';
                                } else {
                                    ratingStars += '<span class="empty">★</span>';
                                }
                            }
                            
                            reviewsHtml += `
                                <div class="review-item">
                                    <div class="review-header">
                                        <div class="review-rating">${ratingStars}</div>
                                        <div class="review-date">${new Date(review.Rating_Date).toLocaleDateString()}</div>
                                    </div>
                                    <div class="review-text">${review.Review_Text || 'No review text provided.'}</div>
                                </div>
                            `;
                        });
                        productReviews.innerHTML = reviewsHtml;
                    }
                } else {
                    productReviews.innerHTML = '<p class="text-brand-subtext text-center">Failed to load reviews.</p>';
                }
            } catch (error) {
                console.error('Error loading reviews:', error);
                productReviews.innerHTML = '<p class="text-brand-subtext text-center">Failed to load reviews.</p>';
            }
        }

        // Add to cart from product details modal
        productDetailsAddToCart.addEventListener('click', () => {
            if (!isLoggedIn) {
                if (loginModal) {
                    loginModal.classList.remove('hidden');
                }
                return;
            }

            const d = productDetailsAddToCart.dataset;
            const modalTitle = quantityModal.querySelector('h3');
            modalTitle.textContent = 'Add to Cart';
            quantityConfirmBtn.textContent = 'Add to Cart';
            quantityConfirmBtn.dataset.buynow = '0';
            
            window.openQuantityModal({
                id: d.id,
                name: d.name,
                price: d.price,
                img: d.img
            });
        });

        // Buy now from product details modal
        productDetailsBuyNow.addEventListener('click', async () => {
            if (!isLoggedIn) {
                if (loginModal) {
                    loginModal.classList.remove('hidden');
                }
                return;
            }

            const d = productDetailsBuyNow.dataset;
            if (!d.id) return;

            try {
                await addToCartAPI(d.id, 1);
                await fetchCart();
                // Open cart modal
                cartModal.classList.remove('hidden');
                cartPanel.classList.remove('translate-x-full');
                showMessage('Added', 'Item added to cart.');
            } catch (err) {
                showMessage('Error', err.message || 'Failed to add item to cart.');
            }
        });

        // Close product details modal
        closeProductDetailsModal.addEventListener('click', () => {
            productDetailsModal.classList.add('hidden');
        });

        quantityCancelBtn && quantityCancelBtn.addEventListener('click', () => {
            quantityModal.classList.add('hidden');
            // Reset button state
            quantityConfirmBtn.disabled = false;
        });

        // Expose a simple opener function for product buttons (call from product card)
        window.openQuantityModal = function(opts) {
            // opts: { id, name, price, img }
            if (!opts || !opts.id) return;
            quantityModalImg.src = opts.img || 'https://placehold.co/80x80';
            quantityModalName.textContent = opts.name || 'Product';
            quantityModalPrice.textContent = opts.price ? `₱${Number(opts.price).toFixed(2)}` : '₱0.00';
            quantityConfirmBtn.dataset.id = opts.id;
            quantityInput.value = '1';
            quantityConfirmBtn.disabled = false;
            quantityModal.classList.remove('hidden');
        };

        // initial load - only for logged-in users
        if (isLoggedIn) {
            fetchCart();
        }

        // Add event listener to the cart button
        openCartBtn.addEventListener('click', () => {
            cartModal.classList.toggle('hidden'); // Toggle visibility
            cartPanel.classList.toggle('translate-x-full'); // Slide in/out effect
        });

        // Close cart when clicking backdrop
        const cartBackdrop = document.getElementById('cart-backdrop');
        cartBackdrop && cartBackdrop.addEventListener('click', () => {
            cartModal.classList.add('hidden');
            cartPanel.classList.add('translate-x-full');
        });

        // Close cart when clicking close button
        const closeCartBtn = document.getElementById('close-cart-btn');
        closeCartBtn && closeCartBtn.addEventListener('click', () => {
            cartModal.classList.add('hidden');
            cartPanel.classList.add('translate-x-full');
        });

        // Checkout button functionality
        const checkoutBtn = document.getElementById('checkout-btn');
        checkoutBtn && checkoutBtn.addEventListener('click', () => {
            // Check if cart has items
            if (!cartItemsContainer.textContent.includes('₱') || cartTotalEl.textContent === '₱0.00') {
                showMessage('Empty Cart', 'Please add items to your cart before checkout.');
                return;
            }
            // Redirect to shipping/checkout page
            window.location.href = 'shipping.php';
        });

        // Hide cart button and checkout for non-logged-in users
        if (!isLoggedIn) {
            const openCartBtn = document.getElementById('open-cart-btn');
            if (openCartBtn) {
                openCartBtn.style.display = 'none';
            }
        }
    });
</script>

</body>

</html>