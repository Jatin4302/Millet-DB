<?php
// ---------------------------------------------------------
// Handles search requests from search.html (via script.js).
// Reads filters from the URL, runs a safe parameterized
// query, and returns results as JSON.
// ---------------------------------------------------------

header('Content-Type: application/json');
require "db_connect.php";

// Which table to search
$dataset = $_GET['dataset'] ?? 'ssr';
$allowed_datasets = ['ssr', 'transcriptomics'];
if (!in_array($dataset, $allowed_datasets)) {
    $dataset = 'ssr';
}
$table = ($dataset === 'ssr') ? 'ssr_markers' : 'transcriptomics';

// Filters (optional — blank means "match anything")
$species = trim($_GET['species'] ?? '');
$gene_id = trim($_GET['gene_id'] ?? '');
$chromosome = trim($_GET['chromosome'] ?? '');
$motif = trim($_GET['motif'] ?? '');
$tissue = trim($_GET['tissue'] ?? '');
$condition_name = trim($_GET['condition_name'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$page_size = 25;

// Build query safely using prepared statements (prevents SQL injection)
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

$count_sql = preg_replace('/SELECT \* FROM/', 'SELECT COUNT(*) AS total FROM', $sql, 1);
$count_stmt = $conn->prepare($count_sql);
if ($params) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total = (int) $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$offset = ($page - 1) * $page_size;
$sql .= " LIMIT ? OFFSET ?";
$params[] = $page_size;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

echo json_encode([
    "dataset" => $dataset,
    "count" => $total,
    "page" => $page,
    "page_size" => $page_size,
    "pages" => max(1, (int) ceil($total / $page_size)),
    "rows" => $rows
]);

$stmt->close();
$conn->close();
?>
