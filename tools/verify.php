<?php
/**
 * Pre-deploy checks for the public site.
 *
 * The site is a hand-edited static tree plus PHP endpoints, and it is uploaded
 * to staging by hand, so a broken page or a catalogue that drifted from the
 * Marketing registry used to reach production unnoticed. Run this before every
 * deploy (CI does):
 *
 *     php tools/verify.php
 *
 * Exit code 0 means everything passed; 1 lists what needs fixing.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$problems = [];
$checks = 0;

function vp_rel(string $path, string $root): string
{
    return ltrim(str_replace($root, '', $path), '/');
}

function vp_fail(string $message): void
{
    global $problems;
    $problems[] = $message;
}

/** Files under the repo we care about, skipping vendored and scratch trees. */
function vp_files(string $root, string $extension): array
{
    $skip = ['.git', 'node_modules', 'vendor', 'scratch', 'artifacts', '.github'];
    $out = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            static function (SplFileInfo $file) use ($skip): bool {
                return !($file->isDir() && in_array($file->getFilename(), $skip, true));
            }
        )
    );
    foreach ($it as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === $extension) {
            $out[] = $file->getPathname();
        }
    }
    sort($out);
    return $out;
}

/* ------------------------------------------------------- 1. PHP syntax ---- */

foreach (vp_files($root, 'php') as $file) {
    $checks++;
    $output = [];
    $status = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $status);
    if ($status !== 0) {
        vp_fail('PHP syntax: ' . vp_rel($file, $root) . ' — ' . trim(implode(' ', $output)));
    }
}

/* -------------------------------------------------------- 2. JSON data ---- */

foreach (vp_files($root, 'json') as $file) {
    $name = vp_rel($file, $root);
    if ($name === 'package-lock.json') continue;   // tooling, not site data
    $checks++;
    json_decode((string)file_get_contents($file), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        vp_fail('Invalid JSON: ' . $name . ' — ' . json_last_error_msg());
    }
}

/* --------------------------------------- 3. Catalogue matches the registry ---- */

require_once $root . '/staff/includes/catalog-sync.php';

$registryFile = $root . '/staff/data/marketing.json';
$registry = json_decode((string)@file_get_contents($registryFile), true);
if (!is_array($registry) || !isset($registry['items']) || !is_array($registry['items'])) {
    vp_fail('Registry: staff/data/marketing.json is missing or has no items[]');
} else {
    // Count with the same partition the sync uses, so the numbers always agree.
    $buckets = staff_catalog_partition($registry['items']);
    $counts = [];
    $total = 0;
    foreach ($buckets as $bucket => $items) {
        $counts[$bucket] = count($items);
        $total += count($items);
    }

    foreach (staff_catalog_page_map() as $page => $def) {
        $path = $root . '/' . $page;
        if (!is_file($path)) {
            vp_fail('Catalogue: ' . $page . ' is missing');
            continue;
        }
        $checks++;
        $html = (string)file_get_contents($path);

        $start = strpos($html, $def['start']);
        $end = $start === false ? false : strpos($html, $def['end'], $start);
        if ($start === false || $end === false) {
            vp_fail('Catalogue: ' . $page . ' lost its ' . $def['start'] . ' / ' . $def['end'] . ' markers');
        } else {
            $rendered = substr_count(substr($html, $start, $end - $start), '<div class="api-row');
            $expected = $counts[$def['bucket']];
            if ($rendered !== $expected) {
                vp_fail(sprintf(
                    'Catalogue: %s renders %d rows but the registry holds %d %s items — save the Marketing desk again.',
                    $page, $rendered, $expected, $def['bucket']
                ));
            }
        }

        // The schema.org graph must list exactly the same products.
        if (preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $m)) {
            $graph = json_decode(trim($m[1]), true);
            if (!is_array($graph) || !isset($graph['@graph'])) {
                vp_fail('Structured data: ' . $page . ' has an unreadable JSON-LD block');
            } else {
                $products = 0;
                foreach ($graph['@graph'] as $node) {
                    $type = $node['@type'] ?? '';
                    if ($type === 'Product' || (is_array($type) && in_array('Product', $type, true))) $products++;
                }
                if ($products !== $counts[$def['bucket']]) {
                    vp_fail(sprintf(
                        'Structured data: %s lists %d products but the registry holds %d.',
                        $page, $products, $counts[$def['bucket']]
                    ));
                }
            }
        }
    }

    // The overview page advertises the same totals.
    $productsPage = $root . '/products.html';
    if (is_file($productsPage)) {
        $checks++;
        $overview = (string)file_get_contents($productsPage);
        $sum = 0;
        if (preg_match_all('/<span class="prod-total">Total:\s*(\d+)<\/span>/i', $overview, $m)) {
            foreach ($m[1] as $value) $sum += (int)$value;
        }
        if ($sum !== $total) {
            vp_fail(sprintf(
                'Overview: products.html advertises %d catalogue items in total but the registry holds %d.',
                $sum, $total
            ));
        }
    }
}

/* ---------------------------------------------------------- 4. Sitemap ---- */

$sitemap = $root . '/sitemap.xml';
if (is_file($sitemap)) {
    $checks++;
    $xml = @simplexml_load_file($sitemap);
    if ($xml === false) {
        vp_fail('Sitemap: sitemap.xml is not valid XML');
    } else {
        foreach ($xml->url as $url) {
            $loc = trim((string)$url->loc);
            $path = parse_url($loc, PHP_URL_PATH);
            if (!$path || $path === '/') continue;
            $target = $root . rawurldecode($path);
            if (is_dir($target)) $target .= '/index.html';
            if (!is_file($target)) {
                vp_fail('Sitemap: ' . $loc . ' has no file (' . $path . ')');
            }
        }
    }
} else {
    vp_fail('Sitemap: sitemap.xml is missing');
}

/* ----------------------------------------------------- 5. Internal links ---- */

$rootPages = glob($root . '/*.html') ?: [];
foreach ($rootPages as $page) {
    $checks++;
    $html = (string)file_get_contents($page);
    if (!preg_match_all('/href="([^"#?]+\.html)(?:[?#][^"]*)?"/i', $html, $m)) continue;
    foreach (array_unique($m[1]) as $href) {
        if (preg_match('#^(https?:)?//#i', $href) || strpos($href, 'mailto:') === 0) continue;
        $target = $root . '/' . ltrim($href, './');
        if (!is_file($target)) {
            vp_fail('Link: ' . vp_rel($page, $root) . ' links to missing ' . $href);
        }
    }
}

/* ---------------------------------------------------------------- report ---- */

if ($problems) {
    fwrite(STDERR, "✗ " . count($problems) . " problem(s) found in $checks checks:\n");
    foreach ($problems as $problem) {
        fwrite(STDERR, '  - ' . $problem . "\n");
    }
    exit(1);
}

echo "✓ All $checks checks passed.\n";
exit(0);
