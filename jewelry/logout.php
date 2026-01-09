<?php
// logout.php
session_start();
require_once 'db_connect.php'; // Ensure this uses PDO ($pdo)

if (isset($_SESSION['User_ID'])) {
    $user_id = $_SESSION['User_ID'];
    $user_role = $_SESSION['Role'] ?? '';

    // Only update the 'Is_Logged_In' flag if the user was an admin
    if ($user_role === 'admin') {
        try {
            $update_sql = "UPDATE user SET Is_Logged_In = 0 WHERE User_ID = :user_id";
            $stmt = $pdo->prepare($update_sql);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
        } catch (PDOException $e) {
            error_log("Logout error updating Is_Logged_In: " . $e->getMessage());
            // Continue with session destruction even if DB update fails
        }
    }
}

// Destroy all session data
session_unset();
session_destroy();

// Redirect to the main page
header('Location: index_user.php');
exit;
?>