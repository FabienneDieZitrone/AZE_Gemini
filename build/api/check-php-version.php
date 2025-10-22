<?php
header('Content-Type: text/plain; charset=utf-8');
echo "PHP Version: " . PHP_VERSION . "\n";
echo "PHP Major Version: " . PHP_MAJOR_VERSION . "\n";
echo "PHP Minor Version: " . PHP_MINOR_VERSION . "\n";
echo "PHP Extensions:\n";
echo "- MySQLi: " . (extension_loaded('mysqli') ? 'YES' : 'NO') . "\n";
echo "- JSON: " . (extension_loaded('json') ? 'YES' : 'NO') . "\n";
echo "- mbstring: " . (extension_loaded('mbstring') ? 'YES' : 'NO') . "\n";
