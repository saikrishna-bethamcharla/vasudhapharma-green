<?php
declare(strict_types=1);

require __DIR__ . '/includes/auth.php';
staff_require_login();

$u = staff_user();
if (!staff_can('documents', $u) && ($u['role'] ?? '') !== 'admin') {
  http_response_code(403);
  echo 'Access denied: Authorized governance or admin access required.';
  exit;
}

$file = __DIR__ . '/data/protected/Vasudha_Sustainability_Report_2025-26.pdf';
if (!is_file($file)) {
  http_response_code(404);
  echo 'Protected Sustainability Report PDF not found on server.';
  exit;
}

staff_log_audit('sustainability_report_downloaded', 'Authorized staff downloaded protected 2025-26 Sustainability Report', 'Vasudha_Sustainability_Report_2025-26.pdf', 'Governance');

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="Vasudha_Sustainability_Report_2025-26.pdf"');
header('Content-Length: ' . filesize($file));
header('Cache-Control: private, no-store, max-age=0');
readfile($file);
exit;
