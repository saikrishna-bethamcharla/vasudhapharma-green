<?php
require __DIR__ . '/includes/auth.php';
staff_require_desk('marketing');
$u = staff_user();

$currentTab = $_GET['tab'] ?? 'leads';
if (!in_array($currentTab, ['leads', 'catalog'], true)) {
  $currentTab = 'leads';
}

$msg = '';
$err = '';

// Load data sources
$data = staff_load_desk('marketing');
if (!isset($data['items']) || !is_array($data['items'])) $data['items'] = [];

// Fallback: If empty, load from marketing.json or products catalog
if (empty($data['items'])) {
  $seedFile = __DIR__ . '/data/marketing.json';
  if (is_file($seedFile)) {
    $seeded = json_decode(file_get_contents($seedFile), true);
    if (!empty($seeded['items'])) {
      $data['items'] = $seeded['items'];
      staff_save_desk('marketing', $data);
    }
  }
}

$enquiries = staff_load_enquiries();
$contacts = staff_load_contacts();

// Combine and unify leads stream
$unifiedLeads = [];
foreach ($enquiries as $enq) {
  $enq['source_origin'] = 'enquiries';
  $unifiedLeads[] = $enq;
}
foreach ($contacts as $con) {
  $con['source_origin'] = 'contacts';
  // Normalize contact record to match lead fields
  if (!isset($con['type'])) {
    $con['type'] = !empty($con['department']) ? ('Contact Form (' . $con['department'] . ')') : 'Website Contact';
  }
  $unifiedLeads[] = $con;
}

// Sort newest first by timestamp or date
usort($unifiedLeads, function ($a, $b) {
  $tA = intval($a['timestamp'] ?? 0);
  $tB = intval($b['timestamp'] ?? 0);
  if ($tA && $tB) return $tB - $tA;
  return strcmp($b['date'] ?? '', $a['date'] ?? '');
});

// =========================================================================
// CSV EXPORT HANDLER
// =========================================================================
if (isset($_GET['export']) && $_GET['export'] === 'leads_csv') {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=vasudha-commercial-leads-' . date('Y-m-d') . '.csv');
  $out = fopen('php://output', 'w');
  // Output BOM for Excel UTF-8 compatibility
  fputs($out, "\xEF\xBB\xBF");
  fputcsv($out, ['Reference ID', 'Type / Channel', 'Date', 'Client Name', 'Email', 'Phone', 'Company / Org', 'Product / Service', 'Category', 'Quantity', 'Grade / Reg', 'Status', 'Technical Dossier / Requirements']);
  foreach ($unifiedLeads as $l) {
    fputcsv($out, [
      $l['id'] ?? '',
      $l['type'] ?? 'Inquiry',
      $l['date'] ?? '',
      $l['name'] ?? '',
      $l['email'] ?? '',
      $l['phone'] ?? '',
      $l['company'] ?? '',
      $l['product'] ?? ($l['productName'] ?? ''),
      $l['category'] ?? ($l['productCategory'] ?? ''),
      $l['quantity'] ?? ($l['qty'] ?? ''),
      $l['reg'] ?? ($l['regulatoryStatus'] ?? ($l['spec'] ?? '')),
      $l['status'] ?? 'New',
      str_replace(["\r\n", "\r", "\n"], ' | ', $l['description'] ?? ($l['message'] ?? ''))
    ]);
  }
  fclose($out);
  exit;
}

// =========================================================================
// POST ACTIONS
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  staff_verify_csrf();
  $action = $_POST['action'] ?? '';

  // 1. Update Lead Status / Notes
  if ($action === 'update_lead_status') {
    $leadId = trim($_POST['lead_id'] ?? '');
    $newStatus = trim($_POST['status'] ?? 'New');
    $internalNotes = trim($_POST['internal_notes'] ?? '');
    $origin = trim($_POST['source_origin'] ?? 'enquiries');

    $found = false;
    if ($origin === 'contacts') {
      foreach ($contacts as &$c) {
        if (($c['id'] ?? '') === $leadId) {
          $c['status'] = $newStatus;
          if ($internalNotes !== '') $c['internal_notes'] = $internalNotes;
          $c['updated_at'] = date('Y-m-d H:i:s');
          $found = true;
          break;
        }
      }
      if ($found) {
        staff_save_contacts($contacts);
        $msg = 'Contact inquiry "' . htmlspecialchars($leadId) . '" status updated to ' . htmlspecialchars($newStatus) . '.';
      }
    } else {
      foreach ($enquiries as &$e) {
        if (($e['id'] ?? '') === $leadId) {
          $e['status'] = $newStatus;
          if ($internalNotes !== '') $e['internal_notes'] = $internalNotes;
          $e['updated_at'] = date('Y-m-d H:i:s');
          $found = true;
          break;
        }
      }
      if ($found) {
        staff_save_enquiries($enquiries);
        $msg = 'Commercial lead "' . htmlspecialchars($leadId) . '" status updated to ' . htmlspecialchars($newStatus) . '.';
      }
    }

    // Refresh unified stream
    header('Location: marketing.php?tab=leads&msg=' . urlencode($msg));
    exit;
  }

  // 2. Catalog: Save Product Item
  if ($action === 'save_item') {
    $id = trim($_POST['id'] ?? '');
    if (!$id) {
      $cleanName = preg_replace('/[^a-z0-9]+/i', '-', strtolower(trim($_POST['name'] ?? '')));
      $id = 'pr-' . trim($cleanName, '-') . '-' . mt_rand(100, 999);
    }

    $item = [
      'id' => $id,
      'name' => trim($_POST['name'] ?? ''),
      'category' => trim($_POST['category'] ?? 'APIs'),
      'cas_no' => trim($_POST['cas_no'] ?? ''),
      'therapeutic' => trim($_POST['therapeutic'] ?? ''),
      'specifications' => trim($_POST['specifications'] ?? 'IP / USP / BP / In-House'),
      'dmf_status' => trim($_POST['dmf_status'] ?? 'Available'),
      'status' => trim($_POST['status'] ?? 'active'),
      'note' => trim($_POST['note'] ?? ''),
      'updated_at' => date('Y-m-d H:i:s'),
    ];

    if ($item['name'] === '') {
      $err = 'Product name is required.';
    } else {
      $found = false;
      foreach ($data['items'] as $i => $it) {
        if ($it['id'] === $item['id']) {
          $data['items'][$i] = array_merge($it, $item);
          $found = true;
          break;
        }
      }
      if (!$found) {
        array_unshift($data['items'], $item);
      }
      staff_save_desk('marketing', $data);
      $msg = 'Product "' . htmlspecialchars($item['name']) . '" successfully saved to Marketing portfolio.';
    }
  }

  // 3. Catalog: Delete Product Item
  if ($action === 'delete') {
    $id = $_POST['id'] ?? '';
    $deletedName = '';
    foreach ($data['items'] as $it) {
      if ($it['id'] === $id) { $deletedName = $it['name']; break; }
    }
    $data['items'] = array_values(array_filter($data['items'], function ($it) use ($id) { return $it['id'] !== $id; }));
    staff_save_desk('marketing', $data);
    $msg = 'Product "' . htmlspecialchars($deletedName ?: $id) . '" removed from portfolio.';
  }
}

