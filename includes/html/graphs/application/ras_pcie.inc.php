<?php

/**
 * PCIe Advanced Error Reporting, split by severity.
 *
 * Corrected errors are recovered by the link layer and are normal in small
 * numbers. Uncorrectable errors mean a device or link is failing.
 */

require 'includes/html/graphs/common.inc.php';

$colours = 'mixed';
$nototal = (($width < 224) ? 1 : 0);
$unit_text = 'PCIe Errors';
$rrd_filename = Rrd::name($device['hostname'], ['app', 'ras', 'pcie', $app->app_id]);

$array = [
    'aer_ce' => ['descr' => 'Corrected'],
    'aer_ue' => ['descr' => 'Uncorrectable'],
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
