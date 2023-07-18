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
$PULL_REQUESTS_CREATED = [];
$OUT = null;

$app = new Application();
$app->register('update')
    ->addOption('branch', null, InputOption::VALUE_NONE, trim(<<<EOT
        next-minor (default)
        next-patch
        last-major-next-patch
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

        // branch
        $branch = $input->getOption('branch');
        if (!in_array($branch, ['next-minor', 'next-patch', 'last-major-next-patch'])) {
            $branch = 'next-minor';
        }

        // dirs
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

        // cmsMajor
        // @todo detect cms major to use based on command line args
        // works out default branch // default major // diff - see gha-merge-up
        $cmsMajor = '5';

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
            // work out module branch to checkout
            if ($branch === 'next-patch') {
                // @todo
                error('branch=next-patch is not supported yet');
                $checkoutBranch = 'todo';
            } elseif ($branch === 'last-major-next-patch') {
                // @todo
                error('branch=last-major-next-patch is not supported yet');
                $checkoutBranch = 'todo';
            } else {
                // next-minor (default)
                // assuming that default branch is the next-minor for now
                // @todo: don't do this
                $cmd = "git symbolic-ref refs/remotes/origin/HEAD | sed 's@^refs/remotes/origin/@@'";
                $checkoutBranch = cmd($cmd, $MODULE_DIR);
            }

            // checkout the base branch - this is important if not using the --reset option
            cmd("git checkout $checkoutBranch", $MODULE_DIR);
            $timestamp = time();

            // create a new branch used for the pull-request
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
                    $url = "https://api.github.com/repos/$account/$repo/pulls";
                    $data = json_encode([
                        'title' => $prTitle,
                        'body' => $prDescripton,
                        'head' => "$prAccount:$prBranch",
                        'base' => $checkoutBranch,
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    $token = githubToken();
                    $headers = [
                        'User-Agent: silverstripe-module-standardiser',
                        'Accept: application/vnd.github+json',
                        "Authorization: Bearer $token",
                        'X-GitHub-Api-Version: 2022-11-28'
                    ];
                    list($response, $httpcode) = curlPost($url, $data, $headers);
                    if ($httpcode === 201) {
                        $json = json_decode($response, true);
                        $prUrl = $json['html_url'];
                        $PULL_REQUESTS_CREATED[] = $prUrl;
                        info("Created pull-request for $repo");
                    } else {
                        warning("HTTP code $httpcode returned from github api");
                        warning($response);
                        error("Failed to create pull-request for $repo");
                    }
                }
            }
        }
        outputPullRequestsCreated();
        return Command::SUCCESS;
    });
$app->run();
