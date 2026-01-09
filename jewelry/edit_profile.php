<?php
// edit_profile.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['User_ID'])) {
    header('Location: login.php');
    exit;
}

require_once 'db_connect.php';

$user_id = $_SESSION['User_ID'];
$user_data = [];
$success_message = '';
$error_message = '';

// Fetch current user data
try {
    $stmt = $pdo->prepare("SELECT Name, Email, Phone, Address FROM user WHERE User_ID = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_data) {
        $error_message = "User not found.";
    }
} catch (PDOException $e) {
    error_log("Profile Fetch Error: " . $e->getMessage());
    $error_message = "Failed to load profile data.";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Name is required.";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required.";
    }
    
    if (empty($phone)) {
        $errors[] = "Phone number is required.";
    }
    
    // Check if email already exists for another user
    try {
        $stmt = $pdo->prepare("SELECT User_ID FROM user WHERE Email = :email AND User_ID != :user_id");
        $stmt->execute([':email' => $email, ':user_id' => $user_id]);
        if ($stmt->fetch()) {
            $errors[] = "Email is already in use by another account.";
        }
    } catch (PDOException $e) {
        $errors[] = "Database error occurred.";
    }
    
    // If password change is requested
    if (!empty($current_password) || !empty($new_password) || !empty($confirm_password)) {
        if (empty($current_password)) {
            $errors[] = "Current password is required to change password.";
        }
        
        if (empty($new_password)) {
            $errors[] = "New password is required.";
        } elseif (strlen($new_password) < 6) {
            $errors[] = "New password must be at least 6 characters.";
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = "New passwords do not match.";
        }
        
        // Verify current password
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("SELECT Password FROM user WHERE User_ID = :user_id");
                $stmt->execute([':user_id' => $user_id]);
                $user_password = $stmt->fetchColumn();
                
                if (!password_verify($current_password, $user_password)) {
                    $errors[] = "Current password is incorrect.";
                }
            } catch (PDOException $e) {
                $errors[] = "Failed to verify password.";
            }
        }
    }
    
    // If no errors, update the database
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Update basic profile info
            $stmt = $pdo->prepare("
                UPDATE user 
                SET Name = :name, Email = :email, Phone = :phone, Address = :address 
                WHERE User_ID = :user_id
            ");
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':phone' => $phone,
                ':address' => $address,
                ':user_id' => $user_id
            ]);
            
            // Update password if provided
            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE user SET Password = :password WHERE User_ID = :user_id");
                $stmt->execute([':password' => $hashed_password, ':user_id' => $user_id]);
            }
            
            $pdo->commit();
            
            // Update session name
            $_SESSION['Name'] = $name;
            
            $success_message = "Profile updated successfully!";
            
            // Refresh user data
            $user_data['Name'] = $name;
            $user_data['Email'] = $email;
            $user_data['Phone'] = $phone;
            $user_data['Address'] = $address;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Profile Update Error: " . $e->getMessage());
            $error_message = "Failed to update profile. Please try again.";
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Profile - Aurex</title>

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
    font-size: 0.875rem;
    font-weight: 500;
    color: #4D4C48;
}
.form-background-shadow {
    box-shadow: 
      0 10px 25px -5px rgba(0, 0, 0, 0.15),
      0 -5px 15px -3px rgba(0, 0, 0, 0.06),
      5px 0 20px -3px rgba(0, 0, 0, 0.08),
      -5px 0 20px -3px rgba(0, 0, 0, 0.08);
}
</style>
</head>
<body class="antialiased">

<header class="py-5 border-b-0 bg-brand-teal shadow-md">
    <div class="max-w-7xl mx-auto px-4 md:px-6 lg:px-8">
        <nav class="flex justify-between items-center">
            <a href="index_user.php" class="font-serif text-2xl font-bold text-white">Aurex</a>
            <a href="index_user.php" class="text-sm font-medium text-white hover:text-gray-200">&larr; Back to Store</a>
        </nav>
    </div>
</header>

<div class="max-w-3xl mx-auto px-4 py-8 sm:py-12">
    
    <?php if ($success_message): ?>
    <div class="mb-6 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
        <div class="flex items-center gap-2">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <span><?php echo htmlspecialchars($success_message); ?></span>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
    <div class="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
        <div class="flex items-start gap-2">
            <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
            </svg>
            <span><?php echo $error_message; ?></span>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="bg-white p-6 md:p-8 rounded-xl form-background-shadow">
        <h1 class="font-serif text-3xl font-bold text-brand-dark mb-6">Edit Profile</h1>
        
        <form method="POST" action="" class="space-y-6">
            
            <div class="border-b border-gray-200 pb-6">
                <h2 class="text-lg font-serif font-bold text-brand-dark mb-4">Personal Information</h2>
                
                <div class="space-y-4">
                    <div>
                        <label for="name" class="form-label">Full Name</label>
                        <input type="text" id="name" name="name" class="form-input" required 
                               value="<?php echo htmlspecialchars($user_data['Name'] ?? ''); ?>">
                    </div>
                    
                    <div>
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" id="email" name="email" class="form-input" required 
                               value="<?php echo htmlspecialchars($user_data['Email'] ?? ''); ?>">
                    </div>
                    
                    <div>
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="form-input" required 
                               value="<?php echo htmlspecialchars($user_data['Phone'] ?? ''); ?>">
                    </div>
                    
                    <div>
                        <label for="address" class="form-label">Address</label>
                        <textarea id="address" name="address" class="form-input" rows="3" 
                                  placeholder="Street, City, Province, Postal Code"><?php echo htmlspecialchars($user_data['Address'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
            
            <div class="border-b border-gray-200 pb-6">
                <h2 class="text-lg font-serif font-bold text-brand-dark mb-2">Change Password</h2>
                <p class="text-sm text-brand-subtext mb-4">Leave blank if you don't want to change your password</p>
                
                <div class="space-y-4">
                    <div>
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" id="current_password" name="current_password" class="form-input" 
                               placeholder="Enter current password">
                    </div>
                    
                    <div>
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" id="new_password" name="new_password" class="form-input" 
                               placeholder="Enter new password (min. 6 characters)">
                    </div>
                    
                    <div>
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input" 
                               placeholder="Confirm new password">
                    </div>
                </div>
            </div>
            
            <div class="flex gap-4">
                <button type="submit" name="update_profile" 
                        class="flex-1 bg-brand-teal text-white py-3 rounded-full font-semibold hover:bg-opacity-90 transition-colors">
                    Save Changes
                </button>
                <a href="index_user.php" 
                   class="flex-1 bg-white text-brand-dark border-2 border-gray-300 py-3 rounded-full font-semibold hover:bg-gray-50 transition-colors text-center">
                    Cancel
                </a>
            </div>
            
        </form>
    </div>
    
</div>

<footer class="bg-white mt-12 sm:mt-16 border-t border-gray-100">
    <div class="max-w-7xl mx-auto px-4 md:px-6 lg:px-8 py-12">
        <div class="text-center text-brand-subtext text-sm">
            <p>&copy; 2024 Jewellery. All rights reserved.</p>
        </div>
    </div>
</footer>

</body>
</html>