<?php
/**
 * Integration tests for the deployed PHP endpoints.
 * Run from the project root: php tests/run_tests.php http://localhost/MilletDB
 */

$base_url = rtrim($argv[1] ?? 'http://localhost/MilletDB', '/');
$passed = 0;
$failed = 0;

function request_endpoint(string $url): array
{
    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $body = curl_exec($handle);
    $error = curl_error($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    curl_close($handle);

    if ($body === false) {
        throw new RuntimeException($error ?: 'Request failed');
    }

    return [$status, $body];
}

function check_test(string $name, bool $condition): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "PASS: $name\n";
    } else {
        $failed++;
        echo "FAIL: $name\n";
    }
}

function json_request(string $base_url, array $query): array
{
    [$status, $body] = request_endpoint($base_url . '/php/search.php?' . http_build_query($query));
    $data = json_decode($body, true);
    return [$status, $data ?: []];
}

try {
    [$status, $health] = request_endpoint($base_url . '/php/health.php');
    $health_data = json_decode($health, true) ?: [];
    check_test('health endpoint responds successfully', $status === 200);
    check_test('health endpoint reports PHP', ($health_data['php'] ?? '') === 'ok');
    check_test('health endpoint reports database', ($health_data['database'] ?? '') === 'ok');

    [$status, $ssr] = json_request($base_url, [
        'dataset' => 'ssr', 'species' => 'Oryza', 'chromosome' => 'Chr1', 'motif' => 'AT', 'page' => 1,
    ]);
    check_test('SSR filter request returns JSON', $status === 200 && ($ssr['dataset'] ?? '') === 'ssr');
    check_test('SSR filter request returns matching rows', ($ssr['count'] ?? 0) > 0);

    [$status, $transcriptomics] = json_request($base_url, [
        'dataset' => 'transcriptomics', 'tissue' => 'leaf', 'condition_name' => 'drought stress', 'page' => 1,
    ]);
    check_test('transcriptomics filters are accepted', $status === 200 && ($transcriptomics['dataset'] ?? '') === 'transcriptomics');
    check_test('transcriptomics filter request returns matching rows', ($transcriptomics['count'] ?? 0) > 0);

    [$status, $page] = json_request($base_url, ['dataset' => 'ssr', 'page' => 2]);
    check_test('pagination request returns page metadata', $status === 200 && ($page['page'] ?? 0) === 2 && isset($page['pages'], $page['page_size']));

    [$status, $csv] = request_endpoint($base_url . '/php/export.php?' . http_build_query([
        'dataset' => 'ssr', 'species' => 'Oryza', 'gene_id' => 'OsGene001',
    ]));
    check_test('CSV export responds successfully', $status === 200);
    check_test('CSV export includes expected columns and data', strpos($csv, 'species') !== false && strpos($csv, 'OsGene001') !== false);
} catch (Throwable $error) {
    $failed++;
    echo "FAIL: test runner error - {$error->getMessage()}\n";
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
?>