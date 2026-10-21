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
$talentPool = staff_load_talent_pool();
$jobsData = staff_load_jobs();
$allJobs = $jobsData['jobs'] ?? [];
$currentTab = ($_GET['tab'] ?? 'active') === 'talent' ? 'talent' : 'active';
$msg = '';
$err = '';

// Helper for stage metadata
function get_stage_meta($step) {
  $step = intval($step);
  switch ($step) {
    case 1:
      return ['num' => 1, 'title' => 'Profile Screening', 'badge' => 'badge-warn', 'class' => 'in-progress', 'color' => '#D97706'];
    case 2:
      return ['num' => 2, 'title' => 'Technical Assessment', 'badge' => 'badge-info', 'class' => 'in-progress', 'color' => '#0284C7'];
    case 3:
      return ['num' => 3, 'title' => 'Panel Interview Scheduled', 'badge' => 'badge-purple', 'class' => 'in-progress', 'color' => '#7E22CE'];
    case 4:
      return ['num' => 4, 'title' => 'Formal Offer Issued', 'badge' => 'badge-open', 'class' => 'passed', 'color' => '#059669'];
    case -1:
      return ['num' => -1, 'title' => 'Archived / Position Closed', 'badge' => 'badge-closed', 'class' => 'rejected', 'color' => '#64748B'];
    default:
      return ['num' => 1, 'title' => 'Profile Screening', 'badge' => 'badge-warn', 'class' => 'in-progress', 'color' => '#D97706'];
  }
}

