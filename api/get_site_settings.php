<?php
require_once 'cors_middleware.php';

$settingsFile = __DIR__ . '/data/super_settings.json';
$DEFAULTS = [
    'siteName' => 'ElectroCom',
    'phone1' => '0536683393',
    'phone2' => '0506408074',
    'whatsapp' => '233536683393',
    'siteEmail' => 'admin@electrocom.gh',
    'maintenanceMode' => false
];

$stored = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [];
$merged = array_merge($DEFAULTS, $stored ?? []);

// Filter only public keys
$publicKeys = ['siteName', 'phone1', 'phone2', 'whatsapp', 'siteEmail', 'maintenanceMode'];
$publicSettings = array_intersect_key($merged, array_flip($publicKeys));

header('Content-Type: application/json');
echo json_encode(['success' => true, 'data' => $publicSettings]);
