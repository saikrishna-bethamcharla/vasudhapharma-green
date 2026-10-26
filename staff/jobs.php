<?php
require __DIR__ . '/includes/auth.php';
staff_require_desk('careers');
$u = staff_user();

$data = staff_load_jobs();
$apps = staff_load_applications();
$talentPool = staff_load_talent_pool();

// Pre-compute application counts by Job ID for quick badge display
$applicantCounts = [];
foreach ($apps as $a) {
  $jid = strtoupper(trim($a['job_id'] ?? ''));
  if ($jid) {
    $applicantCounts[$jid] = ($applicantCounts[$jid] ?? 0) + 1;
  }
}

$edit = null;
if (isset($_GET['edit'])) {
  foreach ($data['jobs'] as $j) {
    if ($j['id'] === $_GET['edit']) {
      $edit = $j;
      break;
    }
  }
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  staff_verify_csrf();
  $action = $_POST['action'] ?? '';

  if ($action === 'save') {
    $id = trim($_POST['id'] ?? '');
    if (!$id) {
      $id = 'VP-REQ-' . date('Y') . '-' . mt_rand(100, 999);
    }
    $skills = array_filter(array_map('trim', explode(',', $_POST['skills'] ?? '')));

    $job = [
      'id' => $id,
      'title' => trim($_POST['title'] ?? ''),
      'department' => trim($_POST['department'] ?? ''),
      'location' => trim($_POST['location'] ?? 'Hyderabad, Telangana, IN'),
      'type' => trim($_POST['type'] ?? 'Full Time'),
      'experience' => trim($_POST['experience'] ?? ''),
      'vacancies' => max(1, intval($_POST['vacancies'] ?? 1)),
      'posted' => trim($_POST['posted'] ?? date('d M Y')),
      'status' => in_array($_POST['status'] ?? 'open', ['open', 'closed'], true) ? $_POST['status'] : 'open',
      'skills' => array_values($skills),
      'role' => trim($_POST['role'] ?? ''),
      'duties' => trim($_POST['duties'] ?? ''),
      'profile' => trim($_POST['profile'] ?? ''),
      'compensation' => trim($_POST['compensation'] ?? 'Industry standard, commensurate with experience'),
      'updated_at' => date('Y-m-d H:i:s'),
    ];

    $found = false;
    foreach ($data['jobs'] as $i => $j) {
      if ($j['id'] === $job['id']) {
        $data['jobs'][$i] = array_merge($j, $job);
        $found = true;
        break;
      }
    }
    if (!$found) {
      array_unshift($data['jobs'], $job);
    }
    staff_save_jobs($data);
    header('Location: jobs.php?saved=1');
    exit;
  }

  if ($action === 'clone') {
    $id = $_POST['id'] ?? '';
    foreach ($data['jobs'] as $j) {
      if ($j['id'] === $id) {
        $copy = $j;
        $copy['id'] = $j['id'] . '-COPY-' . mt_rand(10, 99);
        $copy['title'] = $j['title'] . ' (Copy)';
        $copy['status'] = 'closed';
        $copy['posted'] = date('d M Y');
        $data['jobs'][] = $copy;
        staff_save_jobs($data);
        header('Location: jobs.php?saved=2');
        exit;
      }
    }
  }

  if ($action === 'close' || $action === 'open') {
    $id = $_POST['id'] ?? '';
    foreach ($data['jobs'] as $i => $j) {
      if ($j['id'] === $id) {
        $data['jobs'][$i]['status'] = $action === 'close' ? 'closed' : 'open';
      }
    }
    staff_save_jobs($data);
    header('Location: jobs.php?saved=1');
    exit;
  }

  if ($action === 'delete') {
    $id = $_POST['id'] ?? '';
    $data['jobs'] = array_values(array_filter($data['jobs'], function ($j) use ($id) {
      return $j['id'] !== $id;
    }));
    staff_save_jobs($data);
    header('Location: jobs.php?saved=1');
    exit;
  }
}

if (isset($_GET['saved'])) {
  $msg = $_GET['saved'] === '2' ? 'Requisition cloned as a new draft.' : 'Requisition successfully saved to jobs.json.';
}

$staff_title = 'Careers';
require __DIR__ . '/includes/header.php';

$openCt = count(array_filter($data['jobs'], function($j){ return ($j['status'] ?? 'open') === 'open'; }));
$totalJobs = count($data['jobs'] ?? []);
?>

