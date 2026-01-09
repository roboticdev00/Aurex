<?php
session_start();
require_once __DIR__ . '/vendor/autoload.php';

// Ensure user just signed up
if (empty($_SESSION['new_user_id']) || empty($_SESSION['new_user_secret'])) {
    header('Location: signup.php');
    exit;
}

$g = new PHPGangsta_GoogleAuthenticator();
$secret = $_SESSION['new_user_secret'];
$email = $_SESSION['new_user_email'];
$user_id = $_SESSION['new_user_id'];

// Generate QR code URL (Updated App Name for Jewelry brand feel)
$qrCodeUrl = $g->getQRCodeGoogleUrl('JeweleryStore', $secret, 'JeweleryStore (' . $email . ')');

// Handle verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    if (empty($code) || !preg_match('/^\d{6}$/', $code)) {
        $_SESSION['error'] = 'Please enter a valid 6-digit code.';
        header('Location: setup_2fa.php');
        exit;
    }

    if ($g->verifyCode($secret, $code, 2)) {
        unset($_SESSION['new_user_id'], $_SESSION['new_user_email'], $_SESSION['new_user_secret']);
        $_SESSION['success'] = 'Account verified successfully! You can now log in.';
        header('Location: login.php');
        exit;
    } else {
        $_SESSION['error'] = 'Invalid 2FA code. Please try again.';
        header('Location: setup_2fa.php');
        exit;
    }
}

