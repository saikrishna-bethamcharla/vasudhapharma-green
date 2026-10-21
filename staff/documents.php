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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  staff_verify_csrf();
  $action = $_POST['action'] ?? '';

  // 1. Upload new document
  if ($action === 'upload_new') {
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? 'general');
    $year = trim($_POST['year'] ?? '');
    $unit = trim($_POST['unit'] ?? 'Corporate');
    $desc = trim($_POST['description'] ?? '');

    if ($title === '') {
      $err = 'Document title is required.';
    } elseif (empty($_FILES['pdf_file']['tmp_name']) || !is_uploaded_file($_FILES['pdf_file']['tmp_name'])) {
      $err = 'Please select a valid PDF file to upload.';
    } else {
      $origName = $_FILES['pdf_file']['name'];
      $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
      $size = $_FILES['pdf_file']['size'];

      if ($ext !== 'pdf') {
        $err = 'Only PDF documents (.pdf) are permitted.';
      } elseif ($size > 40 * 1024 * 1024) {
        $err = 'File size must be under 40 MB.';
      } else {
        // Choose target folder
        $relFolder = ($category === 'statutory') ? 'assets/secretarial/statutory' : 'assets/secretarial';
        $absFolder = $siteRoot . '/' . $relFolder;
        if (!is_dir($absFolder)) @mkdir($absFolder, 0755, true);

        // Sanitize filename slug
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

        // If exists and different name needed, append short stamp
        if (is_file($targetPath)) {
          $fileName = $slug . '-' . date('Ymd-His') . '.pdf';
          $targetPath = $absFolder . '/' . $fileName;
        }

        if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $targetPath)) {
          $newId = 'DOC-' . strtoupper(substr(md5(time() . $fileName), 0, 8));
          $catLabels = [
            'sustainability' => 'Sustainability & ESG',
            'annual_return'  => 'Annual Returns (MGT-7)',
            'policy'         => 'Corporate Governance Policies',
            'statutory'      => 'Unit Statutory Filings',
            'general'        => 'General Corporate Disclosures'
          ];

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

          $docsData['documents'][] = $newDoc;
          staff_save_documents($docsData);
          $msg = 'Document "' . htmlspecialchars($title) . '" successfully uploaded and published to registry.';
        } else {
          $err = 'Could not save the uploaded file. Check folder permissions on ' . $relFolder . '.';
        }
      }
    }
  }

  // 2. Replace / Update existing document
  if ($action === 'replace_pdf') {
    $docId = trim($_POST['id'] ?? '');
    $title = trim($_POST['title'] ?? '');
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

      // File replacement if uploaded
      if (!empty($_FILES['pdf_file']['tmp_name']) && is_uploaded_file($_FILES['pdf_file']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['pdf_file']['name'], PATHINFO_EXTENSION));
        $size = $_FILES['pdf_file']['size'];

        if ($ext !== 'pdf') {
          $err = 'Replacement file must be a PDF document (.pdf).';
        } elseif ($size > 40 * 1024 * 1024) {
          $err = 'Replacement PDF file size must be under 40 MB.';
        } else {
          $destAbs = $siteRoot . '/' . $doc['file'];
          $destDir = dirname($destAbs);
          if (!is_dir($destDir)) @mkdir($destDir, 0755, true);

          // Atomic overwrite of target file so existing website hyperlinks remain intact!
          if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $destAbs)) {
            $doc['file_size'] = $size;
            $doc['file_size_formatted'] = round($size / 1024 / 1024, 2) . ' MB';
            $doc['last_modified'] = date('Y-m-d');
            $msg = 'PDF file for "' . htmlspecialchars($doc['title']) . '" successfully updated in-place.';
          } else {
            $err = 'Failed to overwrite existing file. Check permissions on ' . htmlspecialchars($doc['file']) . '.';
          }
        }
      } else {
        $doc['last_modified'] = date('Y-m-d');
        $msg = 'Document metadata for "' . htmlspecialchars($doc['title']) . '" updated.';
      }

      if (!$err) {
        staff_save_documents($docsData);
      }
    }
  }

  // 3. Quick in-place overwrite for Sustainability Report
  if ($action === 'quick_replace_sustainability') {
    if (empty($_FILES['sustainability_pdf']['tmp_name']) || !is_uploaded_file($_FILES['sustainability_pdf']['tmp_name'])) {
      $err = 'Please select a valid Sustainability Report PDF to upload.';
    } else {
      $ext = strtolower(pathinfo($_FILES['sustainability_pdf']['name'], PATHINFO_EXTENSION));
      $size = $_FILES['sustainability_pdf']['size'];

      if ($ext !== 'pdf') {
        $err = 'Sustainability Report must be a PDF document (.pdf).';
      } elseif ($size > 50 * 1024 * 1024) {
        $err = 'File size must be under 50 MB.';
      } else {
        $destAbs = $siteRoot . '/assets/Vasudha_Sustainability_Report_2024-25.pdf';
        if (move_uploaded_file($_FILES['sustainability_pdf']['tmp_name'], $destAbs)) {
          // Update registry record
          foreach ($docsData['documents'] as $i => $d) {
            if ($d['id'] === 'DOC-SUSTAINABILITY' || strpos($d['file'], 'Sustainability_Report') !== false) {
              $docsData['documents'][$i]['file_size'] = $size;
              $docsData['documents'][$i]['file_size_formatted'] = round($size / 1024 / 1024, 2) . ' MB';
              $docsData['documents'][$i]['last_modified'] = date('Y-m-d');
              break;
            }
          }
          staff_save_documents($docsData);
          $msg = 'Sustainability Report PDF replaced successfully! All website navigation links now serve the new file.';
        } else {
          $err = 'Failed to overwrite Sustainability Report PDF file on server.';
        }
      }
    }
  }

  // 4. Delete document
  if ($action === 'delete') {
    $docId = trim($_POST['id'] ?? '');
    $deleted = false;
    $newDocs = [];
    foreach ($docsData['documents'] as $d) {
      if ($d['id'] === $docId) {
        if (!empty($d['is_core'])) {
          $err = 'Core statutory documents (such as active annual returns & sustainability report) cannot be deleted; use "Replace PDF" to update them.';
          $newDocs[] = $d;
          continue;
        }
        $deleted = true;
        // Optionally delete file if in secretarial folder
        $filePath = $siteRoot . '/' . $d['file'];
        if (strpos($d['file'], 'assets/secretarial/') === 0 && is_file($filePath)) {
          @unlink($filePath);
        }
      } else {
        $newDocs[] = $d;
      }
    }
    if ($deleted && !$err) {
      $docsData['documents'] = $newDocs;
      staff_save_documents($docsData);
      $msg = 'Document removed from registry.';
    }
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

$staff_title = 'Compliance & Documents';
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

// Counts by category
$counts = [
  'all'            => count($docsData['documents']),
  'sustainability' => 0,
  'annual_return'  => 0,
  'policy'         => 0,
  'statutory'      => 0,
  'general'        => 0
];
foreach ($docsData['documents'] as $d) {
  $c = $d['category'] ?? 'general';
  if (isset($counts[$c])) $counts[$c]++;
  else $counts['general']++;
}
?>

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
        Corporate Governance &bull; Secretarial &bull; Statutory Filings
      </div>
      <h1 style="margin:0; font-size:22px; color:#FFFFFF;">Corporate Secretarial, Compliance &amp; Documents Desk</h1>
      <p style="margin:6px 0 0; opacity:0.9; font-size:13px; max-width:800px; line-height:1.5;">
        Upload and maintain corporate regulatory filings, statutory environmental consents, Annual Returns (Form MGT-7), Committee Charters, and the published Sustainability &amp; ESG Reports across the global website.
      </p>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
      <a href="#quickSustain" class="btn" style="background:#FFFFFF; color:var(--sp-primary-dark); font-weight:700;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        Replace Sustainability Report
      </a>
      <a href="#uploadDocForm" class="btn" style="background:rgba(255,255,255,0.2); color:#FFFFFF; border:1px solid rgba(255,255,255,0.3); font-weight:600;">
        + Upload New PDF
      </a>
    </div>
  </div>
</div>

<!-- Highlight: Quick In-Place Sustainability Report Manager -->
<div class="card" id="quickSustain" style="border-left: 4px solid #10B981; background: #F0FDF4; padding: 20px 24px;">
  <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px;">
    <div style="flex: 1; min-width: 280px;">
      <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
        <span style="background:#D1FAE5; color:#065F46; padding:3px 8px; border-radius:6px; font-size:11px; font-weight:700; text-transform:uppercase;">
          Primary Corporate Publication
        </span>
        <span style="font-size:12px; color:#047857; font-weight:600;">Linked in Header, Top-Bar &amp; Footer</span>
      </div>
      <h2 style="margin:4px 0 6px; font-size:18px; color:#064E3B;">Vasudha Sustainability &amp; ESG Report</h2>
      <p style="margin:0 0 10px; font-size:13px; color:#065F46; line-height:1.5;">
        Target path: <code>assets/Vasudha_Sustainability_Report_2024-25.pdf</code>. 
        Uploading a new version here overwrites the live file in-place, meaning all visitor links on every page immediately open the latest report without code edits.
      </p>
      <div style="display:flex; gap:12px; font-size:12px; color:#047857;">
        <span>Live File Size: <strong><?php echo htmlspecialchars($docsData['documents'][0]['file_size_formatted'] ?? '1.8 MB'); ?></strong></span>
        <span>&bull;</span>
        <span>Last Updated: <strong><?php echo htmlspecialchars($docsData['documents'][0]['last_modified'] ?? date('Y-m-d')); ?></strong></span>
      </div>
    </div>
    <div style="background:#FFFFFF; border:1px solid #A7F3D0; border-radius:10px; padding:16px; min-width:280px; max-width:380px; width:100%;">
      <form method="post" enctype="multipart/form-data">
        <?php echo staff_csrf_field(); ?>
        <input type="hidden" name="action" value="quick_replace_sustainability">
        <label style="margin-top:0; font-size:12px; color:#064E3B;">Select New Sustainability PDF to Overwrite</label>
        <input type="file" name="sustainability_pdf" accept="application/pdf,.pdf" required style="font-size:12.5px; padding:8px 10px; margin-bottom:10px;">
        <div style="display:flex; gap:8px;">
          <button type="submit" class="btn btn-primary" style="font-size:12.5px; flex:1; justify-content:center;" onclick="return confirm('Replace the current live Sustainability Report across the entire website?');">
            ⚡ Overwrite &amp; Publish
          </button>
          <a href="../assets/Vasudha_Sustainability_Report_2024-25.pdf" target="_blank" rel="noopener" class="btn btn-ghost" style="font-size:12px; padding:6px 10px;" title="View current file">
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
    <div class="stat-sub">Active regulatory files</div>
  </div>
  <div class="stat-card" onclick="filterDocs('annual_return')" style="cursor:pointer;">
    <div class="stat-label">Annual Returns (MGT-7)</div>
    <div class="stat-num"><?php echo $counts['annual_return']; ?></div>
    <div class="stat-sub">FY 2021-22 to 2024-25</div>
  </div>
  <div class="stat-card" onclick="filterDocs('policy')" style="cursor:pointer;">
    <div class="stat-label">Governance Policies</div>
    <div class="stat-num"><?php echo $counts['policy']; ?></div>
    <div class="stat-sub">Board &amp; Committee charters</div>
  </div>
  <div class="stat-card" onclick="filterDocs('statutory')" style="cursor:pointer;">
    <div class="stat-label">Unit Statutory Filings</div>
    <div class="stat-num"><?php echo $counts['statutory']; ?></div>
    <div class="stat-sub">CFO / CFE / Public Act orders</div>
  </div>
</div>

<!-- Documents Registry Table -->
<div class="card">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
    <div>
      <h2 style="margin:0; font-size:18px;">Published Documents &amp; Regulatory Filings (<?php echo count($docsData['documents']); ?>)</h2>
      <div style="font-size:12.5px; color:var(--sp-text-muted); margin-top:2px;">
        Click "Replace PDF" on any document to upload an updated version or revise its description.
      </div>
    </div>
    <a href="#uploadDocForm" class="btn btn-primary" style="font-size:13px;">+ Upload New Document</a>
  </div>

  <!-- Search & Category Filters -->
  <div class="table-search-bar" style="margin-bottom:18px;">
    <input type="text" id="docSearch" placeholder="🔍 Search documents, years, units..." onkeyup="filterDocs()" style="max-width:300px;">
    <div style="display:flex; gap:6px; flex-wrap:wrap;">
      <button type="button" class="btn btn-ghost filter-btn active" data-cat="all" onclick="setCategoryFilter('all', this)" style="padding:6px 12px; font-size:12px;">All (<?php echo $counts['all']; ?>)</button>
      <button type="button" class="btn btn-ghost filter-btn" data-cat="sustainability" onclick="setCategoryFilter('sustainability', this)" style="padding:6px 12px; font-size:12px;">Sustainability (<?php echo $counts['sustainability']; ?>)</button>
      <button type="button" class="btn btn-ghost filter-btn" data-cat="annual_return" onclick="setCategoryFilter('annual_return', this)" style="padding:6px 12px; font-size:12px;">Annual Returns (<?php echo $counts['annual_return']; ?>)</button>
      <button type="button" class="btn btn-ghost filter-btn" data-cat="policy" onclick="setCategoryFilter('policy', this)" style="padding:6px 12px; font-size:12px;">Policies (<?php echo $counts['policy']; ?>)</button>
      <button type="button" class="btn btn-ghost filter-btn" data-cat="statutory" onclick="setCategoryFilter('statutory', this)" style="padding:6px 12px; font-size:12px;">Unit Statutory (<?php echo $counts['statutory']; ?>)</button>
    </div>
  </div>

  <table id="documentsTable">
    <thead>
      <tr>
        <th>Document Title &amp; Category</th>
        <th>Applicable Unit</th>
        <th>Year / Period</th>
        <th>File Details</th>
        <th style="text-align:right">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($docsData['documents'] as $d): 
        $cat = $d['category'] ?? 'general';
        $badgeClass = ($cat === 'sustainability') ? 'badge-open' : (($cat === 'annual_return') ? 'badge-live' : 'badge-closed');
      ?>
      <tr class="doc-row" data-cat="<?php echo htmlspecialchars($cat); ?>" data-search="<?php echo htmlspecialchars(strtolower($d['title'] . ' ' . $d['year'] . ' ' . $d['unit'] . ' ' . ($d['description'] ?? ''))); ?>">
        <td style="max-width:380px;">
          <div style="display:flex; align-items:flex-start; gap:10px;">
            <div style="width:34px; height:38px; flex-shrink:0; background:#FEE2E2; border-radius:6px; display:flex; align-items:center; justify-content:center; color:#DC2626; font-weight:800; font-size:10px; border:1px solid #FECACA;">
              PDF
            </div>
            <div>
              <strong style="color:var(--sp-text-main); font-size:13.5px; display:block; margin-bottom:2px;">
                <?php echo htmlspecialchars($d['title']); ?>
              </strong>
              <div style="font-size:11.5px; color:var(--sp-text-muted); line-height:1.4;">
                <?php echo htmlspecialchars($d['description'] ?? ''); ?>
              </div>
              <div style="margin-top:4px;">
                <span class="<?php echo $badgeClass; ?>" style="font-size:11px;">
                  <?php echo htmlspecialchars($d['category_label'] ?? ucfirst($cat)); ?>
                </span>
                <?php if (!empty($d['is_core'])): ?>
                  <span style="font-size:10.5px; color:#096B51; background:#ECFDF5; padding:2px 6px; border-radius:4px; font-weight:600; margin-left:4px;">Core Hyperlink</span>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </td>
        <td>
          <span style="font-weight:600; font-size:12.5px; color:#334155; background:#F1F5F9; padding:3px 8px; border-radius:4px;">
            <?php echo htmlspecialchars($d['unit'] ?? 'Corporate'); ?>
          </span>
        </td>
        <td>
          <strong style="font-size:13px; color:#0F172A;"><?php echo htmlspecialchars($d['year'] ?? '—'); ?></strong>
        </td>
        <td style="font-size:12px; color:#64748B;">
          <div>Size: <strong><?php echo htmlspecialchars($d['file_size_formatted'] ?? '—'); ?></strong></div>
          <div style="font-size:11px; color:#94A3B8; margin-top:2px;">Updated: <?php echo htmlspecialchars($d['last_modified'] ?? '—'); ?></div>
        </td>
        <td style="text-align:right; white-space:nowrap;">
          <a class="btn btn-ghost" style="padding:5px 9px; font-size:12px;" href="../<?php echo htmlspecialchars($d['file']); ?>" target="_blank" rel="noopener" title="Open live PDF file">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
            Open
          </a>
          <a class="btn btn-ghost" style="padding:5px 9px; font-size:12px; color:var(--sp-primary); font-weight:600;" href="documents.php?edit=<?php echo urlencode($d['id']); ?>#editForm">
            Replace PDF
          </a>
          <?php if (empty($d['is_core'])): ?>
            <form method="post" style="display:inline;" onsubmit="return confirm('Remove document &quot;<?php echo htmlspecialchars($d['title']); ?>&quot; from public registry?');">
              <?php echo staff_csrf_field(); ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?php echo htmlspecialchars($d['id']); ?>">
              <button type="submit" class="btn btn-danger" style="padding:5px 9px; font-size:12px;">Delete</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$docsData['documents']): ?>
        <tr><td colspan="5" style="text-align:center; padding:32px; color:#94A3B8;">No documents registered yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Edit / Replace Existing Document Form -->
