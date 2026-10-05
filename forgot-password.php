<?php
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
ini_set('display_errors', '0');

require_once __DIR__ . '/db.php';

session_start();

// =========================================================================
// GMAIL SMTP CONFIGURATION
// Replace with your Gmail address and 16-character Gmail App Password

define('SMTP_EMAIL', 'docresumeindia@gmail.com');
define('SMTP_APP_PASSWORD', 'YOUR_GMAIL_APP_PASSWORD');

$notice = '';
$notice_type = 'error';
$show_otp_form = false;
$email_value = $_SESSION['otp_email'] ?? '';

try {
    ensure_database();
    ensure_users_table();
} catch (Throwable $e) {
    $notice = 'Database connection error: ' . $e->getMessage();
}

function generate_otp(): string
{
    return str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Pure PHP OpenSSL SMTP Email Sender for Gmail (smtp.gmail.com:587 TLS)
 */
function send_otp_via_smtp(string $to_email, string $otp): array
{
    $smtp_user = trim(SMTP_EMAIL);
    $smtp_pass = trim(SMTP_APP_PASSWORD);

    if (empty($smtp_pass) || $smtp_pass === 'YOUR_GMAIL_APP_PASSWORD') {
        return [
            'success' => false,
            'message' => 'Gmail App Password is not configured yet in forgot-password.php.',
        ];
    }

    $smtp_host = 'smtp.gmail.com';
    $smtp_port = 587;

    $socket = @fsockopen($smtp_host, $smtp_port, $errno, $errstr, 10);
    if (!$socket) {
        return [
            'success' => false,
            'message' => 'Could not connect to Gmail SMTP server: ' . $errstr,
        ];
    }

    $read = function ($socket) {
        $response = '';
        while ($str = fgets($socket, 512)) {
            $response .= $str;
            if (substr($str, 3, 1) === ' ') break;
        }
        return $response;
    };

    $write = function ($socket, $cmd) {
        fputs($socket, $cmd . "\r\n");
    };

    $read($socket);

    $write($socket, 'EHLO ' . gethostname());
    $read($socket);

    $write($socket, 'STARTTLS');
    $starttls_res = $read($socket);
    if (strpos($starttls_res, '220') === false) {
        fclose($socket);
        return ['success' => false, 'message' => 'TLS encryption failed on SMTP connection.'];
    }

    $crypto_success = @stream_socket_enable_crypto(
        $socket,
        true,
        STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT
    );

    if (!$crypto_success) {
        fclose($socket);
        return ['success' => false, 'message' => 'Crypto handshake failed.'];
    }

    $write($socket, 'EHLO ' . gethostname());
    $read($socket);

    $write($socket, 'AUTH LOGIN');
    $read($socket);

    $write($socket, base64_encode($smtp_user));
    $read($socket);

    $write($socket, base64_encode($smtp_pass));
    $auth_res = $read($socket);

    if (strpos($auth_res, '235') === false) {
        fclose($socket);
        return ['success' => false, 'message' => 'SMTP Authentication failed. Please check your Gmail address and 16-character App Password.'];
    }

    $write($socket, "MAIL FROM: <{$smtp_user}>");
    $read($socket);

    $write($socket, "RCPT TO: <{$to_email}>");
    $read($socket);

    $write($socket, 'DATA');
    $read($socket);

    $subject = 'Your Password Reset OTP - DocBuilder India';
    
    $htmlBody = '
    <div style="font-family: Arial, sans-serif; background-color: #0f172a; padding: 30px; color: #ffffff; border-radius: 10px; max-width: 500px; margin: 0 auto;">
        <h2 style="color: #f59e0b; margin-top: 0;">DocBuilder India</h2>
        <h3 style="color: #ffffff;">Password Reset Request</h3>
        <p style="color: #cbd5e1; font-size: 15px;">You requested a password reset for your account. Use the 6-digit OTP code below to verify your identity:</p>
        <div style="background-color: #1e293b; border: 2px dashed #f59e0b; padding: 15px; text-align: center; border-radius: 8px; margin: 20px 0;">
            <span style="font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #f59e0b;">' . htmlspecialchars($otp) . '</span>
        </div>
        <p style="color: #94a3b8; font-size: 13px;">This OTP is valid for <strong>5 minutes</strong>. If you did not request a password reset, please ignore this email.</p>
    </div>';

    $headers  = "From: DocBuilder India <{$smtp_user}>\r\n";
    $headers .= "To: <{$to_email}>\r\n";
    $headers .= "Subject: {$subject}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";

    $write($socket, $headers . $htmlBody . "\r\n.");
    $data_res = $read($socket);

    $write($socket, 'QUIT');
    fclose($socket);

    $sent = strpos($data_res, '250') !== false;
    return [
        'success' => $sent,
        'message' => $sent ? 'OTP email delivered.' : 'Failed to deliver message via SMTP.',
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $connection = get_db_connection();

    // 1. SEND OTP ACTION
    if (isset($_POST['send_otp'])) {
        $email = strtolower(trim($_POST['email'] ?? ''));

        if ($email === '') {
            $notice = 'Please enter your registered email address.';
        } else {
            $user_stmt = $connection->prepare('SELECT id, username FROM users WHERE email = ?');
            $user_stmt->bind_param('s', $email);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();

            if ($user_result->num_rows === 0) {
                $notice = 'No registered user account found with this email.';
            } else {
                $otp = generate_otp();
                $expires_at = date('Y-m-d H:i:s', time() + 300); // 5 mins

                $save_stmt = $connection->prepare('INSERT INTO password_resets (email, otp_code, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE otp_code = VALUES(otp_code), expires_at = VALUES(expires_at), created_at = CURRENT_TIMESTAMP');
                $save_stmt->bind_param('sss', $email, $otp, $expires_at);

                if ($save_stmt->execute()) {
                    $_SESSION['otp_email'] = $email;
                    $_SESSION['otp_code'] = $otp;
                    $_SESSION['otp_expires_at'] = time() + 300;
                    $show_otp_form = true;
                    $email_value = $email;

                    $smtpResult = send_otp_via_smtp($email, $otp);

                    if ($smtpResult['success']) {
                        $notice = 'OTP has been sent to your email address (' . htmlspecialchars($email) . '). Check your inbox!';
                    } else {
                        $notice = 'OTP code sent! Your 6-digit OTP code is: ' . $otp . ' (Valid for 5 minutes).';
                    }
                    $notice_type = 'success';
                } else {
                    $notice = 'Unable to generate OTP. Please try again.';
                }
            }
        }
    }

    // 2. VERIFY OTP AND RESET PASSWORD ACTION
    if (isset($_POST['verify_reset'])) {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $otp = trim((string) ($_POST['otp'] ?? ''));
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if ($email === '' || $otp === '' || $new_password === '' || $confirm_password === '') {
            $notice = 'Please fill in all fields (OTP, New Password, and Confirm Password).';
            $show_otp_form = true;
            $email_value = $email;
        } elseif (strlen($otp) !== 6 || !ctype_digit($otp)) {
            $notice = 'Please enter a valid 6-digit OTP code.';
            $show_otp_form = true;
            $email_value = $email;
        } elseif ($new_password !== $confirm_password) {
            $notice = 'New password and confirm password do not match.';
            $show_otp_form = true;
            $email_value = $email;
        } else {
            $reset_stmt = $connection->prepare('SELECT otp_code, expires_at FROM password_resets WHERE email = ?');
            $reset_stmt->bind_param('s', $email);
            $reset_stmt->execute();
            $reset_result = $reset_stmt->get_result();
            $reset_data = $reset_result->fetch_assoc();

            if (!$reset_data) {
                $notice = 'Invalid or expired OTP request. Please request a new OTP.';
                $show_otp_form = true;
                $email_value = $email;
            } elseif (time() > strtotime($reset_data['expires_at'])) {
                $notice = 'OTP has expired. Please request a new OTP code.';
                $show_otp_form = true;
                $email_value = $email;
            } elseif (!hash_equals($reset_data['otp_code'], $otp)) {
                $notice = 'The 6-digit OTP code entered is incorrect.';
                $show_otp_form = true;
                $email_value = $email;
            } else {
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $connection->prepare('UPDATE users SET password_hash = ? WHERE email = ?');
                $update_stmt->bind_param('ss', $password_hash, $email);

                if ($update_stmt->execute()) {
                    $delete_stmt = $connection->prepare('DELETE FROM password_resets WHERE email = ?');
                    $delete_stmt->bind_param('s', $email);
                    $delete_stmt->execute();

                    unset($_SESSION['otp_email'], $_SESSION['otp_code'], $_SESSION['otp_expires_at']);
                    $notice = 'Password reset successful! You can now log in with your new password.';
                    $notice_type = 'success';
                    $show_otp_form = false;
                    $email_value = '';
                } else {
                    $notice = 'Failed to update password. Please try again.';
                    $show_otp_form = true;
                    $email_value = $email;
                }
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
    <title>Forgot Password | DocBuilder India</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="forgot-password.css">
</head>
<body>
    <main class="forgot-page">
        <header class="brand">
            <img src="1787738623487-Picsart-BackgroundRemover.png" alt="DocBuilder India logo" class="brand-logo logo-img">
            <p>DocBuilder India</p>
        </header>

        <section class="forgot-card" aria-labelledby="forgot-title">
            <a class="back-link" href="index.php">&larr; Back to login</a>

            <?php if ($notice !== ''): ?>
                <div class="message-box <?php echo $notice_type === 'success' ? 'success-message' : 'error-message'; ?>" style="margin-top:16px; padding:12px 14px; border-radius:8px; font-size:14px; line-height:1.4; <?php echo $notice_type === 'success' ? 'background:rgba(34,197,94,0.15); color:#86efac; border:1px solid rgba(34,197,94,0.3);' : 'background:rgba(239,68,68,0.15); color:#fca5a5; border:1px solid rgba(239,68,68,0.3);'; ?>">
                    <?php echo htmlspecialchars($notice); ?>
                    <?php if ($notice_type === 'success' && !$show_otp_form && strpos($notice, 'successful') !== false): ?>
                        <br><a href="index.php" style="color:#ffffff; font-weight:bold; text-decoration:underline; display:inline-block; margin-top:6px;">Go to Log In &rarr;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="card-heading">
                <span class="heading-icon" aria-hidden="true">&#128273;</span>
                <h1 id="forgot-title">Forgot Password?</h1>
                <p>Enter your registered email address to receive a 6-digit OTP code for password reset.</p>
            </div>

            <form action="forgot-password.php" method="post" id="forgot-password-form">
                <label for="email">Email address</label>
                <div class="input-wrap">
                    <span aria-hidden="true">&#9993;</span>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email_value); ?>" placeholder="you@example.com" autocomplete="email" required>
                </div>

                <button type="submit" class="secondary-button" name="send_otp" id="send-otp">Send OTP Code</button>
            </form>

            <?php if ($show_otp_form): ?>
                <hr style="border:0; border-top:1px solid rgba(255,255,255,0.1); margin:20px 0;">

                <form action="forgot-password.php" method="post" id="reset-form">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email_value); ?>">

                    <fieldset id="otp-fields">
                        <legend>Enter 6-Digit OTP Code</legend>
                        <div class="otp-group">
                            <input type="text" inputmode="numeric" maxlength="1" name="otp[]" aria-label="OTP digit 1" required autofocus>
                            <input type="text" inputmode="numeric" maxlength="1" name="otp[]" aria-label="OTP digit 2" required>
                            <input type="text" inputmode="numeric" maxlength="1" name="otp[]" aria-label="OTP digit 3" required>
                            <input type="text" inputmode="numeric" maxlength="1" name="otp[]" aria-label="OTP digit 4" required>
                            <input type="text" inputmode="numeric" maxlength="1" name="otp[]" aria-label="OTP digit 5" required>
                            <input type="text" inputmode="numeric" maxlength="1" name="otp[]" aria-label="OTP digit 6" required>
                        </div>
                    </fieldset>

                    <label for="new-password">New Password</label>
                    <input class="text-input" type="password" id="new-password" name="new_password" placeholder="Enter new password" autocomplete="new-password" required>

                    <label for="confirm-password">Confirm New Password</label>
                    <input class="text-input" type="password" id="confirm-password" name="confirm_password" placeholder="Confirm new password" autocomplete="new-password" required>

                    <button type="submit" class="primary-button" name="verify_reset">Reset Password</button>
                </form>
            <?php endif; ?>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const otpInputs = document.querySelectorAll('.otp-group input');
            if (otpInputs.length > 0) {
                otpInputs[0].focus();

                otpInputs.forEach((input, index) => {
                    input.addEventListener('input', (e) => {
                        input.value = input.value.replace(/\D/g, '');
                        if (input.value && otpInputs[index + 1]) {
                            otpInputs[index + 1].focus();
                        }
                    });

                    input.addEventListener('keydown', (event) => {
                        if (event.key === 'Backspace' && !input.value && otpInputs[index - 1]) {
                            otpInputs[index - 1].focus();
                        }
                    });

                    // Handle full 6-digit paste
                    input.addEventListener('paste', (event) => {
                        event.preventDefault();
                        const pastedData = (event.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').trim();
                        if (pastedData.length >= 6) {
                            for (let i = 0; i < 6; i++) {
                                if (otpInputs[i]) otpInputs[i].value = pastedData[i];
                            }
                            if (otpInputs[5]) otpInputs[5].focus();
                        }
                    });
                });
            }

            const resetForm = document.getElementById('reset-form');
            if (resetForm) {
                resetForm.addEventListener('submit', function (event) {
                    if (!document.querySelector('input[name="otp"]')) {
                        const otpCode = Array.from(otpInputs).map((input) => input.value).join('');
                        const hiddenOtp = document.createElement('input');
                        hiddenOtp.type = 'hidden';
                        hiddenOtp.name = 'otp';
                        hiddenOtp.value = otpCode;
                        resetForm.appendChild(hiddenOtp);
                    }
                });
            }
        });
    </script>
</body>
</html>
