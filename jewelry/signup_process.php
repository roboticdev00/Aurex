<?php
session_start();
require 'db_connect.php';
require_once __DIR__ . '/vendor/autoload.php';

$g = new PHPGangsta_GoogleAuthenticator();

// Check if this is an AJAX request
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        if ($is_ajax) {
            echo json_encode(['success' => false, 'message' => 'All fields are required.']);
            exit;
        }
        $_SESSION['error'] = 'All fields are required.';
        header('Location: signup.php');
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        if ($is_ajax) {
            echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
            exit;
        }
        $_SESSION['error'] = 'Invalid email format.';
        header('Location: signup.php');
        exit;
    }

    if ($password !== $confirm_password) {
        if ($is_ajax) {
            echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
            exit;
        }
        $_SESSION['error'] = 'Passwords do not match.';
        header('Location: signup.php');
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM user WHERE Email = ?");
        $stmt->execute([$email]);
        $existing = $stmt->fetch();

        if ($existing) {
            if ($is_ajax) {
                echo json_encode(['success' => false, 'message' => 'This email is already registered.']);
                exit;
            }
            $_SESSION['error'] = 'This email is already registered.';
            header('Location: signup.php');
            exit;
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Generate 2FA secret key
        $secret = $g->createSecret();

        // Insert new user with 2FA secret
        $insert = $pdo->prepare("INSERT INTO user (Name, Email, Password, user_secret_key, Role) VALUES (?, ?, ?, ?, 'customer')");
        $insert->execute([$name, $email, $hashed_password, $secret]);

        // Get the newly created user ID
        $user_id = $pdo->lastInsertId();

        // Store user info in session for QR code display
        $_SESSION['new_user_id'] = $user_id;
        $_SESSION['new_user_email'] = $email;
        $_SESSION['new_user_secret'] = $secret;

        if ($is_ajax) {
            echo json_encode(['success' => true, 'message' => 'Account created successfully!']);
            exit;
        }
        // ✅ Redirect directly to setup_2fa.php as a full page
        header("Location: setup_2fa.php");
        exit;

    } catch (PDOException $e) {
        if ($is_ajax) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
        header('Location: signup.php');
        exit;
    }
} else {
    if ($is_ajax) {
        echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
        exit;
    }
    header('Location: signup.php');
    exit;
}
?>
