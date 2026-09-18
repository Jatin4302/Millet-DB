<?php
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

$admin_password = getenv('MGR_ADMIN_PASSWORD') ?: 'Millet@2026';
$max_login_attempts = 5;
$lockout_seconds = 15 * 60;
$message = '';
$message_type = '';
$current_settings = ['palette' => 'custom', 'font_family' => 'manrope', 'font_scale' => 'normal', 'palette_ink' => '#173b32', 'palette_deep' => '#12352e', 'palette_muted' => '#688078', 'palette_line' => '#d7e2dc', 'palette_cream' => '#f5f5ec', 'palette_lime' => '#d8e95a', 'palette_orange' => '#f18b4e', 'home_eyebrow' => 'Millet research platform', 'home_title' => 'Exploring Millet Genetic Diversity Through SSR Markers', 'home_intro' => 'A comprehensive research platform for exploring simple sequence repeat markers, genomic diversity, and molecular resources for millet improvement.', 'about_title' => 'A clearer trail from field observation to genomic evidence.', 'about_intro' => 'EleuSSRdb brings marker and expression records into one focused workspace for millet crop improvement research.', 'about_mission_title' => 'Built for useful questions.', 'about_mission_text' => 'The resource is designed for researchers who need to move quickly between a biological question and a searchable record.', 'contact_title' => 'Help us make the collection more useful.', 'contact_intro' => 'Share a dataset question, report an issue, or suggest the next species and fields the resource should support.', 'footer_description' => 'Built for millet improvement research'];

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $lockout_until = (int) ($_SESSION['admin_lockout_until'] ?? 0);
    if ($lockout_until > time()) {
        $remaining_minutes = max(1, (int) ceil(($lockout_until - time()) / 60));
        $message = 'Too many failed attempts. Try again in ' . $remaining_minutes . ' minute' . ($remaining_minutes === 1 ? '' : 's') . '.';
        $message_type = 'error';
    } elseif (hash_equals($admin_password, (string) ($_POST['password'] ?? ''))) {
        unset($_SESSION['admin_failed_attempts'], $_SESSION['admin_lockout_until']);
        session_regenerate_id(true);
        $_SESSION['mgr_admin'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $_SESSION['admin_failed_attempts'] = ((int) ($_SESSION['admin_failed_attempts'] ?? 0)) + 1;
        if ($_SESSION['admin_failed_attempts'] >= $max_login_attempts) {
            $_SESSION['admin_lockout_until'] = time() + $lockout_seconds;
            $message = 'Too many failed attempts. Try again in 15 minutes.';
        } else {
            $attempts_left = $max_login_attempts - $_SESSION['admin_failed_attempts'];
            $message = 'The password did not match. ' . $attempts_left . ' attempt' . ($attempts_left === 1 ? '' : 's') . ' remaining.';
        }
        $message_type = 'error';
    }
}

