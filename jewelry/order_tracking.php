<?php
// order_tracking.php
session_start();
require_once 'db_connect.php';

$user_id = $_SESSION['User_ID'] ?? null;
$order_id = $_GET['order_id'] ?? null;

if (!$user_id || !$order_id) {
    header('Location: login.php');
    exit;
}

$order = null;
$tracking_updates = [];

try {
    // Get order details with coordinates
    $stmt = $pdo->prepare("
        SELECT o.*, u.Name as Customer_Name
        FROM orders o
        JOIN user u ON o.User_ID = u.User_ID
        WHERE o.Order_ID = :order_id AND o.User_ID = :user_id
    ");
    $stmt->execute([':order_id' => $order_id, ':user_id' => $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        header('Location: orders.php');
        exit;
    }

    // Get tracking updates (we'll simulate some for demo)
    $tracking_updates = [
        ['status' => 'Order Placed', 'timestamp' => $order['Order_Date'], 'location' => 'Online'],
        ['status' => 'Processing', 'timestamp' => date('Y-m-d H:i:s', strtotime($order['Order_Date'] . ' +1 hour')), 'location' => 'Warehouse'],
        ['status' => 'Shipped', 'timestamp' => date('Y-m-d H:i:s', strtotime($order['Order_Date'] . ' +2 hours')), 'location' => 'In Transit'],
        ['status' => 'Delivered', 'timestamp' => date('Y-m-d H:i:s', strtotime($order['Order_Date'] . ' +1 day')), 'location' => $order['Shipping_Address']]
    ];

} catch (PDOException $e) {
    error_log("Order tracking error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Tracking - <?php echo htmlspecialchars($order['Order_ID']); ?></title>

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
        }
    </style>
</head>
<body class="min-h-screen">
    <header class="py-6 sticky top-0 z-40 bg-brand-teal/95 backdrop-blur-sm shadow-md transition-all">
        <nav class="flex justify-between items-center max-w-screen-2xl mx-auto px-4 md:px-8 lg:px-16">
            <a href="index_user.php" class="font-serif text-3xl font-bold text-white">Aurex</a>

            <div class="flex items-center gap-5">
                <a href="orders.php" class="bg-white text-brand-teal px-6 py-2 rounded-full text-sm font-semibold hover:bg-gray-100 transition-colors shadow-md">
                    My Orders
                </a>
                <a href="index_user.php" class="bg-white text-brand-teal px-6 py-2 rounded-full text-sm font-semibold hover:bg-gray-100 transition-colors shadow-md">
                    Back to Home
                </a>
            </div>
        </nav>
    </header>

    <div class="max-w-6xl mx-auto px-4 py-8">

        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h1 class="font-serif text-3xl font-bold text-brand-dark mb-2">Order #<?php echo htmlspecialchars($order['Order_ID']); ?></h1>
            <p class="text-brand-subtext">Tracking your delivery</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Map Section -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="font-serif text-xl font-bold text-brand-dark mb-4">Delivery Location</h2>
                <div id="map" class="w-full h-96 rounded-lg"></div>
                <div class="mt-4 text-sm text-brand-subtext">
                    <p><strong>Address:</strong> <?php echo htmlspecialchars($order['Shipping_Address']); ?></p>
                    <?php if ($order['Latitude'] && $order['Longitude']): ?>
                        <p><strong>Coordinates:</strong> <?php echo htmlspecialchars($order['Latitude']); ?>, <?php echo htmlspecialchars($order['Longitude']); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tracking Timeline -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="font-serif text-xl font-bold text-brand-dark mb-4">Tracking Updates</h2>
                <div class="space-y-4">
                    <?php foreach (array_reverse($tracking_updates) as $index => $update): ?>
                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-brand-teal rounded-full flex items-center justify-center">
                                    <span class="text-white text-sm font-bold"><?php echo count($tracking_updates) - $index; ?></span>
                                </div>
                            </div>
                            <div class="flex-1">
                                <h3 class="font-semibold text-brand-dark"><?php echo htmlspecialchars($update['status']); ?></h3>
                                <p class="text-sm text-brand-subtext"><?php echo htmlspecialchars($update['location']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo date('M d, Y H:i', strtotime($update['timestamp'])); ?></p>
                            </div>
                        </div>
                        <?php if ($index < count($tracking_updates) - 1): ?>
                            <div class="ml-4 w-px h-8 bg-gray-300"></div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Order Details -->
        <div class="bg-white rounded-xl shadow-lg p-6 mt-6">
            <h2 class="font-serif text-xl font-bold text-brand-dark mb-4">Order Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-brand-subtext">Customer</p>
                    <p class="font-semibold"><?php echo htmlspecialchars($order['Customer_Name']); ?></p>
                </div>
                <div>
                    <p class="text-sm text-brand-subtext">Order Date</p>
                    <p class="font-semibold"><?php echo date('M d, Y H:i', strtotime($order['Order_Date'])); ?></p>
                </div>
                <div>
                    <p class="text-sm text-brand-subtext">Status</p>
                    <span class="px-3 py-1 rounded-full text-sm font-semibold
                        <?php
                        switch($order['Status']) {
                            case 'Pending': echo 'bg-orange-100 text-orange-800'; break;
                            case 'Processing': echo 'bg-blue-100 text-blue-800'; break;
                            case 'Shipped': echo 'bg-purple-100 text-purple-800'; break;
                            case 'Delivered': echo 'bg-green-100 text-green-800'; break;
                            case 'Cancelled': echo 'bg-red-100 text-red-800'; break;
                            default: echo 'bg-gray-100 text-gray-800';
                        }
                        ?>">
                        <?php echo htmlspecialchars($order['Status']); ?>
                    </span>
                </div>
                <div>
                    <p class="text-sm text-brand-subtext">Total Amount</p>
                    <p class="font-semibold text-brand-teal">₱<?php echo number_format($order['Total_Amount'], 2); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Google Maps API -->
    <script async defer src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBmfBz8XMDFhCPZGcxY2jzSotTEExHG5Ys&v=weekly&callback=initMap"></script>
    <script>
        let map;
        let marker;

        function initMap() {
            try {
                <?php if ($order['Latitude'] && $order['Longitude']): ?>
                    const orderLocation = {
                        lat: parseFloat(<?php echo $order['Latitude']; ?>),
                        lng: parseFloat(<?php echo $order['Longitude']; ?>)
                    };
                    console.log('Using order coordinates:', orderLocation);
                <?php else: ?>
                    // Default to Bacolod if no coordinates
                    orderLocation = { lat: 10.6765, lng: 122.9509 };
                    console.log('Using default Bacolod coordinates:', orderLocation);
                <?php endif; ?>

                // Check if map container exists
                const mapElement = document.getElementById('map');
                if (!mapElement) {
                    console.error('Map container element not found');
                    return;
                }

                map = new google.maps.Map(mapElement, {
                    zoom: 15,
                    center: orderLocation,
                    mapTypeControl: true,
                    streetViewControl: true,
                    fullscreenControl: true
                });

                marker = new google.maps.Marker({
                    position: orderLocation,
                    map: map,
                    title: 'Delivery Location',
                    animation: google.maps.Animation.DROP
                });

                // Add info window
                const infoWindow = new google.maps.InfoWindow({
                    content: `
                        <div>
                            <h3 class="font-semibold">Delivery Address</h3>
                            <p><?php echo htmlspecialchars($order['Shipping_Address']); ?></p>
                            <p class="text-sm text-gray-600 mt-1">Order #<?php echo htmlspecialchars($order['Order_ID']); ?></p>
                        </div>
                    `
                });

                marker.addListener('click', () => {
                    infoWindow.open(map, marker);
                });

                console.log('Map initialized successfully');

            } catch (error) {
                console.error('Error initializing map:', error);
                // Show error message to user
                const mapElement = document.getElementById('map');
                if (mapElement) {
                    mapElement.innerHTML = `
                        <div class="flex items-center justify-center h-full text-center p-4">
                            <div>
                                <svg class="w-12 h-12 text-red-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                </svg>
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">Map Loading Error</h3>
                                <p class="text-gray-600">Unable to load Google Maps. Please check your internet connection and try again.</p>
                                <p class="text-sm text-gray-500 mt-2">Error: ${error.message}</p>
                            </div>
                        </div>
                    `;
                }
            }
        }

        // Fallback initialization in case async loading fails
        window.addEventListener('load', function() {
            if (typeof google === 'undefined' || typeof google.maps === 'undefined') {
                console.error('Google Maps API failed to load');
                const mapElement = document.getElementById('map');
                if (mapElement) {
                    mapElement.innerHTML = `
                        <div class="flex items-center justify-center h-full text-center p-4">
                            <div>
                                <svg class="w-12 h-12 text-red-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                </svg>
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">Google Maps API Error</h3>
                                <p class="text-gray-600">The Google Maps API could not be loaded. This might be due to:</p>
                                <ul class="text-sm text-gray-500 mt-2 text-left">
                                    <li>• Invalid or restricted API key</li>
                                    <li>• Network connectivity issues</li>
                                    <li>• API quota exceeded</li>
                                    <li>• Maps JavaScript API not enabled</li>
                                </ul>
                            </div>
                        </div>
                    `;
                }
            } else if (!map) {
                // Try to initialize if not already done
                initMap();
            }
        });
    </script>
</body>
</html>