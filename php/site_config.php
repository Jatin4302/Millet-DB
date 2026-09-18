<?php
header('Content-Type: application/json; charset=utf-8');
$fail_hard = false;
require __DIR__ . '/db_connect.php';

$images = [];
$result = $conn->query('SELECT slot, image_path, alt_text FROM site_images ORDER BY slot');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $images[$row['slot']] = [
            'path' => $row['image_path'],
            'alt' => $row['alt_text']
        ];
    }
}

$settings = [
    'palette' => 'custom',
    'palette_ink' => '#173b32',
    'palette_deep' => '#12352e',
    'palette_muted' => '#688078',
    'palette_line' => '#d7e2dc',
    'palette_cream' => '#f5f5ec',
    'palette_lime' => '#d8e95a',
    'palette_orange' => '#f18b4e',
    'font_family' => 'manrope',
    'font_scale' => 'normal',
    'home_eyebrow' => 'Millet research platform',
    'home_title' => 'Exploring Millet Genetic Diversity Through SSR Markers',
    'home_intro' => 'A comprehensive research platform for exploring simple sequence repeat markers, genomic diversity, and molecular resources for millet improvement.',
    'about_title' => 'A clearer trail from field observation to genomic evidence.',
    'about_intro' => 'EleuSSRdb brings marker and expression records into one focused workspace for millet crop improvement research.',
    'about_mission_title' => 'Built for useful questions.',
    'about_mission_text' => 'The resource is designed for researchers who need to move quickly between a biological question and a searchable record.',
    'contact_title' => 'Help us make the collection more useful.',
    'contact_intro' => 'Share a dataset question, report an issue, or suggest the next species and fields the resource should support.',
    'footer_description' => 'Built for millet improvement research'
];
$settings_result = $conn->query('SELECT setting_key, setting_value FROM site_settings');
if ($settings_result) {
    while ($row = $settings_result->fetch_assoc()) {
        if (array_key_exists($row['setting_key'], $settings)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
}

echo json_encode(['images' => $images, 'settings' => $settings], JSON_UNESCAPED_SLASHES);
$conn->close();
?>
