<?php
// blackbox/test-shop.php
require_once 'config/database.php';

echo "Uploads dir absolute: " . realpath(__DIR__ . '/uploads/') . "<br>";
echo "Is writable? " . (is_writable(__DIR__ . '/uploads/') ? 'Yes' : 'No') . "<br>";

// Restrict to admin only (for security)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: config/login.php');
    exit;
}

$diagnostic = [];
$fix_results = [];

// ----- 1. Check uploads directory -----
$uploads_dir = __DIR__ . '/uploads/';
$diagnostic['uploads_dir'] = $uploads_dir;
$diagnostic['uploads_exists'] = is_dir($uploads_dir);
$diagnostic['uploads_writable'] = is_writable($uploads_dir);
if (!$diagnostic['uploads_exists']) {
    $diagnostic['uploads_dir_created'] = mkdir($uploads_dir, 0755, true);
    $diagnostic['uploads_writable'] = is_writable($uploads_dir);
}

// ----- 2. Helper: get absolute path for a product image -----
function getProductAbsolutePath($imagePath) {
    if (empty($imagePath)) return null;
    return __DIR__ . '/' . $imagePath;
}

// ----- 3. Handle repair actions -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Fix 1: Add 'uploads/' prefix to paths that lack it
    if (isset($_POST['fix_prefix'])) {
        $stmt = $pdo->prepare("UPDATE products SET image = CONCAT('uploads/', image) WHERE image IS NOT NULL AND image != '' AND image NOT LIKE 'uploads/%'");
        $stmt->execute();
        $fix_results['prefix'] = $stmt->rowCount() . " products updated with 'uploads/' prefix.";
    }
    
    // Fix 2: Remove orphaned images from products table (path empty or file missing)
    if (isset($_POST['remove_orphaned'])) {
        $products = $pdo->query("SELECT id, image FROM products")->fetchAll();
        $removed = 0;
        foreach ($products as $p) {
            $abs = getProductAbsolutePath($p['image']);
            if (empty($p['image']) || ($abs && !file_exists($abs))) {
                $pdo->prepare("UPDATE products SET image = NULL WHERE id = ?")->execute([$p['id']]);
                $removed++;
            }
        }
        $fix_results['orphaned'] = "$removed orphaned image references removed.";
    }
    
    // Fix 3: Attempt to re‑upload a test image (to verify upload works)
    if (isset($_POST['test_upload']) && isset($_FILES['test_image'])) {
        $test_name = sanitizeFilename($_FILES['test_image']['name']);
        $target_file = $uploads_dir . $test_name;
        if (move_uploaded_file($_FILES['test_image']['tmp_name'], $target_file)) {
            $fix_results['test_upload'] = "Test image uploaded successfully as: $test_name";
        } else {
            $fix_results['test_upload'] = "Test upload FAILED. Check folder permissions.";
        }
    }
}

// Helper for sanitisation (same as admin.php)
function sanitizeFilename($filename) {
    $filename = basename($filename);
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    return substr($filename, 0, 100);
}

// ----- 4. Fetch all products with image diagnostics -----
$products = $pdo->query("SELECT id, name, image FROM products ORDER BY id")->fetchAll();
foreach ($products as &$p) {
    $p['abs_path'] = getProductAbsolutePath($p['image']);
    $p['file_exists'] = $p['abs_path'] ? file_exists($p['abs_path']) : false;
    $p['status'] = !$p['image'] ? 'empty' : ($p['file_exists'] ? 'exists' : 'missing');
}
unset($p);

// ----- 5. Check image_uploads table -----
$image_logs = $pdo->query("SELECT * FROM image_uploads ORDER BY uploaded_at DESC LIMIT 20")->fetchAll();