<?php if ($msg): ?>
  <div class="card ok" style="margin-bottom:20px;">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
    <span><?php echo htmlspecialchars($msg); ?></span>
  </div>
<?php endif; ?>

<!-- Executive Desk Header Banner -->
<div class="desk-hero-banner">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; position:relative; z-index:1;">
    <div>
      <div style="text-transform:uppercase; font-size:11px; letter-spacing:0.1em; opacity:0.85; font-weight:700; margin-bottom:6px;">
        Talent Acquisition &bull; Careers Operations Desk
      </div>
      <h1 style="margin:0 0 4px; font-size:24px; color:#FFFFFF;">Careers &amp; Requisition Management</h1>
      <p style="margin:0; opacity:0.9; font-size:13px; color:#E2E8F0;">
        <?php echo $openCt; ?> published positions &bull; <?php echo count($apps); ?> active applications &bull; <?php echo count($talentPool); ?> prospective talent leads
      </p>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
      <a href="jobs.php#jobForm" class="btn" style="background:#FFFFFF; color:var(--sp-primary-dark); font-weight:700; font-size:12.5px; box-shadow:0 4px 12px rgba(0,0,0,0.15);" onclick="document.getElementById('jobIdInput').focus();">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Post Requisition
      </a>
      <a href="../careers.html#openings" target="_blank" rel="noopener" class="btn" style="background:rgba(255,255,255,0.14); color:#FFFFFF; border:1px solid rgba(255,255,255,0.25); font-weight:600; font-size:12.5px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/></svg>
        Preview Public Careers
      </a>
    </div>
  </div>
</div>

<!-- Modern Segmented Sub-Desk Navigation Tabs -->
<div style="display:inline-flex; background:#E2E8F0; padding:4px; border-radius:10px; gap:4px; margin-bottom:22px; flex-wrap:wrap;">
  <a href="jobs.php" class="btn" style="background:var(--sp-primary); color:#FFFFFF; font-weight:600; font-size:12.5px; border-radius:8px; padding:7px 14px; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
    <span>Job Requisitions</span>
    <span style="background:rgba(255,255,255,0.25); padding:1px 7px; border-radius:10px; font-size:11px; margin-left:4px;"><?php echo $totalJobs; ?></span>
  </a>
  <a href="candidates.php" class="btn" style="background:transparent; color:#475569; font-weight:500; font-size:12.5px; border-radius:8px; padding:7px 14px; border:none; box-shadow:none;">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    <span>Candidate Applications</span>
    <span style="background:#CBD5E1; color:#334155; padding:1px 7px; border-radius:10px; font-size:11px; margin-left:4px;"><?php echo count($apps); ?></span>
  </a>
  <a href="candidates.php?tab=talent" class="btn" style="background:transparent; color:#475569; font-weight:500; font-size:12.5px; border-radius:8px; padding:7px 14px; border:none; box-shadow:none;">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
    <span>Talent Community Pool</span>
    <span style="background:#CBD5E1; color:#334155; padding:1px 7px; border-radius:10px; font-size:11px; margin-left:4px;"><?php echo count($talentPool); ?></span>
  </a>
</div>

