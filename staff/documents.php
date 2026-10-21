<?php
require __DIR__ . '/includes/auth.php';
staff_require_desk('documents');
$u = staff_user();
$docsData = staff_load_documents();
if (!isset($docsData['documents']) || !is_array($docsData['documents'])) {
  $docsData['documents'] = [];
}
$msg = '';
$err = '';

$siteRoot = dirname(staff_root());

// Helper function to sync HTML files on disk whenever documents change
function staff_sync_html_governance_menus($docs, $siteRoot) {
  $annualDocs = array_filter($docs['documents'] ?? [], function($d) {
    return ($d['category'] ?? '') === 'annual_return' || ($d['category'] ?? '') === 'annual_report';
  });

  // Sort newest financial year first (e.g. 2025-26 before 2024-25)
  usort($annualDocs, function($a, $b) {
    $yA = intval(preg_replace('/[^0-9]/', '', substr($a['year'] ?? '', 0, 4)));
    $yB = intval(preg_replace('/[^0-9]/', '', substr($b['year'] ?? '', 0, 4)));
    return $yB - $yA;
  });

  $annualMenuHtml = '';
  foreach ($annualDocs as $doc) {
    $t = strtoupper($doc['title'] ?? ('ANNUAL RETURN ' . ($doc['year'] ?? '')));
    $f = htmlspecialchars($doc['file'] ?? '');
    $y = htmlspecialchars($doc['year'] ?? '');
    $annualMenuHtml .= '                <a href="' . $f . '" class="top-bar-dd-item" role="menuitem" target="_blank" rel="noopener" data-year="' . $y . '">' . htmlspecialchars($t) . '</a>' . "\n";
  }
  $annualMenuHtml = rtrim($annualMenuHtml);

  // Scan all HTML files in site root
  $htmlFiles = glob($siteRoot . '/*.html');
  if ($htmlFiles && $annualMenuHtml) {
    foreach ($htmlFiles as $hPath) {
      $c = @file_get_contents($hPath);
      if (!$c) continue;

      // Pattern matching the annual return submenu links
      $pattern = '/(<div class="top-bar-dd-submenu"[^>]*>)([\s\S]*?)(<\/div>)/i';
      if (preg_match($pattern, $c)) {
        $newContent = preg_replace_callback($pattern, function($matches) use ($annualMenuHtml) {
          return $matches[1] . "\n" . $annualMenuHtml . "\n              " . $matches[3];
        }, $c);
        if ($newContent && $newContent !== $c) {
          @file_put_contents($hPath, $newContent);
        }
      }
    }
  }
}

