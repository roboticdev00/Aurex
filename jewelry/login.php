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
    <title>Aurex</title>
    <?= $tailwind_setup ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
    /* Custom styles to match the consistent aesthetic */
    body {
        font-family: 'Inter', sans-serif;
        background-color: #FBF9F6;
        color: #4D4C48;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .auth-wrapper {
        max-width: 1000px;
        width: 90%;
        height: 90vh;
    }

    /* Custom button styles from index.html (Primary button) */
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

    /* Input group styling, same as sign-up */
    .input-style {
        padding: 0.75rem 1rem;
        border: 1px solid #D1D5DB;
        border-radius: 0.5rem;
        background-color: white;
        transition: all 0.2s;
    }

    .input-style:focus {
        outline: none;
        border-color: #8B7B61;
        box-shadow: 0 0 0 3px rgba(139, 123, 97, 0.2);
    }

    /* NEW FADED BACKGROUND STYLE */
    .faded-background {
        /* radial-gradient(at <position>, <color> <percentage>, <color> <percentage>) */
        background-image: radial-gradient(at 100% 0%, rgba(139, 123, 97, 0.63) 0%, transparent 100%),
            radial-gradient(at 0% 100%, #FBF9F6 0%, #FBF9F6 100%);
        background-color: #FBF9F6;
    }
    </style>
</head>

<body class="antialiased">

    <div class="auth-wrapper flex rounded-xl shadow-2xl overflow-hidden">
        <div class="hidden lg:block lg:w-1/2 relative p-8 faded-background">
            <div class="absolute inset-0 bg-cover bg-center opacity-10"
                style="background-image: url('https://placehold.co/1000x1200/DCCEB8/3A3A3A?text=Aurex');"></div>

            <div class="relative flex flex-col justify-center items-start h-full p-8 text-left">
                <h1 class="font-serif text-5xl font-extrabold text-brand-dark leading-snug">
                    Exclusive Access
                </h1>
                <p class="mt-4 text-brand-subtext text-lg max-w-sm">
                    Log in quickly to manage your orders and view your wishlist.
                </p>
                <!-- <p
                    class="mt-8 text-sm text-brand-teal font-semibold border border-brand-teal/50 px-4 py-2 rounded-full">
                    Elegance Awaits.
                </p> -->
            </div>
        </div>

        <div class="w-full lg:w-1/2 bg-white flex justify-center items-center p-8 sm:p-12 md:p-16">
            <div class="w-full max-w-sm">
                <h2 class="font-serif text-4xl font-bold text-brand-dark mb-2 text-left">
                    Welcome Back!
                </h2>
                <p class="mb-6 text-brand-subtext text-left">
                    Please enter your details to continue shopping.
                </p>

                <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-4 text-sm border border-red-200" role="alert">
                    <?= htmlspecialchars($_SESSION['error']);
                        unset($_SESSION['error']); ?>
                </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['success'])): ?>
                <div class="bg-green-100 text-green-700 p-3 rounded-lg mb-4 text-sm border border-green-200"
                    role="alert">
                    <?= htmlspecialchars($_SESSION['success']);
                        unset($_SESSION['success']); ?>
                </div>
                <?php endif; ?>

                <form action="login_process.php" method="POST" class="space-y-4">
                    <div class="input-group">
                        <input type="email" id="email" name="email" placeholder="Enter your email" required
                            class="w-full input-style text-brand-text">
                    </div>

                    <div class="input-group relative">
                        <input type="password" id="password" name="password" placeholder="Enter your password" required
                            class="w-full input-style pr-10 text-brand-text">
                        <span
                            class="toggle-password absolute right-3 top-1/2 transform -translate-y-1/2 cursor-pointer text-brand-subtext hover:text-brand-dark transition-colors"
                            onclick="togglePassword()">
                            <i class="fa-solid fa-eye" id="toggleIcon"></i>
                        </span>
                    </div>

                    <div class="flex justify-between items-center text-sm">
                        <label class="flex items-center gap-2 text-brand-subtext">
                            <input type="checkbox" name="remember"
                                class="focus:ring-brand-teal h-4 w-4 text-brand-teal border-gray-300 rounded cursor-pointer">
                            Remember me
                        </label>
                        <a href="#" class="text-brand-teal hover:underline font-medium transition-colors">
                            Forgot Password?
                        </a>
                    </div>

                    <div class="flex flex-col gap-4 pt-2">
                        <button type="submit"
                            class="w-full btn-primary py-3.5 rounded-xl font-semibold text-lg tracking-wider">
                            Login
                        </button>

                        <p class="text-center text-brand-subtext text-sm">
                            No account yet?
                            <a href="signup.php" class="text-brand-teal hover:underline font-medium transition-colors">
                                Sign up here
                            </a>
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function togglePassword() {
        const password = document.getElementById('password');
        const icon = document.getElementById('toggleIcon');
        const isHidden = password.type === 'password';

        password.type = isHidden ? 'text' : 'password';
        icon.classList.toggle('fa-eye');
        icon.classList.toggle('fa-eye-slash');

        // Optional: Add a subtle visual feedback on the icon click
        icon.style.transition = "transform 0.15s ease";
        icon.style.transform = "scale(1.4)";
        setTimeout(() => {
            icon.style.transform = "scale(1)";
        }, 150);
    }

    // Auto-hide success/error alerts after 4 seconds
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