if (!empty($_GET['msg'])) {
  $msg = $_GET['msg'];
}

$staff_title = 'Marketing & Commercial Operations';
require __DIR__ . '/includes/header.php';

$edit = null;
if (isset($_GET['edit'])) {
  foreach ($data['items'] as $it) {
    if ($it['id'] === $_GET['edit']) {
      $edit = $it;
      break;
    }
  }
}

// Compute Leads Metrics
$leadStats = [
  'total'     => count($unifiedLeads),
  'new'       => 0,
  'in_review' => 0,
  'quoted'    => 0,
  'closed'    => 0,
  'cdmo'      => 0,
  'rfq'       => 0,
  'bot'       => 0,
  'regional'  => 0,
  'contact'   => 0
];

foreach ($unifiedLeads as $l) {
  $st = $l['status'] ?? 'New';
  if ($st === 'New') $leadStats['new']++;
  elseif ($st === 'In Review') $leadStats['in_review']++;
  elseif ($st === 'Quoted') $leadStats['quoted']++;
  elseif ($st === 'Closed') $leadStats['closed']++;

  $tp = strtolower($l['type'] ?? '');
  if (strpos($tp, 'cdmo') !== false) $leadStats['cdmo']++;
  elseif (strpos($tp, 'virtual') !== false || strpos($tp, 'bot') !== false) $leadStats['bot']++;
  elseif (strpos($tp, 'regional') !== false) $leadStats['regional']++;
  elseif (strpos($tp, 'rfq') !== false || strpos($tp, 'product') !== false) $leadStats['rfq']++;
  else $leadStats['contact']++;
}

// Calculate Catalog Category Counts
$counts = [
  'All' => count($data['items']),
  'APIs' => 0,
  'Intermediates' => 0,
  'Pellets' => 0,
  'Piperidone Derivatives' => 0,
  'Under Development' => 0,
];
foreach ($data['items'] as $it) {
  $cat = $it['category'] ?? 'APIs';
  if (isset($counts[$cat])) $counts[$cat]++;
  else $counts[$cat] = 1;
}
?>

