<?php

(function() {
    global $MODULE_DIR;
    $filename = "$MODULE_DIR/composer.json";
    if (!file_exists($filename)) {
        info("No composer.json found in $MODULE_DIR though that's probably OK");
        return;
    }
    $json = json_decode(file_get_contents($filename), true);
    if (!$json) {
        error("Failed to parse json in $filename");
    }
    $version = $json['require-dev']['phpunit/phpunit'] ?? null;
    if (is_null($version)) {
        return;
    }
    if ($version !== '^9.5') {
        return;
    }
    $json['require-dev']['phpunit/phpunit'] = '^9.6';
    $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    writeFile($filename, json_encode($json, $flags));
})();