<?php if ($editDoc): ?>
<div class="card" id="editForm" style="border-left: 4px solid var(--sp-primary); background:#FAFAFA;">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
    <div>
      <h2 style="margin:0; font-size:18px;">Replace File / Edit Document: <?php echo htmlspecialchars($editDoc['title']); ?></h2>
      <div style="font-size:12px; color:var(--sp-text-muted); margin-top:2px;">
        Current File: <code><?php echo htmlspecialchars($editDoc['file']); ?></code> (<?php echo htmlspecialchars($editDoc['file_size_formatted'] ?? '—'); ?>)
      </div>
    </div>
    <a href="documents.php" class="btn btn-ghost" style="font-size:12px;">Close / Cancel</a>
  </div>

  <form method="post" enctype="multipart/form-data">
    <?php echo staff_csrf_field(); ?>
    <input type="hidden" name="action" value="replace_pdf">
    <input type="hidden" name="id" value="<?php echo htmlspecialchars($editDoc['id']); ?>">

    <div class="row">
      <div>
        <label>Document Title</label>
        <input name="title" required value="<?php echo htmlspecialchars($editDoc['title']); ?>">
      </div>
      <div>
        <label>Financial Year / Period</label>
        <input name="year" value="<?php echo htmlspecialchars($editDoc['year']); ?>" placeholder="e.g. 2024-25">
      </div>
    </div>

    <div class="row">
      <div>
        <label>Applicable Unit / Entity</label>
        <input name="unit" value="<?php echo htmlspecialchars($editDoc['unit'] ?? 'Corporate'); ?>" placeholder="e.g. Corporate, VPCL – II, VPCL – III, VPCL – V">
      </div>
      <div>
        <label>Upload New Replacement PDF File (Leave blank to keep existing file)</label>
        <input type="file" name="pdf_file" accept="application/pdf,.pdf">
      </div>
    </div>

    <label>Brief Description &amp; Disclosure Note</label>
    <textarea name="description" placeholder="Statutory compliance summary, board meeting adoption details..."><?php echo htmlspecialchars($editDoc['description'] ?? ''); ?></textarea>

    <p style="margin-top:20px; display:flex; gap:10px; align-items:center;">
      <button class="btn btn-primary" type="submit">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
        Save Changes &amp; Replace PDF
      </button>
      <a class="btn btn-ghost" href="documents.php">Cancel</a>
    </p>
  </form>
