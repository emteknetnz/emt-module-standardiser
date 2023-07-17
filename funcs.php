<?php

function writeTemplateFile($filename, $content)
{
    global $TARGET_DIR;
    $content = prepareContent($content);
    writeFile("$TARGET_DIR/$filename", $content);
}

function writeTemplateFileIfNotExists($filename, $content)
{
    global $TARGET_DIR;
    $content = prepareContent($content);
    if (!file_exists("$TARGET_DIR/$filename")) {
        writeFile("$TARGET_DIR/$filename", $content);
    }
}

function prepareContent($content)
{
    return trim($content) . "\n";
}

function writeFile($filename, $contents)
{
    file_put_contents($filename, $contents);
    info("Wrote to $filename");
}

function info($message)
{
    // TODO: use symfony console instead
    echo "\n$message\n\n";
}

function error($message)
{
    // Don't throw hard exception here, instead let the rest of this script run
    // TODO: use symfony console instead
    echo "\n!! $message\n\n";
}