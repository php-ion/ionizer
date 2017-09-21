PHP ION wrapper
===

Ionize your PHP code!

# TL;DR

This library automatically configure and setup the [php ion](https://github.com/php-ion/php-ion) extension.
Using command line you may start scripts with [php ion](https://github.com/php-ion/php-ion) extension without installation of package in system. Also ionizer allows maintain php-ion versions and configurations.

# Install

`composer global require php-ion/ionizer`

# Usage

* `ion run <file.php>` — parse and execute the specified file, like `php <file.php>`
* `ion eval <php-code>` — evaluate a string as PHP code, like `php -r <code>`
* `ion info` — show summary info
* `ion versions`, `ion versions all` - show available versions
* `ion version <ion-version>` - switch to specific version

See more: `ion help`