<!-- Requisitions List Card -->
<div class="card">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; flex-wrap:wrap; gap:12px;">
    <div>
      <h2 style="margin:0; font-size:17px;">Job Requisitions Directory (<?php echo $totalJobs; ?>)</h2>
      <p style="margin:2px 0 0; font-size:12.5px; color:var(--sp-text-muted);">
        <?php echo $openCt; ?> active positions published live on the careers portal.
      </p>
    </div>
    <div class="table-search-bar" style="margin:0;">
      <div style="position:relative; width:100%; max-width:320px;">
        <input type="text" placeholder="Filter positions..." data-search-target="#jobsTable">
      </div>
    </div>
  </div>

  <div class="table-container">
    <table id="jobsTable">
      <thead>
        <tr>
          <th>Requisition ID</th>
          <th>Position Title &amp; Skills</th>
          <th>Department</th>
          <th>Location</th>
          <th>Type / Experience</th>
          <th>Status</th>
          <th style="text-align:right">Management Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($data['jobs'] as $j): 
        $isOpen = ($j['status'] ?? 'open') !== 'closed';
      ?>
        <tr>
          <td><strong style="color:var(--sp-primary); font-family:'JetBrains Mono', monospace; font-size:12px;"><?php echo htmlspecialchars($j['id']); ?></strong></td>
          <td>
            <div style="font-weight:600; color:var(--sp-text-main); font-size:13.5px;"><?php echo htmlspecialchars($j['title']); ?></div>
            <?php 
              $cCount = $applicantCounts[strtoupper($j['id'])] ?? 0;
              if ($cCount > 0): 
            ?>
              <a href="candidates.php?job=<?php echo urlencode($j['id']); ?>" style="display:inline-flex; align-items:center; gap:4px; font-size:11px; background:#ECFDF5; color:#065F46; border:1px solid #A7F3D0; padding:2px 8px; border-radius:12px; text-decoration:none; margin-top:4px; font-weight:600;" title="View <?php echo $cCount; ?> candidate applications for this position">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                <?php echo $cCount; ?> Applicants &rarr;
              </a>
            <?php endif; ?>
            <?php if (!empty($j['skills'])): ?>
              <div style="font-size:11.5px; color:var(--sp-text-muted); margin-top:4px;">
                <?php echo htmlspecialchars(implode(', ', array_slice($j['skills'], 0, 4))); ?>
                <?php if (count($j['skills']) > 4) echo ' <span style="color:#94A3B8;">+' . (count($j['skills']) - 4) . '</span>'; ?>
              </div>
            <?php endif; ?>
          </td>
          <td><span style="color:#475569; font-weight:500; font-size:12.5px;"><?php echo htmlspecialchars($j['department'] ?? '—'); ?></span></td>
          <td style="font-size:12.5px; color:#475569;"><?php echo htmlspecialchars($j['location'] ?? 'Hyderabad'); ?></td>
          <td>
            <div style="font-size:12.5px; font-weight:500;"><?php echo htmlspecialchars($j['type'] ?? 'Full Time'); ?></div>
            <div style="font-size:11.5px; color:#94A3B8;"><?php echo htmlspecialchars($j['experience'] ?? 'Relevant Exp'); ?></div>
          </td>
          <td>
            <span class="<?php echo $isOpen ? 'badge-open' : 'badge-closed'; ?>">
              <?php echo $isOpen ? '● Live' : '○ Closed'; ?>
            </span>
          </td>
          <td style="text-align:right; white-space:nowrap;">
            <a class="btn btn-ghost" style="padding:5px 10px; font-size:11.5px;" href="jobs.php?edit=<?php echo urlencode($j['id']); ?>#jobForm" title="Edit Requisition">Edit</a>
            
            <form method="post" style="display:inline">
              <?php echo staff_csrf_field(); ?>
              <input type="hidden" name="id" value="<?php echo htmlspecialchars($j['id']); ?>">
              <button class="btn btn-ghost" style="padding:5px 10px; font-size:11.5px;" name="action" value="clone" title="Duplicate Requisition">Clone</button>
              <?php if (!$isOpen): ?>
                <button class="btn btn-ghost" style="padding:5px 10px; font-size:11.5px; color:#059669; border-color:#A7F3D0;" name="action" value="open" title="Publish">Publish</button>
              <?php else: ?>
                <button class="btn btn-ghost" style="padding:5px 10px; font-size:11.5px; color:#64748B;" name="action" value="close" title="Close Requisition">Close</button>
              <?php endif; ?>
              <button class="btn btn-danger" style="padding:5px 10px; font-size:11.5px;" name="action" value="delete" onclick="return confirm('Permanently delete requisition <?php echo htmlspecialchars($j['id']); ?>?');" title="Delete">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$data['jobs']): ?>
        <tr><td colspan="7" style="text-align:center; color:#94A3B8; padding:36px">No job requisitions recorded yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Requisition Form Card -->
