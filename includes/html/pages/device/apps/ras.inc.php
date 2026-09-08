<?php
$app_data = $app['data'] ?? [];
$dimms = $app_data['dimms'] ?? [];

if (! empty($dimms)) {
    echo '<div class="panel panel-default">
    <div class="panel-heading"><h3 class="panel-title">DIMM Error Counts</h3></div>
    <table class="table table-condensed table-striped">
    <thead><tr><th>Slot</th><th>Size</th><th>Corrected</th><th>Uncorrectable</th></tr></thead>
    <tbody>';

    foreach ($dimms as $dimm) {
        $ce = (int) ($dimm['ce'] ?? 0);
        $ue = (int) ($dimm['ue'] ?? 0);
        $row_class = $ue > 0 ? 'danger' : ($ce > 0 ? 'warning' : '');

        echo '<tr class="' . $row_class . '">';
        echo '<td>' . htmlspecialchars($dimm['locator'] ?? '?') . '</td>';
        echo '<td>' . (int) ($dimm['size_mb'] ?? 0) . ' MB</td>';
        echo '<td>' . $ce . '</td>';
        echo '<td>' . $ue . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table></div>';

    if (($app_data['label_source'] ?? '') !== 'dmi') {
        echo '<div class="alert alert-info">Board slot names are unavailable for this host, '
            . 'so EDAC coordinates are shown instead. Firmware either omits the DMI bank '
            . 'locators or disagrees with EDAC about which slots are populated.</div>';
    }
    if (! empty($app_data['dmi_dimm_count'])
        && $app_data['dmi_dimm_count'] != ($app_data['dimm_count'] ?? 0)) {
        echo '<div class="alert alert-warning">EDAC reports '
            . (int) $app_data['dimm_count'] . ' populated DIMMs but firmware reports '
            . (int) $app_data['dmi_dimm_count'] . '. Firmware is normally the correct one.</div>';
    }
}

if (! empty($app_data['bert_severity'])) {
    echo '<div class="alert alert-danger">Firmware reported a '
        . htmlspecialchars($app_data['bert_severity'])
        . ' hardware error on <strong>'
        . htmlspecialchars($app_data['bert_fru'] ?: 'an unknown FRU')
        . '</strong> during the previous boot.</div>';
}

$graphs = [
    'ras_errors' => 'Memory Errors - Host Total',
    'ras_dimms' => 'Memory Errors - Corrected per DIMM',
    'ras_worst_dimm' => 'Memory Errors - Worst DIMM',
    'ras_events' => 'Hardware Error Events (24h)',
    'ras_pcie' => 'PCIe Advanced Error Reporting',
];

foreach ($graphs as $key => $text) {
    $graph_type = $key;
    $graph_array['height'] = '100';
    $graph_array['width'] = '215';
    $graph_array['to'] = \App\Facades\LibrenmsConfig::get('time.now');
    $graph_array['id'] = $app['app_id'];
    $graph_array['type'] = 'application_' . $key;

    echo '<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title">' . $text . '</h3>
    </div>
    <div class="panel-body">
    <div class="row">';
    include 'includes/html/print-graphrow.inc.php';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}
