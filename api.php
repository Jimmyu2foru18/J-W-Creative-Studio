<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$host = 'sql206.infinityfree.com';
$user = 'if0_41764748';
$pass = 'qqO5VAfzeSXcD0';
$db   = 'if0_41764748_jwcreativestudio';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}

$request_method = $_SERVER["REQUEST_METHOD"];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// More robust way to find the API endpoint regardless of subdirectory
$api_path = $uri;
if (($pos = strpos($uri, '/api.php/')) !== false) {
    $api_path = substr($uri, $pos + 8);
} elseif (($pos = strpos($uri, '/api/')) !== false) {
    $api_path = substr($uri, $pos + 4);
} else {
    // Fallback for cases where it might be just /api.php or /api
    $api_path = preg_replace('/^\/api(\.php)?/', '', $api_path);
}

$path = explode('/', trim($api_path, '/'));
$endpoint = $path[0] ?? '';

function getFolderBase($userId) {
    if ($userId === 'james-mcguigan') return 'james';
    if ($userId === 'waleed-bhatti') return 'waleed';
    return $userId;
}

function findValidImage($userId, $prefix = 'profile') {
    $base = getFolderBase($userId);
    $folder = ($prefix === 'profile') ? $base . 'profile' : $base . 'portfilio';
    $extensions = ['png', 'jpg', 'jpeg', 'webp', 'avif'];
    foreach ($extensions as $ext) {
        $filename = ($prefix === 'profile') ? 'profile.' . $ext : 'cover.' . $ext;
        $relPath = $folder . '/' . $filename;
        if (file_exists(__DIR__ . '/' . $relPath)) return '/' . $relPath;
    }
    if ($prefix === 'cover') return findValidImage($userId, 'profile');
    return null;
}

function saveBase64Image($base64String, $userId, $type = 'profile') {
    if (strpos($base64String, 'data:image') === false) {
        return '/' . ltrim($base64String, '/');
    }
    $extension = 'jpg';
    if (strpos($base64String, 'data:image/png') !== false) $extension = 'png';
    elseif (strpos($base64String, 'data:image/webp') !== false) $extension = 'webp';

    $base = getFolderBase($userId);
    $folderName = ($type === 'profile') ? $base . 'profile' : $base . 'portfilio';
    $dir = __DIR__ . '/' . $folderName;
    if (!is_dir($dir)) { @mkdir($dir, 0777, true); }

    // Use unique filename to bypass cache
    $timestamp = time();
    $prefix = ($type === 'profile') ? 'profile' : (($type === 'cover') ? 'cover' : 'img');
    
    if ($type === 'profile' || $type === 'cover') {
        // Delete all old profile/cover images to clean up
        $extensions = ['png', 'jpg', 'jpeg', 'webp', 'avif'];
        foreach ($extensions as $ext) {
            $files = glob($dir . '/' . $prefix . '.*');
            foreach ($files as $file) { @unlink($file); }
        }
        $fileName = $prefix . '.' . $extension;
    } else {
        $fileName = 'img_' . $timestamp . '_' . rand(100, 999) . '.' . $extension;
    }
    
    $filePath = $dir . '/' . $fileName;

    $data = explode(',', $base64String);
    if (isset($data[1])) {
        file_put_contents($filePath, base64_decode($data[1]));
        return '/' . $folderName . '/' . $fileName;
    }
    return '/' . ltrim($base64String, '/');
}

