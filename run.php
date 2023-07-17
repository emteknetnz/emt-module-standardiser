<?php

include 'vendor/autoload.php';
include 'funcs.php';

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;


// global variables
$MODULE_DIR = '';
$OUT = null;

$app = new Application();
$app->register('update')
    ->addOption('branch', 'b', InputOption::VALUE_NONE, trim(<<<EOT
        next-minor (default)
        next-patch
        last-major-next-patch
    EOT))
    ->addOption('reset', 'r', InputOption::VALUE_NONE, 'Delete _data and _modules dirs')
    ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Dry-run - do not create pull-requests')
    ->addOption('module', 'm', InputOption::VALUE_REQUIRED, 'Only update the specified module e.g. silverstripe-config')
    ->setCode(function (InputInterface $input, OutputInterface $output): int {
        // variables
        global $MODULE_DIR, $OUT;
        $OUT = $output;
        $dataDir = '_data';
        $modulesDir = '_modules';

        // branch
        $branch = $input->getOption('branch');
        if (!in_array($branch, ['next-minor', 'next-patch', 'last-major-next-patch'])) {
            $branch = 'next-minor';
        }

        // dirs
        if ($input->getOption('reset')) {
            removeDir($dataDir);
            removeDir($modulesDir);
        }
        if (!file_exists($dataDir)) {
            mkdir($dataDir);
        }
        if (!file_exists($modulesDir)) {
            mkdir($modulesDir);
        }

        // cmsMajor
        // @todo detect cms major to use based on command line args
        // works out default branch // default major // diff - see gha-merge-up
        $cmsMajor = '5';

        // modules
        $modules = getSupportedModules($cmsMajor);
        if ($input->getOption('module')) {
            $modules = array_filter($modules, function ($module) use ($input) {
                return $module === $input->getOption('module');
            });
        }
        exit;

        // script files
        $scriptFiles = array_merge(
            getScriptFiles('any'),
            getScriptFiles($cmsMajor),
        );
        foreach ($modules as $module) {
            $MODULE_DIR = "$modulesDir/$module";
            if (!file_exists($MODULE_DIR)) {
                $cmd = "git clone {$module['cloneUrl']} $MODULE_DIR";
                $output->writeln($cmd);
                shell_exec($cmd);
            }
            foreach ($scriptFiles as $scriptFile) {
                include $scriptFile;
            }
        }

        // return status
        return Command::SUCCESS;
    });
$app->run();

