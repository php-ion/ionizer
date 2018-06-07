PHP ION wrapper
===

Ionize your PHP code!

# TL;DR

This library automatically configure and setup the [php ion](https://github.com/php-ion/php-ion) extension.
Using command line you may start scripts with [php ion](https://github.com/php-ion/php-ion) extension without installation of package in system. Also ionizer allows maintain php-ion versions and configurations.

# Install

`composer global require php-ion/ionizer`

# Basic Usage

* `ion run <file.php>` — parse and execute the specified file, like `php <file.php>`
* `ion eval <php-code>` — evaluate a string as PHP code, like `php -r <code>`
* `ion info` — show summary info
* `ion versions`, `ion versions all` - show available versions
* `ion version <ion-version>` - switch to specific version
* and more, see `ion help`

# Advanced usage

Build extension: 

* `ion build` build extension
  * `ion build .`, `ion build /tmp/php-ion-src/`
  * `ion build master`, `ion build 0.8.3`, `ion build 33b1e417`
* `ion test` test extension
  * `ion test` test the current extension
  * `ion test .`, `ion test /tmp/php-ion-src/`
  * `ion test master`, `ion test 0.8.3`, `ion test 33b1e417`
* `ion clean` clean after build