if ($endpoint === 'auth' && ($path[1] ?? '') === 'login' && $request_method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';
    $isSignUp = $data['isSignUp'] ?? false;

    if ($isSignUp) {
        $name = $data['name'] ?? '';
        $instagram = $data['instagram'] ?? '';
        $facebook = $data['facebook'] ?? '';
        $linkedin = $data['linkedin'] ?? '';
        $role = 'photographer';
        $id = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $name)) . '-' . rand(100, 999);
        $stmt = $conn->prepare("INSERT INTO users (id, email, password_hash, role, name, instagram, facebook, linkedin) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssss", $id, $email, $password, $role, $name, $instagram, $facebook, $linkedin);
        if ($stmt->execute()) {
            @mkdir(__DIR__ . '/' . $id . 'profile', 0777, true);
            @mkdir(__DIR__ . '/' . $id . 'portfilio', 0777, true);
            echo json_encode(["uid" => $id, "id" => $id, "email" => $email, "name" => $name, "role" => $role]);
        } else {
            http_response_code(400); echo json_encode(["error" => $conn->error]);
        }
    } else {
        $stmt = $conn->prepare("SELECT id, id AS uid, email, password_hash, role, name FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if ($user && $password === $user['password_hash']) {
            unset($user['password_hash']);
            echo json_encode($user);
        } else {
            http_response_code(401); echo json_encode(["error" => "Invalid credentials"]);
        }
    }
}
elseif ($endpoint === 'auth' && ($path[1] ?? '') === 'forgot-password' && $request_method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $email = $data['email'] ?? '';
    
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE email = ?");
        $stmt->bind_param("sss", $token, $expires, $email);
        $stmt->execute();
        
        $resetLink = "https://" . $_SERVER['HTTP_HOST'] . "/reset-password?token=" . $token;
        $subject = "Password Reset - J&W Creative Studio";
        $message = "Click the link below to reset your password:\n\n" . $resetLink . "\n\nThis link will expire in 1 hour.";
        $headers = "From: noreply@" . $_SERVER['HTTP_HOST'];
        
        @mail($email, $subject, $message, $headers);
    }
    echo json_encode(["message" => "If an account exists with that email, a reset link has been sent."]);
}
elseif ($endpoint === 'auth' && ($path[1] ?? '') === 'reset-password' && $request_method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $token = $data['token'] ?? '';
    $newPassword = $data['password'] ?? '';
    
    $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expires > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if ($user) {
        $stmt = $conn->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
        $stmt->bind_param("ss", $newPassword, $user['id']);
        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Password updated successfully"]);
        } else {
            http_response_code(500); echo json_encode(["error" => "Failed to update password"]);
        }
    } else {
        http_response_code(400); echo json_encode(["error" => "Invalid or expired token"]);
    }
}
elseif ($endpoint === 'auth' && ($path[1] ?? '') === 'change-password' && $request_method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $email = $data['email'] ?? '';
    $oldPassword = $data['oldPassword'] ?? '';
    $newPassword = $data['newPassword'] ?? '';
    
    $stmt = $conn->prepare("SELECT id, password_hash FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if ($user && $oldPassword === $user['password_hash']) {
        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->bind_param("ss", $newPassword, $user['id']);
        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Password changed successfully"]);
        } else {
            http_response_code(500); echo json_encode(["error" => "Failed to change password"]);
        }
    } else {
        http_response_code(401); echo json_encode(["error" => "Invalid old password"]);
    }
}
elseif ($endpoint === 'photographers' && $request_method === 'GET') {
    if (isset($path[1])) {
        $userId = $path[1];
        if (($path[2] ?? '') === 'portfolio') {
            $stmt = $conn->prepare("SELECT id, image_url AS imageUrl FROM portfolios WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->bind_param("s", $userId); $stmt->execute();
            $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $base = getFolderBase($userId);
            $folder = $base . "portfilio";
            if (is_dir(__DIR__ . "/" . $folder)) {
                $files = scandir(__DIR__ . "/" . $folder);
                foreach ($files as $file) {
                    if (preg_match('/\.(png|jpe?g|gif|webp|avif)$/i', $file) && !strpos($file, 'profile') && !strpos($file, 'cover')) {
                        $url = "/" . $folder . "/" . $file;
                        $found = false;
                        foreach($items as $it) { 
                            $itUrl = '/' . ltrim($it['imageUrl'], '/');
                            if($itUrl === $url) $found = true; 
                        }
                        if(!$found) $items[] = ["id" => "file-" . md5($file), "imageUrl" => $url];
                    }
                }
            }
            // Ensure all database-stored paths are also absolute
            foreach($items as &$item) { $item['imageUrl'] = '/' . ltrim($item['imageUrl'], '/'); }
            echo json_encode($items);
        } else {
            $stmt = $conn->prepare("SELECT id, id AS uid, email, name, bio, pricing_rules, instagram, facebook, linkedin, availability, equipment, profile_image, cover_image, role FROM users WHERE id = ?");
            $stmt->bind_param("s", $userId); $stmt->execute();
            $u = $stmt->get_result()->fetch_assoc();
            if ($u) {
                $u['profile_image'] = findValidImage($userId, 'profile') ?: ('/' . ltrim($u['profile_image'], '/'));
                $u['cover_image'] = findValidImage($userId, 'cover') ?: ('/' . ltrim($u['cover_image'], '/'));
            }
            echo json_encode($u ?: ["error" => "Not found"]);
        }
    } else {
        $result = $conn->query("SELECT id, id AS uid, email, name, bio, pricing_rules, instagram, facebook, linkedin, availability, equipment, profile_image, cover_image, role FROM users WHERE role IN ('photographer', 'admin')");
        $users = $result->fetch_all(MYSQLI_ASSOC);
        foreach ($users as &$u) {
            $u['profile_image'] = findValidImage($u['id'], 'profile') ?: ('/' . ltrim($u['profile_image'], '/'));
            $u['cover_image'] = findValidImage($u['id'], 'cover') ?: ('/' . ltrim($u['cover_image'], '/'));
        }
        echo json_encode($users);
    }
}
elseif ($endpoint === 'photographers' && isset($path[1]) && $request_method === 'POST') {
    $id = $path[1]; $data = json_decode(file_get_contents("php://input"), true);
    $profileImage = saveBase64Image($data['profileImage'] ?? '', $id, 'profile');
    $coverImage = saveBase64Image($data['coverImage'] ?? '', $id, 'cover');
    $stmt = $conn->prepare("UPDATE users SET name = ?, bio = ?, pricing_rules = ?, instagram = ?, facebook = ?, linkedin = ?, availability = ?, equipment = ?, profile_image = ?, cover_image = ? WHERE id = ?");
    $stmt->bind_param("sssssssssss", $data['name'], $data['bio'], $data['pricingRules'], $data['instagram'], $data['facebook'], $data['linkedin'], $data['availability'], $data['equipment'], $profileImage, $coverImage, $id);
    if ($stmt->execute()) echo json_encode(["success" => true]);
    else { http_response_code(500); echo json_encode(["error" => $conn->error]); }
}
elseif ($endpoint === 'portfolio' && isset($path[1]) && $request_method === 'POST') {
    $id = $path[1]; $data = json_decode(file_get_contents("php://input"), true);
    $imageUrl = saveBase64Image($data['image'] ?? '', $id, 'portfolio');
    $stmt = $conn->prepare("INSERT INTO portfolios (user_id, image_url) VALUES (?, ?)");
    $stmt->bind_param("ss", $id, $imageUrl);
    if ($stmt->execute()) echo json_encode(["success" => true, "imageUrl" => $imageUrl]);
    else { http_response_code(500); echo json_encode(["error" => $conn->error]); }
}
elseif ($endpoint === 'portfolio' && isset($path[1]) && $request_method === 'DELETE') {
    $portfolioId = $path[1];
    $stmt = $conn->prepare("SELECT image_url FROM portfolios WHERE id = ?");
    $stmt->bind_param("s", $portfolioId);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    if ($item) {
        $filePath = __DIR__ . '/' . ltrim($item['image_url'], '/');
        $stmt = $conn->prepare("DELETE FROM portfolios WHERE id = ?");
        $stmt->bind_param("s", $portfolioId);
        if ($stmt->execute()) {
            if (file_exists($filePath) && is_file($filePath)) @unlink($filePath);
            echo json_encode(["success" => true]);
        } else {
            http_response_code(500); echo json_encode(["error" => "Failed to delete from database"]);
        }
    } else {
        http_response_code(404); echo json_encode(["error" => "Item not found"]);
    }
}
elseif ($endpoint === 'bookings' && $request_method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $stmt = $conn->prepare("INSERT INTO bookings (client_name, client_email, photographer_id, budget_offer, message) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $data['client_name'], $data['client_email'], $data['photographer_id'], $data['budget_offer'], $data['message']);
    $stmt->execute();
    echo json_encode(["success" => true, "id" => $conn->insert_id]);
}
elseif ($endpoint === 'admin' && ($path[1] ?? '') === 'users' && $request_method === 'GET') {
    $result = $conn->query("SELECT id, name, email, role FROM users ORDER BY created_at DESC");
    $users = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode($users);
}
elseif ($endpoint === 'admin' && ($path[1] ?? '') === 'users' && isset($path[2]) && $request_method === 'DELETE') {
    $userId = $path[2];
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("s", $userId);
    if ($stmt->execute()) echo json_encode(["success" => true]);
    else { http_response_code(500); echo json_encode(["error" => $conn->error]); }
}
elseif ($endpoint === 'admin' && ($path[1] ?? '') === 'users' && ($path[2] ?? '') === 'bulk-update' && $request_method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $users = $data['users'] ?? [];
    if (!is_array($users)) { http_response_code(400); echo json_encode(["error" => "Invalid users array"]); exit; }
    
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        foreach ($users as $u) {
            $stmt->bind_param("ss", $u['role'], $u['id']);
            $stmt->execute();
        }
        $conn->commit();
        echo json_encode(["success" => true]);
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500); echo json_encode(["error" => $e->getMessage()]);
    }
}
else {
    http_response_code(404); echo json_encode(["error" => "Not found"]);
}
$conn->close();
?>