function staff_is_valid_pdf($tmpPath) {
  if (!$tmpPath || !is_file($tmpPath)) return false;
  $fp = @fopen($tmpPath, 'rb');
  if (!$fp) return false;
  $header = fread($fp, 5);
  fclose($fp);
  return $header === '%PDF-';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  staff_verify_csrf();
  $action = $_POST['action'] ?? '';

  // 1. Upload new document / New Financial Year
  if ($action === 'upload_new') {
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? 'annual_return');
    $year = trim($_POST['year'] ?? '');
    $unit = trim($_POST['unit'] ?? 'Corporate');
    $desc = trim($_POST['description'] ?? '');

    if ($title === '') {
      $err = 'Document title / website field name is required.';
    } elseif (empty($_FILES['pdf_file']['tmp_name']) || !is_uploaded_file($_FILES['pdf_file']['tmp_name'])) {
      $err = 'Please select a valid PDF document to upload.';
    } else {
      $origName = $_FILES['pdf_file']['name'];
      $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
      $size = $_FILES['pdf_file']['size'];

      if ($ext !== 'pdf' || !staff_is_valid_pdf($_FILES['pdf_file']['tmp_name'])) {
        $err = 'Only genuine PDF documents (.pdf) are permitted.';
      } elseif ($size > 50 * 1024 * 1024) {
        $err = 'File size must be under 50 MB.';
      } else {
        // Choose target folder
        $relFolder = ($category === 'statutory') ? 'assets/secretarial/statutory' : 'assets/secretarial';
        $absFolder = $siteRoot . '/' . $relFolder;
        if (!is_dir($absFolder)) @mkdir($absFolder, 0755, true);

        // Sanitize filename slug based on title and year
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $title));
        $slug = trim($slug, '-');
        if (!$slug) $slug = 'document';
        if ($year) {
          $cleanYear = preg_replace('/[^a-zA-Z0-9\-]+/', '', $year);
          if ($cleanYear && strpos($slug, $cleanYear) === false) {
            $slug .= '-' . $cleanYear;
          }
        }
        $fileName = $slug . '.pdf';
        $targetPath = $absFolder . '/' . $fileName;

        if (is_file($targetPath)) {
          $fileName = $slug . '-' . date('Ymd-His') . '.pdf';
          $targetPath = $absFolder . '/' . $fileName;
        }

        if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $targetPath)) {
          $catLabels = [
            'annual_return'  => 'Annual Returns & Reports (MGT-7)',
            'annual_report'  => 'Annual Reports',
            'sustainability' => 'Sustainability & ESG',
            'policy'         => 'Corporate Governance Policies',
            'statutory'      => 'Unit Statutory Filings',
            'general'        => 'General Corporate Disclosures'
          ];

          $prefix = ($category === 'annual_return' || $category === 'annual_report') ? 'DOC-AR-' : 'DOC-';
          $cleanYearCode = preg_replace('/[^a-zA-Z0-9]/', '', $year);
          $newId = $prefix . ($cleanYearCode ? $cleanYearCode . '-' : '') . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));

          $newDoc = [
            'id'                  => $newId,
            'title'               => $title,
            'category'            => $category,
            'category_label'      => $catLabels[$category] ?? ucfirst($category),
            'year'                => $year ?: date('Y'),
            'unit'                => $unit,
            'file'                => $relFolder . '/' . $fileName,
            'description'         => $desc,
            'is_core'             => false,
            'file_size'           => $size,
            'file_size_formatted' => round($size / 1024 / 1024, 2) . ' MB',
            'last_modified'       => date('Y-m-d')
          ];

          // If it's an annual return or report, prepend so newest appears first
          if ($category === 'annual_return' || $category === 'annual_report') {
            array_unshift($docsData['documents'], $newDoc);
          } else {
            $docsData['documents'][] = $newDoc;
          }

          staff_save_documents($docsData);
          staff_sync_html_governance_menus($docsData, $siteRoot);
          $msg = 'Document "' . htmlspecialchars($title) . '" successfully uploaded and published to registry & website.';
        } else {
          $err = 'Could not save the uploaded file. Check folder permissions on ' . $relFolder . '.';
        }
      }
    }
  }

  // 2. Edit Document Field Names, Year, Category & In-Place PDF Overwrite
  if ($action === 'replace_pdf' || $action === 'edit_doc') {
    $docId = trim($_POST['id'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $year = trim($_POST['year'] ?? '');
    $unit = trim($_POST['unit'] ?? '');
    $desc = trim($_POST['description'] ?? '');

    $targetIndex = null;
    foreach ($docsData['documents'] as $i => $d) {
      if ($d['id'] === $docId) {
        $targetIndex = $i;
        break;
      }
    }

    if ($targetIndex === null) {
      $err = 'Document record not found.';
    } else {
      $doc = &$docsData['documents'][$targetIndex];
      if ($title !== '') $doc['title'] = $title;
      if ($year !== '') $doc['year'] = $year;
      if ($unit !== '') $doc['unit'] = $unit;
      if ($desc !== '') $doc['description'] = $desc;

      if ($category !== '') {
        $catLabels = [
          'annual_return'  => 'Annual Returns & Reports (MGT-7)',
          'annual_report'  => 'Annual Reports',
          'sustainability' => 'Sustainability & ESG',
          'policy'         => 'Corporate Governance Policies',
          'statutory'      => 'Unit Statutory Filings',
          'general'        => 'General Corporate Disclosures'
        ];
        $doc['category'] = $category;
        $doc['category_label'] = $catLabels[$category] ?? ucfirst($category);
      }

      // Optional file replacement
      if (!empty($_FILES['pdf_file']['tmp_name']) && is_uploaded_file($_FILES['pdf_file']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['pdf_file']['name'], PATHINFO_EXTENSION));
        $size = $_FILES['pdf_file']['size'];

        if ($ext !== 'pdf' || !staff_is_valid_pdf($_FILES['pdf_file']['tmp_name'])) {
          $err = 'Replacement file must be a genuine PDF document (.pdf).';
        } elseif ($size > 50 * 1024 * 1024) {
          $err = 'Replacement PDF file size must be under 50 MB.';
        } else {
          $destAbs = $siteRoot . '/' . $doc['file'];
          $destDir = dirname($destAbs);
          if (!is_dir($destDir)) @mkdir($destDir, 0755, true);

          if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $destAbs)) {
            $doc['file_size'] = $size;
            $doc['file_size_formatted'] = round($size / 1024 / 1024, 2) . ' MB';
            $doc['last_modified'] = date('Y-m-d');
            $msg = 'PDF file and website field names for "' . htmlspecialchars($doc['title']) . '" successfully updated.';
          } else {
            $err = 'Failed to overwrite existing file. Check permissions on ' . htmlspecialchars($doc['file']) . '.';
          }
        }
      } else {
        $doc['last_modified'] = date('Y-m-d');
        $msg = 'Document website title and field metadata for "' . htmlspecialchars($doc['title']) . '" updated.';
      }

      if (!$err) {
        staff_save_documents($docsData);
        staff_sync_html_governance_menus($docsData, $siteRoot);
      }
    }
  }

  // 3. Quick in-place overwrite for Sustainability Report
  if ($action === 'quick_replace_sustainability') {
    $sustainTitle = trim($_POST['sustain_title'] ?? '');
    $sustainYear = trim($_POST['sustain_year'] ?? '');

    if (empty($_FILES['sustainability_pdf']['tmp_name']) || !is_uploaded_file($_FILES['sustainability_pdf']['tmp_name'])) {
      // Just metadata update
      if ($sustainTitle || $sustainYear) {
        foreach ($docsData['documents'] as $i => $d) {
          if ($d['id'] === 'DOC-SUSTAINABILITY' || ($d['category'] ?? '') === 'sustainability') {
            if ($sustainTitle) $docsData['documents'][$i]['title'] = $sustainTitle;
            if ($sustainYear) $docsData['documents'][$i]['year'] = $sustainYear;
            $docsData['documents'][$i]['last_modified'] = date('Y-m-d');
            break;
          }
        }
        staff_save_documents($docsData);
        staff_sync_html_governance_menus($docsData, $siteRoot);
        $msg = 'Sustainability Report website title and year successfully updated!';
      } else {
        $err = 'Please select a valid Sustainability Report PDF or provide updated title/year.';
      }
    } else {
      $ext = strtolower(pathinfo($_FILES['sustainability_pdf']['name'], PATHINFO_EXTENSION));
      $size = $_FILES['sustainability_pdf']['size'];

      if ($ext !== 'pdf' || !staff_is_valid_pdf($_FILES['sustainability_pdf']['tmp_name'])) {
        $err = 'Sustainability Report must be a genuine PDF document (.pdf).';
      } elseif ($size > 50 * 1024 * 1024) {
        $err = 'File size must be under 50 MB.';
      } else {
        $destAbs = $siteRoot . '/assets/Vasudha_Sustainability_Report_2024-25.pdf';
        if (move_uploaded_file($_FILES['sustainability_pdf']['tmp_name'], $destAbs)) {
          foreach ($docsData['documents'] as $i => $d) {
            if ($d['id'] === 'DOC-SUSTAINABILITY' || ($d['category'] ?? '') === 'sustainability') {
              if ($sustainTitle) $docsData['documents'][$i]['title'] = $sustainTitle;
              if ($sustainYear) $docsData['documents'][$i]['year'] = $sustainYear;
              $docsData['documents'][$i]['file_size'] = $size;
              $docsData['documents'][$i]['file_size_formatted'] = round($size / 1024 / 1024, 2) . ' MB';
              $docsData['documents'][$i]['last_modified'] = date('Y-m-d');
              break;
            }
          }
          staff_save_documents($docsData);
          staff_sync_html_governance_menus($docsData, $siteRoot);
          $msg = 'Sustainability Report PDF replaced & field names updated! All website navigation links now serve the new file.';
        } else {
          $err = 'Failed to overwrite Sustainability Report PDF file on server.';
        }
      }
    }
  }

  // 4. Delete document / Remove Old Year Ones
  if ($action === 'delete') {
    $docId = trim($_POST['id'] ?? '');
    $deleted = false;
    $deletedTitle = '';
    $newDocs = [];
    foreach ($docsData['documents'] as $d) {
      if ($d['id'] === $docId) {
        $deleted = true;
        $deletedTitle = $d['title'] ?? $docId;
        // Optionally delete file if requested or preserve archive
        continue;
      }
      $newDocs[] = $d;
    }

    if ($deleted) {
      $docsData['documents'] = $newDocs;
      staff_save_documents($docsData);
      staff_sync_html_governance_menus($docsData, $siteRoot);
      $msg = 'Document "' . htmlspecialchars($deletedTitle) . '" (' . htmlspecialchars($docId) . ') removed from website registry and navigation menus.';
    } else {
      $err = 'Document ID ' . htmlspecialchars($docId) . ' not found.';
    }
  }

  // 5. Force Re-Sync Website Menus
  if ($action === 'force_sync') {
    staff_sync_html_governance_menus($docsData, $siteRoot);
    $msg = 'All website menus, top-bar dropdowns, and statutory grids successfully re-synchronized with documents.json!';
  }
}

