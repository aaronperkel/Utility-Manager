<!-- login.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - 81 Buell Utilities</title>
    <link rel="stylesheet" type="text/css" href="css/login.css">
</head>

<body>
    <header>
        <h1>81 Buell Utilities</h1>
        <h2>Login</h2>
    </header>

    <main>
        <!-- Add this PHP block within the <main> tag in login.php -->
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
        ?>


        <form method="post" action="authenticate.php">
            <input class="user" type="text" name="username" placeholder="Username" required><br>
            <input class="pass" type="password" name="password" placeholder="Password" required><br>
            <button type="submit">Login</button>
        </form>
    </main>

    <footer>
        <p>
            New user?
            <a href="register.php">
                Register here
            </a>
        </p>
    </footer>
</body>
</html>