<?php
require __DIR__ . '/includes/auth.php';
staff_require_login();
$u = staff_user();
if (!staff_can_candidates($u)) {
  http_response_code(403);
  echo 'Access denied: You must be an HR Officer or Administrator to access the Candidate Desk.';
  exit;
}

$dataFile = staff_applications_file();
$apps = staff_load_applications();
$msg = '';
$err = '';

// Helper for stage metadata
function get_stage_meta($step) {
  $step = intval($step);
  switch ($step) {
    case 1:
      return ['num' => 1, 'title' => 'Profile Screening', 'badge' => 'badge-warn', 'class' => 'in-progress'];
    case 2:
      return ['num' => 2, 'title' => 'Technical Assessment', 'badge' => 'badge-info', 'class' => 'in-progress'];
    case 3:
      return ['num' => 3, 'title' => 'Panel Interview Scheduled', 'badge' => 'badge-purple', 'class' => 'in-progress'];
    case 4:
      return ['num' => 4, 'title' => 'Formal Offer Issued', 'badge' => 'badge-open', 'class' => 'passed'];
    case -1:
      return ['num' => -1, 'title' => 'Archived / Closed', 'badge' => 'badge-closed', 'class' => 'rejected'];
    default:
      return ['num' => 1, 'title' => 'Profile Screening', 'badge' => 'badge-warn', 'class' => 'in-progress'];
  }
}

// 1. CSV Export Handler
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=vasudha_candidates_' . date('Y-m-d') . '.csv');
  $out = fopen('php://output', 'w');
  fputcsv($out, [
    'Reference ID', 'Date Applied', 'Full Name', 'Email', 'Phone',
    'Position', 'Qualification', 'Experience (Yrs)', 'Notice Period',
    'Current City', 'Preferred Plant', 'Pipeline Step', 'Status Label',
    'Reviewer Remarks', 'Last Updated', 'Resume File Path'
  ]);
  foreach ($apps as $a) {
    fputcsv($out, [
      $a['id'] ?? '',
      $a['date'] ?? '',
      $a['name'] ?? '',
      $a['email'] ?? '',
      $a['phone'] ?? '',
      $a['job_title'] ?? '',
      $a['degree'] ?? '',
      $a['experience'] ?? '',
      $a['notice'] ?? '',
      $a['location'] ?? '',
      $a['preferred_plant'] ?? '',
      $a['currentStep'] ?? 1,
      $a['statusLabel'] ?? '',
      $a['reviewerNote'] ?? '',
      $a['updated_at'] ?? '',
      $a['cv_path'] ?? ''
    ]);
  }
  fclose($out);
  exit;
}

