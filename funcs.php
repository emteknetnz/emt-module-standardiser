<?php

use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

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
    writeFile("$MODULE_DIR/$filename", $content);
}

function writeTemplateFileIfNotExists($filename, $content)
{
    global $MODULE_DIR;
    if (!file_exists("$MODULE_DIR/$filename")) {
        writeFile("$MODULE_DIR/$filename", $content);
    }
}

function getSupportedModules($cmsMajor)
{
    $filename = "_data/modules-cms$cmsMajor.json";
    if (!file_exists($filename)) {
        $url = "https://raw.githubusercontent.com/silverstripe/supported-modules/$cmsMajor/modules.json";
        info("Downloading $url to $filename");
        $contents = file_get_contents($url);
        file_put_contents($filename, $contents);
    }
    $json = json_decode(file_get_contents($filename), true);
    $modules = [];
    foreach ($json as $module) {
        $ghrepo = $module['github'];
        $modules[] = [
            'ghrepo' => $ghrepo,
            'account' => explode('/', $ghrepo)[0],
            'repo' => explode('/', $ghrepo)[1],
            'cloneUrl' => "git@github.com:$ghrepo.git",
            'branch' => max($module['branches'])
        ];
    }
    return $modules;
}

function writeFile($filename, $contents)
{
    $contents = trim($contents) . "\n";
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
    getIo()->error($message);
    outputPullRequestsCreated();
    die;
}

function cmd($cmd, $cwd)
{
    $message = "Running command: $cmd in $cwd";
    info($message);
    // using Process::fromShellCommandline() instead of new Process() so that pipes work
    $process = Process::fromShellCommandline($cmd, $cwd);
    $process->run();
    if (!$process->isSuccessful()) {
        warning("Error running command: $cmd in $cwd");
        error("Output was: " . $process->getErrorOutput());
    }
    return trim($process->getOutput());
}

function getIo(): SymfonyStyle
{
    global $IN, $OUT;
    return new SymfonyStyle($IN ?: new ArgvInput(), $OUT ?: new NullOutput);
}

function removeDir($dirname)
{
    if (!file_exists(($dirname))) {
        return;
    }
    info("Removing $dirname");
    shell_exec("rm -rf $dirname");
}

function validateSystem()
{
    if (!githubToken()) {
        error('Could not get github token');
    }
}

function githubToken()
{
    // using composer token that's assumed to be on users laptop
    // could also support passing in a token via env variable, though this should be good enough
    $auth = cmd('cat ~/.config/composer/auth.json', '.');
    if (!$auth) {
        return '';
    }
    $json = json_decode($auth, true);
    return $json['github-oauth']['github.com'] ?? '';
}

function curlPost($url, $data, $headers = [])
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$response, $httpcode];
}

function outputPullRequestsCreated()
{
    global $PULL_REQUESTS_CREATED;
    $io = getIo();
    $io->writeln('');
    $io->writeln('Pull requests created:');
    foreach ($PULL_REQUESTS_CREATED as $pr) {
        $io->writeln($pr);
    }
    $io->writeln('');
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
