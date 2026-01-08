<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['User_ID'])) {
    header('Location: login.php');
    exit;
}

require_once 'db_connect.php';
require_once 'utilities.php';

// Get order ID from URL
 $order_id = $_GET['order_id'] ?? null;

if (!$order_id) {
    header('Location: orders.php');
    exit;
}

 $user_id = $_SESSION['User_ID'];
 $error_message = '';
 $order_details = null;
 $rating_details = null;

try {
    // Get order and rating details
    $stmt = $pdo->prepare("
        SELECT o.Order_ID, o.Order_Date, o.Total_Amount, o.Status, o.Shipping_Address, o.Phone_Number, o.Email,
        r.Rating, r.Review_Text, r.Review_Image, r.Rating_Date, p.Name as ProductName, p.Product_ID, 
        p.Description, p.Price, p.Images
        FROM orders o
        INNER JOIN order_ratings r ON o.Order_ID = r.Order_ID
        INNER JOIN product p ON r.Product_ID = p.Product_ID
        WHERE o.Order_ID = :order_id AND o.User_ID = :user_id
    ");
    $stmt->execute([':order_id' => $order_id, ':user_id' => $user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        $error_message = "Rating not found or you don't have permission to view it.";
    } else {
        $order_details = $result;
        $rating_details = [
            'rating' => $result['Rating'],
            'review_text' => $result['Review_Text'],
            'review_image' => $result['Review_Image'],
            'rating_date' => $result['Rating_Date']
        ];
    }
} catch (PDOException $e) {
    error_log("Rating Fetch Error: " . $e->getMessage());
    $error_message = "Failed to load rating: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Rating - Jewelry</title>

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

        .star-rating {
            color: #fbbf24;
            font-size: 2.5rem;
            display: inline-flex;
            align-items: center;
        }

        .star-rating .empty {
            color: #d1d5db;
        }

        .product-image {
            max-width: 100%;
            height: auto;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
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
                <a href="orders.php" class="bg-white text-brand-teal px-6 py-2 rounded-full text-sm font-semibold hover:bg-gray-100 transition-colors shadow-md">
                    Back to Orders
                </a>
            </div>
        </nav>
    </header>

    <div class="max-w-screen-2xl mx-auto px-4 md:px-8 lg:px-16 py-8">
        <?php if ($error_message): ?>
            <div class="bg-red-50 border-2 border-red-300 text-red-900 px-6 py-5 rounded-xl mb-8 flex items-start gap-3 shadow-sm">
                <svg class="w-6 h-6 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                <span class="font-semibold"><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php elseif ($order_details): ?>
            <div class="bg-white rounded-2xl shadow-lg p-8 border-2 border-gray-100">
                <div class="mb-8">
                    <h1 class="font-serif text-4xl font-bold text-brand-dark mb-3">Your Rating</h1>
                    <p class="text-brand-subtext">Order #<?php echo htmlspecialchars($order_details['Order_ID']); ?></p>
                    <p class="text-sm text-brand-subtext font-medium">📅 <?php echo date('F d, Y \a\t h:i A', strtotime($order_details['Order_Date'])); ?></p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                    <!-- Product Details -->
                    <div>
                        <h2 class="text-2xl font-bold text-brand-dark mb-4">Product Details</h2>
                        <div class="flex flex-col md:flex-row gap-6">
                            <div class="md:w-1/2">
                                <img src="<?php echo htmlspecialchars($order_details['Images']); ?>" alt="<?php echo htmlspecialchars($order_details['ProductName']); ?>" class="product-image">
                            </div>
                            <div class="md:w-1/2">
                                <h3 class="text-xl font-semibold text-brand-dark mb-2"><?php echo htmlspecialchars($order_details['ProductName']); ?></h3>
                                <p class="text-2xl font-bold text-brand-teal mb-4">₱<?php echo number_format($order_details['Price'], 2); ?></p>
                                <p class="text-brand-subtext mb-4"><?php echo htmlspecialchars($order_details['Description']); ?></p>
                                <a href="index_user.php?product_id=<?php echo $order_details['Product_ID']; ?>#section-products" class="inline-block bg-brand-teal text-white px-6 py-2 rounded-full text-sm font-semibold hover:bg-brand-dark transition-colors shadow-md">
                                    View Product
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Rating Details -->
                    <div>
                        <h2 class="text-2xl font-bold text-brand-dark mb-4">Your Rating</h2>
                        <div class="bg-gray-50 p-6 rounded-xl">
                            <div class="star-rating mb-4">
                                <?php 
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= $rating_details['rating']) {
                                        echo '★';
                                    } else {
                                        echo '<span class="empty">★</span>';
                                    }
                                }
                                ?>
                            </div>
                            
                            <?php if ($rating_details['review_text']): ?>
                                <div class="mb-4">
                                    <h3 class="font-semibold text-brand-dark mb-2">Your Review:</h3>
                                    <p class="text-brand-subtext"><?php echo htmlspecialchars($rating_details['review_text']); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($rating_details['review_image']): ?>
                                <div class="mb-4">
                                    <h3 class="font-semibold text-brand-dark mb-2">Your Photo:</h3>
                                    <img src="<?php echo htmlspecialchars($rating_details['review_image']); ?>" alt="Review Image" class="review-image" onclick="openImageModal(this.src)">
                                </div>
                            <?php endif; ?>
                            
                            <p class="text-sm text-gray-500">Rated on: <?php echo date('F d, Y \a\t h:i A', strtotime($rating_details['rating_date'])); ?></p>
                        </div>
                        
                        <div class="mt-6">
                            <a href="edit_rating.php?order_id=<?php echo $order_details['Order_ID']; ?>" class="inline-block bg-blue-500 text-white px-6 py-2 rounded-full text-sm font-semibold hover:bg-blue-600 transition-colors shadow-md">
                                Edit Your Rating
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="image-modal">
        <span class="close">&times;</span>
        <img class="modal-content" id="img01">
    </div>

    <script>
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
    </script>
</body>
</html>