<style>
.cat-pill {
  border: 1px solid var(--sp-border);
  background: #FFFFFF;
  color: var(--sp-text-muted);
  font-size: 12.5px;
  font-weight: 600;
  padding: 6px 14px;
  border-radius: 9999px;
  cursor: pointer;
  transition: all 0.15s ease;
  user-select: none;
}
.cat-pill:hover {
  background: var(--sp-primary-light);
  color: var(--sp-primary-dark);
  border-color: #A7F3D0;
}
.cat-pill.is-active {
  background: var(--sp-primary);
  color: #FFFFFF;
  border-color: var(--sp-primary-dark);
  box-shadow: 0 2px 8px rgba(14, 143, 108, 0.25);
}
.badge-cat {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.02em;
}
.badge-cat-api { background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; }
.badge-cat-int { background: #EFF6FF; color: #2563EB; border: 1px solid #BFDBFE; }
.badge-cat-pel { background: #F5F3FF; color: #7C3AED; border: 1px solid #DDD6FE; }
.badge-cat-pip { background: #FFFBEB; color: #D97706; border: 1px solid #FDE68A; }
.badge-cat-dev { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; }
.badge-cat-def { background: #F1F5F9; color: #475569; border: 1px solid #E2E8F0; }

.lead-type-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}
.badge-cdmo { background: #FEF3C7; color: #B45309; border: 1px solid #FDE68A; }
.badge-rfq { background: #EFF6FF; color: #1D4ED8; border: 1px solid #BFDBFE; }
.badge-bot { background: #F3E8FF; color: #7E22CE; border: 1px solid #E9D5FF; }
.badge-regional { background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; }
.badge-contact { background: #F1F5F9; color: #475569; border: 1px solid #CBD5E1; }

.status-pill {
  padding: 3px 9px;
  border-radius: 9999px;
  font-size: 11px;
  font-weight: 700;
  display: inline-block;
}
.status-new { background: #DCFCE7; color: #15803D; border: 1px solid #86EFAC; }
.status-review { background: #FEF9C3; color: #A16207; border: 1px solid #FDE047; }
.status-quoted { background: #DBEAFE; color: #1E40AF; border: 1px solid #93C5FD; }
.status-closed { background: #F1F5F9; color: #64748B; border: 1px solid #CBD5E1; }

/* Modal Backdrop & Box */
.vp-lead-modal-backdrop {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(4px);
  z-index: 9999;
  display: none;
  align-items: center;
  justify-content: center;
  padding: 16px;
}
.vp-lead-modal-box {
  background: #FFFFFF;
  border-radius: 14px;
  width: 100%;
  max-width: 640px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 40px rgba(0,0,0,0.2);
  border: 1px solid #E2E8F0;
  padding: 24px;
}
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

<!-- Desk Overview Hero Banner -->
<div class="card" style="background: linear-gradient(135deg, #096B51 0%, #0E8F6C 55%, #0088AA 100%); color:#FFFFFF; border:0; padding:24px 28px; box-shadow: 0 8px 24px rgba(14,143,108,0.18); margin-bottom:20px;">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
    <div>
      <div style="text-transform:uppercase; font-size:11px; letter-spacing:0.12em; opacity:0.85; font-weight:700; margin-bottom:4px;">
        Commercial Desk &bull; Vasudha Pharma
      </div>
      <h1 style="margin:0; font-size:22px; color:#FFFFFF;">Marketing &amp; Commercial Operations Hub</h1>
      <p style="margin:6px 0 0; opacity:0.9; font-size:13px; line-height:1.5;">
        Live intake stream for CDMO proposals, multi-ton API inquiries, RFQ carts, AI chatbot leads, and pharmaceutical catalog sync.
      </p>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
      <?php if ($currentTab === 'leads'): ?>
        <a href="marketing.php?tab=leads&export=leads_csv" class="btn" style="background:#FFFFFF; color:#096B51; font-weight:700; box-shadow:0 2px 8px rgba(0,0,0,0.12); font-size:12.5px;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Export Leads (CSV)
        </a>
        <a href="../cmo-cdmo.html" target="_blank" rel="noopener" class="btn" style="background:rgba(255,255,255,0.2); color:#FFFFFF; border:1px solid rgba(255,255,255,0.3); font-weight:600; font-size:12.5px;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/></svg>
          CDMO Portal
        </a>
      <?php else: ?>
        <a href="#productForm" class="btn" style="background:#FFFFFF; color:#096B51; font-weight:700; box-shadow:0 2px 8px rgba(0,0,0,0.12); font-size:12.5px;">
          + Register New Product
        </a>
        <a href="../products.html" target="_blank" rel="noopener" class="btn" style="background:rgba(255,255,255,0.2); color:#FFFFFF; border:1px solid rgba(255,255,255,0.3); font-weight:600; font-size:12.5px;">
          View Public Catalog
        </a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Primary Sub-Desk Switcher Tabs -->
<div style="display:flex; gap:10px; margin:0 0 22px; border-bottom:2px solid #E2E8F0; padding-bottom:12px; flex-wrap:wrap;">
  <a href="marketing.php?tab=leads" class="btn" style="<?php echo $currentTab === 'leads' ? 'background:#0E8F6C; color:#FFFFFF; font-weight:700; box-shadow:0 4px 12px rgba(14,143,108,0.2);' : 'background:#FFFFFF; color:#475569; border:1px solid #CBD5E1; font-weight:600;'; ?> padding:10px 18px; border-radius:10px; font-size:13.5px; display:inline-flex; align-items:center; gap:8px;">
    <span>📬 Commercial Inquiries &amp; Leads Stream</span>
    <span style="background:<?php echo $currentTab === 'leads' ? 'rgba(255,255,255,0.25)' : '#F1F5F9'; ?>; padding:2px 8px; border-radius:12px; font-size:11.5px; font-weight:800;"><?php echo $leadStats['total']; ?></span>
    <?php if ($leadStats['new'] > 0): ?>
      <span style="background:#EF4444; color:#FFFFFF; font-size:10px; font-weight:800; padding:1px 6px; border-radius:10px;"><?php echo $leadStats['new']; ?> NEW</span>
    <?php endif; ?>
  </a>
  <a href="marketing.php?tab=catalog" class="btn" style="<?php echo $currentTab === 'catalog' ? 'background:#0088AA; color:#FFFFFF; font-weight:700; box-shadow:0 4px 12px rgba(0,136,170,0.2);' : 'background:#FFFFFF; color:#475569; border:1px solid #CBD5E1; font-weight:600;'; ?> padding:10px 18px; border-radius:10px; font-size:13.5px; display:inline-flex; align-items:center; gap:8px;">
    <span>🏷️ Product Portfolio &amp; Catalog Manager</span>
    <span style="background:<?php echo $currentTab === 'catalog' ? 'rgba(255,255,255,0.25)' : '#F1F5F9'; ?>; padding:2px 8px; border-radius:12px; font-size:11.5px; font-weight:800;"><?php echo count($data['items']); ?></span>
  </a>
</div>

<?php if ($currentTab === 'leads'): ?>
  <!-- ======================================================================= -->
  <!-- TAB 1: COMMERCIAL INQUIRIES & LEADS STREAM                              -->
  <!-- ======================================================================= -->

  <!-- Lead Metrics Cards -->
  <div class="row-4" style="margin-bottom: 22px;">
    <div class="stat-card">
      <div class="stat-label">Total Received Leads</div>
      <div class="stat-num"><?php echo $leadStats['total']; ?></div>
      <div class="stat-sub"><?php echo $leadStats['new']; ?> unread &bull; <?php echo $leadStats['in_review']; ?> in review</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">CDMO &amp; CDA Proposals</div>
      <div class="stat-num" style="color: #D97706;"><?php echo $leadStats['cdmo']; ?></div>
      <div class="stat-sub">Custom synthesis &amp; scale-up</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Product RFQs &amp; Carts</div>
      <div class="stat-num" style="color: #2563EB;"><?php echo $leadStats['rfq']; ?></div>
      <div class="stat-sub">Commercial molecules &amp; sourcing</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Bot &amp; Regional Inquiries</div>
      <div class="stat-num" style="color: #7C3AED;"><?php echo $leadStats['bot'] + $leadStats['regional']; ?></div>
      <div class="stat-sub"><?php echo $leadStats['bot']; ?> bot RFQs &bull; <?php echo $leadStats['regional']; ?> regional</div>
    </div>
  </div>

  <!-- Leads Table Card -->
  <div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
      <div>
        <h2 style="margin:0; font-size:18px;">Incoming Client Leads Registry (<span id="visibleLeadsCount"><?php echo count($unifiedLeads); ?></span> / <?php echo count($unifiedLeads); ?>)</h2>
        <div style="font-size:12.5px; color:var(--sp-text-muted); margin-top:2px;">
          Filter by channel, search by client, company, or molecule, and update lead pipeline status in real-time.
        </div>
      </div>
    </div>

    <!-- Channel Filter Pills -->
    <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 14px;">
      <button type="button" class="cat-pill is-active" data-lead-filter="all">All Channels (<?php echo $leadStats['total']; ?>)</button>
      <button type="button" class="cat-pill" data-lead-filter="cdmo">CDMO Proposals (<?php echo $leadStats['cdmo']; ?>)</button>
      <button type="button" class="cat-pill" data-lead-filter="rfq">Product RFQs (<?php echo $leadStats['rfq']; ?>)</button>
      <button type="button" class="cat-pill" data-lead-filter="bot">AI Assistant Bot (<?php echo $leadStats['bot']; ?>)</button>
      <button type="button" class="cat-pill" data-lead-filter="regional">Regional Portals (<?php echo $leadStats['regional']; ?>)</button>
      <button type="button" class="cat-pill" data-lead-filter="contact">General Contact (<?php echo $leadStats['contact']; ?>)</button>
    </div>

    <!-- Search Input -->
    <div class="table-search-bar" style="margin-bottom:16px;">
      <input type="text" id="leadSearchInput" placeholder="Quick search by buyer name, email, company, molecule, or reference ID..." style="width: 100%; max-width: 520px;">
    </div>

    <table id="leadsTable">
      <thead>
        <tr>
          <th>Reference &amp; Date</th>
          <th>Lead Channel</th>
          <th>Buyer / Company</th>
          <th>Product / Molecule</th>
          <th>Quantity &amp; Specs</th>
          <th>Status</th>
          <th style="text-align:right">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($unifiedLeads as $l):
        $type = $l['type'] ?? 'Inquiry';
        $lowType = strtolower($type);
        $channelKey = 'contact';
        $badgeClass = 'badge-contact';
        if (strpos($lowType, 'cdmo') !== false) { $channelKey = 'cdmo'; $badgeClass = 'badge-cdmo'; }
        elseif (strpos($lowType, 'virtual') !== false || strpos($lowType, 'bot') !== false) { $channelKey = 'bot'; $badgeClass = 'badge-bot'; }
        elseif (strpos($lowType, 'regional') !== false) { $channelKey = 'regional'; $badgeClass = 'badge-regional'; }
        elseif (strpos($lowType, 'rfq') !== false || strpos($lowType, 'product') !== false) { $channelKey = 'rfq'; $badgeClass = 'badge-rfq'; }

        $status = $l['status'] ?? 'New';
        $statusClass = 'status-new';
        if ($status === 'In Review') $statusClass = 'status-review';
        elseif ($status === 'Quoted') $statusClass = 'status-quoted';
        elseif ($status === 'Closed') $statusClass = 'status-closed';

        $phone = preg_replace('/[^0-9+]/', '', $l['phone'] ?? '');
        $email = $l['email'] ?? '';
        $name = $l['name'] ?? 'Prospective Client';
        $comp = $l['company'] ?? ($l['organisation'] ?? 'Organization');
        $prod = $l['product'] ?? ($l['productName'] ?? ($l['molecule'] ?? 'Multiple Active Substances'));
        $qty = $l['quantity'] ?? ($l['qty'] ?? ($l['quantityRequired'] ?? '—'));
        $desc = $l['description'] ?? ($l['message'] ?? '');
        $leadId = $l['id'] ?? 'VP-LEAD';
        $origin = $l['source_origin'] ?? 'enquiries';
      ?>
        <tr data-channel="<?php echo $channelKey; ?>" data-status="<?php echo htmlspecialchars($status); ?>">
          <td>
            <code style="background:#F1F5F9; font-weight:700; color:#0F172A; padding:2px 6px; border-radius:4px; font-size:11.5px;"><?php echo htmlspecialchars($leadId); ?></code>
            <div style="font-size:11.5px; color:#64748B; margin-top:3px;"><?php echo htmlspecialchars($l['date'] ?? 'Recent'); ?></div>
          </td>
          <td>
            <span class="lead-type-badge <?php echo $badgeClass; ?>">
              <?php echo htmlspecialchars($type); ?>
            </span>
          </td>
          <td>
            <div style="font-weight:700; color:#0F172A; font-size:13.5px;"><?php echo htmlspecialchars($name); ?></div>
            <div style="font-size:12px; color:#475569;"><?php echo htmlspecialchars($comp); ?></div>
            <div style="font-size:11.5px; color:#64748B; margin-top:2px;">
              <a href="mailto:<?php echo htmlspecialchars($email); ?>" style="color:#0E8F6C; text-decoration:none;"><?php echo htmlspecialchars($email); ?></a>
              <?php if (!empty($l['phone'])): ?>
                &bull; <?php echo htmlspecialchars($l['phone']); ?>
              <?php endif; ?>
            </div>
          </td>
          <td>
            <strong style="color:#0F172A; font-size:13px;"><?php echo htmlspecialchars($prod); ?></strong>
            <?php if (!empty($l['category'])): ?>
              <div style="font-size:11px; color:#64748B;"><?php echo htmlspecialchars($l['category']); ?></div>
            <?php endif; ?>
          </td>
          <td>
            <div style="font-size:12.5px; font-weight:600; color:#0F172A;"><?php echo htmlspecialchars($qty); ?></div>
            <?php if (!empty($l['reg']) || !empty($l['spec'])): ?>
              <div style="font-size:11px; color:#64748B;"><?php echo htmlspecialchars($l['reg'] ?: $l['spec']); ?></div>
            <?php endif; ?>
          </td>
          <td>
            <span class="status-pill <?php echo $statusClass; ?>"><?php echo htmlspecialchars($status); ?></span>
          </td>
          <td style="text-align:right; white-space:nowrap;">
            <button type="button" class="btn btn-ghost" style="padding:5px 9px; font-size:11.5px;" onclick="openLeadModal(<?php echo htmlspecialchars(json_encode($l), ENT_QUOTES, 'UTF-8'); ?>)">
              📋 Dossier
            </button>
            <?php if ($email): ?>
              <a class="btn btn-ghost" style="padding:5px 8px; font-size:11.5px; color:#0E8F6C;" href="mailto:<?php echo htmlspecialchars($email); ?>?subject=<?php echo urlencode('[Vasudha Pharma Chem] Inquiry Follow-up (' . $leadId . ')'); ?>" title="Direct Email">
                ✉️ Email
              </a>
            <?php endif; ?>
            <?php if ($phone): ?>
              <a class="btn btn-ghost" style="padding:5px 8px; font-size:11.5px; color:#16A34A;" href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $phone); ?>" target="_blank" rel="noopener" title="Direct WhatsApp Chat">
                💬 WA
              </a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$unifiedLeads): ?>
        <tr><td colspan="7" style="text-align:center; padding:32px; color:#64748B;">No commercial inquiries or client leads recorded yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Lead Detail & Status Update Modal -->
  <div class="vp-lead-modal-backdrop" id="leadModalBackdrop" onclick="if(event.target===this)closeLeadModal();">
    <div class="vp-lead-modal-box">
      <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px; border-bottom:1px solid #E2E8F0; padding-bottom:12px;">
        <div>
          <span id="modalLeadBadge" class="lead-type-badge badge-rfq" style="margin-bottom:6px;"></span>
          <h3 id="modalLeadTitle" style="margin:0; font-size:18px; color:#0F172A;">Lead Details</h3>
          <div id="modalLeadDate" style="font-size:12px; color:#64748B; margin-top:2px;"></div>
        </div>
        <button type="button" onclick="closeLeadModal();" style="background:transparent; border:none; font-size:20px; color:#64748B; cursor:pointer;">&times;</button>
      </div>

      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:16px; font-size:13px; background:#F8FAFC; padding:14px; border-radius:10px; border:1px solid #E2E8F0;">
        <div><strong>Buyer Name:</strong> <span id="modalBuyerName"></span></div>
        <div><strong>Company / Org:</strong> <span id="modalCompany"></span></div>
        <div><strong>Work Email:</strong> <span id="modalEmail"></span></div>
        <div><strong>Direct Phone:</strong> <span id="modalPhone"></span></div>
        <div><strong>Product Requested:</strong> <span id="modalProduct"></span></div>
        <div><strong>Batch Quantity:</strong> <span id="modalQty"></span></div>
      </div>

      <div style="margin-bottom:16px;">
        <label style="font-size:12px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.04em;">Technical Dossier / Campaign Scope</label>
        <div id="modalDescription" style="background:#FFFFFF; border:1px solid #CBD5E1; border-radius:8px; padding:14px; font-size:13px; line-height:1.6; white-space:pre-wrap; max-height:200px; overflow-y:auto; color:#1E293B; font-family:monospace;"></div>
      </div>

      <!-- Status Update Form -->
      <form method="post" action="marketing.php?tab=leads" style="background:#F1F5F9; border-radius:10px; padding:16px; border:1px solid #E2E8F0;">
        <?php echo staff_csrf_field(); ?>
        <input type="hidden" name="action" value="update_lead_status">
        <input type="hidden" name="lead_id" id="formLeadId" value="">
        <input type="hidden" name="source_origin" id="formSourceOrigin" value="">

        <div style="display:grid; grid-template-columns: 1fr 2fr; gap:12px; align-items:end;">
          <div>
            <label style="font-size:12px; font-weight:700; color:#334155;">Pipeline Status</label>
            <select name="status" id="formLeadStatus" style="width:100%; padding:8px 10px; font-size:13px; border-radius:6px; border:1px solid #CBD5E1; background:#FFFFFF;">
              <option value="New">● New (Unread)</option>
              <option value="In Review">⏳ In Review</option>
              <option value="Quoted">💼 Quoted / Commercial Pack Sent</option>
              <option value="Closed">✓ Closed / Completed</option>
            </select>
          </div>
          <div>
            <label style="font-size:12px; font-weight:700; color:#334155;">Internal Ops &amp; Sales Notes</label>
            <input type="text" name="internal_notes" id="formInternalNotes" placeholder="e.g. Sent CDA draft on 21 Oct; assigned to Unit-3 technical team..." style="width:100%; padding:8px 10px; font-size:13px; border-radius:6px; border:1px solid #CBD5E1; background:#FFFFFF;">
          </div>
        </div>

        <div style="margin-top:14px; display:flex; justify-content:flex-end; gap:8px;">
          <button type="button" onclick="closeLeadModal();" class="btn btn-ghost" style="font-size:12.5px;">Close</button>
          <button type="submit" class="btn btn-primary" style="font-size:12.5px; font-weight:700;">Save Status Updates</button>
        </div>
      </form>
    </div>
  </div>

  <script>
  // Filter & Search Logic for Leads
  (function() {
    const searchInput = document.getElementById('leadSearchInput');
    const filterPills = document.querySelectorAll('[data-lead-filter]');
    const table = document.getElementById('leadsTable');
    const visibleCountEl = document.getElementById('visibleLeadsCount');
    if (!table) return;

    const rows = Array.from(table.querySelectorAll('tbody tr'));
    let activeChannel = 'all';

    function applyFilters() {
      const term = (searchInput ? searchInput.value : '').toLowerCase().trim();
      let visible = 0;

      rows.forEach(r => {
        if (r.querySelector('td[colspan]')) return;
        const rChannel = (r.getAttribute('data-channel') || '').trim();
        const channelMatch = (activeChannel === 'all' || rChannel === activeChannel);
        const textMatch = !term || r.textContent.toLowerCase().includes(term);

        if (channelMatch && textMatch) {
          r.style.display = '';
          visible++;
        } else {
          r.style.display = 'none';
        }
      });

      if (visibleCountEl) visibleCountEl.textContent = visible;
    }

    filterPills.forEach(pill => {
      pill.addEventListener('click', function() {
        filterPills.forEach(p => p.classList.remove('is-active'));
        this.classList.add('is-active');
        activeChannel = this.getAttribute('data-lead-filter') || 'all';
        applyFilters();
      });
    });

    if (searchInput) {
      searchInput.addEventListener('input', applyFilters);
    }
  })();

  function openLeadModal(lead) {
    if (!lead) return;
    document.getElementById('modalLeadBadge').textContent = lead.type || 'Commercial Inquiry';
    document.getElementById('modalLeadTitle').textContent = (lead.product || lead.productName || lead.company || 'Inquiry') + ' (' + (lead.id || '') + ')';
    document.getElementById('modalLeadDate').textContent = 'Received on ' + (lead.date || 'Recent');
    document.getElementById('modalBuyerName').textContent = lead.name || 'Not provided';
    document.getElementById('modalCompany').textContent = lead.company || lead.organisation || 'Not provided';
    document.getElementById('modalEmail').textContent = lead.email || 'Not provided';
    document.getElementById('modalPhone').textContent = lead.phone || 'Not provided';
    document.getElementById('modalProduct').textContent = lead.product || lead.productName || 'General Sourcing';
    document.getElementById('modalQty').textContent = lead.quantity || lead.qty || lead.quantityRequired || 'Not specified';
    document.getElementById('modalDescription').textContent = lead.description || lead.message || 'No additional technical notes provided.';

    document.getElementById('formLeadId').value = lead.id || '';
    document.getElementById('formSourceOrigin').value = lead.source_origin || 'enquiries';
    document.getElementById('formLeadStatus').value = lead.status || 'New';
    document.getElementById('formInternalNotes').value = lead.internal_notes || '';

    document.getElementById('leadModalBackdrop').style.display = 'flex';
  }

  function closeLeadModal() {
    document.getElementById('leadModalBackdrop').style.display = 'none';
  }
  </script>

<?php else: ?>
  <!-- ======================================================================= -->
  <!-- TAB 2: PRODUCT PORTFOLIO & CATALOG MANAGER                              -->
  <!-- ======================================================================= -->

  <!-- Category Metrics Overview -->
  <div class="row-4" style="margin-bottom: 22px;">
    <div class="stat-card">
      <div class="stat-label">Total Portfolio</div>
      <div class="stat-num"><?php echo $counts['All']; ?></div>
      <div class="stat-sub">Commercial &amp; Pipeline</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">APIs (Active)</div>
      <div class="stat-num" style="color: #059669;"><?php echo $counts['APIs'] ?? 0; ?></div>
      <div class="stat-sub">Commercial APIs</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Intermediates</div>
      <div class="stat-num" style="color: #2563EB;"><?php echo $counts['Intermediates'] ?? 0; ?></div>
      <div class="stat-sub">Advanced Intermediates</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Pellets &amp; Derivatives</div>
      <div class="stat-num" style="color: #7C3AED;"><?php echo ($counts['Pellets'] ?? 0) + ($counts['Piperidone Derivatives'] ?? 0); ?></div>
      <div class="stat-sub">Pellets &amp; Piperidones</div>
    </div>
  </div>

  <!-- Product Table Card -->
  <div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
      <div>
        <h2 style="margin:0; font-size:18px;">Product Registry &amp; Directory (<span id="visibleCount"><?php echo count($data['items']); ?></span> / <?php echo count($data['items']); ?>)</h2>
        <div style="font-size:12.5px; color:var(--sp-text-muted); margin-top:2px;">
          Filter by category, search by chemical name or CAS #, and click Edit to update any product.
        </div>
      </div>
    </div>

    <!-- Interactive Category Filter Pills -->
    <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 14px;">
      <button type="button" class="cat-pill is-active" data-cat="all">All (<?php echo $counts['All']; ?>)</button>
      <button type="button" class="cat-pill" data-cat="APIs">APIs (<?php echo $counts['APIs'] ?? 0; ?>)</button>
      <button type="button" class="cat-pill" data-cat="Intermediates">Intermediates (<?php echo $counts['Intermediates'] ?? 0; ?>)</button>
      <button type="button" class="cat-pill" data-cat="Pellets">Pellets (<?php echo $counts['Pellets'] ?? 0; ?>)</button>
      <button type="button" class="cat-pill" data-cat="Piperidone Derivatives">Piperidone (<?php echo $counts['Piperidone Derivatives'] ?? 0; ?>)</button>
      <button type="button" class="cat-pill" data-cat="Under Development">Pipeline / Dev (<?php echo $counts['Under Development'] ?? 0; ?>)</button>
    </div>

    <!-- Search Input -->
    <div class="table-search-bar">
      <input type="text" id="productSearchInput" placeholder="Quick search by product name, CAS #, therapeutic use, or specs..." style="width: 100%; max-width: 480px;">
    </div>

    <table id="marketingTable">
      <thead>
        <tr>
          <th>Code / ID</th>
          <th>Product Name</th>
          <th>Category</th>
          <th>CAS #</th>
          <th>Therapeutic Segment</th>
          <th>Specifications</th>
          <th>DMF Status</th>
          <th>Status</th>
          <th style="text-align:right">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($data['items'] as $it): 
        $isActive = ($it['status'] ?? 'active') === 'active';
        $cat = $it['category'] ?? 'APIs';
        $catBadgeClass = 'badge-cat-def';
        if ($cat === 'APIs') $catBadgeClass = 'badge-cat-api';
        elseif ($cat === 'Intermediates') $catBadgeClass = 'badge-cat-int';
        elseif ($cat === 'Pellets') $catBadgeClass = 'badge-cat-pel';
        elseif ($cat === 'Piperidone Derivatives') $catBadgeClass = 'badge-cat-pip';
        elseif ($cat === 'Under Development') $catBadgeClass = 'badge-cat-dev';
      ?>
        <tr data-cat="<?php echo htmlspecialchars($cat); ?>">
          <td><code style="background:#F1F5F9; padding:2px 6px; border-radius:4px; font-size:11.5px;"><?php echo htmlspecialchars($it['id']); ?></code></td>
          <td>
            <strong style="color:var(--sp-text-main); font-size:13.5px;"><?php echo htmlspecialchars($it['name']); ?></strong>
            <?php if (!empty($it['note'])): ?>
              <div style="font-size:11px; color:#64748B; margin-top:2px;">📝 <?php echo htmlspecialchars($it['note']); ?></div>
            <?php endif; ?>
          </td>
          <td><span class="badge-cat <?php echo $catBadgeClass; ?>"><?php echo htmlspecialchars($cat); ?></span></td>
          <td><span style="font-family:monospace; font-size:12px; color:#475569;"><?php echo htmlspecialchars($it['cas_no'] ?? '—'); ?></span></td>
          <td><span style="font-size:12.5px;"><?php echo htmlspecialchars($it['therapeutic'] ?? '—'); ?></span></td>
          <td><span style="font-size:11.5px; color:#475569;"><?php echo htmlspecialchars($it['specifications'] ?? 'IP/USP/BP'); ?></span></td>
          <td><span style="font-size:11.5px; background:#F8FAFC; border:1px solid #E2E8F0; padding:2px 6px; border-radius:4px;"><?php echo htmlspecialchars($it['dmf_status'] ?? 'Available'); ?></span></td>
          <td>
            <span class="<?php echo $isActive ? 'badge-open' : 'badge-closed'; ?>">
              <?php echo $isActive ? '● Active' : '○ Pipeline'; ?>
            </span>
          </td>
          <td style="text-align:right; white-space:nowrap;">
            <a class="btn btn-ghost" style="padding:5px 10px; font-size:12px;" href="marketing.php?tab=catalog&edit=<?php echo urlencode($it['id']); ?>#productForm">Edit</a>
            <form method="post" action="marketing.php?tab=catalog" style="display:inline">
              <?php echo staff_csrf_field(); ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?php echo htmlspecialchars($it['id']); ?>">
              <button class="btn btn-danger" style="padding:5px 9px; font-size:12px;" type="submit" onclick="return confirm('Remove product <?php echo htmlspecialchars($it['name']); ?>?');">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$data['items']): ?>
        <tr><td colspan="9" style="text-align:center; padding:24px; color:#64748B;">No products found in portfolio registry.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Add / Edit Product Form Card -->
  <div class="card" id="productForm" style="<?php echo $edit ? 'border: 2px solid var(--sp-primary); background: #FAFDFB;' : ''; ?>">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:8px;">
      <div>
        <h2 style="margin:0; font-size:18px;">
          <?php if ($edit): ?>
            ✏️ Edit Product: <span style="color:var(--sp-primary-dark);"><?php echo htmlspecialchars($edit['name']); ?></span>
          <?php else: ?>
            ➕ Register New Product / Molecule
          <?php endif; ?>
        </h2>
        <div style="font-size:12.5px; color:var(--sp-text-muted); margin-top:2px;">
          <?php echo $edit ? 'Update specifications, CAS numbers, commercial status, or regulatory filings below.' : 'Add a new API, intermediate, pellet, or pipeline molecule to the marketing catalogue.'; ?>
        </div>
      </div>
      <?php if ($edit): ?>
        <a class="btn btn-ghost" href="marketing.php?tab=catalog" style="font-size:12.5px; color:var(--sp-text-muted);">✖ Cancel Edit</a>
      <?php endif; ?>
    </div>

    <form method="post" action="marketing.php?tab=catalog">
      <?php echo staff_csrf_field(); ?>
      <input type="hidden" name="action" value="save_item">

      <div class="row">
        <div>
          <label>Product Code / ID</label>
          <input name="id" value="<?php echo htmlspecialchars($edit['id'] ?? ''); ?>" placeholder="e.g. PR-API-001 (leave blank to auto-generate)" <?php echo $edit ? 'readonly style="background:#F1F5F9;"' : ''; ?>>
        </div>
        <div>
          <label>Commercial Status</label>
          <select name="status">
            <option value="active" <?php echo (($edit['status'] ?? 'active')==='active')?'selected':''; ?>>Active Commercial Supply</option>
            <option value="pipeline" <?php echo (($edit['status'] ?? '')==='pipeline')?'selected':''; ?>>Under Development / Pipeline</option>
            <option value="evaluation" <?php echo (($edit['status'] ?? '')==='evaluation')?'selected':''; ?>>Feasibility Evaluation</option>
          </select>
        </div>
      </div>

      <label>Product / Chemical Name <span style="color:#DC2626;">*</span></label>
      <input name="name" required value="<?php echo htmlspecialchars($edit['name'] ?? ''); ?>" placeholder="e.g. Atorvastatin Calcium Trihydrate">

      <div class="row-3">
        <div>
          <label>Category</label>
          <select name="category">
            <?php
              $cats = ["APIs", "Intermediates", "Pellets", "Piperidone Derivatives", "Under Development", "Custom Synthesis / CDMO"];
              $curCat = $edit["category"] ?? "APIs";
              foreach ($cats as $c) {
                $sel = ($curCat === $c) ? " selected" : "";
                echo "<option value=\"".htmlspecialchars($c)."\"".$sel.">".htmlspecialchars($c)."</option>";
              }
            ?>
          </select>
        </div>
        <div>
          <label>CAS Number</label>
          <input name="cas_no" value="<?php echo htmlspecialchars($edit['cas_no'] ?? ''); ?>" placeholder="e.g. 134523-03-8">
        </div>
        <div>
          <label>Therapeutic Segment</label>
          <input name="therapeutic" value="<?php echo htmlspecialchars($edit['therapeutic'] ?? ''); ?>" placeholder="e.g. Cardiovascular / Statin">
        </div>
      </div>

      <div class="row">
        <div>
          <label>Pharmacopeial Specifications</label>
          <input name="specifications" value="<?php echo htmlspecialchars($edit['specifications'] ?? 'IP / USP / BP / Ph.Eur / In-House'); ?>" placeholder="e.g. IP/USP/BP/Ph.Eur/JP">
        </div>
        <div>
          <label>DMF / Regulatory Filing Status</label>
          <input name="dmf_status" value="<?php echo htmlspecialchars($edit['dmf_status'] ?? 'Available'); ?>" placeholder="e.g. USDMF, CEP, KDMF, EU-GMP">
        </div>
      </div>

      <label>Internal Marketing &amp; Client Note</label>
      <input name="note" value="<?php echo htmlspecialchars($edit['note'] ?? ''); ?>" placeholder="e.g. Commercial batch availability, target markets, scale...">

      <p style="margin-top:22px;">
        <button class="btn btn-primary" type="submit" style="padding:10px 22px; font-weight:700;">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
          <?php echo $edit ? 'Save Product Updates' : 'Register Product'; ?>
        </button>
        <?php if ($edit): ?>
          <a class="btn btn-ghost" href="marketing.php?tab=catalog" style="margin-left:8px;">Cancel</a>
        <?php endif; ?>
      </p>
    </form>
  </div>

  <script>
  // Live Product Filter & Search
  (function() {
    const searchInput = document.getElementById('productSearchInput');
    const catPills = document.querySelectorAll('.cat-pill');
    const table = document.getElementById('marketingTable');
    const visibleCountEl = document.getElementById('visibleCount');
    if (!table) return;

    const rows = Array.from(table.querySelectorAll('tbody tr'));
    let activeCat = 'all';

    function applyFilters() {
      const term = (searchInput ? searchInput.value : '').toLowerCase().trim();
      let visible = 0;

      rows.forEach(r => {
        if (r.querySelector('td[colspan]')) return;
        const rowCat = (r.getAttribute('data-cat') || '').trim();
        const catMatch = (activeCat === 'all' || rowCat === activeCat);
        const textMatch = !term || r.textContent.toLowerCase().includes(term);

        if (catMatch && textMatch) {
          r.style.display = '';
          visible++;
        } else {
          r.style.display = 'none';
        }
      });

      if (visibleCountEl) visibleCountEl.textContent = visible;

      let noResults = table.querySelector('.search-no-results');
      if (visible === 0 && rows.length > 0) {
        if (!noResults) {
          noResults = document.createElement('tr');
          noResults.className = 'search-no-results';
          noResults.innerHTML = '<td colspan="9" style="text-align:center;padding:24px;color:#94A3B8;">No products matching your search or category filter.</td>';
          table.querySelector('tbody').appendChild(noResults);
        }
      } else if (noResults) {
        noResults.remove();
      }
    }

    catPills.forEach(pill => {
      pill.addEventListener('click', function() {
        catPills.forEach(p => p.classList.remove('is-active'));
        this.classList.add('is-active');
        activeCat = this.getAttribute('data-cat') || 'all';
        applyFilters();
      });
    });

    if (searchInput) {
      searchInput.addEventListener('input', applyFilters);
    }
  })();
  </script>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