// Reload live file sizes & timestamps
foreach ($docsData['documents'] as $idx => $doc) {
  $p = $siteRoot . '/' . $doc['file'];
  if (is_file($p)) {
    $st = @stat($p);
    if ($st) {
      $docsData['documents'][$idx]['file_size'] = $st['size'];
      $docsData['documents'][$idx]['file_size_formatted'] = round($st['size'] / 1024 / 1024, 2) . ' MB';
      $docsData['documents'][$idx]['last_modified'] = date('Y-m-d', $st['mtime']);
    }
  } else {
    $docsData['documents'][$idx]['file_missing'] = true;
  }
}

$staff_title = 'Corporate Governance Desk';
require __DIR__ . '/includes/header.php';

$editDoc = null;
if (isset($_GET['edit'])) {
  foreach ($docsData['documents'] as $d) {
    if ($d['id'] === $_GET['edit']) {
      $editDoc = $d;
      break;
    }
  }
}

// Find primary sustainability report
$primarySustain = null;
foreach ($docsData['documents'] as $d) {
  if ($d['id'] === 'DOC-SUSTAINABILITY' || ($d['category'] ?? '') === 'sustainability') {
    $primarySustain = $d;
    break;
  }
}

// Counts by category
$counts = [
  'all'            => count($docsData['documents']),
  'annual_return'  => 0,
  'sustainability' => 0,
  'policy'         => 0,
  'statutory'      => 0,
  'general'        => 0
];
foreach ($docsData['documents'] as $d) {
  $c = $d['category'] ?? 'general';
  if ($c === 'annual_report') $c = 'annual_return';
  if (isset($counts[$c])) $counts[$c]++;
  else $counts['general']++;
}
?>

