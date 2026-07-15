<?php
$DB_HOST = 'sql202.infinityfree.com';
$DB_NAME = 'if0_42324672_foryoubymb';
$DB_USER = 'if0_42324672';
$DB_PASS = 'i29012001M';

try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $hash = password_hash('29012001', PASSWORD_BCRYPT);
    $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?")->execute([$hash, 'admin@foryoubymb.com']);
    
    echo '<div style="font-family:sans-serif;max-width:400px;margin:50px auto;padding:30px;border-radius:16px;background:#f0fff0;text-align:center;direction:rtl;">';
    echo '<h1 style="color:green;">✅ تم الإصلاح!</h1>';
    echo '<p><b>Email:</b> admin@foryoubymb.com</p>';
    echo '<p><b>Mot de passe:</b> 29012001</p>';
    echo '<a href="login.php" style="display:inline-block;background:green;color:white;padding:12px 30px;border-radius:8px;text-decoration:none;font-weight:bold;margin-top:10px;">تسجيل الدخول</a>';
    echo '<p style="color:red;margin-top:20px;">⚠️ احذف هذا الملف بعد الاستخدام!</p>';
    echo '</div>';
} catch (Exception $e) {
    echo '<h1 style="color:red;text-align:center;">❌ ' . $e->getMessage() . '</h1>';
}
?>