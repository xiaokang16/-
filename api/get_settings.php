<?php
header('Content-Type: application/json');

$settingsFile = __DIR__ . '/../data/settings.json';
$settings = [];
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
}
echo json_encode($settings);