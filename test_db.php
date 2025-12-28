<?php
// Simple Database Connection Test
// Access this at: yourdomain.com/test_db.php

$host = 'localhost';
$dbname = 'u786203048_DataBase';
$user = 'u786203048_GrowixGlobal';
$password = 'Itachi@1990';

echo "<h2>Database Connection Test</h2>";
echo "<p><strong>Host:</strong> $host</p>";
echo "<p><strong>Database:</strong> $dbname</p>";
echo "<p><strong>User:</strong> $user</p>";
echo "<p><strong>Password:</strong> " . substr($password, 0, 3) . "****" . substr($password, -3) . " (Hidden)</p>";

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    
    $pdo = new PDO($dsn, $user, $password, $options);
    
    echo "<h3 style='color: green;'>✅ Connection Successful!</h3>";
    echo "<p>The credentials are correct and the user has access to the database.</p>";
    
} catch (PDOException $e) {
    echo "<h3 style='color: red;'>❌ Connection Failed</h3>";
    echo "<p><strong>Error Code:</strong> " . $e->getCode() . "</p>";
    echo "<p><strong>Error Message:</strong> " . $e->getMessage() . "</p>";
    
    if ($e->getCode() == 1044) {
        echo "<div style='background: #ffe6e6; padding: 10px; border: 1px solid red;'>";
        echo "<h4>⚠️ Access Denied (Error 1044)</h4>";
        echo "<p>This means the user <strong>$user</strong> exists, but <strong>does not have permission</strong> to access the database <strong>$dbname</strong>.</p>";
        echo "<h4>Solution:</h4>";
        echo "<ol>";
        echo "<li>Go to your <strong>Hostinger Control Panel</strong>.</li>";
        echo "<li>Click on <strong>MySQL Databases</strong>.</li>";
        echo "<li>Find the list of Current Databases.</li>";
        echo "<li>Check if user <code>$user</code> is assigned to <code>$dbname</code>.</li>";
        echo "<li>If not, find the section to <strong>Add User to Database</strong> and link them.</li>";
        echo "</ol>";
        echo "</div>";
    } elseif ($e->getCode() == 1045) {
        echo "<div style='background: #ffe6e6; padding: 10px; border: 1px solid red;'>";
        echo "<h4>⚠️ Access Denied (Error 1045)</h4>";
        echo "<p>This means the <strong>password is incorrect</strong>.</p>";
        echo "<p>Please check the password in your <code>.env</code> file or <code>config.php</code>.</p>";
        echo "</div>";
    }
}
?>