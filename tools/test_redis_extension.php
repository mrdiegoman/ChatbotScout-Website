<?php
if (extension_loaded('redis')) {
    echo "✅ Redis extension loaded!<br>";
    echo "Version: " . phpversion('redis') . "<br>";
    
    // Test pripojenia
    try {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        $redis->ping();
        echo "✅ Redis connection successful!<br>";
    } catch (Exception $e) {
        echo "❌ Connection failed: " . $e->getMessage();
    }
} else {
    echo "❌ Redis extension NOT loaded!";
}
?>