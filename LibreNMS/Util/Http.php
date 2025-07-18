<?php

/**
 * Http.php
 *
 * -Description-
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
 * @copyright  2022 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\Util;

use App\Facades\LibrenmsConfig;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http as LaravelHttp;

class Http
{
    /**
     * Create a new client with proxy set if appropriate and a distinct User-Agent header
     */
    public static function client(): PendingRequest
    {
        return LaravelHttp::withOptions([
            'proxy' => [
                'http' => Proxy::http(),
                'https' => Proxy::https(),
                'no' => Proxy::ignore(),
            ],
        ])->withHeaders([
            'User-Agent' => LibrenmsConfig::get('project_name') . '/' . Version::VERSION, // we don't need fine version here, just rough
        ]);
    }
}
