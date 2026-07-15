<?php
require_once 'config.php';
require_once 'functions.php';
if (!is_logged_in()) { header("Location: login.php"); exit; }
$user_id = $_SESSION['user_id']; $user_role = $_SESSION['user_role']; $user_name = $_SESSION['user_name'];
$tab = $_GET['tab'] ?? 'dashboard'; $msg = '';

if (isset($_POST['delete_product']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $pdo->prepare("DELETE FROM product_images WHERE product_id=?")->execute([intval($_POST['id'])]);
    $pdo->prepare("DELETE FROM products WHERE id=?")->execute([intval($_POST['id'])]);
    $msg = '<div class="bg-green-100 text-green-800 p-3 rounded-xl mb-4 text-sm">✅ Produit supprimé</div>';
}
if (isset($_POST['delete_category']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([intval($_POST['id'])]);
    $msg = '<div class="bg-green-100 text-green-800 p-3 rounded-xl mb-4 text-sm">✅ Catégorie supprimée</div>';
}
if (isset($_POST['delete_order']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $pdo->prepare("DELETE FROM order_items WHERE order_id=?")->execute([intval($_POST['id'])]);
    $pdo->prepare("DELETE FROM orders WHERE id=?")->execute([intval($_POST['id'])]);
    $msg = '<div class="bg-green-100 text-green-800 p-3 rounded-xl mb-4 text-sm">✅ Commande supprimée</div>';
}
if (isset($_POST['delete_subcategory']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $pdo->prepare("DELETE FROM subcategories WHERE id=?")->execute([intval($_POST['id'])]);
    $msg = '<div class="bg-green-100 text-green-800 p-3 rounded-xl mb-4 text-sm">✅ Sous-catégorie supprimée</div>';
}

$csrf_token = generate_csrf_token();
$total_products = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$total_orders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$pending_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();
$total_revenue = $pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status IN ('confirmed','shipped','delivered')")->fetchColumn();
$products = $pdo->query("SELECT p.*, c.name as cat_name FROM products p JOIN categories c ON p.category_id = c.id ORDER BY p.created_at DESC")->fetchAll();
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$subcategories = $pdo->query("SELECT s.*, c.name as cat_name FROM subcategories s JOIN categories c ON s.category_id = c.id ORDER BY c.name, s.name")->fetchAll();
$orders = $pdo->query("SELECT * FROM orders ORDER BY id DESC LIMIT 50")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin - For you by mb</title>
    <link rel="icon" href="1.png" type="image/png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>*{box-sizing:border-box}body{overflow-x:hidden;max-width:100vw;font-family:'Plus Jakarta Sans',sans-serif}@media(max-width:768px){#sidebar{transform:translateX(-100%);position:fixed;top:0;left:0;z-index:50;transition:transform .3s ease;height:100vh}#sidebar.open{transform:translateX(0)}.table-wrap{display:block;width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch}.table-wrap table{min-width:550px}}</style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen flex flex-col md:flex-row">

<div class="md:hidden bg-[#5A1930] text-white p-3 flex items-center justify-between sticky top-0 z-30 w-full">
    <button onclick="document.getElementById('sidebar').classList.toggle('open');document.getElementById('overlay').classList.toggle('hidden')" class="p-2 hover:bg-white/10 rounded-lg"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
    <div class="flex items-center gap-2"><img src="1.png" class="w-7 h-7 rounded-lg object-contain border border-white/20"><span class="font-bold text-sm">Admin MB</span></div>
    <a href="logout.php" class="text-white/80 hover:text-white text-xs">🚪</a>
</div>
<div id="overlay" class="fixed inset-0 bg-black/50 z-40 hidden md:hidden" onclick="document.getElementById('sidebar').classList.remove('open');this.classList.add('hidden')"></div>

<aside id="sidebar" class="w-64 bg-[#5A1930] text-white flex flex-col shrink-0 min-h-screen">
    <div class="p-5 text-center border-b border-white/10 hidden md:block"><img src="1.png" class="w-14 h-14 mx-auto rounded-xl object-contain border-2 border-white/20 mb-2"><h1 class="font-bold text-lg">For you by mb</h1></div>
    <div class="p-4 md:hidden text-center border-b border-white/10"><img src="1.png" class="w-10 h-10 mx-auto rounded-lg object-contain border border-white/20 mb-1"><p class="font-bold text-sm">For you by mb</p></div>
    <nav class="flex flex-col gap-1 p-3 flex-1">
        <a href="?tab=dashboard" onclick="closeSide()" class="flex items-center gap-2 py-3 px-4 rounded-xl text-sm <?= $tab=='dashboard'?'bg-white/20':'' ?> hover:bg-white/10">📊 Dashboard</a>
        <a href="?tab=products" onclick="closeSide()" class="flex items-center gap-2 py-3 px-4 rounded-xl text-sm <?= $tab=='products'?'bg-white/20':'' ?> hover:bg-white/10">📦 Produits</a>
        <a href="?tab=categories" onclick="closeSide()" class="flex items-center gap-2 py-3 px-4 rounded-xl text-sm <?= $tab=='categories'?'bg-white/20':'' ?> hover:bg-white/10">🏷️ Catégories</a>
        <a href="?tab=subcategories" onclick="closeSide()" class="flex items-center gap-2 py-3 px-4 rounded-xl text-sm <?= $tab=='subcategories'?'bg-white/20':'' ?> hover:bg-white/10">📁 Sous-catégories</a>
        <a href="?tab=orders" onclick="closeSide()" class="flex items-center gap-2 py-3 px-4 rounded-xl text-sm <?= $tab=='orders'?'bg-white/20':'' ?> hover:bg-white/10">📋 Commandes</a>
    </nav>
    <div class="p-4 border-t border-white/10 text-xs text-white/60"><p class="font-semibold text-white/80"><?= htmlspecialchars($user_name) ?></p><p class="text-[10px]"><?= $user_role ?></p><a href="index.php" class="text-white/70 hover:text-white block mt-2">🌐 Voir le site</a><a href="logout.php" class="text-white/70 hover:text-white block">🚪 Déconnexion</a></div>
</aside>

<main class="flex-1 p-4 md:p-8 w-full overflow-x-hidden">
    <?= $msg ?>
    <?php if($tab=='dashboard'): ?>
        <h2 class="text-xl md:text-2xl font-bold text-slate-800 mb-5 md:mb-6">📊 Tableau de Bord</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-4">
            <div class="bg-white p-4 md:p-5 rounded-2xl shadow-sm"><p class="text-[11px] md:text-sm text-slate-500">Produits</p><p class="text-2xl md:text-3xl font-bold text-[#5A1930] mt-1"><?= $total_products ?></p></div>
            <div class="bg-white p-4 md:p-5 rounded-2xl shadow-sm"><p class="text-[11px] md:text-sm text-slate-500">Commandes</p><p class="text-2xl md:text-3xl font-bold text-[#5A1930] mt-1"><?= $total_orders ?></p></div>
            <div class="bg-white p-4 md:p-5 rounded-2xl shadow-sm"><p class="text-[11px] md:text-sm text-slate-500">En attente</p><p class="text-2xl md:text-3xl font-bold text-orange-600 mt-1"><?= $pending_orders ?></p></div>
            <div class="bg-white p-4 md:p-5 rounded-2xl shadow-sm"><p class="text-[11px] md:text-sm text-slate-500">Revenu confirmé</p><p class="text-xl md:text-3xl font-bold text-green-700 mt-1 whitespace-nowrap"><?= number_format($total_revenue,0,',','.') ?> DA</p></div>
        </div>

    <?php elseif($tab=='products'): ?>
        <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-3 mb-5 md:mb-6"><h2 class="text-xl md:text-2xl font-bold">📦 Produits (<?= count($products) ?>)</h2><button onclick="openProductModal()" class="bg-[#5A1930] text-white px-5 py-2.5 md:py-3 rounded-xl text-sm font-semibold hover:bg-[#431223] w-full sm:w-auto text-center">+ Ajouter</button></div>
        <div class="bg-white rounded-2xl shadow-sm table-wrap"><table class="w-full text-left"><thead class="bg-slate-50 border-b"><tr><th class="p-3 text-[11px] uppercase">Image</th><th class="p-3 text-[11px] uppercase">Nom</th><th class="p-3 text-[11px] uppercase">Catégorie</th><th class="p-3 text-[11px] uppercase">Prix</th><th class="p-3 text-[11px] uppercase">Ancien prix</th><th class="p-3 text-[11px] uppercase">Statut</th><th class="p-3 text-[11px] uppercase">Actions</th></tr></thead><tbody>
            <?php foreach($products as $p): ?>
            <tr class="border-b hover:bg-slate-50"><td class="p-3"><img src="<?= htmlspecialchars($p['image']) ?>" class="w-10 h-10 md:w-12 md:h-12 rounded-lg object-cover"></td><td class="p-3 font-semibold text-sm"><?= htmlspecialchars($p['name']) ?></td><td class="p-3 text-xs"><?= htmlspecialchars($p['cat_name']) ?></td><td class="p-3 font-bold text-[#5A1930] text-sm whitespace-nowrap"><?= format_price($p['price']) ?></td><td class="p-3 text-sm"><?= !empty($p['old_price']) ? format_price($p['old_price']) : '-' ?></td><td class="p-3"><span class="px-2 py-1 rounded-full text-[10px] font-semibold <?= $p['status']=='published'?'bg-green-100 text-green-700':($p['status']=='pending'?'bg-yellow-100 text-yellow-700':'bg-slate-100') ?>"><?= $p['status'] ?></span></td><td class="p-3"><button onclick='editProduct(<?= json_encode($p) ?>)' class="text-blue-600 text-sm mr-2">✏️</button><form method="POST" class="inline" onsubmit="return confirm('Supprimer ?')"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button name="delete_product" class="text-red-500 text-sm">🗑️</button></form></td></tr>
            <?php endforeach; ?>
        </tbody></table></div>

    <?php elseif($tab=='categories'): ?>
        <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-3 mb-5 md:mb-6"><h2 class="text-xl md:text-2xl font-bold">🏷️ Catégories (<?= count($categories) ?>)</h2><button onclick="openCategoryModal()" class="bg-[#5A1930] text-white px-5 py-2.5 md:py-3 rounded-xl text-sm font-semibold hover:bg-[#431223] w-full sm:w-auto text-center">+ Ajouter</button></div>
        <div class="bg-white rounded-2xl shadow-sm"><table class="w-full"><thead class="bg-slate-50 border-b"><tr><th class="p-3 text-[11px] uppercase">Nom</th><th class="p-3 text-[11px] uppercase">Slug</th><th class="p-3 text-[11px] uppercase">Actions</th></tr></thead><tbody>
            <?php foreach($categories as $c): ?><tr class="border-b"><td class="p-3 font-semibold text-sm"><?= htmlspecialchars($c['name']) ?></td><td class="p-3 text-xs"><?= htmlspecialchars($c['slug']) ?></td><td class="p-3"><button onclick='editCategory(<?= json_encode($c) ?>)' class="text-blue-600 text-sm mr-2">✏️</button><form method="POST" class="inline" onsubmit="return confirm('Supprimer ?')"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button name="delete_category" class="text-red-500 text-sm">🗑️</button></form></td></tr><?php endforeach; ?>
        </tbody></table></div>

    <?php elseif($tab=='subcategories'): ?>
        <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-3 mb-5 md:mb-6">
            <h2 class="text-xl md:text-2xl font-bold">📁 Sous-catégories (<?= count($subcategories) ?>)</h2>
            <button onclick="openSubcategoryModal()" class="bg-[#5A1930] text-white px-5 py-2.5 md:py-3 rounded-xl text-sm font-semibold hover:bg-[#431223] w-full sm:w-auto text-center">+ Ajouter</button>
        </div>
        <div class="bg-white rounded-2xl shadow-sm">
            <table class="w-full">
                <thead class="bg-slate-50 border-b">
                    <tr>
                        <th class="p-3 text-[11px] uppercase">Nom</th>
                        <th class="p-3 text-[11px] uppercase">Catégorie</th>
                        <th class="p-3 text-[11px] uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($subcategories as $sc): ?>
                    <tr class="border-b">
                        <td class="p-3 font-semibold text-sm"><?= htmlspecialchars($sc['name']) ?></td>
                        <td class="p-3 text-xs"><?= htmlspecialchars($sc['cat_name']) ?></td>
                        <td class="p-3">
                            <form method="POST" class="inline" onsubmit="return confirm('Supprimer cette sous-catégorie ?')">
                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                <input type="hidden" name="id" value="<?= $sc['id'] ?>">
                                <button name="delete_subcategory" class="text-red-500 text-sm">🗑️</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php elseif($tab=='orders'): ?>
        <h2 class="text-xl md:text-2xl font-bold mb-5 md:mb-6">📋 Commandes (<?= count($orders) ?>)</h2>
        <div class="bg-white rounded-2xl shadow-sm table-wrap">
            <table class="w-full text-left">
                <thead class="bg-slate-50 border-b">
                    <tr>
                        <th class="p-3 text-[11px] uppercase">#</th>
                        <th class="p-3 text-[11px] uppercase">Client</th>
                        <th class="p-3 text-[11px] uppercase">Tél</th>
                        <th class="p-3 text-[11px] uppercase">Wilaya</th>
                        <th class="p-3 text-[11px] uppercase">Commune</th>
                        <th class="p-3 text-[11px] uppercase">Adresse</th>
                        <th class="p-3 text-[11px] uppercase">Total</th>
                        <th class="p-3 text-[11px] uppercase">Statut</th>
                        <th class="p-3 text-[11px] uppercase">Date</th>
                        <th class="p-3 text-[11px] uppercase">Détails</th>
                        <th class="p-3 text-[11px] uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $sc = [
                        'pending' => 'bg-orange-100 text-orange-700',
                        'confirmed' => 'bg-blue-100 text-blue-700',
                        'shipped' => 'bg-purple-100 text-purple-700',
                        'delivered' => 'bg-green-100 text-green-700',
                        'cancelled' => 'bg-red-100 text-red-700'
                    ];
                    $orderNum = 1;
                    foreach($orders as $o): 
                    ?>
                    <tr class="border-b hover:bg-slate-50">
                        <td class="p-3 font-mono font-bold text-sm">#<?= $orderNum ?></td>
                        <td class="p-3 font-semibold text-sm"><?= htmlspecialchars($o['customer_name']) ?></td>
                        <td class="p-3 text-xs whitespace-nowrap"><?= htmlspecialchars($o['phone']) ?></td>
                        <td class="p-3 text-xs"><?= htmlspecialchars($o['wilaya']??'-') ?></td>
                        <td class="p-3 text-xs"><?= htmlspecialchars($o['commune']??'-') ?></td>
                        <td class="p-3 text-xs max-w-[180px] truncate" title="<?= htmlspecialchars($o['address']??'') ?>"><?= htmlspecialchars($o['address']??'-') ?></td>
                        <td class="p-3 font-bold text-[#5A1930] text-sm whitespace-nowrap"><?= format_price($o['total']) ?></td>
                        <td class="p-3">
                            <span class="px-2 py-1 rounded-full text-[10px] font-semibold <?= $sc[$o['status']]??'bg-slate-100' ?>">
                                <?= $o['status'] ?>
                            </span>
                        </td>
                        <td class="p-3 text-xs whitespace-nowrap"><?= date('d/m/Y H:i', strtotime($o['created_at'])) ?></td>
                        <td class="p-3">
                            <button onclick="viewOrderDetails(<?= $o['id'] ?>)" class="text-blue-600 hover:underline text-xs">👁️ Voir</button>
                        </td>
                        <td class="p-3">
                            <select onchange="updateOrderStatus(<?= $o['id'] ?>, this.value)" class="text-xs border rounded-lg px-2 py-1.5 w-28 md:w-auto bg-white">
                                <option value="">-- Changer --</option>
                                <option value="confirmed" <?= $o['status'] == 'confirmed' ? 'selected' : '' ?>>✅ Confirmée</option>
                                <option value="shipped" <?= $o['status'] == 'shipped' ? 'selected' : '' ?>>📦 Expédiée</option>
                                <option value="delivered" <?= $o['status'] == 'delivered' ? 'selected' : '' ?>>🏠 Livrée</option>
                                <option value="cancelled" <?= $o['status'] == 'cancelled' ? 'selected' : '' ?>>❌ Annulée</option>
                            </select>
                            <form method="POST" class="inline ml-1">
                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                <input type="hidden" name="id" value="<?= $o['id'] ?>">
                                <button name="delete_order" class="text-red-500" onclick="return confirm('Supprimer ?')">🗑️</button>
                            </form>
                        </td>
                    </tr>
                    <?php $orderNum++; endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>

<!-- Product Modal -->
<div id="product-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-start md:items-center justify-center p-2 md:p-4 pt-16 md:pt-4">
    <div class="bg-white rounded-2xl p-5 md:p-6 w-full max-w-[96vw] md:max-w-lg max-h-[85vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4"><h3 id="p-modal-title" class="text-lg md:text-xl font-bold">Ajouter un produit</h3><button onclick="closeModal('product-modal')" class="text-slate-400 hover:text-slate-600 text-xl">✕</button></div>
        <form id="product-form" class="space-y-3" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><input type="hidden" name="id" id="prod-id"><input type="hidden" name="action" id="prod-action" value="add_product"><input type="hidden" name="current_image" id="current-image">
            <div><label class="text-xs font-bold text-slate-500 mb-1 block">Nom du produit *</label><input name="name" id="prod-name" required class="w-full px-3 py-2.5 border rounded-xl text-sm"></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="text-xs font-bold text-slate-500 mb-1 block">Prix (DA) *</label><input type="number" name="price" id="prod-price" required class="w-full px-3 py-2.5 border rounded-xl text-sm"></div>
                <div><label class="text-xs font-bold text-slate-500 mb-1 block">Ancien prix (DA)</label><input type="number" name="old_price" id="prod-oldprice" class="w-full px-3 py-2.5 border rounded-xl text-sm" placeholder="Prix avant réduction"></div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="text-xs font-bold text-slate-500 mb-1 block">Catégorie *</label>
                    <select name="category_id" id="prod-cat" class="w-full px-3 py-2.5 border rounded-xl text-sm" onchange="loadSubcategoriesByCategory()">
                        <?php foreach($categories as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div><label class="text-xs font-bold text-slate-500 mb-1 block">Statut</label><select name="status" id="prod-status" class="w-full px-3 py-2.5 border rounded-xl text-sm"><option value="published">Publié</option><option value="draft">Brouillon</option><option value="pending">En attente</option></select></div>
            </div>
            <div><label class="text-xs font-bold text-slate-500 mb-1 block">Sous-catégorie</label>
                <select name="subcategory_id" id="prod-subcat" class="w-full px-3 py-2.5 border rounded-xl text-sm">
                    <option value="">Aucune</option>
                    <?php 
                    $selected_cat = isset($_GET['edit_id']) ? $editProduct['category_id'] ?? 0 : 0;
                    foreach($subcategories as $sc): 
                        if($selected_cat == 0 || $sc['category_id'] == $selected_cat):
                    ?>
                    <option value="<?= $sc['id'] ?>" <?= ($editProduct['subcategory_id'] ?? '') == $sc['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sc['name']) ?>
                    </option>
                    <?php 
                        endif; 
                    endforeach; 
                    ?>
                </select>
            </div>
            <div><label class="text-xs font-bold text-slate-500 mb-1 block">Description</label><textarea name="description" id="prod-desc" rows="2" class="w-full px-3 py-2.5 border rounded-xl text-sm"></textarea></div>
            <div><label class="text-xs font-bold text-slate-500 mb-1 block">Tailles disponibles</label><div class="flex flex-wrap gap-2 mb-2" id="sizes-tags"></div><div class="flex gap-2"><input type="text" id="size-input" class="flex-1 px-3 py-2 border rounded-xl text-sm" placeholder="XS, S, M, L..."><button type="button" onclick="addSize()" class="bg-slate-200 px-4 py-2 rounded-xl text-sm font-semibold hover:bg-slate-300">+</button></div><input type="hidden" name="sizes" id="prod-sizes"></div>
            <div><label class="text-xs font-bold text-slate-500 mb-1 block">Couleurs disponibles</label><div class="flex flex-wrap gap-2 mb-2" id="colors-tags"></div><div class="flex gap-2"><input type="text" id="color-input" class="flex-1 px-3 py-2 border rounded-xl text-sm" placeholder="Noir, Rose, Blanc..."><button type="button" onclick="addColor()" class="bg-slate-200 px-4 py-2 rounded-xl text-sm font-semibold hover:bg-slate-300">+</button></div><input type="hidden" name="colors" id="prod-colors"></div>
            <div><label class="text-xs font-bold text-slate-500 mb-1 block">Image principale</label><div class="flex gap-3 items-start"><div class="flex-1"><input type="file" name="image" accept="image/*" class="w-full text-sm" onchange="previewMainImage(this)"><input name="image_url" id="prod-imgurl" class="w-full px-3 py-2.5 border rounded-xl text-sm mt-1" placeholder="Ou URL image"></div><div class="w-20 h-20 rounded-lg border overflow-hidden bg-slate-100 shrink-0"><img id="main-preview-img" src="" class="w-full h-full object-cover hidden"><span id="no-preview-text" class="flex items-center justify-center h-full text-[10px] text-slate-400">Aucune</span></div></div></div>
            <div class="border-t pt-4"><label class="text-xs font-bold text-slate-500 mb-2 block">📸 Images par couleur</label><p class="text-[10px] text-slate-400 mb-3">Ajoutez d'abord les couleurs ci-dessus, puis associez une image à chaque couleur.</p><div id="images-container"></div><button type="button" onclick="addImageRow()" class="text-xs text-blue-600 hover:underline mt-2">+ Ajouter une image</button></div>
            <div><label class="text-xs font-bold text-slate-500 mb-1 block">Quantité maximale</label><input type="number" name="max_qty" id="prod-maxqty" value="10" class="w-full px-3 py-2.5 border rounded-xl text-sm"></div>
            <button type="submit" class="w-full bg-[#5A1930] text-white py-3 rounded-xl text-sm font-bold hover:bg-[#431223]">Enregistrer le produit</button>
        </form>
    </div>
</div>

<!-- Category Modal -->
<div id="category-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-start md:items-center justify-center p-2 md:p-4 pt-16 md:pt-4"><div class="bg-white rounded-2xl p-5 md:p-6 w-full max-w-[96vw] md:max-w-md"><div class="flex justify-between items-center mb-4"><h3 id="c-modal-title" class="text-lg md:text-xl font-bold">Ajouter</h3><button onclick="closeModal('category-modal')" class="text-slate-400 hover:text-slate-600 text-xl">✕</button></div><form id="category-form" class="space-y-3"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><input type="hidden" name="id" id="cat-id"><input type="hidden" name="action" id="cat-action" value="add_category"><div><label class="text-xs font-bold text-slate-500 mb-1 block">Nom *</label><input name="name" id="cat-name" required class="w-full px-3 py-2.5 border rounded-xl text-sm"></div><button type="submit" class="w-full bg-[#5A1930] text-white py-3 rounded-xl text-sm font-bold hover:bg-[#431223]">Enregistrer</button></form></div></div>

<!-- Subcategory Modal -->
<div id="subcategory-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-start md:items-center justify-center p-2 md:p-4 pt-16 md:pt-4"><div class="bg-white rounded-2xl p-5 md:p-6 w-full max-w-[96vw] md:max-w-md"><div class="flex justify-between items-center mb-4"><h3 class="text-lg md:text-xl font-bold">➕ Ajouter une sous-catégorie</h3><button onclick="closeModal('subcategory-modal')" class="text-slate-400 hover:text-slate-600 text-xl">✕</button></div><form id="subcategory-form" class="space-y-3"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><input type="hidden" name="action" value="add_subcategory"><div><label class="text-xs font-bold text-slate-500 mb-1 block">Nom *</label><input name="name" id="sub-name" required class="w-full px-3 py-2.5 border rounded-xl text-sm"></div><div><label class="text-xs font-bold text-slate-500 mb-1 block">Catégorie *</label><select name="category_id" id="sub-cat" class="w-full px-3 py-2.5 border rounded-xl text-sm"><?php foreach($categories as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?></select></div><button type="submit" class="w-full bg-[#5A1930] text-white py-3 rounded-xl text-sm font-bold hover:bg-[#431223]">Enregistrer</button></form></div></div>

<!-- Order Detail Modal -->
<div id="order-detail-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4"><div class="bg-white rounded-2xl p-5 md:p-6 w-full max-w-md max-h-[80vh] overflow-y-auto"><div class="flex justify-between items-center mb-4"><h3 class="text-lg font-bold">Détails de la commande</h3><button onclick="closeModal('order-detail-modal')" class="text-slate-400 hover:text-slate-600 text-xl">✕</button></div><div id="order-detail-content"></div></div></div>

<script>
function closeSide(){if(window.innerWidth<768){document.getElementById('sidebar').classList.remove('open');document.getElementById('overlay').classList.add('hidden')}}
function openModal(id){document.getElementById(id).classList.remove('hidden');document.getElementById(id).classList.add('flex');document.body.style.overflow='hidden'}
function closeModal(id){document.getElementById(id).classList.add('hidden');document.getElementById(id).classList.remove('flex');document.body.style.overflow=''}

function openSubcategoryModal(){
    document.getElementById('subcategory-form').reset();
    openModal('subcategory-modal');
}
document.getElementById('subcategory-form').addEventListener('submit',function(e){
    e.preventDefault();
    var fd=new FormData(this);
    fd.append('action','add_subcategory');
    fetch('api.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if(d.success){location.reload();}
        else{alert(d.message||'Erreur')}
    });
});

// === Fonction pour charger les sous-catégories selon la catégorie ===
function loadSubcategoriesByCategory() {
    var catId = document.getElementById('prod-cat').value;
    var subcatSelect = document.getElementById('prod-subcat');
    var currentSubcat = subcatSelect.value;
    
    if (!catId) {
        subcatSelect.innerHTML = '<option value="">Aucune</option>';
        return;
    }
    
    fetch('api.php?action=list_subcategories&category_id=' + catId)
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                subcatSelect.innerHTML = '<option value="">Aucune</option>';
                d.subcategories.forEach(function(sc) {
                    var selected = (sc.id == currentSubcat) ? 'selected' : '';
                    subcatSelect.innerHTML += '<option value="' + sc.id + '" ' + selected + '>' + sc.name + '</option>';
                });
            }
        });
}

let sizesList=[];
function addSize(){const v=document.getElementById('size-input').value.trim();if(v){const ns=v.split(',').map(s=>s.trim()).filter(s=>s&&!sizesList.includes(s));sizesList=[...sizesList,...ns];document.getElementById('size-input').value='';renderSizes();updateColorSelects()}}
function removeSize(s){sizesList=sizesList.filter(x=>x!==s);renderSizes()}
function renderSizes(){document.getElementById('sizes-tags').innerHTML=sizesList.map(s=>`<span class="inline-flex items-center gap-1 px-3 py-1.5 bg-[#5A1930] text-white text-xs rounded-full">${s}<button type="button" onclick="removeSize('${s}')" class="text-white/70 hover:text-white text-xs">✕</button></span>`).join('');document.getElementById('prod-sizes').value=sizesList.join(',')}

let colorsList=[];
function addColor(){const v=document.getElementById('color-input').value.trim();if(v){const nc=v.split(',').map(s=>s.trim()).filter(s=>s&&!colorsList.includes(s));colorsList=[...colorsList,...nc];document.getElementById('color-input').value='';renderColors();updateColorSelects()}}
function removeColor(c){colorsList=colorsList.filter(x=>x!==c);renderColors();updateColorSelects()}
function renderColors(){document.getElementById('colors-tags').innerHTML=colorsList.map(c=>`<span class="inline-flex items-center gap-1 px-3 py-1.5 bg-pink-100 text-pink-800 text-xs rounded-full">${c}<button type="button" onclick="removeColor('${c}')" class="text-pink-500 hover:text-pink-700 text-xs">✕</button></span>`).join('');document.getElementById('prod-colors').value=colorsList.join(',')}

function updateColorSelects(){document.querySelectorAll('.color-select').forEach(sel=>{const cv=sel.value;sel.innerHTML='<option value="">Choisir une couleur</option>';colorsList.forEach(c=>{sel.innerHTML+=`<option value="${c}" ${c===cv?'selected':''}>${c}</option>`})})}

function previewMainImage(input){const p=document.getElementById('main-preview-img');const n=document.getElementById('no-preview-text');if(input.files&&input.files[0]){const r=new FileReader();r.onload=function(e){p.src=e.target.result;p.classList.remove('hidden');n.classList.add('hidden')};r.readAsDataURL(input.files[0])}}

function addImageRow(){
    const c=document.getElementById('images-container');
    const index=c.children.length;
    const d=document.createElement('div');
    d.className='image-row flex gap-2 items-center mb-2 bg-slate-50 p-2 rounded-lg';
    d.innerHTML=`
        <input type="file" name="product_images[]" accept="image/*" class="w-full text-xs">
        <select name="image_colors[]" class="color-select w-36 px-2 py-1.5 border rounded-lg text-xs">
            <option value="">Couleur</option>
            ${colorsList.map(c=>`<option value="${c}">${c}</option>`).join('')}
        </select>
        <label class="text-[10px] whitespace-nowrap">
            <input type="radio" name="image_default" value="${index}" class="w-3 h-3"> Principale
        </label>
        <button type="button" onclick="this.parentElement.remove()" class="text-red-500 text-xs">✕</button>`;
    c.appendChild(d);
}

function openProductModal(){
    document.getElementById('p-modal-title').innerText='Ajouter un produit';
    document.getElementById('prod-action').value='add_product';document.getElementById('prod-id').value='';document.getElementById('current-image').value='';
    document.getElementById('product-form').reset();document.getElementById('images-container').innerHTML='';
    sizesList=[];renderSizes();colorsList=[];renderColors();
    document.getElementById('main-preview-img').classList.add('hidden');document.getElementById('no-preview-text').classList.remove('hidden');
    document.getElementById('prod-status').value='published';
    setTimeout(loadSubcategoriesByCategory, 100);
    addImageRow();openModal('product-modal');
}
function editProduct(p){
    document.getElementById('p-modal-title').innerText='Modifier: '+p.name;
    document.getElementById('prod-action').value='edit_product';document.getElementById('prod-id').value=p.id;
    document.getElementById('current-image').value=p.image||'';
    document.getElementById('prod-name').value=p.name;document.getElementById('prod-desc').value=p.description||'';
    document.getElementById('prod-price').value=p.price;
    document.getElementById('prod-oldprice').value=p.old_price||'';
    document.getElementById('prod-cat').value=p.category_id;
    document.getElementById('prod-status').value=p.status||'published';
    document.getElementById('prod-imgurl').value='';document.getElementById('prod-maxqty').value=p.max_qty||10;
    sizesList=(p.sizes||'').split(',').map(s=>s.trim()).filter(s=>s);renderSizes();
    colorsList=(p.colors||'').split(',').map(c=>c.trim()).filter(c=>c);renderColors();
    if(p.image){document.getElementById('main-preview-img').src=p.image;document.getElementById('main-preview-img').classList.remove('hidden');document.getElementById('no-preview-text').classList.add('hidden')}
    else{document.getElementById('main-preview-img').classList.add('hidden');document.getElementById('no-preview-text').classList.remove('hidden')}
    const imgContainer=document.getElementById('images-container');imgContainer.innerHTML='';
    if(p.product_images&&p.product_images.length>0){p.product_images.forEach(img=>{const d=document.createElement('div');d.className='image-row flex gap-2 items-center mb-2 bg-green-50 p-2 rounded-lg';d.innerHTML=`<img src="${img.image_path}" class="w-12 h-12 rounded-lg object-cover"><span class="text-xs text-slate-600 flex-1">${img.color_name||'Sans couleur'}</span>${img.is_default==1?'<span class="text-[10px] bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Principale</span>':''}<span class="text-[10px] text-slate-400">(existante)</span>`;imgContainer.appendChild(d)})}
    setTimeout(loadSubcategoriesByCategory, 200);
    addImageRow();openModal('product-modal');
}
document.getElementById('product-form').addEventListener('submit',function(e){e.preventDefault();fetch('api.php',{method:'POST',body:new FormData(this)}).then(r=>r.json()).then(d=>{if(d.success)location.reload();else alert(d.message||'Erreur')})})

function openCategoryModal(){document.getElementById('c-modal-title').innerText='Ajouter une catégorie';document.getElementById('cat-action').value='add_category';document.getElementById('cat-id').value='';document.getElementById('category-form').reset();openModal('category-modal')}
function editCategory(c){document.getElementById('c-modal-title').innerText='Modifier: '+c.name;document.getElementById('cat-action').value='edit_category';document.getElementById('cat-id').value=c.id;document.getElementById('cat-name').value=c.name;openModal('category-modal')}
document.getElementById('category-form').addEventListener('submit',function(e){e.preventDefault();fetch('api.php',{method:'POST',body:new FormData(this)}).then(r=>r.json()).then(d=>{if(d.success)location.reload();else alert(d.message||'Erreur')})})

// === Fonction corrigée pour mettre à jour le statut ===
function updateOrderStatus(id, status) {
    if (!status) return;
    var fd = new FormData();
    fd.append('action', 'update_order_status');
    fd.append('id', id);
    fd.append('status', status);
    
    fetch('api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                location.reload();
            } else {
                alert('Erreur: ' + (d.message || 'Impossible de mettre à jour'));
            }
        })
        .catch(() => alert('Erreur réseau'));
}

function viewOrderDetails(orderId){
    fetch('api.php?action=get_order_items&order_id='+orderId).then(r=>r.json()).then(d=>{
        if(d.success){
            let html='<div class="space-y-2 text-sm">';
            html+='<p><b>Client:</b> '+d.order.customer_name+'</p>';
            html+='<p><b>Tél:</b> '+d.order.phone+'</p>';
            html+='<p><b>Wilaya:</b> '+(d.order.wilaya||'-')+'</p>';
            html+='<p><b>Commune:</b> '+(d.order.commune||'-')+'</p>';
            html+='<p><b>Adresse:</b> '+d.order.address+'</p>';
            html+='<p><b>Livraison:</b> '+(d.order.delivery_method=='domicile'?'Domicile':'Bureau')+'</p>';
            html+='<hr class="my-2"><p class="font-bold text-xs">Produits commandés:</p>';
            d.items.forEach(item=>{
                html+='<div class="bg-slate-50 p-2 rounded-lg text-xs"><b>'+item.product_name+'</b> (x'+item.qty+')<br>';
                if(item.size)html+='Taille: '+item.size+' | ';
                if(item.color)html+='Couleur: '+item.color;
                html+='<br>Prix: '+(item.price*item.qty).toLocaleString('fr-FR')+' DA</div>';
            });
            html+='<hr class="my-2"><p class="font-bold">Total: '+d.order.total.toLocaleString('fr-FR')+' DA</p></div>';
            document.getElementById('order-detail-content').innerHTML=html;
            openModal('order-detail-modal');
        }
    });
}
</script>
</body>
</html>