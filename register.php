<!-- register.php -->
<?php
include 'php/connect-DB.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve and sanitize input
    $username = htmlspecialchars(trim($_POST['username']));
    $password = $_POST['password']; // Password policies can be enforced here

    // Generate salt and hash password
    $salt = bin2hex(random_bytes(32));
    $password_hash = sha1($password . $salt);

    // Insert into database
    $sql = 'INSERT INTO users (username, password_hash, salt) VALUES (?, ?, ?)';
    $stmt = $pdo->prepare($sql);
    try {
        $stmt->execute([$username, $password_hash, $salt]);
        header('Location: login.php');
    } catch (PDOException $e) {
        echo 'Error: ' . $e->getMessage();
    }
}
?>

<!-- login.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - 81 Buell Utilties</title>
    <link rel="stylesheet" type="text/css" href="css/login.css">
</head>

<body>
    <header>
        <h1>81 Buell Utilties</h1>
        <h2>Register</h2>
    </header>

    <main>
        <!-- Add this PHP block within the <main> tag in login.php -->
        <?php
        if (isset($_GET['error'])) {
            echo '<div class="panel pale-red leftbar border-red">';
            echo '<p>' . htmlspecialchars($_GET['error']) . '</p>';
            echo '</div>';
        }
        ?>

        <form method="post" action="register.php">
            <input class="user" type="text" name="username" placeholder="Username" required><br>
            <input class="pass" type="password" name="password" placeholder="Password" required><br>
            <button type="submit">Register</button>
        </form>
    </main>

    <footer>
        <p>
            Already have an account?
            <a href="login.php">
                Login here
            </a>
        </p>
    </footer>
</body>
</html>