</div>
<?php endif; ?>

<!-- Upload New Document Form -->
<div class="card" id="uploadDocForm">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
    <div>
      <h2 style="margin:0; font-size:18px;">Upload New Document to Corporate Registry</h2>
      <div style="font-size:12.5px; color:var(--sp-text-muted); margin-top:2px;">
        Add a new Annual Return, statutory environmental statement, or committee policy.
      </div>
    </div>
  </div>

  <form method="post" enctype="multipart/form-data">
    <?php echo staff_csrf_field(); ?>
    <input type="hidden" name="action" value="upload_new">

    <div class="row">
      <div>
        <label>Document Title *</label>
        <input name="title" required placeholder="e.g. Annual Return (Form MGT-7) 2025-2026">
      </div>
      <div>
        <label>Regulatory Category *</label>
        <select name="category" required>
          <option value="annual_return">Annual Returns (Form MGT-7)</option>
          <option value="sustainability">Sustainability &amp; ESG Reports</option>
          <option value="policy">Corporate Governance Policies &amp; Charters</option>
          <option value="statutory">Unit Statutory Filings (CFO / CFE / Act)</option>
          <option value="general">General Regulatory Disclosures</option>
        </select>
      </div>
    </div>

    <div class="row">
      <div>
        <label>Financial Year / Period</label>
        <input name="year" placeholder="e.g. 2025-26 or 2026">
      </div>
      <div>
        <label>Applicable Unit / Entity</label>
        <input name="unit" placeholder="e.g. Corporate, VPCL – II, VPCL – III, VPCL – V" value="Corporate">
      </div>
    </div>

    <label>Select PDF File to Upload * (Max 40 MB)</label>
    <input type="file" name="pdf_file" accept="application/pdf,.pdf" required style="padding:9px 12px; background:#FFFFFF;">

    <label>Brief Description &amp; Disclosure Note</label>
    <textarea name="description" placeholder="Describe the document filing, section under Companies Act, or environmental clearance scope..."></textarea>

    <p style="margin-top:22px;">
      <button class="btn btn-primary" type="submit">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        Upload &amp; Publish Document
      </button>
    </p>
  </form>
</div>

<script>
let currentCategory = 'all';

function setCategoryFilter(cat, btn) {
  currentCategory = cat;
  document.querySelectorAll('.filter-btn').forEach(b => {
    b.classList.remove('active');
    b.style.background = '';
    b.style.color = '';
  });
  if (btn) {
    btn.classList.add('active');
    btn.style.background = 'var(--sp-primary)';
    btn.style.color = '#FFFFFF';
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
