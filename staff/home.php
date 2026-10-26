<?php
require __DIR__ . '/includes/auth.php';
staff_require_login();
$u = staff_user();
if (($u['role'] ?? '') !== 'admin') {
  header('Location: ' . staff_default_desk_url($u));
  exit;
}
$stats = staff_portal_stats();
$desks = staff_desks();
$isAdmin = ($u['role'] ?? '') === 'admin';

// Load jobs data for counts
$jobsData = staff_load_jobs();

// Check storage files health
$storageFiles = [
  'Careers & Requisitions' => ['path' => staff_jobs_file(), 'file' => 'jobs.json', 'desc' => 'Active job postings & applicant requirements'],
  'Commercial Inquiries' => ['path' => staff_enquiries_file(), 'file' => 'staff/data/enquiries.json', 'desc' => 'Global B2B product inquiries & quotation leads'],
  'Website Contact Leads' => ['path' => staff_contacts_file(), 'file' => 'staff/data/contacts.json', 'desc' => 'General contact form inquiries & customer leads'],
  'Talent Network Pool' => ['path' => staff_talent_pool_file(), 'file' => 'staff/data/talent_pool.json', 'desc' => 'Prospective candidates talent community database'],
  'News & Conferences' => ['path' => dirname(staff_root()) . '/news-events.json', 'file' => 'news-events.json', 'desc' => 'Press releases, company milestones & event schedules'],
  'Foundation Galleries' => ['path' => dirname(staff_root()) . '/foundation-galleries.json', 'file' => 'foundation-galleries.json', 'desc' => 'CSR activities & marquee image streams'],
  'Staff Users & Credentials' => ['path' => staff_users_file(), 'file' => 'staff/data/users.json', 'desc' => 'Staff account credentials & role permissions'],
  'Corporate Governance Documents' => ['path' => staff_documents_file(), 'file' => 'documents.json', 'desc' => 'Statutory certificates, policies & regulatory filings'],
  'Board of Directors' => ['path' => staff_directors_file(), 'file' => 'directors.json', 'desc' => 'Board of directors & leadership executive profiles'],
];

$staff_title = 'Home';
require __DIR__ . '/includes/header.php';
?>

<!-- Executive Operations Header Banner -->
<div class="desk-hero-banner">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:20px; position:relative; z-index:1;">
    <div>
      <div style="text-transform:uppercase; font-size:11px; letter-spacing:0.12em; opacity:0.85; font-weight:700; margin-bottom:8px; display:flex; align-items:center; gap:8px;">
        <span style="display:inline-block; width:7px; height:7px; background:#10B981; border-radius:50%; box-shadow:0 0 0 2px rgba(16,185,129,0.3);"></span>
        <span>Executive Operations Command &bull; Vasudha Pharma</span>
      </div>
      <h1 style="margin:0 0 6px; font-size:26px; color:#FFFFFF; letter-spacing:-0.03em;">Welcome back, <?php echo htmlspecialchars($u['name']); ?></h1>
      <p style="margin:0; opacity:0.9; font-size:13px; color:#E2E8F0;">
        System Administrator &bull; All 7 operational desks active &bull; <?php echo date('l, d F Y'); ?>
      </p>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
      <a href="users.php?tab=audit" class="btn" style="background:rgba(255,255,255,0.14); color:#FFFFFF; border:1px solid rgba(255,255,255,0.22); font-weight:600; font-size:12.5px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        Audit Log
      </a>
      <a href="users.php?tab=users" class="btn" style="background:rgba(255,255,255,0.14); color:#FFFFFF; border:1px solid rgba(255,255,255,0.22); font-weight:600; font-size:12.5px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        User Accounts
      </a>
      <a href="../careers.html" target="_blank" rel="noopener" class="btn" style="background:#FFFFFF; color:var(--sp-primary-dark); font-weight:700; font-size:12.5px; box-shadow:0 4px 12px rgba(0,0,0,0.15);">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
        Live Website
      </a>
    </div>
  </div>
</div>

