# Module standardiser

This is a tool to standardise some files in Silverstripe modules. It's a essentially a script that runs on a developers laptop and create a number of pull-requests.

**This is intended for use only by Silverstripe core committers or the Silverstripe Ltd CMS Squad.**

## Usage

```bash
git clone silverstripe/module-standardiser
cd module-standardiser
composer install
php run.php update
```

## Command line options:

# TODO make this a table

* `--reset` - Delete _data and _modules dirs
* `--dry-run` - Do not push to github or create pull-requests
* `--only` - Only include the specified modules (without account prefix) separated by commas e.g. silverstripe-config,silverstripe-assets
