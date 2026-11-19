# Dependencies and security updates

## What is installed

| Component | Runtime | Dev |
|---|---|---|
| main-application | `guzzlehttp/guzzle` ^7, `twig/twig` ^3 | `phpunit/phpunit` ^9 |
| endpoint | none | `phpunit/phpunit` ^9 |

Twig is required but the views are plain PHP; it is kept for now.
There are no Node/npm dependencies (an unused `jsdom` was removed in
November 2026).

## Lock files are not versioned

Both `composer.lock` files are git-ignored. `composer install` therefore picks
the newest versions that fit `composer.json` **and the PHP version running
Composer**. Consequences:

- Run Composer with the PHP version the app runs on (8.1+).
- After every install or update, run `composer audit`.
- `test-installation/composer.lock` is the only tracked lock file.

## Routine update

```bash
cd main-application
composer update -W
composer audit
vendor/bin/phpunit

cd ../endpoint
composer update -W
composer audit
```

Then open the dashboard and one comparison to check that the app still works.

## GitHub alerts

- **Dependabot** watches the root repository (`dbmysync-app`) and opens pull
  requests for tracked lock files. Since only `test-installation/composer.lock`
  is tracked, most alerts come from files that are no longer in the repository
  and disappear after a while.
- The alert count printed by `git push` is GitHub's cached value; check the
  current state at
  `https://github.com/Jensovic/dbmysync-app/security/dependabot`.
- `composer audit` locally is the reliable check for what is actually
  installed.