// 2. Handle POST Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  staff_verify_csrf();
  $action = $_POST['action'] ?? '';

  if ($action === 'update_status') {
    $targetId = trim($_POST['candidate_id'] ?? '');
    $newStep  = intval($_POST['step'] ?? 1);
    $customLabel = trim($_POST['status_label'] ?? '');
    $reviewerNote = trim($_POST['reviewer_note'] ?? '');
    $notifyCandidate = !empty($_POST['notify_candidate']);

    $meta = get_stage_meta($newStep);
    $statusLabel = $customLabel ?: $meta['title'];
    $statusClass = $meta['class'];

    $found = false;
    $updatedCand = null;

    foreach ($apps as $idx => $cand) {
      if (strtoupper($cand['id']) === strtoupper($targetId)) {
        $apps[$idx]['currentStep']  = $newStep;
        $apps[$idx]['statusLabel']  = $statusLabel;
        $apps[$idx]['statusClass']  = $statusClass;
        $apps[$idx]['reviewerNote'] = $reviewerNote;
        $apps[$idx]['updated_at']   = date('Y-m-d H:i:s');
        $apps[$idx]['updated_by']   = $u['name'] ?? ($u['email'] ?? 'HR Officer');
        if ($newStep === -1) {
          $apps[$idx]['archived'] = true;
        } else {
          $apps[$idx]['archived'] = false;
        }
        $updatedCand = $apps[$idx];
        $found = true;
        break;
      }
    }

    if ($found) {
      staff_save_applications($apps);
      $msg = "Application status for {$targetId} updated to \"{$statusLabel}\".";

      // Optional Candidate Notification Email
      if ($notifyCandidate && !empty($updatedCand['email'])) {
        $toEmail = $updatedCand['email'];
        $subject = "[Vasudha Pharma] Application Status Update: {$targetId} — {$statusLabel}";
        $serverHost = $_SERVER['SERVER_NAME'] ?? ($_SERVER['HTTP_HOST'] ?? 'vasudhapharma.com');
        $mailDomain = (strpos($serverHost, 'mytemp.website') !== false || empty($serverHost)) ? 'vasudhapharma.com' : $serverHost;

        $candBody = '<!DOCTYPE html><html><body style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif; background:#f4fbf7; margin:0; padding:24px; color:#0f172a;">
          <div style="max-width:560px; margin:0 auto; background:#ffffff; border-radius:14px; border:1px solid #e2e8f0; padding:28px 24px; box-shadow:0 4px 18px rgba(0,0,0,0.06);">
            <div style="border-bottom:2px solid #0E8F6C; padding-bottom:14px; margin-bottom:18px;">
              <h2 style="margin:0; font-size:20px; color:#096B51;">Vasudha Pharma Chem Limited</h2>
              <div style="font-size:12px; color:#0E8F6C; margin-top:2px;">Recruitment &amp; Talent Acquisition Desk</div>
            </div>
            <p>Dear <strong>' . htmlspecialchars($updatedCand['name']) . '</strong>,</p>
            <p>Your application for <strong>' . htmlspecialchars($updatedCand['job_title']) . '</strong> (Reference ID: <code>' . htmlspecialchars($targetId) . '</code>) has an updated status:</p>
            <div style="background:#ECFDF5; border-left:4px solid #0E8F6C; padding:14px; border-radius:6px; margin:16px 0;">
              <div style="font-size:13px; font-weight:700; color:#065F46; text-transform:uppercase;">Current Hiring Stage</div>
              <div style="font-size:18px; font-weight:800; color:#096B51; margin-top:4px;">' . htmlspecialchars($statusLabel) . '</div>
              ' . ($reviewerNote ? '<div style="margin-top:8px; font-size:13.5px; color:#1E293B; line-height:1.5;"><em>"' . htmlspecialchars($reviewerNote) . '"</em></div>' : '') . '
            </div>
            <p style="font-size:13.5px; line-height:1.6; color:#475569;">You can track real-time progress of your application credentials and review committee updates at any time on our Careers Portal.</p>
            <div style="text-align:center; margin:24px 0;">
              <a href="https://' . htmlspecialchars($serverHost) . '/apply.html" style="background:#0E8F6C; color:#ffffff; padding:12px 24px; text-decoration:none; border-radius:8px; font-weight:700; font-size:14px; display:inline-block;">Track Application Status &rarr;</a>
            </div>
            <div style="border-top:1px solid #e2e8f0; padding-top:16px; font-size:12px; color:#94a3b8; text-align:center;">
              Vasudha Pharma Chem Limited &bull; Hyderabad &amp; Visakhapatnam, India
            </div>
          </div>
        </body></html>';

        $hdrs = [
          'MIME-Version: 1.0',
          'Content-Type: text/html; charset=UTF-8',
          'From: Vasudha HR Desk <noreply@' . $mailDomain . '>',
          'Reply-To: wisdom@vasudhapharma.com',
          'Auto-Submitted: auto-generated',
          'X-Mailer: VasudhaDesk/1.0'
        ];
        @mail($toEmail, $subject, $candBody, implode("\r\n", $hdrs));
        $msg .= " Email notification dispatched to candidate ({$toEmail}).";
      }
    } else {
      $err = "Application ID {$targetId} not found.";
    }
  }

  if ($action === 'delete_candidate') {
    $targetId = trim($_POST['candidate_id'] ?? '');
    $beforeCount = count($apps);
    $apps = array_values(array_filter($apps, function($c) use ($targetId) {
      return strtoupper($c['id'] ?? '') !== strtoupper($targetId);
    }));
    if (count($apps) < $beforeCount) {
      staff_save_applications($apps);
      $msg = "Application {$targetId} permanently removed.";
    }
  }
}

