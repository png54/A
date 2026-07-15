<?php
require_once 'config.php';
require_once 'functions.php';

if (is_logged_in()) {
    header("Location: admin.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_btn'])) {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = "Échec de la validation de sécurité. Veuillez actualiser la page.";
    } else {
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = "Veuillez renseigner tous les champs.";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['LAST_ACTIVITY'] = time();
                    header("Location: admin.php");
                    exit;
                } else {
                    $error = "Identifiants incorrects.";
                }
            } catch (Exception $e) {
                $error = "Erreur système.";
                error_log("Login error: " . $e->getMessage());
            }
        }
    }
}

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Connexion - For you by mb</title>
    <link rel="icon" href="1.png" type="image/png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400..800;1,400..800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="bg-[#FDF8FA] min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-xl p-6 md:p-8 w-full max-w-md">
        <div class="text-center mb-6">
            <img src="1.png" alt="For you by mb" class="w-20 h-20 md:w-24 md:h-24 mx-auto rounded-2xl object-contain border-2 border-[#E7B8CF]/30 shadow-lg mb-4" onerror="this.style.display='none'">
            <h1 class="font-serif italic font-extrabold text-2xl md:text-3xl text-[#5A1930]">For you by mb</h1>
            <p class="text-slate-500 text-xs md:text-sm mt-2">Espace d'administration</p>
        </div>
        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-100 text-red-700 p-3 rounded-xl text-xs md:text-sm mb-4 text-center"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <div><label class="block text-xs font-bold text-slate-500 mb-1">Email</label><input type="email" name="email" required class="w-full px-4 py-3 border rounded-xl text-sm focus:outline-none focus:border-[#5A1930]" placeholder="admin@foryoubymb.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"></div>
            <div><label class="block text-xs font-bold text-slate-500 mb-1">Mot de passe</label><input type="password" name="password" required class="w-full px-4 py-3 border rounded-xl text-sm focus:outline-none focus:border-[#5A1930]" placeholder="••••••••"></div>
            <button type="submit" name="login_btn" class="w-full bg-[#5A1930] text-white py-3 rounded-xl font-semibold hover:bg-[#431223] transition text-sm">Se connecter</button>
        </form>
        <p class="text-center text-xs text-slate-400 mt-4"><a href="index.php" class="hover:text-[#5A1930]">← Retour à la boutique</a></p>
    </div>
</body>
</html>