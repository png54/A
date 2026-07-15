<?php
require_once 'config.php';
require_once 'functions.php';

$current_category = $_GET['cat'] ?? 'all';
$current_subcategory = $_GET['subcat'] ?? null;

$shippingRates = [];
try { $stmt = $pdo->query("SELECT * FROM shipping_rates"); while ($row = $stmt->fetch()) { $shippingRates[$row['wilaya_id']] = $row; } } catch (Exception $e) {}

$settings = [];
try { $stmt = $pdo->query("SELECT * FROM settings"); while ($row = $stmt->fetch()) { $settings[$row['key_name']] = $row['value']; } } catch (Exception $e) {}

try {
    $cats = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
    $wilayas = $pdo->query("SELECT * FROM wilayas ORDER BY code ASC")->fetchAll();
    $subcats = [];
    try {
        $subcats = $pdo->query("SELECT s.*, c.name as cat_name, c.slug as cat_slug FROM subcategories s JOIN categories c ON s.category_id = c.id ORDER BY c.name, s.name")->fetchAll();
    } catch (Exception $e) {}
    $current_subcategories = [];
    if ($current_category !== 'all') {
        $current_subcategories = array_filter($subcats, function($sc) use ($current_category) { return $sc['cat_slug'] === $current_category; });
    }
    if ($current_subcategory) {
        foreach ($subcats as $sc) {
            if ($sc['slug'] === $current_subcategory) { $current_category = $sc['cat_slug']; break; }
        }
        $current_subcategories = array_filter($subcats, function($sc) use ($current_category) { return $sc['cat_slug'] === $current_category; });
    }
    $sql = "SELECT p.*, c.name as cat_name, s.name as sub_name, s.slug as sub_slug FROM products p JOIN categories c ON p.category_id = c.id LEFT JOIN subcategories s ON p.subcategory_id = s.id WHERE p.status = 'published'";
    $params = [];
    if ($current_subcategory) { $sql .= " AND s.slug = ?"; $params[] = $current_subcategory; }
    elseif ($current_category !== 'all') { $sql .= " AND c.slug = ?"; $params[] = $current_category; }
    $sql .= " ORDER BY p.featured DESC, p.created_at DESC";
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    $products = $stmt->fetchAll();
    foreach ($products as &$p) {
        $st = $pdo->prepare("SELECT size FROM product_sizes WHERE product_id = ?"); $st->execute([$p['id']]); $p['sizes'] = $st->fetchAll(PDO::FETCH_COLUMN);
        $st = $pdo->prepare("SELECT color_name FROM product_colors WHERE product_id = ?"); $st->execute([$p['id']]); $p['colors'] = $st->fetchAll(PDO::FETCH_COLUMN);
        $st = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_default DESC"); $st->execute([$p['id']]); $p['images'] = $st->fetchAll();
        $p['default_image'] = $p['images'][0]['image_path'] ?? $p['image'];
        $p['old_price'] = $p['old_price'] ?? null;
        $p['has_options'] = (!empty($p['sizes']) || !empty($p['colors']));
    }
    unset($p);
    $page_title = 'Tous les produits';
    if ($current_subcategory) {
        foreach ($subcats as $sc) { if ($sc['slug'] === $current_subcategory) { $page_title = htmlspecialchars($sc['name']); break; } }
    } elseif ($current_category !== 'all') {
        foreach ($cats as $cat) { if ($cat['slug'] === $current_category) { $page_title = htmlspecialchars($cat['name']); break; } }
    }
} catch (Exception $e) {
    $cats = []; $wilayas = []; $products = []; $subcats = []; $current_subcategories = []; $page_title = 'Tous les produits';
}

$csrf_token = generate_csrf_token();
$fb = $settings['facebook_url'] ?? '';
$ig = $settings['instagram_url'] ?? '';
$tk = $settings['tiktok_url'] ?? '';
$ph = $settings['phone_number'] ?? '0797336257';
$ad1 = $settings['address_line1'] ?? 'Ain Benian, Alger';
$ad2 = $settings['address_line2'] ?? '';
$map_url = 'https://maps.app.goo.gl/b5j6ApWJhUEqLwJf7';
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
<title>For you by mb — Boutique Officielle</title>
<meta name="description" content="Boutique officielle For you by mb — Mode féminine, beauté et bien-être. Livraison partout en Algérie.">
<meta name="keywords" content="boutique femme algérie, mode féminine, beauté, bien-être, livraison algérie">
<meta property="og:title" content="For you by mb — Boutique Officielle">
<meta property="og:description" content="Mode féminine, beauté et bien-être. Livraison dans toute l'Algérie.">
<meta property="og:image" content="1.png">
<meta property="og:type" content="website">
<meta name="theme-color" content="#5A1930">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Inter:wght@300;400;500;600;700;800&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  theme: {
    extend: {
      colors: {
        burgundy: '#5A1930',
        'burgundy-light': '#8B3A5A',
        'burgundy-dark': '#3D0F1F',
        brandPink: '#D394B6',
        bgSoft: '#E5C8DD',
        cream: '#FDF8FA',
        charcoal: '#2D2D2D'
      },
      fontFamily: {
        serif: ['"Playfair Display"', 'serif'],
        sans: ['Inter', 'sans-serif'],
        arabic: ['"Cairo"', 'sans-serif']
      }
    }
  }
}
</script>
<style>
/* ─── THEME TOKENS ─── */
:root {
  --B: #5A1930; --BL: #8B3A5A; --BD: #3D0F1F;
  --P: #D394B6; --bg: #FDF8FA; --surf: #FFFFFF;
  --card: #FFFFFF; --cream: #E5C8DD;
  --tx: #2D2D2D; --tx2: #6B7280; --tx3: #9CA3AF;
  --border: rgba(90,25,48,.08); --borderS: rgba(90,25,48,.16);
  --sh1: 0 2px 10px rgba(90,25,48,.06);
  --sh2: 0 10px 40px rgba(90,25,48,.12);
  --sh3: 0 24px 70px rgba(90,25,48,.18);
}
[data-theme="dark"] {
  --bg: #0E0608; --surf: #1A0B10; --card: #22101A;
  --cream: #2A1020; --tx: #F0E0E8; --tx2: #C09FB0;
  --tx3: #7A5068; --border: rgba(211,148,182,.08);
  --borderS: rgba(211,148,182,.16);
  --sh1: 0 2px 10px rgba(0,0,0,.2);
  --sh2: 0 10px 40px rgba(0,0,0,.32);
  --sh3: 0 24px 70px rgba(0,0,0,.45);
}

/* ─── BASE ─── */
*{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--tx);-webkit-font-smoothing:antialiased;transition:background .3s,color .3s}

/* ─── SCROLLBAR ─── */
::-webkit-scrollbar{width:5px;height:5px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--B);border-radius:10px}
.noscroll::-webkit-scrollbar{display:none}
.noscroll{-ms-overflow-style:none;scrollbar-width:none}

/* ─── ANIMATIONS ─── */
@keyframes floatY{0%,100%{transform:translateY(0)}50%{transform:translateY(-9px)}}
@keyframes fadeUp{from{opacity:0;transform:translateY(26px)}to{opacity:1;transform:translateY(0)}}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
@keyframes gradShift{0%,100%{background-position:0% 50%}50%{background-position:100% 50%}}
@keyframes blink{0%,100%{opacity:1}50%{opacity:0}}
@keyframes cartShake{0%,100%{transform:rotate(0)}20%{transform:rotate(-13deg)}40%{transform:rotate(13deg)}60%{transform:rotate(-8deg)}80%{transform:rotate(8deg)}}
@keyframes petalFly{0%{opacity:.9;transform:translate(0,0) rotate(0) scale(1)}100%{opacity:0;transform:translate(var(--px),var(--py)) rotate(var(--pr)) scale(.2)}}
@keyframes rippleOut{to{transform:scale(4);opacity:0}}
@keyframes wishPop{0%{transform:scale(1)}40%{transform:scale(1.6)}70%{transform:scale(.88)}100%{transform:scale(1)}}
@keyframes badgePop{0%{transform:scale(1)}50%{transform:scale(1.45)}100%{transform:scale(1)}}
@keyframes shimbarMove{0%{background-position:200% 0}100%{background-position:-200% 0}}
@keyframes silkShimmer{0%{background-position:200% 200%}100%{background-position:-200% -200%}}
@keyframes revealUp{from{opacity:0;transform:translateY(28px)}to{opacity:1;transform:translateY(0)}}
@keyframes overlayIn{from{opacity:0}to{opacity:1}}
@keyframes modalIn{from{opacity:0;transform:translateY(20px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}

/* ─── PROGRESS BAR ─── */
#pgbar{position:fixed;top:0;left:0;width:0;height:3px;z-index:9999;
  background:linear-gradient(90deg,var(--B),var(--P),var(--B));
  background-size:200% 100%;animation:shimbarMove 1.2s linear infinite;
  transition:width .25s ease,opacity .4s}

/* ─── BACK TO TOP ─── */
#btt{position:fixed;bottom:26px;right:22px;width:46px;height:46px;border-radius:50%;
  background:var(--B);color:#fff;border:none;cursor:pointer;
  display:flex;align-items:center;justify-content:center;font-size:17px;
  box-shadow:0 6px 24px rgba(90,25,48,.35);z-index:120;
  opacity:0;transform:translateY(18px) scale(.8);
  transition:all .3s cubic-bezier(.4,0,.2,1);pointer-events:none}
#btt.show{opacity:1;transform:translateY(0) scale(1);pointer-events:all}
#btt:hover{transform:translateY(-3px) scale(1.06)}

/* ─── PETAL CONTAINER ─── */
#petals{position:fixed;inset:0;pointer-events:none;z-index:9990;overflow:hidden}
.petal{position:absolute;width:9px;height:9px;border-radius:50% 0 50% 0;
  animation:petalFly .75s ease-out forwards;
  animation-delay:var(--pd,0s)}

/* ─── DARK TOGGLE ─── */
.dktog{position:relative;width:38px;height:21px;border-radius:11px;
  border:none;cursor:pointer;background:rgba(255,255,255,.18);transition:background .3s}
.dktog::after{content:'';position:absolute;top:3px;left:3px;
  width:15px;height:15px;border-radius:50%;background:#fff;transition:transform .3s}
[data-theme="dark"] .dktog{background:rgba(211,148,182,.35)}
[data-theme="dark"] .dktog::after{transform:translateX(17px);background:var(--P)}

/* ─── HEADER GLASS ─── */
#hdr{background:rgba(253,248,250,.93);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);
  border-bottom:1px solid var(--border);transition:box-shadow .3s}
