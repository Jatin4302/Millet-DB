<?php
// ---------------------------------------------------------
// Streams the current search results as a downloadable CSV.
// Uses the exact same filters as search.php so the download
// always matches what the user sees on screen.
// ---------------------------------------------------------

require "db_connect.php";

$dataset = $_GET['dataset'] ?? 'ssr';
$allowed_datasets = ['ssr', 'transcriptomics'];
if (!in_array($dataset, $allowed_datasets)) {
    $dataset = 'ssr';
}
$table = ($dataset === 'ssr') ? 'ssr_markers' : 'transcriptomics';

$species = trim($_GET['species'] ?? '');
$gene_id = trim($_GET['gene_id'] ?? '');
$chromosome = trim($_GET['chromosome'] ?? '');
$motif = trim($_GET['motif'] ?? '');
$tissue = trim($_GET['tissue'] ?? '');
$condition_name = trim($_GET['condition_name'] ?? '');

$sql = "SELECT * FROM $table WHERE 1=1";
$params = [];
$types = "";

if ($species !== '') {
    $sql .= " AND species LIKE ?";
    $params[] = "%$species%";
    $types .= "s";
}

if ($gene_id !== '') {
    $sql .= " AND gene_id LIKE ?";
    $params[] = "%$gene_id%";
    $types .= "s";
}

$if_ssr = $dataset === 'ssr';
if ($if_ssr && $chromosome !== '') {
    $sql .= " AND chromosome LIKE ?";
    $params[] = "%$chromosome%";
    $types .= "s";
}
if ($if_ssr && $motif !== '') {
    $sql .= " AND motif LIKE ?";
    $params[] = "%$motif%";
    $types .= "s";
}
if (!$if_ssr && $tissue !== '') {
    $sql .= " AND tissue LIKE ?";
    $params[] = "%$tissue%";
    $types .= "s";
}
if (!$if_ssr && $condition_name !== '') {
    $sql .= " AND condition_name LIKE ?";
    $params[] = "%$condition_name%";
    $types .= "s";
}

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Force browser to download rather than display
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $dataset . '_export.csv"');

$output = fopen('php://output', 'w');

$first = true;
while ($row = $result->fetch_assoc()) {
    if ($first) {
        fputcsv($output, array_keys($row)); // header row
        $first = false;
    }
    fputcsv($output, $row);
}

if ($first) {
    // No rows matched — still give a valid empty file with a header
    fputcsv($output, ['No results found for this search']);
}

fclose($output);
$stmt->close();
$conn->close();
?>
