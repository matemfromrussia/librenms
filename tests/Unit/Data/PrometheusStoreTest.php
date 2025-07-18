<?php

/**
 * PrometheusStoreTest.php
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
 * @copyright  2018 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\Tests\Unit\Data;

use App\Facades\LibrenmsConfig;
use App\Models\Device;
use Illuminate\Support\Facades\Http as LaravelHttp;
use LibreNMS\Data\Store\Prometheus;
use LibreNMS\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('datastores')]
class PrometheusStoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        LibrenmsConfig::set('prometheus.enable', true);
        LibrenmsConfig::set('prometheus.url', 'http://fake:9999');
    }

    public function testFailWrite(): void
    {
        LaravelHttp::fakeSequence()->push('Bad response', 422);
        $prometheus = app(Prometheus::class);

        \Log::shouldReceive('debug');
        \Log::shouldReceive('error')->once()->with('Prometheus Error: Bad response');
        $prometheus->write('none', ['one' => 1]);
    }

    public function testSimpleWrite(): void
    {
        LaravelHttp::fake([
            '*' => LaravelHttp::response(),
        ]);

        $prometheus = app(Prometheus::class);

        $measurement = 'testmeasure';
        $tags = ['ifName' => 'testifname', 'type' => 'testtype'];
        $fields = ['ifIn' => 234234, 'ifOut' => 53453];
        $meta = ['device' => new Device(['hostname' => 'testhost'])];

        \Log::shouldReceive('debug');
        \Log::shouldReceive('error')->times(0);

        $prometheus->write($measurement, $fields, $tags, $meta);

        LaravelHttp::assertSentCount(1);
        LaravelHttp::assertSent(function (\Illuminate\Http\Client\Request $request) {
            return $request->method() == 'POST' &&
                $request->url() == 'http://fake:9999/metrics/job/librenms/instance/testhost/measurement/testmeasure/ifName/testifname/type/testtype' &&
                $request->body() == "ifIn 234234\nifOut 53453\n";
        });
    }
}
