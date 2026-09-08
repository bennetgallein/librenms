<?php

/**
 * Corrected error count per DIMM.
 *
 * The poller writes one RRD per DIMM, named by its board locator, so this
 * graph enumerates the RRD files rather than re-reading the device.
 */

require 'includes/html/graphs/common.inc.php';

$colours = 'mixed';
$nototal = (($width < 224) ? 1 : 0);
$unit_text = 'Corrected';

$prefix = Rrd::name($device['hostname'], ['app', 'ras', 'dimm', $app->app_id, '']);
$prefix = substr($prefix, 0, -strlen('.rrd'));
$matches = glob($prefix . '*.rrd');

if (empty($matches)) {
    throw new \LibreNMS\Exceptions\RrdGraphException('No per-DIMM data files found');
}

sort($matches);

$i = 0;
foreach ($matches as $filename) {
    $locator = substr(basename($filename, '.rrd'), strlen(basename($prefix)));
    if ($locator === '') {
        continue;
    }

    $rrd_list[$i]['filename'] = $filename;
    $rrd_list[$i]['descr'] = $locator;
    $rrd_list[$i]['ds'] = 'ce';
    $rrd_list[$i]['colour'] = \App\Facades\LibrenmsConfig::get("graph_colours.$colours.$i");
    $i++;
}

if ($i === 0) {
    throw new \LibreNMS\Exceptions\RrdGraphException('No per-DIMM data files found');
}

require 'includes/html/graphs/generic_multi_line.inc.php';
