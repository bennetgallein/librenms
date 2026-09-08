<?php
/**
 * Poller module for the RAS application.
 *
 * Parses JSON from the `ras` SNMP extend script, which reports hardware
 * errors from the EDAC sysfs tree and the rasdaemon event database.
 *
 * EDAC counters reset at every boot, so every dataset is a GAUGE. A drop to
 * zero means the host rebooted, not that the errors were repaired.
 */

use LibreNMS\Exceptions\JsonAppException;
use LibreNMS\RRD\RrdDefinition;

$name = 'ras';

try {
    $result = json_app_get($device, $name, 1);
} catch (JsonAppException $e) {
    echo PHP_EOL . $name . ':' . $e->getCode() . ':' . $e->getMessage() . PHP_EOL;
    update_application($app, $e->getCode(), ['error' => $e->getMessage()]);
    return;
}

$data = $result['data'] ?? [];

// --- Host-wide error totals ---
$rrd_name = ['app', $name, 'errors', $app->app_id];
$rrd_def = RrdDefinition::make()
    ->addDataset('ce_total', 'GAUGE', 0)
    ->addDataset('ue_total', 'GAUGE', 0)
    ->addDataset('worst_ce', 'GAUGE', 0)
    ->addDataset('worst_ue', 'GAUGE', 0);

$fields = [
    'ce_total' => $data['ce_total'] ?? 0,
    'ue_total' => $data['ue_total'] ?? 0,
    'worst_ce' => $data['worst_ce'] ?? 0,
    'worst_ue' => $data['worst_ue'] ?? 0,
];

$tags = ['name' => $name, 'app_id' => $app->app_id, 'rrd_def' => $rrd_def, 'rrd_name' => $rrd_name];
app('Datastore')->put($device, 'app', $tags, $fields);

// --- Per-DIMM corrected error counts ---
$dimms = $data['dimms'] ?? [];
foreach ($dimms as $dimm) {
    $locator = $dimm['locator'] ?? $dimm['label'] ?? null;
    if (empty($locator)) {
        continue;
    }

    $dimm_rrd_name = ['app', $name, 'dimm', $app->app_id, $locator];
    $dimm_rrd_def = RrdDefinition::make()
        ->addDataset('ce', 'GAUGE', 0)
        ->addDataset('ue', 'GAUGE', 0);

    $dimm_fields = [
        'ce' => $dimm['ce'] ?? 0,
        'ue' => $dimm['ue'] ?? 0,
    ];

    $dimm_tags = [
        'name' => $name,
        'app_id' => $app->app_id,
        'dimm' => $locator,
        'rrd_def' => $dimm_rrd_def,
        'rrd_name' => $dimm_rrd_name,
    ];
    app('Datastore')->put($device, 'app', $dimm_tags, $dimm_fields);
}

// --- Decoded event history from rasdaemon ---
// These cover far more than memory: CPU machine checks, PCIe AER, block I/O
// errors, and NIC health reports.
$event_tables = [
    'mc_event', 'mce_record', 'aer_event', 'extlog_event',
    'devlink_event', 'disk_errors', 'memory_failure_event', 'non_standard_event',
];

$events = $data['events'] ?? [];
$events_24h = $data['events_24h'] ?? [];

$rrd_name = ['app', $name, 'events', $app->app_id];
$rrd_def = RrdDefinition::make();
foreach ($event_tables as $table) {
    $rrd_def->addDataset($table, 'GAUGE', 0);
}

$fields = [];
foreach ($event_tables as $table) {
    $fields[$table] = $events_24h[$table] ?? 0;
}

$tags = ['name' => $name, 'app_id' => $app->app_id, 'rrd_def' => $rrd_def, 'rrd_name' => $rrd_name];
app('Datastore')->put($device, 'app', $tags, $fields);

// --- PCIe Advanced Error Reporting ---
$rrd_name = ['app', $name, 'pcie', $app->app_id];
$rrd_def = RrdDefinition::make()
    ->addDataset('aer_ce', 'GAUGE', 0)
    ->addDataset('aer_ue', 'GAUGE', 0);

$fields = [
    'aer_ce' => $data['aer_ce'] ?? 0,
    'aer_ue' => $data['aer_ue'] ?? 0,
];

$tags = ['name' => $name, 'app_id' => $app->app_id, 'rrd_def' => $rrd_def, 'rrd_name' => $rrd_name];
app('Datastore')->put($device, 'app', $tags, $fields);

// --- Application metrics, used by alert rules ---
// application_metrics.value is numeric. Strings coerce to 0 there, so DIMM
// names and BERT text go into the JSON data column and the status line.
$metrics = [
    'ce_total' => $data['ce_total'] ?? 0,
    'ue_total' => $data['ue_total'] ?? 0,
    'worst_ce' => $data['worst_ce'] ?? 0,
    'worst_ue' => $data['worst_ue'] ?? 0,
    'dimm_count' => $data['dimm_count'] ?? 0,
    'dmi_dimm_count' => $data['dmi_dimm_count'] ?? 0,
    'edac_present' => $data['edac_present'] ?? 0,
    'rasdaemon' => $data['rasdaemon'] ?? 0,
    'bert_fatal' => $data['bert_fatal'] ?? 0,
    'aer_ce' => $data['aer_ce'] ?? 0,
    'aer_ue' => $data['aer_ue'] ?? 0,
];

foreach ($event_tables as $table) {
    $metrics[$table] = $events[$table] ?? 0;
    $metrics[$table . '_24h'] = $events_24h[$table] ?? 0;
}

// --- Human readable detail ---
$status_parts = [];
if (! empty($data['ue_total'])) {
    $status_parts[] = $data['ue_total'] . ' UE on ' . ($data['worst_ue_dimm'] ?: 'unknown DIMM');
}
if (! empty($data['ce_total'])) {
    $status_parts[] = $data['ce_total'] . ' CE on ' . ($data['worst_ce_dimm'] ?: 'unknown DIMM');
}
if (! empty($data['bert_fatal'])) {
    $status_parts[] = 'previous boot: fatal firmware error on ' . ($data['bert_fru'] ?: 'unknown FRU');
}
if (! empty($data['aer_ue'])) {
    $status_parts[] = $data['aer_ue'] . ' uncorrectable PCIe errors';
}
if (! empty($events_24h['disk_errors'])) {
    $status_parts[] = $events_24h['disk_errors'] . ' block I/O errors in 24h';
}
if (! empty($events_24h['mce_record'])) {
    $status_parts[] = $events_24h['mce_record'] . ' machine checks in 24h';
}
if (empty($data['rasdaemon'])) {
    $status_parts[] = 'rasdaemon stopped';
}
if (empty($status_parts)) {
    $status_parts[] = ($data['dimm_count'] ?? 0) . ' DIMMs, no errors';
}

$app->data = [
    'dimms' => $data['dimms'] ?? [],
    'label_source' => $data['label_source'] ?? 'edac',
    'dimm_count' => $data['dimm_count'] ?? 0,
    'dmi_dimm_count' => $data['dmi_dimm_count'] ?? 0,
    'events' => $events,
    'events_24h' => $events_24h,
    'worst_ce_dimm' => $data['worst_ce_dimm'] ?? '',
    'worst_ue_dimm' => $data['worst_ue_dimm'] ?? '',
    'bert_fru' => $data['bert_fru'] ?? '',
    'bert_severity' => $data['bert_severity'] ?? '',
];

update_application($app, 'OK', $metrics, implode('; ', $status_parts));
