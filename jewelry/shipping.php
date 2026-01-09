<?php
// shipping.php
session_start();

// NOTE: Ensure this file exists and contains the PDO connection $pdo
require_once 'db_connect.php'; 

$user_id = $_SESSION['User_ID'] ?? null;

if (!$user_id) {
    header('Location: login.php');
    exit;
}

$user_data = [];
$cart_items = [];
$cart_total = 0;

try {
    // 1. Fetch User Data for form pre-fill
    $stmt_user = $pdo->prepare("SELECT Name, Email, Phone, Address FROM user WHERE User_ID = :user_id");
    $stmt_user->execute([':user_id' => $user_id]);
    $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

    // 2. Get Cart ID
    $stmt_cart_id = $pdo->prepare("SELECT Cart_ID FROM cart WHERE User_ID = :user_id");
    $stmt_cart_id->execute([':user_id' => $user_id]);
    $cart_id = $stmt_cart_id->fetchColumn();

    if ($cart_id) {
        // 3. Fetch Cart Items
        $stmt_cart = $pdo->prepare("
            SELECT 
                ci.Product_ID, 
                ci.Quantity, 
                p.Name, 
                p.Price, 
                p.Images 
            FROM cart_item ci
            JOIN product p ON ci.Product_ID = p.Product_ID
            WHERE ci.Cart_ID = :cart_id
        ");
        $stmt_cart->execute([':cart_id' => $cart_id]);
        $cart_items = $stmt_cart->fetchAll(PDO::FETCH_ASSOC);

        // Calculate total
        $cart_total = array_reduce($cart_items, function($sum, $item) {
            return $sum + ($item['Price'] * (int)$item['Quantity']);
        }, 0);
    }

    // Calculate shipping and discounts based on user history
    $shipping = 150;
    $discount = 0;

    if ($user_id) {
        // Get user order history
        $stmt_history = $pdo->prepare("SELECT COUNT(*) as order_count, COALESCE(SUM(Total_Amount), 0) as total_spent FROM orders WHERE User_ID = :user_id");
        $stmt_history->execute([':user_id' => $user_id]);
        $history = $stmt_history->fetch(PDO::FETCH_ASSOC);
        $order_count = $history['order_count'];
        $total_spent = $history['total_spent'];

        // Frequent buyer: >3 orders, 10% discount
        if ($order_count > 3) {
            $discount += 0.1 * $cart_total;
        }

        // Big spender: total spent >3000, free shipping
        if ($total_spent > 3000) {
            $shipping = 0;
        }
    }

    // Determine discount reasons for display
    $discount_reasons = [];
    if ($order_count > 3) {
        $discount_reasons[] = "Frequent Buyer (10% off)";
    }
    if ($total_spent > 3000) {
        $discount_reasons[] = "Big Spender (Free Shipping)";
    }

    $final_total = $cart_total + $shipping - $discount;
    
} catch (PDOException $e) {
    error_log("Shipping PHP Error: " . $e->getMessage());
    // Fallback to empty values
}

// Prepare cart data for JavaScript
$cart_json = json_encode($cart_items);
$subtotal_formatted = number_format($cart_total, 2);
$shipping_formatted = number_format($shipping, 2);
$discount_formatted = number_format($discount, 2);
$final_total_formatted = number_format($final_total, 2);

// Split address for form (assuming simple format like 'Street, City, Province Zip')
$address_parts = explode(',', $user_data['Address'] ?? '');
$street_address = trim($address_parts[0] ?? '');
$city = trim($address_parts[1] ?? '');
$province_zip = trim($address_parts[2] ?? '');
// You might need more advanced parsing here for province/zip depending on your stored address format.

// Simple logic to disable checkout if cart is empty
$disable_checkout = empty($cart_items);
?>

<input type="hidden" id="php-shipping-fee" value="<?php echo $shipping; ?>">
<input type="hidden" id="php-discount" value="<?php echo $discount; ?>">
<input type="hidden" id="php-final-total" value="<?php echo $final_total; ?>">
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Checkout - Jewelery</title>

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
}
.form-input {
width: 100%;
border-radius: 0.375rem;
border: 1px solid #D1D5DB;
padding: 0.625rem 0.875rem;
background-color: white;
transition: all 0.2s;
font-size: 0.9375rem;
}
.form-input:focus {
outline: none;
border-color: #8B7B61;
box-shadow: 0 0 0 2px rgba(139, 123, 97, 0.3);
}
.form-label {
display: block;
margin-bottom: 0.375rem;
font-size: 0.8125rem;
font-weight: 500;
color: #4D4C48;
}
.radio-card {
display: flex;
align-items: center;
padding: 0.875rem 1rem;
border: 1px solid #D1D5DB;
border-radius: 0.5rem;
cursor: pointer;
transition: all 0.2s ease-in-out;
background-color: white;
}
.radio-card:hover {
border-color: #A0907A;
box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
}
.radio-card:has(.radio-input:checked) {
border-color: #8B7B61;
background-color: #8B7B61;
box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}
.radio-card:has(.radio-input:checked) span {
color: white;
font-weight: 600;
}
.radio-input {
appearance: none;
width: 1.25rem;
height: 1.25rem;
border: 2px solid #D1D5DB;
border-radius: 50%;
margin-right: 0.75rem;
position: relative;
flex-shrink: 0;
transition: all 0.2s ease-in-out;
}
.radio-input:checked {
border-color: white;
}
.radio-input:checked::after {
content: '';
display: block;
width: 0.625rem;
height: 0.625rem;
background-color: white;
border-radius: 50%;
position: absolute;
top: 50%;
left: 50%;
transform: translate(-50%, -50%);
}
.radio-card-text {
font-size: 0.8125rem;
font-weight: 500;
color: #4D4C48;
transition: color 0.2s ease-in-out;
}
.form-background-shadow {
box-shadow: 
 0 10px 25px -5px rgba(0, 0, 0, 0.15),
 0 -5px 15px -3px rgba(0, 0, 0, 0.06),
 5px 0 20px -3px rgba(0, 0, 0, 0.08),
 -5px 0 20px -3px rgba(0, 0, 0, 0.08);
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
.success-checkmark {
 margin: 0 auto 1.5rem;
 width: 80px;
 height: 80px;
}
.checkmark {
 width: 80px;
 height: 80px;
 border-radius: 50%;
 display: block;
 stroke-width: 4;
 stroke: #22C55E;
 stroke-miterlimit: 10;
 animation: scale .3s ease-in-out .9s both;
}
.checkmark__circle {
 stroke-dasharray: 166;
 stroke-dashoffset: 166;
 stroke-width: 4;
 stroke-miterlimit: 10;
 stroke: #22C55E;
 fill: none;
 animation: stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
}
.checkmark__check {
 transform-origin: 50% 50%;
 stroke-dasharray: 48;
 stroke-dashoffset: 48;
 stroke: #22C55E;
 animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards;
}
@keyframes stroke {
 100% {
  stroke-dashoffset: 0;
 }
}
@keyframes scale {
 0%, 100% {
  transform: none;
 }
 50% {
  transform: scale3d(1.1, 1.1, 1);
 }
}
.computation-row {
 padding: 0.625rem 0;
 border-bottom: 1px dashed #E5E7EB;
}
.computation-row:last-child {
 border-bottom: none;
}
</style>
</head>
<body class="antialiased">

<div id="review-modal" class="modal hidden fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
<div class="modal-panel relative w-full max-w-md bg-white rounded-lg shadow-xl p-5 md:p-6">
<h3 class="text-2xl font-serif font-bold text-brand-dark mb-4">Review Your Order</h3>
<div class="space-y-3">
<div class="bg-brand-beige p-3 rounded-lg border border-gray-100 shadow-sm">
<h4 class="font-semibold text-brand-dark mb-2 text-sm">Shipping Details</h4>
<p class="text-brand-text text-xs mb-1"><strong class="font-medium">Name:</strong> <span id="review-name"></span></p>
<p class="text-brand-text text-xs mb-1"><strong class="font-medium">Email:</strong> <span id="review-email"></span></p>
<p class="text-brand-text text-xs mb-1"><strong class="font-medium">Phone:</strong> <span id="review-phone"></span></p>
<p class="text-brand-text text-xs"><strong class="font-medium">Address:</strong> <span id="review-address"></span></p>
</div>
<div class="bg-brand-beige p-3 rounded-lg border border-gray-100 shadow-sm">
<h4 class="font-semibold text-brand-dark mb-2 text-sm">Payment & Total</h4>
<p class="text-brand-text text-xs mb-2"><strong class="font-medium">Payment Method:</strong> <span id="review-payment"></span></p>
<div class="pt-2 border-t border-gray-200">
<div class="flex justify-between text-xs text-brand-subtext mb-1">
<span>Subtotal:</span>
<span id="review-subtotal"></span>
</div>
<div class="flex justify-between text-xs text-brand-subtext mb-2">
<span>Shipping:</span>
<span id="review-shipping"></span> 
</div>
<div class="flex justify-between text-base font-bold text-brand-dark">
<span>Total:</span>
<span id="review-total"></span>
</div>
</div>
</div>
</div>
<div class="flex gap-3 mt-6">
<button id="edit-details-btn" class="w-1/2 bg-white text-brand-dark border border-gray-300 py-2 rounded-full text-sm font-semibold hover:bg-gray-50 transition-colors">
Edit Details
</button>
<button id="confirm-order-btn" class="w-1/2 bg-brand-teal text-white py-2 rounded-full text-sm font-semibold hover:bg-opacity-90 transition-colors">
Confirm Order
</button>
</div>
</div>
</div>


<header class="py-5 border-b-0 bg-brand-teal shadow-md">
<div class="max-w-7xl mx-auto px-4 md:px-6 lg:px-8">
<nav class="flex justify-between items-center">
<a href="index_user.php" class="font-serif text-2xl font-bold text-white">Aurex</a>
<a href="index_user.php" class="text-sm font-medium text-white hover:text-gray-200">&larr; Back to Store</a>
</nav>
</div>
</header>

<div class="max-w-7xl mx-auto px-4 py-8 sm:py-12">

<div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

<div class="lg:col-span-3">
<div id="shipping-section" class="bg-white p-6 md:p-8 rounded-xl form-background-shadow <?php echo $disable_checkout ? 'hidden' : ''; ?>">
<h1 class="font-serif text-3xl font-bold text-brand-dark mb-6">Shipping Details</h1>
<form id="shipping-form" class="space-y-4">
<h2 class="text-lg font-serif font-bold text-brand-dark pb-2 border-b border-gray-200">Contact Information</h2>
<div>
<label for="email" class="form-label">Email Address</label>
<input type="email" id="email" name="email" class="form-input" required value="<?php echo htmlspecialchars($user_data['Email'] ?? ''); ?>">
</div>

<h2 class="text-lg font-serif font-bold text-brand-dark pt-3 pb-2 border-b border-gray-200">Shipping Address</h2>

<div>
<label for="full-name" class="form-label">Full Name</label>
<input type="text" id="full-name" name="full-name" class="form-input" required value="<?php echo htmlspecialchars($user_data['Name'] ?? ''); ?>">
</div>

<div>
<label for="address" class="form-label">Address (Street/Barangay/House No.)</label>
<input type="text" id="address" name="address" class="form-input" required value="<?php echo htmlspecialchars($street_address); ?>">
</div>

<div class="mb-4">
<label class="form-label">Select Location on Map</label>
<div id="map" class="w-full h-64 rounded-lg border-2 border-gray-300 mb-2"></div>
<p class="text-sm text-gray-600 mb-2">Click on the map to set your delivery location</p>
<div class="grid grid-cols-2 gap-3">
<div>
<label class="form-label text-sm">Latitude</label>
<input type="text" id="latitude" name="latitude" class="form-input text-sm" readonly>
</div>
<div>
<label class="form-label text-sm">Longitude</label>
<input type="text" id="longitude" name="longitude" class="form-input text-sm" readonly>
</div>
</div>
</div>

<div>
<label for="city" class="form-label">City</label>
<input type="text" id="city" name="city" class="form-input" required value="<?php echo htmlspecialchars($city); ?>">
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
<div>
<label for="province" class="form-label">Province / State</label>
<input type="text" id="province" name="province" class="form-input" required value="<?php echo htmlspecialchars($province_zip); ?>">
</div>
<div>
<label for="zip" class="form-label">Postal Code</label>
<input type="text" id="zip" name="zip" class="form-input" required>
</div>
</div>

<div>
<label for="phone" class="form-label">Phone Number</label>
<input type="tel" id="phone" name="phone" class="form-input" required value="<?php echo htmlspecialchars($user_data['Phone'] ?? ''); ?>">
</div>

<h2 class="text-lg font-serif font-bold text-brand-dark pt-3 pb-2 border-b border-gray-200">Payment Method</h2>
<div class="space-y-2">
<label class="radio-card">
<input type="radio" name="payment-method" value="Cash on Delivery" class="radio-input" checked>
<span class="radio-card-text">Cash on Delivery (COD)</span>
</label>
<label class="radio-card">
<input type="radio" name="payment-method" value="Bank Transfer" class="radio-input">
<span class="radio-card-text">Bank Transfer / Card</span>
</label>
</div>

<div id="payment-details-section" class="hidden space-y-3">
<h2 class="text-lg font-serif font-bold text-brand-dark pt-3 pb-2 border-b border-gray-200">Card Details</h2>
<div>
<label for="cardholder-name" class="form-label">Cardholder Name</label>
<input type="text" id="cardholder-name" name="cardholder-name" class="form-input" placeholder="John Doe">
</div>
<div>
<label for="card-number" class="form-label">Card Number</label>
<input type="text" id="card-number" name="card-number" class="form-input" placeholder="1234 5678 9012 3456">
</div>
<div class="grid grid-cols-2 gap-3">
<div>
<label for="expiry-date" class="form-label">Expiry Date</label>
<input type="text" id="expiry-date" name="expiry-date" class="form-input" placeholder="MM/YY">
</div>
<div>
<label for="cvv" class="form-label">CVV</label>
<input type="text" id="cvv" name="cvv" class="form-input" placeholder="123">
</div>
</div>
</div>

<div class="pt-4">
<button type="submit" class="w-full bg-brand-teal text-white py-3 rounded-full font-semibold text-base hover:bg-opacity-90 transition-colors">
Place Order
</button>
</div>
</form>
</div>
</div>

<div class="lg:col-span-2">
<div id="order-summary-section" class="bg-white p-6 rounded-xl form-background-shadow lg:sticky lg:top-24">
<h2 class="font-serif text-2xl font-bold text-brand-dark mb-6">Order Summary</h2>

<?php if ($discount > 0 || $shipping == 0): ?>
<div class="bg-green-50 border-2 border-green-300 text-green-900 px-4 py-3 rounded-xl mb-6 flex items-start gap-3">
<svg class="w-6 h-6 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
</svg>
<div>
<p class="font-semibold">Congratulations! You qualify for special discounts:</p>
<ul class="list-disc list-inside mt-1">
<?php foreach ($discount_reasons as $reason): ?>
<li><?php echo htmlspecialchars($reason); ?></li>
<?php endforeach; ?>
</ul>
</div>
</div>
<?php endif; ?>
  
<div id="cart-summary-items" class="space-y-3 border-b border-gray-200 pb-4 mb-4 max-h-80 overflow-y-auto">
  <?php if ($disable_checkout): ?>
    <p class="text-center text-brand-subtext text-sm">Your cart is empty. Please return to the store to add items.</p>
  <?php else: ?>
    <?php foreach ($cart_items as $item): 
      $item_total = $item['Price'] * (int)$item['Quantity'];
      $item_img_url = !empty($item['Images']) ? htmlspecialchars($item['Images']) : 'https://placehold.co/80x80/D4C9BC/3A3A3A?text=' . urlencode(substr($item['Name'], 0, 4));
    ?>
    <div class="flex gap-2.5 items-start">
      <img src="<?php echo $item_img_url; ?>" alt="<?php echo htmlspecialchars($item['Name']); ?>" class="w-14 h-14 rounded-lg object-cover border flex-shrink-0">
      <div class="flex-grow min-w-0">
        <h3 class="font-semibold text-brand-dark text-xs truncate"><?php echo htmlspecialchars($item['Name']); ?></h3>
        <p class="text-xs text-brand-subtext">Qty: <?php echo htmlspecialchars($item['Quantity']); ?></p>
        <p class="font-semibold text-brand-dark text-xs mt-0.5">₱<?php echo number_format($item_total, 2); ?></p>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<div class="space-y-0 mb-4">
  <div class="computation-row flex justify-between items-center text-sm">
    <span class="text-brand-subtext">Subtotal</span>
    <span id="subtotal-amount" class="text-brand-dark font-medium">₱<?php echo $subtotal_formatted; ?></span>
  </div>
  <div class="computation-row flex justify-between items-center text-sm">
    <span class="text-brand-subtext">Shipping Fee</span>
    <span id="shipping-amount" class="text-brand-dark font-medium"><?php echo $shipping == 0 ? 'FREE' : '₱' . $shipping_formatted; ?></span>
  </div>
  <?php if ($discount > 0): ?>
  <div class="computation-row flex justify-between items-center text-sm">
    <span class="text-green-600"><?php echo implode(', ', $discount_reasons); ?></span>
    <span class="text-green-600 font-medium">-₱<?php echo $discount_formatted; ?></span>
  </div>
  <?php endif; ?>
  <div class="computation-row flex justify-between items-center text-sm">
    <span class="text-brand-subtext">Tax</span>
    <span id="tax-amount" class="text-brand-dark font-medium">₱0.00</span>
  </div>
</div>

<div class="flex justify-between items-center pt-4 pb-4 border-t-2 border-brand-teal">
  <span class="text-lg font-serif font-bold text-brand-dark">Total Amount</span>
  <span id="summary-total" class="text-xl font-serif font-bold text-brand-teal">₱<?php echo $final_total_formatted; ?></span>
</div>

<div class="mt-4 p-3 bg-brand-beige rounded-lg">
  <p class="text-xs text-brand-subtext text-center">
    <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
    </svg>
    Secure checkout powered by SSL encryption
  </p>
</div>
</div>
</div>

</div>

</div>

<div id="confirmation-section" class="hidden">
 <div class="max-w-2xl mx-auto px-4 py-16">
  <div class="text-center bg-white p-8 md:p-12 rounded-xl form-background-shadow">
   <div class="success-checkmark">
    <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
     <circle class="checkmark__circle" cx="26" cy="26" r="25" fill="none"/>
     <path class="checkmark__check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
    </svg>
   </div>

   <h1 class="font-serif text-4xl font-bold text-brand-dark mb-4">Thank You!</h1>
   <p class="text-lg text-brand-subtext mb-8">Your order has been confirmed.</p>
   
   <a href="orders.php" class="inline-block bg-brand-teal text-white px-10 py-3 rounded-full font-semibold hover:bg-opacity-90 transition-colors">
    View Order
   </a>
  </div>
 </div>
</div>

<footer class="bg-white mt-12 sm:mt-16 border-t border-gray-100">
<div class="max-w-7xl mx-auto px-4 md:px-6 lg:px-8 py-12">
<div class="text-center text-brand-subtext text-sm">
<p>&copy; 2024 Jewellery. All rights reserved.</p>
</div>
</div>
</footer>

<script>
document.addEventListener('DOMContentLoaded', async () => {
 const shippingForm = document.getElementById('shipping-form');
 const shippingSection = document.getElementById('shipping-section');
 const confirmationSection = document.getElementById('confirmation-section');

 const summaryTotalEl = document.getElementById('summary-total');
 const subtotalAmountEl = document.getElementById('subtotal-amount');
 const taxAmountEl = document.getElementById('tax-amount');
 const shippingAmountEl = document.getElementById('shipping-amount'); // Added reference

 const reviewModal = document.getElementById('review-modal');
 const confirmOrderBtn = document.getElementById('confirm-order-btn');
 const editDetailsBtn = document.getElementById('edit-details-btn');

 // Added: Get PHP calculated values from hidden fields
 const phpShippingFee = parseFloat(document.getElementById('php-shipping-fee').value);
 const phpDiscount = parseFloat(document.getElementById('php-discount').value);

 // --- Buy Now Flow ---
 const urlParams = new URLSearchParams(window.location.search);
 const isBuyNow = urlParams.get('buynow') === '1';
 
 if (isBuyNow) {
  const buyNowProductData = sessionStorage.getItem('buyNowProduct');
  
  if (buyNowProductData) {
   const product = JSON.parse(buyNowProductData);
   const cartSummaryItems = document.getElementById('cart-summary-items');
   const itemTotal = product.price * product.quantity;
   
   cartSummaryItems.innerHTML = `
    <div class="flex gap-2.5 items-start">
     <img src="${product.image || 'https://placehold.co/80x80'}" alt="${product.name}" class="w-14 h-14 rounded-lg object-cover border flex-shrink-0">
     <div class="flex-grow min-w-0">
      <h3 class="font-semibold text-brand-dark text-xs truncate">${product.name}</h3>
      <p class="text-xs text-brand-subtext">Qty: ${product.quantity}</p>
      <p class="font-semibold text-brand-dark text-xs mt-0.5">₱${itemTotal.toFixed(2)}</p>
     </div>
    </div>
   `;
   
   updateComputations(itemTotal);
   shippingSection.classList.remove('hidden');
   
   // Add to cart database
   const formData = new FormData();
   formData.append('action', 'add_to_cart');
   formData.append('product_id', product.id);
   formData.append('quantity', product.quantity);
   
   await fetch('cart_handler.php', {
    method: 'POST',
    body: formData
   });
  }
 }

 let cartTotalAmount = parseFloat(summaryTotalEl.textContent.replace('₱', '').replace(',', ''));

 if (shippingSection.classList.contains('hidden') && !isBuyNow) {
  return;
 }

 // MODIFIED: Uses phpShippingFee and phpDiscount
 function updateComputations(subtotal) {
  const tax = 0;
  
  // Recalculate total using PHP values for consistency
  const total = subtotal + phpShippingFee - phpDiscount + tax;
  
  subtotalAmountEl.textContent = `₱${subtotal.toFixed(2)}`;
  taxAmountEl.textContent = `₱${tax.toFixed(2)}`;

    // Update shipping amount display based on PHP logic
    shippingAmountEl.textContent = phpShippingFee === 0 ? 'FREE' : `₱${phpShippingFee.toFixed(2)}`;
    
  summaryTotalEl.textContent = `₱${total.toFixed(2)}`;
  
  return total;
 }

 const paymentMethodInputs = document.querySelectorAll('input[name="payment-method"]');
 const paymentDetailsSection = document.getElementById('payment-details-section');

 paymentMethodInputs.forEach(input => {
  input.addEventListener('change', (e) => {
   if (e.target.value === 'Bank Transfer') {
    paymentDetailsSection.classList.remove('hidden');
   } else {
    paymentDetailsSection.classList.add('hidden');
   }
  });
 });

 shippingForm.addEventListener('submit', (e) => {
  e.preventDefault();
  
  const email = document.getElementById('email').value;
  const name = document.getElementById('full-name').value;
  const address = document.getElementById('address').value;
  const city = document.getElementById('city').value;
  const province = document.getElementById('province').value;
  const zip = document.getElementById('zip').value;
  const phone = document.getElementById('phone').value;
  const paymentMethod = document.querySelector('input[name="payment-method"]:checked').value;
  const total = summaryTotalEl.textContent;
  const subtotal = subtotalAmountEl.textContent;
    // Added: Get shipping amount for review modal
    const shipping = shippingAmountEl.textContent; 
  const fullAddress = `${address}, ${city}, ${province} ${zip}`;

  document.getElementById('review-name').textContent = name;
  document.getElementById('review-email').textContent = email;
  document.getElementById('review-phone').textContent = phone;
  document.getElementById('review-address').textContent = fullAddress;
  document.getElementById('review-payment').textContent = paymentMethod;
  document.getElementById('review-subtotal').textContent = subtotal;
    // Added: Set shipping amount in review modal
    document.getElementById('review-shipping').textContent = shipping;
  document.getElementById('review-total').textContent = total;

  reviewModal.classList.remove('hidden');
 });

 editDetailsBtn.addEventListener('click', () => {
  reviewModal.classList.add('hidden');
 });

 confirmOrderBtn.addEventListener('click', async () => {
  const name = document.getElementById('full-name').value.trim();
  const email = document.getElementById('email').value.trim();
  const address = document.getElementById('address').value.trim();
  const city = document.getElementById('city').value.trim();
  const province = document.getElementById('province').value.trim();
  const zip = document.getElementById('zip').value.trim();
  const phone = document.getElementById('phone').value.trim();
  const paymentMethod = document.querySelector('input[name="payment-method"]:checked').value;
  
  if (!name || !email || !address || !city || !province || !zip || !phone) {
   alert('Please fill in all shipping details.');
   return;
  }

  const fullAddress = `${address}, ${city}, ${province} ${zip}`;
  
  const formData = new FormData();
  formData.append('name', name);
  formData.append('email', email);
  formData.append('phone', phone);
  formData.append('address', fullAddress);
  formData.append('latitude', document.getElementById('latitude').value);
  formData.append('longitude', document.getElementById('longitude').value);
  formData.append('payment_method', paymentMethod);

  if (paymentMethod === 'Bank Transfer') {
   const cardholderName = document.getElementById('cardholder-name').value.trim();
   const cardNumber = document.getElementById('card-number').value.trim().replace(/\s/g, '');
   const expiryDate = document.getElementById('expiry-date').value.trim();
   const cvv = document.getElementById('cvv').value.trim();

   if (!cardholderName || !cardNumber || !expiryDate || !cvv) {
    alert('Please fill in all card details.');
    return;
   }

   if (cardNumber.length !== 16) {
    alert('Card number must be 16 digits.');
    return;
   }

   if (!/^\d{2}\/\d{2}$/.test(expiryDate)) {
    alert('Expiry date must be in MM/YY format.');
    return;
   }

   if (cvv.length < 3 || cvv.length > 4) {
    alert('CVV must be 3 or 4 digits.');
    return;
   }

   formData.append('cardholder_name', cardholderName);
   formData.append('card_number', cardNumber);
   formData.append('expiry_date', expiryDate);
   formData.append('cvv', cvv);
  }

  confirmOrderBtn.disabled = true;
  confirmOrderBtn.textContent = 'Processing...';

  try {
   const response = await fetch('checkout_handler.php', {
    method: 'POST',
    body: formData
   });
   const data = await response.json();

   if (data.success) {
    sessionStorage.removeItem('buyNowProduct');
    document.querySelector('.max-w-7xl.mx-auto.px-4.py-8').classList.add('hidden');
    confirmationSection.classList.remove('hidden');
    reviewModal.classList.add('hidden');
    window.scrollTo({ top: 0, behavior: 'smooth' });
   } else {
    alert(`Order failed: ${data.message}`);
    confirmOrderBtn.disabled = false;
    confirmOrderBtn.textContent = 'Confirm Order';
    reviewModal.classList.add('hidden');
   }
  } catch (error) {
   console.error("Network error during checkout:", error);
   alert("A network error occurred. Please try again.");
   confirmOrderBtn.disabled = false;
   confirmOrderBtn.textContent = 'Confirm Order';
   reviewModal.classList.add('hidden');
  }
 });
});
</script>

<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBmfBz8XMDFhCPZGcxY2jzSotTEExHG5Ys&libraries=places"></script>
<script>
let map;
let marker;

function initMap() {
 // Default location (Bacolod City, Philippines)
 const defaultLocation = { lat: 10.6765, lng: 122.9509 };

 map = new google.maps.Map(document.getElementById('map'), {
  zoom: 15,
  center: defaultLocation,
 });

 marker = new google.maps.Marker({
  position: defaultLocation,
  map: map,
  draggable: true,
 });

 // Update coordinates when marker is dragged
 marker.addListener('dragend', function() {
  updateCoordinates(marker.getPosition());
 });

 // Update coordinates when map is clicked
 map.addListener('click', function(event) {
  marker.setPosition(event.latLng);
  updateCoordinates(event.latLng);
 });

 // Try to get user's current location
 if (navigator.geolocation) {
  navigator.geolocation.getCurrentPosition(function(position) {
   const userLocation = {
    lat: position.coords.latitude,
    lng: position.coords.longitude
   };
   map.setCenter(userLocation);
   marker.setPosition(userLocation);
   updateCoordinates(userLocation);
  });
 }
}

function updateCoordinates(latLng) {
 // Handle both LatLng objects (with .lat()/.lng() methods) and plain objects (with .lat/.lng properties)
 const lat = typeof latLng.lat === 'function' ? latLng.lat() : latLng.lat;
 const lng = typeof latLng.lng === 'function' ? latLng.lng() : latLng.lng;

 document.getElementById('latitude').value = lat;
 document.getElementById('longitude').value = lng;
}

// Initialize map when page loads
window.onload = initMap;
</script>

</body>
</html>