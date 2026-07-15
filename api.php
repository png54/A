<?php
header('Content-Type: application/json');
require_once 'config.php';
require_once 'functions.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'get_communes') {
    $wilaya_id = intval($_GET['wilaya_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM communes WHERE wilaya_id = ? ORDER BY name ASC");
    $stmt->execute([$wilaya_id]);
    echo json_encode(['success' => true, 'communes' => $stmt->fetchAll()]);
    exit;
}

if ($action === 'submit_order') {
    $lastname = trim($_POST['lastname'] ?? '');
    $firstname = trim($_POST['firstname'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $wilaya_id = intval($_POST['wilaya'] ?? 0);
    $commune = trim($_POST['commune'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $delivery = $_POST['delivery'] ?? 'domicile';
    $shipping = intval($_POST['shipping_fee'] ?? 600);
    $cart = json_decode($_POST['cart'] ?? '[]', true);

    $fullAddress = $address;
    if (!empty($commune)) { $fullAddress .= ', ' . $commune; }

    $errors = [];
    if (empty($lastname)) $errors[] = 'Nom requis';
    if (empty($firstname)) $errors[] = 'Prénom requis';
    if (empty($phone) || strlen($phone) < 8) $errors[] = 'Téléphone invalide';
    if (empty($wilaya_id)) $errors[] = 'Wilaya requise';
    if (empty($commune)) $errors[] = 'Commune requise';
    if (empty($address)) $errors[] = 'Adresse requise';
    if (empty($cart)) $errors[] = 'Panier vide';
    if (!empty($errors)) { echo json_encode(['success' => false, 'message' => implode(', ', $errors)]); exit; }

    $w = $pdo->prepare("SELECT name FROM wilayas WHERE id = ?");
    $w->execute([$wilaya_id]); $wilaya_name = $w->fetchColumn() ?? '';

    try {
        $pdo->beginTransaction();
        $total = 0;
        foreach ($cart as $item) { $total += $item['price'] * $item['qty']; }
        $total += $shipping;
        $stmt = $pdo->prepare("INSERT INTO orders (customer_name, phone, wilaya, commune, address, delivery_method, shipping_fee, total) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$firstname . ' ' . $lastname, $phone, $wilaya_name, $commune, $fullAddress, $delivery, $shipping, $total]);
        $order_id = $pdo->lastInsertId();
        $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, price, qty, size, color) VALUES (?,?,?,?,?,?,?)");
        foreach ($cart as $item) { $stmt->execute([$order_id, $item['product_id'] ?? null, $item['name'], $item['price'], $item['qty'], $item['size'] ?? null, $item['color'] ?? null]); }
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Commande #' . $order_id . ' enregistrée !']);
    } catch (Exception $e) { $pdo->rollBack(); echo json_encode(['success' => false, 'message' => 'Erreur serveur']); }
    exit;
}

if (!is_logged_in()) { echo json_encode(['success' => false, 'message' => 'Non autorisé']); exit; }
$user_id = $_SESSION['user_id'];

switch ($action) {
    case 'list_products':
        $stmt = $pdo->query("SELECT p.*, c.name as cat_name FROM products p JOIN categories c ON p.category_id = c.id ORDER BY p.created_at DESC");
        $products = $stmt->fetchAll();
        foreach ($products as &$p) {
            $stmt = $pdo->prepare("SELECT size FROM product_sizes WHERE product_id = ?"); $stmt->execute([$p['id']]); $p['sizes'] = implode(',', $stmt->fetchAll(PDO::FETCH_COLUMN));
            $stmt = $pdo->prepare("SELECT color_name FROM product_colors WHERE product_id = ?"); $stmt->execute([$p['id']]); $p['colors'] = implode(',', $stmt->fetchAll(PDO::FETCH_COLUMN));
            $stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_default DESC");
            $stmt->execute([$p['id']]); $p['product_images'] = $stmt->fetchAll();
        }
        echo json_encode(['success' => true, 'products' => $products]);
        break;

    case 'add_product':
        $name = trim($_POST['name']); 
        $desc = trim($_POST['description'] ?? '');
        $price = intval($_POST['price']);
        $old_price = !empty($_POST['old_price']) ? intval($_POST['old_price']) : null;
        $cat = intval($_POST['category_id']);
        $subcat = !empty($_POST['subcategory_id']) ? intval($_POST['subcategory_id']) : null;
        $status = $_POST['status'] ?? 'published';
        $max_qty = intval($_POST['max_qty'] ?? 10); 
        $slug = get_unique_slug($pdo, $name, 'products');
        $img = '';
        if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK && $_FILES['image']['size'] > 0) { $up = upload_image('image'); if($up['success']) $img = $up['filepath']; }
        if (empty($img) && !empty($_POST['image_url'])) { $img = $_POST['image_url']; }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO products (name, description, price, old_price, category_id, subcategory_id, image, max_qty, slug, status) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$name, $desc, $price, $old_price, $cat, $subcat, $img, $max_qty, $slug, $status]);
        $pid = $pdo->lastInsertId();

        $sizes = array_filter(explode(',', $_POST['sizes'] ?? ''));
        if (!empty($sizes)) { $st = $pdo->prepare("INSERT INTO product_sizes (product_id, size) VALUES (?,?)"); foreach ($sizes as $s) { $st->execute([$pid, trim($s)]); } }

        $colors = array_filter(explode(',', $_POST['colors'] ?? ''));
        if (!empty($colors)) { $st = $pdo->prepare("INSERT INTO product_colors (product_id, color_name, color_code) VALUES (?,?,?)"); foreach ($colors as $c) { $st->execute([$pid, trim($c), '#cccccc']); } }

        $hasImages = false;
        if (!empty($_FILES['product_images']['name'])) { foreach ($_FILES['product_images']['name'] as $i => $fname) { if (!empty($fname) && $_FILES['product_images']['error'][$i] === UPLOAD_ERR_OK && $_FILES['product_images']['size'][$i] > 0) { $hasImages = true; break; } } }

        if ($hasImages) {
            $stImg = $pdo->prepare("INSERT INTO product_images (product_id, image_path, color_name, is_default) VALUES (?,?,?,?)");
            $imgColors = $_POST['image_colors'] ?? [];
            $imgDefault = intval($_POST['image_default'] ?? -1);
            if (!is_dir('uploads')) { mkdir('uploads', 0755, true); }
            foreach ($_FILES['product_images']['name'] as $i => $fname) {
                if (!empty($fname) && $_FILES['product_images']['error'][$i] === UPLOAD_ERR_OK && $_FILES['product_images']['size'][$i] > 0) {
                    $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
                    $target = 'uploads/prod_' . bin2hex(random_bytes(8)) . '.' . $ext;
                    if (move_uploaded_file($_FILES['product_images']['tmp_name'][$i], $target)) {
                        $color = isset($imgColors[$i]) ? trim($imgColors[$i]) : '';
                        $isDefault = ($imgDefault === $i) ? 1 : 0;
                        $stImg->execute([$pid, $target, $color, $isDefault]);
                        if ($isDefault) { $pdo->prepare("UPDATE products SET image=? WHERE id=?")->execute([$target, $pid]); }
                    }
                }
            }
        }
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Produit ajouté']);
        break;

    case 'edit_product':
        $id = intval($_POST['id']); 
        $name = trim($_POST['name']); 
        $desc = trim($_POST['description'] ?? '');
        $price = intval($_POST['price']);
        $old_price = !empty($_POST['old_price']) ? intval($_POST['old_price']) : null;
        $cat = intval($_POST['category_id']);
        $subcat = !empty($_POST['subcategory_id']) ? intval($_POST['subcategory_id']) : null;
        $max_qty = intval($_POST['max_qty'] ?? 10); 
        $status = $_POST['status'] ?? 'published'; 
        $featured = isset($_POST['featured']) ? 1 : 0;
        $slug = get_unique_slug($pdo, $name, 'products', 'slug', $id);
        $img = $_POST['current_image'] ?? '';
        if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK && $_FILES['image']['size'] > 0) { $up = upload_image('image'); if($up['success']) $img = $up['filepath']; }
        elseif (!empty($_POST['image_url'])) { $img = $_POST['image_url']; }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE products SET name=?, description=?, price=?, old_price=?, category_id=?, subcategory_id=?, image=?, max_qty=?, status=?, featured=?, slug=? WHERE id=?");
        $stmt->execute([$name, $desc, $price, $old_price, $cat, $subcat, $img, $max_qty, $status, $featured, $slug, $id]);

        $pdo->prepare("DELETE FROM product_sizes WHERE product_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM product_colors WHERE product_id=?")->execute([$id]);
        $sizes = array_filter(explode(',', $_POST['sizes'] ?? '')); if (!empty($sizes)) { $st = $pdo->prepare("INSERT INTO product_sizes (product_id, size) VALUES (?,?)"); foreach ($sizes as $s) { $st->execute([$id, trim($s)]); } }
        $colors = array_filter(explode(',', $_POST['colors'] ?? '')); if (!empty($colors)) { $st = $pdo->prepare("INSERT INTO product_colors (product_id, color_name, color_code) VALUES (?,?,?)"); foreach ($colors as $c) { $st->execute([$id, trim($c), '#cccccc']); } }

        $hasImages = false;
        if (!empty($_FILES['product_images']['name'])) { foreach ($_FILES['product_images']['name'] as $i => $fname) { if (!empty($fname) && $_FILES['product_images']['error'][$i] === UPLOAD_ERR_OK && $_FILES['product_images']['size'][$i] > 0) { $hasImages = true; break; } } }

        if ($hasImages) {
            $pdo->prepare("DELETE FROM product_images WHERE product_id=?")->execute([$id]);
            $stImg = $pdo->prepare("INSERT INTO product_images (product_id, image_path, color_name, is_default) VALUES (?,?,?,?)");
            $imgColors = $_POST['image_colors'] ?? [];
            $imgDefault = intval($_POST['image_default'] ?? -1);
            if (!is_dir('uploads')) { mkdir('uploads', 0755, true); }
            foreach ($_FILES['product_images']['name'] as $i => $fname) {
                if (!empty($fname) && $_FILES['product_images']['error'][$i] === UPLOAD_ERR_OK && $_FILES['product_images']['size'][$i] > 0) {
                    $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
                    $target = 'uploads/prod_' . bin2hex(random_bytes(8)) . '.' . $ext;
                    if (move_uploaded_file($_FILES['product_images']['tmp_name'][$i], $target)) {
                        $color = isset($imgColors[$i]) ? trim($imgColors[$i]) : '';
                        $isDefault = ($imgDefault === $i) ? 1 : 0;
                        $stImg->execute([$id, $target, $color, $isDefault]);
                        if ($isDefault) { $pdo->prepare("UPDATE products SET image=? WHERE id=?")->execute([$target, $id]); }
                    }
                }
            }
        }
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Produit modifié']);
        break;

    case 'delete_product': 
        $pdo->prepare("DELETE FROM products WHERE id=?")->execute([intval($_POST['id'])]); 
        echo json_encode(['success' => true]); 
        break;

    case 'list_categories': 
        echo json_encode(['success' => true, 'categories' => $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll()]); 
        break;

    case 'add_category': 
        $n=trim($_POST['name']); $s=get_unique_slug($pdo,$n,'categories'); 
        $pdo->prepare("INSERT INTO categories (name, slug) VALUES (?,?)")->execute([$n,$s]); 
        echo json_encode(['success'=>true]); 
        break;

    case 'edit_category': 
        $id=intval($_POST['id']); $n=trim($_POST['name']); $s=get_unique_slug($pdo,$n,'categories','slug',$id); 
        $pdo->prepare("UPDATE categories SET name=?, slug=? WHERE id=?")->execute([$n,$s,$id]); 
        echo json_encode(['success'=>true]); 
        break;

    case 'delete_category': 
        $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([intval($_POST['id'])]); 
        echo json_encode(['success'=>true]); 
        break;

    case 'list_orders': 
        echo json_encode(['success'=>true,'orders'=>$pdo->query("SELECT * FROM orders ORDER BY id DESC LIMIT 100")->fetchAll()]); 
        break;

    case 'update_order_status': 
        $pdo->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$_POST['status'],intval($_POST['id'])]); 
        echo json_encode(['success'=>true]); 
        break;

    case 'get_order_items':
        $id = intval($_GET['order_id'] ?? 0);
        $order = $pdo->prepare("SELECT * FROM orders WHERE id=?");
        $order->execute([$id]); $order = $order->fetch();
        $items = $pdo->prepare("SELECT * FROM order_items WHERE order_id=?");
        $items->execute([$id]);
        echo json_encode(['success' => true, 'order' => $order, 'items' => $items->fetchAll()]);
        break;

    case 'list_subcategories':
        $cat_id = intval($_GET['category_id'] ?? 0);
        if ($cat_id > 0) {
            $stmt = $pdo->prepare("SELECT * FROM subcategories WHERE category_id = ? ORDER BY name ASC");
            $stmt->execute([$cat_id]);
        } else {
            $stmt = $pdo->query("SELECT s.*, c.name as cat_name FROM subcategories s JOIN categories c ON s.category_id = c.id ORDER BY c.name, s.name");
        }
        echo json_encode(['success' => true, 'subcategories' => $stmt->fetchAll()]);
        break;

    case 'add_subcategory':
        $name = trim($_POST['name'] ?? '');
        $cat_id = intval($_POST['category_id'] ?? 0);
        if (empty($name) || $cat_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Nom ou catégorie invalide']);
            break;
        }
        $slug = get_unique_slug($pdo, $name, 'subcategories');
        $pdo->prepare("INSERT INTO subcategories (name, slug, category_id) VALUES (?,?,?)")->execute([$name, $slug, $cat_id]);
        echo json_encode(['success' => true, 'message' => 'Sous-catégorie ajoutée']);
        break;

    case 'delete_subcategory':
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID invalide']);
            break;
        }
        $pdo->prepare("DELETE FROM subcategories WHERE id=?")->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Sous-catégorie supprimée']);
        break;

    case 'dashboard_stats': 
        echo json_encode([
            'success'=>true,
            'stats'=>[
                'total_products'=>$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn(),
                'total_orders'=>$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
                'pending_orders'=>$pdo->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn(),
                // ✅ فقط confirmed, shipped, delivered
                'total_revenue'=>$pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status IN ('confirmed','shipped','delivered')")->fetchColumn()
            ]
        ]); 
        break;

    default: 
        echo json_encode(['success'=>false,'message'=>'Action inconnue']);
}
?>