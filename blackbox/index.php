<?php
// blackbox/index.php
require_once 'config/database.php';

$welcome_message = "Welcome to Sweet Factory - Premium Quality Candies & Sweets!";
$description = "Discover our delicious handmade sweets, chocolates, and candies. Best quality ingredients, delightful flavors for every occasion.";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= $description ?>">
    <meta name="keywords" content="sweets, candies, chocolate, gift, sweet factory">
    <title>Sweet Factory - Premium Candies & Sweets</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
                    <!-- Only Shop link for all users -->
                    <li class="nav-item"><a class="nav-link" href="shop.php">Shop</a></li>
                    
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <!-- When logged in: only Logout, no dashboard links -->
                        <li class="nav-item"><a class="nav-link" href="config/logout.php">Logout (<?= htmlspecialchars($_SESSION['username']) ?>)</a></li>
                    <?php else: ?>
                        <!-- When not logged in: Login and Signup buttons -->
                        <li class="nav-item"><a class="nav-link btn btn-primary text-white ms-2" href="config/login.php">Login</a></li>
                        <li class="nav-item"><a class="nav-link btn btn-success text-white ms-2" href="config/signup.php">Signup</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="hero bg-warning text-dark py-5 text-center">
        <div class="container">
            <h1 class="display-4 fw-bold"><?= $welcome_message ?></h1>
            <p class="lead"><?= $description ?></p>
            <a href="shop.php" class="btn btn-dark btn-lg">Shop Now</a>
            <?php if(!isset($_SESSION['user_id'])): ?>
                <a href="config/signup.php" class="btn btn-outline-dark btn-lg ms-2">Join Sweet Club</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="container my-5">
        <div class="row">
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-gem fa-3x text-warning"></i>
                        <h3>Premium Quality</h3>
                        <p>Finest ingredients from around the world</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-truck fa-3x text-warning"></i>
                        <h3>Fast Delivery</h3>
                        <p>Fresh sweets delivered to your doorstep</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-gift fa-3x text-warning"></i>
                        <h3>Gift Ready</h3>
                        <p>Beautiful packaging for every occasion</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-dark text-white text-center py-3">
        <p>&copy; 2025 Sweet Factory. All Rights Reserved.</p>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>