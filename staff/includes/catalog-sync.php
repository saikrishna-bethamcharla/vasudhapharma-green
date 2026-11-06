<?php
/**
 * Catalog sync — Marketing desk (marketing.json) to the public product pages.
 *
 * The Marketing desk owns the commercial portfolio registry. Every save
 * projects that registry onto the five public catalogue pages by rewriting the
 * rows between their VP_*_CATALOG_START / END marker pairs, onto the schema.org
 * Product graph in each page's JSON-LD block, and onto the molecule viewer
 * dataset at assets/data/products-catalog.json.
 *
 * Kept in its own file so it can be exercised from the command line without a
 * session (see the CLI harness used when changing the row templates).
 */

if (!function_exists('staff_replace_between_markers')) {
  function staff_replace_between_markers($html, $startMarker, $endMarker, $replacement) {
    $posStart = strpos($html, $startMarker);
    if ($posStart === false) return false;
    $posEnd = strpos($html, $endMarker, $posStart + strlen($startMarker));
    if ($posEnd === false) return false;

    $before = substr($html, 0, $posStart + strlen($startMarker));
    $after = substr($html, $posEnd);
    return $before . "\n" . $replacement . "\n" . $after;
  }
}

/**
 * Replace a catalogue block, preserving the surrounding whitespace so repeated
 * portal saves do not churn the page layout on every write.
 */
if (!function_exists('staff_catalog_replace_block')) {
  function staff_catalog_replace_block($html, $startMarker, $endMarker, $rowsHtml) {
    $posStart = strpos($html, $startMarker);
    if ($posStart === false) return false;
    $bodyStart = $posStart + strlen($startMarker);
    $posEnd = strpos($html, $endMarker, $bodyStart);
    if ($posEnd === false) return false;

    // Keep the original indentation of the closing marker.
    $lineStart = $posEnd;
    while ($lineStart > $bodyStart && ($html[$lineStart - 1] === ' ' || $html[$lineStart - 1] === "\t")) {
      $lineStart--;
    }
    $indent = substr($html, $lineStart, $posEnd - $lineStart);

    return substr($html, 0, $bodyStart)
      . "\n\n" . rtrim($rowsHtml) . "\n" . $indent
      . substr($html, $posEnd);
  }
}

/** Escape a value for HTML output, tolerating values already stored as entities. */
if (!function_exists('staff_catalog_e')) {
  function staff_catalog_e($value) {
    $plain = html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return htmlspecialchars($plain, ENT_QUOTES, 'UTF-8');
  }
}

