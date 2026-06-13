<?php
// blackbox/shop.php
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: config/login.php');
    exit;
}

$user_role = $_SESSION['role'];

// Query products
if ($user_role == 'admin') {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE stock_quantity > 0 ORDER BY created_at DESC");
} elseif ($user_role == 'vip') {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE stock_quantity > 0 ORDER BY created_at DESC");
} else {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE stock_quantity > 0 AND (target_role = 'common' OR target_role = 'all') ORDER BY created_at DESC");
}
$stmt->execute();
$products = $stmt->fetchAll();

// Add to cart (only for non-admin)
if ($user_role != 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $pid = $_POST['product_id'];
    $qty = max(1, intval($_POST['quantity']));
    $stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ?");
    $stmt->execute([$pid]);
    $p = $stmt->fetch();
    if ($p && $p['stock_quantity'] >= $qty) {
        $stmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$_SESSION['user_id'], $pid]);
        $item = $stmt->fetch();
        if ($item) {
            $new = $item['quantity'] + $qty;
            $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?")->execute([$new, $item['id']]);
        } else {
            $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?,?,?)")->execute([$_SESSION['user_id'], $pid, $qty]);
        }
        $success = "Product added to cart!";
    } else {
        $error = "Insufficient stock!";
    }
}

// Determine back link based on role
$back_link = '';
if ($user_role == 'admin') {
    $back_link = 'admin/admin.php';
} elseif ($user_role == 'vip') {
    $back_link = 'customer/VIP_customer.php';
} else {
    $back_link = 'customer/common_customer.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Shop - Sweet Factory</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <span class="navbar-brand"><i class="fas fa-candy-cane"></i> Sweet Factory</span>
            <ul class="nav ms-auto">
                <li class="nav-item"><a class="nav-link active" href="shop.php"><i class="fas fa-store"></i> Shop</a></li>
                <?php if($user_role != 'admin'): ?>
                    <li class="nav-item"><a class="nav-link" href="customer/cart.php"><i class="fas fa-shopping-cart"></i> Cart</a></li>
                    <li class="nav-item"><a class="nav-link" href="customer/receipts.php"><i class="fas fa-receipt"></i> Receipts</a></li>
                <?php endif; ?>
                <li class="nav-item"><a class="nav-link" href="config/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>
    <div class="container my-5">
        <?php if($user_role == 'admin'): ?>
            <div class="alert alert-info"><i class="fas fa-eye"></i> Admin Preview Mode – Cart functionality is disabled.</div>
        <?php endif; ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="mb-0">Our Sweet Collection</h1>
            <a href="<?= $back_link ?>" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
        <?php if(isset($success)) echo "<div class='alert alert-success'>$success</div>"; ?>
        <?php if(isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
        
        <?php if(count($products) === 0): ?>
            <div class="alert alert-info">No products available.</div>
        <?php else: ?>
            <div class="products-grid row">
                <?php foreach($products as $p): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <?php if(!empty($p['image'])): ?>
                                <img src="<?= htmlspecialchars($p['image']) ?>" class="card-img-top" style="height:200px; object-fit:cover;" alt="<?= htmlspecialchars($p['name']) ?>">
                            <?php else: ?>
                                <div class="bg-secondary text-white text-center py-5">No Image</div>
                            <?php endif; ?>
                            <div class="card-body">
                                <h5 class="card-title"><?= htmlspecialchars($p['name']) ?></h5>
                                <p class="card-text"><?= htmlspecialchars(substr($p['description'],0,100)) ?></p>
                                <p class="fw-bold">Ksh <?= number_format($p['price'],2) ?></p>
                                <p>Stock: <?= $p['stock_quantity'] ?></p>
                                <?php if($user_role != 'admin'): ?>
                                    <form method="POST">
                                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                        <div class="input-group">
                                            <input type="number" name="quantity" value="1" min="1" max="<?= $p['stock_quantity'] ?>" class="form-control" style="width:80px">
                                            <button type="submit" name="add_to_cart" class="btn btn-warning"><i class="fas fa-cart-plus"></i> Add to Cart</button>
                                        </div>
                                    </form>
                                <?php else: ?>
                                    <button class="btn btn-secondary" disabled><i class="fas fa-ban"></i> Purchase disabled (Admin)</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>