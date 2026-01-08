<?php
session_start();
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
        \'brand-teal\': \'#8B7B61\', // This is your gold/bronze color
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
    <title>Sign Up - Jewelery</title>
    <?= $tailwind_setup ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* Custom styles to match the index.html aesthetic and create the two-column layout */
        body {
            font-family: 'Inter', sans-serif;
            background-color: #FBF9F6; /* brand-beige */
            color: #4D4C48; /* brand-dark */
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .auth-wrapper {
            max-width: 1000px;
            width: 90%;
            height: 90vh; /* Use vh for a nice full-page look */
        }
        /* Custom button styles from index.html */
        .btn-primary {
            background-color: #8B7B61;
            color: white;
            border: 2px solid #8B7B61;
            transition: all 0.2s;
        }
        .btn-primary:hover {
            background-color: #7a6c54;
            border-color: #7a6c54;
        }
        /* Input group styling */
        .input-style {
            padding: 0.75rem 1rem;
            border: 1px solid #D1D5DB; /* light gray border */
            border-radius: 0.5rem;
            background-color: white;
            transition: all 0.2s;
        }
        .input-style:focus {
            outline: none;
            border-color: #8B7B61; /* brand-teal focus */
            box-shadow: 0 0 0 3px rgba(139, 123, 97, 0.2);
        }

        /* FADED BACKGROUND STYLE (Copied from Login) */
        .faded-background {
            /* radial-gradient(at <position>, <color> <percentage>, <color> <percentage>) */
            background-image: radial-gradient(at 100% 0%, rgba(139, 123, 97, 0.63) 0%, transparent 100%),
                              radial-gradient(at 0% 100%, #FBF9F6 0%, #FBF9F6 100%);
            background-color: #FBF9F6;
        }

        /* Hide native password reveal/clear buttons (major browsers) */
        input[type="password"]::-ms-reveal,
        input[type="password"]::-ms-clear {
            display: none !important;
        }

        input[type="password"]::-webkit-credentials-show-password-button,
        input[type="password"]::-webkit-password-toggle-button,
        input[type="password"]::-webkit-textfield-decoration-container {
            display: none !important;
        }
    </style>
</head>
<body class="antialiased">

<div class="auth-wrapper flex rounded-xl shadow-2xl overflow-hidden">
    <div class="hidden lg:block lg:w-1/2 relative p-8 faded-background">
        <div class="absolute inset-0 bg-cover bg-center opacity-10" style="background-image: url('https://placehold.co/1000x1200/DCCEB8/3A3A3A?text=Jewelery');"></div>

        <div class="relative flex flex-col justify-center items-start h-full p-8 text-left">
            <h1 class="font-serif text-5xl font-extrabold text-brand-dark leading-snug">
                Adorn Yourself in Elegance
            </h1>
            <p class="mt-4 text-brand-subtext text-lg max-w-sm">
                Sign up to discover exclusive collections and personal styling advice.
            </p>
            <p class="mt-8 text-sm text-brand-teal font-semibold border border-brand-teal/50 px-4 py-2 rounded-full">
                New Arrivals Every Week.
            </p>
        </div>
    </div>

    <div class="w-full lg:w-1/2 bg-white flex justify-center items-center p-8 sm:p-12 md:p-16">
        <div class="w-full max-w-sm">
            <h2 class="font-serif text-4xl font-bold text-brand-dark mb-2 text-left">
                Create Your Account
            </h2>
            <p class="mb-6 text-brand-subtext text-left">
                Already a member? 
                <a href="login.php" class="text-brand-teal hover:underline font-semibold transition-colors">Log in</a>
            </p>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-4 text-sm border border-red-200" role="alert">
                    <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <form action="signup_process.php" method="POST" class="space-y-4">
                <div class="input-group">
                    <input type="text" id="name" name="name" placeholder="Full Name" required 
                           class="w-full input-style text-brand-text">
                </div>

                <div class="input-group">
                    <input type="email" id="email" name="email" placeholder="Email Address" required 
                           class="w-full input-style text-brand-text">
                </div>

                <div class="input-group relative">
                    <input type="password" id="password" name="password" placeholder="Enter your password" required 
                           class="w-full input-style pr-10 text-brand-text">
                    <span class="toggle-password absolute right-3 top-1/2 transform -translate-y-1/2 cursor-pointer text-brand-subtext hover:text-brand-dark transition-colors" 
                          onclick="togglePassword('password', this)">
                        <i class="fa-solid fa-eye"></i>
                    </span>
                </div>

                <div class="input-group relative">
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required 
                           class="w-full input-style pr-10 text-brand-text">
                    <span class="toggle-password absolute right-3 top-1/2 transform -translate-y-1/2 cursor-pointer text-brand-subtext hover:text-brand-dark transition-colors" 
                          onclick="togglePassword('confirm_password', this)">
                        <i class="fa-solid fa-eye"></i>
                    </span>
                </div>

                <div class="flex items-start pt-2">
                    <div class="flex items-center h-5">
                        <input id="terms" name="terms" type="checkbox" required 
                               class="focus:ring-brand-teal h-4 w-4 text-brand-teal border-gray-300 rounded cursor-pointer">
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="terms" class="text-brand-subtext">
                            I agree to the 
                            <a href="#" class="text-brand-teal hover:underline font-medium">Terms & Conditions</a>
                        </label>
                    </div>
                </div>

                <button type="submit" class="w-full btn-primary py-3.5 rounded-xl font-semibold text-lg tracking-wider mt-6">
                    Create account
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    function togglePassword(id, el) {
        const input = document.getElementById(id);
        const icon = el.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }
</script>

</body>
</html>