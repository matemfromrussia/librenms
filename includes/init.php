<?php

/**
 * init.php
 *
 * Load includes and initialize needed things
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * @link       https://www.librenms.org
 *
 * @copyright  2016 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

/**
 * @param  array  $modules  Which modules to initialize
 */

use App\Facades\LibrenmsConfig;
use LibreNMS\Authentication\LegacyAuth;
use LibreNMS\Util\Debug;
use LibreNMS\Util\Laravel;

global $vars, $console_color;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

$install_dir = realpath(__DIR__ . '/..');
chdir($install_dir);

// composer autoload
if (! is_file($install_dir . '/vendor/autoload.php')) {
    require_once $install_dir . '/includes/common.php';
    c_echo("%RError: Missing dependencies%n, run: %B./scripts/composer_wrapper.php install --no-dev%n\n\n");
}
require_once $install_dir . '/vendor/autoload.php';

if (! function_exists('module_selected')) {
    function module_selected($module, $modules)
    {
        return in_array($module, (array) $modules);
    }
}

// function only files
require_once $install_dir . '/includes/common.php';
require_once $install_dir . '/includes/dbFacile.php';
require_once $install_dir . '/includes/syslog.php';
require_once $install_dir . '/includes/snmp.inc.php';
require_once $install_dir . '/includes/services.inc.php';
require_once $install_dir . '/includes/functions.php';
require_once $install_dir . '/includes/rewrites.php';

if (module_selected('web', $init_modules)) {
    require_once $install_dir . '/includes/html/functions.inc.php';
}

if (module_selected('discovery', $init_modules)) {
    require_once $install_dir . '/includes/discovery/functions.inc.php';
}

if (module_selected('polling', $init_modules)) {
    require_once $install_dir . '/includes/polling/functions.inc.php';
}

// Boot Laravel
if (module_selected('web', $init_modules)) {
    Laravel::bootWeb(module_selected('auth', $init_modules));
} else {
    Laravel::bootCli();
}
Debug::set($debug ?? false); // override laravel configured settings (hides legacy errors too)

if (! module_selected('nodb', $init_modules)) {
    if (! \LibreNMS\DB\Eloquent::isConnected()) {
        echo "Could not connect to database, check logs/librenms.log.\n";

        if (! extension_loaded('mysqlnd') || ! extension_loaded('pdo_mysql')) {
            echo "\nYour PHP is missing required mysql extension(s), please install and enable.\n";
            echo "Check the install docs for more info: https://docs.librenms.org/Installation/\n";
        }

        exit(1);
    }
}
\LibreNMS\DB\Eloquent::setStrictMode(false); // disable strict mode for legacy code...

if (is_numeric(LibrenmsConfig::get('php_memory_limit')) && LibrenmsConfig::get('php_memory_limit') > 128) {
    ini_set('memory_limit', LibrenmsConfig::get('php_memory_limit') . 'M');
}

try {
    LegacyAuth::get();
} catch (Exception $exception) {
    print_error('ERROR: no valid auth_mechanism defined!');
    echo $exception->getMessage() . PHP_EOL;
    exit;
}

if (module_selected('web', $init_modules)) {
    require $install_dir . '/includes/html/vars.inc.php';
}

$console_color = new Console_Color2();
