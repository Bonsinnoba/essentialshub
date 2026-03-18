require_once 'security.php';

// Clear the HttpOnly session cookie
clearSession();

header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
exit;