/** Lowercased search haystack, escaped once for a data-search attribute. */
if (!function_exists('staff_catalog_search')) {
  function staff_catalog_search(array $parts) {
    $clean = [];
    foreach ($parts as $p) {
      $p = trim(html_entity_decode((string)$p, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
      if ($p !== '') $clean[] = $p;
    }
    return htmlspecialchars(strtolower(implode(' ', $clean)), ENT_QUOTES, 'UTF-8');
  }
}

/**
 * Piperidone rows are grouped in the public catalogue and filtered by group key.
 * The filter buttons on piperidone-derivatives.html are data-filter="n-methyl",
 * "n-benzyl", "grignard" and "specialised", so these keys are load bearing.
 */
function staff_catalog_piperidone_groups() {
  return [
    'n-methyl'   => 'N-Methyl Piperidone Derivatives',
    'n-benzyl'   => 'N-Benzyl Piperidone Derivatives',
    'grignard'   => 'Grignard Reaction Piperidone Derivatives',
    'specialised' => 'Specialised Piperidone Derivatives',
  ];
}

/** Resolve the stored derivative group, falling back to the default group. */
function staff_catalog_piperidone_group($item) {
  $groups = staff_catalog_piperidone_groups();
  $stored = strtolower(trim((string)($item['group'] ?? '')));

  if ($stored !== '') {
    if (isset($groups[$stored])) return $stored;
    foreach ($groups as $key => $label) {
      if ($stored === strtolower($label)) return $key;
    }
  }

  $ther = strtolower(trim((string)($item['therapeutic'] ?? '')));
  if ($ther !== '') {
    foreach ($groups as $key => $label) {
      if ($ther === strtolower($label)) return $key;
    }
  }

  return 'specialised';
}

/** Split the registry into the five public catalogue buckets. */
function staff_catalog_partition(array $items) {
  $buckets = ['apis' => [], 'intermediates' => [], 'pellets' => [], 'piperidones' => [], 'under_dev' => []];

  foreach ($items as $it) {
    if (($it['status'] ?? 'active') === 'deleted') continue;
    $cat = strtolower(trim($it['category'] ?? ''));

    if ($cat === 'apis' || $cat === 'api') {
      $buckets['apis'][] = $it;
    } elseif ($cat === 'intermediates' || $cat === 'intermediate') {
      $buckets['intermediates'][] = $it;
    } elseif (strpos($cat, 'pellet') !== false) {
      $buckets['pellets'][] = $it;
    } elseif (strpos($cat, 'piperid') !== false) {
      $buckets['piperidones'][] = $it;
    } elseif (strpos($cat, 'under') !== false || strpos($cat, 'pipeline') !== false || ($it['status'] ?? '') === 'pipeline') {
      $buckets['under_dev'][] = $it;
    } else {
      $buckets['apis'][] = $it;
    }
  }

  return $buckets;
}

function staff_catalog_rfq_cell() {
  return '              <div class="api-cell api-action">' . "\n"
    . '                <button type="button" class="api-view-btn" onclick="return window.vpToggleRfq(this, event)">+ RFQ</button>' . "\n"
    . '              </div>' . "\n";
}

function staff_catalog_detail_row($label, $value) {
  return '                  <div><span class="adl">' . $label . '</span><span class="adv">' . $value . '</span></div>' . "\n";
}

/* ---------------------------------------------------------------- APIs ---- */
function staff_catalog_rows_apis(array $items) {
  $rows = '';
  foreach ($items as $it) {
    $name = staff_catalog_e($it['name'] ?? '');
    $nameAttr = staff_catalog_e(strtolower(html_entity_decode((string)($it['name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    $cas = staff_catalog_e($it['cas_no'] ?? '');
    $casCell = $cas !== '' ? $cas : '—';
    $id = staff_catalog_e($it['id'] ?? ('api-' . strtolower(preg_replace('/[^a-z0-9]+/i', '-', (string)($it['name'] ?? '')))));
    $spec = staff_catalog_e($it['specifications'] ?? 'IP/USP/BP/Ph.Eur/JP');
    $dmf = staff_catalog_e($it['dmf_status'] ?? 'Available');
    $ther = staff_catalog_e($it['therapeutic'] ?? '—');

    $rows .= '            <div class="api-row" data-name="' . $nameAttr . '" data-cas="' . $cas . '" id="' . $id . '">' . "\n";
    $rows .= '              <div class="api-cell api-name"><strong>' . $name . '</strong></div>' . "\n";
    $rows .= '              <div class="api-cell">' . $spec . '</div>' . "\n";
    $rows .= '              <div class="api-cell">' . $casCell . '</div>' . "\n";
    $rows .= '              <div class="api-cell">' . $dmf . '</div>' . "\n";
    $rows .= '              <div class="api-cell">' . $ther . '</div>' . "\n";
    $rows .= staff_catalog_rfq_cell();
    $rows .= '              <div class="api-detail" hidden>' . "\n";
    $rows .= '                <div class="api-detail-grid">' . "\n";
    $rows .= staff_catalog_detail_row('Product', $name);
    $rows .= staff_catalog_detail_row('Specifications', $spec);
    $rows .= staff_catalog_detail_row('CAS No.', $casCell);
    $rows .= staff_catalog_detail_row('DMF / Filings', $dmf);
    $rows .= staff_catalog_detail_row('Therapeutic category', $ther);
    $rows .= '                </div>' . "\n";
    $rows .= '              </div>' . "\n";
    $rows .= '            </div>' . "\n";
  }
  return $rows;
}

/* ------------------------------------------------------- Intermediates ---- */
function staff_catalog_rows_intermediates(array $items) {
  $rows = '';
  foreach ($items as $it) {
    $name = staff_catalog_e($it['name'] ?? '');
    $intName = staff_catalog_e($it['intermediate_name'] ?? ($it['name'] ?? ''));
    $cas = staff_catalog_e($it['cas_no'] ?? '');
    $casCell = $cas !== '' ? $cas : '—';
    $ther = staff_catalog_e($it['therapeutic'] ?? '—');
    $search = staff_catalog_search([$it['name'] ?? '', $it['intermediate_name'] ?? '', $it['cas_no'] ?? '', $it['therapeutic'] ?? '']);

    $rows .= '            <div class="api-row int-row" data-cas="' . $cas . '" data-search="' . $search . '">' . "\n";
    $rows .= '              <div class="api-cell api-name"><strong>' . $name . '</strong></div>' . "\n";
    $rows .= '              <div class="api-cell">' . $intName . '</div>' . "\n";
    $rows .= '              <div class="api-cell">' . $casCell . '</div>' . "\n";
    $rows .= '              <div class="api-cell">' . $ther . '</div>' . "\n";
    $rows .= staff_catalog_rfq_cell();
    $rows .= '              <div class="api-detail" hidden>' . "\n";
    $rows .= '                <div class="api-detail-grid">' . "\n";
    $rows .= staff_catalog_detail_row('Related API / Product', $name);
    $rows .= staff_catalog_detail_row('Intermediate', $intName);
    $rows .= staff_catalog_detail_row('CAS No.', $casCell);
    $rows .= staff_catalog_detail_row('Therapeutic category', $ther);
    $rows .= '                </div>' . "\n";
    $rows .= '              </div>' . "\n";
    $rows .= '            </div>' . "\n";
  }
  return $rows;
}

/* -------------------------------------------------------------- Pellets ---- */
function staff_catalog_rows_pellets(array $items) {
  $rows = '';
  foreach ($items as $it) {
    $name = staff_catalog_e($it['name'] ?? '');
    $nameAttr = staff_catalog_e(strtolower(html_entity_decode((string)($it['name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    $cas = staff_catalog_e($it['cas_no'] ?? '');
    $casCell = $cas !== '' ? $cas : '—';
    $spec = staff_catalog_e($it['specifications'] ?? 'IP / USP / BP / In-House');
    $reg = staff_catalog_e($it['dmf_status'] ?? 'Available');
    $comp = staff_catalog_e($it['composition'] ?? '—');
    $ther = staff_catalog_e($it['therapeutic'] ?? '—');
    $catAttr = staff_catalog_e(strtolower(html_entity_decode((string)($it['therapeutic'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    $type = staff_catalog_e($it['pellet_type'] ?? '—');
    $typeAttr = staff_catalog_e(strtolower(html_entity_decode((string)($it['pellet_type'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

    $rows .= '            <div class="api-row pellet-row" data-name="' . $nameAttr . '" data-cas="' . $cas . '" data-spec="' . $spec . '" data-reg="' . $reg . '" data-comp="' . $comp . '" data-cat="' . $catAttr . '" data-type="' . $typeAttr . '">' . "\n";
    $rows .= '              <div class="api-cell api-name"><strong>' . $name . '</strong></div>' . "\n";
    $rows .= '              <div class="api-cell">' . $type . '</div>' . "\n";
    $rows .= '              <div class="api-cell">' . $comp . '</div>' . "\n";
    $rows .= '              <div class="api-cell">' . $casCell . '</div>' . "\n";
    $rows .= '              <div class="api-cell">' . $ther . '</div>' . "\n";
    $rows .= staff_catalog_rfq_cell();
    $rows .= '              <div class="api-detail" hidden>' . "\n";
    $rows .= '                <div class="api-detail-grid">' . "\n";
    $rows .= staff_catalog_detail_row('CAS No.', $casCell);
    $rows .= staff_catalog_detail_row('Specifications', $spec);
    $rows .= staff_catalog_detail_row('Regulatory Status', $reg);
    $rows .= staff_catalog_detail_row('Product', $name);
    $rows .= staff_catalog_detail_row('Type', $type);
    $rows .= staff_catalog_detail_row('Composition', $comp);
    $rows .= staff_catalog_detail_row('Therapeutic category', $ther);
    $rows .= '                </div>' . "\n";
    $rows .= '              </div>' . "\n";
    $rows .= '            </div>' . "\n";
  }
  return $rows;
}

/* ----------------------------------------------------- Piperidones ---- */
function staff_catalog_rows_piperidones(array $items) {
  $groups = staff_catalog_piperidone_groups();
  $rows = '';
  foreach ($items as $it) {
    $name = staff_catalog_e($it['name'] ?? '');
    $code = staff_catalog_e(($it['product_code'] ?? '') !== '' ? $it['product_code'] : '—');
    $cas = staff_catalog_e($it['cas_no'] ?? '');
    $casCell = $cas !== '' ? $cas : '—';
    $groupKey = staff_catalog_piperidone_group($it);
    $groupLabel = staff_catalog_e($groups[$groupKey] ?? $groups['specialised']);
    $search = staff_catalog_search([$it['name'] ?? '', $it['cas_no'] ?? '', $it['product_code'] ?? '', $groups[$groupKey] ?? '']);

    $rows .= '            <div class="api-row pip-row" data-search="' . $search . '" data-cat="' . staff_catalog_e($groupKey) . '">' . "\n";
    $rows .= '              <div class="api-cell api-name"><strong>' . $name . '</strong></div>' . "\n";
    $rows .= '              <div class="api-cell">' . $code . '</div>' . "\n";
    $rows .= '              <div class="api-cell">' . $casCell . '</div>' . "\n";
    $rows .= '              <div class="api-cell">' . $groupLabel . '</div>' . "\n";
    $rows .= staff_catalog_rfq_cell();
    $rows .= '              <div class="api-detail" hidden>' . "\n";
    $rows .= '                <div class="api-detail-grid">' . "\n";
    $rows .= staff_catalog_detail_row('Product', $name);
    $rows .= staff_catalog_detail_row('Code', $code);
    $rows .= staff_catalog_detail_row('CAS No.', $casCell);
    $rows .= staff_catalog_detail_row('Category', $groupLabel);
    $rows .= '                </div>' . "\n";
    $rows .= '              </div>' . "\n";
    $rows .= '            </div>' . "\n";
  }
  return $rows;
}

/* ---------------------------------------------------- Under Development ---- */
function staff_catalog_rows_under_dev(array $items) {
  $rows = '';
  foreach ($items as $it) {
    $name = staff_catalog_e($it['name'] ?? '');
    $cas = staff_catalog_e($it['cas_no'] ?? '');
    $casCell = $cas !== '' ? $cas : '—';
    $spec = staff_catalog_e($it['specifications'] ?? 'In-House');
    $dmf = staff_catalog_e($it['dmf_status'] ?? 'Under Development');
    $ther = staff_catalog_e($it['therapeutic'] ?? '—');
    $search = staff_catalog_search([$it['name'] ?? '', $it['cas_no'] ?? '', $it['therapeutic'] ?? '']);

    $rows .= '            <div class="api-row udev-row" data-search="' . $search . '">' . "\n";
    $rows .= '              <div class="api-cell api-name"><strong>' . $name . '</strong></div>' . "\n";
    $rows .= '              <div class="api-cell">' . $spec . '</div>' . "\n";
    $rows .= '              <div class="api-cell">' . $casCell . '</div>' . "\n";
    $rows .= '              <div class="api-cell">' . $dmf . '</div>' . "\n";
    $rows .= '              <div class="api-cell">' . $ther . '</div>' . "\n";
    $rows .= staff_catalog_rfq_cell();
    $rows .= '              <div class="api-detail" hidden>' . "\n";
    $rows .= '                <div class="api-detail-grid">' . "\n";
    $rows .= staff_catalog_detail_row('Product', $name);
    $rows .= staff_catalog_detail_row('Specifications', $spec);
    $rows .= staff_catalog_detail_row('CAS No.', $casCell);
    $rows .= staff_catalog_detail_row('DMF Status', $dmf);
    $rows .= staff_catalog_detail_row('Therapeutic use', $ther);
    $rows .= '                </div>' . "\n";
    $rows .= '              </div>' . "\n";
    $rows .= '            </div>' . "\n";
  }
  return $rows;
}

/** The five public catalogue pages and the bucket each one renders. */
function staff_catalog_page_map() {
  return [
    'apis.html'                   => ['bucket' => 'apis',        'start' => '<!-- VP_API_CATALOG_START -->',    'end' => '<!-- VP_API_CATALOG_END -->',    'rows' => 'staff_catalog_rows_apis',        'label' => 'APIs'],
    'intermediates.html'          => ['bucket' => 'intermediates','start' => '<!-- VP_INT_CATALOG_START -->',    'end' => '<!-- VP_INT_CATALOG_END -->',    'rows' => 'staff_catalog_rows_intermediates', 'label' => 'Intermediates'],
    'pellets.html'                => ['bucket' => 'pellets',     'start' => '<!-- VP_PELLET_CATALOG_START -->', 'end' => '<!-- VP_PELLET_CATALOG_END -->', 'rows' => 'staff_catalog_rows_pellets',     'label' => 'Pellets &amp; MUPS'],
    'piperidone-derivatives.html' => ['bucket' => 'piperidones', 'start' => '<!-- VP_PIP_CATALOG_START -->',    'end' => '<!-- VP_PIP_CATALOG_END -->',    'rows' => 'staff_catalog_rows_piperidones', 'label' => 'Piperidone &amp; Piperidine Derivatives'],
    'under-development.html'      => ['bucket' => 'under_dev',   'start' => '<!-- VP_UDEV_CATALOG_START -->',   'end' => '<!-- VP_UDEV_CATALOG_END -->',   'rows' => 'staff_catalog_rows_under_dev',   'label' => 'Under Development APIs'],
  ];
}

/* ------------------------------------------------- structured data (JSON-LD) ---- */

/** One schema.org Product node for a registry item, matching the authored shape. */
function staff_catalog_jsonld_product(array $it, $page) {
  $name = (string)($it['name'] ?? '');
  $cat  = (string)($it['category'] ?? '');
  $desc = $name . ' (' . $cat . ') manufactured by Vasudha Pharma Chem Limited under cGMP standards.';

  $props = [];
  $addProp = function ($label, $value) use (&$props) {
    if ($value === '' || $value === null) return;
    $props[] = ['@type' => 'PropertyValue', 'name' => $label, 'value' => (string)$value];
  };

  if (!empty($it['therapeutic']))    $desc .= ' Therapeutic Area: ' . $it['therapeutic'] . '.';
  if (!empty($it['specifications'])) $desc .= ' Pharmacopoeia: ' . $it['specifications'] . '.';
  if (!empty($it['dmf_status']))     $desc .= ' Regulatory DMF Status: ' . $it['dmf_status'] . '.';

  $addProp('CAS Registry Number', $it['cas_no'] ?? '');
  $addProp('Pharmacopoeial Specifications', $it['specifications'] ?? '');
  $addProp('Regulatory DMF Status', $it['dmf_status'] ?? '');
  $addProp('Therapeutic Category', $it['therapeutic'] ?? '');

  return [
    '@type'       => 'Product',
    '@id'         => 'https://vasudhapharma.com/' . $page . '#product-' . ($it['id'] ?? ''),
    'name'        => $name,
    'category'    => $cat,
    'description' => $desc,
    'manufacturer' => ['@id' => 'https://vasudhapharma.com/#organization'],
    'offers'      => [
      '@type'        => 'AggregateOffer',
      'priceCurrency' => 'USD',
      'availability' => 'https://schema.org/InStock',
      'url'          => 'https://vasudhapharma.com/' . $page,
    ],
    'additionalProperty' => $props,
  ];
}

/**
 * Rebuild the Product half of a page's JSON-LD @graph from the same registry
 * rows the page displays, keeping any non-Product node (the Organization)
 * exactly as authored. Returns the html unchanged when there is no block.
 */
function staff_catalog_apply_jsonld($html, array $items, $page) {
  $openTag = '<script type="application/ld+json">';
  $pos = strpos($html, $openTag);
  if ($pos === false) return $html;
  $bodyStart = $pos + strlen($openTag);
  $bodyEnd = strpos($html, '</script>', $bodyStart);
  if ($bodyEnd === false) return $html;

  $doc = json_decode(trim(substr($html, $bodyStart, $bodyEnd - $bodyStart)), true);
  if (!is_array($doc) || !isset($doc['@graph']) || !is_array($doc['@graph'])) return $html;

  $graph = [];
  foreach ($doc['@graph'] as $node) {
    $type = $node['@type'] ?? '';
    $isProduct = ($type === 'Product') || (is_array($type) && in_array('Product', $type, true));
    if (!$isProduct) $graph[] = $node;
  }
  foreach ($items as $it) {
    if (($it['status'] ?? 'active') === 'deleted') continue;
    $graph[] = staff_catalog_jsonld_product($it, $page);
  }
  $doc['@graph'] = $graph;

  $json = json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  if ($json === false) return $html;
  // PHP indents pretty-printed JSON by four spaces; the pages are authored with two.
  $json = preg_replace_callback('/^( +)/m', function ($m) {
    return str_repeat(' ', intdiv(strlen($m[1]), 2));
  }, $json);

  return substr($html, 0, $bodyStart) . "\n" . $json . "\n  " . substr($html, $bodyEnd);
}

/** Category slugs used by assets/data/products-catalog.json (molecule viewer). */
function staff_catalog_molecule_meta() {
  return [
    'apis'         => ['apis', 'API', 'apis.html'],
    'intermediates' => ['intermediates', 'Intermediate', 'intermediates.html'],
    'pellets'      => ['pellets', 'Pellets & MUPS', 'pellets.html'],
    'piperidones'  => ['piperidones', 'Piperidone', 'piperidone-derivatives.html'],
    'under_dev'    => ['under-dev', 'Under Development', 'under-development.html'],
  ];
}

/**
 * Keep assets/data/products-catalog.json (the 3D molecule viewer + catalog
 * search dataset) aligned with the registry, without discarding the viewer-only
 * fields it carries: synonyms, sub_category and the broad therapeutic segment.
 */
function staff_sync_molecule_catalog(array $buckets, $siteRoot) {
  $file = $siteRoot . '/assets/data/products-catalog.json';
  $meta = staff_catalog_molecule_meta();

  $existing = [];
  if (is_file($file)) {
    $decoded = json_decode((string)@file_get_contents($file), true);
    if (is_array($decoded)) $existing = $decoded;
  }

  // Several records legitimately share a product name and differ only by CAS,
  // so records are pooled per name and consumed in order: prefer an exact
  // name+CAS hit, otherwise take the next unmatched record with that name. That
  // keeps viewer-only fields (synonyms, sub_category) attached even when the
  // registry has since corrected a CAS number.
  $pool = [];
  foreach ($existing as $rec) {
    if (!is_array($rec)) continue;
    $nameKey = strtolower(trim(html_entity_decode((string)($rec['name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    if ($nameKey === '') continue;
    $pool[$nameKey][] = [
      'cas' => strtolower(trim((string)($rec['cas'] ?? ''))),
      'rec' => $rec,
      'used' => false,
    ];
  }

  $ordered = [];
  foreach ($meta as $bucket => $m) {
    list($slug, $label, $page) = $m;
    foreach ($buckets[$bucket] ?? [] as $it) {
      $name = trim(html_entity_decode((string)($it['name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
      if ($name === '') continue;
      $nameKey = strtolower($name);
      $cas = trim((string)($it['cas_no'] ?? ''));

      // The registry can legitimately list the same molecule more than once
      // (e.g. one product name carrying several intermediates), and the public
      // pages render one row per registry entry — so the viewer dataset keeps
      // one record per entry rather than collapsing duplicates.
      $rec = null;
      $casKey = strtolower($cas);
      $fallbackIndex = null;
      foreach ($pool[$nameKey] ?? [] as $idx => $cand) {
        if ($cand['used']) continue;
        if ($cand['cas'] === $casKey) {
          $rec = $cand['rec'];
          $pool[$nameKey][$idx]['used'] = true;
          $fallbackIndex = null;
          break;
        }
        if ($fallbackIndex === null) $fallbackIndex = $idx;
      }
      if ($rec === null && $fallbackIndex !== null) {
        $rec = $pool[$nameKey][$fallbackIndex]['rec'];
        $pool[$nameKey][$fallbackIndex]['used'] = true;
      }
      if ($rec === null) {
        $rec = [
          'name' => $name,
          'category' => $slug,
          'category_label' => $label,
          'cas' => '',
          'specs' => '',
          'filings' => '',
          'page' => $page,
          'therapeutic' => '',
          'sub_category' => '',
          'synonyms' => [],
          'dmf_status' => '',
        ];
      }

      $rec['name'] = $name;
      $rec['category'] = $slug;
      $rec['category_label'] = $label;
      $rec['page'] = $page;
      $rec['cas'] = (string)($it['cas_no'] ?? '');
      $rec['specs'] = (string)($it['specifications'] ?? '');
      $rec['dmf_status'] = (string)($it['dmf_status'] ?? '');

      $ordered[] = $rec;
    }
  }

  // Records the registry no longer lists are retired from the viewer too.
  $json = json_encode($ordered, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  if ($json === false) return ['assets/data/products-catalog.json (encode failed)'];

  // This dataset is authored with 2-space indentation; PHP's pretty printer
  // uses 4. Re-indent so a save produces a meaningful diff instead of a rewrite.
  $json = preg_replace_callback('/^( +)/m', function ($m) {
    return str_repeat(' ', (int)(strlen($m[1]) / 2));
  }, $json);

  $dir = dirname($file);
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  if (@file_put_contents($file, $json . "\n", LOCK_EX) === false) {
    return ['assets/data/products-catalog.json (not writable)'];
  }
  return [];
}

/**
 * Project the Marketing registry onto every public catalogue surface.
 *
 * @return string[] human readable problems; empty means everything synced.
 */
function staff_sync_html_product_catalogs($data, $siteRoot) {
  $items = $data['items'] ?? [];
  if (!is_array($items)) $items = [];

  $problems = [];
  $buckets = staff_catalog_partition($items);
  $pages = staff_catalog_page_map();

  // 1. Update the per-category totals on the products overview page.
  $prodFile = $siteRoot . '/products.html';
  if (is_file($prodFile)) {
    $c = file_get_contents($prodFile);
    foreach ($pages as $page => $def) {
      $count = count($buckets[$def['bucket']]);
      $pattern = '/(<h3>\s*' . preg_quote($def['label'], '/') . '\s*<\/h3>\s*<span class="prod-total">Total:\s*)\d+(<\/span>)/i';
      $c = preg_replace($pattern, '${1}' . $count . '${2}', $c);
    }
    if (@file_put_contents($prodFile, $c, LOCK_EX) === false) {
      $problems[] = 'products.html (not writable — category totals left stale)';
    }
  }

  // 2. Rewrite each catalogue block from the registry.
  foreach ($pages as $page => $def) {
    $path = $siteRoot . '/' . $page;
    if (!is_file($path)) continue;

    $html = file_get_contents($path);
    if ($html === false) {
      $problems[] = $page . ' (unreadable)';
      continue;
    }

    $rowsHtml = call_user_func($def['rows'], $buckets[$def['bucket']]);
    $updated = staff_catalog_replace_block($html, $def['start'], $def['end'], $rowsHtml);
    if ($updated === false) {
      $problems[] = $page . ' (catalogue markers missing — page left unchanged)';
      continue;
    }

    // The structured data (schema.org Product nodes) must list the same items.
    $updated = staff_catalog_apply_jsonld($updated, $buckets[$def['bucket']], $page);

    if ($updated === $html) continue;

    if (@file_put_contents($path, $updated, LOCK_EX) === false) {
      $problems[] = $page . ' (not writable — catalogue left stale)';
    }
  }

  // 3. Refresh the molecule viewer dataset.
  foreach (staff_sync_molecule_catalog($buckets, $siteRoot) as $problem) {
    $problems[] = $problem;
  }

  return $problems;
}
