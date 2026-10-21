<?php
/**
 * Vasudha Pharma Chem Limited — Talent Community Network Registration API
 * Receives prospective candidate profile registrations for future requisitions,
 * securely stores records in staff/data/talent_pool.json,
 * and notifies HR and the candidate via email.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/staff/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

// Read raw JSON or form-data
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data)) {
    $data = $_POST;
}

$name    = str_replace(["\r", "\n"], '', trim($data['name'] ?? ($data['tName'] ?? '')));
$email   = filter_var(trim($data['email'] ?? ($data['tEmail'] ?? '')), FILTER_VALIDATE_EMAIL);
$phone   = str_replace(["\r", "\n"], '', trim($data['phone'] ?? ($data['tPhone'] ?? '')));
$domainKey = str_replace(["\r", "\n"], '', trim($data['domain'] ?? ($data['tDomain'] ?? '')));
$expKey    = str_replace(["\r", "\n"], '', trim($data['experience'] ?? ($data['tExp'] ?? 'fresher')));
$locKey    = str_replace(["\r", "\n"], '', trim($data['location'] ?? ($data['tLoc'] ?? 'any')));
$summary   = trim($data['summary'] ?? ($data['tSummary'] ?? ''));

if (!$name || !$email) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Full name and a valid corporate/personal email address are required.']);
    exit;
}

// Domain dictionary
$DOMAIN_LABELS = [
    'production' => 'API & Commercial Manufacturing',
    'rnd'        => 'Chemical R&D / Process Chemistry',
    'qc'         => 'Quality Control (HPLC / GC-MS)',
    'qa'         => 'Quality Assurance & Compliance',
    'pellets'    => 'Pellets, MUPS & Formulations',
    'regulatory' => 'Regulatory Affairs & IPR',
    'ehs'        => 'EHS, Safety & Engineering',
    'commercial' => 'Supply Chain & Commercial',
];

$EXP_LABELS = [
    'fresher' => 'Fresher / Graduate Trainee (< 1 year)',
    '1-3'     => '1 to 3 Years',
    '4-7'     => '4 to 7 Years',
    '8-12'    => '8 to 12 Years',
    '12+'     => '12+ Years (Leadership)',
];

$LOC_LABELS = [
    'any'       => 'Flexible / Open to Both',
    'hyderabad' => 'Hyderabad (R&D / Unit-1 & 2)',
    'vizag'     => 'Visakhapatnam (JNPC Plant Units)',
];

$domainLabel = $DOMAIN_LABELS[$domainKey] ?? ($domainKey ?: 'General Pharmaceutical');
$expLabel    = $EXP_LABELS[$expKey] ?? ($expKey ?: 'Unspecified Experience');
$locLabel    = $LOC_LABELS[$locKey] ?? ($locKey ?: 'Flexible');

// Load talent pool data
$pool = staff_load_talent_pool();
$existingIds = array_column($pool, 'id');

// Generate high-entropy Talent Network ID (VP-TN-2026-XXXXXX)
$tnId = '';
for ($i = 0; $i < 50; $i++) {
    $cand = 'VP-TN-2026-' . strtoupper(bin2hex(random_bytes(3)));
    if (!in_array($cand, $existingIds, true)) {
        $tnId = $cand;
        break;
    }
}
if (!$tnId) {
    $tnId = 'VP-TN-2026-' . strtoupper(bin2hex(random_bytes(4)));
}

$record = [
    'id'          => $tnId,
    'date'        => date('d M Y, H:i'),
    'timestamp'   => time(),
    'name'        => $name,
    'email'       => $email,
    'phone'       => $phone,
    'domain_key'  => $domainKey,
    'domain'      => $domainLabel,
    'exp_key'     => $expKey,
    'experience'  => $expLabel,
    'loc_key'     => $locKey,
    'location'    => $locLabel,
    'summary'     => $summary,
    'status'      => 'Available', // Available, Contacted, Converted, Archived
    'notes'       => '',
    'created_at'  => date('Y-m-d H:i:s'),
    'updated_at'  => date('Y-m-d H:i:s'),
    'ip'          => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
];

array_unshift($pool, $record);
staff_save_talent_pool($pool);

// 1. Send Notification Email to HR & Wisdom
$recipients = [
    'wisdom@vasudhapharma.com',
    'saikrishna@zailabs.co.in'
];
$subject = "[Vasudha Talent Network] New Profile: {$name} — {$domainLabel} ({$expLabel})";
$serverHost = $_SERVER['SERVER_NAME'] ?? ($_SERVER['HTTP_HOST'] ?? 'vasudhapharma.com');
$mailDomain = (strpos($serverHost, 'mytemp.website') !== false || empty($serverHost)) ? 'vasudhapharma.com' : $serverHost;
$staffPortalUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'vasudhapharma.com') . '/staff/candidates.php?tab=talent';

$htmlBody = '<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; background: #f8fafc; margin: 0; padding: 24px; color: #0f172a; }
    .card { max-width: 580px; margin: 0 auto; background: #ffffff; border-radius: 14px; border: 1px solid #e2e8f0; padding: 28px 24px; box-shadow: 0 4px 18px rgba(0,0,0,0.06); }
    .header { border-bottom: 2px solid #0E8F6C; padding-bottom: 16px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
    .title { font-size: 18px; font-weight: 800; color: #096B51; }
    .badge { background: #ECFDF5; color: #0E8F6C; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 13px; font-family: monospace; }
    .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    .meta-table td { padding: 8px 10px; font-size: 13px; border-bottom: 1px solid #f1f5f9; }
    .meta-table td.label { font-weight: 600; color: #64748b; width: 35%; }
    .meta-table td.val { color: #0f172a; }
    .msg-box { background: #f8fafc; border-left: 4px solid #0E8F6C; border-radius: 6px; padding: 14px; font-size: 13.5px; line-height: 1.6; color: #1e293b; white-space: pre-wrap; margin: 16px 0; }
    .footer { font-size: 12px; color: #94a3b8; text-align: center; margin-top: 24px; padding-top: 16px; border-top: 1px solid #e2e8f0; }
  </style>
</head>
<body>
  <div class="card">
    <div class="header">
      <div class="title">New Talent Network Registration</div>
      <div class="badge">' . htmlspecialchars($tnId) . '</div>
    </div>
    <table class="meta-table">
      <tr><td class="label">Candidate Name:</td><td class="val"><strong>' . htmlspecialchars($name) . '</strong></td></tr>
      <tr><td class="label">Email:</td><td class="val"><a href="mailto:' . htmlspecialchars($email) . '" style="color:#0E8F6C; font-weight:600;">' . htmlspecialchars($email) . '</a></td></tr>
      <tr><td class="label">Phone / WhatsApp:</td><td class="val">' . htmlspecialchars($phone ?: 'Not provided') . '</td></tr>
      <tr><td class="label">Primary Domain:</td><td class="val"><strong style="color:#096B51;">' . htmlspecialchars($domainLabel) . '</strong></td></tr>
      <tr><td class="label">Experience Level:</td><td class="val">' . htmlspecialchars($expLabel) . '</td></tr>
      <tr><td class="label">Preferred Location:</td><td class="val">' . htmlspecialchars($locLabel) . '</td></tr>
      <tr><td class="label">Registration Date:</td><td class="val">' . htmlspecialchars($record['date']) . '</td></tr>
    </table>';

if ($summary) {
    $htmlBody .= '<div style="font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px;">Profile Summary &amp; Credentials:</div>
    <div class="msg-box">' . htmlspecialchars($summary) . '</div>';
}

$htmlBody .= '<div style="text-align: center; margin: 24px 0;">
      <a href="' . htmlspecialchars($staffPortalUrl) . '" style="background: #0E8F6C; color: #ffffff; padding: 10px 20px; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 13px; display: inline-block;">
        View in Talent Community Pool &rarr;
      </a>
    </div>
    <div class="footer">Vasudha Pharma Chem Limited &bull; Talent Acquisition &amp; Careers Community</div>
  </div>
</body>
</html>';

$headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
    'From: Vasudha Talent Network <noreply@' . $mailDomain . '>',
    'Reply-To: ' . $email,
    'Auto-Submitted: auto-generated',
    'X-Auto-Response-Suppress: All',
    'X-Mailer: VasudhaDesk/1.0'
];
$headersStr = implode("\r\n", $headers);

foreach ($recipients as $toAddr) {
    @mail($toAddr, $subject, $htmlBody, $headersStr);
}

// 2. Send Automated Welcome Email to Candidate
if ($email) {
    $candSubject = "[Vasudha Pharma] Welcome to the Talent Network — Member ID: {$tnId}";
    $candBody = '<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; background: #f4fbf7; margin: 0; padding: 24px; color: #0f172a; }
    .card { max-width: 580px; margin: 0 auto; background: #ffffff; border-radius: 14px; border: 1px solid #e2e8f0; padding: 32px 28px; box-shadow: 0 4px 18px rgba(0,0,0,0.06); }
    .header { border-bottom: 2px solid #0E8F6C; padding-bottom: 16px; margin-bottom: 22px; text-align: center; }
    .company { font-size: 20px; font-weight: 800; color: #096B51; letter-spacing: 0.5px; }
    .subhead { font-size: 12px; color: #0E8F6C; margin-top: 2px; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 600; }
    .id-box { background: #ECFDF5; border: 2px dashed #059669; border-radius: 10px; padding: 18px 20px; text-align: center; margin: 24px 0; }
    .id-label { font-size: 11px; font-weight: 700; color: #065F46; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 4px; }
    .id-value { font-size: 24px; font-weight: 800; color: #096B51; font-family: monospace; letter-spacing: 2px; }
    .footer { font-size: 12px; color: #94a3b8; text-align: center; margin-top: 28px; padding-top: 18px; border-top: 1px solid #e2e8f0; }
  </style>
</head>
<body>
  <div class="card">
    <div class="header">
      <div class="company">Vasudha Pharma Chem Limited</div>
      <div class="subhead">Global Talent Community</div>
    </div>
    <p style="font-size: 15px; line-height: 1.6; color: #334155;">
      Dear <strong>' . htmlspecialchars($name) . '</strong>,
    </p>
    <p style="font-size: 14px; line-height: 1.6; color: #475569;">
      Thank you for registering your profile with the <strong>Vasudha Pharma Talent Network</strong>. Your credentials have been cataloged in our proactive pharmaceutical talent bench under <strong>' . htmlspecialchars($domainLabel) . '</strong>.
    </p>
    <div class="id-box">
      <div class="id-label">Your Talent Network Membership ID</div>
      <div class="id-value">' . htmlspecialchars($tnId) . '</div>
    </div>
    <p style="font-size: 13.5px; line-height: 1.6; color: #475569;">
      Our Talent Acquisition team regularly reviews this bench against emerging positions across our R&amp;D center in Hyderabad and our API / Pellets manufacturing plants in Visakhapatnam. When a role aligned with your profile opens up, our recruitment partners will contact you directly.
    </p>
    <div class="footer">
      Vasudha Pharma Chem Limited &bull; Hyderabad &bull; Visakhapatnam<br>
      Contributing to affordable health care...
    </div>
  </div>
</body>
</html>';

    $candHeaders = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: Vasudha Talent Acquisition <noreply@' . $mailDomain . '>',
        'Reply-To: wisdom@vasudhapharma.com',
        'Auto-Submitted: auto-generated',
        'X-Mailer: VasudhaDesk/1.0'
    ];
    @mail($email, $candSubject, $candBody, implode("\r\n", $candHeaders));
}

echo json_encode([
    'ok'     => true,
    'id'     => $tnId,
    'name'   => $name,
    'domain' => $domainLabel
]);
exit;
