<?php
session_start();
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!is_array($data)) {
    $data = $_POST;
}

$credential = trim((string) ($data['credential'] ?? ''));
$directEmail = strtolower(trim((string) ($data['email'] ?? '')));
$directName = trim((string) ($data['name'] ?? ''));

$email = '';
$name = '';

if ($credential !== '') {
    $tokenInfoUrl = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential);
    $tokenInfoResponse = @file_get_contents($tokenInfoUrl);

    if ($tokenInfoResponse !== false) {
        $payload = json_decode($tokenInfoResponse, true);
        if (is_array($payload) && !empty($payload['email'])) {
            $email = strtolower(trim((string) $payload['email']));
            $name = trim((string) ($payload['name'] ?? $payload['given_name'] ?? preg_replace('/@.*/', '', $email)));
        }
    }
}

if ($email === '' && $directEmail !== '') {
    $email = $directEmail;
    $name = $directName !== '' ? $directName : preg_replace('/@.*/', '', $email);
}

if ($email === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Valid Google user email or token required.']);
    exit;
}

if ($name === '') {
    $name = preg_replace('/@.*/', '', $email);
}

try {
    ensure_database();
    ensure_users_table();
    $conn = get_db_connection();

    // Check if user already exists in DB
    $stmt = $conn->prepare('SELECT id, username, email FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        $userId = (int) $user['id'];
        $username = $user['username'];
    } else {
        // Register new Google user in DB
        $passwordHash = password_hash('GOOGLE_OAUTH_' . bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
        $insertStmt = $conn->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
        $insertStmt->bind_param('sss', $name, $email, $passwordHash);
        
        if (!$insertStmt->execute()) {
            throw new RuntimeException('Could not create Google user record.');
        }
        $userId = (int) $insertStmt->insert_id;
        $username = $name;
    }

    // Set user session
    $_SESSION['logged_in'] = true;
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_name'] = $username;
    $_SESSION['auth_provider'] = 'google';

    echo json_encode([
        'status' => 'success',
        'message' => 'Google sign-in successful.',
        'redirect' => 'home.php',
        'user' => [
            'id' => $userId,
            'email' => $email,
            'name' => $username,
        ],
    ]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}
