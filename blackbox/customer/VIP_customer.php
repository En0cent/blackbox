<?php
// blackbox/customer/VIP_customer.php
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'vip') {
    header('Location: ../config/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>VIP Dashboard - Sweet Factory</title>
    <link rel="stylesheet" href="../css/style.css"> 
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <span class="navbar-brand"><i class="fas fa-crown"></i> Sweet Factory VIP</span>
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
        <h1>Welcome VIP, <?= htmlspecialchars($user['username']) ?></h1>
        <div class="card border-warning">
            <div class="card-body">
                <h5>VIP Profile</h5>
                <p>Email: <?= htmlspecialchars($user['email']) ?><br>Phone: <?= htmlspecialchars($user['phone']) ?></p>
                <p><strong>Exclusive Perks:</strong> Early access to new products, priority support, and exclusive VIP‑only sweets.</p>
                <p>Member since: <?= $user['created_at'] ?></p>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>