<div class="card" id="jobForm">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
    <div>
      <h2 style="margin:0; font-size:17px;"><?php echo $edit ? ('Edit Position: ' . htmlspecialchars($edit['id'])) : 'Create New Job Requisition'; ?></h2>
      <p style="margin:2px 0 0; font-size:12.5px; color:var(--sp-text-muted);">Fill in role specifications, requirements, and publishing status.</p>
    </div>
    <?php if ($edit): ?>
      <a class="btn btn-ghost" href="jobs.php" style="font-size:12px">Cancel Edit</a>
    <?php endif; ?>
  </div>

  <form method="post">
    <?php echo staff_csrf_field(); ?>
    <input type="hidden" name="action" value="save">
    
    <div class="row">
      <div>
        <label>Job Code / Reference ID</label>
        <input id="jobIdInput" name="id" required placeholder="e.g. VP-QC-004" value="<?php echo htmlspecialchars($edit['id'] ?? ''); ?>">
      </div>
      <div>
        <label>Publishing Status</label>
        <select name="status">
          <option value="open" <?php echo (($edit['status'] ?? 'open')==='open')?'selected':''; ?>>Live &amp; Open (Published on Careers Portal)</option>
          <option value="closed" <?php echo (($edit['status'] ?? '')==='closed')?'selected':''; ?>>Closed (Archived / Hidden from Candidates)</option>
        </select>
      </div>
    </div>
    
    <div style="margin-top:14px;">
      <label>Position Title</label>
      <input name="title" required placeholder="e.g. Quality Assurance Executive — Method Validation" value="<?php echo htmlspecialchars($edit['title'] ?? ''); ?>">
    </div>

    <div class="row-3" style="margin-top:14px;">
      <div>
        <label>Department</label>
        <select name="department">
          <?php
            $depts = ["Quality","Manufacturing","R&D","EHS","Marketing","Foundation","Corporate Governance","Careers","Supply Chain","Regulatory Affairs","Other"];
            $cur = $edit["department"] ?? "";
            echo "<option value=\"\">Select Department...</option>";
            foreach ($depts as $d) {
              $sel = ($cur === $d) ? " selected" : "";
              echo "<option value=\"".htmlspecialchars($d)."\"".$sel.">".htmlspecialchars($d)."</option>";
            }
          ?>
        </select>
      </div>
      <div>
        <label>Work Location</label>
        <input name="location" placeholder="e.g. Hyderabad, Telangana, IN" value="<?php echo htmlspecialchars($edit['location'] ?? 'Hyderabad, Telangana, IN'); ?>">
      </div>
      <div>
        <label>Employment Type</label>
        <select name="type">
          <?php
            $types = ["Full Time", "Contract", "Internship", "Part Time"];
            $curType = $edit["type"] ?? "Full Time";
            foreach ($types as $t) {
              $sel = ($curType === $t) ? " selected" : "";
              echo "<option value=\"".htmlspecialchars($t)."\"".$sel.">".htmlspecialchars($t)."</option>";
            }
          ?>
        </select>
      </div>
    </div>

    <div class="row-3" style="margin-top:14px;">
      <div>
        <label>Experience Required</label>
        <input name="experience" placeholder="e.g. 2–5 Years or Freshers" value="<?php echo htmlspecialchars($edit['experience'] ?? ''); ?>">
      </div>
      <div>
        <label>Vacancies Count</label>
        <input name="vacancies" type="number" min="1" placeholder="e.g. 1" value="<?php echo htmlspecialchars($edit['vacancies'] ?? '1'); ?>">
      </div>
      <div>
        <label>Posting Date Label</label>
        <input name="posted" placeholder="e.g. Oct 2026 or Immediate" value="<?php echo htmlspecialchars($edit['posted'] ?? date('d M Y')); ?>">
      </div>
    </div>

    <div style="margin-top:14px;">
      <label>Skills &amp; Keywords (comma-separated)</label>
      <input name="skills" placeholder="e.g. QC, GMP, HPLC, Analysis, B.Pharm, M.Sc" value="<?php echo htmlspecialchars(implode(', ', $edit['skills'] ?? [])); ?>">
    </div>

    <div style="margin-top:14px;">
      <label>Role Purpose &amp; Overview</label>
      <textarea name="role" placeholder="High-level summary of role purpose and operational scope..."><?php echo htmlspecialchars($edit['role'] ?? ''); ?></textarea>
    </div>
    
    <div style="margin-top:14px;">
      <label>Key Duties &amp; Operational Responsibilities</label>
      <textarea name="duties" placeholder="Detailed day-to-day responsibilities, shift requirements, and compliance standards..."><?php echo htmlspecialchars($edit['duties'] ?? ''); ?></textarea>
    </div>
    
    <div style="margin-top:14px;">
      <label>Candidate Profile &amp; Educational Qualification</label>
      <textarea name="profile" placeholder="Required degrees, industry experience, certifications, and competencies..."><?php echo htmlspecialchars($edit['profile'] ?? ''); ?></textarea>
    </div>
    
    <div style="margin-top:24px; display:flex; gap:10px;">
      <button class="btn btn-primary" type="submit">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
        <span>Save Requisition</span>
      </button>
      <a class="btn btn-ghost" href="jobs.php">Cancel</a>
    </div>
  </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