<!-- High-Impact KPI Performance Strip -->
<div class="row-4" style="margin-bottom:24px;">
  <div class="stat-card">
    <div class="stat-label">Candidate Applications</div>
    <div class="stat-num"><?php echo $stats['apps_total'] ?? 0; ?></div>
    <div class="stat-sub"><?php echo ($stats['apps_screening'] ?? 0); ?> in review &bull; <?php echo ($stats['apps_interview'] ?? 0); ?> scheduled interviews</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Active Job Requisitions</div>
    <div class="stat-num"><?php echo $stats['jobs_open']; ?></div>
    <div class="stat-sub"><?php echo $stats['jobs_total']; ?> total requisitions in careers database</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Commercial Inquiries</div>
    <div class="stat-num"><?php echo ($stats['leads_total'] ?? 0); ?></div>
    <div class="stat-sub">Product catalog &amp; RFQ customer leads</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">News &amp; Corporate Events</div>
    <div class="stat-num"><?php echo $stats['news_total']; ?></div>
    <div class="stat-sub"><?php echo $stats['events_total']; ?> scheduled international conferences</div>
  </div>
</div>

<!-- Department Operational Workspaces Grid -->
<div class="card" style="margin-bottom:24px;">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
    <div>
      <h2 style="margin:0; font-size:18px;">Executive Department Workspaces</h2>
      <p style="margin:4px 0 0; font-size:13px; color:var(--sp-text-muted);">Direct administrative access across all operational departments.</p>
    </div>
    <span style="font-size:12px; color:#065F46; background:#ECFDF5; border:1px solid #A7F3D0; padding:4px 10px; border-radius:20px; font-weight:600;">
      All Desks Unrestricted
    </span>
  </div>

  <div class="row-3" style="gap:18px;">
    <!-- Careers Desk -->
    <div style="border:1px solid var(--sp-border); border-radius:12px; padding:22px; background:#FFFFFF; display:flex; flex-direction:column; justify-content:space-between; gap:16px; box-shadow:var(--sp-shadow-xs); transition:all 0.2s ease;">
      <div>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
          <h3 style="margin:0; font-size:16px; color:var(--sp-primary); font-weight:700;">Careers Desk</h3>
          <span class="badge-open">Active</span>
        </div>
        <p style="margin:0; font-size:12.5px; color:var(--sp-text-muted); line-height:1.5;">
          Manage job vacancies, applicant review pipeline, interview scheduling, and talent community pool.
        </p>
      </div>
      <div style="display:flex; gap:8px;">
        <a href="jobs.php" class="btn btn-primary" style="flex:1; font-size:12px;">Open Desk &rarr;</a>
        <a href="candidates.php" class="btn btn-ghost" style="font-size:12px;" title="Candidates Desk">Applicants</a>
      </div>
    </div>

    <!-- Commercial Marketing Desk -->
    <div style="border:1px solid var(--sp-border); border-radius:12px; padding:22px; background:#FFFFFF; display:flex; flex-direction:column; justify-content:space-between; gap:16px; box-shadow:var(--sp-shadow-xs); transition:all 0.2s ease;">
      <div>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
          <h3 style="margin:0; font-size:16px; color:var(--sp-primary); font-weight:700;">Sales &amp; Commercial Marketing</h3>
          <span class="badge-open">Active</span>
        </div>
        <p style="margin:0; font-size:12.5px; color:var(--sp-text-muted); line-height:1.5;">
          Review customer inquiries, commercial RFQ quotation leads, export requests, and API catalog management.
        </p>
      </div>
      <div>
        <a href="marketing.php" class="btn btn-primary" style="width:100%; font-size:12px;">Open Desk &rarr;</a>
      </div>
    </div>

    <!-- Foundation CSR Desk -->
    <div style="border:1px solid var(--sp-border); border-radius:12px; padding:22px; background:#FFFFFF; display:flex; flex-direction:column; justify-content:space-between; gap:16px; box-shadow:var(--sp-shadow-xs); transition:all 0.2s ease;">
      <div>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
          <h3 style="margin:0; font-size:16px; color:var(--sp-primary); font-weight:700;">Vasudha Foundation (CSR)</h3>
          <span class="badge-open">Active</span>
        </div>
        <p style="margin:0; font-size:12.5px; color:var(--sp-text-muted); line-height:1.5;">
          Manage CSR community initiatives, public healthcare programs, and marquee banner image galleries.
        </p>
      </div>
      <div>
        <a href="foundation.php" class="btn btn-primary" style="width:100%; font-size:12px;">Open Desk &rarr;</a>
      </div>
    </div>

    <!-- Governance Desk -->
    <div style="border:1px solid var(--sp-border); border-radius:12px; padding:22px; background:#FFFFFF; display:flex; flex-direction:column; justify-content:space-between; gap:16px; box-shadow:var(--sp-shadow-xs); transition:all 0.2s ease;">
      <div>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
          <h3 style="margin:0; font-size:16px; color:var(--sp-primary); font-weight:700;">Governance Desk</h3>
          <span class="badge-open">Active</span>
        </div>
        <p style="margin:0; font-size:12.5px; color:var(--sp-text-muted); line-height:1.5;">
          Upload and maintain statutory compliance certificates, regulatory policies, ESG disclosures, and audits.
        </p>
      </div>
      <div>
        <a href="documents.php" class="btn btn-primary" style="width:100%; font-size:12px;">Open Desk &rarr;</a>
      </div>
    </div>

    <!-- Corporate Media & PR Desk -->
    <div style="border:1px solid var(--sp-border); border-radius:12px; padding:22px; background:#FFFFFF; display:flex; flex-direction:column; justify-content:space-between; gap:16px; box-shadow:var(--sp-shadow-xs); transition:all 0.2s ease;">
      <div>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
          <h3 style="margin:0; font-size:16px; color:var(--sp-primary); font-weight:700;">Corporate Media &amp; PR</h3>
          <span class="badge-open">Active</span>
        </div>
        <p style="margin:0; font-size:12.5px; color:var(--sp-text-muted); line-height:1.5;">
          Publish global pharmaceutical trade conferences, exhibition booth locations, press releases, and announcements.
        </p>
      </div>
      <div>
        <a href="news.php" class="btn btn-primary" style="width:100%; font-size:12px;">Open Desk &rarr;</a>
      </div>
    </div>

    <!-- Board & Leadership Desk -->
    <div style="border:1px solid var(--sp-border); border-radius:12px; padding:22px; background:#FFFFFF; display:flex; flex-direction:column; justify-content:space-between; gap:16px; box-shadow:var(--sp-shadow-xs); transition:all 0.2s ease;">
      <div>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
          <h3 style="margin:0; font-size:16px; color:var(--sp-primary); font-weight:700;">Board of Directors &amp; Leadership</h3>
          <span class="badge-open">Admin Only</span>
        </div>
        <p style="margin:0; font-size:12.5px; color:var(--sp-text-muted); line-height:1.5;">
          Curate the Board of Directors and Senior Management profiles, leadership designations, bios, and order.
        </p>
      </div>
      <div>
        <a href="directors.php" class="btn btn-primary" style="width:100%; font-size:12px;">Open Desk &rarr;</a>
      </div>
    </div>
  </div>
