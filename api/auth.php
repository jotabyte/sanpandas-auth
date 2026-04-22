<?php
ob_start();                     // Buffer ALL output — catches stray PHP warnings/notices
ini_set('display_errors', 0);   // Never leak PHP errors into the response body
error_reporting(E_ALL);         // Still log internally

require_once __DIR__ . '/../includes/db.php';

ob_clean();                     // Discard anything db.php may have printed
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || (($_SERVER['SERVER_PORT'] ?? 80) == 443);

if ($action === 'request_magic_link') {
    $email = $_POST['email'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid email address']);
        exit;
    }

    try {
        // Find existing user or insert as new student provisional
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            // Reject unknown email — only registered users may log in
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'No account found with this email.']);
            exit;
        } else {
            $userId = $user['id'];
        }

        // Clean up expired tokens for this user
        $pdo->prepare("DELETE FROM magic_tokens WHERE user_id = ? OR expires_at < NOW()")->execute([$userId]);

        // Generate secure 4-digit OTP code
        $code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $hashedCode = hash('sha256', $code);
        $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        $pdo->prepare("INSERT INTO magic_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)")->execute([$userId, $hashedCode, $expires]);

        require_once __DIR__ . '/../includes/PHPMailer/Exception.php';
        require_once __DIR__ . '/../includes/PHPMailer/PHPMailer.php';
        require_once __DIR__ . '/../includes/PHPMailer/SMTP.php';

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.dreamhost.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'auth-noreply@sanpandas.com';
        $mail->Password = 'Dionne2503abcd';
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('auth-noreply@sanpandas.com', 'San Pandas Authenticator');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Your Login Code - San Pandas';
        $mail->Body = "Hello,<br><br>Here is your 4-digit secure code to securely log into your portal:<br><br><h1 style='font-size:32px; letter-spacing:4px;'>{$code}</h1><br>This code expires in 15 minutes.<br><br>If you did not request this, you may safely ignore it.";

        $mail->send();

        // Returning dev_code locally for easy continuity while password is unset
        echo json_encode([
            'success' => true,
            'message' => 'Check your email for your 4-digit code.'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
    }
} elseif ($action === 'verify_code') {
    $email = $_POST['email'] ?? '';
    $code = $_POST['code'] ?? '';

    if (!$email || !$code) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Email and code required']);
        exit;
    }

    $hashedCode = hash('sha256', $code);

    try {
        $stmt = $pdo->prepare("
            SELECT m.id as token_id, u.id as user_id, u.role 
            FROM magic_tokens m 
            JOIN users u ON m.user_id = u.id 
            WHERE u.email = ? AND m.token_hash = ? AND m.expires_at > NOW()
        ");
        $stmt->execute([$email, $hashedCode]);
        $auth = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($auth) {
            // Delete token to prevent reuse
            $pdo->prepare("DELETE FROM magic_tokens WHERE id = ?")->execute([$auth['token_id']]);

            // Start Secure Session allowing SSO across *.sanpandas.com
            session_set_cookie_params([
                'lifetime' => 86400 * 30, // 30 days
                'path' => '/',
                'domain' => '.sanpandas.com',
                'secure' => $isHttps,     // true on HTTPS production, false on HTTP local
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            // Populate session
            $_SESSION['user_id'] = $auth['user_id'];
            $_SESSION['user_role'] = $auth['role'];
            $_SESSION['logged_in'] = true;

            // Server-side redirect — keeps secure cookie intact over HTTPS
            echo json_encode(['success' => true, 'redirect' => 'https://academy.sanpandas.com/dashboard.php']);
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Invalid or expired code.']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error during verification.']);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