// Stage counts calculation
$stageCounts = [
  'all'       => count($apps),
  'step1'     => 0,
  'step2'     => 0,
  'step3'     => 0,
  'step4'     => 0,
  'archived'  => 0,
];

foreach ($apps as $a) {
  $s = intval($a['currentStep'] ?? 1);
  if (!empty($a['archived']) || $s === -1) {
    $stageCounts['archived']++;
  } elseif ($s === 1) {
    $stageCounts['step1']++;
  } elseif ($s === 2) {
    $stageCounts['step2']++;
  } elseif ($s === 3) {
    $stageCounts['step3']++;
  } elseif ($s === 4) {
    $stageCounts['step4']++;
  }
}

$staff_title = 'Candidate Desk';
require __DIR__ . '/includes/header.php';
?>

<style>
  .badge-warn { background: #FEF3C7; color: #D97706; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 11.5px; display: inline-flex; align-items: center; gap: 4px; }
  .badge-info { background: #E0F2FE; color: #0284C7; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 11.5px; display: inline-flex; align-items: center; gap: 4px; }
  .badge-purple { background: #F3E8FF; color: #7E22CE; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 11.5px; display: inline-flex; align-items: center; gap: 4px; }
  .badge-closed { background: #F1F5F9; color: #64748B; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 11.5px; display: inline-flex; align-items: center; gap: 4px; }
  
  .cand-card {
    background: #FFFFFF;
    border: 1px solid var(--sp-border);
    border-radius: 14px;
    padding: 22px 24px;
    margin-bottom: 18px;
    transition: all 0.2s ease;
    box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
  }
  .cand-card:hover {
    border-color: #0E8F6C;
    box-shadow: 0 6px 20px rgba(14, 143, 108, 0.08);
  }
  .cand-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 14px;
    border-bottom: 1px solid #F1F5F9;
    padding-bottom: 12px;
  }
  .cand-id-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #F1F5F9;
    border: 1px solid #CBD5E1;
    color: #0F172A;
    font-size: 13px;
    font-weight: 800;
    font-family: monospace;
    padding: 4px 10px;
    border-radius: 6px;
    letter-spacing: 0.5px;
  }
  .cand-copy-btn {
    background: none;
    border: none;
    color: #64748B;
    cursor: pointer;
    padding: 0;
    display: inline-flex;
    align-items: center;
  }
  .cand-copy-btn:hover { color: #0E8F6C; }
  
  .filter-tabs {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 20px;
  }
  .filter-tab {
    background: #FFFFFF;
    border: 1px solid var(--sp-border);
    color: #475569;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }
  .filter-tab:hover {
    border-color: #0E8F6C;
    color: #0E8F6C;
  }
  .filter-tab.active {
    background: #0E8F6C;
    border-color: #0E8F6C;
    color: #FFFFFF;
  }
  
  .preset-chip {
    background: #F8FAFC;
    border: 1px dashed #CBD5E1;
    border-radius: 6px;
    padding: 4px 10px;
    font-size: 11.5px;
    color: #334155;
    cursor: pointer;
    transition: all 0.15s;
    display: inline-block;
    margin: 3px 2px;
  }
  .preset-chip:hover {
    background: #ECFDF5;
    border-color: #0E8F6C;
    color: #065F46;
  }
</style>

<?php if ($msg): ?>
  <div class="card ok" style="margin-bottom: 20px;">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
    <span><?php echo htmlspecialchars($msg); ?></span>
  </div>
<?php endif; ?>

<?php if ($err): ?>
  <div class="card err" style="margin-bottom: 20px;">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <span><?php echo htmlspecialchars($err); ?></span>
  </div>
<?php endif; ?>

<!-- Top Hub Hero -->
<div class="card" style="background: linear-gradient(135deg, #096B51 0%, #0E8F6C 60%, #1DB88A 100%); color:#FFFFFF; border:0; padding:24px 28px; box-shadow:0 8px 24px rgba(14,143,108,0.18);">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
    <div>
      <div style="text-transform:uppercase; font-size:11px; letter-spacing:0.12em; opacity:0.85; font-weight:700; margin-bottom:4px;">
        Vasudha Pharma Chem &bull; Talent Acquisition ATS Console
      </div>
      <h1 style="margin:0; font-size:24px; color:#FFFFFF;">Applicant Pipeline &amp; Status Management</h1>
      <p style="margin:6px 0 0; opacity:0.9; font-size:13.5px; max-width:650px;">
        Review job applications, download candidate CVs, and advance candidates through the 4-stage hiring pipeline. Status updates sync in real time with the candidate's tracking link.
      </p>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
      <a href="candidates.php?export=csv" class="btn" style="background:#FFFFFF; color:#096B51; font-weight:700; box-shadow:0 2px 8px rgba(0,0,0,0.12);">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Export Candidates (CSV)
      </a>
      <a href="../apply.html" target="_blank" rel="noopener" class="btn" style="background:rgba(255,255,255,0.2); color:#FFFFFF; border:1px solid rgba(255,255,255,0.3); font-weight:600;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/></svg>
        Public Tracker Portal
      </a>
    </div>
  </div>
</div>

<!-- KPI Metrics Strip -->
<div class="row-4" style="margin-bottom:22px;">
  <div class="stat-card">
    <div class="stat-label">Total Applicants</div>
    <div class="stat-num"><?php echo $stageCounts['all']; ?></div>
    <div class="stat-sub">Across all requisitions</div>
  </div>
  <div class="stat-card" style="border-left:4px solid #D97706;">
    <div class="stat-label">Step 1: Screening</div>
    <div class="stat-num" style="color:#D97706;"><?php echo $stageCounts['step1']; ?></div>
    <div class="stat-sub">Initial CV review</div>
  </div>
  <div class="stat-card" style="border-left:4px solid #0284C7;">
    <div class="stat-label">Step 2: Technical Evaluation</div>
    <div class="stat-num" style="color:#0284C7;"><?php echo $stageCounts['step2']; ?></div>
    <div class="stat-sub">Written / chemistry tests</div>
  </div>
  <div class="stat-card" style="border-left:4px solid #7E22CE;">
    <div class="stat-label">Step 3: Panel Interview</div>
    <div class="stat-num" style="color:#7E22CE;"><?php echo $stageCounts['step3']; ?></div>
    <div class="stat-sub">Technical leadership round</div>
  </div>
</div>

<!-- Search & Stage Filters -->
<div class="card" style="padding:18px 24px;">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
    <!-- Live Search Input -->
    <div style="flex:1; min-width:260px; max-width:440px; position:relative;">
      <input type="text" id="liveSearchInput" placeholder="Quick search candidate name, ID (e.g. VP-2026), role, or email..." style="padding-left:36px;">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2.2" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); pointer-events:none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    </div>

    <!-- Filter Buttons -->
    <div class="filter-tabs" style="margin-bottom:0;">
      <button type="button" class="filter-tab active" data-filter="all">All (<?php echo $stageCounts['all']; ?>)</button>
      <button type="button" class="filter-tab" data-filter="step-1">1: Screening (<?php echo $stageCounts['step1']; ?>)</button>
      <button type="button" class="filter-tab" data-filter="step-2">2: Technical (<?php echo $stageCounts['step2']; ?>)</button>
      <button type="button" class="filter-tab" data-filter="step-3">3: Interview (<?php echo $stageCounts['step3']; ?>)</button>
      <button type="button" class="filter-tab" data-filter="step-4">4: Offer Issued (<?php echo $stageCounts['step4']; ?>)</button>
      <button type="button" class="filter-tab" data-filter="step-archived">Archived (<?php echo $stageCounts['archived']; ?>)</button>
    </div>
  </div>
</div>

<!-- Candidate Cards Stream -->
<div id="candidatesStream">
  <?php if (empty($apps)): ?>
    <div class="card" style="text-align:center; padding:48px 20px; color:#64748B;">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="1.5" style="margin-bottom:12px;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      <h3 style="margin:0 0 8px; color:#0F172A;">No Candidate Applications Yet</h3>
      <p style="margin:0 auto; max-width:440px; font-size:14px;">When candidates submit applications on <code>apply.html</code>, their records and uploaded CVs will appear here automatically for HR review.</p>
    </div>
  <?php else: ?>
    <?php foreach ($apps as $cand): 
      $candId = $cand['id'] ?? 'VP-2026-0000';
      $stepNum = intval($cand['currentStep'] ?? 1);
      $isArchived = !empty($cand['archived']) || $stepNum === -1;
      $stageFilterClass = $isArchived ? 'step-archived' : 'step-' . $stepNum;
      $meta = get_stage_meta($isArchived ? -1 : $stepNum);
    ?>
      <div class="cand-card" data-stage="<?php echo $stageFilterClass; ?>" data-search="<?php echo htmlspecialchars(strtolower(($cand['id'] ?? '') . ' ' . ($cand['name'] ?? '') . ' ' . ($cand['email'] ?? '') . ' ' . ($cand['job_title'] ?? '') . ' ' . ($cand['phone'] ?? ''))); ?>">
        <div class="cand-header">
          <div>
            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:6px;">
              <span class="cand-id-badge">
                <?php echo htmlspecialchars($candId); ?>
                <button type="button" class="cand-copy-btn" title="Copy Reference ID" onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars($candId); ?>'); this.style.color='#0E8F6C';">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                </button>
              </span>
              <span class="<?php echo $meta['badge']; ?>">
                ● Stage <?php echo $meta['num'] > 0 ? $meta['num'] . ' of 4: ' : ''; ?><?php echo htmlspecialchars($cand['statusLabel'] ?? $meta['title']); ?>
              </span>
              <span style="font-size:12px; color:#64748B;">Applied: <strong><?php echo htmlspecialchars($cand['date'] ?? 'Recent'); ?></strong></span>
            </div>
            <h3 style="margin:0 0 4px; font-size:18px; color:#0F172A;">
              <?php echo htmlspecialchars($cand['name'] ?? 'Candidate'); ?>
            </h3>
            <div style="font-size:14px; font-weight:600; color:#096B51;">
              Position: <?php echo htmlspecialchars($cand['job_title'] ?? 'Pharmaceutical Candidate'); ?>
            </div>
          </div>

          <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <?php if (!empty($cand['cv_path'])): ?>
              <a href="../<?php echo htmlspecialchars($cand['cv_path']); ?>" target="_blank" class="btn btn-primary" style="padding:7px 14px; font-size:12.5px; background:#0E8F6C;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="12" y2="18"/><line x1="15" y1="15" x2="12" y2="18"/></svg>
                Download Resume (CV)
              </a>
            <?php else: ?>
              <span style="font-size:12px; color:#94A3B8; padding:6px 10px; background:#F8FAFC; border-radius:6px;">No CV file uploaded</span>
            <?php endif; ?>

            <?php if (!empty($cand['email'])): ?>
              <a href="mailto:<?php echo htmlspecialchars($cand['email']); ?>?subject=Vasudha%20Pharma%20Application%20Update%20(<?php echo htmlspecialchars($candId); ?>)" class="btn btn-ghost" style="padding:7px 12px; font-size:12.5px;" title="Send Direct Email">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                Email
              </a>
            <?php endif; ?>

            <?php if (!empty($cand['phone'])): ?>
              <a href="tel:<?php echo htmlspecialchars($cand['phone']); ?>" class="btn btn-ghost" style="padding:7px 12px; font-size:12.5px;" title="Call Candidate">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                Call
              </a>
            <?php endif; ?>
          </div>
        </div>

        <!-- Candidate Profile Grid -->
        <div class="row-4" style="background:#F8FAFC; border-radius:10px; padding:12px 16px; margin-bottom:16px; font-size:13px;">
          <div>
            <div style="color:#64748B; font-size:11.5px; font-weight:600;">EMAIL &amp; MOBILE</div>
            <div style="font-weight:600; color:#0F172A; margin-top:2px; word-break:break-all;">
              <?php echo htmlspecialchars($cand['email'] ?? 'Not given'); ?><br>
              <span style="color:#475569; font-weight:500;"><?php echo htmlspecialchars($cand['phone'] ?? 'No phone'); ?></span>
            </div>
          </div>
          <div>
            <div style="color:#64748B; font-size:11.5px; font-weight:600;">QUALIFICATION &amp; EXP</div>
            <div style="font-weight:600; color:#0F172A; margin-top:2px;">
              <?php echo htmlspecialchars($cand['degree'] ?: 'Degree provided in CV'); ?><br>
              <span style="color:#475569; font-weight:500;"><?php echo !empty($cand['experience']) ? htmlspecialchars($cand['experience']) . ' Yrs Experience' : 'Experience in CV'; ?></span>
            </div>
          </div>
          <div>
            <div style="color:#64748B; font-size:11.5px; font-weight:600;">LOCATION &amp; PREFERRED PLANT</div>
            <div style="font-weight:600; color:#0F172A; margin-top:2px;">
              <?php echo htmlspecialchars($cand['location'] ?: 'India'); ?><br>
              <span style="color:#0E8F6C; font-weight:600;"><?php echo !empty($cand['preferred_plant']) ? ucfirst(htmlspecialchars($cand['preferred_plant'])) : 'Open Location'; ?></span>
            </div>
          </div>
          <div>
            <div style="color:#64748B; font-size:11.5px; font-weight:600;">NOTICE PERIOD</div>
            <div style="font-weight:600; color:#0F172A; margin-top:2px;">
              <?php echo htmlspecialchars(ucfirst($cand['notice'] ?: '30 Days')); ?><br>
              <span style="color:#94A3B8; font-size:11.5px;">Last active: <?php echo htmlspecialchars($cand['updated_at'] ? date('d M Y', strtotime($cand['updated_at'])) : ($cand['date'] ?? '')); ?></span>
            </div>
          </div>
        </div>

        <?php if (!empty($cand['note'])): ?>
          <div style="background:#FFFFFF; border:1px solid #E2E8F0; border-radius:8px; padding:10px 14px; margin-bottom:16px; font-size:13px; color:#334155;">
            <strong style="color:#0F172A; font-size:12px; text-transform:uppercase; letter-spacing:0.04em;">Applicant Summary / Cover Highlights:</strong>
            <p style="margin:4px 0 0; line-height:1.5; font-style:italic;"><?php echo nl2br(htmlspecialchars($cand['note'])); ?></p>
          </div>
        <?php endif; ?>

        <!-- HR Pipeline Stage Updater Form -->
        <div style="background:#F0FDF4; border:1px solid #BBF7D0; border-radius:10px; padding:16px 18px;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
            <div style="font-size:13.5px; font-weight:700; color:#065F46; display:flex; align-items:center; gap:6px;">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
              Update Candidate Hiring Stage &amp; Public Remarks
            </div>
            <?php if (!empty($cand['updated_by'])): ?>
              <span style="font-size:11.5px; color:#047857;">Updated by <em><?php echo htmlspecialchars($cand['updated_by']); ?></em></span>
            <?php endif; ?>
          </div>

          <form method="post" id="form-<?php echo htmlspecialchars($candId); ?>">
            <?php echo staff_csrf_field(); ?>
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="candidate_id" value="<?php echo htmlspecialchars($candId); ?>">

            <div class="row" style="margin-bottom:10px;">
              <div>
                <label style="margin-top:0; font-size:12px; color:#065F46;">Hiring Pipeline Step *</label>
                <select name="step" id="step-<?php echo htmlspecialchars($candId); ?>" onchange="syncStepPreset('<?php echo htmlspecialchars($candId); ?>')">
                  <option value="1" <?php echo ($stepNum === 1 && !$isArchived) ? 'selected' : ''; ?>>Step 1: Profile Screening (Initial Review)</option>
                  <option value="2" <?php echo ($stepNum === 2 && !$isArchived) ? 'selected' : ''; ?>>Step 2: Technical Assessment (Written / Evaluation)</option>
                  <option value="3" <?php echo ($stepNum === 3 && !$isArchived) ? 'selected' : ''; ?>>Step 3: Panel Interview Scheduled</option>
                  <option value="4" <?php echo ($stepNum === 4 && !$isArchived) ? 'selected' : ''; ?>>Step 4: Formal Offer Issued &amp; Onboarding</option>
                  <option value="-1" <?php echo $isArchived ? 'selected' : ''; ?>>Archived / Position Closed / On Reserve File</option>
                </select>
              </div>

              <div>
                <label style="margin-top:0; font-size:12px; color:#065F46;">Custom Status Headline (Optional)</label>
                <input type="text" name="status_label" id="label-<?php echo htmlspecialchars($candId); ?>" value="<?php echo htmlspecialchars($cand['statusLabel'] ?? ''); ?>" placeholder="e.g. Technical Assessment in Progress, Offer Released...">
              </div>
            </div>

            <!-- Quick Remark Presets for 1-Click Convenience -->
            <div style="margin-bottom:10px;">
              <span style="font-size:11.5px; font-weight:700; color:#065F46; margin-right:6px;">1-Click Remark Presets:</span>
              <button type="button" class="preset-chip" onclick="applyPreset('<?php echo htmlspecialchars($candId); ?>', 2, 'Technical Evaluation In Progress', 'Profile approved by Talent Acquisition. Technical evaluation case study is currently under technical review by Principal Scientist.')">
                🧪 Shortlist for Tech Round
              </button>
              <button type="button" class="preset-chip" onclick="applyPreset('<?php echo htmlspecialchars($candId); ?>', 3, 'Panel Interview Scheduled', 'Technical assessment passed with commendation. Panel interview scheduled with Senior Technical Committee & HR.')">
                📅 Schedule Panel Interview
              </button>
              <button type="button" class="preset-chip" onclick="applyPreset('<?php echo htmlspecialchars($candId); ?>', 4, 'Formal Offer Issued', 'All technical and executive rounds cleared. Formal appointment letter dispatched to registered email. Induction scheduled.')">
                🎉 Issue Formal Offer
              </button>
              <button type="button" class="preset-chip" onclick="applyPreset('<?php echo htmlspecialchars($candId); ?>', -1, 'Profile Kept on Reserve File', 'Thank you for participating. While other candidates matched current openings more closely, your credentials remain on reserve for upcoming plant expansion roles.')">
                📁 Reserve for Future
              </button>
            </div>

            <div>
              <label style="margin-top:0; font-size:12px; color:#065F46;">Reviewer Remarks &amp; Feedback (Visible to Candidate on Live Tracker)</label>
              <textarea name="reviewer_note" id="note-<?php echo htmlspecialchars($candId); ?>" rows="2" style="min-height:64px; background:#FFFFFF;" placeholder="Type specific notes, interview dates, assessment scores, or instructions..."><?php echo htmlspecialchars($cand['reviewerNote'] ?? ''); ?></textarea>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-top:12px;">
              <label style="margin:0; font-weight:500; font-size:12.5px; color:#065F46; display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                <input type="checkbox" name="notify_candidate" value="1" style="width:auto; margin:0;" checked>
                Dispatch instant email alert to candidate (<code><?php echo htmlspecialchars($cand['email'] ?? ''); ?></code>)
              </label>

              <div style="display:flex; gap:8px;">
                <button type="submit" class="btn btn-primary" style="background:#0E8F6C; font-size:13px;">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                  Save Status Update
                </button>
              </div>
            </div>
          </form>
        </div>

        <div style="display:flex; justify-content:flex-end; margin-top:10px;">
          <form method="post" onsubmit="return confirm('Are you sure you want to permanently remove candidate application <?php echo htmlspecialchars($candId); ?>?');" style="margin:0;">
            <?php echo staff_csrf_field(); ?>
            <input type="hidden" name="action" value="delete_candidate">
            <input type="hidden" name="candidate_id" value="<?php echo htmlspecialchars($candId); ?>">
            <button type="submit" style="background:none; border:none; color:#EF4444; font-size:11.5px; cursor:pointer; padding:4px 8px;">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
              Delete record
            </button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<script>
// 1. One-Click Quick Presets for HR
function applyPreset(candId, step, label, note) {
  const stepSelect = document.getElementById('step-' + candId);
  const labelInput = document.getElementById('label-' + candId);
  const noteTextarea = document.getElementById('note-' + candId);

  if (stepSelect) stepSelect.value = step;
  if (labelInput) labelInput.value = label;
  if (noteTextarea) noteTextarea.value = note;
}

function syncStepPreset(candId) {
  const stepSelect = document.getElementById('step-' + candId);
  const labelInput = document.getElementById('label-' + candId);
  if (!stepSelect || !labelInput) return;

  const step = parseInt(stepSelect.value, 10);
  switch (step) {
    case 1: labelInput.value = 'Profile Screening'; break;
    case 2: labelInput.value = 'Technical Assessment'; break;
    case 3: labelInput.value = 'Panel Interview Scheduled'; break;
    case 4: labelInput.value = 'Formal Offer Issued'; break;
    case -1: labelInput.value = 'Position Closed / Reserve List'; break;
  }
}

// 2. Client-side Live Search & Filter
document.addEventListener('DOMContentLoaded', function () {
  const searchInput = document.getElementById('liveSearchInput');
  const filterTabs = document.querySelectorAll('.filter-tab');
  const cards = document.querySelectorAll('.cand-card');

  let activeFilter = 'all';
  let searchQuery = '';

  function applyFilters() {
    cards.forEach(card => {
      const cardStage = card.getAttribute('data-stage');
      const cardText = card.getAttribute('data-search') || '';

      const matchesFilter = (activeFilter === 'all') || (cardStage === activeFilter);
      const matchesSearch = !searchQuery || cardText.includes(searchQuery);

      if (matchesFilter && matchesSearch) {
        card.style.display = '';
      } else {
        card.style.display = 'none';
      }
    });
  }

  if (searchInput) {
    searchInput.addEventListener('input', function () {
      searchQuery = this.value.trim().toLowerCase();
      applyFilters();
    });
  }

  filterTabs.forEach(tab => {
    tab.addEventListener('click', function () {
      filterTabs.forEach(t => t.classList.remove('active'));
      this.classList.add('active');
      activeFilter = this.getAttribute('data-filter');
      applyFilters();
    });
  });
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
