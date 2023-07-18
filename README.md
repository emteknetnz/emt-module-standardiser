# Module standardiser

This tools standardises some files in Silverstripe modules that's intended to run on a developers laptop and create 
a number of pull-requests in GitHub.

It will run across all modules in [supported-modules](https://github.com/silverstripe/supported-modules) list and the 
relevant branch e.g. `5` will be used depending on the command-line `--branch` option that's passed in.

It will run all scripts in the `scripts/any` folder and then run all scripts in the applicable 
`scripts/<cms-version>` folder depending on the command-line `--branch` option that's passed in.

**This tool only is only intended for use by Silverstripe core committers or the Silverstripe Ltd CMS Squad**

## Requirements

It is assumed there is a composer GitHub API token stored in `~/.config/composer/auth.json`. This tool will not run 
if that token is not present.

## Usage

```bash
git clone silverstripe/module-standardiser
cd module-standardiser
composer install
php run.php update <options>
```

## Command line options:

| Flag | Description |
| ---- | ------------|
| --branch | next-major-next-minor - will use the default branch plus 1
             next-minor - will use the default branch of the repo (default)
             next-patch - will use the highest minor branch that matches the default branch 
             last-major-next-minor - will use the default branch minus 1
             last-major-next-patch - will use the highest minor branches the default branch minus 1 |
| --dry-run | Do not push to github or create pull-requests |
| --account | GitHub account to use for creating pull-requests (default: creative-commoners) |
| --only | Only include the specified modules (without account prefix) separated by commas e.g. silverstripe-config,silverstripe-assets |
| --exclude | Exclude the specified modules (without account prefix) separated by commas e.g. silverstripe-mfa,silverstripe-totp |
| --no-delete | Do not delete _data and _modules directories before running |

## GitHub API secondary rate limit

You may hit a secondary GitHub rate limit because this tool will create too many pull-requests. To help with this 
the tool will always output the urls of all pull-requests updated and also the repos that were updated so you can 
add them to the --exclude flag on subsequent re-runs.

## Adding new scripts

Simply add new scripts to either `scripts/cms-any` or `scripts/cms-<version>` and they will be automatically picked 
up and run when the tool is run.

Follow these guidelines when writing scripts:
- Wrap scripts in an anonymous function that calls itself to ensure that variables are not shared between scripts
- Add `global $MODULE_DIR;` at the top of the script if you need to read any existing files in the module. 
  `$MODULE_DIR` is a global variable that is set to the path of the module currently being processed
- Use functions in `funcs_scripts.php` such as `writeTemplateFileIfNotExists()` so that console output is consistent
- Do not use functions in `funcs_utils.php` as they are not intended to be used in scripts

## Updating the tool when a new major version of CMS is updated

Update the `CURRENT_CMS_MAJOR` constant in `run.php`
