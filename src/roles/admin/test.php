<?php
// ============================================================
// DIAGNOSTIC SCRIPT — delete this file once everything works.
// Open this in your browser at the same URL path as admin-overview.php,
// e.g. http://localhost/foodbridge/admin/test.php
// ============================================================

echo "<h1>Step 1: Is PHP executing?</h1>";
echo "<p style='color:green'>YES — if you can read this, PHP is running. Good.</p>";
echo "<p>PHP version: " . phpversion() . "</p>";

echo "<hr><h1>Step 2: Does the config file load?</h1>";
$configPath = '../../config/database.php';
if (!file_exists($configPath)) {
    echo "<p style='color:red'>FAIL — file not found at: <code>" . realpath('.') . "/" . $configPath . "</code></p>";
    echo "<p>This means the path '../../config/database.php' is wrong relative to where this script sits. Find your actual config file and tell me its real path.</p>";
    exit;
}
echo "<p style='color:green'>File exists at: " . realpath($configPath) . "</p>";

try {
    require_once $configPath;
    echo "<p style='color:green'>File included without a fatal error.</p>";
} catch (Throwable $e) {
    echo "<p style='color:red'>FAIL — including the config file threw an error:</p>";
    echo "<pre style='color:red'>" . htmlspecialchars($e->getMessage()) . "</pre>";
    exit;
}

echo "<hr><h1>Step 3: Does it expose \$pdo?</h1>";
if (!isset($pdo)) {
    echo "<p style='color:red'>FAIL — \$pdo is not set after including the config file.</p>";
    echo "<p>Your config file uses a different variable name, or a different DB API (mysqli). Open the config file and tell me exactly what variable it defines and how it connects.</p>";
    exit;
}
if (!($pdo instanceof PDO)) {
    echo "<p style='color:red'>FAIL — \$pdo exists but is not a PDO instance (type: " . gettype($pdo) . ").</p>";
    exit;
}
echo "<p style='color:green'>\$pdo is a valid PDO instance.</p>";

echo "<hr><h1>Step 4: Can it actually query the database?</h1>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) AS c FROM donations WHERE status = 'active'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p style='color:green'>Query succeeded. Active donations count = <strong>" . htmlspecialchars($row['c']) . "</strong></p>";
} catch (Throwable $e) {
    echo "<p style='color:red'>FAIL — the query threw an error:</p>";
    echo "<pre style='color:red'>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<p>Common causes: wrong database name selected, table name typo, or the donations table doesn't exist in this DB.</p>";
    exit;
}

echo "<hr><h1>Step 5: Full users/donations sanity check</h1>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) AS c FROM users");
    echo "<p>Total users: " . htmlspecialchars($stmt->fetch(PDO::FETCH_ASSOC)['c']) . "</p>";

    $stmt = $pdo->query("SELECT COUNT(*) AS c FROM donations");
    echo "<p>Total donations: " . htmlspecialchars($stmt->fetch(PDO::FETCH_ASSOC)['c']) . "</p>";

    $stmt = $pdo->query("SELECT COALESCE(SUM(total_food_donated),0) AS c FROM users WHERE role='donor'");
    echo "<p>Sum of total_food_donated: " . htmlspecialchars($stmt->fetch(PDO::FETCH_ASSOC)['c']) . "</p>";
} catch (Throwable $e) {
    echo "<pre style='color:red'>" . htmlspecialchars($e->getMessage()) . "</pre>";
}

echo "<hr><h2 style='color:green'>If you see this line, everything works end-to-end.</h2>";
echo "<p>That means admin-overview.php SHOULD be showing real numbers. If it still isn't, the problem is specifically in admin-overview.php's own code, or you're opening a cached/different copy of that file — not your database setup.</p>";