$is_authenticated = !empty($_SESSION['mgr_admin']);
if ($is_authenticated) {
    require __DIR__ . '/db_connect.php';
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'login') {
        if (!hash_equals($_SESSION['csrf_token'], (string) ($_POST['csrf_token'] ?? ''))) {
            $message = 'Your session token expired. Refresh and try again.';
            $message_type = 'error';
        } elseif (($_POST['action'] ?? '') === 'save_settings') {
            $allowed_settings = [
                'palette' => ['custom', 'botanical', 'terracotta', 'ink'],
                'font_family' => ['manrope', 'baskerville', 'mono'],
                'font_scale' => ['compact', 'normal', 'large']
            ];
            $settings_saved = 0;
            $stmt = $conn->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            foreach ($allowed_settings as $key => $allowed_values) {
                $value = $_POST[$key] ?? '';
                if (in_array($value, $allowed_values, true)) {
                    $stmt->bind_param('ss', $key, $value);
                    $stmt->execute();
                    $settings_saved++;
                }
            }
            $stmt->close();
            $color_keys = ['palette_ink', 'palette_deep', 'palette_muted', 'palette_line', 'palette_cream', 'palette_lime', 'palette_orange'];
            $color_stmt = $conn->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            foreach ($color_keys as $key) {
                $value = strtolower(trim((string) ($_POST[$key] ?? '')));
                if (preg_match('/^#[0-9a-f]{6}$/', $value)) {
                    $color_stmt->bind_param('ss', $key, $value);
                    $color_stmt->execute();
                    $settings_saved++;
                }
            }
            $color_stmt->close();
            $content_keys = ['home_eyebrow', 'home_title', 'home_intro', 'about_title', 'about_intro', 'about_mission_title', 'about_mission_text', 'contact_title', 'contact_intro', 'footer_description'];
            $content_stmt = $conn->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            foreach ($content_keys as $key) {
                $value = trim((string) ($_POST[$key] ?? ''));
                if ($value !== '' && strlen($value) <= 4000) {
                    $content_stmt->bind_param('ss', $key, $value);
                    $content_stmt->execute();
                    $settings_saved++;
                }
            }
            $content_stmt->close();
            $message = $settings_saved . ' setting' . ($settings_saved === 1 ? '' : 's') . ' saved.';
            $message_type = 'success';
        } elseif (($_POST['action'] ?? '') === 'upload_images') {
            $upload_dir = __DIR__ . '/../assets/uploads';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $slots = ['navbar_background', 'body_background', 'hero_1', 'hero_2', 'hero_3', 'resource_ssr', 'resource_expression', 'resource_download'];
            $saved = 0;
            $errors = [];
            foreach ($slots as $slot) {
                $file_key = 'image_' . $slot;
                if (empty($_FILES[$file_key]['name'])) {
                    continue;
                }
                $file = $_FILES[$file_key];
                if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 5 * 1024 * 1024) {
                    $errors[] = $slot . ' must be an image smaller than 5 MB.';
                    continue;
                }
                $image_info = @getimagesize($file['tmp_name']);
                $mime_to_extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
                if (!$image_info || !isset($mime_to_extension[$image_info['mime']])) {
                    $errors[] = $slot . ' is not a supported JPG, PNG, WEBP, or GIF image.';
                    continue;
                }
                $filename = $slot . '-' . bin2hex(random_bytes(8)) . '.' . $mime_to_extension[$image_info['mime']];
                $relative_path = 'assets/uploads/' . $filename;
                if (!move_uploaded_file($file['tmp_name'], $upload_dir . '/' . $filename)) {
                    $errors[] = 'Could not save the ' . $slot . ' image.';
                    continue;
                }
                $alt = trim($_POST['alt_' . $slot] ?? '') ?: 'EleuSSRdb millet research image';
                $stmt = $conn->prepare('INSERT INTO site_images (slot, image_path, alt_text) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE image_path = VALUES(image_path), alt_text = VALUES(alt_text)');
                $stmt->bind_param('sss', $slot, $relative_path, $alt);
                $stmt->execute();
                $stmt->close();
                $saved++;
            }
            $message = $saved . ' image' . ($saved === 1 ? '' : 's') . ' updated.';
            if ($errors) {
                $message .= ' ' . implode(' ', $errors);
                $message_type = 'error';
            } else {
                $message_type = 'success';
            }
        } elseif (($_POST['action'] ?? '') === 'import_csv') {
            $dataset = $_POST['dataset'] ?? '';
            $file = $_FILES['csv_file'] ?? null;
            $definitions = [
                'ssr' => [
                    'table' => 'ssr_markers',
                    'columns' => ['species', 'gene_id', 'chromosome', 'motif', 'repeat_count', 'start_pos', 'end_pos', 'forward_primer', 'reverse_primer', 'source'],
                    'required' => ['species']
                ],
                'transcriptomics' => [
                    'table' => 'transcriptomics',
                    'columns' => ['species', 'gene_id', 'tissue', 'condition_name', 'expression_value', 'sequence', 'source'],
                    'required' => ['species']
                ]
            ];
            if (!isset($definitions[$dataset]) || !$file || $file['error'] !== UPLOAD_ERR_OK) {
                $message = 'Choose a dataset and a readable CSV file.';
                $message_type = 'error';
            } else {
                $definition = $definitions[$dataset];
                $handle = fopen($file['tmp_name'], 'r');
                $header = $handle ? fgetcsv($handle) : false;
                $header = $header ? array_map(static fn($value) => strtolower(trim((string) $value)), $header) : false;
                $missing = $header ? array_diff($definition['required'], $header) : $definition['required'];
                if (!$header || $missing) {
                    $message = 'CSV header must include: ' . implode(', ', $definition['required']) . '.';
                    $message_type = 'error';
                } else {
                    $columns = array_values(array_intersect($definition['columns'], $header));
                    $column_sql = implode(', ', $columns);
                    $marks = implode(', ', array_fill(0, count($columns), '?'));
                    $stmt = $conn->prepare('INSERT INTO ' . $definition['table'] . ' (' . $column_sql . ') VALUES (' . $marks . ')');
                    $header_indexes = array_flip($header);
                    $imported = 0;
                    $conn->begin_transaction();
                    try {
                        while (($row = fgetcsv($handle)) !== false) {
                            if (count(array_filter($row, static fn($value) => trim((string) $value) !== '')) === 0) {
                                continue;
                            }
                            $values = [];
                            foreach ($columns as $column) {
                                $value = trim((string) ($row[$header_indexes[$column]] ?? ''));
                                $values[] = $value === '' ? null : $value;
                            }
                            $types = str_repeat('s', count($values));
                            $stmt->bind_param($types, ...$values);
                            $stmt->execute();
                            $imported++;
                        }
                        $conn->commit();
                        $message = $imported . ' ' . $dataset . ' record' . ($imported === 1 ? '' : 's') . ' imported.';
                        $message_type = 'success';
                    } catch (Throwable $exception) {
                        $conn->rollback();
                        $message = 'Import stopped before completion: ' . $exception->getMessage();
                        $message_type = 'error';
                    }
                    $stmt->close();
                }
                if ($handle) {
                    fclose($handle);
                }
            }
        }
    }

    $current_images = [];
    $result = $conn->query('SELECT slot, image_path, alt_text FROM site_images ORDER BY slot');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $current_images[$row['slot']] = $row;
        }
    }
    $current_settings = ['palette' => 'custom', 'font_family' => 'manrope', 'font_scale' => 'normal', 'palette_ink' => '#173b32', 'palette_deep' => '#12352e', 'palette_muted' => '#688078', 'palette_line' => '#d7e2dc', 'palette_cream' => '#f5f5ec', 'palette_lime' => '#d8e95a', 'palette_orange' => '#f18b4e', 'home_eyebrow' => 'Millet research platform', 'home_title' => 'Exploring Millet Genetic Diversity Through SSR Markers', 'home_intro' => 'A comprehensive research platform for exploring simple sequence repeat markers, genomic diversity, and molecular resources for millet improvement.', 'about_title' => 'A clearer trail from field observation to genomic evidence.', 'about_intro' => 'EleuSSRdb brings marker and expression records into one focused workspace for millet crop improvement research.', 'about_mission_title' => 'Built for useful questions.', 'about_mission_text' => 'The resource is designed for researchers who need to move quickly between a biological question and a searchable record.', 'contact_title' => 'Help us make the collection more useful.', 'contact_intro' => 'Share a dataset question, report an issue, or suggest the next species and fields the resource should support.', 'footer_description' => 'Built for millet improvement research'];
    $settings_result = $conn->query('SELECT setting_key, setting_value FROM site_settings');
    if ($settings_result) {
        while ($row = $settings_result->fetch_assoc()) {
            if (array_key_exists($row['setting_key'], $current_settings)) {
                $current_settings[$row['setting_key']] = $row['setting_value'];
            }
        }
    }
    $conn->close();
}

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | EleuSSRdb</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="admin-body">
<?php if (!$is_authenticated): ?>
    <main class="admin-login page-main">
        <a class="brand" href="../home.html"><img src="../assets/logo-transparent.png" alt="EleuSSRdb logo"><span><strong>EleuSSRdb admin</strong></span></a>
        <section class="admin-login-panel">
            <p class="eyebrow"><span></span> Private workspace</p>
            <h1>Resource administration.</h1>
            <p>Manage research imagery and import prepared datasets.</p>
            <?php if ($message): ?><p class="admin-message <?= e($message_type) ?>"><?= e($message) ?></p><?php endif; ?>
            <form class="admin-form" method="post">
                <input type="hidden" name="action" value="login">
                <div class="form-row"><label for="password">Admin password</label><input id="password" name="password" type="password" required autofocus></div>
                <button class="search-button" type="submit">Sign in <span aria-hidden="true">→</span></button>
            </form>
        </section>
    </main>
