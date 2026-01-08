<?php
// view_review.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['User_ID'])) {
    header('Location: login.php');
    exit;
}

require_once 'db_connect.php';

 $user_id = $_SESSION['User_ID'];
 $userName = $_SESSION['Name'] ?? 'Customer';
 $greetingName = htmlspecialchars($userName);

// Get product ID from URL
 $product_id = $_GET['product_id'] ?? null;

if (!$product_id) {
    header('Location: index_user.php');
    exit;
}

// Initialize variables to avoid undefined variable warnings
 $error_message = '';
 $product = null;
 $reviews = [];
 $rating_distribution = [0, 0, 0, 0, 0];

// Get product details
try {
    $stmt = $pdo->prepare("
        SELECT 
            p.Product_ID, 
            p.Name AS ProductName, 
            p.Description, 
            p.Price, 
            p.Images, 
            p.Avg_Rating,
            p.Rating_Count,
            c.Category_Name
        FROM product p
        LEFT JOIN category c ON p.Category_ID = c.Category_ID
        WHERE p.Product_ID = :product_id
    ");
    
    $stmt->execute([':product_id' => $product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        header('Location: index_user.php');
        exit;
    }
    
    // Get all reviews for this product - including Review_Image
    $stmt_reviews = $pdo->prepare("
        SELECT 
            r.Rating, 
            r.Review_Text, 
            r.Rating_Date,
            r.Review_Image,
            u.Name as UserName,
            u.User_ID,
            o.Order_ID
        FROM order_ratings r
        INNER JOIN user u ON r.User_ID = u.User_ID
        INNER JOIN orders o ON r.Order_ID = o.Order_ID
        WHERE r.Product_ID = :product_id
        ORDER BY r.Rating_Date DESC
    ");
    
    $stmt_reviews->execute([':product_id' => $product_id]);
    $reviews = $stmt_reviews->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate rating distribution
    $rating_distribution = [0, 0, 0, 0, 0]; // 1 star, 2 stars, etc.
    foreach ($reviews as $review) {
        $rating_distribution[$review['Rating'] - 1]++;
    }
    
} catch (PDOException $e) {
    error_log("Error fetching product reviews: " . $e->getMessage());
    $error_message = "Failed to load reviews: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Reviews - <?php echo htmlspecialchars($product['ProductName'] ?? 'Product'); ?> - Aurex</title>

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
            font-size: 1.2rem;
            display: inline-flex;
            align-items: center;
        }

        .star-rating .empty {
            color: #d1d5db;
        }

        .review-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
        }

        .review-card:hover {
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .rating-bar {
            height: 8px;
            background-color: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }

        .rating-fill {
            height: 100%;
            background-color: #fbbf24;
            border-radius: 4px;
            transition: width 0.5s ease;
        }

        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background-color: #8B7B61;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.2rem;
        }

        .filter-btn {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.2s;
            cursor: pointer;
            border: 1px solid #e5e7eb;
            background-color: white;
        }

        .filter-btn:hover {
            background-color: #f3f4f6;
        }

        .filter-btn.active {
            background-color: #8B7B61;
            color: white;
            border-color: #8B7B61;
        }

        .product-image {
            max-height: 300px;
            object-fit: contain;
        }

        .rating-summary-card {
            background: linear-gradient(135deg, #8B7B61 0%, #4D4C48 100%);
            color: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }

        .rating-number {
            font-size: 3rem;
            font-weight: 800;
            line-height: 1;
        }

        .no-reviews {
            text-align: center;
            padding: 3rem;
            color: #7A7977;
        }

        .review-date {
            font-size: 0.875rem;
            color: #7A7977;
        }

        .review-text {
            margin-top: 1rem;
            line-height: 1.6;
        }

        .helpful-btn {
            padding: 0.25rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            background-color: #f3f4f6;
            color: #4D4C48;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .helpful-btn:hover {
            background-color: #e5e7eb;
        }

        .helpful-btn.active {
            background-color: #8B7B61;
            color: white;
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
            <a href="index_user.php" class="font-serif text-3xl font-bold text-white flex items-center gap-2">
                <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z" fill="white"/>
                    <path d="M12 2l1.5 4.5L18 8l-4.5 1.5L12 14l-1.5-4.5L6 8l4.5-1.5L12 2z" fill="white"/>
                </svg>
                Aurex
            </a>

            <div class="flex items-center gap-5">
                <span class="text-white font-semibold text-base hidden md:inline-block">
                    Welcome, <?php echo $greetingName; ?>!
                </span>
                <a href="orders.php" class="text-gray-100 hover:text-white transition-colors" title="My Orders">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                </a>
                <a href="index_user.php" class="bg-white text-brand-teal px-6 py-2 rounded-full text-sm font-semibold hover:bg-gray-100 transition-colors shadow-md">
                    Back to Products
                </a>
            </div>
        </nav>
    </header>

    <div class="max-w-screen-2xl mx-auto px-4 md:px-8 lg:px-16 py-8">
        <?php if (!empty($error_message)): ?>
            <div class="bg-red-50 border-2 border-red-300 text-red-900 px-6 py-5 rounded-xl mb-8 flex items-start gap-3 shadow-sm">
                <svg class="w-6 h-6 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                <span class="font-semibold"><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($product): ?>
            <!-- Product Header -->
            <div class="bg-white rounded-2xl shadow-lg p-8 mb-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <img src="<?php echo htmlspecialchars($product['Images'] ?? 'https://placehold.co/400x400'); ?>" 
                             alt="<?php echo htmlspecialchars($product['ProductName']); ?>" 
                             class="w-full product-image rounded-lg">
                    </div>
                    <div>
                        <h1 class="font-serif text-3xl font-bold text-brand-dark mb-4"><?php echo htmlspecialchars($product['ProductName']); ?></h1>
                        <p class="text-brand-subtext mb-4"><?php echo htmlspecialchars($product['Category_Name']); ?></p>
                        <p class="text-2xl font-semibold text-brand-teal mb-6">₱<?php echo number_format($product['Price'], 2); ?></p>
                        <p class="text-brand-subtext mb-6"><?php echo htmlspecialchars($product['Description'] ?? 'No description available.'); ?></p>
                        
                        <div class="flex items-center gap-4">
                            <div class="star-rating">
                                <?php 
                                $avg_rating = $product['Avg_Rating'] ?? 0;
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= round($avg_rating)) {
                                        echo '★';
                                    } else {
                                        echo '<span class="empty">★</span>';
                                    }
                                }
                                ?>
                            </div>
                            <span class="text-lg font-semibold"><?php echo number_format($avg_rating, 1); ?></span>
                            <span class="text-brand-subtext">(<?php echo $product['Rating_Count'] ?? 0; ?> reviews)</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reviews Section -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Rating Summary -->
                <div class="lg:col-span-1">
                    <div class="rating-summary-card">
                        <h2 class="text-2xl font-bold mb-6">Rating Summary</h2>
                        <div class="text-center mb-8">
                            <div class="rating-number"><?php echo number_format($avg_rating, 1); ?></div>
                            <div class="star-rating justify-center mb-2">
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
                            <div class="text-white/80"><?php echo $product['Rating_Count'] ?? 0; ?> reviews</div>
                        </div>
                        
                        <!-- Rating Distribution -->
                        <div class="space-y-3">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center gap-1 w-16">
                                        <span><?php echo $i; ?></span>
                                        <span class="text-yellow-300">★</span>
                                    </div>
                                    <div class="flex-1">
                                        <div class="rating-bar">
                                            <div class="rating-fill" style="width: <?php echo $product['Rating_Count'] > 0 ? ($rating_distribution[$i-1] / $product['Rating_Count']) * 100 : 0; ?>%"></div>
                                        </div>
                                    </div>
                                    <div class="w-12 text-right text-white/80">
                                        <?php echo $rating_distribution[$i-1]; ?>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>

                <!-- Reviews List -->
                <div class="lg:col-span-2">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold text-brand-dark">Customer Reviews</h2>
                        <div class="flex gap-2">
                            <button class="filter-btn active" data-filter="all">All</button>
                            <button class="filter-btn" data-filter="5">5 Stars</button>
                            <button class="filter-btn" data-filter="4">4 Stars</button>
                            <button class="filter-btn" data-filter="3">3 Stars</button>
                            <button class="filter-btn" data-filter="2">2 Stars</button>
                            <button class="filter-btn" data-filter="1">1 Star</button>
                        </div>
                    </div>

                    <?php if (empty($reviews)): ?>
                        <div class="no-reviews">
                            <svg class="w-24 h-24 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                            </svg>
                            <h3 class="text-xl font-semibold text-brand-dark mb-2">No Reviews Yet</h3>
                            <p class="text-brand-subtext">Be the first to review this product!</p>
                        </div>
                    <?php else: ?>
                        <div id="reviews-container">
                            <?php foreach ($reviews as $review): ?>
                                <div class="review-card" data-rating="<?php echo $review['Rating']; ?>">
                                    <div class="flex items-start gap-4">
                                        <div class="user-avatar">
                                            <?php echo strtoupper(substr($review['UserName'], 0, 1)); ?>
                                        </div>
                                        <div class="flex-grow">
                                            <div class="flex justify-between items-start mb-2">
                                                <div>
                                                    <h4 class="font-semibold text-brand-dark"><?php echo htmlspecialchars($review['UserName']); ?></h4>
                                                    <div class="star-rating">
                                                        <?php 
                                                        for ($i = 1; $i <= 5; $i++) {
                                                            if ($i <= $review['Rating']) {
                                                                echo '★';
                                                            } else {
                                                                echo '<span class="empty">★</span>';
                                                            }
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                                <div class="review-date">
                                                    <?php echo date('M d, Y', strtotime($review['Rating_Date'])); ?>
                                                </div>
                                            </div>
                                            <div class="review-text">
                                                <?php echo htmlspecialchars($review['Review_Text'] ?? 'No review text provided.'); ?>
                                            </div>
                                            
                                            <!-- Display review image if available -->
                                            <?php if (!empty($review['Review_Image'])): ?>
                                                <img src="<?php echo htmlspecialchars($review['Review_Image']); ?>" 
                                                     alt="Review Image" 
                                                     class="review-image"
                                                     onclick="openImageModal(this.src)">
                                            <?php endif; ?>
                                            
                                            <div class="flex items-center gap-4 mt-4">
                                                <span class="text-sm text-brand-subtext">Order #<?php echo $review['Order_ID']; ?></span>
                                                <button class="helpful-btn">
                                                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"></path>
                                                    </svg>
                                                    Helpful
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
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
        document.addEventListener('DOMContentLoaded', () => {
            // Filter functionality
            const filterButtons = document.querySelectorAll('.filter-btn');
            const reviewCards = document.querySelectorAll('.review-card');
            
            filterButtons.forEach(button => {
                button.addEventListener('click', () => {
                    // Update active button
                    filterButtons.forEach(btn => btn.classList.remove('active'));
                    button.classList.add('active');
                    
                    const filterValue = button.dataset.filter;
                    
                    // Filter reviews
                    reviewCards.forEach(card => {
                        if (filterValue === 'all' || card.dataset.rating === filterValue) {
                            card.style.display = 'block';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                });
            });
            
            // Helpful button functionality
            const helpfulButtons = document.querySelectorAll('.helpful-btn');
            helpfulButtons.forEach(button => {
                button.addEventListener('click', () => {
                    button.classList.toggle('active');
                    if (button.classList.contains('active')) {
                        button.innerHTML = `
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"></path>
                            </svg>
                            Helpful
                        `;
                    } else {
                        button.innerHTML = `
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"></path>
                            </svg>
                            Helpful
                        `;
                    }
                });
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