<?php
echo "<h1>.htaccess Test Page</h1>";
echo "<p>If you can see this, PHP is working!</p>";

// Check if mod_rewrite is enabled
if(function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    if(in_array('mod_rewrite', $modules)) {
        echo "<p style='color:green'>✓ mod_rewrite is ENABLED</p>";
    } else {
        echo "<p style='color:red'>✗ mod_rewrite is DISABLED</p>";
    }
} else {
    echo "<p>Cannot check mod_rewrite directly</p>";
}

// Check .htaccess is being read
echo "<h2>Testing URL Rewriting:</h2>";
echo "<p>Current URL: " . $_SERVER['REQUEST_URI'] . "</p>";
?>