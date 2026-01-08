<?php
// edit_rating.php
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

// Get order ID from URL
 $order_id = $_GET['order_id'] ?? null;

if (!$order_id) {
    header('Location: orders.php');
    exit;
}

// Initialize variables to avoid undefined variable warnings
 $error_message = '';
 $order = null;
 $rating = null;

try {
    // Get order details
    $stmt = $pdo->prepare("
        SELECT o.Order_ID, o.Order_Date, o.Total_Amount, o.Status, o.Shipping_Address, o.Phone_Number, o.Email,
        r.Rating, r.Review_Text, r.Review_Image, r.Rating_Date, p.Name as ProductName, p.Product_ID
        FROM orders o
        INNER JOIN order_ratings r ON o.Order_ID = r.Order_ID
        INNER JOIN product p ON r.Product_ID = p.Product_ID
        WHERE o.Order_ID = :order_id AND o.User_ID = :user_id
    ");
    
    $stmt->execute([':order_id' => $order_id, ':user_id' => $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        header('Location: orders.php');
        exit;
    }
    
    // Get all products in this order
    $stmt_products = $pdo->prepare("
        SELECT oi.Product_ID, p.Name as ProductName, oi.Quantity, oi.Price
        FROM order_item oi
        INNER JOIN product p ON oi.Product_ID = p.Product_ID
        WHERE oi.Order_ID = :order_id
    ");
    $stmt_products->execute([':order_id' => $order_id]);
    $order_products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Error fetching order details: " . $e->getMessage());
    $error_message = "Failed to load order details: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Review - Order #<?php echo htmlspecialchars($order['Order_ID']); ?> - Aurex</title>

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

        .star-rating-input {
            display: flex;
            font-size: 2.5rem;
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

        .product-item {
            background: white;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .order-summary {
            background: linear-gradient(135deg, #8B7B61 0%, #4D4C48 100%);
            color: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body class="antialiased">
    <header class="py-6 sticky top-0 z-40 bg-brand-teal/95 backdrop-blur-sm shadow-md transition-all">
        <nav class="flex justify-between items-center max-w-screen-2xl mx-auto px-4 md:px-8 lg:px-16">
            <a href="orders.php" class="font-serif text-3xl font-bold text-white flex items-center gap-2">
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
                <a href="orders.php" class="bg-white text-brand-teal px-6 py-2 rounded-full text-sm font-semibold hover:bg-gray-100 transition-colors shadow-md">
                    Back to Orders
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

        <?php if ($order): ?>
            <div class="bg-white rounded-2xl shadow-lg p-8 mb-8">
                <h1 class="font-serif text-3xl font-bold text-brand-dark mb-6">Edit Review for Order #<?php echo htmlspecialchars($order['Order_ID']); ?></h1>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                    <div>
                        <h2 class="text-xl font-bold text-brand-dark mb-4">Order Information</h2>
                        <div class="space-y-2">
                            <p class="text-brand-subtext">Order Date: <span class="text-brand-dark font-medium"><?php echo date('F d, Y \a\t h:i A', strtotime($order['Order_Date'])); ?></span></p>
                            <p class="text-brand-subtext">Total Amount: <span class="text-brand-dark font-medium">₱<?php echo number_format($order['Total_Amount'], 2); ?></span></p>
                            <p class="text-brand-subtext">Status: <span class="text-brand-dark font-medium"><?php echo htmlspecialchars($order['Status']); ?></span></p>
                        </div>
                        
                        <h2 class="text-xl font-bold text-brand-dark mb-4 mt-6">Products in this Order</h2>
                        <div class="space-y-3">
                            <?php foreach ($order_products as $product): ?>
                                <div class="product-item">
                                    <h3 class="font-semibold text-brand-dark"><?php echo htmlspecialchars($product['ProductName']); ?></h3>
                                    <p class="text-brand-subtext">Quantity: <?php echo htmlspecialchars($product['Quantity']); ?> | Price: ₱<?php echo number_format($product['Price'], 2); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div>
                        <h2 class="text-xl font-bold text-brand-dark mb-4">Current Review</h2>
                        <div class="order-summary p-6 rounded-xl">
                            <div class="flex items-center gap-4 mb-4">
                                <div class="star-rating">
                                    <?php 
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $order['Rating']) {
                                            echo '★';
                                        } else {
                                            echo '<span class="empty">★</span>';
                                        }
                                    }
                                    ?>
                                </div>
                                <span class="text-2xl font-bold"><?php echo $order['Rating']; ?>/5</span>
                            </div>
                            
                            <p class="text-white/90 mb-4"><?php echo htmlspecialchars($order['Review_Text'] ?? 'No review text provided.'); ?></p>
                            
                            <?php if (!empty($order['Review_Image'])): ?>
                                <img src="<?php echo htmlspecialchars($order['Review_Image']); ?>" 
                                     alt="Review Image" 
                                     class="review-image"
                                     onclick="openImageModal(this.src)">
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <form id="edit-rating-form">
                    <input type="hidden" id="order-id" value="<?php echo $order['Order_ID']; ?>">
                    
                    <h2 class="text-2xl font-bold text-brand-dark mb-6">Update Your Review</h2>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-brand-dark mb-2">Your Rating</label>
                        <div class="star-rating-input" id="star-rating-input">
                            <span class="star" data-rating="1">★</span>
                            <span class="star" data-rating="2">★</span>
                            <span class="star" data-rating="3">★</span>
                            <span class="star" data-rating="4">★</span>
                            <span class="star" data-rating="5">★</span>
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <label for="review-text" class="block text-sm font-medium text-brand-dark mb-2">Review Text</label>
                        <textarea id="review-text" class="review-textarea" placeholder="Share your experience with this order (optional)"><?php echo htmlspecialchars($order['Review_Text'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-6">
                        <label for="review-image" class="block text-sm font-medium text-brand-dark mb-2">Update Photo (Optional)</label>
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
                        <?php if (!empty($order['Review_Image'])): ?>
                            <p class="text-sm text-brand-subtext mt-2">Current image will be replaced if you upload a new one.</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="flex gap-4">
                        <button type="submit" class="submit-rating-btn">Update Review</button>
                        <a href="orders.php" class="px-6 py-2.5 rounded-full font-semibold text-sm bg-gray-200 text-brand-dark hover:bg-gray-300 transition-colors">Cancel</a>
                    </div>
                </form>
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
            const editRatingForm = document.getElementById('edit-rating-form');
            const starRatingInput = document.getElementById('star-rating-input');
            const reviewText = document.getElementById('review-text');
            const reviewImageInput = document.getElementById('review-image');
            const imagePreview = document.getElementById('image-preview');
            const previewImg = document.getElementById('preview-img');
            const removeImageBtn = document.getElementById('remove-image');
            
            let selectedRating = <?php echo $order['Rating'] ?? 0; ?>;
            let imageData = null;
            
            // Initialize star display
            updateStarDisplay();
            
            // Image upload handling
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
            
            // Form submission
            editRatingForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const orderId = document.getElementById('order-id').value;
                
                if (selectedRating === 0) {
                    alert('Please select a rating before submitting.');
                    return;
                }
                
                const submitBtn = e.target.querySelector('button[type="submit"]');
                submitBtn.disabled = true;
                submitBtn.textContent = 'Updating...';
                
                try {
                    const response = await fetch('update_rating.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ 
                            order_id: orderId, 
                            rating: selectedRating, 
                            review_text: reviewText.value,
                            review_image: imageData
                        })
                    });
                    const data = await response.json();
                    
                    if (data.success) {
                        alert('✅ Your review has been updated successfully!');
                        window.location.href = 'orders.php?status=View Ratings';
                    } else {
                        alert('❌ Failed to update review: ' + data.message);
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Update Review';
                    }
                } catch (error) {
                    console.error('Error updating rating:', error);
                    alert('⚠️ An error occurred while updating your review');
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Update Review';
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