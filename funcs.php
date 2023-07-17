<?php

use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Style\SymfonyStyle;

function getScriptFiles($cmsMajor)
{
    if (!ctype_digit($cmsMajor)) {
        $cmsMajor = "-$cmsMajor";
    }
    $scriptFiles = [];
    $dir = "scripts/cms$cmsMajor";
    if ($handle = opendir($dir)) {
        while (false !== ($scriptFile = readdir($handle))) {
            if ('.' === $scriptFile || '..' === $scriptFile) {
                continue;
            }
            $scriptFiles[] = "$dir/$scriptFile";
        }
        closedir($handle);
    }
    return $scriptFiles;
}

function writeTemplateFileEvenIfExists($filename, $content)
{
    global $MODULE_DIR;
    $content = prepareContent($content);
    writeFile("$MODULE_DIR/$filename", $content);
}

function writeTemplateFileIfNotExists($filename, $content)
{
    global $MODULE_DIR;
    $content = prepareContent($content);
    if (!file_exists("$MODULE_DIR/$filename")) {
        writeFile("$MODULE_DIR/$filename", $content);
    }
}

function getSupportedModules($cmsMajor)
{
    $url = "https://raw.githubusercontent.com/silverstripe/supported-modules/$cmsMajor/modules.json";
    $json = json_decode(file_get_contents($url), true);
    $supportedModules = [];
    foreach ($json as $module) {
        $supportedModules[] = [
            'cloneUrl' => 'git@github.com:' . $module['github'] . '.git',
            'branch' => max($module['branches'])
        ];
    }
    return $supportedModules;
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
    // using writeln with <info> instead of ->info() so that it only takes up one line instead of five
    getIo()->writeln("<info>$message</>");
}

function warning($message)
{
    getIo()->warning($message);
}

function error($message)
{
    // Don't throw hard exception here, instead let the rest of this script run
    getIo()->error($message);
}

function getIo(): SymfonyStyle
{
    global $IN;
    global $OUT;
    return new SymfonyStyle($IN ?: new ArgvInput(), $OUT ?: new NullOutput);
}
