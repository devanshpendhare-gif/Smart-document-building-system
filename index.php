<?php
require_once __DIR__ . '/db.php';

session_start();

$login_error = '';
$signup_error = '';
$db_error = '';

try {
    ensure_database();
    ensure_users_table();
} catch (Throwable $e) {
    $db_error = $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login_submit'])) {
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if ($db_error !== '') {
            $login_error = 'Database connection error: ' . $db_error;
        } elseif ($email === '' || $password === '') {
            $login_error = 'Please enter your email and password.';
        } else {
            try {
                $connection = get_db_connection();
                $stmt = $connection->prepare('SELECT id, username, password_hash FROM users WHERE email = ?');
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();

                if ($user && password_verify($password, $user['password_hash'])) {
                    $_SESSION['user_id'] = (int) $user['id'];
                    $_SESSION['user_name'] = $user['username'];
                    $_SESSION['user_email'] = $email;
                    $_SESSION['logged_in'] = true;
                    header('Location: home.php');
                    exit;
                }

                $login_error = 'Invalid email or password.';
            } catch (Throwable $e) {
                $login_error = 'Login failed: ' . $e->getMessage();
            }
        }
    }

    if (isset($_POST['signup_submit'])) {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $confirm_password = trim($_POST['confirm-password'] ?? '');

        if ($db_error !== '') {
            $signup_error = 'Database connection error: ' . $db_error;
        } elseif ($username === '' || $email === '' || $password === '' || $confirm_password === '') {
            $signup_error = 'Please fill in all signup fields.';
        } elseif ($password !== $confirm_password) {
            $signup_error = 'Passwords do not match.';
        } else {
            try {
                $connection = get_db_connection();
                $check_stmt = $connection->prepare('SELECT id FROM users WHERE email = ?');
                $check_stmt->bind_param('s', $email);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();

                if ($check_result->num_rows > 0) {
                    $signup_error = 'This email is already registered.';
                } else {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $insert_stmt = $connection->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
                    $insert_stmt->bind_param('sss', $username, $email, $password_hash);

                    if ($insert_stmt->execute()) {
                        $_SESSION['user_name'] = $username;
                        $_SESSION['user_email'] = $email;
                        $_SESSION['logged_in'] = true;
                        header('Location: home.php');
                        exit;
                    }

                    $signup_error = 'Unable to create account. Please try again.';
                }
            } catch (Throwable $e) {
                $signup_error = 'Signup failed: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DocBuilder India | Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="login.css">
</head>
<body>
    <div class="main">
        <div class="logo">
            <img src="1787738623487-Picsart-BackgroundRemover.png" alt="logo" class="logo-img" >
        </div>
        <div class="h1">
            <h1>DocBuilder India</h1>
        </div>

        <div class="tabs-container">
            <input type="radio" id="login-tab" name="auth-switch" checked>
            <input type="radio" id="signup-tab" name="auth-switch">

            <div class="tab-buttons">
                <label for="login-tab" class="tab-btn">Log In</label>
                <label for="signup-tab" class="tab-btn">Sign Up</label>
            </div>

            <div class="tab-content-area">
                <div class="content login-content">
                    <form action="index.php" method="POST" id="login-form">
                        <?php if (!empty($login_error)): ?>
                            <p class="form-message error"><?php echo htmlspecialchars($login_error); ?></p>
                        <?php endif; ?>
                        <input type="email" name="email" placeholder="Email" required>
                        <div class="login-password">
                            <input type="password" name="password" placeholder="Password" required>
                        </div>
                        <div class="login-button">
                            <button type="submit" name="login_submit" class="auth-submit-btn" style="background-color:#f59e0b; color:#ffffff; border:none; padding:10px 20px; border-radius:10px; cursor:pointer; font-family:'Playfair Display', serif; font-size:20px; width:260px; height:44px; display:flex; align-items:center; justify-content:center; margin:10px auto;">Log In</button>
                        </div>
                        <div class="tab-content">
                            <a href="forgot-password.php" class="forgot-link">Forgot Password?</a>
                        </div>
                        <button type="button" class="google-btn">
                            <div class="google-icon-wrapper">
                                <img class="google-icon" src="https://www.svgrepo.com/show/475656/google-color.svg" alt="Google logo">
                            </div>
                            <p class="btn-text"><b>Log in with Google</b></p>
                        </button>
                    </form>
                </div>

                <div class="content signup-content">
                    <form action="index.php" method="POST" id="signup-form">
                        <?php if (!empty($signup_error)): ?>
                            <p class="form-message error"><?php echo htmlspecialchars($signup_error); ?></p>
                        <?php endif; ?>
                        <input type="text" id="username" name="username" placeholder="Username" required>
                        <input type="email" id="signup-email" name="email" placeholder="Email" required>
                        <input type="password" id="signup-password" name="password" placeholder="Password" required>
                        <input type="password" id="confirm-password" name="confirm-password" placeholder="Confirm Password" required>
                        <div class="signup-button">
                            <button type="submit" name="signup_submit" class="auth-submit-btn" style="background-color:#f59e0b; color:#ffffff; border:none; padding:10px 20px; border-radius:10px; cursor:pointer; font-family:'Playfair Display', serif; font-size:20px; width:260px; height:44px; display:flex; align-items:center; justify-content:center; margin:10px auto;">Sign Up</button>
                        </div>
                        <button type="button" class="google-btn">
                            <div class="google-icon-wrapper">
                                <img class="google-icon" src="https://www.svgrepo.com/show/475656/google-color.svg" alt="Google logo">
                            </div>
                            <p class="btn-text"><b>Sign up with Google</b></p>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <script>
        (function () {
            // Replace with your real Google OAuth Client ID from Google Cloud Console
            const GOOGLE_CLIENT_ID = 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com';
            const buttons = document.querySelectorAll('.google-btn');

            function handleGoogleResponse(response) {
                fetch('google-login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ credential: response.credential })
                })
                .then(function (res) {
                    return res.json();
                })
                .then(function (data) {
                    if (data && data.status === 'success') {
                        window.location.href = data.redirect || 'home.php';
                    } else {
                        alert(data && data.message ? data.message : 'Google sign-in failed.');
                    }
                })
                .catch(function () {
                    alert('Google sign-in failed.');
                });
            }

            if (typeof google !== 'undefined' && google.accounts && google.accounts.id && GOOGLE_CLIENT_ID !== 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com') {
                google.accounts.id.initialize({
                    client_id: GOOGLE_CLIENT_ID,
                    callback: handleGoogleResponse
                });
            }

            buttons.forEach(function (button) {
                button.addEventListener('click', function () {
                    if (GOOGLE_CLIENT_ID === 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com') {
                        const emailInput = prompt("Google Sign-In / Sign-Up:\n\nEnter your Google email address to sign up or log in (or configure your Google Client ID in index.php):");
                        if (emailInput && emailInput.trim()) {
                            const name = emailInput.split('@')[0];
                            fetch('google-login.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ email: emailInput.trim(), name: name })
                            })
                            .then(function (res) { return res.json(); })
                            .then(function (data) {
                                if (data && data.status === 'success') {
                                    window.location.href = data.redirect || 'home.php';
                                } else {
                                    alert(data && data.message ? data.message : 'Google sign-in failed.');
                                }
                            })
                            .catch(function () {
                                alert('Google sign-in failed.');
                            });
                        }
                        return;
                    }

                    if (typeof google !== 'undefined' && google.accounts && google.accounts.id) {
                        google.accounts.id.prompt();
                    } else {
                        alert('Google Sign-In script is loading, please try again.');
                    }
                });
            });
        })();
    </script>
</body>
</html>


  
    