</div>

<!-- Storage Health & System Integrity -->
<div class="card">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
    <div>
      <h2 style="margin:0; font-size:17px;">System Data Storage &amp; Integrity Health</h2>
      <p style="margin:3px 0 0; font-size:12.5px; color:var(--sp-text-muted);">Real-time monitoring of all file-based JSON persistence stores.</p>
    </div>
    <span style="font-size:11.5px; font-weight:600; color:#065F46; background:#ECFDF5; border:1px solid #A7F3D0; padding:3px 10px; border-radius:20px;">
      Storage Encrypted &amp; Writable
    </span>
  </div>

  <div class="table-container">
    <table>
      <thead>
        <tr>
          <th>Data Store</th>
          <th>Physical Target</th>
          <th>Description</th>
          <th>Size</th>
          <th>Last Synchronized</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($storageFiles as $label => $info):
          $exists = is_file($info['path']);
          $writable = $exists ? is_writable($info['path']) : is_writable(dirname($info['path']));
          $size = $exists ? round(filesize($info['path']) / 1024, 1) . ' KB' : '—';
          $mtime = $exists ? date('d M Y, H:i', filemtime($info['path'])) : 'Not created yet';
        ?>
          <tr>
            <td><strong style="color:var(--sp-text-main);"><?php echo htmlspecialchars($label); ?></strong></td>
            <td><code style="background:#F8FAFC; border:1px solid #E2E8F0; padding:3px 7px; border-radius:4px; font-size:11.5px; color:#006039; font-family:'JetBrains Mono', monospace;"><?php echo htmlspecialchars($info['file']); ?></code></td>
            <td style="color:var(--sp-text-muted); font-size:12.5px;"><?php echo htmlspecialchars($info['desc']); ?></td>
            <td style="font-variant-numeric:tabular-nums; font-size:12.5px;"><?php echo $size; ?></td>
            <td style="font-size:12px; color:var(--sp-text-subtle);"><?php echo $mtime; ?></td>
            <td>
              <?php if ($exists && $writable): ?>
                <span class="badge-open">Healthy</span>
              <?php elseif ($exists && !$writable): ?>
                <span class="badge-closed" style="background:#FEF2F2; color:#991B1B; border-color:#FECACA;">Read-Only</span>
              <?php else: ?>
                <span class="badge-closed">Pending</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
