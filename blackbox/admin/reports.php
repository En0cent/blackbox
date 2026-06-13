<?php
// blackbox/admin/reports.php
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../config/login.php');
    exit;
}

// Handle role change directly from reports table (now includes admin)
if (isset($_POST['change_role'])) {
    $user_id = $_POST['user_id'];
    $new_role = $_POST['new_role'];
    if (in_array($new_role, ['common', 'vip', 'admin'])) {
        $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$new_role, $user_id]);
        $role_msg = "Role updated to " . ucfirst($new_role) . "!";
    }
}

// Customer registration info (show all non-admin users? Actually show all except current admin? We'll show all users except the logged-in admin or show all and allow any change. Safer to show all non-admin users, but to promote to admin we need to see them. Let's show all users except the current admin (so we don't lock ourselves out). We'll show all users where role != 'admin' OR id != current admin? Actually if we show admins, we could demote them. Let's show all users with role != 'admin' to keep list clean, but we need to allow promotion to admin. So we fetch all users where id != current admin id.
$current_admin_id = $_SESSION['user_id'];
$customers = $pdo->prepare("SELECT id, username, email, phone, role, created_at FROM users WHERE id != ? ORDER BY created_at DESC");
$customers->execute([$current_admin_id]);
$customers = $customers->fetchAll();

// Product sales summary with target_role
$sales = $pdo->query("SELECT p.name, p.target_role, SUM(oi.quantity) as total_sold, SUM(oi.quantity * oi.price) as revenue FROM order_items oi JOIN products p ON oi.product_id = p.id GROUP BY p.id")->fetchAll();

// Stock details with target_role
$stock_details = $pdo->query("
    SELECT p.id, p.name, p.target_role, p.stock_quantity as current_stock, p.price,
        COALESCE(SUM(CASE WHEN sm.movement_type = 'addition' THEN sm.quantity ELSE 0 END), 0) as total_added,
        COALESCE(SUM(CASE WHEN sm.movement_type = 'removal' THEN sm.quantity ELSE 0 END), 0) as total_removed
    FROM products p
    LEFT JOIN stock_movements sm ON p.id = sm.product_id
    GROUP BY p.id
")->fetchAll();

$monthly_spend = $pdo->query("SELECT DATE_FORMAT(order_date, '%Y-%m') as month, SUM(total_amount) as total FROM orders GROUP BY month ORDER BY month DESC")->fetchAll();
$balance = $pdo->query("SELECT SUM(total_amount) as total_revenue FROM orders")->fetchColumn();

$receipts = $pdo->query("
    SELECT o.receipt_number, o.order_date, u.username, oi.product_id, p.name, oi.quantity 
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    JOIN users u ON o.user_id = u.id
    ORDER BY o.order_date DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports - Sweet Factory</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <span class="navbar-brand"><i class="fas fa-chart-line"></i> Sweet Factory Reports</span>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="admin.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link active" href="reports.php"><i class="fas fa-file-alt"></i> Reports</a></li>
                    <li class="nav-item"><a class="nav-link" href="../config/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <h1>Comprehensive Reports</h1>
        <?php if(isset($role_msg)) echo "<div class='alert alert-success'>$role_msg</div>"; ?>
        
        <div class="card mb-4">
            <div class="card-header">Customer Registration Info</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr><th>Username</th><th>Email</th><th>Phone</th><th>Role</th><th>Change Role</th><th>Registered</th>
                        </thead>
                        <tbody>
                            <?php foreach($customers as $c): ?>
                                <form method="POST">
                                    <tr>
                                        <td><?= htmlspecialchars($c['username']) ?></td>
                                        <td><?= htmlspecialchars($c['email']) ?></td>
                                        <td><?= htmlspecialchars($c['phone']) ?></td>
                                        <td><?= ucfirst($c['role']) ?></td>
                                        <td>
                                            <input type="hidden" name="user_id" value="<?= $c['id'] ?>">
                                            <select name="new_role" class="form-select d-inline w-auto">
                                                <option value="common" <?= $c['role']=='common'?'selected':'' ?>>Common</option>
                                                <option value="vip" <?= $c['role']=='vip'?'selected':'' ?>>VIP</option>
                                                <option value="admin">Admin</option>
                                            </select>
                                            <button type="submit" name="change_role" class="btn btn-sm btn-primary">Update</button>
                                        </td>
                                        <td><?= $c['created_at'] ?></td>
                                    </tr>
                                </form>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">Product Sales Summary</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr><th>Product</th><th>Target Role</th><th>Total Sold</th><th>Revenue (Ksh)</th>
                        </thead>
                        <tbody>
                            <?php foreach($sales as $s): ?>
                                <tr>
                                    <td><?= htmlspecialchars($s['name']) ?></td>
                                    <td><?= ucfirst($s['target_role']) ?></td>
                                    <td><?= $s['total_sold'] ?></td>
                                    <td>Ksh <?= number_format($s['revenue'],2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">Stock Details (Added / Ended / Current)</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr><th>Product</th><th>Target Role</th><th>Total Added</th><th>Total Ended</th><th>Current Stock</th><th>Stock Value (Ksh)</th>
                        </thead>
                        <tbody>
                            <?php foreach($stock_details as $st): ?>
                                <tr>
                                    <td><?= htmlspecialchars($st['name']) ?></td>
                                    <td><?= ucfirst($st['target_role']) ?></td>
                                    <td><?= $st['total_added'] ?></td>
                                    <td><?= $st['total_removed'] ?></td>
                                    <td><?= $st['current_stock'] ?></td>
                                    <td>Ksh <?= number_format($st['current_stock'] * $st['price'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">Monthly Spend & Account Balance</div>
            <div class="card-body">
                <p><strong>Total Account Balance:</strong> Ksh <?= number_format($balance, 2) ?></p>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Month</th><th>Total Spend (Ksh)</th></tr></thead>
                        <tbody>
                            <?php foreach($monthly_spend as $m): ?>
                                <tr><td><?= $m['month'] ?></td><td>Ksh <?= number_format($m['total'],2) ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">Receipt Numbers & Quantities</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr><th>Receipt #</th><th>Date</th><th>Customer</th><th>Product</th><th>Quantity</th>
                        </thead>
                        <tbody>
                            <?php foreach($receipts as $r): ?>
                                <tr>
                                    <td><?= $r['receipt_number'] ?></td>
                                    <td><?= $r['order_date'] ?></td>
                                    <td><?= htmlspecialchars($r['username']) ?></td>
                                    <td><?= htmlspecialchars($r['name']) ?></td>
                                    <td><?= $r['quantity'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="card mb-4">
    <div class="card-header">Image Upload History (for Reports)</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr><th>Product ID</th><th>Product Name</th><th>Image Filename</th><th>Uploaded At</th></tr>
                </thead>
                <tbody>
                    <?php
                    $images = $pdo->query("
                        SELECT i.product_id, p.name AS product_name, i.image_path, i.original_filename, i.uploaded_at
                        FROM image_uploads i
                        JOIN products p ON i.product_id = p.id
                        ORDER BY i.uploaded_at DESC
                    ")->fetchAll();
                    foreach($images as $img):
                    ?>
                    <tr>
                        <td><?= $img['product_id'] ?></td>
                        <td><?= htmlspecialchars($img['product_name']) ?></td>
                        <td><?= htmlspecialchars($img['original_filename']) ?></td>
                        <td><?= $img['uploaded_at'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>