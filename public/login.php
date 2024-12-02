<!-- login.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - 81 Buell Utilities</title>
    <link rel="icon" href="favicon.ico">
    <link rel="stylesheet" type="text/css" href="css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
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
            <div class="input-container">
                <i class="fa fa-user icon" style="color:black;"></i>
                <input class="user-input" type="text" name="username" placeholder="Username" required>
            </div>
            <div class="input-container">
                <i class="fa fa-lock icon" style="color:black;"></i>
                <input class="pass-input" type="password" name="password" placeholder="Password" required>
            </div>
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