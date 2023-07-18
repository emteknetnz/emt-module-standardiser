<?php

// Use these functions in scripts

function writeTemplateFileEvenIfExists($filename, $content)
{
    global $MODULE_DIR;
    writeFile("$MODULE_DIR/$filename", $content);
}

function writeTemplateFileIfNotExists($filename, $content)
{
    global $MODULE_DIR;
    if (!file_exists("$MODULE_DIR/$filename")) {
        writeFile("$MODULE_DIR/$filename", $content);
    }
}

function writeFile($filename, $contents)
{
    $contents = trim($contents) . "\n";
    file_put_contents($filename, $contents);
    info("Wrote to $filename");
}

function isRecipe()
{
    global $MODULE_DIR;
    if (strpos('/recipe-', $MODULE_DIR) !== false) {
        return true;
    }
    if (strpos('/silverstripe-installer', $MODULE_DIR) !== false) {
        return true;
    }
    return false;
}

function info($message)
{
    // using writeln with <info> instead of ->info() so that it only takes up one line instead of five
    getIo()->writeln("<info>$message</>");
}

function warning($message)
{
    getIo()->warning($message);
}