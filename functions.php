<?php
if (basename($_SERVER['SCRIPT_FILENAME']) === 'functions.php') {
    header("HTTP/1.1 403 Forbidden");
    exit("Access denied");
}

if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        $length = strlen($needle);
        if (!$length) return true;
        return substr($haystack, -$length) === $needle;
    }
}

function sanitize($data) {
    if (is_array($data)) return array_map('sanitize', $data);
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    if (function_exists('iconv')) $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return empty($text) ? 'produit-' . rand(100, 999) : $text;
}

function get_unique_slug($pdo, $text, $table, $column = 'slug', $exclude_id = null) {
    $slug = slugify($text);
    $original_slug = $slug;
    $counter = 1;
    while (true) {
        $sql = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?";
        $params = [$slug];
        if ($exclude_id) { $sql .= " AND id != ?"; $params[] = $exclude_id; }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetchColumn() == 0) break;
        $slug = $original_slug . '-' . $counter;
        $counter++;
    }
    return $slug;
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

function require_role($allowed_roles) {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit;
    }
    $has_role = in_array($_SESSION['user_role'], (array)$allowed_roles);
    if (!$has_role) {
        header("Location: admin.php?error=unauthorized");
        exit;
    }
}

function upload_image($file_input, $upload_dir = 'uploads/') {
    if (!isset($_FILES[$file_input]) || $_FILES[$file_input]['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Aucune image sélectionnée.'];
    }
    $file = $_FILES[$file_input];
    $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'error' => 'Image trop volumineuse (max 5 MB).'];
    }
    
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    } else {
        $img_info = @getimagesize($file['tmp_name']);
        $mime = $img_info ? $img_info['mime'] : '';
    }
    if (!in_array($mime, $allowed_types)) {
        return ['success' => false, 'error' => 'Type MIME invalide.'];
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_extensions)) {
        return ['success' => false, 'error' => 'Extension invalide.'];
    }
    
    if (!is_dir($upload_dir)) @mkdir($upload_dir, 0755, true);
    
    $new_name = bin2hex(random_bytes(16)) . '.' . $ext;
    $target = $upload_dir . $new_name;
    
    if (move_uploaded_file($file['tmp_name'], $target)) {
        return ['success' => true, 'filepath' => $target];
    }
    return ['success' => false, 'error' => 'Échec du téléchargement.'];
}

function log_activity($pdo, $user_id, $action, $details = '') {
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $details, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Exception $e) {}
}

function format_price($amount) {
    return number_format($amount, 0, ',', '.') . ' DA';
}