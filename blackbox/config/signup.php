<?php
// blackbox/config/signup.php
require_once 'database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validations
    if (empty($username) || empty($email) || empty($phone) || empty($password)) {
        $error = 'All fields are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format';
    } elseif (!preg_match('/^[0-9+\-\s()]{8,20}$/', $phone)) {
        $error = 'Invalid phone number format';
    } elseif (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        // Check if username or email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $error = 'Username or email already exists';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, phone, password, role) VALUES (?, ?, ?, ?, 'common')");
            if ($stmt->execute([$username, $email, $phone, $hashed])) {
                $success = 'Registration successful! <a href="login.php">Login here</a>';
            } else {
                $error = 'Registration failed';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signup - Sweet Factory</title>
    <link rel="stylesheet" href="../css/style.css"> 
</head>
<body class="bg-light">
    <div class="container mt-5" style="max-width: 500px;">
        <div class="card shadow">
            <div class="card-header bg-success text-white">
                <h3 class="mb-0">Create Account</h3>
            </div>
            <div class="card-body">
                <?php if($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if($success): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                <?php endif; ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="phone" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-success w-100">Sign Up</button>
                </form>
                <p class="mt-3 text-center">Already have an account? <a href="login.php">Login here</a></p>
                <p class="text-center mt-2">
                    <a href="../index.php" class="btn btn-outline-secondary btn-sm">← Back to Home</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>