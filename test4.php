<?php

echo "<pre>";

echo "PHP Version: " . PHP_VERSION . "\n\n";

echo "curl: ";
var_dump(extension_loaded('curl'));

echo "pdo: ";
var_dump(extension_loaded('pdo'));

echo "pdo_pgsql: ";
var_dump(extension_loaded('pdo_pgsql'));

echo "\nLoaded Extensions:\n";
print_r(get_loaded_extensions());