// 1. CSV Export Handlers
if (isset($_GET['export']) && $_GET['export'] === 'talent_csv') {
  staff_log_audit('talent_export_csv', 'Exported talent network candidate pool to CSV (' . count($talentPool) . ' profiles)', 'talent_pool.csv', 'Talent Community');
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=vasudha_talent_network_' . date('Y-m-d') . '.csv');
  $out = fopen('php://output', 'w');
  fputcsv($out, [
    'Member ID', 'Registered Date', 'Full Name', 'Email', 'Phone',
    'Primary Domain', 'Experience Level', 'Preferred Location', 'Status',
    'Summary / Credentials', 'Internal HR Notes'
  ]);
  foreach ($talentPool as $t) {
    fputcsv($out, [
      $t['id'] ?? '',
      $t['date'] ?? '',
      $t['name'] ?? '',
      $t['email'] ?? '',
      $t['phone'] ?? '',
      $t['domain'] ?? '',
      $t['experience'] ?? '',
      $t['location'] ?? '',
      $t['status'] ?? 'Available',
      $t['summary'] ?? '',
      $t['notes'] ?? ''
    ]);
  }
  fclose($out);
  exit;
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  staff_log_audit('candidate_export_csv', 'Exported active job candidate applications to CSV (' . count($apps) . ' applications)', 'candidates.csv', 'Candidate ATS');
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=vasudha_candidates_' . date('Y-m-d') . '.csv');
  $out = fopen('php://output', 'w');
  fputcsv($out, [
    'Reference ID', 'Date Applied', 'Full Name', 'Email', 'Phone',
    'Position', 'Job Code', 'Qualification', 'Experience (Yrs)', 'Notice Period',
    'Current City', 'Preferred Plant', 'Pipeline Step', 'Status Label',
    'Reviewer Remarks (Public)', 'Private HR Notes', 'Star Rating', 'Last Updated', 'Resume File Path'
  ]);
  foreach ($apps as $a) {
    fputcsv($out, [
      $a['id'] ?? '',
      $a['date'] ?? '',
      $a['name'] ?? '',
      $a['email'] ?? '',
      $a['phone'] ?? '',
      $a['job_title'] ?? '',
      $a['job_id'] ?? '',
      $a['degree'] ?? '',
      $a['experience'] ?? '',
      $a['notice'] ?? '',
      $a['location'] ?? '',
      $a['preferred_plant'] ?? '',
      $a['currentStep'] ?? 1,
      $a['statusLabel'] ?? '',
      $a['reviewerNote'] ?? '',
      $a['internal_notes'] ?? '',
      $a['rating'] ?? 0,
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
    $targetId        = trim($_POST['candidate_id'] ?? '');
    $newStep         = intval($_POST['step'] ?? 1);
    $customLabel     = trim($_POST['status_label'] ?? '');
    $reviewerNote    = trim($_POST['reviewer_note'] ?? '');
    $internalNotes   = trim($_POST['internal_notes'] ?? '');
    $rating          = intval($_POST['rating'] ?? 0);
    $notifyCandidate = !empty($_POST['notify_candidate']);
    $customEmailBody = trim($_POST['custom_email_body'] ?? '');

    $meta = get_stage_meta($newStep);
    $statusLabel = $customLabel ?: $meta['title'];
    $statusClass = $meta['class'];

    $found = false;
    $updatedCand = null;

    foreach ($apps as $idx => $cand) {
      if (strtoupper($cand['id']) === strtoupper($targetId)) {
        $apps[$idx]['currentStep']    = $newStep;
        $apps[$idx]['statusLabel']    = $statusLabel;
        $apps[$idx]['statusClass']    = $statusClass;
        $apps[$idx]['reviewerNote']   = $reviewerNote;
        $apps[$idx]['internal_notes'] = $internalNotes;
        $apps[$idx]['rating']         = $rating;
        $apps[$idx]['updated_at']     = date('Y-m-d H:i:s');
        $apps[$idx]['updated_by']     = $u['name'] ?? ($u['email'] ?? 'HR Officer');
        $apps[$idx]['archived']       = ($newStep === -1);
        $updatedCand = $apps[$idx];
        $found = true;
        break;
      }
    }

    if ($found) {
      staff_save_applications($apps);
      $msg = "Application status for {$targetId} updated to \"{$statusLabel}\".";

      // Candidate Notification Email
      if ($notifyCandidate && !empty($updatedCand['email'])) {
        $toEmail = $updatedCand['email'];
        $subject = "[Vasudha Pharma] Application Status Update: {$targetId} — {$statusLabel}";
        $serverHost = $_SERVER['SERVER_NAME'] ?? ($_SERVER['HTTP_HOST'] ?? 'vasudhapharma.com');
        $mailDomain = (strpos($serverHost, 'mytemp.website') !== false || empty($serverHost)) ? 'vasudhapharma.com' : $serverHost;

        $bodyContent = $customEmailBody ?: ($reviewerNote ? $reviewerNote : "Your application credentials have been reviewed by our hiring committee and progressed to: {$statusLabel}.");

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
              <div style="margin-top:10px; font-size:13.5px; color:#1E293B; line-height:1.6; white-space:pre-wrap;">' . htmlspecialchars($bodyContent) . '</div>
            </div>
            <p style="font-size:13.5px; line-height:1.6; color:#475569;">You can track real-time progress of your application credentials at any time on our Careers Portal.</p>
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
        $msg .= " Email alert dispatched to {$toEmail}.";
      }
    } else {
      $err = "Application ID {$targetId} not found.";
    }
  }

  // Handle HR direct CV file upload
  if ($action === 'upload_cv') {
    $targetId = trim($_POST['candidate_id'] ?? '');
    if (!empty($_FILES['cv_file']) && $_FILES['cv_file']['error'] === UPLOAD_ERR_OK) {
      $ext = strtolower(pathinfo($_FILES['cv_file']['name'], PATHINFO_EXTENSION));
      if (in_array($ext, ['pdf', 'doc', 'docx'])) {
        $uploadDir = dirname(__DIR__) . '/uploads/resumes';
        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
        $safeName = 'cv_' . preg_replace('/[^A-Za-z0-9\-]/', '', $targetId) . '_' . date('Ymd_His') . '.' . $ext;
        if (move_uploaded_file($_FILES['cv_file']['tmp_name'], $uploadDir . '/' . $safeName)) {
          $newPath = 'uploads/resumes/' . $safeName;
          foreach ($apps as $idx => $cand) {
            if (strtoupper($cand['id']) === strtoupper($targetId)) {
              $apps[$idx]['cv_path'] = $newPath;
              $apps[$idx]['updated_at'] = date('Y-m-d H:i:s');
              break;
            }
          }
          staff_save_applications($apps);
          $msg = "Resume successfully attached to candidate {$targetId}.";
        }
      } else {
        $err = "Invalid file format. Please upload PDF, DOC, or DOCX.";
      }
    }
  }

  // Handle Bulk Pipeline Action
  if ($action === 'bulk_action') {
    $targetIds = $_POST['selected_ids'] ?? [];
    $bulkType = trim($_POST['bulk_type'] ?? '');
    if (!empty($targetIds) && is_array($targetIds)) {
      $count = 0;
      if ($bulkType === 'delete') {
        $apps = array_values(array_filter($apps, function($c) use ($targetIds) {
          return !in_array($c['id'] ?? '', $targetIds, true);
        }));
        staff_save_applications($apps);
        $msg = count($targetIds) . " candidate records permanently deleted.";
      } else {
        $stepMap = ['step_1' => 1, 'step_2' => 2, 'step_3' => 3, 'step_4' => 4, 'archive' => -1];
        if (isset($stepMap[$bulkType])) {
          $newStep = $stepMap[$bulkType];
          $meta = get_stage_meta($newStep);
          foreach ($apps as $idx => $cand) {
            if (in_array($cand['id'], $targetIds, true)) {
              $apps[$idx]['currentStep'] = $newStep;
              $apps[$idx]['statusLabel'] = $meta['title'];
              $apps[$idx]['statusClass'] = $meta['class'];
              $apps[$idx]['archived'] = ($newStep === -1);
              $apps[$idx]['updated_at'] = date('Y-m-d H:i:s');
              $apps[$idx]['updated_by'] = $u['name'] ?? ($u['email'] ?? 'HR Bulk Update');
              $count++;
            }
          }
          staff_save_applications($apps);
          $msg = "{$count} candidates moved to \"{$meta['title']}\".";
        }
      }
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

  // Handle Talent Community Updates
  if ($action === 'update_talent') {
    $tId = trim($_POST['talent_id'] ?? '');
    $newStatus = trim($_POST['status'] ?? 'Available');
    $notes = trim($_POST['notes'] ?? '');
    $found = false;
    foreach ($talentPool as $i => $item) {
      if (($item['id'] ?? '') === $tId) {
        $talentPool[$i]['status'] = $newStatus;
        $talentPool[$i]['notes'] = $notes;
        $talentPool[$i]['updated_at'] = date('Y-m-d H:i:s');
        $found = true;
        break;
      }
    }
    if ($found) {
      staff_save_talent_pool($talentPool);
      $msg = "Talent Network profile {$tId} successfully updated.";
    } else {
      $err = "Talent profile {$tId} not found.";
    }
  }

  if ($action === 'delete_talent') {
    $tId = trim($_POST['talent_id'] ?? '');
    $beforeCount = count($talentPool);
    $talentPool = array_values(array_filter($talentPool, function($item) use ($tId) {
      return ($item['id'] ?? '') !== $tId;
    }));
    if (count($talentPool) < $beforeCount) {
      staff_save_talent_pool($talentPool);
      $msg = "Member {$tId} removed from Talent Network pool.";
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

// Requisitions index for filter dropdown
$distinctJobs = [];
foreach ($apps as $a) {
  $jid = trim($a['job_id'] ?? '');
  $jtitle = trim($a['job_title'] ?? '');
  $key = $jid ?: $jtitle;
  if ($key && !isset($distinctJobs[$key])) {
    $distinctJobs[$key] = $jtitle . ($jid ? " ({$jid})" : "");
  }

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

// Query parameters for pre-filtering
$paramJob = trim($_GET['job'] ?? '');

$staff_title = 'Candidate Desk';
require __DIR__ . '/includes/header.php';
?>

<style>
  .badge-warn { background: #FEF3C7; color: #D97706; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 11.5px; display: inline-flex; align-items: center; gap: 4px; }
  .badge-info { background: #E0F2FE; color: #0284C7; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 11.5px; display: inline-flex; align-items: center; gap: 4px; }
  .badge-purple { background: #F3E8FF; color: #7E22CE; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 11.5px; display: inline-flex; align-items: center; gap: 4px; }
  .badge-open { background: #DCFCE7; color: #15803D; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 11.5px; display: inline-flex; align-items: center; gap: 4px; }
  .badge-closed { background: #F1F5F9; color: #64748B; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 11.5px; display: inline-flex; align-items: center; gap: 4px; }
  
  .cand-card {
    background: #FFFFFF;
    border: 1px solid var(--sp-border);
    border-radius: 14px;
    padding: 22px 24px;
    margin-bottom: 18px;
    transition: all 0.2s ease;
    box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
    position: relative;
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
    margin-bottom: 0;
  }
  .filter-tab {
    background: #FFFFFF;
    border: 1px solid var(--sp-border);
    color: #475569;
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
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

  .bulk-bar {
    background: #0F172A;
    color: #FFFFFF;
    border-radius: 10px;
    padding: 12px 20px;
    margin-bottom: 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    box-shadow: 0 4px 14px rgba(0,0,0,0.15);
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
<div class="card" style="background: <?php echo $currentTab === 'talent' ? 'linear-gradient(135deg, #075E74 0%, #0088AA 60%, #17A9C9 100%)' : 'linear-gradient(135deg, #096B51 0%, #0E8F6C 60%, #1DB88A 100%)'; ?>; color:#FFFFFF; border:0; padding:24px 28px; box-shadow:0 8px 24px rgba(14,143,108,0.18);">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
    <div>
      <div style="text-transform:uppercase; font-size:11px; letter-spacing:0.12em; opacity:0.85; font-weight:700; margin-bottom:4px;">
        Vasudha Pharma Chem &bull; Talent Acquisition ATS Console
      </div>
      <h1 style="margin:0; font-size:24px; color:#FFFFFF;">
        <?php echo $currentTab === 'talent' ? 'Talent Community &amp; Proactive Bench' : 'Applicant Pipeline &amp; HR Management Desk'; ?>
      </h1>
      <p style="margin:6px 0 0; opacity:0.9; font-size:13.5px; max-width:650px;">
        <?php if ($currentTab === 'talent'): ?>
          Manage proactive talent registrations from prospective scientists and engineers. Filter by domain, evaluate credentials, record internal HR notes, and reach out directly when openings arise.
        <?php else: ?>
          Review candidates, evaluate technical skills extracted by AI, inspect resumes, record internal HR interview ratings, and advance hiring stages. Candidate status updates sync live with the candidate tracking system.
        <?php endif; ?>
      </p>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
      <?php if ($currentTab === 'talent'): ?>
        <a href="candidates.php?tab=talent&export=talent_csv" class="btn" style="background:#FFFFFF; color:#075E74; font-weight:700; box-shadow:0 2px 8px rgba(0,0,0,0.12);">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Export Talent Bench (CSV)
        </a>
        <a href="../talent-community.html" target="_blank" rel="noopener" class="btn" style="background:rgba(255,255,255,0.2); color:#FFFFFF; border:1px solid rgba(255,255,255,0.3); font-weight:600;">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/></svg>
          Public Registration Page
        </a>
      <?php else: ?>
        <a href="candidates.php?export=csv" class="btn" style="background:#FFFFFF; color:#096B51; font-weight:700; box-shadow:0 2px 8px rgba(0,0,0,0.12);">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Export Candidates (CSV)
        </a>
        <a href="jobs.php" class="btn" style="background:rgba(255,255,255,0.2); color:#FFFFFF; border:1px solid rgba(255,255,255,0.3); font-weight:600;">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
          Requisitions Desk
        </a>
        <a href="../apply.html" target="_blank" rel="noopener" class="btn" style="background:rgba(255,255,255,0.2); color:#FFFFFF; border:1px solid rgba(255,255,255,0.3); font-weight:600;">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/></svg>
          Public Tracker Portal
        </a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Primary Sub-Desk Switcher Tabs -->
<div style="display:flex; gap:10px; margin:20px 0 22px; border-bottom:2px solid #E2E8F0; padding-bottom:12px; flex-wrap:wrap;">
  <a href="candidates.php?tab=active" class="btn" style="<?php echo $currentTab === 'active' ? 'background:#0E8F6C; color:#FFFFFF; font-weight:700; box-shadow:0 4px 12px rgba(14,143,108,0.2);' : 'background:#FFFFFF; color:#475569; border:1px solid #CBD5E1; font-weight:600;'; ?> padding:10px 18px; border-radius:10px; font-size:13.5px; display:inline-flex; align-items:center; gap:8px;">
    <span>💼 Active Job Applications</span>
    <span style="background:<?php echo $currentTab === 'active' ? 'rgba(255,255,255,0.25)' : '#F1F5F9'; ?>; padding:2px 8px; border-radius:12px; font-size:11.5px; font-weight:800;"><?php echo count($apps); ?></span>
  </a>
  <a href="candidates.php?tab=talent" class="btn" style="<?php echo $currentTab === 'talent' ? 'background:#0088AA; color:#FFFFFF; font-weight:700; box-shadow:0 4px 12px rgba(0,136,170,0.2);' : 'background:#FFFFFF; color:#475569; border:1px solid #CBD5E1; font-weight:600;'; ?> padding:10px 18px; border-radius:10px; font-size:13.5px; display:inline-flex; align-items:center; gap:8px;">
    <span>🌐 Talent Community Network Pool</span>
    <span style="background:<?php echo $currentTab === 'talent' ? 'rgba(255,255,255,0.25)' : '#F1F5F9'; ?>; padding:2px 8px; border-radius:12px; font-size:11.5px; font-weight:800;"><?php echo count($talentPool); ?></span>
  </a>
</div>

<?php if ($currentTab === 'talent'): ?>
  <?php
    $talentCounts = [
      'all'        => count($talentPool),
      'available'  => 0,
      'contacted'  => 0,
      'converted'  => 0,
      'archived'   => 0
    ];
    $distinctDomains = [];
    foreach ($talentPool as $t) {
      $st = $t['status'] ?? 'Available';
      if ($st === 'Available') $talentCounts['available']++;
      elseif ($st === 'Contacted') $talentCounts['contacted']++;
      elseif ($st === 'Converted') $talentCounts['converted']++;
      elseif ($st === 'Archived') $talentCounts['archived']++;

      $d = $t['domain'] ?? '';
      if ($d && !in_array($d, $distinctDomains, true)) {
        $distinctDomains[] = $d;
      }
    }
    sort($distinctDomains);
  ?>

  <!-- KPI Metrics Strip for Talent Pool -->
  <div class="row-4" style="margin-bottom:22px;">
    <div class="stat-card">
      <div class="stat-label">Total in Talent Bench</div>
      <div class="stat-num"><?php echo $talentCounts['all']; ?></div>
      <div class="stat-sub">Proactive candidate profiles</div>
    </div>
    <div class="stat-card" style="border-left:4px solid #059669;">
      <div class="stat-label">Available / On Bench</div>
      <div class="stat-num" style="color:#059669;"><?php echo $talentCounts['available']; ?></div>
      <div class="stat-sub">Ready for outreach</div>
    </div>
    <div class="stat-card" style="border-left:4px solid #0284C7;">
      <div class="stat-label">Contacted / Engaged</div>
      <div class="stat-num" style="color:#0284C7;"><?php echo $talentCounts['contacted']; ?></div>
      <div class="stat-sub">HR discussions in progress</div>
    </div>
    <div class="stat-card" style="border-left:4px solid #7E22CE;">
      <div class="stat-label">Converted to Requisition</div>
      <div class="stat-num" style="color:#7E22CE;"><?php echo $talentCounts['converted']; ?></div>
      <div class="stat-sub">Moved into formal hiring</div>
    </div>
  </div>

  <!-- Live Multi-Criteria Filter Card for Talent Pool -->
  <div class="card" style="margin-bottom:20px; padding:18px 22px;">
    <div style="display:grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap:12px; align-items:flex-end;">
      <div>
        <label style="font-size:11.5px; font-weight:700; color:#64748B; text-transform:uppercase; letter-spacing:0.05em; display:block; margin-bottom:6px;">Search Talent Profiles</label>
        <input type="text" id="liveTalentSearch" placeholder="Search by name, email, phone, or technical skills..." style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #CBD5E1; border-radius:8px; font-size:13.5px;">
      </div>
      <div>
        <label style="font-size:11.5px; font-weight:700; color:#64748B; text-transform:uppercase; letter-spacing:0.05em; display:block; margin-bottom:6px;">Domain / Specialization</label>
        <select id="filterTalentDomain" style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #CBD5E1; border-radius:8px; font-size:13px; background:#fff;">
          <option value="">All Domains</option>
          <option value="Chemical R&D">Chemical R&D / Process Chemistry</option>
          <option value="Quality Control">Quality Control (HPLC / GC-MS)</option>
          <option value="Quality Assurance">Quality Assurance & Compliance</option>
          <option value="Manufacturing">API & Commercial Manufacturing</option>
          <option value="Pellets">Pellets, MUPS & Formulations</option>
          <option value="Regulatory">Regulatory Affairs & IPR</option>
          <option value="EHS">EHS, Safety & Engineering</option>
          <option value="Supply Chain">Supply Chain & Commercial</option>
        </select>
      </div>
      <div>
        <label style="font-size:11.5px; font-weight:700; color:#64748B; text-transform:uppercase; letter-spacing:0.05em; display:block; margin-bottom:6px;">Experience Level</label>
        <select id="filterTalentExp" style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #CBD5E1; border-radius:8px; font-size:13px; background:#fff;">
          <option value="">All Experience</option>
          <option value="Fresher">Fresher / Graduate Trainee (&lt; 1 yr)</option>
          <option value="1 to 3">1 to 3 Years</option>
          <option value="4 to 7">4 to 7 Years</option>
          <option value="8 to 12">8 to 12 Years</option>
          <option value="12+">12+ Years (Leadership)</option>
        </select>
      </div>
      <div>
        <label style="font-size:11.5px; font-weight:700; color:#64748B; text-transform:uppercase; letter-spacing:0.05em; display:block; margin-bottom:6px;">Status</label>
        <select id="filterTalentStatus" style="width:100%; box-sizing:border-box; padding:9px 12px; border:1px solid #CBD5E1; border-radius:8px; font-size:13px; background:#fff;">
          <option value="">All Statuses</option>
          <option value="Available">Available / Bench</option>
          <option value="Contacted">Contacted</option>
          <option value="Converted">Converted</option>
          <option value="Archived">Archived</option>
        </select>
      </div>
      <div>
        <button type="button" id="btnClearTalentFilters" class="btn" style="background:#F1F5F9; color:#475569; font-size:12.5px; padding:9px 14px;">Reset</button>
      </div>
    </div>
  </div>

  <!-- Talent Members List -->
  <div id="talentListContainer">
    <?php if (empty($talentPool)): ?>
      <div class="card" style="text-align:center; padding:48px 24px;">
        <div style="font-size:42px; margin-bottom:12px;">🌐</div>
        <h3 style="font-size:18px; color:#0F172A; margin:0 0 6px;">No Talent Network Members Registered Yet</h3>
        <p style="color:#64748B; font-size:14px; max-width:500px; margin:0 auto 18px;">
          Candidates who register via the public Talent Community page will automatically populate this proactive talent bench.
        </p>
        <a href="../talent-community.html" target="_blank" class="btn btn-primary" style="display:inline-flex; align-items:center; gap:6px;">
          <span>Open Talent Community Page &rarr;</span>
        </a>
      </div>
    <?php else: ?>
      <?php foreach ($talentPool as $t):
        $tId = $t['id'] ?? '';
        $tName = $t['name'] ?? 'Candidate';
        $tEmail = $t['email'] ?? '';
        $tPhone = $t['phone'] ?? '';
        $tDomain = $t['domain'] ?? 'General Pharmaceutical';
        $tExp = $t['experience'] ?? 'Unspecified';
        $tLoc = $t['location'] ?? 'Flexible';
        $tStatus = $t['status'] ?? 'Available';
        $tNotes = $t['notes'] ?? '';
        $tSummary = $t['summary'] ?? '';
        $tDate = $t['date'] ?? '';

        $statusColors = [
          'Available' => ['bg' => '#DCFCE7', 'fg' => '#15803D'],
          'Contacted' => ['bg' => '#E0F2FE', 'fg' => '#0284C7'],
          'Converted' => ['bg' => '#F3E8FF', 'fg' => '#7E22CE'],
          'Archived'  => ['bg' => '#F1F5F9', 'fg' => '#64748B']
        ];
        $stCol = $statusColors[$tStatus] ?? $statusColors['Available'];
        $cleanPhone = preg_replace('/[^0-9]/', '', $tPhone);
      ?>
      <div class="cand-card talent-card" data-domain="<?php echo htmlspecialchars($tDomain); ?>" data-exp="<?php echo htmlspecialchars($tExp); ?>" data-status="<?php echo htmlspecialchars($tStatus); ?>" data-search="<?php echo htmlspecialchars(strtolower($tId . ' ' . $tName . ' ' . $tEmail . ' ' . $tPhone . ' ' . $tDomain . ' ' . $tSummary)); ?>">
        <div class="cand-header">
          <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <span class="cand-id-badge" style="background:#E0F2FE; border-color:#BAE6FD; color:#0369A1;">
              <?php echo htmlspecialchars($tId); ?>
            </span>
            <span style="font-size:12.5px; color:#64748B;">Registered: <strong><?php echo htmlspecialchars($tDate); ?></strong></span>
          </div>
          <span style="background:<?php echo $stCol['bg']; ?>; color:<?php echo $stCol['fg']; ?>; padding:4px 12px; border-radius:6px; font-weight:700; font-size:12px; display:inline-flex; align-items:center; gap:6px;">
            <span style="width:7px; height:7px; border-radius:50%; background:currentColor;"></span>
            <?php echo htmlspecialchars($tStatus); ?>
          </span>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 340px; gap:24px; align-items:start;">
          <!-- Left: Candidate Information -->
          <div>
            <h3 style="font-size:18px; font-weight:800; color:#0F172A; margin:0 0 8px;">
              <?php echo htmlspecialchars($tName); ?>
            </h3>

            <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:14px;">
              <span style="background:#ECFDF5; color:#0E8F6C; border:1px solid #A7F3D0; padding:3px 10px; border-radius:6px; font-size:12px; font-weight:700;">
                🔬 <?php echo htmlspecialchars($tDomain); ?>
              </span>
              <span style="background:#F8FAFC; color:#334155; border:1px solid #E2E8F0; padding:3px 10px; border-radius:6px; font-size:12px; font-weight:600;">
                ⏱️ <?php echo htmlspecialchars($tExp); ?>
              </span>
              <span style="background:#F8FAFC; color:#334155; border:1px solid #E2E8F0; padding:3px 10px; border-radius:6px; font-size:12px; font-weight:600;">
                📍 <?php echo htmlspecialchars($tLoc); ?>
              </span>
            </div>

            <!-- Direct Contact Actions -->
            <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:14px;">
              <a href="mailto:<?php echo htmlspecialchars($tEmail); ?>?subject=<?php echo rawurlencode('Inquiry from Vasudha Pharma Talent Acquisition: ' . $tName); ?>" class="btn" style="background:#ECFDF5; color:#0E8F6C; border:1px solid #A7F3D0; font-size:12px; padding:6px 12px; font-weight:600;">
                ✉️ <?php echo htmlspecialchars($tEmail); ?>
              </a>
              <?php if ($tPhone): ?>
                <a href="tel:<?php echo htmlspecialchars($tPhone); ?>" class="btn" style="background:#F1F5F9; color:#334155; border:1px solid #E2E8F0; font-size:12px; padding:6px 12px; font-weight:600;">
                  📞 <?php echo htmlspecialchars($tPhone); ?>
                </a>
                <?php if ($cleanPhone): ?>
                  <a href="https://wa.me/<?php echo htmlspecialchars($cleanPhone); ?>?text=<?php echo rawurlencode('Hello ' . $tName . ', Greetings from Vasudha Pharma Chem Limited Talent Acquisition team regarding your registration with our Talent Community.'); ?>" target="_blank" rel="noopener" class="btn" style="background:#DCFCE7; color:#15803D; border:1px solid #86EFAC; font-size:12px; padding:6px 12px; font-weight:600;">
                    💬 WhatsApp
                  </a>
                <?php endif; ?>
              <?php endif; ?>
            </div>

            <?php if ($tSummary): ?>
              <div style="background:#F8FAFC; border-left:3px solid #0088AA; border-radius:6px; padding:12px 14px; font-size:13px; line-height:1.6; color:#334155; margin-top:8px;">
                <strong style="color:#0F172A; display:block; font-size:11.5px; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">Credentials &amp; Technical Capabilities:</strong>
                <?php echo nl2br(htmlspecialchars($tSummary)); ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- Right: Status Update & HR Notes -->
          <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:10px; padding:16px;">
            <form method="post">
              <?php echo staff_csrf_field(); ?>
              <input type="hidden" name="action" value="update_talent">
              <input type="hidden" name="talent_id" value="<?php echo htmlspecialchars($tId); ?>">

              <label style="font-size:11.5px; font-weight:700; color:#475569; text-transform:uppercase; display:block; margin-bottom:4px;">Talent Bench Status</label>
              <select name="status" style="width:100%; box-sizing:border-box; padding:8px 10px; border:1px solid #CBD5E1; border-radius:6px; font-size:13px; background:#fff; margin-bottom:12px;">
                <option value="Available" <?php echo $tStatus === 'Available' ? 'selected' : ''; ?>>🟢 Available / In Bench</option>
                <option value="Contacted" <?php echo $tStatus === 'Contacted' ? 'selected' : ''; ?>>🔵 Contacted / In Outreach</option>
                <option value="Converted" <?php echo $tStatus === 'Converted' ? 'selected' : ''; ?>>🟣 Converted to Requisition</option>
                <option value="Archived" <?php echo $tStatus === 'Archived' ? 'selected' : ''; ?>>⚪ Archived / Closed</option>
              </select>

              <label style="font-size:11.5px; font-weight:700; color:#475569; text-transform:uppercase; display:block; margin-bottom:4px;">Private HR Notes</label>
              <textarea name="notes" rows="2" placeholder="e.g. Cleared preliminary telephonic screening; consider for Unit-2 expansion..." style="width:100%; box-sizing:border-box; padding:8px 10px; border:1px solid #CBD5E1; border-radius:6px; font-size:12.5px; resize:vertical; margin-bottom:10px;"><?php echo htmlspecialchars($tNotes); ?></textarea>

              <button type="submit" class="btn btn-primary" style="width:100%; font-size:12.5px; padding:8px 12px; justify-content:center;">
                Save Profile Status
              </button>
            </form>

            <div style="display:flex; justify-content:flex-end; margin-top:10px;">
              <form method="post" onsubmit="return confirm('Are you sure you want to remove <?php echo htmlspecialchars($tName); ?> (<?php echo htmlspecialchars($tId); ?>) from the Talent Network?');" style="margin:0;">
                <?php echo staff_csrf_field(); ?>
                <input type="hidden" name="action" value="delete_talent">
                <input type="hidden" name="talent_id" value="<?php echo htmlspecialchars($tId); ?>">
                <button type="submit" style="background:none; border:none; color:#EF4444; font-size:11.5px; cursor:pointer; padding:2px;">
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                  Remove from bench
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
<?php else: ?>
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

<!-- Advanced Search & Multi-Criteria Filtering Console -->
<div class="card" style="padding:18px 24px; margin-bottom:18px;">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; margin-bottom:14px;">
    <!-- Live Search Input -->
    <div style="flex:1; min-width:260px; max-width:440px; position:relative;">
      <input type="text" id="liveSearchInput" placeholder="Instant search candidate name, ID, position, skills, email..." style="padding-left:36px;">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2.2" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); pointer-events:none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    </div>

    <!-- Quick Pipeline Stage Tabs -->
    <div class="filter-tabs">
      <button type="button" class="filter-tab active" data-filter="all">All (<?php echo $stageCounts['all']; ?>)</button>
      <button type="button" class="filter-tab" data-filter="step-1">1: Screening (<?php echo $stageCounts['step1']; ?>)</button>
      <button type="button" class="filter-tab" data-filter="step-2">2: Technical (<?php echo $stageCounts['step2']; ?>)</button>
      <button type="button" class="filter-tab" data-filter="step-3">3: Interview (<?php echo $stageCounts['step3']; ?>)</button>
      <button type="button" class="filter-tab" data-filter="step-4">4: Offer Issued (<?php echo $stageCounts['step4']; ?>)</button>
      <button type="button" class="filter-tab" data-filter="step-archived">Archived (<?php echo $stageCounts['archived']; ?>)</button>
    </div>
  </div>

  <!-- Secondary Filters: Position, Plant, Experience -->
  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:12px; border-top:1px solid #F1F5F9; padding-top:12px;">
    <div>
      <label style="font-size:11.5px; font-weight:700; color:#475569; text-transform:uppercase; margin-bottom:4px; display:block;">Filter by Requisition:</label>
      <select id="filterRequisition" style="font-size:12.5px; padding:6px 10px;">
        <option value="">All Requisitions (<?php echo count($apps); ?>)</option>
        <?php foreach ($distinctJobs as $jKey => $jTitle): ?>
          <option value="<?php echo htmlspecialchars(strtolower($jKey)); ?>" <?php echo (strtolower($paramJob) === strtolower($jKey)) ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($jTitle); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label style="font-size:11.5px; font-weight:700; color:#475569; text-transform:uppercase; margin-bottom:4px; display:block;">Filter by Plant Location:</label>
      <select id="filterPlant" style="font-size:12.5px; padding:6px 10px;">
        <option value="">All Facilities</option>
        <option value="hyderabad">Hyderabad (R&amp;D / Unit-1, 2)</option>
        <option value="vizag">Visakhapatnam (Units 3, 4, 5)</option>
        <option value="open">Open / Flexible</option>
      </select>
    </div>

    <div>
      <label style="font-size:11.5px; font-weight:700; color:#475569; text-transform:uppercase; margin-bottom:4px; display:block;">Experience Range:</label>
      <select id="filterExp" style="font-size:12.5px; padding:6px 10px;">
        <option value="">All Experience Levels</option>
        <option value="fresher">Freshers / &lt; 2 Years</option>
        <option value="mid">Mid-Level (2 to 5 Years)</option>
        <option value="senior">Senior (5+ Years)</option>
      </select>
    </div>

    <div style="display:flex; align-items:flex-end;">
      <button type="button" class="btn btn-ghost" id="btnClearFilters" style="font-size:12px; width:100%; justify-content:center;">
        Reset All Filters
      </button>
    </div>
  </div>
</div>

<!-- Bulk Actions Toolbar -->
<form method="post" id="bulkActionForm" class="bulk-bar" style="display:none;">
  <?php echo staff_csrf_field(); ?>
  <input type="hidden" name="action" value="bulk_action">
  <div style="display:flex; align-items:center; gap:12px;">
    <span style="font-size:13.5px; font-weight:700;"><span id="bulkSelectedCount">0</span> Candidates Selected</span>
    <button type="button" class="btn btn-ghost" id="btnSelectAll" style="padding:4px 10px; font-size:11.5px; color:#94A3B8; border-color:#334155;">Select All</button>
  </div>
  <div style="display:flex; align-items:center; gap:8px;">
    <select name="bulk_type" required style="font-size:12.5px; padding:6px 10px; background:#1E293B; color:#FFFFFF; border-color:#334155;">
      <option value="">Choose Bulk Action...</option>
      <option value="step_1">Move to Step 1: Profile Screening</option>
      <option value="step_2">Move to Step 2: Technical Assessment</option>
      <option value="step_3">Move to Step 3: Panel Interview</option>
      <option value="step_4">Move to Step 4: Formal Offer Issued</option>
      <option value="archive">Move to Archive / Closed</option>
      <option value="delete">Permanently Delete Records</option>
    </select>
    <button type="submit" class="btn btn-primary" style="background:#0E8F6C; font-size:12.5px;" onclick="return confirm('Apply bulk action to all selected candidates?');">
      Apply to Selected
    </button>
  </div>
</form>

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
      $jobKey = strtolower(trim(($cand['job_id'] ?? '') . ' ' . ($cand['job_title'] ?? '')));
      $plantKey = strtolower(trim($cand['preferred_plant'] ?? ($cand['location'] ?? '')));
      $expNum = floatval($cand['experience'] ?? 0);
      $expTier = ($expNum < 2) ? 'fresher' : (($expNum <= 5) ? 'mid' : 'senior');
      $skills = $cand['skills'] ?? [];
      $rating = intval($cand['rating'] ?? 0);
    ?>
      <div class="cand-card"
           data-stage="<?php echo $stageFilterClass; ?>"
           data-job="<?php echo htmlspecialchars($jobKey); ?>"
           data-plant="<?php echo htmlspecialchars($plantKey); ?>"
           data-exp="<?php echo htmlspecialchars($expTier); ?>"
           data-search="<?php echo htmlspecialchars(strtolower(($cand['id'] ?? '') . ' ' . ($cand['name'] ?? '') . ' ' . ($cand['email'] ?? '') . ' ' . ($cand['job_title'] ?? '') . ' ' . ($cand['job_id'] ?? '') . ' ' . ($cand['degree'] ?? '') . ' ' . ($cand['phone'] ?? '') . ' ' . implode(' ', $skills))); ?>">
        
        <div class="cand-header">
          <div style="display:flex; align-items:flex-start; gap:12px;">
            <input type="checkbox" name="selected_ids[]" form="bulkActionForm" value="<?php echo htmlspecialchars($candId); ?>" class="cand-checkbox" style="margin-top:6px; cursor:pointer; width:16px; height:16px;">
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
                <?php if ($rating > 0): ?>
                  <span style="color:#F59E0B; font-size:13px;" title="HR Rating: <?php echo $rating; ?>/5">
                    <?php echo str_repeat('★', $rating) . str_repeat('☆', 5 - $rating); ?>
                  </span>
                <?php endif; ?>
              </div>
              <h3 style="margin:0 0 4px; font-size:18px; color:#0F172A;">
                <?php echo htmlspecialchars($cand['name'] ?? 'Candidate'); ?>
              </h3>
              <div style="font-size:14px; font-weight:600; color:#096B51; display:flex; align-items:center; gap:8px;">
                <span>Position: <?php echo htmlspecialchars($cand['job_title'] ?? 'Pharmaceutical Candidate'); ?></span>
                <?php if (!empty($cand['job_id'])): ?>
                  <a href="jobs.php?edit=<?php echo urlencode($cand['job_id']); ?>" style="font-size:11.5px; color:#0E8F6C; text-decoration:underline;">[View Requisition]</a>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <?php if (!empty($cand['cv_path'])): ?>
              <a href="../<?php echo htmlspecialchars($cand['cv_path']); ?>" target="_blank" class="btn btn-primary" style="padding:7px 14px; font-size:12.5px; background:#0E8F6C;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="12" y2="18"/><line x1="15" y1="15" x2="12" y2="18"/></svg>
                View / Download Resume (CV)
              </a>
            <?php else: ?>
              <!-- HR Upload CV Form -->
              <form method="post" enctype="multipart/form-data" style="display:inline-flex; align-items:center; gap:4px; margin:0;">
                <?php echo staff_csrf_field(); ?>
                <input type="hidden" name="action" value="upload_cv">
                <input type="hidden" name="candidate_id" value="<?php echo htmlspecialchars($candId); ?>">
                <label class="btn btn-ghost" style="padding:6px 10px; font-size:12px; cursor:pointer;">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                  Attach CV
                  <input type="file" name="cv_file" accept=".pdf,.doc,.docx" onchange="this.form.submit()" style="display:none;">
                </label>
              </form>
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
        <div class="row-4" style="background:#F8FAFC; border-radius:10px; padding:12px 16px; margin-bottom:14px; font-size:13px;">
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
              <?php echo htmlspecialchars($cand['degree'] ?: 'Degree in CV'); ?><br>
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
            <div style="color:#64748B; font-size:11.5px; font-weight:600;">NOTICE PERIOD &amp; ACTIVITY</div>
            <div style="font-weight:600; color:#0F172A; margin-top:2px;">
              <?php echo htmlspecialchars(ucfirst($cand['notice'] ?: '30 Days')); ?><br>
              <span style="color:#94A3B8; font-size:11.5px;">Last active: <?php echo htmlspecialchars($cand['updated_at'] ? date('d M Y', strtotime($cand['updated_at'])) : ($cand['date'] ?? '')); ?></span>
            </div>
          </div>
        </div>

        <!-- Detected Skills Tags -->
        <?php if (!empty($skills)): ?>
          <div style="margin-bottom:12px; display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
            <span style="font-size:11.5px; font-weight:700; color:#475569; text-transform:uppercase;">AI Extracted Competencies:</span>
            <?php foreach ($skills as $sk): ?>
              <span style="background:#EFF6FF; color:#1D4ED8; font-size:11px; font-weight:600; padding:2px 8px; border-radius:4px; border:1px solid #BFDBFE;">
                <?php echo htmlspecialchars(strtoupper($sk)); ?>
              </span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($cand['note'])): ?>
          <div style="background:#FFFFFF; border:1px solid #E2E8F0; border-radius:8px; padding:10px 14px; margin-bottom:14px; font-size:13px; color:#334155;">
            <strong style="color:#0F172A; font-size:12px; text-transform:uppercase; letter-spacing:0.04em;">Applicant Summary / Cover Highlights:</strong>
            <p style="margin:4px 0 0; line-height:1.5; font-style:italic;"><?php echo nl2br(htmlspecialchars($cand['note'])); ?></p>
          </div>
        <?php endif; ?>

        <!-- HR Pipeline Stage Updater Form -->
        <div style="background:#F0FDF4; border:1px solid #BBF7D0; border-radius:10px; padding:16px 18px;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; flex-wrap:wrap; gap:8px;">
            <div style="font-size:13.5px; font-weight:700; color:#065F46; display:flex; align-items:center; gap:6px;">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
              Advance Hiring Pipeline &amp; Public Candidate Remarks
            </div>
            <?php if (!empty($cand['updated_by'])): ?>
              <span style="font-size:11.5px; color:#047857;">Last updated by <em><?php echo htmlspecialchars($cand['updated_by']); ?></em></span>
            <?php endif; ?>
          </div>

          <form method="post" id="form-<?php echo htmlspecialchars($candId); ?>">
            <?php echo staff_csrf_field(); ?>
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="candidate_id" value="<?php echo htmlspecialchars($candId); ?>">

            <div class="row" style="margin-bottom:10px;">
              <div>
                <label style="margin-top:0; font-size:12px; color:#065F46;">Hiring Pipeline Step *</label>
                <select name="step" id="step-<?php echo htmlspecialchars($candId); ?>">
                  <option value="1" <?php echo ($stepNum === 1 && !$isArchived) ? 'selected' : ''; ?>>Step 1: Profile Screening (Initial Review)</option>
                  <option value="2" <?php echo ($stepNum === 2 && !$isArchived) ? 'selected' : ''; ?>>Step 2: Technical Assessment (Written / Evaluation)</option>
                  <option value="3" <?php echo ($stepNum === 3 && !$isArchived) ? 'selected' : ''; ?>>Step 3: Panel Interview Scheduled</option>
                  <option value="4" <?php echo ($stepNum === 4 && !$isArchived) ? 'selected' : ''; ?>>Step 4: Formal Offer Issued &amp; Onboarding</option>
                  <option value="-1" <?php echo $isArchived ? 'selected' : ''; ?>>Archived / Position Closed / On Reserve File</option>
                </select>
              </div>

              <div>
                <label style="margin-top:0; font-size:12px; color:#065F46;">Status Headline (Visible to Candidate on Live Tracker)</label>
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

            <div class="row" style="margin-bottom:10px;">
              <div>
                <label style="margin-top:0; font-size:12px; color:#065F46;">Reviewer Remarks &amp; Feedback (Visible to Candidate on Live Tracker)</label>
                <textarea name="reviewer_note" id="note-<?php echo htmlspecialchars($candId); ?>" rows="2" style="min-height:56px; background:#FFFFFF;" placeholder="Type specific notes, interview dates, assessment scores, or instructions..."><?php echo htmlspecialchars($cand['reviewerNote'] ?? ''); ?></textarea>
              </div>

              <div>
                <label style="margin-top:0; font-size:12px; color:#991B1B; font-weight:700;">🔒 Private Internal HR Notes (Confidential — Hidden from Candidate)</label>
                <textarea name="internal_notes" rows="2" style="min-height:56px; background:#FFFBEB; border-color:#FDE68A;" placeholder="Expected CTC, current salary, notice period negotiations, interviewer ratings..."><?php echo htmlspecialchars($cand['internal_notes'] ?? ''); ?></textarea>
              </div>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-top:8px;">
              <div style="display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
                <label style="margin:0; font-weight:500; font-size:12.5px; color:#065F46; display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                  <input type="checkbox" name="notify_candidate" value="1" style="width:auto; margin:0;" checked>
                  Dispatch instant email alert to candidate (<code><?php echo htmlspecialchars($cand['email'] ?? ''); ?></code>)
                </label>

                <div style="display:inline-flex; align-items:center; gap:4px;">
                  <span style="font-size:12px; color:#065F46; font-weight:600;">Fit Rating:</span>
                  <select name="rating" style="padding:4px 8px; font-size:12px; width:auto;">
                    <option value="0" <?php echo $rating === 0 ? 'selected' : ''; ?>>Unrated</option>
                    <option value="5" <?php echo $rating === 5 ? 'selected' : ''; ?>>★★★★★ Outstanding</option>
                    <option value="4" <?php echo $rating === 4 ? 'selected' : ''; ?>>★★★★☆ Strong Fit</option>
                    <option value="3" <?php echo $rating === 3 ? 'selected' : ''; ?>>★★★☆☆ Good</option>
                    <option value="2" <?php echo $rating === 2 ? 'selected' : ''; ?>>★★☆☆☆ Marginal</option>
                    <option value="1" <?php echo $rating === 1 ? 'selected' : ''; ?>>★☆☆☆☆ Poor</option>
                  </select>
                </div>
              </div>

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
              Delete candidate record
            </button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php endif; /* end active tab view */ ?>

<script>
  // Talent Community Live Filter System
  (function() {
    var search = document.getElementById('liveTalentSearch');
    var domain = document.getElementById('filterTalentDomain');
    var exp = document.getElementById('filterTalentExp');
    var status = document.getElementById('filterTalentStatus');
    var clear = document.getElementById('btnClearTalentFilters');
    var cards = document.querySelectorAll('.talent-card');

    if (!search && !domain && !cards.length) return;

    function filterTalent() {
      var q = (search ? search.value : '').toLowerCase().trim();
      var d = (domain ? domain.value : '').toLowerCase().trim();
      var e = (exp ? exp.value : '').toLowerCase().trim();
      var s = (status ? status.value : '').toLowerCase().trim();

      cards.forEach(function(card) {
        var cardSearch = (card.getAttribute('data-search') || '').toLowerCase();
        var cardDomain = (card.getAttribute('data-domain') || '').toLowerCase();
        var cardExp = (card.getAttribute('data-exp') || '').toLowerCase();
        var cardStatus = (card.getAttribute('data-status') || '').toLowerCase();

        var matchQ = !q || cardSearch.indexOf(q) !== -1;
        var matchD = !d || cardDomain.indexOf(d) !== -1;
        var matchE = !e || cardExp.indexOf(e) !== -1;
        var matchS = !s || cardStatus === s;

        card.style.display = (matchQ && matchD && matchE && matchS) ? '' : 'none';
      });
    }

    if (search) search.addEventListener('input', filterTalent);
    if (domain) domain.addEventListener('change', filterTalent);
    if (exp) exp.addEventListener('change', filterTalent);
    if (status) status.addEventListener('change', filterTalent);
    if (clear) {
      clear.addEventListener('click', function() {
        if (search) search.value = '';
        if (domain) domain.value = '';
        if (exp) exp.value = '';
        if (status) status.value = '';
        filterTalent();
      });
    }
  })();
  function applyPreset(candId, step, label, note) {
    var stepEl = document.getElementById('step-' + candId);
    var labelEl = document.getElementById('label-' + candId);
    var noteEl = document.getElementById('note-' + candId);
    if (stepEl) stepEl.value = step;
    if (labelEl) labelEl.value = label;
    if (noteEl) noteEl.value = note;
  }

  // Multi-Criteria Live Filtering System
  (function () {
    var searchInput = document.getElementById('liveSearchInput');
    var filterReq = document.getElementById('filterRequisition');
    var filterPlant = document.getElementById('filterPlant');
    var filterExp = document.getElementById('filterExp');
    var clearBtn = document.getElementById('btnClearFilters');
    var stageTabs = document.querySelectorAll('.filter-tab');
    var currentStage = 'all';

    function runFilter() {
      var query = (searchInput.value || '').trim().toLowerCase();
      var reqVal = (filterReq.value || '').trim().toLowerCase();
      var plantVal = (filterPlant.value || '').trim().toLowerCase();
      var expVal = (filterExp.value || '').trim().toLowerCase();

      var cards = document.querySelectorAll('#candidatesStream .cand-card');
      var visibleCount = 0;

      cards.forEach(function (card) {
        var cardStage = card.getAttribute('data-stage') || '';
        var cardJob = card.getAttribute('data-job') || '';
        var cardPlant = card.getAttribute('data-plant') || '';
        var cardExp = card.getAttribute('data-exp') || '';
        var cardSearch = card.getAttribute('data-search') || '';

        var matchStage = (currentStage === 'all') || (cardStage === currentStage);
        var matchReq = !reqVal || cardJob.includes(reqVal);
        var matchPlant = !plantVal || cardPlant.includes(plantVal);
        var matchExp = !expVal || (cardExp === expVal);
        var matchQuery = !query || cardSearch.includes(query);

        if (matchStage && matchReq && matchPlant && matchExp && matchQuery) {
          card.style.display = '';
          visibleCount++;
        } else {
          card.style.display = 'none';
        }
      });
    }

    if (searchInput) searchInput.addEventListener('input', runFilter);
    if (filterReq) filterReq.addEventListener('change', runFilter);
    if (filterPlant) filterPlant.addEventListener('change', runFilter);
    if (filterExp) filterExp.addEventListener('change', runFilter);

    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        if (searchInput) searchInput.value = '';
        if (filterReq) filterReq.value = '';
        if (filterPlant) filterPlant.value = '';
        if (filterExp) filterExp.value = '';
        currentStage = 'all';
        stageTabs.forEach(function (t) { t.classList.toggle('active', t.getAttribute('data-filter') === 'all'); });
        runFilter();
      });
    }

    stageTabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        stageTabs.forEach(function (t) { t.classList.remove('active'); });
        this.classList.add('active');
        currentStage = this.getAttribute('data-filter');
        runFilter();
      });
    });

    // Bulk selection handler
    var bulkBar = document.getElementById('bulkActionForm');
    var bulkCount = document.getElementById('bulkSelectedCount');
    var checkboxes = document.querySelectorAll('.cand-checkbox');
    var selectAllBtn = document.getElementById('btnSelectAll');

    function updateBulkBar() {
      var selected = document.querySelectorAll('.cand-checkbox:checked').length;
      if (bulkCount) bulkCount.textContent = selected;
      if (bulkBar) bulkBar.style.display = selected > 0 ? 'flex' : 'none';
    }

    checkboxes.forEach(function (cb) {
      cb.addEventListener('change', updateBulkBar);
    });

    if (selectAllBtn) {
      selectAllBtn.addEventListener('click', function () {
        var anyUnchecked = false;
        checkboxes.forEach(function (cb) {
          if (cb.closest('.cand-card').style.display !== 'none' && !cb.checked) {
            anyUnchecked = true;
          }
        });
        checkboxes.forEach(function (cb) {
          if (cb.closest('.cand-card').style.display !== 'none') {
            cb.checked = anyUnchecked;
          }
        });
        selectAllBtn.textContent = anyUnchecked ? 'Deselect All' : 'Select All';
        updateBulkBar();
      });
    }

    // Run initial filter in case ?job= was passed
    runFilter();
  })();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
