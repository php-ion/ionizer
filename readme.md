PHP ION wrapper
===

Ionize your PHP code!

# TL;DR

This library automatically configure and setup the [php ion](https://github.com/php-ion/php-ion) extension.
Using command line you may start scripts with [php ion](https://github.com/php-ion/php-ion) extension without installation of package in system. Also ionizer allows maintain php-ion versions and configurations.

# Install

`composer global require php-ion/ionizer`

# Usage

* `ion run <file.php>`
* `ion eval <php-code>`
* `ion info`
* `ion versions`, `ion versions all`
* `ion version <ion-version>`


# More one thing

> How to detect what script started with ionizer

Check enviroment variable `IONIZER_STARTER` via `getenv('IONIZER_STARTER')`. Or check `ION` class.
