<?php
// edit_user.php
session_start();
require_once 'db_connect.php'; // PDO connection

// --- CRITICAL RBAC CHECK ---
if (!isset($_SESSION['User_ID']) || ($_SESSION['Role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

// Get the current admin user's name for the sidebar greeting
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


$user_id = $_GET['id'] ?? null;
$is_editing = $user_id !== null;
$user = [];
$message = '';
$message_type = '';

// 1. Handle Form Submission (Create or Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $phone = $_POST['phone'] ?? null;
    $password = $_POST['password'] ?? null;
    $current_id = $_POST['user_id'] ?? null;

    if ($current_id) {
        // UPDATE Existing User
        $update_sql = "UPDATE user SET Name = :name, Email = :email, Role = :role";
        $params = [':name' => $name, ':email' => $email, ':role' => $role];

        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $update_sql .= ", Password = :password";
            $params[':password'] = $hashed_password;
        }

        $update_sql .= " WHERE User_ID = :user_id";
        $params[':user_id'] = $current_id;

        $stmt = $pdo->prepare($update_sql);

        if ($stmt->execute($params)) {
            // Success: Redirect back to the list
            header('Location: manage_users.php?msg=updated');
            exit;
        } else {
            $message = "Error updating user.";
            $message_type = 'error';
        }
    } else {
        // CREATE New User
        if (empty($password)) {
            $message = "Error: Password is required for new users.";
            $message_type = 'error';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_sql = "INSERT INTO user (Name, Email, Password, Role) VALUES (:name, :email, :password, :role)";
            $stmt = $pdo->prepare($insert_sql);

            if ($stmt->execute([':name' => $name, ':email' => $email, ':password' => $hashed_password, ':role' => $role])) {
                // Success: Redirect back to the list
                header('Location: manage_users.php?msg=created');
                exit;
            } else {
                $message = "Error creating user.";
                $message_type = 'error';
            }
        }
    }
}

// 2. Fetch User Data for Edit Form
if ($is_editing) {
    $stmt = $pdo->prepare("SELECT User_ID, Name, Email, Phone, Role FROM user WHERE User_ID = :user_id");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch();

    if (!$user) {
        $message = "Error: User not found.";
        $message_type = 'error';
        $is_editing = false; // Switch to create mode if not found
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $is_editing ? 'Edit User' : 'Add New User' ?> - Jewellery Admin</title>

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
            background-color: #FBF9F6; /* brand-beige */
            color: #4D4C48; /* brand-dark */
        }
        .admin-sidebar {
            background-color: #4D4C48; /* brand-dark */
        }
        .nav-link:hover {
            background-color: #8B7B61; /* brand-teal */
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
    </style>
</head>
<body class="flex antialiased">
    
    <div class="w-64 admin-sidebar h-screen p-6 fixed flex flex-col shadow-2xl">
        <h1 class="font-serif text-white text-3xl font-bold mb-10 border-b border-gray-600 pb-4">jewellery</h1>
        
        <div class="text-white mb-8 border-b border-teal-light/50 pb-4">
            <p class="font-semibold text-sm text-teal-light uppercase tracking-wider">Store Administrator</p>
            <p class="text-lg font-bold text-white mt-1"><?= $userName ?></p>
        </div>
        
        <ul class="space-y-2 flex-grow">
            <li><a href="admin_dashboard.php" class="block p-3 rounded text-white nav-link hover:bg-brand-teal transition-all">
                <span class="font-medium">Dashboard</span>
            </a></li>
            <li><a href="manage_users.php" class="block p-3 rounded text-white nav-link hover:bg-brand-teal transition-all">
                <span class="font-medium">Manage Users</span>
            </a></li>
            <li><a href="manage_products.php" class="block p-3 rounded text-white nav-link hover:bg-brand-teal transition-all">
                <span class="font-medium">Manage Products</span>
            </a></li>
            <li><a href="manage_orders.php" class="block p-3 rounded text-white nav-link hover:bg-brand-teal transition-all">
                <span class="font-medium">Manage Orders</span>
            </a></li>
        </ul>
        
        <a href="logout.php" class="mt-auto block w-full text-center p-3 rounded-full logout-btn shadow-lg">
            <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
            Sign Out
        </a>
    </div>

    <div class="flex-1 ml-64 p-10">
        <div class="flex justify-between items-center mb-6 border-b border-gray-200 pb-4">
            <h2 class="font-serif text-4xl font-bold text-brand-dark"><?= $is_editing ? 'Edit User' : 'Add New User' ?></h2>
            <a href="manage_users.php" class="font-semibold text-brand-subtext hover:text-brand-dark transition-colors flex items-center">
                &larr; Back to User List
            </a>
        </div>
        
        <?php if ($message): ?>
        <div class="p-4 mb-4 rounded-xl <?= $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?> border border-gray-200">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <div class="bg-white p-8 rounded-xl shadow-lg max-w-2xl mx-auto">
            <form method="POST">
                <?php if ($is_editing): ?>
                    <input type="hidden" name="user_id" value="<?= htmlspecialchars($user['User_ID']) ?>">
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="mb-4 col-span-full">
                        <label for="name" class="block text-sm font-medium text-brand-dark mb-1">Full Name</label>
                        <input type="text" id="name" name="name" value="<?= htmlspecialchars($user['Name'] ?? '') ?>" required 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-brand-teal focus:border-brand-teal transition">
                    </div>
                    
                    <div class="mb-4">
                        <label for="email" class="block text-sm font-medium text-brand-dark mb-1">Email</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['Email'] ?? '') ?>" required 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-brand-teal focus:border-brand-teal transition">
                    </div>

                    <div class="mb-4">
                        <label for="phone" class="block text-sm font-medium text-brand-dark mb-1">Phone (Optional)</label>
                        <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($user['Phone'] ?? '') ?>" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-brand-teal focus:border-brand-teal transition">
                    </div>

                    <div class="mb-4">
                        <label for="role" class="block text-sm font-medium text-brand-dark mb-1">Role</label>
                        <select id="role" name="role" required 
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-brand-teal focus:border-brand-teal transition">
                            <option value="customer" <?= ($user['Role'] ?? '') === 'customer' ? 'selected' : '' ?>>Customer</option>
                            <option value="admin" <?= ($user['Role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                    </div>

                    <div class="mb-6">
                        <label for="password" class="block text-sm font-medium text-brand-dark mb-1">Password <?= $is_editing ? ' (Leave blank to keep current)' : ' (Required)' ?></label>
                        <input type="password" id="password" name="password" <?= $is_editing ? '' : 'required' ?> 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-brand-teal focus:border-brand-teal transition">
                    </div>
                </div> <div class="flex justify-end gap-4 mt-6">
                    <a href="manage_users.php" class="bg-gray-200 text-brand-dark px-6 py-2.5 rounded-full font-semibold hover:bg-gray-300 transition-colors">
                        Cancel
                    </a>
                    <button type="submit" class="bg-brand-teal text-white px-8 py-2.5 rounded-full font-semibold hover:bg-opacity-90 transition-colors shadow-md">
                        <?= $is_editing ? 'Update User' : 'Create User' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>