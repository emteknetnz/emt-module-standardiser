<?php

include 'funcs.php';

// global variable
$MODULE_DIR = '';

$scriptFiles = array_merge(
    getScriptFiles('any'),
    // @todo detect cms major to use based on command line args
    // works out default branch // default major // diff - see gha-merge-up
    getScriptFiles('5'),
);

// @todo - get modules from  funcs.php::getSupportedModules($cmsMajor)
$modules = [
    'silverstripe-config'
];
foreach ($modules as $module) {
    $MODULE_DIR = "_modules/$module";
    foreach ($scriptFiles as $scriptFile) {
        include $scriptFile;
    }
}