// ----- 6. Test database connection and session -----
$session_ok = isset($_SESSION['user_id']);
$admin_role = ($_SESSION['role'] ?? '') === 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Full Diagnostic & Repair Tool</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; }
        .status-exists { color: #2e7d64; font-weight: bold; }
        .status-missing { color: #c73e1d; font-weight: bold; }
        .status-empty { color: #f0ad4e; font-weight: bold; }
        .diagnostic-box { background: #fff; border-left: 5px solid #1A2A4F; margin-bottom: 1.5rem; }
        .pre-scroll { max-height: 300px; overflow-y: auto; }
    </style>
</head>
<body>
<div class="container my-5">
    <div class="card shadow">
        <div class="card-header bg-dark text-white">
            <h2><i class="fas fa-stethoscope"></i> Sweet Factory – Full Diagnostic & Repair</h2>
        </div>
        <div class="card-body">
            
            <!-- Environment Check -->
            <div class="diagnostic-box p-3">
                <h4>🔍 Server & Environment</h4>
                <ul class="list-unstyled">
                    <li>✅ PHP Version: <?= PHP_VERSION ?></li>
                    <li>✅ Uploads directory: <?= $diagnostic['uploads_dir'] ?></li>
                    <li>📁 Directory exists: <?= $diagnostic['uploads_exists'] ? 'Yes' : '<span class="text-danger">No (attempted to create)</span>' ?></li>
                    <li>✍️ Writable: <?= $diagnostic['uploads_writable'] ? 'Yes' : '<span class="text-danger">No – please chmod 0755 ' . $uploads_dir . '</span>' ?></li>
                    <li>🔐 Session active: <?= $session_ok ? 'Yes' : '<span class="text-danger">No session</span>' ?></li>
                    <li>👑 Admin role: <?= $admin_role ? 'Yes' : '<span class="text-danger">Not admin (some repairs may be restricted)</span>' ?></li>
                </ul>
            </div>
            
            <!-- Repair Actions -->
            <div class="diagnostic-box p-3">
                <h4>🛠️ Repair Actions</h4>
                <div class="row">
                    <div class="col-md-4">
                        <form method="post">
                            <button type="submit" name="fix_prefix" class="btn btn-warning w-100 mb-2" onclick="return confirm('Add \'uploads/\' to all image paths that don\'t have it?')">
                                <i class="fas fa-code-branch"></i> Add missing 'uploads/' prefix
                            </button>
                        </form>
                    </div>
                    <div class="col-md-4">
                        <form method="post">
                            <button type="submit" name="remove_orphaned" class="btn btn-danger w-100 mb-2" onclick="return confirm('Remove image references where file is missing?')">
                                <i class="fas fa-trash-alt"></i> Remove orphaned image references
                            </button>
                        </form>
                    </div>
                    <div class="col-md-4">
                        <form method="post" enctype="multipart/form-data">
                            <input type="file" name="test_image" class="form-control mb-2" required>
                            <button type="submit" name="test_upload" class="btn btn-primary w-100">Test Upload (simulate admin.php)</button>
                        </form>
                    </div>
                </div>
                <?php if (!empty($fix_results)): ?>
                    <div class="alert alert-info mt-3">
                        <?php foreach ($fix_results as $msg) echo "<div>$msg</div>"; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Product Image Status -->
            <div class="diagnostic-box p-3">
                <h4>📦 Product Image Status</h4>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-dark">
                            <tr><th>ID</th><th>Product Name</th><th>Stored Path</th><th>Status</th><th>Absolute Path Checked</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $p): 
                                $status_class = match($p['status']) {
                                    'exists' => 'status-exists',
                                    'missing' => 'status-missing',
                                    default => 'status-empty'
                                };
                                $status_text = match($p['status']) {
                                    'exists' => '✅ File exists',
                                    'missing' => '❌ File missing',
                                    'empty' => '⚠️ No image set'
                                };
                            ?>
                                <tr>
                                    <td><?= $p['id'] ?></td>
                                    <td><?= htmlspecialchars($p['name']) ?></td>
                                    <td><code><?= htmlspecialchars($p['image'] ?: '(empty)') ?></code></td>
                                    <td class="<?= $status_class ?>"><?= $status_text ?></td>
                                    <td><small><?= htmlspecialchars($p['abs_path'] ?: 'N/A') ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Image Upload History (image_uploads table) -->
            <div class="diagnostic-box p-3">
                <h4>📜 Image Upload History (from image_uploads table)</h4>
                <?php if (count($image_logs) === 0): ?>
                    <div class="alert alert-secondary">No uploads recorded yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead><tr><th>Product ID</th><th>Original Filename</th><th>Path</th><th>Uploaded At</th></tr></thead>
                            <tbody>
                                <?php foreach ($image_logs as $log): ?>
                                    <tr>
                                        <td><?= $log['product_id'] ?></td>
                                        <td><?= htmlspecialchars($log['original_filename']) ?></td>
                                        <td><code><?= htmlspecialchars($log['image_path']) ?></code></td>
                                        <td><?= $log['uploaded_at'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Troubleshooting Guide -->
            <div class="alert alert-info mt-3">
                <h5>🔧 Troubleshooting Guide</h5>
                <ul>
                    <li><strong>File NOT found</strong> → The database path does not match an actual file. Use <em>Add 'uploads/' prefix</em> or <em>Remove orphaned references</em>.</li>
                    <li><strong>Upload test fails</strong> → Check folder permissions: <code>chmod 0755 blackbox/uploads/</code> (or 0777 on some servers).</li>
                    <li><strong>Image exists but not showing in shop</strong> → Verify that <code>getProductImageSrc()</code> in <code>shop.php</code> uses the same absolute path logic. Our test uses identical logic.</li>
                    <li><strong>Admin upload fails</strong> → The <code>admin.php</code> uses <code>__DIR__ . '/../../uploads/'</code> (from blackbox/admin/). Check that this resolves correctly.</li>
                    <li><strong>No image_uploads records</strong> → The <code>admin.php</code> may not be logging uploads. Ensure the INSERT into <code>image_uploads</code> is executed after product creation.</li>
                </ul>
                <hr>
                <p><strong>Next steps after running repairs:</strong> Refresh the shop and admin pages. If images still don't appear, re-upload the product using the admin panel – the new upload will use the corrected logic and log to <code>image_uploads</code>.</p>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>