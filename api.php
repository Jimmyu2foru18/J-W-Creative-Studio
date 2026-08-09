<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$baseDir = __DIR__;
$dataDir = $baseDir . '/data';

function ensureDataDir($dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
}

function loadJson($path, $default = []) {
    if (!file_exists($path)) {
        return $default;
    }
    $content = file_get_contents($path);
    $data = json_decode($content, true);
    return $data === null ? $default : $data;
}

function saveJson($path, $data) {
    $dir = dirname($path);
    ensureDataDir($dir);
    $fp = fopen($path, 'c+');
    if ($fp) {
        if (flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($data, JSON_PRETTY_PRINT));
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }
}

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
        if (file_exists($GLOBALS['baseDir'] . '/' . $relPath)) return '/' . $relPath;
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
    $dir = $GLOBALS['baseDir'] . '/' . $folderName;
    if (!is_dir($dir)) { @mkdir($dir, 0777, true); }

    $timestamp = time();
    $prefix = ($type === 'profile') ? 'profile' : (($type === 'cover') ? 'cover' : 'img');
    
    if ($type === 'profile' || $type === 'cover') {
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

$request_method = $_SERVER["REQUEST_METHOD"];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$api_path = $uri;
if (($pos = strpos($uri, '/api.php/')) !== false) {
    $api_path = substr($uri, $pos + 8);
} elseif (($pos = strpos($uri, '/api/')) !== false) {
    $api_path = substr($uri, $pos + 4);
} else {
    $api_path = preg_replace('/^\/api(\.php)?/', '', $api_path);
}

$path = explode('/', trim($api_path, '/'));
$endpoint = $path[0] ?? '';

$photographersPath = $dataDir . '/photographers.json';
$bookingsPath = $dataDir . '/bookings.json';

// Auth endpoints
if ($endpoint === 'auth' && ($path[1] ?? '') === 'login' && $request_method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';
    $isSignUp = $data['isSignUp'] ?? false;

    $photographers = loadJson($photographersPath, []);

    if ($isSignUp) {
        $name = $data['name'] ?? '';
        $instagram = $data['instagram'] ?? '';
        $facebook = $data['facebook'] ?? '';
        $linkedin = $data['linkedin'] ?? '';
        $role = 'photographer';
        $id = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $name)) . '-' . rand(100, 999);
        
        $newUser = [
            'id' => $id,
            'uid' => $id,
            'email' => $email,
            'password_hash' => $password,
            'role' => $role,
            'name' => $name,
            'instagram' => $instagram,
            'facebook' => $facebook,
            'linkedin' => $linkedin,
            'bio' => '',
            'pricing_rules' => '',
            'equipment' => '',
            'availability' => '',
            'profile_image' => '',
            'cover_image' => '',
            'reset_token' => null,
            'reset_token_expires' => null,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $photographers[] = $newUser;
        saveJson($photographersPath, $photographers);
        
        $base = getFolderBase($id);
        @mkdir($baseDir . '/' . $base . 'profile', 0777, true);
        @mkdir($baseDir . '/' . $base . 'portfilio', 0777, true);
        
        echo json_encode(["uid" => $id, "id" => $id, "email" => $email, "name" => $name, "role" => $role]);
    } else {
        $user = null;
        foreach ($photographers as $u) {
            if ($u['email'] === $email) {
                $user = $u;
                break;
            }
        }
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
    
    $photographers = loadJson($photographersPath, []);
    $user = null;
    foreach ($photographers as $u) {
        if ($u['email'] === $email) {
            $user = $u;
            break;
        }
    }
    
    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        foreach ($photographers as &$u) {
            if ($u['email'] === $email) {
                $u['reset_token'] = $token;
                $u['reset_token_expires'] = $expires;
                break;
            }
        }
        saveJson($photographersPath, $photographers);
        
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
    
    $photographers = loadJson($photographersPath, []);
    $user = null;
    foreach ($photographers as $u) {
        if ($u['reset_token'] === $token && $u['reset_token_expires'] > date('Y-m-d H:i:s')) {
            $user = $u;
            break;
        }
    }
    
    if ($user) {
        foreach ($photographers as &$u) {
            if ($u['id'] === $user['id']) {
                $u['password_hash'] = $newPassword;
                $u['reset_token'] = null;
                $u['reset_token_expires'] = null;
                break;
            }
        }
        saveJson($photographersPath, $photographers);
        echo json_encode(["success" => true, "message" => "Password updated successfully"]);
    } else {
        http_response_code(400); echo json_encode(["error" => "Invalid or expired token"]);
    }
}
elseif ($endpoint === 'auth' && ($path[1] ?? '') === 'change-password' && $request_method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $email = $data['email'] ?? '';
    $oldPassword = $data['oldPassword'] ?? '';
    $newPassword = $data['newPassword'] ?? '';
    
    $photographers = loadJson($photographersPath, []);
    $user = null;
    foreach ($photographers as $u) {
        if ($u['email'] === $email) {
            $user = $u;
            break;
        }
    }
    
    if ($user && $oldPassword === $user['password_hash']) {
        foreach ($photographers as &$u) {
            if ($u['id'] === $user['id']) {
                $u['password_hash'] = $newPassword;
                break;
            }
        }
        saveJson($photographersPath, $photographers);
        echo json_encode(["success" => true, "message" => "Password changed successfully"]);
    } else {
        http_response_code(401); echo json_encode(["error" => "Invalid old password"]);
    }
}
// Photographers endpoints
elseif ($endpoint === 'photographers' && $request_method === 'GET') {
    if (isset($path[1])) {
        $userId = $path[1];
        if (($path[2] ?? '') === 'portfolio') {
            $items = [];
            $base = getFolderBase($userId);
            $folder = $base . "portfilio";
            if (is_dir($baseDir . "/" . $folder)) {
                $files = scandir($baseDir . "/" . $folder);
                foreach ($files as $file) {
                    if (preg_match('/\.(png|jpe?g|gif|webp|avif)$/i', $file) && !strpos($file, 'profile') && !strpos($file, 'cover')) {
                        $url = "/" . $folder . "/" . $file;
                        $items[] = ["id" => "file-" . md5($file), "imageUrl" => $url];
                    }
                }
            }
            echo json_encode($items);
        } else {
            $photographers = loadJson($photographersPath, []);
            $u = null;
            foreach ($photographers as $p) {
                if ($p['id'] === $userId) {
                    $u = $p;
                    break;
                }
            }
            if ($u) {
                $u['profile_image'] = findValidImage($userId, 'profile') ?: ('/' . ltrim($u['profile_image'], '/'));
                $u['cover_image'] = findValidImage($userId, 'cover') ?: ('/' . ltrim($u['cover_image'], '/'));
            }
            echo json_encode($u ?: ["error" => "Not found"]);
        }
    } else {
        $photographers = loadJson($photographersPath, []);
        $users = [];
        foreach ($photographers as $u) {
            if ($u['role'] === 'photographer' || $u['role'] === 'admin') {
                $u['profile_image'] = findValidImage($u['id'], 'profile') ?: ('/' . ltrim($u['profile_image'], '/'));
                $u['cover_image'] = findValidImage($u['id'], 'cover') ?: ('/' . ltrim($u['cover_image'], '/'));
                $users[] = $u;
            }
        }
        echo json_encode($users);
    }
}
elseif ($endpoint === 'photographers' && isset($path[1]) && $request_method === 'POST') {
    $id = $path[1];
    $data = json_decode(file_get_contents("php://input"), true);
    
    $profileImage = saveBase64Image($data['profileImage'] ?? '', $id, 'profile');
    $coverImage = saveBase64Image($data['coverImage'] ?? '', $id, 'cover');
    
    $photographers = loadJson($photographersPath, []);
    foreach ($photographers as &$u) {
        if ($u['id'] === $id) {
            $u['name'] = $data['name'] ?? $u['name'];
            $u['bio'] = $data['bio'] ?? $u['bio'];
            $u['pricing_rules'] = $data['pricingRules'] ?? $u['pricing_rules'];
            $u['instagram'] = $data['instagram'] ?? $u['instagram'];
            $u['facebook'] = $data['facebook'] ?? $u['facebook'];
            $u['linkedin'] = $data['linkedin'] ?? $u['linkedin'];
            $u['availability'] = $data['availability'] ?? $u['availability'];
            $u['equipment'] = $data['equipment'] ?? $u['equipment'];
            if ($profileImage) $u['profile_image'] = $profileImage;
            if ($coverImage) $u['cover_image'] = $coverImage;
            break;
        }
    }
    saveJson($photographersPath, $photographers);
    echo json_encode(["success" => true]);
}
elseif ($endpoint === 'portfolio' && isset($path[1]) && $request_method === 'POST') {
    $id = $path[1];
    $data = json_decode(file_get_contents("php://input"), true);
    $imageUrl = saveBase64Image($data['image'] ?? '', $id, 'portfolio');
    echo json_encode(["success" => true, "imageUrl" => $imageUrl]);
}
elseif ($endpoint === 'portfolio' && isset($path[1]) && $request_method === 'DELETE') {
    $portfolioId = $path[1];
    $hash = preg_replace('/^file-/', '', $portfolioId);
    $found = false;
    
    $photographers = loadJson($photographersPath, []);
    foreach ($photographers as $u) {
        $base = getFolderBase($u['id']);
        $folder = $base . "portfilio";
        $dir = $baseDir . "/" . $folder;
        if (is_dir($dir)) {
            $files = scandir($dir);
            foreach ($files as $file) {
                if (preg_match('/\.(png|jpe?g|gif|webp|avif)$/i', $file) && !strpos($file, 'profile') && !strpos($file, 'cover')) {
                    $fileHash = md5_file($dir . '/' . $file);
                    if ($fileHash === $hash) {
                        @unlink($dir . '/' . $file);
                        $found = true;
                        break 2;
                    }
                }
            }
        }
    }
    
    if ($found) {
        echo json_encode(["success" => true]);
    } else {
        http_response_code(404); echo json_encode(["error" => "Item not found"]);
    }
}
// Bookings endpoint
elseif ($endpoint === 'bookings' && $request_method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $bookings = loadJson($bookingsPath, []);
    $booking = [
        'id' => count($bookings) > 0 ? max(array_column($bookings, 'id')) + 1 : 1,
        'client_name' => $data['client_name'] ?? '',
        'client_email' => $data['client_email'] ?? '',
        'photographer_id' => $data['photographer_id'] ?? '',
        'budget_offer' => $data['budget_offer'] ?? '',
        'message' => $data['message'] ?? '',
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s')
    ];
    $bookings[] = $booking;
    saveJson($bookingsPath, $bookings);
    echo json_encode(["success" => true, "id" => $booking['id']]);
}
// Admin endpoints
elseif ($endpoint === 'admin' && ($path[1] ?? '') === 'users' && $request_method === 'GET') {
    $photographers = loadJson($photographersPath, []);
    $users = [];
    foreach ($photographers as $u) {
        $users[] = [
            'id' => $u['id'],
            'name' => $u['name'],
            'email' => $u['email'],
            'role' => $u['role']
        ];
    }
    echo json_encode($users);
}
elseif ($endpoint === 'admin' && ($path[1] ?? '') === 'users' && isset($path[2]) && $request_method === 'DELETE') {
    $userId = $path[2];
    $photographers = loadJson($photographersPath, []);
    $newPhotographers = [];
    foreach ($photographers as $u) {
        if ($u['id'] !== $userId) {
            $newPhotographers[] = $u;
        }
    }
    saveJson($photographersPath, $newPhotographers);
    echo json_encode(["success" => true]);
}
elseif ($endpoint === 'admin' && ($path[1] ?? '') === 'users' && ($path[2] ?? '') === 'bulk-update' && $request_method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $users = $data['users'] ?? [];
    if (!is_array($users)) { http_response_code(400); echo json_encode(["error" => "Invalid users array"]); exit; }
    
    $photographers = loadJson($photographersPath, []);
    foreach ($photographers as &$u) {
        foreach ($users as $update) {
            if ($u['id'] === $update['id']) {
                $u['role'] = $update['role'];
                break;
            }
        }
    }
    saveJson($photographersPath, $photographers);
    echo json_encode(["success" => true]);
}
else {
    http_response_code(404); echo json_encode(["error" => "Not found"]);
}
?>
