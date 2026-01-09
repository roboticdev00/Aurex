<?php
// manage_users.php
session_start();
require_once 'db_connect.php'; // PDO connection

// --- CRITICAL RBAC CHECK (Security First) ---
if (!isset($_SESSION['User_ID']) || ($_SESSION['Role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

// Get current admin user's name for the sidebar greeting
 $userName = '';
if (isset($_SESSION['User_ID'])) {
    $user_id = $_SESSION['User_ID'];
    $stmt_name = $pdo->prepare("SELECT Name FROM user WHERE User_ID = :user_id");
    $stmt_name->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_name->execute();
    $result_name = $stmt_name->fetch();
    if ($result_name) {
        $userName = htmlspecialchars($result_name['Name']);
    }
}

 $message = '';
 $message_type = ''; // success or error

// Handle Delete Request
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];

    // Check if the user is trying to delete themselves (Prevent accidental lockouts)
    if ($delete_id == $_SESSION['User_ID']) {
        $message = "Error: You cannot delete your own admin account.";
        $message_type = 'error';
    } else {
        // Prepare/Execute Delete using PDO
        $stmt_delete = $pdo->prepare("DELETE FROM user WHERE User_ID = :delete_id");
        $stmt_delete->bindParam(':delete_id', $delete_id, PDO::PARAM_INT);

        if ($stmt_delete->execute()) {
            $message = "User ID $delete_id successfully deleted.";
            $message_type = 'success';
        } else {
            $message = "Error deleting user.";
            $message_type = 'error';
        }
    }
}

// Fetch All Users
 $users = [];
 $stmt_users = $pdo->prepare("SELECT User_ID, Name, Email, Phone, Role FROM user ORDER BY User_ID DESC");
 $stmt_users->execute();
 $users = $stmt_users->fetchAll();

// Count users by role
 $allUsersCount = count($users);
 $adminUsersCount = count(array_filter($users, function($u) { return $u['Role'] === 'admin'; }));
 $customerUsersCount = count(array_filter($users, function($u) { return $u['Role'] !== 'admin'; }));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users - Aurex Jewelry</title>

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

        /* --- Premium Stats Card Design --- */
        .stats-card {
            background-color: white;
            border-left: 5px solid #8B7B61; 
            border-radius: 0.75rem; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stats-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .stats-card.active {
            background-color: #FBF9F6; /* brand-beige */
            border-left: 5px solid #8B7B61;
            box-shadow: 0 10px 15px -3px rgba(139, 123, 97, 0.1);
            transform: translateY(-3px);
        }

        /* Icon Styling */
        .stats-icon-wrapper {
            transition: all 0.3s ease;
        }

        .stats-card .stats-icon-wrapper {
            background-color: #f3f4f6; 
            color: #9ca3af; 
        }
        
        .stats-card:hover .stats-icon-wrapper {
            background-color: #f0efe9; 
            color: #8B7B61; 
        }

        .stats-card.active .stats-icon-wrapper {
            background-color: #8B7B61; 
            color: white;
            box-shadow: 0 4px 6px -1px rgba(139, 123, 97, 0.2);
        }

        .stats-number {
            font-family: 'Playfair Display', serif;
        }
        .stats-label {
            font-family: 'Inter', sans-serif;
            letter-spacing: 0.05em;
        }
    </style>
</head>
<body class="flex antialiased">
    
    <!-- Sidebar -->
    <div class="w-64 admin-sidebar h-screen p-6 fixed flex flex-col shadow-2xl">
        <!-- UPDATED: Changed to Aurex matching other pages -->
        <h1 class="font-serif text-white text-3xl font-bold mb-10 border-b border-gray-600 pb-4">Aurex</h1>
        
        <div class="text-white mb-8 border-b border-teal-light/50 pb-4">
            <p class="font-semibold text-sm text-teal-light uppercase tracking-wider">Store Administrator</p>
            <p class="text-lg font-bold text-white mt-1"><?= $userName ?></p>
        </div>
        
        <ul class="space-y-2 flex-grow">
            <li><a href="admin_dashboard.php" class="block p-3 rounded text-white hover:bg-brand-teal transition-all">Dashboard</a></li>
            <li><a href="manage_users.php" class="block p-3 rounded text-white nav-active transition-all">Manage Users</a></li>
            <li><a href="manage_products.php" class="block p-3 rounded text-white hover:bg-brand-teal transition-all">Manage Products</a></li>
            <li><a href="manage_orders.php" class="block p-3 rounded text-white hover:bg-brand-teal transition-all">Manage Orders</a></li>
            <li><a href="manage_ratings.php" class="block p-3 rounded text-white hover:bg-brand-teal transition-all">Manage Ratings</a></li>
            <li><a href="manage_report.php" class="block p-3 rounded text-white hover:bg-brand-teal transition-all">Sales Report</a></li>
        </ul>
        
        <a href="logout.php" class="mt-auto block w-full text-center p-3 rounded-full logout-btn shadow-lg">
            <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
            Sign Out
        </a>
    </div>

    <!-- Main Content -->
    <div class="flex-1 ml-64 p-10">
        <div class="flex justify-between items-center mb-6 border-b border-gray-200 pb-4">
            <h2 class="font-serif text-4xl font-bold text-brand-dark">Manage Users</h2>
            
            <a href="edit_user.php" class="bg-brand-teal text-white px-6 py-2 rounded-full font-semibold hover:bg-opacity-90 transition-colors shadow-md">
                + Add New User
            </a>
        </div>
        
        <?php if ($message): ?>
        <div class="p-4 mb-4 rounded-xl <?= $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?> border border-gray-200">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            
            <!-- All Users Card -->
            <div class="stats-card active p-6 cursor-pointer relative overflow-hidden group" data-filter="all">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <h3 class="stats-label text-sm font-semibold text-gray-400 uppercase mb-1 group-hover:text-brand-teal transition-colors">All Users</h3>
                        <div class="stats-number text-4xl text-brand-dark"><?= $allUsersCount ?></div>
                    </div>
                    <div class="stats-icon-wrapper w-14 h-14 flex items-center justify-center rounded-full shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Admin Card -->
            <div class="stats-card p-6 cursor-pointer relative overflow-hidden group" data-filter="admin">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <h3 class="stats-label text-sm font-semibold text-gray-400 uppercase mb-1 group-hover:text-brand-teal transition-colors">Admins</h3>
                        <div class="stats-number text-4xl text-brand-dark"><?= $adminUsersCount ?></div>
                    </div>
                    <div class="stats-icon-wrapper w-14 h-14 flex items-center justify-center rounded-full shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Customers Card -->
            <div class="stats-card p-6 cursor-pointer relative overflow-hidden group" data-filter="customer">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <h3 class="stats-label text-sm font-semibold text-gray-400 uppercase mb-1 group-hover:text-brand-teal transition-colors">Customers</h3>
                        <div class="stats-number text-4xl text-brand-dark"><?= $customerUsersCount ?></div>
                    </div>
                    <div class="stats-icon-wrapper w-14 h-14 flex items-center justify-center rounded-full shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-lg overflow-x-auto border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="table-header">
                    <tr>
                        <th class="px-6 py-3 text-left text-sm uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3 text-left text-sm uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-sm uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-left text-sm uppercase tracking-wider">Role</th>
                        <th class="px-6 py-3 text-center text-sm uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100" id="userTableBody">
                    <?php if (empty($users)): ?>
                        <tr><td colspan="5" class="px-6 py-10 text-center text-brand-subtext text-lg">No users found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                        <tr data-role="<?= htmlspecialchars($user['Role']) ?>">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-brand-dark"><?= htmlspecialchars($user['User_ID']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-brand-dark"><?= htmlspecialchars($user['Name']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-brand-subtext"><?= htmlspecialchars($user['Email']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm capitalize">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?= $user['Role'] === 'admin' ? 'bg-brand-teal text-white' : 'bg-gray-100 text-brand-dark' ?>">
                                    <?= htmlspecialchars($user['Role']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium space-x-3">
                                <a href="edit_user.php?id=<?= $user['User_ID'] ?>" class="px-3 py-1 bg-blue-500 text-white rounded text-xs font-semibold hover:bg-blue-600 transition-colors">
                                    Edit
                                </a>
                                <a href="manage_users.php?delete_id=<?= $user['User_ID'] ?>" onclick="return confirm('Are you sure you want to delete this user?');" class="px-3 py-1 bg-red-500 text-white rounded text-xs font-semibold hover:bg-red-600 transition-colors">
                                    Delete
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const filterBoxes = document.querySelectorAll('.stats-card');
            const tableRows = document.querySelectorAll('#userTableBody tr');
            
            filterBoxes.forEach(box => {
                box.addEventListener('click', function() {
                    // Remove active class from all boxes
                    filterBoxes.forEach(b => b.classList.remove('active'));

                    // Add active class to clicked box
                    this.classList.add('active');

                    const filter = this.getAttribute('data-filter');
                    
                    // Show/hide table rows based on filter
                    tableRows.forEach(row => {
                        if (filter === 'all') {
                            row.style.display = '';
                        } else if (filter === 'admin') {
                            const role = row.getAttribute('data-role');
                            row.style.display = role === 'admin' ? '' : 'none';
                        } else if (filter === 'customer') {
                            const role = row.getAttribute('data-role');
                            row.style.display = role !== 'admin' ? '' : 'none';
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>