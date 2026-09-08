<?php

/**
 * Hardware error events rasdaemon recorded in the last 24 hours, by class.
 */

require 'includes/html/graphs/common.inc.php';

$colours = 'mixed';
$nototal = (($width < 224) ? 1 : 0);
$unit_text = 'Events/24h';
$rrd_filename = Rrd::name($device['hostname'], ['app', 'ras', 'events', $app->app_id]);

$array = [
    'mc_event' => ['descr' => 'Memory'],
    'mce_record' => ['descr' => 'Machine Check'],
    'aer_event' => ['descr' => 'PCIe AER'],
    'disk_errors' => ['descr' => 'Block I/O'],
    'devlink_event' => ['descr' => 'NIC Health'],
    'extlog_event' => ['descr' => 'Firmware Log'],
    'memory_failure_event' => ['descr' => 'Page Offline'],
    'non_standard_event' => ['descr' => 'Vendor'],
];

$i = 0;

if (Rrd::checkRrdExists($rrd_filename)) {
    foreach ($array as $ds => $var) {
        $rrd_list[$i]['filename'] = $rrd_filename;
        $rrd_list[$i]['descr'] = $var['descr'];
        $rrd_list[$i]['ds'] = $ds;
        $rrd_list[$i]['colour'] = \App\Facades\LibrenmsConfig::get("graph_colours.$colours.$i");
        $i++;
    }
} else {
    throw new \LibreNMS\Exceptions\RrdGraphException("No Data file $rrd_filename");
}

require 'includes/html/graphs/generic_multi_line.inc.php';
