#!/usr/bin/env php
<?php

/*
 * LibreNMS
 *
 * Copyright (c) 2014 Neil Lathwood <https://github.com/laf/ http://www.lathwood.co.uk/fa>
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version.  Please see LICENSE.txt at the top level of
 * the source code distribution for details.
 */

use App\Facades\LibrenmsConfig;
use LibreNMS\ValidationResult;
use LibreNMS\Validator;

chdir(__DIR__); // cwd to the directory containing this script

ini_set('display_errors', 1);

$options = getopt('g:m:s::h::');

if (isset($options['h'])) {
    echo
    "\n Validate setup tool

    Usage: ./validate.php [-g <group>] [-s] [-h]
        -h This help section.
        -s Print the status of each group
        -g Any validation groups you want to run, comma separated:
          Non-default groups:
          - mail: this will test your email settings  (uses default_mail option even if default_only is not set)
          - distributedpoller: this will test for the install running as a distributed poller
          - rrdcheck: this will check to see if your rrd files are corrupt
          Default groups:
          - configuration: checks various config settings are correct
          - database: checks the database for errors
          - dependencies: checks that all required libraries are installed and up-to-date
          - disk: checks for disk space and other disk related issues
          - php: check that various PHP modules and functions exist
          - poller: check that the poller and discovery are running properly
          - programs: check that external programs exist and are executable
          - python: check that various Python modules and functions exist
          - system: checks system related items
          - updates: checks the status of git and updates
          - user: check that the LibreNMS user is set properly

        Example: ./validate.php -g mail.

        ";
    exit(1);
}

if (function_exists('posix_getuid') && posix_getuid() === 0) {
    echo 'Do not run validate.php as root' . PHP_EOL;
    exit(1);
}

// Check autoload
if (! file_exists('vendor/autoload.php')) {
    print_fail('Composer has not been run, dependencies are missing', './scripts/composer_wrapper.php install --no-dev');
    exit(1);
}

require_once 'vendor/autoload.php';
require_once 'includes/common.php';
require_once 'includes/functions.php';

// Buffer output
ob_start();
$precheck_complete = false;
register_shutdown_function(function () {
    global $precheck_complete;

    if (! $precheck_complete) {
        // use this in case composer autoloader isn't available
        spl_autoload_register(function ($class) {
            @include str_replace('\\', '/', $class) . '.php';
        });
        print_header();
    }
});

$pre_checks_failed = false;

// config.php checks
if (file_exists('config.php')) {
    $syntax_check = `php -ln config.php`;
    if (strpos($syntax_check, 'No syntax errors detected') === false) {
        print_fail('Syntax error in config.php');
        echo $syntax_check;
        $pre_checks_failed = true;
    }

    $first_line = rtrim(`head -n1 config.php`);
    if (! strpos($first_line, '<?php') === 0) {
        print_fail("config.php doesn't start with a <?php - please fix this ($first_line)");
        $pre_checks_failed = true;
    }
    if (strpos(`tail config.php`, '?>') !== false) {
        print_fail('Remove the ?> at the end of config.php');
        $pre_checks_failed = true;
    }
}

// Composer check
$validator = new Validator();
$validator->validate(['dependencies']);
if ($validator->getGroupStatus('dependencies') == ValidationResult::FAILURE) {
    $pre_checks_failed = true;
}

if ($pre_checks_failed) {
    exit(1);
}

$init_modules = ['nodb'];
require 'includes/init.php';

// make sure install_dir is set correctly, or the next includes will fail
if (! file_exists(LibrenmsConfig::get('install_dir') . '/.env')) {
    $suggested = realpath(__DIR__);
    print_fail('\'install_dir\' config setting is not set correctly.', "It should probably be set to: $suggested");
    exit(1);
}

$precheck_complete = true; // disable shutdown function
print_header();

if (isset($options['g'])) {
    $modules = explode(',', $options['g']);
} elseif (isset($options['m'])) {
    $modules = explode(',', $options['m']); // backwards compat
} else {
    $modules = []; // all modules
}

// the code below may not show the database check above, always print it
if (! in_array('database', $modules)) {
    $validator->printResults('database');
}

// run checks
$validator->validate($modules, isset($options['s']) || ! empty($modules));

exit($validator->getStatus() ? 0 : 1);

function print_header(): void
{
    $output = '';

    if (ob_get_level() > 0) {
        $output = ob_get_contents();
        ob_end_clean();
    }

    echo \LibreNMS\Util\Version::get()->header() . PHP_EOL;
    echo $output;
}

// output matches that of ValidationResult
function print_fail($msg, $fix = null)
{
    echo "[\033[31;1mFAIL\033[0m]  $msg";
    if ($fix && strlen($msg) > 72) {
        echo PHP_EOL . '       ';
    }

    if (! empty($fix)) {
        echo " [\033[34;1mFIX\033[0m] \033[34;1m$fix\033[0m";
    }
    echo PHP_EOL;
}
