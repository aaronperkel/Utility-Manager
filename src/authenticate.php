<!-- authenticate.php -->
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
include 'app/connect-DB.php';

// Password validation function
function validate_password($password) {
    if (strlen($password) < 8 || strlen($password) > 25) {
        return 'Password must be between 8 and 25 characters long.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return 'Password must contain at least one uppercase letter.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        return 'Password must contain at least one lowercase letter.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        return 'Password must contain at least one number.';
    }
    if (!preg_match('/[\W_]/', $password)) {
        return 'Password must contain at least one special character.';
    }
    return '';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve and sanitize input
    $username = htmlspecialchars(trim($_POST['username']));
    $password = $_POST['password'];

    // Fetch user from database
    $sql = 'SELECT * FROM users WHERE username = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Check if account is locked
        if ($user['is_locked']) {
            // Replace echo statements with:
            $error_message = urlencode('Your account is locked due to too many failed login attempts.');
            header("Location: login.php?error=$error_message");
            exit;
        }

        // Verify password
        $password_hash = sha1($password . $user['salt']);
        if ($password_hash === $user['password_hash']) {
            // Reset failed attempts
            $sql = 'UPDATE users SET failed_attempts = 0 WHERE username = ?';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$username]);

            // Set session variables
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $user['role'];

            header('Location: index.php');
            exit;
        } else {
            // Increment failed attempts
            $failed_attempts = $user['failed_attempts'] + 1;
            $is_locked = $failed_attempts >= 3 ? 1 : 0;
            $sql = 'UPDATE users SET failed_attempts = ?, is_locked = ? WHERE username = ?';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$failed_attempts, $is_locked, $username]);

            $attempts_left = 3 - $failed_attempts;
            // Replace echo statements with:
            $error_message = urlencode('Invalid password. Please try again.');
            header("Location: login.php?error=$error_message");
            exit;
        }
    } else {
        // Replace echo statements with:
        $error_message = urlencode('Invalid username. Please try again.');
        header("Location: login.php?error=$error_message");
        exit;
    }
}
?>
