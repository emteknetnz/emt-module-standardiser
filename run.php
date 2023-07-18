<?php

include 'vendor/autoload.php';
include 'funcs.php';

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

// consts
const CURRENT_CMS_MAJOR = '5';

// global variables
$MODULE_DIR = '';
$PULL_REQUESTS_CREATED = [];
$OUT = null;

$app = new Application();
$app->register('update')
    ->addOption('branch', null, InputOption::VALUE_REQUIRED, trim(<<<EOT
        next-major-next-minor - use the default branch plus 1
        next-minor - will use the default branch of the repo (default)
        next-patch - will use the highest minor branch that matches the default branch 
        last-major-next-minor - will use the default branch minus 1
        last-major-next-patch - will use the highest minor branches the default branch minus 1
    EOT))
    ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not push to github or create pull-requests')
    ->addOption('account', null, InputOption::VALUE_REQUIRED, 'GitHub account to use for creating pull-requests (default: creative-commoners)')
    ->addOption('only', null, InputOption::VALUE_REQUIRED, 'Only include the specified modules (without account prefix) separated by commas e.g. silverstripe-config,silverstripe-assets')
    ->addOption('exclude', null, InputOption::VALUE_REQUIRED, 'Exclude the specified modules (without account prefix) separated by commas e.g. silverstripe-mfa,silverstripe-totp')
    ->addOption('no-delete', null, InputOption::VALUE_NONE, 'Do not delete _data and _modules dirs before running')
    ->setCode(function (InputInterface $input, OutputInterface $output): int {

        // variables
        global $MODULE_DIR, $OUT, $PULL_REQUESTS_CREATED;
        $OUT = $output;
        $dataDir = '_data';
        $modulesDir = '_modules';
        $prTitle = 'MNT Run module-standardiser';
        $toolUrl = 'https://github.com/silverstripe/module-standardiser';
        $prDescripton = "Created using [$toolUrl]($toolUrl)";

        // make sure everything setup correctly
        validateSystem();

        // setup dirs
        if (!$input->getOption('no-delete')) {
            removeDir($dataDir);
            removeDir($modulesDir);
        }
        if (!file_exists($dataDir)) {
            mkdir($dataDir);
        }
        if (!file_exists($modulesDir)) {
            mkdir($modulesDir);
        }

        // branch
        $branchOption = $input->getOption('branch');
        if (!in_array($branchOption, [
            'next-major-next-minor',
            'next-minor',
            'next-patch',
            'last-major-next-minor',
            'last-major-next-patch'
        ])) {
            $branchOption = 'next-minor';
        }

        // work out the CMS major version to use
        // there is an assumption that the default branch for repos being updated matches the current CMS major version
        $cmsMajor = CURRENT_CMS_MAJOR;
        if (strpos($branchOption, 'last-major') !== false) {
            $cmsMajor = CURRENT_CMS_MAJOR - 1;
        } elseif (strpos($branchOption, 'next-major') !== false) {
            $cmsMajor = CURRENT_CMS_MAJOR + 1;
        }

        // modules
        $modules = getSupportedModules($cmsMajor);
        if ($input->getOption('only')) {
            $only = explode(',', $input->getOption('only'));
            $modules = array_filter($modules, function ($module) use ($only) {
                return in_array($module['repo'], $only);
            });
        }
        if ($input->getOption('exclude')) {
            $exclude = explode(',', $input->getOption('exclude'));
            $modules = array_filter($modules, function ($module) use ($exclude) {
                return !in_array($module['repo'], $exclude);
            });
        }

        // script files
        $scriptFiles = array_merge(
            getScriptFiles('any'),
            getScriptFiles($cmsMajor),
        );

        // clone repos & run scripts
        foreach ($modules as $module) {
            $account = $module['account'];
            $repo = $module['repo'];
            $cloneUrl = $module['cloneUrl'];
            $MODULE_DIR = "$modulesDir/$repo";
            if (!file_exists($MODULE_DIR)) {
                cmd("git clone $cloneUrl", $modulesDir);
            }

            // get default branch
            $cmd = "git symbolic-ref refs/remotes/origin/HEAD | sed 's@^refs/remotes/origin/@@'";
            $defaultBranch = cmd($cmd, $MODULE_DIR);
            
            // get all branches
            // https://docs.github.com/en/rest/branches/branches?apiVersion=2022-11-28#list-branches
            $allBranches = explode("\n", cmd('git branch -r', $MODULE_DIR));
            $allBranches = array_map(fn($branch) => trim(str_replace('origin/', '', $branch)), $allBranches);

            // work out module branch to checkout
            $checkoutBranch = checkoutBranch($allBranches, $branchOption, $defaultBranch);
            if (!in_array($checkoutBranch, $allBranches)) {
                error("Could not find branch to checkout for $repo using --branch=$branchOption");
            }

            // checkout the base branch - this is important if re-running while using the --no-delete option
            cmd("git checkout $checkoutBranch", $MODULE_DIR);

            // create a new branch used for the pull-request
            $timestamp = time();
            $prBranch = "pulls/$checkoutBranch/module-standardiser-$timestamp";
            cmd("git checkout -b $prBranch", $MODULE_DIR);

            // run scripts on $MODULE_DIR
            foreach ($scriptFiles as $scriptFile) {
                include $scriptFile;
            }

            // set git remote
            $prAccount = $input->getOption('account') ?? 'creative-commoners';
            $origin = cmd('git remote get-url origin', $MODULE_DIR);
            $prOrigin = str_replace("git@github.com:$account", "git@github.com:$prAccount", $origin);
            // remove any existing pr-remote - need to do this in case we change the account option
            $remotes = explode("\n", cmd('git remote', $MODULE_DIR));
            if (in_array('pr-remote', $remotes)) {
                cmd('git remote remove pr-remote', $MODULE_DIR);
            }
            cmd("git remote add pr-remote $prOrigin", $MODULE_DIR);

            // commit changes, push changes and create pull-request
            $status = cmd('git status', $MODULE_DIR);
            if (strpos($status, 'nothing to commit') !== false) {
                info("No changes to commit for $repo");
            } else {
                cmd('git add .', $MODULE_DIR);
                cmd("git commit -m '$prTitle'", $MODULE_DIR);
                if ($input->getOption('dry-run')) {
                    info('Not pushing changes or creating pull-request because --dry-run option is set');
                } else {
                    // push changes to pr-remote
                    cmd("git push -u pr-remote $prBranch", $MODULE_DIR);
                    // create pull-request using github api
                    // https://docs.github.com/en/rest/pulls/pulls?apiVersion=2022-11-28#create-a-pull-request
                    $responseJson = gitHubApi("https://api.github.com/repos/$account/$repo/pulls", [
                        'title' => $prTitle,
                        'body' => $prDescripton,
                        'head' => "$prAccount:$prBranch",
                        'base' => $checkoutBranch,
                    ]);
                    $PULL_REQUESTS_CREATED[] = $responseJson['html_url'];
                    info("Created pull-request for $repo");
                }
            }
        }
        outputPullRequestsCreated();
        return Command::SUCCESS;
    });
$app->run();
