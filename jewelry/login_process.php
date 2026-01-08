<?php
// login_process.php
session_start();
require_once 'db_connect.php'; // Assuming this provides the $pdo object

// Helper function to centralize redirection logic
function get_redirect_url($user_role) {
    if ($user_role === 'admin') {
        // --- ADMIN REDIRECT ---
        return 'admin_dashboard.php'; 
    } else {
        // --- CUSTOMER/DEFAULT REDIRECT ---
        // Redirects customers to the new jewelry landing page
        return 'index_user.php'; 
    }
}

// Check if this is an AJAX request
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Get and sanitize form inputs
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Validate form inputs
    if (empty($email) || empty($password)) {
        if ($is_ajax) {
            echo json_encode(['success' => false, 'message' => 'Please enter both email and password.']);
            exit;
        }
        $_SESSION['error'] = "Please enter both email and password.";
        echo "<script>window.top.location.href='login.php';</script>";
        exit;
    }

    try {
        // Prepare SQL statement to find user by email. We must fetch Role and Is_Logged_In.
        $stmt = $pdo->prepare("SELECT User_ID, Name, Email, Password, Role, user_secret_key, last_2fa_verification, Is_Logged_In FROM user WHERE Email = :email LIMIT 1");
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Verify password
            if (password_verify($password, $user['Password'])) {


                $redirect_url = get_redirect_url($user['Role']);

                // Check if user has 2FA enabled
                if (!empty($user['user_secret_key'])) {
                    $requires_2fa = true;

                    if (!empty($user['last_2fa_verification'])) {
                        $last_verification = strtotime($user['last_2fa_verification']);
                        $current_time = time();
                        $hours_since_verification = ($current_time - $last_verification) / 3600;

                        if ($hours_since_verification < 24) {
                            $requires_2fa = false;
                        }
                    }

                    if ($requires_2fa) {
                        $_SESSION['pending_2fa_user_id'] = $user['User_ID'];
                        if ($is_ajax) {
                            echo json_encode(['success' => false, 'redirect' => 'verify_2fa.php', 'message' => '2FA required']);
                            exit;
                        }
                        // Redirect to 2FA verification page
                        echo "<script>window.top.location.href='verify_2fa.php';</script>";
                        exit;
                    } else {
                        // User verified within 24 hours - log in directly
                        $_SESSION['User_ID'] = $user['User_ID'];
                        $_SESSION['Name'] = $user['Name'];
                        $_SESSION['Email'] = $user['Email'];
                        $_SESSION['Role'] = $user['Role'];
                        $_SESSION['2fa_verified'] = true;
                        $_SESSION['2fa_verified_at'] = time();

                        if ($is_ajax) {
                            echo json_encode(['success' => true, 'redirect' => $redirect_url]);
                            exit;
                        }
                        // Successful Login Redirect
                        echo "<script>window.top.location.href='" . $redirect_url . "';</script>";
                        exit;
                    }

                } else {
                    // No 2FA - log in directly
                    $_SESSION['User_ID'] = $user['User_ID'];
                    $_SESSION['Name'] = $user['Name'];
                    $_SESSION['Email'] = $user['Email'];
                    $_SESSION['Role'] = $user['Role'];

                    if ($is_ajax) {
                        echo json_encode(['success' => true, 'redirect' => $redirect_url]);
                        exit;
                    }
                    // Successful Login Redirect
                    echo "<script>window.top.location.href='" . $redirect_url . "';</script>";
                    exit;
                }

            } else {
                if ($is_ajax) {
                    echo json_encode(['success' => false, 'message' => 'Incorrect password.']);
                    exit;
                }
                $_SESSION['error'] = "Incorrect password.";
                // Redirect on password failure
                echo "<script>window.top.location.href='login.php';</script>";
                exit;
            }
        } else {
            if ($is_ajax) {
                echo json_encode(['success' => false, 'message' => 'No account found with that email.']);
                exit;
            }
            $_SESSION['error'] = "No account found with that email.";
            // Redirect on email not found
            echo "<script>window.top.location.href='login.php';</script>";
            exit;
        }
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        if ($is_ajax) {
            echo json_encode(['success' => false, 'message' => 'Database error occurred. Please try again.']);
            exit;
        }
        $_SESSION['error'] = "Database error occurred. Please try again.";
        // Redirect on database error
        echo "<script>window.top.location.href='login.php';</script>";
        exit;
    }
} else {
    if ($is_ajax) {
        echo json_encode(['success' => false, 'message' => 'Invalid access.']);
        exit;
    }
    $_SESSION['error'] = "Invalid access.";
    // Redirect on invalid request method
    echo "<script>window.top.location.href='login.php';</script>";
    exit;
}
?>