// Include the Tailwind config and custom styles from index.html
$tailwind_setup = '
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
<script>
tailwind.config = {
  theme: {
    extend: {
      fontFamily: {
        sans: [\'Inter\', \'sans-serif\'],
        serif: [\'Playfair Display\', \'serif\'],
      },
      colors: {
        \'brand-beige\': \'#FBF9F6\',
        \'brand-dark\': \'#4D4C48\',
        \'brand-teal\': \'#8B7B61\', // Your gold/bronze color
        \'brand-text\': \'#4D4C48\',
        \'brand-subtext\': \'#7A7977\',
      }
    }
  }
}
</script>
';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup 2FA - Jewelery</title>
    <?= $tailwind_setup ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* Base styles for consistency */
        body {
            font-family: 'Inter', sans-serif;
            background-color: #FBF9F6;
            color: #4D4C48;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 0;
        }
        .auth-wrapper {
            max-width: 1000px;
            width: 90%;
            height: auto;
        }
        /* Custom button styles from index.html (Primary button) */
        .btn-primary {
            background-color: #8B7B61;
            color: white;
            border: 2px solid #8B7B61;
            transition: all 0.2s;
            font-weight: 600; /* Adjusted for Inter font readability */
        }
        .btn-primary:hover {
            background-color: #7a6c54;
            border-color: #7a6c54;
        }
        /* FADED BACKGROUND STYLE (Same as login/signup) */
        .faded-background {
            background-image: radial-gradient(at 100% 0%, rgba(139, 123, 97, 0.63) 0%, transparent 100%),
                              radial-gradient(at 0% 100%, #FBF9F6 0%, #FBF9F6 100%);
            background-color: #FBF9F6;
        }
        /* QR Code Input Styling (Modern and central) */
        input[name="code"] {
            width: 100%;
            padding: 0.8rem 0;
            text-align: center;
            border: 1px solid #D1D5DB; 
            border-radius: 0.75rem;
            background-color: #FBF9F6; /* brand-beige background */
            font-size: 1.5rem;
            letter-spacing: 5px;
            font-weight: 700;
            color: #4D4C48;
            transition: all 0.2s;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        input[name="code"]:focus {
            background: #fff;
            border-color: #8B7B61; 
            box-shadow: 0 0 0 3px rgba(139, 123, 97, 0.2);
            outline: none;
        }
        /* QR container styling */
        .qr-container {
            text-align: center;
            background: white;
            border: 1px solid #eee;
            border-radius: 10px;
            padding: 15px;
            margin: 15px 0;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        .qr-container img {
            width: 180px;
            height: 180px;
            border-radius: 6px;
        }
        .secret-box {
            background: #F4F4F4;
            padding: 8px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 0.9rem;
            color: #4D4C48;
            text-align: center;
            margin-bottom: 15px;
            border: 1px dashed #D1D5DB;
            cursor: copy;
            transition: background 0.2s;
        }
        .secret-box:hover {
            background: #E8E8E8;
        }
    </style>
</head>
<body class="antialiased">
    <div class="auth-wrapper flex rounded-xl shadow-2xl overflow-hidden">
        <div class="hidden lg:block lg:w-1/2 relative p-8 faded-background">
            <div class="absolute inset-0 bg-cover bg-center opacity-10" style="background-image: url('https://placehold.co/1000x1200/DCCEB8/3A3A3A?text=Jewelery');"></div>
            
            <div class="relative flex flex-col justify-center items-start h-full p-8 text-left">
                <h1 class="font-serif text-5xl font-extrabold text-brand-dark leading-snug">
                    Premium Security
                </h1>
                <p class="mt-4 text-brand-subtext text-lg max-w-sm">
                    Enhance your account safety with a second layer of protection.
                </p>
                <p class="mt-8 text-sm text-brand-teal font-semibold border border-brand-teal/50 px-4 py-2 rounded-full">
                    Protection is Key.
                </p>
            </div>
        </div>

        <div class="w-full lg:w-1/2 bg-white flex justify-center items-center p-6 sm:p-8 md:p-10">
            <div class="w-full max-w-sm">
                <h2 class="font-serif text-2xl font-bold text-brand-dark mb-2 text-left">
                    Secure Your Account
                </h2>
                <p class="text-brand-subtext text-left mb-4 text-sm">
                    Follow the steps below to enable 2-step verification.
                </p>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-100 text-red-700 p-2.5 rounded-lg mb-3 text-xs border border-red-200" role="alert">
                        <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <div class="instructions space-y-2 text-xs text-brand-dark mb-4">
                    <p class="flex items-start gap-2">
                        <span class="font-bold text-brand-teal">1.</span>
                        Install an authenticator app (e.g., Google Authenticator) on your phone.
                    </p>
                    <p class="flex items-start gap-2">
                        <span class="font-bold text-brand-teal">2.</span>
                        Scan the QR code below or manually enter the secret key.
                    </p>
                    <p class="flex items-start gap-2">
                        <span class="font-bold text-brand-teal">3.</span>
                        Enter the 6-digit code from the app to finish setup.
                    </p>
                </div>

                <div class="qr-container">
                    <img src="<?= htmlspecialchars($qrCodeUrl) ?>" alt="2FA QR Code" class="mx-auto">
                </div>

                <div id="secret-key-box" class="secret-box cursor-pointer select-all" title="Click to copy secret key">
                    <span class="font-bold text-brand-teal mr-2">Secret Key:</span> <?= htmlspecialchars($secret) ?>
                </div>

                <div class="p-2.5 bg-yellow-50/70 text-brand-dark border border-yellow-200 rounded-lg mb-4 text-xs leading-snug">
                    <i class="fa-solid fa-triangle-exclamation text-brand-teal mr-2"></i>
                    <strong>Important:</strong> Save this key in a secure location. It's needed to recover your 2FA if you lose your phone.
                </div>

                <form method="POST" class="space-y-3">
                    <div class="input-group">
                        <input name="code" type="text" placeholder="000000" maxlength="6" pattern="\d{6}" required autofocus>
                    </div>
                    <button type="submit" class="w-full btn-primary py-3 rounded-xl font-semibold text-base tracking-wider">
                        Verify & Complete Setup
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    // Copy to clipboard functionality for the secret key
    document.getElementById('secret-key-box').addEventListener('click', function() {
        const secretText = this.textContent.replace('Secret Key:', '').trim();
        navigator.clipboard.writeText(secretText).then(() => {
            alert('Secret Key copied to clipboard!');
        }).catch(err => {
            console.error('Could not copy text: ', err);
        });
    });

    // Auto-hide alerts after 4 seconds
    document.addEventListener("DOMContentLoaded", () => {
        const alerts = document.querySelectorAll("[role='alert']");
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.transition = "opacity 0.5s ease";
                alert.style.opacity = "0";
                setTimeout(() => alert.remove(), 500);
            }, 4000); 
        });
    });
    </script>
</body>
</html>