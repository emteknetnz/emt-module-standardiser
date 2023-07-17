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
    ->addOption('reclone', 'r', InputOption::VALUE_NONE, 'Delete and reclone modules in _modules dir')
    ->setCode(function (InputInterface $input, OutputInterface $output): int {
        global $MODULE_DIR;
        global $OUT;
        $OUT = $output;
        $modulesDir = '_modules';
        // branch
        $branch = $input->getOption('branch');
        if (!in_array($branch, ['next-minor', 'next-patch', 'last-major-next-patch'])) {
            $branch = 'next-minor';
        }
        // _module dir
        if ($input->getOption('reclone')) {
            unlink($modulesDir);
        }
        if (!file_exists($modulesDir)) {
            mkdir($modulesDir);
        }
        // run
        // @todo - get modules from  funcs.php::getSupportedModules($cmsMajor)
        $modules = [
            'silverstripe-config'
        ];
        $scriptFiles = array_merge(
            getScriptFiles('any'),
            // @todo detect cms major to use based on command line args
            // works out default branch // default major // diff - see gha-merge-up
            getScriptFiles('5'),
        );
        foreach ($modules as $module) {
            $MODULE_DIR = "$modulesDir/$module";
            foreach ($scriptFiles as $scriptFile) {
                include $scriptFile;
            }
        }
        return Command::SUCCESS;
    });
$app->run();

