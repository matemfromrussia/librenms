<?php

$unit_text = 'resp_3xx_other';
$descr = 'resp_3xx_other';
$ds = 'resp_3xx_other';

$rrd_filename = Rrd::name($device['hostname'], ['app', $app->app_type, $app->app_id]);

require 'includes/html/graphs/generic_stats.inc.php';
