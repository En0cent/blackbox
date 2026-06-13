<?php
// blackbox/customer/receipts.php
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] == 'admin') {
    header('Location: ../config/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get all orders with items
$orders_stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY order_date DESC");
$orders_stmt->execute([$user_id]);
$orders = $orders_stmt->fetchAll();

// For each order, fetch items
$order_items = [];
foreach ($orders as $order) {
    $items_stmt = $pdo->prepare("SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
    $items_stmt->execute([$order['id']]);
    $order_items[$order['id']] = $items_stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Receipts - Sweet Factory</title>
    <link rel="stylesheet" href="../css/style.css"> 
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <span class="navbar-brand"><i class="fas fa-receipt"></i> My Receipts</span>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="../shop.php"><i class="fas fa-store"></i> Shop</a></li>
                    <li class="nav-item"><a class="nav-link" href="cart.php"><i class="fas fa-shopping-cart"></i> Cart</a></li>
                    <li class="nav-item"><a class="nav-link active" href="receipts.php"><i class="fas fa-receipt"></i> Receipts</a></li>
                    <li class="nav-item"><a class="nav-link" href="../config/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container my-5">
        <h1>Your Transaction Receipts</h1>
        <?php if(count($orders) === 0): ?>
            <div class="alert alert-info">You have no orders yet. <a href="../shop.php">Start shopping</a></div>
        <?php else: ?>
            <?php foreach($orders as $order): ?>
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <strong>Receipt #: <?= htmlspecialchars($order['receipt_number']) ?></strong><br>
                        Date: <?= $order['order_date'] ?><br>
                        Total Amount: <strong>Ksh <?= number_format($order['total_amount'], 2) ?></strong>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <thead>
                                <tr><th>Product</th><th>Quantity</th><th>Unit Price (Ksh)</th><th>Subtotal (Ksh)</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach($order_items[$order['id']] as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['name']) ?></td>
                                        <td><?= $item['quantity'] ?></td>
                                        <td>Ksh <?= number_format($item['price'], 2) ?></td>
                                        <td>Ksh <?= number_format($item['quantity'] * $item['price'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>