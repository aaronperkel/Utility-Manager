<!-- register.php -->
<?php
include '../app/connect-DB.php';

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
        $message = 'User created succesfully!';
        header("Location: login.php?message=$message");
    } catch (PDOException $e) {
        $error_message = 'There was an error creating your account.';
        header("Location: login.php?error=$error_message");
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - 81 Buell Utilties</title>
    <link rel="icon" href="favicon.ico">
    <link rel="stylesheet" type="text/css" href="css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
</head>

<body>
    <header>
        <h1>81 Buell Utilties</h1>
        <h2>Register</h2>
    </header>

    <main>
        <?php
        if (isset($_GET['error'])) {
            echo '<div class="panel pale-red leftbar border-red">';
            echo '<p>' . htmlspecialchars($_GET['error']) . '</p>';
            echo '</div>';
        }
        if (isset($_GET['message'])) {
            echo '<div class="panel pale-green leftbar border-green">';
            echo '<p>' . htmlspecialchars($_GET['message']) . '</p>';
            echo '</div>';
        }

        /*
        <form method="post" action="register.php">
            <div class="input-container">
                <i class="fa fa-user icon" style="color:black;"></i>
                <input class="user-input" type="text" name="username" placeholder="Username" required>
            </div>
            <div class="input-container">
                <i class="fa fa-lock icon" style="color:black;"></i>
                <input class="pass-input" type="password" name="password" placeholder="Password" required>
            </div>
            <button type="submit">Register</button>
        </form>
        */
        ?>

        <div class="panel pale-red leftbar border-red">
            <p>We are currently not accepting new users.</p><br>
            <p>Please contact <a href="mailto:me@aaronperkel.com">me@aaronperkel.com</a> for help.</p>
        </div>
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