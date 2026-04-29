# Contributing

## Requirements

- PHP 8.2+
- Composer 2.x

## Setup

Install all dependencies (including dev tools):

```bash
composer install
```

## Coding Guidelines

This project follows the
[TYPO3 Core PER-CS coding standard](https://github.com/TYPO3/typo3/blob/main/Build/php-cs-fixer/config.php),
enforced via [PHP-CS-Fixer](https://cs.symfony.com/).

Check for violations (dry-run, no files modified):

```bash
composer cs:check
```

Fix all violations automatically:

```bash
composer cs:fix
```

## Static Analysis

This project uses [PHPStan](https://phpstan.org/) at level 8 for static
analysis. Run it after applying code style fixes:

```bash
composer php:analyze
```

When intentional deviations from the rules are introduced, regenerate the
baseline to record them:

```bash
composer php:analyze:generate-baseline
```

Commit the updated `phpstan-baseline.neon` alongside the code change.

## Testing

Run the complete test suite:

```bash
composer test
```

Run only unit tests:

```bash
composer test:unit
```

Run only functional (integration) tests:

```bash
composer test:functional
```

## Build

The build produces a self-contained PHAR binary. Because the DI container
uses runtime class discovery that is incompatible with PHAR streams, the
container must be pre-compiled to a PHP class before packaging.

The full build chain runs both steps in sequence:

```bash
composer build
```

This will:
1. Compile the DI container to `var/cache/SBReleaserContainer.php`
2. Package everything into `build/sb-releaser`

To compile the container separately (e.g. for debugging):

```bash
composer dump-container
```

The binary can be executed directly:

```bash
php build/sb-releaser <command>
```
