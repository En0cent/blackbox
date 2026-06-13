<?php
// blackbox/customer/cart.php
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] == 'admin') {
    header('Location: ../config/login.php');
    exit;
}

// Update cart quantities
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_cart'])) {
        foreach ($_POST['quantities'] as $cart_id => $qty) {
            $qty = max(1, intval($qty));
            $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?")->execute([$qty, $cart_id, $_SESSION['user_id']]);
        }
    } elseif (isset($_POST['remove_item'])) {
        $cart_id = $_POST['cart_id'];
        $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?")->execute([$cart_id, $_SESSION['user_id']]);
    } elseif (isset($_POST['checkout'])) {
        // Process checkout
        $cart_items = $pdo->prepare("SELECT c.*, p.price, p.stock_quantity, p.name FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ?");
        $cart_items->execute([$_SESSION['user_id']]);
        $items = $cart_items->fetchAll();
        if (empty($items)) {
            $error = "Cart is empty!";
        } else {
            $total = 0;
            foreach ($items as $item) {
                if ($item['stock_quantity'] < $item['quantity']) {
                    $error = "Insufficient stock for {$item['name']}";
                    break;
                }
                $total += $item['price'] * $item['quantity'];
            }
            if (!isset($error)) {
                $receipt = 'RCP-' . date('YmdHis') . rand(100, 999);
                $pdo->beginTransaction();
                try {
                    $order_stmt = $pdo->prepare("INSERT INTO orders (user_id, total_amount, receipt_number) VALUES (?, ?, ?)");
                    $order_stmt->execute([$_SESSION['user_id'], $total, $receipt]);
                    $order_id = $pdo->lastInsertId();
                    
                    foreach ($items as $item) {
                        // Insert order item
                        $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)")
                            ->execute([$order_id, $item['product_id'], $item['quantity'], $item['price']]);
                        // Update stock
                        $new_stock = $item['stock_quantity'] - $item['quantity'];
                        $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?")->execute([$new_stock, $item['product_id']]);
                        // Log stock removal
                        $pdo->prepare("INSERT INTO stock_movements (product_id, quantity, movement_type, reference) VALUES (?, ?, 'removal', ?)")
                            ->execute([$item['product_id'], $item['quantity'], "Order #$order_id"]);
                    }
                    // Clear cart
                    $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$_SESSION['user_id']]);
                    $pdo->commit();
                    $success = "Order placed! Receipt: $receipt";
                } catch(Exception $e) {
                    $pdo->rollBack();
                    $error = "Checkout failed: " . $e->getMessage();
                }
            }
        }
    }
}

$stmt = $pdo->prepare("SELECT c.id as cart_id, c.quantity, p.id as product_id, p.name, p.price, p.stock_quantity, p.image FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$cart_items = $stmt->fetchAll();
$total = 0;
foreach ($cart_items as $item) {
    $total += $item['price'] * $item['quantity'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Cart - Sweet Factory</title>
    <link rel="stylesheet" href="../css/style.css"> 
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <span class="navbar-brand"><i class="fas fa-candy-cane"></i> Sweet Factory</span>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="../shop.php"><i class="fas fa-store"></i> Shop</a></li>
                    <li class="nav-item"><a class="nav-link active" href="cart.php"><i class="fas fa-shopping-cart"></i> Cart</a></li>
                    <li class="nav-item"><a class="nav-link" href="../config/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container my-5">
        <h1>Shopping Cart</h1>
        <?php if(isset($success)): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        <?php if(isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        <?php if(empty($cart_items)): ?>
            <div class="alert alert-info">Your cart is empty. <a href="../shop.php">Continue Shopping</a></div>
        <?php else: ?>
            <form method="POST">
                <table class="table table-bordered">
                    <thead>
                        <tr><th>Product</th><th>Price (Ksh)</th><th>Quantity</th><th>Subtotal (Ksh)</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($cart_items as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['name']) ?></td>
                                <td>Ksh <?= number_format($item['price'], 2) ?></td>
                                <td><input type="number" name="quantities[<?= $item['cart_id'] ?>]" value="<?= $item['quantity'] ?>" min="1" max="<?= $item['stock_quantity'] ?>" class="form-control" style="width: 80px;"></td>
                                <td>Ksh <?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                                <td><button type="submit" name="remove_item" value="1" class="btn btn-danger btn-sm" onclick="this.form.cart_id.value=<?= $item['cart_id'] ?>">Remove</button></td>
                            </tr>
                            <input type="hidden" name="cart_id" value="">
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr><th colspan="3">Total</th><th>Ksh <?= number_format($total, 2) ?></th><th></th></tr>
                    </tfoot>
                </table>
                <button type="submit" name="update_cart" class="btn btn-secondary">Update Cart</button>
                <button type="submit" name="checkout" class="btn btn-success">Proceed to Checkout</button>
            </form>
        <?php endif; ?>
    </div>
    <script>
        document.querySelectorAll('button[name="remove_item"]').forEach(btn => {
            btn.addEventListener('click', function(e) {
                this.closest('form').querySelector('input[name="cart_id"]').value = this.closest('tr').querySelector('input[name^="quantities"]').name.match(/\d+/)[0];
            });
        });
    </script>
</body>
</html>