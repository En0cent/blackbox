<?php
// blackbox/admin/admin.php
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../config/login.php');
    exit;
}

// Correct uploads directory: from admin/ folder, go up one level to blackbox/, then into uploads/
$uploads_dir = __DIR__ . '/../uploads/';
if (!is_dir($uploads_dir)) {
    mkdir($uploads_dir, 0755, true);
}

// Add product (with image upload)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name = $_POST['name'];
    $desc = $_POST['description'];
    $price = $_POST['price'];
    $stock = $_POST['stock'];
    $target_role = $_POST['target_role'];
    $image_path = '';
    $upload_error = null;

    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $original = basename($_FILES['image']['name']);
        // Sanitize only to prevent path traversal, keep original name
        $original = preg_replace('/[^a-zA-Z0-9._-]/', '_', $original);
        $target = $uploads_dir . $original;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
            $image_path = 'uploads/' . $original;
        } else {
            $upload_error = "Image upload failed. Check folder permissions: " . $uploads_dir;
        }
    } elseif (isset($_FILES['image']) && $_FILES['image']['error'] != 4) {
        $upload_error = "File upload error code: " . $_FILES['image']['error'];
    }

    // If upload error, show error and don't insert product
    if ($upload_error) {
        $error = $upload_error;
    } else {
        $stmt = $pdo->prepare("INSERT INTO products (name, description, price, stock_quantity, image, target_role) VALUES (?,?,?,?,?,?)");
        if ($stmt->execute([$name, $desc, $price, $stock, $image_path, $target_role])) {
            $pid = $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO stock_movements (product_id, quantity, movement_type, reference) VALUES (?,?,'addition','Admin')")->execute([$pid, $stock]);
            $success = "Product added! Image saved as: " . htmlspecialchars($original ?? 'none');
        } else {
            $error = "Database insert failed.";
        }
    }
}

// Stock adjustment (unchanged)
if (isset($_POST['adjust_stock'])) {
    $pid = $_POST['product_id'];
    $qty = intval($_POST['adjust_qty']);
    $type = $_POST['adjust_type'];
    $stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id=?");
    $stmt->execute([$pid]);
    $prod = $stmt->fetch();
    if ($prod) {
        $new = ($type == 'add') ? $prod['stock_quantity'] + $qty : $prod['stock_quantity'] - $qty;
        if ($new >= 0) {
            $pdo->prepare("UPDATE products SET stock_quantity=? WHERE id=?")->execute([$new, $pid]);
            $mov = ($type == 'add') ? 'addition' : 'removal';
            $pdo->prepare("INSERT INTO stock_movements (product_id, quantity, movement_type, reference) VALUES (?,?,?,'Admin')")->execute([$pid, $qty, $mov]);
            $stock_success = "Stock updated!";
        } else $stock_error = "Stock cannot be negative";
    }
}

// Role update (including self)
if (isset($_POST['update_role'])) {
    $uid = $_POST['user_id'];
    $role = $_POST['role'];
    if (in_array($role, ['admin','common','vip'])) {
        $pdo->prepare("UPDATE users SET role=? WHERE id=?")->execute([$role, $uid]);
        if ($uid == $_SESSION['user_id']) $_SESSION['role'] = $role;
        $client_msg = "Role updated to " . ucfirst($role);
    }
}

$products = $pdo->query("SELECT * FROM products ORDER BY created_at DESC")->fetchAll();
$clients = $pdo->query("SELECT * FROM users WHERE role != 'admin' ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <span class="navbar-brand"><i class="fas fa-candy-cane"></i> Sweet Factory Admin</span>
            <ul class="nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="reports.php">Reports</a></li>
                <li class="nav-item"><a class="nav-link" href="../shop.php">Shop</a></li>
                <li class="nav-item"><a class="nav-link" href="../config/logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>
    <div class="container my-5">
        <h1>Admin Dashboard</h1>
        <?php if(isset($success)) echo "<div class='alert alert-success'>$success</div>"; ?>
        <?php if(isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
        <?php if(isset($stock_success)) echo "<div class='alert alert-success'>$stock_success</div>"; ?>
        <?php if(isset($stock_error)) echo "<div class='alert alert-danger'>$stock_error</div>"; ?>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">Add New Product</div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="text" name="name" class="form-control mb-2" placeholder="Product Name" required>
                            <textarea name="description" class="form-control mb-2" placeholder="Description"></textarea>
                            <input type="number" step="0.01" name="price" class="form-control mb-2" placeholder="Price (Ksh)" required>
                            <input type="number" name="stock" class="form-control mb-2" placeholder="Stock" required>
                            <select name="target_role" class="form-control mb-2">
                                <option value="all">All Customers</option>
                                <option value="common">Common Only</option>
                                <option value="vip">VIP Only</option>
                            </select>
                            <input type="file" name="image" class="form-control mb-2" accept="image/*">
                            <button type="submit" name="add_product" class="btn btn-primary">Add Product</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">Adjust Stock</div>
                    <div class="card-body">
                        <form method="POST">
                            <select name="product_id" class="form-control mb-2">
                                <?php foreach($products as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (Stock: <?= $p['stock_quantity'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" name="adjust_qty" class="form-control mb-2" placeholder="Quantity" required>
                            <select name="adjust_type" class="form-control mb-2">
                                <option value="add">Add Stock</option>
                                <option value="remove">Remove Stock</option>
                            </select>
                            <button type="submit" name="adjust_stock" class="btn btn-warning">Update Stock</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Products table -->
        <div class="card">
            <div class="card-header">Current Products</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr><th>Image</th><th>Name</th><th>Price (Ksh)</th><th>Stock</th><th>Target Role</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($products as $p): ?>
                                <tr>
                                    <td>
                                        <?php if(!empty($p['image']) && file_exists('../' . $p['image'])): ?>
                                            <img src="../<?= htmlspecialchars($p['image']) ?>" style="height:60px; object-fit:cover;" alt="<?= htmlspecialchars($p['name']) ?>">
                                        <?php else: ?>
                                            <span class="text-muted">No image</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($p['name']) ?></td>
                                    <td>Ksh <?= number_format($p['price'],2) ?></td>
                                    <td><?= $p['stock_quantity'] ?></td>
                                    <td><?= ucfirst($p['target_role']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <?php if(isset($_GET['manage_clients'])): ?>
        <div class="card mt-4">
            <div class="card-header">Manage Clients</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead><tr><th>Username</th><th>Email</th><th>Phone</th><th>Role</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php foreach($clients as $c): ?>
                            <form method="POST">
                            <tr>
                                <td><?= htmlspecialchars($c['username']) ?></td>
                                <td><?= htmlspecialchars($c['email']) ?></td>
                                <td><?= htmlspecialchars($c['phone']) ?></td>
                                <td><?= ucfirst($c['role']) ?></td>
                                <td>
                                    <input type="hidden" name="user_id" value="<?= $c['id'] ?>">
                                    <select name="role" class="form-select d-inline w-auto">
                                        <option value="common" <?= $c['role']=='common'?'selected':'' ?>>Common</option>
                                        <option value="vip" <?= $c['role']=='vip'?'selected':'' ?>>VIP</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                    <button type="submit" name="update_role" class="btn btn-sm btn-primary">Update</button>
                                </td>
                            </tr>
                            </form>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>