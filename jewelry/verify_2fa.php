<?php

session_start();

// 1. Database connection
require 'db_connect.php';

// 2. Load Google Authenticator library
require_once __DIR__ . '/vendor/autoload.php';
$g = new PHPGangsta_GoogleAuthenticator();

// 3. Check if user is pending 2FA verification
if (empty($_SESSION['pending_2fa_user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['pending_2fa_user_id'];

// 4. Fetch user data with error handling
try {
    $stmt = $pdo->prepare("SELECT * FROM user WHERE User_ID = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $_SESSION['error'] = 'User not found.';
        header('Location: login.php');
        exit;
    }
    
    // Check if user has 2FA setup
    if (empty($user['user_secret_key'])) {
        $_SESSION['error'] = '2FA not configured for this user.';
        header('Location: login.php');
        exit;
    }
    
} catch (PDOException $e) {
    error_log("Database error in verify_2fa.php: " . $e->getMessage());
    $_SESSION['error'] = 'Database error occurred.';
    header('Location: login.php');
    exit;
}

// --- QR Code Generation ---
$qrCodeUrl = $g->getQRCodeGoogleUrl(
    $user['Email'] ?? 'user',
    $user['user_secret_key'],
    'JewelryStore'
);

// 5. Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    
    // Validate code format
    if (empty($code) || !preg_match('/^\d{6}$/', $code)) {
        $_SESSION['error'] = 'Please enter a valid 6-digit code.';
        header('Location: verify_2fa.php');
        exit;
    }
    
    $secret = $user['user_secret_key'];
    
    // Verify the code with tolerance (2 = 60 seconds: 2*30s windows)
    if ($g->verifyCode($secret, $code, 2)) {
        // Success - update last verification timestamp
        try {
            $updateStmt = $pdo->prepare("UPDATE user SET last_2fa_verification = NOW() WHERE User_ID = ?");
            $updateStmt->execute([$user['User_ID']]);
        } catch (PDOException $e) {
            error_log("Error updating 2FA timestamp: " . $e->getMessage());
        }
        
        // Set session variables
        unset($_SESSION['pending_2fa_user_id']);
        $_SESSION['User_ID'] = $user['User_ID'];
        $_SESSION['Name'] = $user['Name'];
        $_SESSION['Email'] = $user['Email'];
        $_SESSION['Role'] = $user['Role'];
        $_SESSION['2fa_verified'] = true;
        $_SESSION['2fa_verified_at'] = time(); // Store verification time

        header('Location: index_user.php');
        exit;
    } else {
        $_SESSION['error'] = 'Invalid 2FA code. Please try again.';
        header('Location: verify_2fa.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify 2FA</title>
    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #3441527a; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            margin: 0; 
        }
        .container { 
            background: white; 
            padding: 40px; 
            border-radius: 12px; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.1); 
            width: 100%; 
            max-width: 380px; 
            text-align: center;
        }
        h2 { 
            color: #334155; 
            margin-bottom: 25px; 
            font-weight: 600; 
        }
        p {
            color: #4b5563;
            font-size: 14px;
            margin-bottom: 20px;
        }
        .qr-code {
            margin-bottom: 20px;
        }
        input[name="code"] { 
            width: 100%; 
            padding: 12px; 
            margin-bottom: 20px; 
            border: 1px solid #cbd5e1; 
            border-radius: 8px; 
            box-sizing: border-box; 
            transition: border-color 0.3s;
            text-align: center;
            font-size: 1.2em;
            letter-spacing: 2px;
        }
        input:focus { 
            border-color: #2563eb; 
            outline: none; 
        }
        button { 
            background-color: #10b981;
            color: white; 
            padding: 12px 20px; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            width: 100%; 
            font-size: 16px; 
            font-weight: 600;
            transition: background-color 0.3s, transform 0.1s;
        }
        button:hover { 
            background-color: #059669; 
        }
        button:active { 
            transform: scale(0.99); 
        }
        .error { 
            color: #ef4444; 
            background-color: #fee2e2; 
            padding: 10px; 
            border-radius: 6px; 
            border: 1px solid #fca5a5; 
            margin-bottom: 20px; 
            font-size: 14px;
        }
        .utility-links { 
            margin-top: 25px; 
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
            font-size: 14px;
            text-align: center;
        }
        .utility-links a { 
            color: #2563eb; 
            text-decoration: none; 
            font-weight: 500; 
            margin: 0 10px;
        }
        .utility-links a:hover { 
            text-decoration: underline; 
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Enter Authenticator Code</h2>

        <div class="qr-code">
            <img src="<?= htmlspecialchars($qrCodeUrl) ?>" alt="Scan this QR code with your Authenticator app" width="180" height="180">
            <p style="font-size:13px;color:#64748b;">Scan this QR code with your Google Authenticator app.</p>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="error"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php else: ?>
            <p>Enter the 6-digit code from your authenticator app to log in.</p>
        <?php endif; ?>

        <form method="post">
            <input name="code" type="text" placeholder="6-digit code" maxlength="6" pattern="\d{6}" required autofocus>
            <button type="submit">Verify Code</button>
        </form>

        <div class="utility-links">
            <a href="login.php">Back to Login</a> • <a href="logout.php">Cancel</a>
        </div>
    </div>
</body>
</html>