# Module standardiser

This tools standardises some files in Silverstripe modules.

It's script that's intended to run on a developers laptop and create a number of pull-requests in GitHub.

It will run across all modules in [supported-modules](https://github.com/silverstripe/supported-modules) list.

**This is intended for use only by Silverstripe core committers or the Silverstripe Ltd CMS Squad.**

## Usage

```bash
git clone silverstripe/module-standardiser
cd module-standardiser
composer install
php run.php update --branch=next-minor
```

## Command line options:

| Flag | Description |
| ---- | ------------|
| --branch | Branch type to use - next-minor | next-patch | last-major-next-patch (default: next-minor) |
| --dry-run | Do not push to github or create pull-requests |
| --account | GitHub account to use for creating pull-requests (defaut: creative-commoners) |
| --only | Only include the specified modules (without account prefix) separated by commas e.g. silverstripe-config,silverstripe-assets |
| --exclude | Exclude the specified modules (without account prefix) separated by commas e.g. silverstripe-mfa,silverstripe-totp |
| --no-delete | Do not delete _data and _modules dirs before running |

## 
