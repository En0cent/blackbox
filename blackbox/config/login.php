<?php
// blackbox/config/login.php
require_once 'database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            // Redirect based on role
            switch ($user['role']) {
                case 'admin':
                    header('Location: ../admin/admin.php');
                    break;
                case 'vip':
                    header('Location: ../customer/VIP_customer.php');
                    break;
                default: // 'common'
                    header('Location: ../customer/common_customer.php');
                    break;
            }
            exit;
        } else {
            $error = 'Invalid username/email or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sweet Factory</title>
    <link rel="stylesheet" href="../css/style.css"> 
</head>
<body class="bg-light">
    <div class="container mt-5" style="max-width: 500px;">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">Login to Sweet Factory</h3>
            </div>
            <div class="card-body">
                <p> LOG IN </p>
                <?php if($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Username or Email</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Login</button>
                </form>
                <p class="mt-3 text-center">Don't have an account? <a href="signup.php">Signup here</a></p>
                <p class="text-center mt-2">
                    <a href="../index.php" class="btn btn-outline-secondary btn-sm">← Back to Home</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>