<?php else: ?>
    <header class="site-header"><a class="brand" href="../home.html"><img src="../assets/logo-transparent.png" alt="EleuSSRdb logo"><span><strong>EleuSSR</strong><small>Admin workspace</small></span></a><a class="header-action" href="?logout=1">Sign out <span aria-hidden="true">↗</span></a></header>
    <main class="page-main admin-main">
        <section class="page-hero"><p class="eyebrow"><span></span> Content operations</p><h1>Keep the resource current.</h1><p>Update the imagery that frames the collection and load validated CSV records into the search database.</p></section>
        <?php if ($message): ?><p class="admin-message <?= e($message_type) ?>"><?= e($message) ?></p><?php endif; ?>
        <section class="admin-section" aria-labelledby="images-title">
            <div class="admin-section-heading"><div><p class="page-kicker">01 / Visual identity</p><h2 id="images-title">Manage images</h2></div><p>Hero images rotate automatically. Resource images appear on the home page cards.</p></div>
            <form class="admin-form image-admin-form" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_images"><input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <?php $image_labels = ['navbar_background' => 'Navbar background', 'body_background' => 'Page background', 'hero_1' => 'Hero image 01', 'hero_2' => 'Hero image 02', 'hero_3' => 'Hero image 03', 'resource_ssr' => 'SSR markers', 'resource_expression' => 'Expression data', 'resource_download' => 'Download results']; ?>
                <?php foreach ($image_labels as $slot => $label): ?>
                    <div class="admin-image-field"><div class="admin-image-preview" <?php if (!empty($current_images[$slot]['image_path'])): ?>style="background-image:url('../<?= e($current_images[$slot]['image_path']) ?>')"<?php endif; ?>><span><?= e($label) ?></span></div><label for="image_<?= e($slot) ?>"><?= e($label) ?> <small>JPG, PNG, WEBP or GIF · 5 MB max</small></label><input id="image_<?= e($slot) ?>" name="image_<?= e($slot) ?>" type="file" accept="image/jpeg,image/png,image/webp,image/gif"><input name="alt_<?= e($slot) ?>" type="text" value="<?= e($current_images[$slot]['alt_text'] ?? '') ?>" placeholder="Accessible image description"></div>
                <?php endforeach; ?>
                <button class="search-button" type="submit">Save images <span aria-hidden="true">→</span></button>
            </form>
        </section>
        <section class="admin-section" aria-labelledby="import-title">
            <div class="admin-section-heading"><div><p class="page-kicker">02 / Data intake</p><h2 id="import-title">Import a CSV</h2></div><p>Use a header row and keep column names aligned with the selected dataset.</p></div>
            <form class="admin-form csv-form" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_csv"><input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <div class="form-row"><label for="dataset">Dataset</label><select id="dataset" name="dataset" required><option value="">Choose a table</option><option value="ssr">SSR markers</option><option value="transcriptomics">Transcriptomics</option></select></div>
                <div class="form-row"><label for="csv_file">CSV file</label><input id="csv_file" name="csv_file" type="file" accept=".csv,text/csv" required></div>
                <p class="csv-help"><strong>SSR headers:</strong> species, gene_id, chromosome, motif, repeat_count, start_pos, end_pos, forward_primer, reverse_primer, source<br><strong>Transcriptomics headers:</strong> species, gene_id, tissue, condition_name, expression_value, sequence, source</p>
                <button class="search-button" type="submit">Import records <span aria-hidden="true">→</span></button>
            </form>
        </section>
        <section class="admin-section" aria-labelledby="appearance-title">
            <div class="admin-section-heading"><div><p class="page-kicker">03 / Visual system</p><h2 id="appearance-title">Tune the interface</h2></div><p>Choose a restrained colour palette, type family, and comfortable reading scale for the public resource.</p></div>
            <form class="admin-form appearance-form" method="post">
                <input type="hidden" name="action" value="save_settings"><input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <div class="form-row"><label for="palette">Colour palette</label><select id="palette" name="palette"><option value="custom" <?= $current_settings['palette'] === 'custom' ? 'selected' : '' ?>>Custom colours</option><option value="botanical" <?= $current_settings['palette'] === 'botanical' ? 'selected' : '' ?>>Botanical preset</option><option value="terracotta" <?= $current_settings['palette'] === 'terracotta' ? 'selected' : '' ?>>Terracotta preset</option><option value="ink" <?= $current_settings['palette'] === 'ink' ? 'selected' : '' ?>>Ink preset</option></select></div>
                <?php $colour_labels = ['ink' => 'Main text', 'deep' => 'Dark panels', 'muted' => 'Muted text', 'line' => 'Borders', 'cream' => 'Page wash', 'lime' => 'Highlight', 'orange' => 'Action colour']; ?>
                <?php foreach ($colour_labels as $colour_key => $colour_label): ?><div class="colour-control"><label for="palette_<?= e($colour_key) ?>"><?= e($colour_label) ?></label><input id="palette_<?= e($colour_key) ?>" name="palette_<?= e($colour_key) ?>" type="color" value="<?= e($current_settings['palette_' . $colour_key]) ?>"></div><?php endforeach; ?>
                <div class="form-row"><label for="font_family">Font style</label><select id="font_family" name="font_family"><option value="manrope" <?= $current_settings['font_family'] === 'manrope' ? 'selected' : '' ?>>Manrope / clean sans</option><option value="baskerville" <?= $current_settings['font_family'] === 'baskerville' ? 'selected' : '' ?>>Libre Baskerville / editorial serif</option><option value="mono" <?= $current_settings['font_family'] === 'mono' ? 'selected' : '' ?>>DM Mono / technical</option></select></div>
                <div class="form-row"><label for="font_scale">Font size</label><select id="font_scale" name="font_scale"><option value="compact" <?= $current_settings['font_scale'] === 'compact' ? 'selected' : '' ?>>Compact</option><option value="normal" <?= $current_settings['font_scale'] === 'normal' ? 'selected' : '' ?>>Standard</option><option value="large" <?= $current_settings['font_scale'] === 'large' ? 'selected' : '' ?>>Large reading size</option></select></div>
                <button class="search-button" type="submit">Save appearance <span aria-hidden="true">→</span></button>
            </form>
        </section>
        <section class="admin-section" aria-labelledby="content-title">
            <div class="admin-section-heading"><div><p class="page-kicker">04 / Public copy</p><h2 id="content-title">Edit page content</h2></div><p>Update the words visitors see on the homepage, About page, Contact page, and footer.</p></div>
            <form class="admin-form content-admin-form" method="post">
                <input type="hidden" name="action" value="save_settings"><input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <div class="form-row"><label for="home_eyebrow">Homepage label</label><input id="home_eyebrow" name="home_eyebrow" type="text" maxlength="160" value="<?= e($current_settings['home_eyebrow']) ?>"></div>
                <div class="form-row"><label for="home_title">Homepage heading</label><textarea id="home_title" name="home_title" maxlength="4000" rows="3"><?= e($current_settings['home_title']) ?></textarea></div>
                <div class="form-row"><label for="home_intro">Homepage introduction</label><textarea id="home_intro" name="home_intro" maxlength="4000" rows="4"><?= e($current_settings['home_intro']) ?></textarea></div>
                <div class="form-row"><label for="about_title">About page heading</label><textarea id="about_title" name="about_title" maxlength="4000" rows="3"><?= e($current_settings['about_title']) ?></textarea></div>
                <div class="form-row"><label for="about_intro">About page introduction</label><textarea id="about_intro" name="about_intro" maxlength="4000" rows="4"><?= e($current_settings['about_intro']) ?></textarea></div>
                <div class="form-row"><label for="about_mission_title">About mission heading</label><input id="about_mission_title" name="about_mission_title" type="text" maxlength="240" value="<?= e($current_settings['about_mission_title']) ?>"></div>
                <div class="form-row"><label for="about_mission_text">About mission text</label><textarea id="about_mission_text" name="about_mission_text" maxlength="4000" rows="5"><?= e($current_settings['about_mission_text']) ?></textarea></div>
                <div class="form-row"><label for="contact_title">Contact page heading</label><textarea id="contact_title" name="contact_title" maxlength="4000" rows="3"><?= e($current_settings['contact_title']) ?></textarea></div>
                <div class="form-row"><label for="contact_intro">Contact page introduction</label><textarea id="contact_intro" name="contact_intro" maxlength="4000" rows="4"><?= e($current_settings['contact_intro']) ?></textarea></div>
                <div class="form-row"><label for="footer_description">Footer link text</label><input id="footer_description" name="footer_description" type="text" maxlength="240" value="<?= e($current_settings['footer_description']) ?>"></div>
                <button class="search-button" type="submit">Save page content <span aria-hidden="true">→</span></button>
            </form>
        </section>
    </main>
<?php endif; ?>
</body>
</html>