[data-theme="dark"] #hdr{background:rgba(26,11,16,.93)}
#hdr.scrolled{box-shadow:0 4px 30px rgba(90,25,48,.12)}

/* ─── NAV LINKS ─── */
.nl{position:relative;transition:all .3s cubic-bezier(.4,0,.2,1)}
.nl::after{content:'';position:absolute;bottom:4px;left:50%;width:0;height:2px;
  background:var(--B);transition:all .3s cubic-bezier(.4,0,.2,1);
  transform:translateX(-50%);border-radius:2px}
.nl:hover::after,.nl.act::after{width:60%}
.nl.act{background:var(--B);color:#fff;box-shadow:0 4px 20px rgba(90,25,48,.28)}
.nl.act::after{display:none}
.sl{position:relative;transition:all .3s cubic-bezier(.4,0,.2,1)}
.sl.act{background:var(--B);color:#fff;box-shadow:0 6px 24px rgba(90,25,48,.32)}
.sl.act .sbdg{background:rgba(255,255,255,.2);color:#fff}

/* ─── HERO ─── */
.hero{position:relative;overflow:hidden;
  background:linear-gradient(135deg,rgba(90,25,48,.06) 0%,var(--bg) 45%,rgba(211,148,182,.07) 100%)}
.hero-grid{position:absolute;inset:0;opacity:.035;
  background-image:repeating-linear-gradient(45deg,var(--B) 0,var(--B) 1px,transparent 0,transparent 50%),
    repeating-linear-gradient(-45deg,var(--B) 0,var(--B) 1px,transparent 0,transparent 50%);
  background-size:28px 28px}
.hero-glow1{position:absolute;width:560px;height:560px;border-radius:50%;
  background:radial-gradient(circle,rgba(211,148,182,.14) 0%,transparent 70%);
  top:-80px;right:-80px;animation:floatY 9s ease-in-out infinite}
.hero-glow2{position:absolute;width:380px;height:380px;border-radius:50%;
  background:radial-gradient(circle,rgba(90,25,48,.07) 0%,transparent 70%);
  bottom:-80px;left:-40px;animation:floatY 12s ease-in-out infinite reverse}
.grad-text{background:linear-gradient(135deg,var(--B) 0%,var(--P) 55%,var(--B) 100%);
  background-size:200% 200%;-webkit-background-clip:text;
  -webkit-text-fill-color:transparent;background-clip:text;animation:gradShift 4s ease infinite}
.tcursor::after{content:'|';animation:blink .8s ease infinite;color:var(--P)}
.eyebrow{font-size:10px;font-weight:700;text-transform:uppercase;
  letter-spacing:.18em;color:var(--P);margin-bottom:8px}

/* ─── SEARCH ─── */
.srch{background:var(--surf);border:2px solid var(--border);color:var(--tx);
  border-radius:50px;padding:10px 18px;font-size:13px;
  transition:border-color .25s,box-shadow .25s;outline:none;width:100%}
.srch:focus{border-color:var(--B);box-shadow:0 0 0 4px rgba(90,25,48,.08)}
.srch::placeholder{color:var(--tx3)}
.srch-wrap{position:relative}
.srch-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--tx3);font-size:13px;pointer-events:none}
.srch.hasi{padding-left:38px}
.srch-clr{position:absolute;right:13px;top:50%;transform:translateY(-50%);
  color:var(--tx3);cursor:pointer;display:none;background:none;border:none;font-size:14px}
.srch-clr:hover{color:var(--B)}

/* ─── SORT SELECT ─── */
.srt{background:var(--surf);border:2px solid var(--border);color:var(--tx);
  border-radius:50px;padding:10px 36px 10px 18px;font-size:12px;font-weight:600;
  cursor:pointer;transition:border-color .25s;outline:none;appearance:none;
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='11' height='7' viewBox='0 0 11 7'%3E%3Cpath fill='%235A1930' d='M5.5 7L0 0h11z'/%3E%3C/svg%3E");
  background-repeat:no-repeat;background-position:right 13px center}
.srt:focus{border-color:var(--B)}
[data-theme="dark"] .srt{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='11' height='7' viewBox='0 0 11 7'%3E%3Cpath fill='%23D394B6' d='M5.5 7L0 0h11z'/%3E%3C/svg%3E")}

/* ─── PRODUCT CARDS ─── */
.pc{background:var(--card);border-radius:20px;overflow:visible;position:relative;
  border:1px solid var(--border);box-shadow:var(--sh1);
  transition:box-shadow .4s cubic-bezier(.4,0,.2,1),border-color .4s;
  transform-style:preserve-3d;will-change:transform;
  opacity:0;animation:revealUp .5s ease forwards}
.pc:hover{box-shadow:var(--sh3);border-color:var(--borderS)}

/* silk shimmer on hover */
.pc::before{content:'';position:absolute;inset:0;border-radius:20px;
  background:linear-gradient(135deg,transparent 30%,rgba(255,255,255,.07) 50%,transparent 70%);
  background-size:300% 300%;opacity:0;pointer-events:none;z-index:2;
  transition:opacity .3s}
.pc:hover::before{opacity:1;animation:silkShimmer 1.6s linear infinite}

.pimg-w{border-radius:20px 20px 0 0;overflow:hidden;position:relative;
  aspect-ratio:1;background:var(--cream);cursor:pointer}
.pimg{width:100%;height:100%;object-fit:cover;
  transition:transform .6s cubic-bezier(.4,0,.2,1);display:block}
.pc:hover .pimg{transform:scale(1.07)}

/* wishlist btn */
.wbtn{position:absolute;top:10px;left:10px;width:34px;height:34px;
  border-radius:50%;background:rgba(253,248,250,.9);backdrop-filter:blur(12px);
  border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;
  font-size:14px;color:#9CA3AF;transition:all .25s;z-index:4}
.wbtn:hover{background:#fff;transform:scale(1.1)}
.wbtn.on{color:#E53E3E;animation:wishPop .35s ease}

/* discount badge */
.dbdg{position:absolute;top:10px;left:52px;background:var(--B);color:#fff;
  font-size:9px;font-weight:800;padding:3px 9px;border-radius:20px;z-index:4;letter-spacing:.03em}

/* price badge */
.pbdg{position:absolute;top:10px;right:10px;
  background:rgba(253,248,250,.93);backdrop-filter:blur(12px);
  padding:5px 10px;border-radius:12px;display:flex;flex-direction:column;align-items:flex-end;z-index:4}
.pbdg .op{font-size:9px;color:#9CA3AF;text-decoration:line-through;line-height:1}
.pbdg .cp{font-size:13px;font-weight:700;color:var(--B);line-height:1.2}

/* quick view overlay */
.pov{position:absolute;inset:0;
  background:linear-gradient(to top,rgba(61,15,31,.7) 0%,transparent 55%);
  display:flex;align-items:flex-end;justify-content:center;padding-bottom:14px;
  opacity:0;transition:opacity .3s;z-index:3}
.pc:hover .pov{opacity:1}
.qvbtn{background:rgba(253,248,250,.95);color:var(--B);border:none;
  border-radius:50px;padding:8px 18px;font-size:11px;font-weight:700;
  cursor:pointer;backdrop-filter:blur(10px);transition:all .2s;
  display:flex;align-items:center;gap:5px}
.qvbtn:hover{background:var(--B);color:#fff}

/* card body */
.cbody{padding:14px 16px}
.cmeta{display:flex;align-items:center;gap:5px;margin-bottom:5px}
.ccat{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:var(--B);opacity:.5}
.csep{color:var(--tx3);font-size:8px}
.csub{font-size:9px;color:var(--tx3)}
.cname{font-family:'Playfair Display',serif;font-weight:600;
  font-size:clamp(13px,2vw,15px);color:var(--tx);line-height:1.3;margin-bottom:8px}
.cprice-row{display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:11px}
.cprice{font-weight:700;font-size:14px;color:var(--B)}
.cold{font-size:11px;color:var(--tx3);text-decoration:line-through}
.cdpill{background:rgba(90,25,48,.08);color:var(--B);font-size:9px;font-weight:700;padding:2px 7px;border-radius:20px}

/* card buttons */
.cbtn{width:100%;border:none;border-radius:12px;padding:10px;
  font-size:11px;font-weight:700;cursor:pointer;
  transition:all .3s ease;display:flex;align-items:center;justify-content:center;gap:6px;
  position:relative;overflow:hidden}
.cbtn-add{background:rgba(90,25,48,.06);color:var(--B)}
.cbtn-add:hover{background:var(--B);color:#fff;box-shadow:0 6px 20px rgba(90,25,48,.28);transform:translateY(-1px)}
.cbtn-opt{background:var(--cream);color:var(--B)}
.cbtn-opt:hover{background:var(--B);color:#fff;box-shadow:0 6px 20px rgba(90,25,48,.28);transform:translateY(-1px)}
.rpl{position:absolute;border-radius:50%;background:rgba(255,255,255,.3);
  width:60px;height:60px;margin-top:-30px;margin-left:-30px;
  animation:rippleOut .6s linear;pointer-events:none}

/* ─── CART BADGE POP ─── */
.bpop{animation:badgePop .28s ease}

/* ─── WISHLIST / CART DRAWERS ─── */
#wl-drawer,#cart-drawer{transition:transform .32s cubic-bezier(.4,0,.2,1)}

/* ─── GALLERY THUMB ─── */
.gt{transition:all .3s ease;cursor:pointer;border:2px solid transparent;border-radius:10px;overflow:hidden}
.gt:hover,.gt.active{border-color:var(--B);opacity:1!important}

/* ─── QTY CONTROLS ─── */
.qbtn{width:36px;height:36px;border-radius:50%;border:2px solid var(--B);
  background:transparent;color:var(--B);font-size:18px;font-weight:bold;
  cursor:pointer;transition:all .3s;display:flex;align-items:center;justify-content:center}
.qbtn:hover{background:var(--B);color:#fff}
.qdsp{font-size:20px;font-weight:bold;color:var(--tx);min-width:38px;text-align:center}

/* ─── MODALS ─── */
.mover{animation:overlayIn .25s ease}
.mcont{animation:modalIn .35s cubic-bezier(.4,0,.2,1)}

/* ─── TOAST ─── */
#toast{display:flex;align-items:center;gap:9px;max-width:calc(100vw - 32px);
  background:rgba(30,15,20,.95);backdrop-filter:blur(12px)}

/* ─── EMPTY STATE ─── */
.empty{text-align:center;padding:80px 20px;animation:fadeUp .5s ease}

/* ─── BACK TO TOP ─── */
#btt{position:fixed;bottom:26px;right:22px}

/* ─── DARK OVERRIDES ─── */
[data-theme="dark"] #topbar{background:var(--BD)}
[data-theme="dark"] footer{background:#06030A!important}
[data-theme="dark"] .mcont,[data-theme="dark"] #cart-drawer,[data-theme="dark"] #wl-drawer{background:var(--surf)!important;color:var(--tx)!important}
[data-theme="dark"] input,[data-theme="dark"] select,[data-theme="dark"] textarea{background:var(--card)!important;color:var(--tx)!important;border-color:var(--border)!important}

/* ─── OLD PRICE ─── */
.old-price{text-decoration:line-through;color:#9ca3af;font-size:.75rem}
.discount-badge{background:var(--B);color:#fff;font-size:.6rem;font-weight:bold;padding:2px 8px;border-radius:9999px;margin-left:6px}

/* ─── STAGGER ─── */
.pc:nth-child(1){animation-delay:.04s}
.pc:nth-child(2){animation-delay:.09s}
.pc:nth-child(3){animation-delay:.14s}
.pc:nth-child(4){animation-delay:.19s}
.pc:nth-child(5){animation-delay:.24s}
.pc:nth-child(6){animation-delay:.29s}
.pc:nth-child(7){animation-delay:.34s}
.pc:nth-child(8){animation-delay:.39s}

/* ─── WISHLIST COUNT ─── */
#wl-cnt{position:absolute;top:-2px;right:-2px;min-width:16px;height:16px;
  background:var(--P);color:#fff;font-size:8px;font-weight:800;
  border-radius:8px;display:flex;align-items:center;justify-content:center;padding:0 3px}

/* ─── RTL ─── */
[dir="rtl"]{direction:rtl;text-align:right;font-family:'Cairo','Inter',sans-serif}

/* ─── MOBILE ─── */
@media(max-width:640px){
  .nl{font-size:12px;padding:6px 14px}
  .sl{font-size:12px;padding:6px 14px}
  .cname{font-size:13px}
  .dbdg{left:46px}
}
</style>
</head>

<body>
<!-- Progress bar -->
<div id="pgbar"></div>
<!-- Petal FX -->
<div id="petals"></div>
<!-- Back to top -->
<button id="btt" onclick="scrollTo({top:0,behavior:'smooth'})" aria-label="Haut de page">
  <i class="fas fa-chevron-up"></i>
</button>

<!-- ══════════════ TOPBAR ══════════════ -->
<div id="topbar" class="bg-burgundy text-white/70 text-xs py-2.5 px-4 border-b border-white/5">
  <div class="max-w-7xl mx-auto flex flex-wrap items-center justify-between gap-2">
    <div class="flex items-center gap-5 text-[11px] flex-wrap">
      <span class="flex items-center gap-1.5"><i class="fas fa-phone text-[11px]"></i>
        <a href="tel:0552800684" class="hover:text-white transition">0552800684</a></span>
      <span class="flex items-center gap-1.5"><i class="fas fa-phone text-[11px]"></i>
        <a href="tel:0797336257" class="hover:text-white transition">0797336257</a></span>
      <a href="<?= $map_url ?>" target="_blank" class="flex items-center gap-1.5 hover:text-white transition text-[11px]">
        <i class="fas fa-map-marker-alt text-[11px]"></i>Ain Benian, Alger</a>
    </div>
    <div class="flex items-center gap-4">
      <div class="flex items-center gap-3 text-[13px]">
        <a href="<?= $fb ?: '#' ?>" target="_blank" class="hover:text-white transition opacity-70 hover:opacity-100"><i class="fab fa-facebook"></i></a>
        <a href="<?= $ig ?: '#' ?>" target="_blank" class="hover:text-white transition opacity-70 hover:opacity-100"><i class="fab fa-instagram"></i></a>
        <a href="<?= $tk ?: '#' ?>" target="_blank" class="hover:text-white transition opacity-70 hover:opacity-100"><i class="fab fa-tiktok"></i></a>
      </div>
      <div class="flex items-center gap-1.5 text-[10px]">
        <i class="fas fa-sun opacity-50"></i>
        <button class="dktog" id="dtbtn" onclick="toggleDark()" aria-label="Mode sombre"></button>
        <i class="fas fa-moon opacity-50"></i>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════ HEADER ══════════════ -->
<header id="hdr" class="sticky top-0 z-40">
  <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between gap-3">
    <!-- Logo -->
    <a href="index.php" class="flex items-center gap-3 group flex-shrink-0">
      <div class="w-10 h-10 md:w-14 md:h-14 rounded-xl flex items-center justify-center overflow-hidden transition" style="background:rgba(90,25,48,.06)">
        <img src="1.png" class="w-8 h-8 md:w-11 md:h-11 object-contain" alt="For you by mb">
      </div>
      <div>
        <span class="font-serif italic font-bold text-xl md:text-2xl tracking-tight" style="color:var(--B)">For you by mb</span>
        <span class="block text-[9px] font-medium tracking-[.15em] uppercase" style="color:var(--tx3)">Boutique officielle</span>
      </div>
    </a>

    <!-- Desktop search -->
    <div class="flex-1 max-w-xs hidden md:block srch-wrap">
      <i class="fas fa-search srch-icon"></i>
      <input type="search" id="sh-desk" placeholder="Rechercher…" class="srch hasi"
             oninput="doSearch(this.value)" onkeydown="if(event.key==='Escape')clrSearch()">
      <button class="srch-clr" id="sc-desk" onclick="clrSearch()"><i class="fas fa-times-circle"></i></button>
    </div>

    <!-- Actions -->
    <div class="flex items-center gap-1">
      <button class="md:hidden p-2.5 rounded-xl hover:bg-burgundy/5 transition" style="color:var(--B)" onclick="toggleMSrch()" aria-label="Rechercher">
        <i class="fas fa-search text-base"></i>
      </button>
      <button onclick="openWL()" class="relative p-2.5 rounded-xl hover:bg-burgundy/5 transition" style="color:var(--B)" aria-label="Favoris">
        <i class="far fa-heart text-lg" id="wl-icon"></i>
        <span id="wl-cnt" class="hidden">0</span>
      </button>
      <button onclick="openCart()" id="cart-btn" class="relative p-2.5 rounded-xl hover:bg-burgundy/5 transition" style="color:var(--B)" aria-label="Panier">
        <i class="fas fa-shopping-bag text-lg" id="cart-icon"></i>
        <span id="cart-badge" class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] flex items-center justify-center text-[9px] font-bold text-white rounded-full hidden" style="background:var(--B)">0</span>
      </button>
    </div>
  </div>

  <!-- Mobile search bar -->
  <div id="msrch" class="hidden px-4 pb-3 md:hidden">
    <div class="srch-wrap">
      <i class="fas fa-search srch-icon"></i>
      <input type="search" id="sh-mob" placeholder="Rechercher…" class="srch hasi"
             oninput="doSearch(this.value)" onkeydown="if(event.key==='Escape'){clrSearch();toggleMSrch()}">
      <button class="srch-clr" id="sc-mob" onclick="clrSearch()"><i class="fas fa-times-circle"></i></button>
    </div>
  </div>

  <!-- Categories nav -->
  <nav style="background:rgba(90,25,48,.03);border-top:1px solid var(--border)">
    <div class="max-w-7xl mx-auto px-4">
      <div class="flex overflow-x-auto noscroll gap-0.5 py-2 md:justify-center">
        <a href="index.php"
           class="nl <?= ($current_category==='all'&&!$current_subcategory)?'act':'' ?> px-4 md:px-6 py-2 rounded-lg text-[12px] md:text-[13px] font-semibold whitespace-nowrap transition" style="color:var(--tx2)">
          Tous les produits
        </a>
        <?php foreach($cats as $cat): ?>
        <a href="?cat=<?= $cat['slug'] ?>"
           class="nl <?= ($current_category===$cat['slug']&&!$current_subcategory)?'act':'' ?> px-4 md:px-6 py-2 rounded-lg text-[12px] md:text-[13px] font-semibold whitespace-nowrap transition" style="color:var(--tx2)">
          <?= htmlspecialchars($cat['name']) ?>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </nav>

  <!-- Subcategories -->
  <?php if(!empty($current_subcategories)): ?>
  <div style="background:var(--surf);border-top:1px solid var(--border);box-shadow:0 2px 12px rgba(90,25,48,.04)">
    <div class="max-w-7xl mx-auto px-4">
      <div class="flex items-center gap-1 py-2.5 overflow-x-auto noscroll">
        <span class="text-[10px] font-semibold uppercase tracking-[.12em] hidden lg:flex items-center gap-2 mr-3" style="color:var(--tx3)">
          <i class="fas fa-circle text-[7px]"></i>Explorer
        </span>
        <span class="w-px h-5 hidden lg:block mr-3" style="background:var(--border)"></span>
        <?php foreach($current_subcategories as $sc):
          $cs=$pdo->prepare("SELECT COUNT(*) FROM products WHERE subcategory_id=? AND status='published'");
          $cs->execute([$sc['id']]); $cnt=$cs->fetchColumn();
          $ia=($current_subcategory??'')===$sc['slug'];
        ?>
        <a href="?subcat=<?= $sc['slug'] ?>"
           class="sl <?= $ia?'act':'' ?> flex items-center gap-2.5 px-4 md:px-5 py-2 rounded-lg text-[12px] md:text-[13px] font-medium whitespace-nowrap transition" style="color:var(--tx2)">
          <span class="w-1 h-1 rounded-full" style="background:<?= $ia?'rgba(255,255,255,.6)':'rgba(90,25,48,.3)' ?>"></span>
          <?= htmlspecialchars($sc['name']) ?>
          <span class="sbdg text-[10px] font-medium px-2 py-0.5 rounded-full" style="<?= $ia?'background:rgba(255,255,255,.2);color:#fff':'background:rgba(90,25,48,.1);color:rgba(90,25,48,.5)' ?>">
            <?= $cnt ?>
          </span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>
</header>

<!-- ══════════════ BREADCRUMB ══════════════ -->
<div style="background:var(--surf);border-bottom:1px solid var(--border)">
  <div class="max-w-7xl mx-auto px-4 py-3">
    <div class="flex items-center gap-2 text-[12px]" style="color:var(--tx3)">
      <a href="index.php" class="hover:text-burgundy transition" style="--burgundy:#5A1930"><i class="fas fa-home"></i></a>
      <?php if($current_subcategory):
        $cn=''; $cs='';
        foreach($subcats as $sc){ if($sc['slug']===$current_subcategory){ $cn=$sc['cat_name'];$cs=$sc['cat_slug'];break; } }
      ?>
        <span style="color:var(--border)">/</span>
        <a href="?cat=<?= $cs ?>" class="hover:text-burgundy transition"><?= htmlspecialchars($cn) ?></a>
        <span style="color:var(--border)">/</span>
        <span style="color:var(--B);font-weight:600"><?= htmlspecialchars($page_title) ?></span>
      <?php elseif($current_category!=='all'): ?>
        <span style="color:var(--border)">/</span>
        <span style="color:var(--B);font-weight:600"><?= htmlspecialchars($page_title) ?></span>
      <?php else: ?>
        <span style="color:var(--border)">/</span>
        <span>Tous les produits</span>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ══════════════ HERO ══════════════ -->
<section class="hero pt-14 pb-20 px-4">
  <div class="hero-grid"></div>
  <div class="hero-glow1"></div>
  <div class="hero-glow2"></div>
  <div class="max-w-3xl mx-auto text-center relative">
    <div class="w-20 h-20 mx-auto rounded-2xl flex items-center justify-center mb-6" style="animation:floatY 5s ease-in-out infinite;background:rgba(255,255,255,.72);backdrop-filter:blur(10px);border:1px solid rgba(90,25,48,.1);box-shadow:0 10px 40px rgba(90,25,48,.08)">
      <img src="1.png" class="w-14 h-14 object-contain" alt="For you by mb">
    </div>
    <div class="eyebrow">Boutique Officielle</div>
    <h1 class="font-serif italic font-bold text-4xl md:text-6xl mb-4 tracking-tight grad-text">For you by mb</h1>
    <p class="text-base md:text-lg font-light mb-8 max-w-md mx-auto tcursor" id="hero-tag" style="color:var(--tx2);min-height:1.8em"></p>
    <div class="flex items-center justify-center gap-3 flex-wrap">
      <button onclick="document.getElementById('products-section').scrollIntoView({behavior:'smooth'})"
              class="group inline-flex items-center gap-2.5 text-white px-8 py-3.5 rounded-full text-sm font-semibold transition-all hover:shadow-xl relative overflow-hidden"
              style="background:var(--B)" onmouseover="this.style.background='#3D0F1F'" onmouseout="this.style.background='#5A1930'">
        <span>Découvrir la collection</span>
        <i class="fas fa-arrow-right text-sm transition-transform group-hover:translate-x-1"></i>
      </button>
      <button onclick="openWL()"
              class="inline-flex items-center gap-2 px-8 py-3.5 rounded-full text-sm font-semibold transition border-2"
              style="border-color:rgba(90,25,48,.2);color:var(--B)" onmouseover="this.style.background='rgba(90,25,48,.05)'" onmouseout="this.style.background='transparent'">
        <i class="far fa-heart"></i>Mes favoris
      </button>
    </div>
    <div class="mt-12 flex items-center justify-center gap-8 flex-wrap">
      <div class="text-center">
        <div class="font-serif font-bold text-2xl" style="color:var(--B)"><?= count($products) ?>+</div>
        <div class="text-[10px] font-medium uppercase tracking-wider" style="color:var(--tx3)">Produits</div>
      </div>
      <div class="w-px h-8" style="background:var(--border)"></div>
      <div class="text-center">
        <div class="font-serif font-bold text-2xl" style="color:var(--B)">58</div>
        <div class="text-[10px] font-medium uppercase tracking-wider" style="color:var(--tx3)">Wilayas</div>
      </div>
      <div class="w-px h-8" style="background:var(--border)"></div>
      <div class="text-center">
        <div class="font-serif font-bold text-2xl" style="color:var(--B)">100%</div>
        <div class="text-[10px] font-medium uppercase tracking-wider" style="color:var(--tx3)">Sécurisé</div>
      </div>
    </div>
  </div>
</section>

<!-- ══════════════ PRODUCTS ══════════════ -->
<main id="products-section" class="max-w-7xl mx-auto px-4 py-12">
  <!-- Header row -->
  <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
    <div>
      <div class="eyebrow">Collection</div>
      <h2 class="font-serif italic font-bold text-2xl md:text-3xl" style="color:var(--B)"><?= $page_title ?></h2>
      <p class="text-sm mt-1" style="color:var(--tx3)"><span id="pcnt"><?= count($products) ?> produit<?= count($products)>1?'s':'' ?></span></p>
    </div>
    <div class="flex items-center gap-3 flex-wrap">
      <?php if($current_category!=='all'||$current_subcategory): ?>
      <a href="index.php" class="text-[12px] font-medium flex items-center gap-1.5 transition hover:opacity-80" style="color:rgba(90,25,48,.5)">
        <i class="fas fa-arrow-left"></i>Voir tout
      </a>
      <?php endif; ?>
      <select class="srt" id="srt-sel" onchange="doSort(this.value)" aria-label="Trier par">
        <option value="default">Par défaut</option>
        <option value="price-asc">Prix ↑</option>
        <option value="price-desc">Prix ↓</option>
        <option value="name">Nom A-Z</option>
        <option value="promo">Promotions</option>
      </select>
    </div>
  </div>

  <!-- Mobile search in content -->
  <div class="md:hidden mb-6 srch-wrap">
    <i class="fas fa-search srch-icon"></i>
    <input type="search" id="sh-body" placeholder="Rechercher un produit…" class="srch hasi"
           oninput="doSearch(this.value)">
  </div>

  <!-- Grid — JS rendered -->
  <div id="products-grid" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6"></div>

  <!-- Empty state -->
  <div id="empty-st" class="hidden empty">
    <div class="w-16 h-16 mx-auto rounded-full flex items-center justify-center mb-4" style="background:rgba(90,25,48,.05)">
      <i class="fas fa-search text-2xl" style="color:rgba(90,25,48,.18)"></i>
    </div>
    <p class="font-medium mb-2" style="color:var(--tx3)">Aucun produit trouvé</p>
    <p class="text-sm" style="color:var(--tx3)">Essayez un autre terme de recherche</p>
    <button onclick="clrSearch()" class="mt-4 text-sm font-semibold hover:underline" style="color:var(--B)">Réinitialiser</button>
  </div>
</main>

<!-- ══════════════ WISHLIST DRAWER ══════════════ -->
<div id="wl-ov" class="fixed inset-0 bg-black/40 z-40 hidden backdrop-blur-sm" onclick="closeWL()"></div>
<div id="wl-drawer" class="fixed top-0 right-0 w-full max-w-md h-full z-50 transform translate-x-full flex flex-col shadow-2xl" style="background:var(--surf)">
  <div class="px-5 py-4 flex justify-between items-center" style="border-bottom:1px solid var(--border)">
    <h3 class="font-serif font-bold text-lg flex items-center gap-2" style="color:var(--B)">
      <i class="fas fa-heart"></i>Mes Favoris <span id="wl-hdr-cnt" class="text-sm font-normal" style="color:var(--tx3)"></span>
    </h3>
    <button onclick="closeWL()" class="w-8 h-8 rounded-full flex items-center justify-center transition hover:bg-burgundy/5" style="color:var(--tx3)"><i class="fas fa-times"></i></button>
  </div>
  <div id="wl-body" class="flex-1 overflow-y-auto px-4 py-4"></div>
</div>

<!-- ══════════════ PRODUCT DETAIL ══════════════ -->
<div id="product-detail-overlay" class="fixed inset-0 bg-black/60 z-50 hidden items-center justify-center p-2 backdrop-blur-sm">
  <div class="mcont rounded-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col relative" style="background:var(--surf)">
    <button onclick="closeProductDetail()" class="absolute top-3 right-3 z-10 w-9 h-9 rounded-full flex items-center justify-center transition text-lg" style="background:var(--surf);box-shadow:var(--sh1);color:var(--tx3)">
      <i class="fas fa-times"></i>
    </button>
    <div class="grid grid-cols-1 md:grid-cols-2 overflow-y-auto flex-1">
      <div class="p-4 md:p-6">
        <div class="relative aspect-square rounded-xl overflow-hidden mb-3 cursor-pointer" onclick="openLightbox()" style="background:var(--cream)">
          <img id="detail-main-image" src="" class="w-full h-full object-cover" style="transition:opacity .3s">
          <div class="absolute bottom-3 right-3 px-3 py-1.5 rounded-full text-[10px] font-medium text-white" style="background:rgba(0,0,0,.4);backdrop-filter:blur(8px)">
            <i class="fas fa-search-plus mr-1"></i>Zoom
          </div>
        </div>
        <div class="flex gap-2 overflow-x-auto noscroll" id="detail-gallery"></div>
      </div>
      <div class="p-4 md:p-6 flex flex-col">
        <span class="text-[10px] font-semibold uppercase tracking-wider mb-1" style="color:rgba(90,25,48,.4)" id="detail-cat"></span>
        <h2 class="font-serif font-bold text-xl md:text-2xl mb-2" id="detail-name" style="color:var(--tx)"></h2>
        <div class="mb-3" id="detail-prices"></div>
        <div class="mb-3" id="detail-sizes"></div>
        <div class="mb-3" id="detail-colors"></div>
        <div class="mb-3">
          <span class="text-[11px] font-semibold block mb-1.5" style="color:var(--tx3)">Quantité</span>
          <div class="flex items-center gap-3">
            <button type="button" onclick="changeQuantity(-1)" class="qbtn">−</button>
            <span id="detail-quantity" class="qdsp">1</span>
            <button type="button" onclick="changeQuantity(1)" class="qbtn">+</button>
          </div>
        </div>
        <div class="text-sm leading-relaxed whitespace-pre-line flex-1 overflow-y-auto max-h-40 pt-3 mt-2" id="detail-desc" style="border-top:1px solid var(--border);color:var(--tx2)"></div>
        <div class="pt-4">
          <button onclick="addToCartFromDetail()" class="w-full text-white py-3.5 rounded-xl text-sm font-semibold transition" style="background:var(--B)" onmouseover="this.style.background='#3D0F1F'" onmouseout="this.style.background='#5A1930'">
            <i class="fas fa-shopping-bag mr-2"></i>Ajouter au panier
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════ LIGHTBOX ══════════════ -->
<div id="lightbox" class="fixed inset-0 bg-black/92 z-[60] hidden items-center justify-center p-4" onclick="closeLightbox()">
  <button onclick="closeLightbox()" class="absolute top-4 right-4 text-white/60 hover:text-white text-3xl transition"><i class="fas fa-times"></i></button>
  <img id="lightbox-img" src="" class="max-w-full max-h-[90vh] object-contain rounded-xl">
</div>

<!-- ══════════════ CART DRAWER ══════════════ -->
<div id="cart-overlay" class="fixed inset-0 bg-black/40 z-40 hidden backdrop-blur-sm" onclick="closeCart()"></div>
<div id="cart-drawer" class="fixed top-0 right-0 w-full max-w-md h-full z-50 transform translate-x-full transition duration-300 flex flex-col shadow-2xl" style="background:var(--surf)">
  <div class="px-5 py-4 flex justify-between items-center" style="border-bottom:1px solid var(--border)">
    <h3 class="font-serif font-bold text-lg flex items-center gap-2" style="color:var(--B)">
      <i class="fas fa-shopping-bag"></i>Panier
    </h3>
    <button onclick="closeCart()" class="w-8 h-8 rounded-full flex items-center justify-center transition hover:bg-burgundy/5" style="color:var(--tx3)"><i class="fas fa-times"></i></button>
  </div>
  <div id="cart-body" class="flex-1 overflow-y-auto px-4 py-4"></div>
  <div id="cart-footer" class="p-4 hidden" style="border-top:1px solid var(--border);background:var(--cream)"></div>
</div>

<!-- ══════════════ CHECKOUT ══════════════ -->
<div id="checkout-overlay" class="fixed inset-0 bg-black/60 z-50 hidden items-center justify-center p-2 backdrop-blur-sm">
  <div class="mcont rounded-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto p-6" style="background:var(--surf)">
    <div class="flex items-center gap-3 mb-5">
      <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:rgba(90,25,48,.05)">
        <img src="1.png" class="w-7 h-7 object-contain">
      </div>
      <h3 class="font-serif font-bold text-xl" style="color:var(--B)">Finaliser la commande</h3>
    </div>
    <form id="checkout-form" class="space-y-3">
      <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-[11px] font-semibold block mb-1" style="color:var(--tx3)">Nom</label>
          <input type="text" name="lastname" id="c-lastname" required class="w-full px-3.5 py-2.5 border rounded-xl text-sm focus:outline-none focus:ring-2 transition" style="border-color:var(--border);background:var(--card);color:var(--tx)">
        </div>
        <div>
          <label class="text-[11px] font-semibold block mb-1" style="color:var(--tx3)">Prénom</label>
          <input type="text" name="firstname" id="c-firstname" required class="w-full px-3.5 py-2.5 border rounded-xl text-sm focus:outline-none focus:ring-2 transition" style="border-color:var(--border);background:var(--card);color:var(--tx)">
        </div>
      </div>
      <div>
        <label class="text-[11px] font-semibold block mb-1" style="color:var(--tx3)">Téléphone</label>
        <input type="tel" name="phone" id="c-phone" required class="w-full px-3.5 py-2.5 border rounded-xl text-sm focus:outline-none focus:ring-2 transition" style="border-color:var(--border);background:var(--card);color:var(--tx)">
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-[11px] font-semibold block mb-1" style="color:var(--tx3)">Wilaya</label>
          <select name="wilaya" id="c-wilaya" required onchange="loadCommunes(this.value)" class="w-full px-3.5 py-2.5 border rounded-xl text-sm focus:outline-none transition" style="border-color:var(--border);background:var(--card);color:var(--tx)">
            <option value="">Choisir</option>
            <?php foreach($wilayas as $w): ?>
            <option value="<?= $w['id'] ?>"><?= $w['code'] ?> - <?= htmlspecialchars($w['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="text-[11px] font-semibold block mb-1" style="color:var(--tx3)">Commune</label>
          <select name="commune" id="c-commune" required class="w-full px-3.5 py-2.5 border rounded-xl text-sm focus:outline-none transition" style="border-color:var(--border);background:var(--card);color:var(--tx)">
            <option value="">Sélectionner</option>
          </select>
        </div>
      </div>
      <div>
        <label class="text-[11px] font-semibold block mb-1" style="color:var(--tx3)">Adresse complète</label>
        <textarea name="address" id="c-address" required rows="3" class="w-full px-3.5 py-2.5 border rounded-xl text-sm focus:outline-none focus:ring-2 transition" style="border-color:var(--border);background:var(--card);color:var(--tx)"></textarea>
      </div>
      <div>
        <label class="text-[11px] font-semibold block mb-1" style="color:var(--tx3)">Mode de livraison</label>
        <div class="grid grid-cols-2 gap-2 mt-1">
          <label class="flex items-center gap-2 p-2.5 border rounded-xl cursor-pointer text-sm transition hover:border-burgundy/20" style="border-color:var(--border);color:var(--tx)">
            <input type="radio" name="delivery" value="domicile" checked onchange="updateShippingFromWilaya()" style="accent-color:#5A1930">
            Domicile (<span id="domicile-price">--</span> DA)
          </label>
          <label class="flex items-center gap-2 p-2.5 border rounded-xl cursor-pointer text-sm transition hover:border-burgundy/20" id="bureau-option" style="border-color:var(--border);color:var(--tx)">
            <input type="radio" name="delivery" value="bureau" onchange="updateShippingFromWilaya()" style="accent-color:#5A1930">
            Bureau (<span id="bureau-price">--</span> DA)
          </label>
        </div>
        <p id="shipping-unavailable" class="text-[10px] text-rose-500 hidden mt-1">Non disponible pour cette zone</p>
      </div>
      <div class="p-3.5 rounded-xl text-sm flex items-center gap-2" style="background:rgba(16,185,129,.06);color:#065F46;border:1px solid rgba(16,185,129,.15)">
        <i class="fas fa-shield-alt"></i>Paiement sécurisé à la livraison — Cash on delivery
      </div>
      <div class="flex justify-between items-center font-semibold pt-2" style="border-top:1px solid var(--border)">
        <span style="color:var(--tx2)">Total commande</span>
        <span id="checkout-total" class="text-xl font-serif italic" style="color:var(--B)">0 DA</span>
      </div>
      <button type="submit" id="order-btn" class="w-full text-white py-3.5 rounded-xl font-semibold transition" style="background:var(--B)" onmouseover="this.style.background='#3D0F1F'" onmouseout="this.style.background='#5A1930'">
        <i class="fas fa-check mr-2"></i>Confirmer la commande
      </button>
      <button type="button" onclick="closeCheckout()" class="w-full py-2.5 rounded-xl text-sm font-medium transition" style="background:rgba(45,45,45,.06);color:var(--tx3)">Annuler</button>
    </form>
  </div>
</div>

<!-- ══════════════ TOAST ══════════════ -->
<div id="toast" class="fixed bottom-6 left-1/2 -translate-x-1/2 text-white px-5 py-3 rounded-xl text-sm z-[300] translate-y-24 opacity-0 transition-all duration-500 shadow-2xl">
  <i id="toast-ico" class="fas fa-check-circle text-green-400"></i>
  <span id="toast-msg"></span>
</div>

<!-- ══════════════ FOOTER ══════════════ -->
<footer class="pt-14 pb-8 mt-16" style="background:#3D0F1F">
  <div class="max-w-7xl mx-auto px-4">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-8 text-center md:text-left">
      <div>
        <div class="flex items-center justify-center md:justify-start gap-3 mb-3">
          <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:rgba(255,255,255,.05)">
            <img src="1.png" class="w-7 h-7 object-contain opacity-80">
          </div>
          <span class="font-serif italic font-bold text-xl text-white">For you by mb</span>
        </div>
        <p class="text-sm font-light" style="color:rgba(255,255,255,.3)">© <?= date('Y') ?> — Tous droits réservés</p>
        <p class="text-[11px] mt-1" style="color:rgba(255,255,255,.2)">Boutique officielle · Ain Benian, Alger</p>
      </div>
      <div>
        <h4 class="text-white font-semibold text-sm mb-3">Navigation</h4>
        <ul class="space-y-1.5 text-sm" style="color:rgba(255,255,255,.45)">
          <li><a href="index.php" class="hover:text-white transition">Accueil</a></li>
          <?php foreach(array_slice($cats,0,4) as $cat): ?>
          <li><a href="?cat=<?= $cat['slug'] ?>" class="hover:text-white transition"><?= htmlspecialchars($cat['name']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div>
        <h4 class="text-white font-semibold text-sm mb-3">Contact</h4>
        <div class="space-y-1.5 text-sm" style="color:rgba(255,255,255,.45)">
          <p class="flex items-center justify-center md:justify-start gap-2">
            <i class="fas fa-phone" style="color:rgba(255,255,255,.25)"></i>
            <a href="tel:0552800684" class="hover:text-white transition">0552800684</a>
          </p>
          <p class="flex items-center justify-center md:justify-start gap-2">
            <i class="fas fa-phone" style="color:rgba(255,255,255,.25)"></i>
            <a href="tel:0797336257" class="hover:text-white transition">0797336257</a>
          </p>
          <a href="<?= $map_url ?>" target="_blank" class="inline-flex items-center gap-2 hover:text-white transition mt-1">
            <i class="fas fa-map-marker-alt" style="color:rgba(255,255,255,.25)"></i>Ain Benian, Alger
          </a>
        </div>
      </div>
      <div>
        <h4 class="text-white font-semibold text-sm mb-3">Suivez-nous</h4>
        <div class="flex justify-center md:justify-start gap-3 text-xl mb-4">
          <a href="<?= $fb ?: '#' ?>" target="_blank" class="hover:text-white transition" style="color:rgba(255,255,255,.3)"><i class="fab fa-facebook"></i></a>
          <a href="<?= $ig ?: '#' ?>" target="_blank" class="hover:text-white transition" style="color:rgba(255,255,255,.3)"><i class="fab fa-instagram"></i></a>
          <a href="<?= $tk ?: '#' ?>" target="_blank" class="hover:text-white transition" style="color:rgba(255,255,255,.3)"><i class="fab fa-tiktok"></i></a>
        </div>
        <div class="text-[11px] space-y-1" style="color:rgba(255,255,255,.22)">
          <div><i class="fas fa-truck mr-1"></i>Livraison partout en Algérie</div>
          <div><i class="fas fa-lock mr-1"></i>Paiement à la livraison</div>
        </div>
      </div>
    </div>
    <div class="mt-8 pt-6 text-center text-[11px]" style="border-top:1px solid rgba(255,255,255,.05);color:rgba(255,255,255,.18)">
      Conçu avec soin — For you by mb ✦
    </div>
  </div>
</footer>

<!-- ══════════════ SCRIPTS ══════════════ -->
<script>
/* ──────── DATA ──────── */
const productsData = <?php echo json_encode($products, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;
let cart=[], wishlist=[], selectedSizes={}, selectedColors={};
let shippingFee=0, currentDetailProduct=null, currentQuantity=1;
let curSort='default', curSearch='';
const shippingRates = <?php echo json_encode($shippingRates); ?>;
const productImages = {};
productsData.forEach(p=>{
  productImages[p.id]={};
  (p.images||[]).forEach(img=>{ productImages[p.id][img.color_name]=img.image_path; });
});

/* ──────── HELPERS ──────── */
function esc(s){ const d=document.createElement('div'); d.appendChild(document.createTextNode(s||'')); return d.innerHTML; }
function isAR(t){ return /[\u0600-\u06FF]/.test(t); }

/* ──────── PROGRESS BAR ──────── */
const pgEl=document.getElementById('pgbar');
let pgV=0;
const pgT=setInterval(()=>{ pgV+=Math.random()*12; if(pgV>=88){pgV=88;clearInterval(pgT);} pgEl.style.width=pgV+'%'; },110);
window.addEventListener('load',()=>{ clearInterval(pgT); pgEl.style.width='100%'; setTimeout(()=>{pgEl.style.opacity='0';},420); setTimeout(()=>{pgEl.style.display='none';},820); });

/* ──────── DARK MODE ──────── */
function toggleDark(){
  const h=document.documentElement;
  const d=h.getAttribute('data-theme')==='dark';
  h.setAttribute('data-theme',d?'light':'dark');
  localStorage.setItem('foryou_theme',d?'light':'dark');
}
(function(){ const s=localStorage.getItem('foryou_theme'); if(s==='dark') document.documentElement.setAttribute('data-theme','dark'); })();

/* ──────── TYPING ANIM ──────── */
const TAGS=['Beauté, bien-être et mode féminine','Livraison partout en Algérie 🚚','Collections exclusives pour vous','Élégance et style au quotidien'];
let tIdx=0,cIdx=0,tDel=false;
function typeAnim(){
  const el=document.getElementById('hero-tag'); if(!el) return;
  const cur=TAGS[tIdx];
  if(tDel){el.textContent=cur.substring(0,cIdx-1);cIdx--;}
  else{el.textContent=cur.substring(0,cIdx+1);cIdx++;}
  let d=tDel?38:68;
  if(!tDel&&cIdx===cur.length){d=2700;tDel=true;}
  else if(tDel&&cIdx===0){tDel=false;tIdx=(tIdx+1)%TAGS.length;d=380;}
  setTimeout(typeAnim,d);
}
document.addEventListener('DOMContentLoaded',()=>{ setTimeout(typeAnim,900); });

/* ──────── SCROLL ──────── */
const hdr=document.getElementById('hdr'), btt=document.getElementById('btt');
window.addEventListener('scroll',()=>{
  if(window.scrollY>50) hdr.classList.add('scrolled'); else hdr.classList.remove('scrolled');
  if(window.scrollY>400) btt.classList.add('show'); else btt.classList.remove('show');
},{passive:true});

/* ──────── SEARCH ──────── */
function doSearch(v){
  curSearch=v.toLowerCase().trim();
  ['sh-desk','sh-mob','sh-body'].forEach(id=>{ const el=document.getElementById(id); if(el&&el.value!==v) el.value=v; });
  ['sc-desk','sc-mob'].forEach(id=>{ const el=document.getElementById(id); if(el) el.style.display=curSearch?'block':'none'; });
  renderProducts();
}
function clrSearch(){
  curSearch='';
  ['sh-desk','sh-mob','sh-body'].forEach(id=>{ const el=document.getElementById(id); if(el) el.value=''; });
  ['sc-desk','sc-mob'].forEach(id=>{ const el=document.getElementById(id); if(el) el.style.display='none'; });
  renderProducts();
}
function toggleMSrch(){
  const el=document.getElementById('msrch');
  el.classList.toggle('hidden');
  if(!el.classList.contains('hidden')) document.getElementById('sh-mob').focus();
}
function doSort(v){ curSort=v; renderProducts(); }

/* ──────── RENDER PRODUCTS ──────── */
function getProducts(){
  let arr=[...productsData];
  if(curSearch) arr=arr.filter(p=>(p.name||'').toLowerCase().includes(curSearch)||(p.cat_name||'').toLowerCase().includes(curSearch)||(p.sub_name||'').toLowerCase().includes(curSearch));
  switch(curSort){
    case 'price-asc': arr.sort((a,b)=>a.price-b.price); break;
    case 'price-desc': arr.sort((a,b)=>b.price-a.price); break;
    case 'name': arr.sort((a,b)=>(a.name||'').localeCompare(b.name||'','fr')); break;
    case 'promo': arr=arr.filter(p=>p.old_price&&p.old_price>p.price).concat(arr.filter(p=>!p.old_price||p.old_price<=p.price)); break;
    default: arr.sort((a,b)=>(b.featured?1:0)-(a.featured?1:0));
  }
  return arr;
}

function cardHTML(p, i){
  const disc=p.old_price&&p.old_price>p.price?Math.round((1-p.price/p.old_price)*100):0;
  const wOn=wishlist.includes(p.id);
  return `<div class="pc" data-pid="${p.id}" style="animation-delay:${Math.min(i*.07,.55)}s">
    <div class="pimg-w" onclick="openProductDetail(${p.id})">
      <img src="${esc(p.default_image||'')}" loading="lazy" alt="${esc(p.name)}" class="pimg">
      ${disc>0?`<div class="dbdg">-${disc}%</div>`:''}
      <div class="pbdg">${p.old_price&&p.old_price>p.price?`<span class="op">${p.old_price.toLocaleString('fr-FR')}</span>`:''}
        <span class="cp">${p.price.toLocaleString('fr-FR')} DA</span></div>
      <button class="wbtn${wOn?' on':''}" data-pid="${p.id}" onclick="event.stopPropagation();toggleWL(${p.id})" aria-label="Favoris">
        <i class="${wOn?'fas':'far'} fa-heart"></i></button>
      <div class="pov">
        <button class="qvbtn" onclick="event.stopPropagation();openProductDetail(${p.id})">
          <i class="fas fa-eye"></i>Aperçu rapide</button>
      </div>
    </div>
    <div class="cbody">
      <div class="cmeta">
        <span class="ccat">${esc(p.cat_name||'')}</span>
        ${p.sub_name?`<span class="csep">·</span><span class="csub">${esc(p.sub_name)}</span>`:''}
      </div>
      <h3 class="cname">${esc(p.name)}</h3>
      <div class="cprice-row">
        <span class="cprice">${p.price.toLocaleString('fr-FR')} DA</span>
        ${p.old_price&&p.old_price>p.price?`<span class="cold">${p.old_price.toLocaleString('fr-FR')} DA</span><span class="cdpill">-${disc}%</span>`:''}
      </div>
      ${p.has_options
        ?`<button class="cbtn cbtn-opt" onclick="openProductDetail(${p.id})"><i class="fas fa-sliders-h"></i>Choisir</button>`
        :`<button class="cbtn cbtn-add" onclick="addToCart(${p.id},1,this)"><i class="fas fa-shopping-bag"></i>Ajouter</button>`
      }
    </div>
  </div>`;
}

function renderProducts(){
  const grid=document.getElementById('products-grid');
  const empty=document.getElementById('empty-st');
  const pcnt=document.getElementById('pcnt');
  const prods=getProducts();
  if(prods.length===0){
    grid.innerHTML=''; empty.classList.remove('hidden');
    pcnt.textContent='0 produit';
  } else {
    empty.classList.add('hidden');
    pcnt.textContent=`${prods.length} produit${prods.length>1?'s':''}`;
    grid.innerHTML=prods.map((p,i)=>cardHTML(p,i)).join('');
    initTilt();
    initRipples();
  }
}

/* ──────── 3D TILT ──────── */
function initTilt(){
  if(!window.matchMedia('(hover:hover)').matches) return;
  document.querySelectorAll('.pc').forEach(c=>{
    c.addEventListener('mousemove',e=>{
      const r=c.getBoundingClientRect();
      const rx=((e.clientY-r.top)/r.height-.5)*-12;
      const ry=((e.clientX-r.left)/r.width-.5)*12;
      c.style.transform=`perspective(1200px) rotateX(${rx}deg) rotateY(${ry}deg) translateZ(8px)`;
      c.style.transition='transform 0.08s ease';
    });
    c.addEventListener('mouseleave',()=>{ c.style.transform=''; c.style.transition='transform 0.5s ease'; });
  });
}

/* ──────── RIPPLE ──────── */
function initRipples(){
  document.querySelectorAll('.cbtn').forEach(btn=>{
    btn.addEventListener('click',e=>{
      const rpl=document.createElement('span');
      rpl.className='rpl';
      const r=btn.getBoundingClientRect();
      rpl.style.left=(e.clientX-r.left)+'px';
      rpl.style.top=(e.clientY-r.top)+'px';
      btn.appendChild(rpl);
      setTimeout(()=>rpl.remove(),620);
    });
  });
}

/* ──────── PETAL FX ──────── */
function spawnPetals(originEl){
  const ctn=document.getElementById('petals');
  const cartEl=document.getElementById('cart-btn');
  if(!ctn||!cartEl||!originEl) return;
  const oR=originEl.getBoundingClientRect();
  const cR=cartEl.getBoundingClientRect();
  const COLORS=['#D394B6','#E5C8DD','#8B3A5A','#F2D4E7'];
  for(let i=0;i<8;i++){
    const p=document.createElement('div'); p.className='petal';
    p.style.left=(oR.left+oR.width/2)+'px';
    p.style.top=(oR.top+oR.height/2)+'px';
    p.style.background=COLORS[i%COLORS.length];
    const dx=cR.left-oR.left+(Math.random()-.5)*50;
    const dy=cR.top-oR.top+(Math.random()-.5)*20;
    p.style.setProperty('--px',dx+'px');
    p.style.setProperty('--py',dy+'px');
    p.style.setProperty('--pr',(Math.random()*540-270)+'deg');
    p.style.setProperty('--pd',(i*.04)+'s');
    ctn.appendChild(p);
    setTimeout(()=>p.remove(),820);
  }
}

/* ──────── TOAST ──────── */
function showToast(msg,type='ok'){
  const el=document.getElementById('toast');
  const ico=document.getElementById('toast-ico');
  document.getElementById('toast-msg').textContent=msg;
  ico.className='fas ';
  if(type==='heart') ico.className+='fa-heart' ; else if(type==='err') ico.className+='fa-exclamation-circle';
  else if(type==='info') ico.className+='fa-info-circle'; else ico.className+='fa-check-circle';
  ico.style.color=type==='heart'?'#E53E3E':type==='err'?'#FC8181':type==='info'?'#63B3ED':'#68D391';
  el.classList.remove('translate-y-24','opacity-0');
  el.classList.add('translate-y-0','opacity-100');
  clearTimeout(el._t);
  el._t=setTimeout(()=>{ el.classList.add('translate-y-24','opacity-0'); el.classList.remove('translate-y-0','opacity-100'); },3000);
}

/* ──────── WISHLIST ──────── */
function loadWL(){ try{ const s=localStorage.getItem('foryou_wishlist'); if(s) wishlist=JSON.parse(s); }catch(e){wishlist=[];} updWLBadge(); }
function saveWL(){ try{ localStorage.setItem('foryou_wishlist',JSON.stringify(wishlist)); }catch(e){} }
function toggleWL(pid){
  const i=wishlist.indexOf(pid);
  if(i===-1){ wishlist.push(pid); showToast((productsData.find(p=>p.id===pid)?.name||'')+' ajouté aux favoris','heart'); }
  else{ wishlist.splice(i,1); showToast('Retiré des favoris','info'); }
  saveWL(); updWLBadge();
  document.querySelectorAll(`.wbtn[data-pid="${pid}"]`).forEach(b=>{
    b.classList.toggle('on',wishlist.includes(pid));
    b.querySelector('i').className=wishlist.includes(pid)?'fas fa-heart':'far fa-heart';
  });
  if(!document.getElementById('wl-drawer').classList.contains('translate-x-full')) renderWL();
}
function updWLBadge(){
  const n=wishlist.length;
  const bdg=document.getElementById('wl-cnt');
  const ico=document.getElementById('wl-icon');
  if(n>0){ bdg.textContent=n; bdg.classList.remove('hidden'); ico.className='fas fa-heart text-lg'; ico.style.color='#E53E3E'; }
  else{ bdg.classList.add('hidden'); ico.className='far fa-heart text-lg'; ico.style.color='var(--B)'; }
}
function openWL(){
  document.getElementById('wl-drawer').classList.remove('translate-x-full');
  document.getElementById('wl-ov').classList.remove('hidden');
  renderWL(); document.body.style.overflow='hidden';
}
function closeWL(){
  document.getElementById('wl-drawer').classList.add('translate-x-full');
  document.getElementById('wl-ov').classList.add('hidden');
  document.body.style.overflow='';
}
function renderWL(){
  const body=document.getElementById('wl-body');
  const hdr=document.getElementById('wl-hdr-cnt');
  const wished=productsData.filter(p=>wishlist.includes(p.id));
  hdr.textContent=`(${wished.length})`;
  if(!wished.length){
    body.innerHTML=`<div class="empty"><div class="w-12 h-12 mx-auto rounded-full flex items-center justify-center mb-3" style="background:rgba(90,25,48,.05)"><i class="far fa-heart text-xl" style="color:rgba(90,25,48,.18)"></i></div><p class="font-medium" style="color:var(--tx3)">Aucun favori</p><p class="text-sm mt-1" style="color:var(--tx3)">Cliquez sur ♥ pour ajouter des produits</p></div>`;
    return;
  }
  body.innerHTML=wished.map(p=>`
    <div class="flex gap-3 p-3 rounded-xl mb-2" style="background:var(--cream)">
      <img src="${esc(p.default_image||'')}" class="w-14 h-14 rounded-lg object-cover flex-shrink-0" alt="${esc(p.name)}">
      <div class="flex-1 min-w-0">
        <h4 class="font-serif font-semibold text-sm truncate" style="color:var(--tx)">${esc(p.name)}</h4>
        <p class="text-xs font-bold mt-0.5" style="color:var(--B)">${p.price.toLocaleString('fr-FR')} DA</p>
        <div class="flex gap-2 mt-2">
          <button onclick="closeWL();openProductDetail(${p.id})" class="text-[10px] font-semibold px-3 py-1.5 rounded-lg text-white" style="background:var(--B)">Voir</button>
          <button onclick="toggleWL(${p.id})" class="text-[10px] font-medium px-3 py-1.5 rounded-lg" style="background:rgba(229,58,58,.1);color:#E53E3E">Retirer</button>
        </div>
      </div>
    </div>`).join('');
}

/* ──────── CART ──────── */
function loadCart(){ try{ const s=localStorage.getItem('foryou_cart'); if(s){cart=JSON.parse(s);} updBadge(); }catch(e){cart=[];} }
function saveCart(){ try{ localStorage.setItem('foryou_cart',JSON.stringify(cart)); }catch(e){} }
function updBadge(){
  const n=cart.reduce((s,i)=>s+i.qty,0);
  const b=document.getElementById('cart-badge');
  if(n>0){ b.textContent=n; b.classList.remove('hidden'); b.classList.add('bpop'); setTimeout(()=>b.classList.remove('bpop'),300); }
  else b.classList.add('hidden');
}
function openCart(){ document.getElementById('cart-drawer').classList.remove('translate-x-full'); document.getElementById('cart-overlay').classList.remove('hidden'); renderCart(); document.body.style.overflow='hidden'; }
function closeCart(){ document.getElementById('cart-drawer').classList.add('translate-x-full'); document.getElementById('cart-overlay').classList.add('hidden'); document.body.style.overflow=''; }
function renderCart(){
  const body=document.getElementById('cart-body'), footer=document.getElementById('cart-footer');
  if(!cart.length){
    body.innerHTML=`<div class="empty"><div class="w-12 h-12 mx-auto rounded-full flex items-center justify-center mb-3" style="background:rgba(90,25,48,.05)"><i class="fas fa-shopping-bag text-xl" style="color:rgba(90,25,48,.18)"></i></div><p class="font-medium" style="color:var(--tx3)">Votre panier est vide</p></div>`;
    footer.classList.add('hidden'); return;
  }
  const sub=cart.reduce((s,i)=>s+(i.price*i.qty),0);
  body.innerHTML=cart.map((item,idx)=>{
    let det='';
    if(item.size) det+=`<span class="text-[9px]" style="color:var(--tx3)">Taille: ${esc(item.size)}</span> `;
    if(item.color) det+=`<span class="text-[9px]" style="color:var(--tx3)">Couleur: ${esc(item.color)}</span>`;
    return `<div class="flex gap-3 p-3 rounded-xl mb-2" style="background:var(--cream);animation:revealUp .3s ease ${idx*.05}s both">
      <img src="${esc(item.image)}" class="w-14 h-14 rounded-lg object-cover flex-shrink-0">
      <div class="flex-1 min-w-0">
        <h4 class="font-semibold text-sm truncate" style="color:var(--tx)">${esc(item.name)}</h4>
        <div>${det}</div>
        <div class="flex justify-between items-center mt-1">
          <span class="font-bold text-sm" style="color:var(--B)">${(item.price*item.qty).toLocaleString('fr-FR')} DA</span>
          <span class="text-[10px]" style="color:var(--tx3)">Qté: ${item.qty}</span>
        </div>
      </div>
      <button onclick="removeFromCart('${item.cartId}')" class="hover:text-red-500 transition self-start mt-1 flex-shrink-0" style="color:var(--tx3)"><i class="fas fa-trash-alt text-xs"></i></button>
    </div>`;
  }).join('');
  footer.classList.remove('hidden');
  footer.innerHTML=`<div class="flex justify-between font-semibold mb-2"><span style="color:var(--tx2)">Sous-total</span><span style="color:var(--B)">${sub.toLocaleString('fr-FR')} DA</span></div>
    <p class="text-[10px] mb-3" style="color:var(--tx3)">Frais de livraison calculés à la commande</p>
    <button onclick="openCheckout()" class="w-full text-white py-3 rounded-xl text-sm font-semibold transition" style="background:var(--B)" onmouseover="this.style.background='#3D0F1F'" onmouseout="this.style.background='#5A1930'">
      <i class="fas fa-credit-card mr-2"></i>Commander</button>`;
}
function removeFromCart(id){ cart=cart.filter(i=>i.cartId!==id); updBadge(); saveCart(); renderCart(); }

/* ──────── ADD TO CART ──────── */
function addToCart(pid, qty=1, originEl=null){
  const p=productsData.find(x=>x.id===pid); if(!p) return;
  const size=selectedSizes[pid]||(p.sizes?.length?p.sizes[0]:null);
  const color=selectedColors[pid]||(p.colors?.length?p.colors[0]:null);
  let img=p.default_image;
  if(color&&productImages[pid]?.[color]) img=productImages[pid][color];
  const cid=pid+'-'+(size||'')+'-'+(color||'');
  const ex=cart.find(x=>x.cartId===cid);
  if(ex) ex.qty+=qty; else cart.push({cartId:cid,product_id:p.id,name:p.name,price:p.price,image:img,size,color,qty});
  updBadge(); saveCart();
  showToast(p.name+(qty>1?` (x${qty})`:'')+ ' ajouté au panier');
  const ci=document.getElementById('cart-icon');
  ci.classList.remove('cartShk'); void ci.offsetWidth; ci.style.animation='cartShake .4s ease';
  setTimeout(()=>ci.style.animation='',450);
  if(originEl) spawnPetals(originEl);
}
function addToCartFromDetail(){
  if(!currentDetailProduct) return;
  if(currentDetailProduct.sizes?.length&&!selectedSizes[currentDetailProduct.id]){ showToast('Choisissez une taille','err'); return; }
  if(currentDetailProduct.colors?.length&&!selectedColors[currentDetailProduct.id]){ showToast('Choisissez une couleur','err'); return; }
  addToCart(currentDetailProduct.id,currentQuantity); closeProductDetail();
  currentQuantity=1; document.getElementById('detail-quantity').textContent='1';
}

/* ──────── SELECTORS ──────── */
function selectSize(pid,size){
  selectedSizes[pid]=size;
  document.querySelectorAll('#product-detail-overlay .sz-'+pid).forEach(b=>{ b.classList.remove('bg-burgundy','text-white','border-burgundy'); b.classList.add('border-burgundy/10','text-charcoal/60'); });
  const b=document.querySelector('#product-detail-overlay .sz-'+pid+'[data-size="'+size+'"]');
  if(b){ b.classList.add('bg-burgundy','text-white','border-burgundy'); b.classList.remove('border-burgundy/10','text-charcoal/60'); }
}
function selectColor(pid,color){
  selectedColors[pid]=color;
  document.querySelectorAll('#product-detail-overlay .cl-'+pid).forEach(b=>{ b.classList.remove('bg-burgundy','text-white','border-burgundy'); b.classList.add('border-burgundy/10','text-charcoal/60'); });
  const b=document.querySelector('#product-detail-overlay .cl-'+pid+'[data-color="'+color+'"]');
  if(b){ b.classList.add('bg-burgundy','text-white','border-burgundy'); b.classList.remove('border-burgundy/10','text-charcoal/60'); }
  if(productImages[pid]?.[color]){
    const m=document.getElementById('detail-main-image');
    if(m){ m.style.opacity='0'; setTimeout(()=>{m.src=productImages[pid][color];m.style.opacity='1';},150); }
    document.querySelectorAll('#product-detail-overlay .gt').forEach(t=>{ t.classList.remove('active','border-burgundy'); t.classList.add('border-burgundy/10','opacity-60'); });
    const th=document.querySelector('#product-detail-overlay .gt[src="'+productImages[pid][color]+'"]');
    if(th){ th.classList.add('active','border-burgundy'); th.classList.remove('border-burgundy/10','opacity-60'); }
  }
}
function changeQuantity(d){ currentQuantity=Math.max(1,currentQuantity+d); document.getElementById('detail-quantity').textContent=currentQuantity; }

/* ──────── PRODUCT MODAL ──────── */
function openProductDetail(pid){
  currentDetailProduct=productsData.find(x=>x.id===pid); if(!currentDetailProduct) return;
  currentQuantity=1; document.getElementById('detail-quantity').textContent='1';
  document.getElementById('detail-name').textContent=currentDetailProduct.name;
  document.getElementById('detail-cat').textContent=currentDetailProduct.cat_name||'';
  let ph='';
  if(currentDetailProduct.old_price&&currentDetailProduct.old_price>currentDetailProduct.price){
    const d=Math.round((1-currentDetailProduct.price/currentDetailProduct.old_price)*100);
    ph=`<span style="color:var(--tx3);text-decoration:line-through;font-size:1.1rem;margin-right:8px">${currentDetailProduct.old_price.toLocaleString('fr-FR')} DA</span>
        <span style="color:var(--B);font-weight:800;font-size:1.5rem">${currentDetailProduct.price.toLocaleString('fr-FR')} DA</span>
        <span style="background:var(--B);color:#fff;padding:2px 10px;border-radius:20px;font-size:10px;font-weight:700;margin-left:8px">-${d}%</span>`;
  } else ph=`<span style="color:var(--B);font-weight:800;font-size:1.5rem">${currentDetailProduct.price.toLocaleString('fr-FR')} DA</span>`;
  document.getElementById('detail-prices').innerHTML=ph;
  const desc=currentDetailProduct.description||'';
  document.getElementById('detail-desc').innerHTML=desc.replace(/\n/g,'<br>');
  document.getElementById('detail-desc').setAttribute('dir',isAR(desc)?'rtl':'ltr');
  let imgs=[];
  if(currentDetailProduct.image) imgs.push(currentDetailProduct.image);
  (currentDetailProduct.images||[]).forEach(img=>{ if(!imgs.includes(img.image_path)) imgs.push(img.image_path); });
  if(imgs.length) document.getElementById('detail-main-image').src=imgs[0];
  document.getElementById('detail-gallery').innerHTML=imgs.map((img,i)=>
    `<img src="${img}" class="gt w-14 h-14 md:w-16 md:h-16 object-cover border-2 ${i===0?'active border-burgundy':'border-burgundy/10 opacity-60'}" onclick="event.stopPropagation();document.getElementById('detail-main-image').src='${img}';document.querySelectorAll('.gt').forEach(t=>{t.classList.remove('active','border-burgundy');t.classList.add('border-burgundy/10','opacity-60')});this.classList.add('active','border-burgundy');this.classList.remove('border-burgundy/10','opacity-60')">`
  ).join('');
  const szs=currentDetailProduct.sizes||[];
  if(!selectedSizes[pid]&&szs.length) selectedSizes[pid]=szs[0];
  document.getElementById('detail-sizes').innerHTML=szs.length?`<span class="text-[11px] font-semibold block mb-1.5" style="color:var(--tx3)">Tailles</span><div class="flex flex-wrap gap-1.5">${szs.map(s=>`<button onclick="selectSize(${pid},'${s}')" class="sz-${pid} px-3.5 py-1.5 text-xs font-semibold rounded-lg border-2 transition ${selectedSizes[pid]===s?'bg-burgundy text-white border-burgundy':'border-burgundy/10 text-charcoal/60 hover:border-burgundy/30'}" data-size="${s}">${s}</button>`).join('')}</div>`:'';
  const cls=currentDetailProduct.colors||[];
  if(!selectedColors[pid]&&cls.length) selectedColors[pid]=cls[0];
  document.getElementById('detail-colors').innerHTML=cls.length?`<span class="text-[11px] font-semibold block mb-1.5 mt-2" style="color:var(--tx3)">Couleurs</span><div class="flex flex-wrap gap-1.5">${cls.map(c=>`<button onclick="selectColor(${pid},'${c}')" class="cl-${pid} px-3.5 py-1.5 text-xs font-semibold rounded-lg border-2 transition ${selectedColors[pid]===c?'bg-burgundy text-white border-burgundy':'border-burgundy/10 text-charcoal/60 hover:border-burgundy/30'}" data-color="${c}">${c}</button>`).join('')}</div>`:'';
  if(selectedColors[pid]&&productImages[pid]?.[selectedColors[pid]]) document.getElementById('detail-main-image').src=productImages[pid][selectedColors[pid]];
  document.getElementById('product-detail-overlay').classList.remove('hidden');
  document.getElementById('product-detail-overlay').classList.add('flex');
  document.body.style.overflow='hidden';
}
function closeProductDetail(){ document.getElementById('product-detail-overlay').classList.add('hidden'); document.getElementById('product-detail-overlay').classList.remove('flex'); document.body.style.overflow=''; }
function openLightbox(){ document.getElementById('lightbox-img').src=document.getElementById('detail-main-image').src; document.getElementById('lightbox').classList.remove('hidden'); document.getElementById('lightbox').classList.add('flex'); }
function closeLightbox(){ document.getElementById('lightbox').classList.add('hidden'); document.getElementById('lightbox').classList.remove('flex'); }

/* ──────── CHECKOUT ──────── */
function openCheckout(){
  if(!cart.length) return; closeCart(); shippingFee=0;
  const tot=cart.reduce((s,i)=>s+(i.price*i.qty),0);
  document.getElementById('checkout-total').textContent=tot.toLocaleString('fr-FR')+' DA';
  document.getElementById('domicile-price').textContent='--';
  document.getElementById('bureau-price').textContent='--';
  document.getElementById('shipping-unavailable').classList.add('hidden');
  document.getElementById('bureau-option').classList.remove('opacity-50','pointer-events-none');
  document.querySelector('input[value="domicile"]').checked=true;
  document.getElementById('c-wilaya').value='';
  document.getElementById('c-commune').innerHTML='<option value="">Sélectionner</option>';
  document.getElementById('checkout-overlay').classList.remove('hidden');
  document.getElementById('checkout-overlay').classList.add('flex');
}
function closeCheckout(){ document.getElementById('checkout-overlay').classList.add('hidden'); document.getElementById('checkout-overlay').classList.remove('flex'); }
function updateShippingFromWilaya(){
  const wid=parseInt(document.getElementById('c-wilaya').value);
  const del=document.querySelector('input[name="delivery"]:checked').value;
  const rates=shippingRates[wid];
  const ua=document.getElementById('shipping-unavailable');
  const bo=document.getElementById('bureau-option');
  if(rates){
    const bp=rates.bureau, dp=rates.domicile;
    document.getElementById('domicile-price').textContent=dp?dp.toLocaleString('fr-FR'):'--';
    document.getElementById('bureau-price').textContent=bp?bp.toLocaleString('fr-FR'):'--';
    if(del==='domicile'&&dp){shippingFee=dp;ua.classList.add('hidden');bo.classList.remove('opacity-50');}
    else if(del==='bureau'&&bp){shippingFee=bp;ua.classList.add('hidden');bo.classList.remove('opacity-50');}
    else if(del==='domicile'&&!dp){ua.classList.remove('hidden');shippingFee=0;}
    else if(del==='bureau'&&!bp){ua.classList.remove('hidden');shippingFee=0;document.querySelector('input[value="domicile"]').checked=true;updateShippingFromWilaya();return;}
    if(!bp) bo.classList.add('opacity-50'); else bo.classList.remove('opacity-50');
  } else shippingFee=0;
  const tot=cart.reduce((s,i)=>s+(i.price*i.qty),0);
  document.getElementById('checkout-total').textContent=(tot+shippingFee).toLocaleString('fr-FR')+' DA';
}
function loadCommunes(wid){
  fetch('api.php?action=get_communes&wilaya_id='+wid).then(r=>r.json()).then(data=>{
    const sel=document.getElementById('c-commune');
    sel.innerHTML='<option value="">Choisir</option>';
    if(data.success) data.communes.forEach(c=>sel.innerHTML+=`<option value="${c.name}">${c.name}</option>`);
  });
  updateShippingFromWilaya();
}
document.getElementById('checkout-form').addEventListener('submit',function(e){
  e.preventDefault();
  const fd=new FormData(this);
  fd.append('action','submit_order');
  fd.append('cart',JSON.stringify(cart));
  fd.append('shipping_fee',shippingFee);
  if(document.getElementById('c-phone').value.trim().length<8){showToast('Numéro invalide','err');return;}
  const btn=document.getElementById('order-btn');
  btn.textContent='Envoi en cours…'; btn.disabled=true;
  fetch('api.php',{method:'POST',body:fd}).then(r=>r.json()).then(data=>{
    btn.disabled=false; btn.innerHTML='<i class="fas fa-check mr-2"></i>Confirmer la commande';
    if(data.success){ cart=[];updBadge();saveCart();closeCheckout();renderCart();showToast('Commande confirmée ! Merci 🎉'); }
    else showToast('Erreur: '+(data.message||'Réessayez'),'err');
  }).catch(()=>{ btn.disabled=false; btn.innerHTML='<i class="fas fa-check mr-2"></i>Confirmer la commande'; showToast('Erreur réseau','err'); });
});

/* ──────── KEYBOARD ──────── */
document.addEventListener('keydown',e=>{
  if(e.key==='Escape'){ closeProductDetail();closeLightbox();closeCart();closeCheckout();closeWL(); }
  if(e.key==='/'&&!['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)){
    e.preventDefault();
    const el=document.getElementById('sh-desk')||document.getElementById('sh-body');
    if(el){el.focus();el.select();}
  }
});

/* ──────── INIT ──────── */
loadCart(); loadWL();
document.addEventListener('DOMContentLoaded',()=>{ renderProducts(); });
</script>
</body>
</html>
