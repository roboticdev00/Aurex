<?php
// utilities.php - Shared utility functions

/**
 * Generate status badge HTML for orders
 * @param string $status The order status
 * @param bool $admin Whether this is for admin view (true) or customer view (false)
 * @return string HTML badge
 */
function getStatusBadge($status, $admin = false) {
    if ($admin) {
        // Admin view - simple badges
        $colors = [
            'Pending' => 'bg-yellow-100 text-yellow-800',
            'Processing' => 'bg-blue-100 text-blue-800',
            'Shipped' => 'bg-purple-100 text-purple-800',
            'Delivered' => 'bg-green-100 text-green-800',
            'Cancelled' => 'bg-red-100 text-red-800'
        ];
        $class = $colors[$status] ?? 'bg-gray-100 text-gray-800';
        return "<span class='px-3 py-1 rounded-full text-sm font-semibold {$class}'>{$status}</span>";
    } else {
        // Customer view - with display names and borders
        $status_mapping = [
            'Pending' => ['display' => 'To Pay', 'class' => 'bg-orange-100 text-orange-800 border border-orange-300'],
            'Processing' => ['display' => 'To Ship', 'class' => 'bg-blue-100 text-blue-800 border border-blue-300'],
            'Shipped' => ['display' => 'To Receive', 'class' => 'bg-purple-100 text-purple-800 border border-purple-300'],
            'Delivered' => ['display' => 'Completed', 'class' => 'bg-green-100 text-green-800 border border-green-300'],
            'Cancelled' => ['display' => 'Cancelled', 'class' => 'bg-red-100 text-red-800 border border-red-300']
        ];

        $info = $status_mapping[$status] ?? ['display' => $status, 'class' => 'bg-gray-100 text-gray-800 border border-gray-300'];
        return "<span class='px-4 py-2 rounded-lg text-sm font-bold {$info['class']} shadow-sm'>{$info['display']}</span>";
    }
}

/**
 * Get display name for order status
 * @param string $status The order status
 * @return string Display name
 */
function getStatusDisplay($status) {
    $status_mapping = [
        'Pending' => 'To Pay',
        'Processing' => 'To Ship',
        'Shipped' => 'To Receive',
        'Delivered' => 'Completed',
        'Cancelled' => 'Cancelled'
    ];
    return $status_mapping[$status] ?? $status;
}

/**
 * Get availability status based on stock quantity
 * @param int $stock The current stock quantity
 * @return string Availability status
 */
function getAvailabilityFromStock($stock) {
    if ($stock <= 0) {
        return 'Out of Stock';
    } elseif ($stock <= 5) {
        return 'Low Stock';
    } else {
        return 'In Stock';
    }
}
?>