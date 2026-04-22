<?php
require_once __DIR__ . '/includes/db.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    die("Invalid link.");
}

$hashedToken = hash('sha256', $token);

try {
    // Find the token
    $stmt = $pdo->prepare("
        SELECT m.id as token_id, u.id as user_id, u.role 
        FROM magic_tokens m 
        JOIN users u ON m.user_id = u.id 
        WHERE m.token_hash = ? AND m.expires_at > NOW()
    ");
    $stmt->execute([$hashedToken]);
    $auth = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($auth) {
        // Valid token. Delete it to prevent reuse.
        $pdo->prepare("DELETE FROM magic_tokens WHERE id = ?")->execute([$auth['token_id']]);
        
        // Start Secure Session allowing SSO across *.sanpandas.com
        session_set_cookie_params([
            'lifetime' => 86400 * 30, // 30 days
            'path' => '/',
            'domain' => '.sanpandas.com',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();

        // Populate session
        $_SESSION['user_id'] = $auth['user_id'];
        $_SESSION['user_role'] = $auth['role'];
        $_SESSION['logged_in'] = true;

        // Redirect to dashboard
        header("Location: http://academy.sanpandas.com/dashboard.php");
        exit;
    } else {
        die("This magic link is invalid or has expired. Please request a new one.");
    }
} catch (Exception $e) {
    die("An error occurred verifying your link.");
}