<style>
  .doc-table { width: 100%; border-collapse: collapse; margin-top: 14px; font-size: 13px; }
  .doc-table th { background: #F8FAFC; color: #475569; font-weight: 700; text-align: left; padding: 12px 14px; border-bottom: 2px solid var(--sp-border); font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.05em; }
  .doc-table td { padding: 14px; border-bottom: 1px solid #F1F5F9; vertical-align: middle; }
  .doc-table tr:hover { background: #F8FAFC; }

  .badge-category { display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; }
  .badge-cat-annual_return, .badge-cat-annual_report { background: #EFF6FF; color: #1D4ED8; border: 1px solid #BFDBFE; }
  .badge-cat-sustainability { background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; }
  .badge-cat-policy { background: #F3E8FF; color: #7E22CE; border: 1px solid #E9D5FF; }
  .badge-cat-statutory { background: #FEF3C7; color: #B45309; border: 1px solid #FDE68A; }
  .badge-cat-general { background: #F1F5F9; color: #475569; border: 1px solid #E2E8F0; }

  .filter-btn { background: #FFFFFF; border: 1px solid var(--sp-border); color: #475569; padding: 7px 14px; border-radius: 8px; font-size: 12.5px; font-weight: 600; cursor: pointer; transition: all 0.15s ease; }
  .filter-btn:hover { border-color: #0E8F6C; color: #0E8F6C; }
  .filter-btn.active { background: #0E8F6C !important; color: #FFFFFF !important; border-color: #0E8F6C !important; }

  .preset-add-btn { background: #FFFFFF; border: 1px dashed #0E8F6C; color: #065F46; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.15s; }
  .preset-add-btn:hover { background: #ECFDF5; }
</style>

<?php if ($msg): ?>
  <div class="card ok">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
    <span><?php echo htmlspecialchars($msg); ?></span>
  </div>
<?php endif; ?>

<?php if ($err): ?>
  <div class="card err-box">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <span><?php echo htmlspecialchars($err); ?></span>
  </div>
<?php endif; ?>

<!-- Desk Overview Banner -->
<div class="card" style="background: linear-gradient(135deg, #096B51 0%, #0E8F6C 55%, #1DB88A 100%); color:#FFFFFF; border:0; padding:24px 28px; box-shadow: 0 8px 24px rgba(14,143,108,0.18);">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
    <div>
      <div style="text-transform:uppercase; font-size:11px; letter-spacing:0.12em; opacity:0.85; font-weight:700; margin-bottom:4px;">
        Vasudha Pharma Chem &bull; Secretarial &bull; Statutory &bull; MCA Filings
      </div>
      <h1 style="margin:0; font-size:24px; color:#FFFFFF;">Corporate Governance Desk</h1>
      <p style="margin:6px 0 0; opacity:0.9; font-size:13.5px; max-width:820px; line-height:1.5;">
        Complete control over corporate disclosures: customize website field names (e.g. <em>Annual Report 2025-2026</em>), add new financial year filings, remove old year documents, replace PDFs in-place, and publish statutory environmental clearances.
      </p>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
      <a href="#uploadDocForm" class="btn" style="background:#FFFFFF; color:#096B51; font-weight:700; box-shadow:0 2px 8px rgba(0,0,0,0.12);">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        + Add New Year Report / Filing
      </a>
      <form method="post" style="margin:0; display:inline;">
        <?php echo staff_csrf_field(); ?>
        <input type="hidden" name="action" value="force_sync">
        <button type="submit" class="btn" style="background:rgba(255,255,255,0.2); color:#FFFFFF; border:1px solid rgba(255,255,255,0.3); font-weight:600;" title="Re-sync navigation menus across all HTML files">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
          Sync Website Menus
        </button>
      </form>
      <a href="../statutory-regulations.html" target="_blank" rel="noopener" class="btn" style="background:rgba(255,255,255,0.2); color:#FFFFFF; border:1px solid rgba(255,255,255,0.3); font-weight:600;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/></svg>
        Preview Statutory Page
      </a>
    </div>
  </div>
</div>

<!-- Highlight: Sustainability & ESG Report Controller -->
<div class="card" id="quickSustain" style="border-left: 4px solid #10B981; background: #F0FDF4; padding: 20px 24px;">
  <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px;">
    <div style="flex: 1; min-width: 280px;">
      <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
        <span style="background:#D1FAE5; color:#065F46; padding:3px 8px; border-radius:6px; font-size:11px; font-weight:700; text-transform:uppercase;">
          Live Corporate Sustainability Publication
        </span>
        <span style="font-size:12px; color:#047857; font-weight:600;">Linked in Header, Top-Bar &amp; Footer</span>
      </div>
      <h2 style="margin:4px 0 6px; font-size:18px; color:#064E3B;">
        <?php echo htmlspecialchars($primarySustain['title'] ?? 'Vasudha Sustainability & ESG Report'); ?>
      </h2>
      <p style="margin:0 0 10px; font-size:13px; color:#065F46; line-height:1.5;">
        Website Field Name: <strong><?php echo htmlspecialchars($primarySustain['title'] ?? 'Vasudha Sustainability & ESG Report'); ?></strong> &bull; Period: <strong><?php echo htmlspecialchars($primarySustain['year'] ?? '2024-25'); ?></strong><br>
        Target file: <code><?php echo htmlspecialchars($primarySustain['file'] ?? 'assets/Vasudha_Sustainability_Report_2024-25.pdf'); ?></code>. Updating the field name or replacing the PDF file instantly updates every visitor link across the entire website.
      </p>
      <div style="display:flex; gap:12px; font-size:12px; color:#047857;">
        <span>File Size: <strong><?php echo htmlspecialchars($primarySustain['file_size_formatted'] ?? '1.8 MB'); ?></strong></span>
        <span>&bull;</span>
        <span>Last Updated: <strong><?php echo htmlspecialchars($primarySustain['last_modified'] ?? date('Y-m-d')); ?></strong></span>
      </div>
    </div>
    <div style="background:#FFFFFF; border:1px solid #A7F3D0; border-radius:10px; padding:16px; min-width:300px; max-width:420px; width:100%;">
      <form method="post" enctype="multipart/form-data">
        <?php echo staff_csrf_field(); ?>
        <input type="hidden" name="action" value="quick_replace_sustainability">
        <label style="margin-top:0; font-size:12px; color:#064E3B;">Update Website Display Title / Field Name</label>
        <input type="text" name="sustain_title" value="<?php echo htmlspecialchars($primarySustain['title'] ?? 'Vasudha Sustainability & ESG Report 2024-25'); ?>" style="font-size:12.5px; padding:6px 10px; margin-bottom:8px;">

        <label style="margin-top:0; font-size:12px; color:#064E3B;">Financial Year Label</label>
        <input type="text" name="sustain_year" value="<?php echo htmlspecialchars($primarySustain['year'] ?? '2024-25'); ?>" style="font-size:12.5px; padding:6px 10px; margin-bottom:8px;">

        <label style="margin-top:0; font-size:12px; color:#064E3B;">Select New PDF File (Optional — leave blank to keep existing file)</label>
        <input type="file" name="sustainability_pdf" accept="application/pdf,.pdf" style="font-size:12px; padding:6px 10px; margin-bottom:10px;">

        <div style="display:flex; gap:8px;">
          <button type="submit" class="btn btn-primary" style="font-size:12.5px; flex:1; justify-content:center; background:#0E8F6C;">
            ⚡ Save &amp; Publish Updates
          </button>
          <a href="../<?php echo htmlspecialchars($primarySustain['file'] ?? 'assets/Vasudha_Sustainability_Report_2024-25.pdf'); ?>" target="_blank" rel="noopener" class="btn btn-ghost" style="font-size:12px; padding:6px 10px;" title="View current file">
            View
          </a>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Metrics & Quick Filters Bar -->
<div class="row-4" style="margin-bottom:20px;">
  <div class="stat-card" onclick="filterDocs('all')" style="cursor:pointer;">
    <div class="stat-label">Total Documents</div>
    <div class="stat-num"><?php echo $counts['all']; ?></div>
    <div class="stat-sub">Active regulatory registry</div>
  </div>
  <div class="stat-card" onclick="filterDocs('annual_return')" style="cursor:pointer; border-left:4px solid #1D4ED8;">
    <div class="stat-label">Annual Returns &amp; Reports</div>
    <div class="stat-num" style="color:#1D4ED8;"><?php echo $counts['annual_return']; ?></div>
    <div class="stat-sub">MGT-7 &amp; Annual Reports</div>
  </div>
  <div class="stat-card" onclick="filterDocs('policy')" style="cursor:pointer; border-left:4px solid #7E22CE;">
    <div class="stat-label">Governance Policies</div>
    <div class="stat-num" style="color:#7E22CE;"><?php echo $counts['policy']; ?></div>
    <div class="stat-sub">Board &amp; Committee charters</div>
  </div>
  <div class="stat-card" onclick="filterDocs('statutory')" style="cursor:pointer; border-left:4px solid #D97706;">
    <div class="stat-label">Unit Statutory Filings</div>
    <div class="stat-num" style="color:#D97706;"><?php echo $counts['statutory']; ?></div>
    <div class="stat-sub">Unit 1–5 statutory clearances</div>
  </div>
</div>

<!-- Search & Category Filters -->
<div class="card" style="padding:16px 20px; margin-bottom:18px;">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
    <div style="flex:1; min-width:240px; max-width:400px; position:relative;">
      <input type="text" id="docSearch" placeholder="Search by title, year (e.g. 2024-25, 2025-26), unit, or filename..." oninput="filterDocs()" style="padding-left:34px;">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); pointer-events:none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    </div>

    <div style="display:flex; gap:6px; flex-wrap:wrap;">
      <button type="button" class="filter-btn active" data-cat="all" onclick="setCategoryFilter('all', this)">All (<?php echo $counts['all']; ?>)</button>
      <button type="button" class="filter-btn" data-cat="annual_return" onclick="setCategoryFilter('annual_return', this)">Annual Returns / Reports (<?php echo $counts['annual_return']; ?>)</button>
      <button type="button" class="filter-btn" data-cat="sustainability" onclick="setCategoryFilter('sustainability', this)">Sustainability (<?php echo $counts['sustainability']; ?>)</button>
      <button type="button" class="filter-btn" data-cat="policy" onclick="setCategoryFilter('policy', this)">Governance Policies (<?php echo $counts['policy']; ?>)</button>
      <button type="button" class="filter-btn" data-cat="statutory" onclick="setCategoryFilter('statutory', this)">Unit Statutory (<?php echo $counts['statutory']; ?>)</button>
    </div>
  </div>
</div>

<!-- Documents Registry Table -->
<div class="card" style="padding:0; overflow:hidden;">
  <div style="padding:18px 24px 14px; border-bottom:1px solid var(--sp-border); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
    <div>
      <h2 style="margin:0; font-size:17px;">Published Regulatory Registry &amp; Website Documents</h2>
      <div style="font-size:12.5px; color:var(--sp-text-muted); margin-top:2px;">
        Editing the title updates the display label on the website navigation menus. You can also delete older years or add new ones anytime.
      </div>
    </div>
    <a href="#uploadDocForm" class="btn btn-primary" style="font-size:12.5px; background:#0E8F6C;">
      + Add New Year Report / Filing
    </a>
  </div>

  <table class="doc-table">
    <thead>
      <tr>
        <th style="width:50px;">Year</th>
        <th>Website Field Name &amp; Document Title</th>
        <th>Category</th>
        <th>Unit / Scope</th>
        <th>File Path &amp; Size</th>
        <th>Last Updated</th>
        <th style="text-align:right;">Actions</th>
      </tr>
    </thead>
    <tbody id="docTableBody">
      <?php foreach ($docsData['documents'] as $d): 
        $cat = $d['category'] ?? 'general';
        if ($cat === 'annual_report') $cat = 'annual_return';
        $badgeClass = 'badge-cat-' . $cat;
        $isMissing = !empty($d['file_missing']);
        $searchIndex = strtolower(($d['id'] ?? '') . ' ' . ($d['title'] ?? '') . ' ' . ($d['year'] ?? '') . ' ' . ($d['unit'] ?? '') . ' ' . ($d['file'] ?? '') . ' ' . ($d['description'] ?? ''));
      ?>
        <tr class="doc-row" data-cat="<?php echo htmlspecialchars($cat); ?>" data-search="<?php echo htmlspecialchars($searchIndex); ?>">
          <td>
            <span style="font-weight:700; font-family:monospace; font-size:12.5px; color:#0F172A; background:#F1F5F9; padding:2px 6px; border-radius:4px;">
              <?php echo htmlspecialchars($d['year'] ?? '—'); ?>
            </span>
          </td>
          <td>
            <div style="font-weight:700; color:#0F172A; font-size:14px;">
              <?php echo htmlspecialchars($d['title'] ?? 'Document'); ?>
            </div>
            <?php if (!empty($d['description'])): ?>
              <div style="font-size:11.5px; color:#64748B; margin-top:3px; max-width:440px; line-height:1.4;">
                <?php echo htmlspecialchars($d['description']); ?>
              </div>
            <?php endif; ?>
            <div style="font-size:11px; color:#94A3B8; margin-top:2px; font-family:monospace;">
              ID: <?php echo htmlspecialchars($d['id'] ?? ''); ?>
            </div>
          </td>
          <td>
            <span class="badge-category <?php echo $badgeClass; ?>">
              <?php echo htmlspecialchars($d['category_label'] ?? ucfirst($cat)); ?>
            </span>
          </td>
          <td>
            <span style="font-weight:600; color:#475569; font-size:12.5px;">
              <?php echo htmlspecialchars($d['unit'] ?? 'Corporate'); ?>
            </span>
          </td>
          <td>
            <div style="font-size:11.5px; font-family:monospace; color:#334155;">
              <?php echo htmlspecialchars($d['file'] ?? ''); ?>
            </div>
            <div style="font-size:11px; color:#64748B; margin-top:2px;">
              <?php if ($isMissing): ?>
                <span style="color:#EF4444; font-weight:700;">⚠️ File missing on disk</span>
              <?php else: ?>
                <span><?php echo htmlspecialchars($d['file_size_formatted'] ?? '—'); ?></span>
              <?php endif; ?>
            </div>
          </td>
          <td>
            <span style="font-size:12px; color:#64748B;">
              <?php echo htmlspecialchars($d['last_modified'] ?? '—'); ?>
            </span>
          </td>
          <td style="text-align:right; white-space:nowrap;">
            <a href="documents.php?edit=<?php echo urlencode($d['id']); ?>#editDocForm" class="btn btn-ghost" style="padding:5px 9px; font-size:12px;" title="Edit website field name, title, year, or replace PDF">
              Edit / Rename
            </a>
            <a href="../<?php echo htmlspecialchars($d['file']); ?>" target="_blank" rel="noopener" class="btn btn-ghost" style="padding:5px 9px; font-size:12px;" title="View published PDF">
              View
            </a>
            <form method="post" onsubmit="return confirm('Remove document &quot;<?php echo htmlspecialchars($d['title'] ?? ''); ?>&quot; (<?php echo htmlspecialchars($d['year'] ?? ''); ?>) from the website?');" style="display:inline; margin:0;">
              <?php echo staff_csrf_field(); ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?php echo htmlspecialchars($d['id']); ?>">
              <button type="submit" class="btn btn-danger" style="padding:5px 9px; font-size:12px;" title="Remove this year's filing from website registry">
                Delete
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Edit Document Form (When edit button is clicked) -->
<?php if ($editDoc): ?>
<div class="card" id="editDocForm" style="border: 2px solid #0E8F6C; background:#F8FAFC; margin-top:24px;">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
    <div>
      <span style="font-size:11.5px; font-weight:700; color:#0E8F6C; text-transform:uppercase;">Website Field &amp; Document Editor</span>
      <h2 style="margin:2px 0 0; font-size:18px;">Edit: <?php echo htmlspecialchars($editDoc['title']); ?> (<?php echo htmlspecialchars($editDoc['id']); ?>)</h2>
    </div>
    <a class="btn btn-ghost" href="documents.php" style="font-size:12px;">Cancel Edit</a>
  </div>

  <form method="post" enctype="multipart/form-data">
    <?php echo staff_csrf_field(); ?>
    <input type="hidden" name="action" value="edit_doc">
    <input type="hidden" name="id" value="<?php echo htmlspecialchars($editDoc['id']); ?>">

    <div class="row">
      <div>
        <label>Website Display Title / Field Name *</label>
        <input name="title" required value="<?php echo htmlspecialchars($editDoc['title']); ?>" placeholder="e.g. Annual Report 2025-2026, Annual Returns (Form MGT-7) 2024-2025">
        <div style="font-size:11.5px; color:#64748B; margin-top:3px;">
          This is the exact label displayed on the website dropdowns and links.
        </div>
      </div>
      <div>
        <label>Regulatory Category *</label>
        <select name="category" required>
          <option value="annual_return" <?php echo ($editDoc['category'] === 'annual_return' || $editDoc['category'] === 'annual_report') ? 'selected' : ''; ?>>Annual Returns &amp; Reports (MGT-7)</option>
          <option value="sustainability" <?php echo $editDoc['category'] === 'sustainability' ? 'selected' : ''; ?>>Sustainability &amp; ESG Reports</option>
          <option value="policy" <?php echo $editDoc['category'] === 'policy' ? 'selected' : ''; ?>>Corporate Governance Policies &amp; Charters</option>
          <option value="statutory" <?php echo $editDoc['category'] === 'statutory' ? 'selected' : ''; ?>>Unit Statutory Filings (CFO / CFE / Act)</option>
          <option value="general" <?php echo $editDoc['category'] === 'general' ? 'selected' : ''; ?>>General Corporate Disclosures</option>
        </select>
      </div>
    </div>

    <div class="row">
      <div>
        <label>Financial Year / Period Label</label>
        <input name="year" value="<?php echo htmlspecialchars($editDoc['year']); ?>" placeholder="e.g. 2025-26, 2024-25, 2026">
      </div>
      <div>
        <label>Applicable Unit / Entity</label>
        <input name="unit" value="<?php echo htmlspecialchars($editDoc['unit'] ?? 'Corporate'); ?>" placeholder="e.g. Corporate, VPCL – II, VPCL – III, VPCL – V">
      </div>
    </div>

    <div>
      <label>Replace PDF File (Optional — Leave blank to keep existing file: <code><?php echo htmlspecialchars($editDoc['file']); ?></code>)</label>
      <input type="file" name="pdf_file" accept="application/pdf,.pdf" style="background:#FFFFFF; padding:8px 12px;">
    </div>

    <label>Brief Description &amp; Disclosure Note</label>
    <textarea name="description" rows="2" placeholder="Statutory compliance summary, board meeting adoption details..."><?php echo htmlspecialchars($editDoc['description'] ?? ''); ?></textarea>

    <p style="margin-top:20px; display:flex; gap:10px; align-items:center;">
      <button class="btn btn-primary" type="submit" style="background:#0E8F6C;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
        Save Website Field Name &amp; Updates
      </button>
      <a class="btn btn-ghost" href="documents.php">Cancel</a>
    </p>
  </form>
</div>
<?php endif; ?>

<!-- Upload New Document / Add New Financial Year Form -->
<div class="card" id="uploadDocForm" style="margin-top:24px;">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
    <div>
      <h2 style="margin:0; font-size:18px;">+ Add New Financial Year Report or Statutory Filing</h2>
      <div style="font-size:12.5px; color:var(--sp-text-muted); margin-top:2px;">
        Upload the official PDF and define the field name to publish on the website navigation.
      </div>
    </div>
    <div style="display:flex; gap:6px; flex-wrap:wrap;">
      <button type="button" class="preset-add-btn" onclick="applyAddPreset('annual_return', 'Annual Report 2025-2026', '2025-26', 'Annual statutory filing under Companies Act, 2013.')">
        + Annual Report 2025-26
      </button>
      <button type="button" class="preset-add-btn" onclick="applyAddPreset('annual_return', 'Annual Return (Form MGT-7) 2025-2026', '2025-26', 'Form MGT-7 statutory annual return filing.')">
        + MGT-7 Return 2025-26
      </button>
      <button type="button" class="preset-add-btn" onclick="applyAddPreset('sustainability', 'Vasudha Sustainability & ESG Report 2025-26', '2025-26', 'Corporate environmental, social and governance report.')">
        + Sustainability Report 2025-26
      </button>
    </div>
  </div>

  <form method="post" enctype="multipart/form-data" id="newDocForm">
    <?php echo staff_csrf_field(); ?>
    <input type="hidden" name="action" value="upload_new">

    <div class="row">
      <div>
        <label>Document Title / Website Field Name *</label>
        <input name="title" id="newTitle" required placeholder="e.g. Annual Report 2025-2026 or Annual Return (Form MGT-7) 2025-2026">
        <div style="font-size:11.5px; color:#64748B; margin-top:3px;">
          This title will be displayed directly in website navigation menus and dropdowns.
        </div>
      </div>
      <div>
        <label>Regulatory Category *</label>
        <select name="category" id="newCategory" required>
          <option value="annual_return">Annual Returns &amp; Reports (MGT-7)</option>
          <option value="sustainability">Sustainability &amp; ESG Reports</option>
          <option value="policy">Corporate Governance Policies &amp; Charters</option>
          <option value="statutory">Unit Statutory Filings (CFO / CFE / Act)</option>
          <option value="general">General Corporate Disclosures</option>
        </select>
      </div>
    </div>

    <div class="row">
      <div>
        <label>Financial Year / Period (e.g. 2025-26) *</label>
        <input name="year" id="newYear" required placeholder="e.g. 2025-26 or 2026-27">
      </div>
      <div>
        <label>Applicable Unit / Entity</label>
        <input name="unit" id="newUnit" placeholder="e.g. Corporate, VPCL – II, VPCL – III, VPCL – V" value="Corporate">
      </div>
    </div>

    <label>Select PDF Document to Upload * (Max 50 MB)</label>
    <input type="file" name="pdf_file" accept="application/pdf,.pdf" required style="padding:9px 12px; background:#FFFFFF;">

    <label>Brief Description &amp; Disclosure Note</label>
    <textarea name="description" id="newDesc" rows="2" placeholder="Describe the document filing, section under Companies Act, or board meeting adoption date..."></textarea>

    <p style="margin-top:22px;">
      <button class="btn btn-primary" type="submit" style="background:#0E8F6C;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        Upload &amp; Publish New Financial Year Document
      </button>
    </p>
  </form>
</div>

<script>
let currentCategory = 'all';

function applyAddPreset(cat, title, year, desc) {
  var catEl = document.getElementById('newCategory');
  var titleEl = document.getElementById('newTitle');
  var yearEl = document.getElementById('newYear');
  var descEl = document.getElementById('newDesc');
  if (catEl) catEl.value = cat;
  if (titleEl) titleEl.value = title;
  if (yearEl) yearEl.value = year;
  if (descEl) descEl.value = desc;
  document.getElementById('uploadDocForm').scrollIntoView({ behavior: 'smooth' });
}

function setCategoryFilter(cat, btn) {
  currentCategory = cat;
  document.querySelectorAll('.filter-btn').forEach(b => {
    b.classList.remove('active');
  });
  if (btn) {
    btn.classList.add('active');
  }
  filterDocs();
}

function filterDocs(forcedCat) {
  if (forcedCat) {
    const btn = document.querySelector(`.filter-btn[data-cat="${forcedCat}"]`);
    setCategoryFilter(forcedCat, btn);
    return;
  }
  const q = (document.getElementById('docSearch').value || '').toLowerCase().trim();
  const rows = document.querySelectorAll('.doc-row');

  rows.forEach(r => {
    const rowCat = r.getAttribute('data-cat') || '';
    const rowText = r.getAttribute('data-search') || '';

    const matchesCat = (currentCategory === 'all' || rowCat === currentCategory);
    const matchesQuery = (!q || rowText.includes(q));

    if (matchesCat && matchesQuery) {
      r.style.display = '';
    } else {
      r.style.display = 'none';
    }
  });
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
