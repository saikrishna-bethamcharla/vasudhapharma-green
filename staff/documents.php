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

// Helper function to sync statutory-regulations.html grid with documents.json
function staff_sync_statutory_page($docs, $siteRoot) {
  $statPath = $siteRoot . '/statutory-regulations.html';
  if (!is_file($statPath)) return;
  $c = @file_get_contents($statPath);
  if (!$c) return;

  $statDocs = array_filter($docs['documents'] ?? [], function($d) {
    return ($d['category'] ?? '') === 'statutory';
  });

  // Group by Unit
  $units = [];
  foreach ($statDocs as $d) {
    $unit = trim($d['unit'] ?? 'General Corporate');
    if (!isset($units[$unit])) $units[$unit] = [];
    $units[$unit][] = $d;
  }

  $gridHtml = '    <div class="container" id="statutoryUnitsContainer" style="max-width: 1120px;">' . "
";
  foreach ($units as $unitName => $uDocs) {
    $gridHtml .= '      <div class="stat-unit-block">' . "
";
    $gridHtml .= '        <h2 class="stat-unit-title">' . htmlspecialchars($unitName) . '</h2>' . "
";
    $gridHtml .= '        <div class="stat-pdf-grid">' . "
";
    foreach ($uDocs as $doc) {
      $f = htmlspecialchars($doc['file'] ?? '');
      $lbl = htmlspecialchars($doc['menu_label'] ?? $doc['title'] ?? 'DOCUMENT');
      $gridHtml .= '          <a class="stat-pdf-card" href="' . $f . '" target="_blank" rel="noopener">' . "
";
      $gridHtml .= '            <span class="stat-pdf-icon" aria-hidden="true">' . "
";
      $gridHtml .= '              <svg viewBox="0 0 48 56" width="44" height="52" xmlns="http://www.w3.org/2000/svg">' . "
";
      $gridHtml .= '                <path fill="#1DB88A" d="M8 0h24l16 16v36a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4V4a4 4 0 0 1 4-4z"/>' . "
";
      $gridHtml .= '                <path fill="#fff" fill-opacity=".9" d="M32 0v12a4 4 0 0 0 4 4h12L32 0z"/>' . "
";
      $gridHtml .= '                <text x="24" y="40" text-anchor="middle" fill="#fff" font-size="11" font-family="Arial,sans-serif" font-weight="700">PDF</text>' . "
";
      $gridHtml .= '              </svg>' . "
";
      $gridHtml .= '            </span>' . "
";
      $gridHtml .= '            <span class="stat-pdf-label">' . $lbl . '</span>' . "
";
      $gridHtml .= '          </a>' . "
";
    }
    $gridHtml .= '        </div>' . "
";
    $gridHtml .= '      </div>' . "
";
  }
  $gridHtml .= '    </div>';

  $pattern = '/<div class="container" id="statutoryUnitsContainer"[^>]*>([\s\S]*?)<\/div>\s*<\/section>/i';
  if (preg_match($pattern, $c)) {
    $newContent = preg_replace($pattern, $gridHtml . "
  </section>", $c);
    if ($newContent && $newContent !== $c) {
      @file_put_contents($statPath, $newContent);
    }
  }
}

// Master synchronizer: syncs Annual Returns, Policies, Sustainability links across all HTML files
function staff_sync_html_governance_menus($docs, $siteRoot) {
  // 1. Build Annual Returns submenu links
  $annualDocs = array_filter($docs['documents'] ?? [], function($d) {
    return ($d['category'] ?? '') === 'annual_return' || ($d['category'] ?? '') === 'annual_report';
  });

  usort($annualDocs, function($a, $b) {
    $yA = intval(preg_replace('/[^0-9]/', '', substr($a['year'] ?? '', 0, 4)));
    $yB = intval(preg_replace('/[^0-9]/', '', substr($b['year'] ?? '', 0, 4)));
    return $yB - $yA;
  });

  $annualMenuHtml = '';
  foreach ($annualDocs as $doc) {
    $label = trim($doc['menu_label'] ?? '') ?: trim($doc['title'] ?? '');
    if (!$label) $label = 'Annual Return ' . ($doc['year'] ?? '');
    $f = htmlspecialchars($doc['file'] ?? '');
    $y = htmlspecialchars($doc['year'] ?? '');
    $annualMenuHtml .= '                <a href="' . $f . '" class="top-bar-dd-item" role="menuitem" target="_blank" rel="noopener" data-year="' . $y . '">' . htmlspecialchars($label) . '</a>' . "\n";
  }
  $annualMenuHtml = rtrim($annualMenuHtml);

  // 2. Build Policies submenu links
  $policyDocs = array_filter($docs['documents'] ?? [], function($d) {
    return ($d['category'] ?? '') === 'policy';
  });

  $policiesMenuHtml = '';
  foreach ($policyDocs as $doc) {
    $label = trim($doc['menu_label'] ?? '') ?: trim($doc['title'] ?? '');
    $f = htmlspecialchars($doc['file'] ?? '');
    $isPdfOrExt = preg_match('/\.pdf$/i', $f) || preg_match('/^https?:\/\//i', $f);
    $targetAttr = $isPdfOrExt ? ' target="_blank" rel="noopener"' : '';
    $policiesMenuHtml .= '                <a href="' . $f . '" class="top-bar-dd-item" role="menuitem"' . $targetAttr . '>' . htmlspecialchars($label) . '</a>' . "\n";
  }
  $policiesMenuHtml = rtrim($policiesMenuHtml);

  // 3. Active Sustainability Report
  $sustainDoc = null;
  foreach ($docs['documents'] ?? [] as $d) {
    if (($d['id'] ?? '') === 'DOC-SUSTAINABILITY' || ($d['category'] ?? '') === 'sustainability') {
      $sustainDoc = $d;
      break;
    }
  }
  $sustainFile = $sustainDoc['file'] ?? 'assets/Vasudha_Sustainability_Report_2024-25.pdf';

  // 4. Update all HTML files in site root
  $htmlFiles = glob($siteRoot . '/*.html');
  if ($htmlFiles) {
    foreach ($htmlFiles as $hPath) {
      $c = @file_get_contents($hPath);
      if (!$c) continue;
      $changed = false;

      // Sync Annual Returns submenu
      if ($annualMenuHtml) {
        $arPattern = '/(<button[^>]*>\s*Annual Returns[\s\S]*?<div class="top-bar-dd-submenu"[^>]*>)([\s\S]*?)(<\/div>)/i';
        if (preg_match($arPattern, $c)) {
          $newC = preg_replace_callback($arPattern, function($m) use ($annualMenuHtml) {
            return $m[1] . "\n" . $annualMenuHtml . "\n              " . $m[3];
          }, $c);
          if ($newC && $newC !== $c) {
            $c = $newC;
            $changed = true;
          }
        }
      }

      // Sync Policies submenu
      if ($policiesMenuHtml) {
        $polPattern = '/(<div class="top-bar-dd-submenu top-bar-dd-submenu--policies"[^>]*>)([\s\S]*?)(<\/div>)/i';
        if (preg_match($polPattern, $c)) {
          $newC = preg_replace_callback($polPattern, function($m) use ($policiesMenuHtml) {
            return $m[1] . "\n" . $policiesMenuHtml . "\n              " . $m[3];
          }, $c);
          if ($newC && $newC !== $c) {
            $c = $newC;
            $changed = true;
          }
        }
      }

      // Sync Sustainability Report link in top-bar
      if ($sustainFile) {
        $sustainTop = '/(<a\s+href=")[^"]*Vasudha_Sustainability_Report[^"]*("\s+target="_blank"\s+rel="noopener">Sustainability Report<\/a>)/i';
        if (preg_match($sustainTop, $c)) {
          $newC = preg_replace($sustainTop, '$1' . $sustainFile . '$2', $c);
          if ($newC && $newC !== $c) {
            $c = $newC;
            $changed = true;
          }
        }

        $sustainFoot = '/(<a\s+href=")[^"]*Vasudha_Sustainability_Report[^"]*("\s+target="_blank"\s+rel="noopener">Sustainability<\/a>)/i';
        if (preg_match($sustainFoot, $c)) {
          $newC = preg_replace($sustainFoot, '$1' . $sustainFile . '$2', $c);
          if ($newC && $newC !== $c) {
            $c = $newC;
            $changed = true;
          }
        }
      }

      if ($changed) {
        @file_put_contents($hPath, $c);
      }
    }
  }

  // 5. Sync statutory regulations page
  staff_sync_statutory_page($docs, $siteRoot);
}


function staff_extract_policy_page_content($filePath) {
  if (!is_file($filePath)) return null;
  $c = @file_get_contents($filePath);
  if (!$c) return null;

  $h1Match = [];
  preg_match('/<h1[^>]*>([\s\S]*?)<\/h1>/i', $c, $h1Match);
  $title = !empty($h1Match[1]) ? trim(strip_tags($h1Match[1])) : '';

  $heroEnd = strpos($c, '</section>', strpos($c, 'class="page-hero"'));
  if ($heroEnd === false) $heroEnd = 0; else $heroEnd += 10;

  $nextSectionIdx = strpos($c, '<section class="news-section"');
  $footerIdx = strpos($c, '<footer');
  $endIdx = ($nextSectionIdx !== false) ? $nextSectionIdx : (($footerIdx !== false) ? $footerIdx : strlen($c));

  $rawSection = substr($c, $heroEnd, $endIdx - $heroEnd);
  $innerMatch = [];
  preg_match('/<div class="container"[^>]*>\s*<div[^>]*>([\s\S]*?)<\/div>\s*<\/div>\s*<\/section>/i', $rawSection, $innerMatch);

  return [
    'title' => $title,
    'body'  => !empty($innerMatch[1]) ? trim($innerMatch[1]) : trim($rawSection),
    'ok'    => true
  ];
}

function staff_update_policy_page_content($filePath, $newTitle, $newBody, $menuLabel) {
  if (!is_file($filePath)) return false;
  $c = @file_get_contents($filePath);
  if (!$c) return false;

  // Update H1 title
  $c = preg_replace('/<h1[^>]*>[\s\S]*?<\/h1>/i', '<h1 style="font-size: 26px; color: var(--primary-red); margin: 0 0 8px; font-weight: 700;">' . htmlspecialchars($newTitle) . '</h1>', $c);

  // Update Breadcrumb if present
  $lbl = htmlspecialchars($menuLabel ?: $newTitle);
  $c = preg_replace('/<span>Policies<\/span>\s*<span[^>]*>\/<\/span>\s*<span>[^<]*<\/span>/i', '<span>Policies</span>\n        <span style="margin: 0 8px;">/</span>\n        <span>' . $lbl . '</span>', $c);

  // Update Main Policy Section Body
  $heroEnd = strpos($c, '</section>', strpos($c, 'class="page-hero"'));
  if ($heroEnd === false) $heroEnd = 0; else $heroEnd += 10;

  $nextSectionIdx = strpos($c, '<section class="news-section"');
  $footerIdx = strpos($c, '<footer');
  $endIdx = ($nextSectionIdx !== false) ? $nextSectionIdx : (($footerIdx !== false) ? $footerIdx : strlen($c));

  $newSectionHtml = "\n  <section style=\"padding: 32px 0 72px;\">\n    <div class=\"container\">\n      <div class=\"policy-doc\" style=\"padding: 12px 0 32px; font-size: 15px; line-height: 1.75; color: #333;\">\n\n" . $newBody . "\n\n      </div>\n    </div>\n  </section>\n\n  ";

  $final = substr($c, 0, $heroEnd) . $newSectionHtml . substr($c, $endIdx);
  return file_put_contents($filePath, $final) !== false;
}

function staff_create_policy_page($filePath, $title, $menuLabel, $body, $siteRoot) {
  $vigilPath = $siteRoot . '/vigil-mechanism.html';
  if (!is_file($vigilPath)) return false;
  $vigil = file_get_contents($vigilPath);
  if (!$vigil) return false;

  $heroStart = strpos($vigil, '<section class="page-hero"');
  $nextSectionIdx = strpos($vigil, '<section class="news-section"');

  $headAndHeader = substr($vigil, 0, $heroStart);
  $footerAndScripts = substr($vigil, $nextSectionIdx);

  // Customize head title & meta
  $customizedHead = preg_replace('/<title>[\s\S]*?<\/title>/i', '<title>' . htmlspecialchars($title) . ' | Vasudha Pharma Chem Limited</title>', $headAndHeader);
  $customizedHead = preg_replace('/<meta name="description"[^>]*>/i', '<meta name="description" content="' . htmlspecialchars($title) . ' of Vasudha Pharma Chem Limited. Formulated and approved by the Board of Directors.">', $customizedHead);

  $lbl = htmlspecialchars($menuLabel ?: $title);
  $heroHtml = '<section class="page-hero" style="padding: 40px 0 16px; border-bottom: 1px solid var(--border);">
    <div class="container">
      <div style="font-size: 13px; color: var(--text-light); margin-bottom: 10px;">
        <span style="color: var(--primary-red); font-weight: 600;">Corporate Governance</span>
        <span style="margin: 0 8px;">/</span>
        <span>Policies</span>
        <span style="margin: 0 8px;">/</span>
        <span>' . $lbl . '</span>
      </div>
      <h1 style="font-size: 26px; color: var(--primary-red); margin: 0 0 8px; font-weight: 700;">' . htmlspecialchars($title) . '</h1>
      <p style="margin: 0; color: var(--text-light); font-size: 15px;">Vasudha Pharma Chem Limited &bull; Board Approved Charter</p>
    </div>
  </section>';

  $bodySection = "\n  <section style=\"padding: 32px 0 72px;\">
    <div class=\"container\" style=\"max-width: 1040px;\">
      <div class=\"policy-doc\" style=\"padding: 12px 0 32px; font-size: 15px; line-height: 1.75; color: #333;\">

" . $body . "

      </div>
    </div>
  </section>\n\n  ";

  $fullPage = $customizedHead . $heroHtml . $bodySection . $footerAndScripts;
  return file_put_contents($filePath, $fullPage) !== false;
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


  // 1b. Save Edited Policy Content directly into HTML file
  if ($action === 'save_policy_content') {
    $id = trim($_POST['id'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $menuLabel = trim($_POST['menu_label'] ?? '') ?: $title;
    $bodyContent = trim($_POST['body_content'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $year = trim($_POST['year'] ?? date('Y'));

    $targetDoc = null;
    $targetIdx = -1;
    foreach ($docsData['documents'] as $idx => $d) {
      if (($d['id'] ?? '') === $id) {
        $targetDoc = $d;
        $targetIdx = $idx;
        break;
      }
    }

    if (!$targetDoc) {
      $err = 'Policy not found in registry.';
    } elseif ($title === '') {
      $err = 'Policy title is required.';
    } elseif ($bodyContent === '') {
      $err = 'Policy body content cannot be empty.';
    } else {
      $fileRel = $targetDoc['file'] ?? '';
      $filePath = $siteRoot . '/' . $fileRel;

      if (!is_file($filePath) || substr($fileRel, -5) !== '.html') {
        $err = 'The selected policy is not an editable HTML web page (' . htmlspecialchars($fileRel) . ').';
      } else {
        $updatedFile = staff_update_policy_page_content($filePath, $title, $bodyContent, $menuLabel);
        if ($updatedFile) {
          $docsData['documents'][$targetIdx]['title'] = $title;
          $docsData['documents'][$targetIdx]['menu_label'] = $menuLabel;
          $docsData['documents'][$targetIdx]['description'] = $desc;
          $docsData['documents'][$targetIdx]['year'] = $year;
          $docsData['documents'][$targetIdx]['last_modified'] = date('Y-m-d');
          staff_save_documents($docsData);
          staff_sync_html_governance_menus($docsData, $siteRoot);
          staff_log_audit('Policy Content Updated', "Updated policy text and title for '$title' ($fileRel)", $id, 'Corporate Governance');
          $msg = 'Policy "' . htmlspecialchars($title) . '" text and clauses successfully updated and published to ' . htmlspecialchars($fileRel) . '!';
        } else {
          $err = 'Failed to write updated policy content to disk (' . htmlspecialchars($filePath) . ').';
        }
      }
    }
  }

  // 1c. Create & Author New Standalone Policy Page
  if ($action === 'create_policy_page') {
    $title = trim($_POST['title'] ?? '');
    $menuLabel = trim($_POST['menu_label'] ?? '') ?: $title;
    $fileSlug = trim($_POST['file_slug'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $year = trim($_POST['year'] ?? date('Y'));
    $bodyContent = trim($_POST['body_content'] ?? '');

    if ($title === '') {
      $err = 'Policy title is required.';
    } elseif ($bodyContent === '') {
      $err = 'Policy clauses and text content are required.';
    } else {
      // Sanitize file slug
      if (!$fileSlug) {
        $fileSlug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $title));
        $fileSlug = trim($fileSlug, '-');
      } else {
        $fileSlug = strtolower(preg_replace('/[^a-zA-Z0-9\-]+/', '-', $fileSlug));
        $fileSlug = trim($fileSlug, '-');
      }
      if (substr($fileSlug, -5) !== '.html') {
        $fileSlug .= '.html';
      }

      $destPath = $siteRoot . '/' . $fileSlug;
      if (is_file($destPath)) {
        $err = 'A page with filename "' . htmlspecialchars($fileSlug) . '" already exists. Please choose a different slug.';
      } else {
        $created = staff_create_policy_page($destPath, $title, $menuLabel, $bodyContent, $siteRoot);
        if ($created) {
          $newId = 'DOC-POLICY-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
          $newDoc = [
            'id'             => $newId,
            'title'          => $title,
            'menu_label'     => $menuLabel,
            'category'       => 'policy',
            'category_label' => 'Corporate Governance Policies',
            'year'           => $year,
            'unit'           => 'Corporate',
            'file'           => $fileSlug,
            'description'    => $desc,
            'is_core'        => false,
            'last_modified'  => date('Y-m-d')
          ];
          $docsData['documents'][] = $newDoc;
          staff_save_documents($docsData);
          staff_sync_html_governance_menus($docsData, $siteRoot);
          staff_log_audit('Policy Created', "Authored new policy page '$title' ($fileSlug)", $newId, 'Corporate Governance');
          $msg = 'New policy page "' . htmlspecialchars($title) . '" successfully authored, published to ' . htmlspecialchars($fileSlug) . ', and added to website dropdown menus!';
        } else {
          $err = 'Failed to generate policy page HTML file.';
        }
      }
    }
  }

  // 1. Upload new document / New Financial Year / New Policy
  if ($action === 'upload_new') {
    $title = trim($_POST['title'] ?? '');
    $menuLabel = trim($_POST['menu_label'] ?? '') ?: $title;
    $category = trim($_POST['category'] ?? 'annual_return');
    $year = trim($_POST['year'] ?? '');
    $unit = trim($_POST['unit'] ?? 'Corporate');
    $desc = trim($_POST['description'] ?? '');
    $linkUrl = trim($_POST['link_url'] ?? '');

    if ($title === '') {
      $err = 'Document title / website field name is required.';
    } elseif ($category === 'policy' && !empty($linkUrl) && (empty($_FILES['pdf_file']['tmp_name']) || !is_uploaded_file($_FILES['pdf_file']['tmp_name']))) {
      // Policy linking to a page or custom URL
      $newId = 'DOC-POLICY-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
      $newDoc = [
        'id'                  => $newId,
        'title'               => $title,
        'menu_label'          => $menuLabel,
        'category'            => 'policy',
        'category_label'      => 'Corporate Governance Policies',
        'year'                => $year ?: date('Y'),
        'unit'                => $unit,
        'file'                => $linkUrl,
        'description'         => $desc,
        'is_core'             => false,
        'last_modified'       => date('Y-m-d')
      ];
      $docsData['documents'][] = $newDoc;
      staff_save_documents($docsData);
      staff_sync_html_governance_menus($docsData, $siteRoot);
      staff_log_audit('Policy Added', "Published policy '$title' with menu label '$menuLabel'", $title, 'Governance');
      $msg = 'Policy "' . htmlspecialchars($title) . '" successfully added to navigation menus and policies registry.';
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
        if ($category === 'statutory') {
          $relFolder = 'assets/secretarial/statutory';
        } elseif ($category === 'sustainability') {
          $relFolder = 'assets';
        } else {
          $relFolder = 'assets/secretarial';
        }

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
            'menu_label'          => $menuLabel,
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
          staff_log_audit('Document Uploaded', "Uploaded '$title' ($category, $year) with menu label '$menuLabel'", $title, 'Governance');
          $msg = 'Document "' . htmlspecialchars($title) . '" successfully uploaded and published to registry & website navigation menus.';
        } else {
          $err = 'Could not save the uploaded file. Check folder permissions on ' . $relFolder . '.';
        }
      }
    }
  }

  // 2. Edit Document Field Names, Menu Label, Year, Category & In-Place PDF Overwrite
  if ($action === 'replace_pdf' || $action === 'edit_doc') {
    $docId = trim($_POST['id'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $menuLabel = trim($_POST['menu_label'] ?? '');
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
      $doc['menu_label'] = $menuLabel !== '' ? $menuLabel : ($doc['title'] ?? '');
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
            $msg = 'PDF file, menu display name, and website fields for "' . htmlspecialchars($doc['title']) . '" successfully updated.';
          } else {
            $err = 'Failed to overwrite existing file. Check permissions on ' . htmlspecialchars($doc['file']) . '.';
          }
        }
      } else {
        $doc['last_modified'] = date('Y-m-d');
        $msg = 'Document title, menu display name, and metadata for "' . htmlspecialchars($doc['title']) . '" updated.';
      }

      if (!$err) {
        staff_save_documents($docsData);
        staff_sync_html_governance_menus($docsData, $siteRoot);
        staff_log_audit('Document Updated', "Updated '$title' (Menu Name: '{$doc['menu_label']}', ID: $docId)", $title, 'Governance');
      }
    }
  }

  // 3. Quick in-place overwrite for Sustainability Report
  if ($action === 'quick_replace_sustainability') {
    $sustainTitle = trim($_POST['sustain_title'] ?? '');
    $sustainYear = trim($_POST['sustain_year'] ?? '');
    $sustainMenu = trim($_POST['sustain_menu'] ?? '') ?: $sustainTitle;

    if (empty($_FILES['sustainability_pdf']['tmp_name']) || !is_uploaded_file($_FILES['sustainability_pdf']['tmp_name'])) {
      // Just metadata update
      if ($sustainTitle || $sustainYear) {
        foreach ($docsData['documents'] as $i => $d) {
          if ($d['id'] === 'DOC-SUSTAINABILITY' || ($d['category'] ?? '') === 'sustainability') {
            if ($sustainTitle) $docsData['documents'][$i]['title'] = $sustainTitle;
            if ($sustainMenu) $docsData['documents'][$i]['menu_label'] = $sustainMenu;
            if ($sustainYear) $docsData['documents'][$i]['year'] = $sustainYear;
            $docsData['documents'][$i]['last_modified'] = date('Y-m-d');
            break;
          }
        }
        staff_save_documents($docsData);
        staff_sync_html_governance_menus($docsData, $siteRoot);
        staff_log_audit('Sustainability Updated', "Updated metadata: $sustainTitle ($sustainYear)", 'Sustainability', 'Governance');
        $msg = 'Sustainability Report website title, menu label, and year successfully updated!';
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
        // Construct target filename based on year or keep standard
        $destRel = 'assets/Vasudha_Sustainability_Report_' . ($sustainYear ? preg_replace('/[^a-zA-Z0-9\-]+/', '', $sustainYear) : '2024-25') . '.pdf';
        $destAbs = $siteRoot . '/' . $destRel;

        if (move_uploaded_file($_FILES['sustainability_pdf']['tmp_name'], $destAbs)) {
          foreach ($docsData['documents'] as $i => $d) {
            if ($d['id'] === 'DOC-SUSTAINABILITY' || ($d['category'] ?? '') === 'sustainability') {
              if ($sustainTitle) $docsData['documents'][$i]['title'] = $sustainTitle;
              if ($sustainMenu) $docsData['documents'][$i]['menu_label'] = $sustainMenu;
              if ($sustainYear) $docsData['documents'][$i]['year'] = $sustainYear;
              $docsData['documents'][$i]['file'] = $destRel;
              $docsData['documents'][$i]['file_size'] = $size;
              $docsData['documents'][$i]['file_size_formatted'] = round($size / 1024 / 1024, 2) . ' MB';
              $docsData['documents'][$i]['last_modified'] = date('Y-m-d');
              break;
            }
          }
          staff_save_documents($docsData);
          staff_sync_html_governance_menus($docsData, $siteRoot);
          staff_log_audit('Sustainability Replaced', "Uploaded new Sustainability PDF: $destRel ($sustainTitle)", 'Sustainability', 'Governance');
          $msg = 'Sustainability Report PDF replaced & field names updated! All website navigation links now serve the new file.';
        } else {
          $err = 'Failed to overwrite Sustainability Report PDF file on server.';
        }
      }
    }
  }

  // 4. Delete document / Remove Old Year / Remove Policy / Remove Filing
  if ($action === 'delete') {
    $docId = trim($_POST['id'] ?? '');
    $deleted = false;
    $deletedTitle = '';
    $newDocs = [];
    foreach ($docsData['documents'] as $d) {
      if ($d['id'] === $docId) {
        $deleted = true;
        $deletedTitle = $d['title'] ?? $docId;
        continue;
      }
      $newDocs[] = $d;
    }

    if ($deleted) {
      $docsData['documents'] = $newDocs;
      staff_save_documents($docsData);
      staff_sync_html_governance_menus($docsData, $siteRoot);
      if (!empty($_POST['delete_file']) && $deletedFile && is_file($siteRoot . '/' . $deletedFile)) {
        @unlink($siteRoot . '/' . $deletedFile);
      }
      staff_log_audit('Document Deleted', "Removed document '$deletedTitle' ($docId) from website navigation & registry", $deletedTitle, 'Governance');
      $msg = 'Document "' . htmlspecialchars($deletedTitle) . '" (' . htmlspecialchars($docId) . ') removed from website registry and navigation dropdowns.';
    } else {
      $err = 'Document ID ' . htmlspecialchars($docId) . ' not found.';
    }
  }

  // 5. Force Re-Sync Website Menus
  if ($action === 'force_sync') {
    staff_sync_html_governance_menus($docsData, $siteRoot);
    staff_log_audit('Forced Sync', 'Manually synchronized all website dropdown menus, statutory grids, and links', 'Website Menus', 'Governance');
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
    // If it's a page link like "csr-policy.html", it's an HTML file on disk
    if (is_file($siteRoot . '/' . $doc['file'])) {
      $docsData['documents'][$idx]['file_size_formatted'] = 'Web Page';
    } else {
      $docsData['documents'][$idx]['file_missing'] = true;
    }
  }
}

$staff_title = 'Governance';
require __DIR__ . '/includes/header.php';


$editPolicyDoc = null;
$policyContent = null;
if (isset($_GET['edit_policy'])) {
  foreach ($docsData['documents'] as $d) {
    if (($d['id'] ?? '') === $_GET['edit_policy']) {
      $editPolicyDoc = $d;
      $pPath = $siteRoot . '/' . ($d['file'] ?? '');
      $policyContent = staff_extract_policy_page_content($pPath);
      break;
    }
  }
}

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
<div class="desk-hero-banner">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; position:relative; z-index:1;">
    <div>
      <div style="text-transform:uppercase; font-size:11px; letter-spacing:0.1em; opacity:0.85; font-weight:700; margin-bottom:6px;">
        Secretarial &bull; Statutory Governance &bull; MCA Filings
      </div>
      <h1 style="margin:0 0 4px; font-size:24px; color:#FFFFFF;">Governance Desk</h1>
      <p style="margin:0; opacity:0.9; font-size:13px; color:#E2E8F0; max-width:820px; line-height:1.5;">
        Statutory disclosures, annual reports, MCA filings, whistleblower protections, environmental clearances, and live PDF document management.
      </p>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
      <a href="#uploadDocForm" class="btn" style="background:#FFFFFF; color:var(--sp-primary-dark); font-weight:700; font-size:12.5px; box-shadow:0 4px 12px rgba(0,0,0,0.15);">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        + Add New Report / Filing
      </a>
      <button type="button" onclick="openNewPolicyModal()" class="btn" style="background:rgba(255,255,255,0.14); color:#FFFFFF; border:1px solid rgba(255,255,255,0.25); font-weight:600; font-size:12.5px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
        Write Policy Page
      </button>
    </div>
  </div>
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
        Website Display Title: <strong><?php echo htmlspecialchars($primarySustain['title'] ?? 'Vasudha Sustainability & ESG Report'); ?></strong> &bull; Period: <strong><?php echo htmlspecialchars($primarySustain['year'] ?? '2024-25'); ?></strong><br>
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
    <div class="stat-sub">MGT-7 &amp; Annual Reports in dropdown</div>
  </div>
  <div class="stat-card" onclick="filterDocs('policy')" style="cursor:pointer; border-left:4px solid #7E22CE;">
    <div class="stat-label">Governance Policies</div>
    <div class="stat-num" style="color:#7E22CE;"><?php echo $counts['policy']; ?></div>
    <div class="stat-sub">Board &amp; Committee charters in dropdown</div>
  </div>
  <div class="stat-card" onclick="filterDocs('statutory')" style="cursor:pointer; border-left:4px solid #D97706;">
    <div class="stat-label">Unit Statutory Filings</div>
    <div class="stat-num" style="color:#D97706;"><?php echo $counts['statutory']; ?></div>
    <div class="stat-sub">Unit 1–5 statutory clearances &amp; CFO</div>
  </div>
</div>

<!-- Search & Category Filters -->
<div class="card" style="padding:16px 20px; margin-bottom:18px;">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
    <div style="flex:1; min-width:240px; max-width:400px; position:relative;">
      <input type="text" id="docSearch" placeholder="Search by title, menu name, year, or unit..." oninput="filterDocs()" style="padding-left:34px;">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); pointer-events:none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    </div>

    <div style="display:flex; gap:6px; flex-wrap:wrap;">
      <button type="button" class="filter-btn active" data-cat="all" onclick="setCategoryFilter('all', this)">All (<?php echo $counts['all']; ?>)</button>
      <button type="button" class="filter-btn" data-cat="annual_return" onclick="setCategoryFilter('annual_return', this)">Annual Returns / Reports (<?php echo $counts['annual_return']; ?>)</button>
      <button type="button" class="filter-btn" data-cat="policy" onclick="setCategoryFilter('policy', this)">Governance Policies (<?php echo $counts['policy']; ?>)</button>
      <button type="button" class="filter-btn" data-cat="statutory" onclick="setCategoryFilter('statutory', this)">Unit Statutory (<?php echo $counts['statutory']; ?>)</button>
      <button type="button" class="filter-btn" data-cat="sustainability" onclick="setCategoryFilter('sustainability', this)">Sustainability (<?php echo $counts['sustainability']; ?>)</button>
    </div>
  </div>
</div>

<!-- Documents Registry Table -->
<div class="card" style="padding:0; overflow:hidden;">
  <div style="padding:18px 24px 14px; border-bottom:1px solid var(--sp-border); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
    <div>
      <h2 style="margin:0; font-size:17px;">Published Regulatory Registry &amp; Website Documents</h2>
      <div style="font-size:12.5px; color:var(--sp-text-muted); margin-top:2px;">
        Editing the title or menu name updates the display label on the website navigation menus. You can also delete older years or add new ones anytime.
      </div>
    </div>
    <a href="#uploadDocForm" class="btn btn-primary" style="font-size:12.5px; background:#0E8F6C;">
      + Add New Report, Policy or Filing
    </a>
  </div>

  <table class="doc-table">
    <thead>
      <tr>
        <th style="width:50px;">Year</th>
        <th>Website Title &amp; Scrolldown Menu Name</th>
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
        $menuLabel = $d['menu_label'] ?? $d['title'] ?? '';
        $searchIndex = strtolower(($d['id'] ?? '') . ' ' . ($d['title'] ?? '') . ' ' . $menuLabel . ' ' . ($d['year'] ?? '') . ' ' . ($d['unit'] ?? '') . ' ' . ($d['file'] ?? '') . ' ' . ($d['description'] ?? ''));
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
            <div style="margin-top:3px; display:inline-flex; align-items:center; gap:5px; background:#F1F5F9; padding:2px 8px; border-radius:4px; font-size:11.5px; color:#334155;">
              <span style="color:#64748B; font-weight:600;">Menu Name:</span>
              <strong style="color:#0E8F6C;"><?php echo htmlspecialchars($menuLabel); ?></strong>
            </div>
            <?php if (!empty($d['description'])): ?>
              <div style="font-size:11.5px; color:#64748B; margin-top:4px; max-width:440px; line-height:1.4;">
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
            <?php if (substr($d['file'] ?? '', -5) === '.html'): ?>
              <a href="documents.php?edit_policy=<?php echo urlencode($d['id']); ?>#policyEditorSection" class="btn" style="padding:5px 9px; font-size:12px; background:#F3E8FF; color:#7E22CE; border:1px solid #E9D5FF; font-weight:600;" title="Edit policy text, sections and clauses">
                📝 Edit Content
              </a>
            <?php endif; ?>
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
              <button type="submit" class="btn btn-danger" style="padding:5px 9px; font-size:12px;" title="Remove this filing from website registry and scrolldown menu">
                Delete
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>


<!-- ================= POLICY CONTENT & CLAUSES EDITOR ================= -->
<?php if ($editPolicyDoc && $policyContent): ?>
<div class="card" id="policyEditorSection" style="border: 2px solid #7E22CE; background: #FAF5FF; margin-top:24px; padding:24px 28px;">
  <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
    <div>
      <span style="font-size:11.5px; font-weight:700; color:#7E22CE; text-transform:uppercase; letter-spacing:0.04em;">
        Corporate Governance &bull; Policy Content &amp; Clauses Editor
      </span>
      <h2 style="margin:2px 0 0; font-size:20px; color:#581C87;">
        Editing: <?php echo htmlspecialchars($editPolicyDoc['title']); ?> (<code><?php echo htmlspecialchars($editPolicyDoc['file']); ?></code>)
      </h2>
      <p style="margin:4px 0 0; font-size:13px; color:#6B21A8;">
        Edit the live policy clauses, headings, and legal text published on this web page. Saving updates the live HTML file and re-synchronizes website navigation.
      </p>
    </div>
    <div style="display:flex; gap:8px;">
      <a href="../<?php echo htmlspecialchars($editPolicyDoc['file']); ?>" target="_blank" rel="noopener" class="btn btn-ghost" style="font-size:12px; background:#FFFFFF; border-color:#D8B4FE; color:#7E22CE;">
        View Live Page ↗
      </a>
      <a class="btn btn-ghost" href="documents.php" style="font-size:12px; background:#FFFFFF;">Cancel Edit</a>
    </div>
  </div>

  <form method="post">
    <?php echo staff_csrf_field(); ?>
    <input type="hidden" name="action" value="save_policy_content">
    <input type="hidden" name="id" value="<?php echo htmlspecialchars($editPolicyDoc['id']); ?>">

    <div class="row">
      <div>
        <label style="color:#581C87;">Policy Page Heading (H1 Title) *</label>
        <input name="title" required value="<?php echo htmlspecialchars($policyContent['title'] ?: $editPolicyDoc['title']); ?>" style="background:#FFFFFF;">
      </div>
      <div>
        <label style="color:#581C87;">Website Scrolldown Menu Display Text *</label>
        <input name="menu_label" required value="<?php echo htmlspecialchars($editPolicyDoc['menu_label'] ?? $editPolicyDoc['title']); ?>" style="background:#FFFFFF;">
        <div style="font-size:11.5px; color:#6B21A8; margin-top:3px;">
          Appears in the <code>Corporate Governance → Policies</code> dropdown menu across all pages.
        </div>
      </div>
    </div>

    <div class="row" style="margin-top:12px;">
      <div>
        <label style="color:#581C87;">Adoption Period / Year</label>
        <input name="year" value="<?php echo htmlspecialchars($editPolicyDoc['year'] ?? date('Y')); ?>" style="background:#FFFFFF;">
      </div>
      <div>
        <label style="color:#581C87;">Brief Statutory Disclosure Summary</label>
        <input name="description" value="<?php echo htmlspecialchars($editPolicyDoc['description'] ?? ''); ?>" style="background:#FFFFFF;">
      </div>
    </div>

    <div style="margin-top:16px;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; flex-wrap:wrap; gap:8px;">
        <label style="margin:0; font-weight:700; color:#581C87;">Policy Body Content &amp; Clauses (HTML / Formatted Text) *</label>
        <div style="display:flex; gap:6px; flex-wrap:wrap;">
          <button type="button" class="btn btn-ghost" style="padding:3px 8px; font-size:11.5px; background:#FFFFFF; border-color:#D8B4FE;" onclick="insertPolicyTag('policyBodyText', '<h2 style=\"font-size: 16px; color: var(--primary-red); font-weight: 700; margin: 24px 0 10px;\">', '</h2>')">H2 Section</button>
          <button type="button" class="btn btn-ghost" style="padding:3px 8px; font-size:11.5px; background:#FFFFFF; border-color:#D8B4FE;" onclick="insertPolicyTag('policyBodyText', '<h3 style=\"font-size: 14.5px; color: #1E293B; font-weight: 700; margin: 18px 0 8px;\">', '</h3>')">H3 Clause</button>
          <button type="button" class="btn btn-ghost" style="padding:3px 8px; font-size:11.5px; background:#FFFFFF; border-color:#D8B4FE;" onclick="insertPolicyTag('policyBodyText', '<p style=\"line-height: 1.7; color: #333; margin: 0 0 16px;\">', '</p>')">Paragraph</button>
          <button type="button" class="btn btn-ghost" style="padding:3px 8px; font-size:11.5px; background:#FFFFFF; border-color:#D8B4FE;" onclick="insertPolicyList('policyBodyText')">Bullet List</button>
          <button type="button" class="btn btn-ghost" style="padding:3px 8px; font-size:11.5px; background:#FFFFFF; border-color:#D8B4FE;" onclick="insertPolicyAlert('policyBodyText')">Statutory Note Box</button>
          <button type="button" class="btn btn-ghost" style="padding:3px 8px; font-size:11.5px; background:#7E22CE; color:#FFFFFF; border-color:#7E22CE;" onclick="togglePolicyPreview('policyBodyText', 'policyPreviewArea', 'policyPreviewContent')">Toggle Live Preview</button>
        </div>
      </div>

      <textarea id="policyBodyText" name="body_content" rows="18" style="background:#FFFFFF; font-family:Consolas, monospace; font-size:13px; line-height:1.6;" required><?php echo htmlspecialchars($policyContent['body']); ?></textarea>

      <!-- Live Preview Area -->
      <div id="policyPreviewArea" style="display:none; margin-top:12px; background:#FFFFFF; border:1px solid #CBD5E1; border-radius:8px; padding:24px; max-height:450px; overflow-y:auto;">
        <div style="font-size:12px; font-weight:700; color:#64748B; text-transform:uppercase; margin-bottom:12px;">Live Document Preview:</div>
        <div id="policyPreviewContent"></div>
      </div>
    </div>

    <div style="margin-top:20px; display:flex; gap:12px; align-items:center;">
      <button class="btn btn-primary" type="submit" style="background:#7E22CE; padding:10px 20px; font-weight:700;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
        Save &amp; Publish Policy Content to Live Web Page
      </button>
      <a class="btn btn-ghost" href="documents.php">Cancel</a>
    </div>
  </form>
</div>
<?php endif; ?>

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
        <label>Website Display Title / Registry Name *</label>
        <input name="title" required value="<?php echo htmlspecialchars($editDoc['title']); ?>" placeholder="e.g. Annual Return (Form MGT-7) 2024-2025, POSH Policy">
      </div>
      <div>
        <label>Website Scrolldown Menu Display Text *</label>
        <input name="menu_label" required value="<?php echo htmlspecialchars($editDoc['menu_label'] ?? $editDoc['title']); ?>" placeholder="e.g. ANNUAL RETURN (FORM MGT-7) 2024-2025 or POSH Policy">
        <div style="font-size:11.5px; color:#64748B; margin-top:3px;">
          This exact name appears in the website top-bar dropdown menu.
        </div>
      </div>
    </div>

    <div class="row" style="margin-top:12px;">
      <div>
        <label>Regulatory Category *</label>
        <select name="category" required>
          <option value="annual_return" <?php echo ($editDoc['category'] === 'annual_return' || $editDoc['category'] === 'annual_report') ? 'selected' : ''; ?>>Annual Returns &amp; Reports (MGT-7)</option>
          <option value="policy" <?php echo $editDoc['category'] === 'policy' ? 'selected' : ''; ?>>Corporate Governance Policies &amp; Charters</option>
          <option value="sustainability" <?php echo $editDoc['category'] === 'sustainability' ? 'selected' : ''; ?>>Sustainability &amp; ESG Reports</option>
          <option value="statutory" <?php echo $editDoc['category'] === 'statutory' ? 'selected' : ''; ?>>Unit Statutory Filings (CFO / CFE / Act)</option>
          <option value="general" <?php echo $editDoc['category'] === 'general' ? 'selected' : ''; ?>>General Corporate Disclosures</option>
        </select>
      </div>
      <div>
        <label>Financial Year / Period Label</label>
        <input name="year" value="<?php echo htmlspecialchars($editDoc['year']); ?>" placeholder="e.g. 2025-26, 2024-25, 2026">
      </div>
    </div>

    <div class="row" style="margin-top:12px;">
      <div>
        <label>Applicable Unit / Entity</label>
        <input name="unit" value="<?php echo htmlspecialchars($editDoc['unit'] ?? 'Corporate'); ?>" placeholder="e.g. Corporate, VPCL – II, VPCL – III, VPCL – V">
      </div>
      <div>
        <label>Replace PDF File (Optional — Leave blank to keep existing: <code><?php echo htmlspecialchars($editDoc['file']); ?></code>)</label>
        <input type="file" name="pdf_file" accept="application/pdf,.pdf" style="background:#FFFFFF; padding:8px 12px;">
      </div>
    </div>

    <div style="margin-top:12px;">
      <label>Brief Description &amp; Disclosure Note</label>
      <textarea name="description" rows="2" placeholder="Statutory compliance summary, board meeting adoption details..."><?php echo htmlspecialchars($editDoc['description'] ?? ''); ?></textarea>
    </div>

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
      <h2 style="margin:0; font-size:18px;">+ Add New Financial Year Report, Policy, or Statutory Filing</h2>
      <div style="font-size:12.5px; color:var(--sp-text-muted); margin-top:2px;">
        Upload official PDF and define the exact menu name to publish directly on the website navigation.
      </div>
    </div>
    <div style="display:flex; gap:6px; flex-wrap:wrap;">
      <button type="button" class="preset-add-btn" onclick="applyAddPreset('annual_return', 'Annual Report 2025-2026', 'ANNUAL REPORT 2025-2026', '2025-26', 'Corporate', 'Annual statutory filing under Companies Act, 2013.')">
        + Annual Report 2025-26
      </button>
      <button type="button" class="preset-add-btn" onclick="applyAddPreset('annual_return', 'Annual Return (Form MGT-7) 2025-2026', 'ANNUAL RETURN (FORM MGT-7) 2025-2026', '2025-26', 'Corporate', 'Form MGT-7 statutory annual return filing.')">
        + MGT-7 Return 2025-26
      </button>
      <button type="button" class="preset-add-btn" onclick="applyAddPreset('policy', 'Whistleblower & Vigil Policy 2025', 'Whistleblower Policy', '2025', 'Corporate', 'Board-approved whistleblower policy framework.')">
        + New Policy
      </button>
      <button type="button" class="preset-add-btn" onclick="applyAddPreset('statutory', 'Public Act Policy 2025-26 (Unit V)', 'PUBLIC ACT POLICY 2025-26', '2025-26', 'VPCL – V', 'Unit V public liability statutory compliance filing.')">
        + Unit Statutory Filing
      </button>
    </div>
  </div>

  <form method="post" enctype="multipart/form-data" id="newDocForm">
    <?php echo staff_csrf_field(); ?>
    <input type="hidden" name="action" value="upload_new">

    <div class="row">
      <div>
        <label>Document Title / Registry Name *</label>
        <input name="title" id="newTitle" required placeholder="e.g. Annual Report 2025-2026, POSH Policy">
      </div>
      <div>
        <label>Website Scrolldown Menu Display Text *</label>
        <input name="menu_label" id="newMenuLabel" required placeholder="e.g. ANNUAL RETURN (FORM MGT-7) 2025-2026, Whistleblower Policy">
        <div style="font-size:11.5px; color:#64748B; margin-top:3px;">
          This is the exact label displayed in the website dropdown menu.
        </div>
      </div>
    </div>

    <div class="row" style="margin-top:12px;">
      <div>
        <label>Regulatory Category *</label>
        <select name="category" id="newCategory" required>
          <option value="annual_return">Annual Returns &amp; Reports (MGT-7)</option>
          <option value="policy">Corporate Governance Policies &amp; Charters</option>
          <option value="sustainability">Sustainability &amp; ESG Reports</option>
          <option value="statutory">Unit Statutory Filings (CFO / CFE / Act)</option>
          <option value="general">General Corporate Disclosures</option>
        </select>
      </div>
      <div>
        <label>Financial Year / Period (e.g. 2025-26) *</label>
        <input name="year" id="newYear" required placeholder="e.g. 2025-26 or 2026-27">
      </div>
    </div>

    <div class="row" style="margin-top:12px;">
      <div>
        <label>Applicable Unit / Entity</label>
        <input name="unit" id="newUnit" placeholder="e.g. Corporate, VPCL – II, VPCL – III, VPCL – V" value="Corporate">
      </div>
      <div>
        <label>Select PDF Document to Upload * (Max 50 MB)</label>
        <input type="file" name="pdf_file" accept="application/pdf,.pdf" required style="padding:8px 12px; background:#FFFFFF;">
      </div>
    </div>

    <div style="margin-top:12px;">
      <label>Brief Description &amp; Disclosure Note</label>
      <textarea name="description" id="newDesc" rows="2" placeholder="Describe the document filing, section under Companies Act, or board meeting adoption date..."></textarea>
    </div>

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

function applyAddPreset(cat, title, menuLabel, year, unit, desc) {
  var catEl = document.getElementById('newCategory');
  var titleEl = document.getElementById('newTitle');
  var menuEl = document.getElementById('newMenuLabel');
  var yearEl = document.getElementById('newYear');
  var unitEl = document.getElementById('newUnit');
  var descEl = document.getElementById('newDesc');
  if (catEl) catEl.value = cat;
  if (titleEl) titleEl.value = title;
  if (menuEl) menuEl.value = menuLabel;
  if (yearEl) yearEl.value = year;
  if (unitEl) unitEl.value = unit;
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


<!-- ================= WRITE NEW POLICY PAGE MODAL ================= -->
<div id="newPolicyModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); z-index:9999; align-items:center; justify-content:center; padding:20px;">
  <div style="background:#FFFFFF; border-radius:14px; width:100%; max-width:760px; max-height:92vh; overflow-y:auto; box-shadow:0 20px 40px rgba(0,0,0,0.25); padding:26px 30px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; border-bottom:1px solid #E2E8F0; padding-bottom:12px;">
      <div>
        <h3 style="margin:0; font-size:19px; color:#581C87;">+ Write New Corporate Governance Policy Page</h3>
        <p style="margin:3px 0 0; font-size:12.5px; color:#6B21A8;">
          Author a standalone policy page. This generates the HTML file and automatically hooks it into the top-bar scrolldown menu across all pages.
        </p>
      </div>
      <button type="button" onclick="closeNewPolicyModal()" style="background:transparent; border:0; font-size:24px; cursor:pointer; color:#64748B;">&times;</button>
    </div>

    <!-- Policy Presets Selector -->
    <div style="margin-bottom:16px; background:#FAF5FF; border:1px solid #E9D5FF; border-radius:8px; padding:12px 16px;">
      <div style="font-weight:700; font-size:12px; color:#7E22CE; margin-bottom:6px; text-transform:uppercase;">Quick Starter Presets (Click to load standard charter):</div>
      <div style="display:flex; gap:6px; flex-wrap:wrap;">
        <button type="button" class="btn btn-ghost" style="padding:4px 10px; font-size:11.5px; background:#FFFFFF; border-color:#D8B4FE; color:#581C87;" onclick="applyNewPolicyPreset('code-of-conduct')">Code of Business Conduct</button>
        <button type="button" class="btn btn-ghost" style="padding:4px 10px; font-size:11.5px; background:#FFFFFF; border-color:#D8B4FE; color:#581C87;" onclick="applyNewPolicyPreset('rpt-policy')">Related Party Transactions</button>
        <button type="button" class="btn btn-ghost" style="padding:4px 10px; font-size:11.5px; background:#FFFFFF; border-color:#D8B4FE; color:#581C87;" onclick="applyNewPolicyPreset('dividend-policy')">Dividend Distribution</button>
        <button type="button" class="btn btn-ghost" style="padding:4px 10px; font-size:11.5px; background:#FFFFFF; border-color:#D8B4FE; color:#581C87;" onclick="applyNewPolicyPreset('risk-policy')">Risk Management Policy</button>
      </div>
    </div>

    <form method="post">
      <?php echo staff_csrf_field(); ?>
      <input type="hidden" name="action" value="create_policy_page">

      <div class="row">
        <div>
          <label>Policy Title (Header on Page) *</label>
          <input type="text" id="np_title" name="title" required placeholder="e.g. Code of Business Conduct & Ethics" onkeyup="autoSlugPolicy(this.value)">
        </div>
        <div>
          <label>Website Scrolldown Menu Name *</label>
          <input type="text" id="np_menu_label" name="menu_label" required placeholder="e.g. Code of Conduct">
        </div>
      </div>

      <div class="row" style="margin-top:12px;">
        <div>
          <label>Web Page URL Slug / Filename *</label>
          <input type="text" id="np_slug" name="file_slug" required placeholder="e.g. code-of-conduct.html">
        </div>
        <div>
          <label>Adoption Year / Period</label>
          <input type="text" id="np_year" name="year" value="<?php echo date('Y'); ?>">
        </div>
      </div>

      <div style="margin-top:12px;">
        <label>Brief Statutory Summary / Disclosure Note</label>
        <input type="text" id="np_desc" name="description" placeholder="e.g. Statutory policy adopted by Board of Directors pursuant to Companies Act, 2013.">
      </div>

      <div style="margin-top:16px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; flex-wrap:wrap; gap:8px;">
          <label style="margin:0; font-weight:700;">Policy Body Clauses &amp; Sections *</label>
          <div style="display:flex; gap:6px; flex-wrap:wrap;">
            <button type="button" class="btn btn-ghost" style="padding:3px 8px; font-size:11.5px;" onclick="insertPolicyTag('np_body', '<h2 style=\"font-size: 16px; color: var(--primary-red); font-weight: 700; margin: 24px 0 10px;\">', '</h2>')">H2 Section</button>
            <button type="button" class="btn btn-ghost" style="padding:3px 8px; font-size:11.5px;" onclick="insertPolicyTag('np_body', '<h3 style=\"font-size: 14.5px; color: #1E293B; font-weight: 700; margin: 18px 0 8px;\">', '</h3>')">H3 Clause</button>
            <button type="button" class="btn btn-ghost" style="padding:3px 8px; font-size:11.5px;" onclick="insertPolicyTag('np_body', '<p style=\"line-height: 1.7; color: #333; margin: 0 0 16px;\">', '</p>')">Paragraph</button>
            <button type="button" class="btn btn-ghost" style="padding:3px 8px; font-size:11.5px;" onclick="insertPolicyList('np_body')">Bullet List</button>
            <button type="button" class="btn btn-ghost" style="padding:3px 8px; font-size:11.5px;" onclick="insertPolicyAlert('np_body')">Statutory Note Box</button>
            <button type="button" class="btn btn-ghost" style="padding:3px 8px; font-size:11.5px; background:#7E22CE; color:#FFFFFF; border-color:#7E22CE;" onclick="togglePolicyPreview('np_body', 'np_preview_area', 'np_preview_content')">Preview</button>
          </div>
        </div>

        <textarea id="np_body" name="body_content" rows="14" style="background:#FAFAFA; font-family:Consolas, monospace; font-size:13px; line-height:1.6;" required placeholder="Enter or paste policy preamble, clauses, definitions, governance procedures..."></textarea>

        <div id="np_preview_area" style="display:none; margin-top:12px; background:#FFFFFF; border:1px solid #CBD5E1; border-radius:8px; padding:20px; max-height:350px; overflow-y:auto;">
          <div style="font-size:11px; font-weight:700; color:#64748B; text-transform:uppercase; margin-bottom:10px;">Live Preview:</div>
          <div id="np_preview_content"></div>
        </div>
      </div>

      <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px; border-top:1px solid #E2E8F0; padding-top:16px;">
        <button type="button" onclick="closeNewPolicyModal()" class="btn btn-ghost">Cancel</button>
        <button type="submit" class="btn btn-primary" style="background:#7E22CE; font-weight:700; padding:9px 20px;">
          Create &amp; Publish Policy Page
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openNewPolicyModal() {
  document.getElementById('newPolicyModal').style.display = 'flex';
}
function closeNewPolicyModal() {
  document.getElementById('newPolicyModal').style.display = 'none';
}

function autoSlugPolicy(title) {
  if (!document.getElementById('np_slug').dataset.manuallyEdited) {
    let slug = title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    if (slug) slug += '.html';
    document.getElementById('np_slug').value = slug;
  }
  if (!document.getElementById('np_menu_label').dataset.manuallyEdited) {
    document.getElementById('np_menu_label').value = title;
  }
}

document.getElementById('np_slug')?.addEventListener('input', function() {
  this.dataset.manuallyEdited = '1';
});
document.getElementById('np_menu_label')?.addEventListener('input', function() {
  this.dataset.manuallyEdited = '1';
});

function insertPolicyTag(textareaId, openTag, closeTag) {
  const ta = document.getElementById(textareaId);
  if (!ta) return;
  const start = ta.selectionStart;
  const end = ta.selectionEnd;
  const sel = ta.value.substring(start, end);
  const rep = openTag + (sel || 'Text here...') + closeTag;
  ta.value = ta.value.substring(0, start) + rep + ta.value.substring(end);
  ta.focus();
  ta.setSelectionRange(start + openTag.length, start + rep.length - closeTag.length);
}

function insertPolicyList(textareaId) {
  const ta = document.getElementById(textareaId);
  if (!ta) return;
  const listHtml = '<ul style="list-style: none; padding: 0; margin: 0 0 20px;">\n' +
    '  <li style="padding: 6px 0 6px 24px; position: relative; line-height: 1.6; color: #333;">\n' +
    '    <span style="position: absolute; left: 0; color: var(--primary-red); font-weight: 700;">▸</span>\n' +
    '    Clause item 1\n' +
    '  </li>\n' +
    '  <li style="padding: 6px 0 6px 24px; position: relative; line-height: 1.6; color: #333;">\n' +
    '    <span style="position: absolute; left: 0; color: var(--primary-red); font-weight: 700;">▸</span>\n' +
    '    Clause item 2\n' +
    '  </li>\n' +
    '</ul>\n';
  insertAtCursor(ta, listHtml);
}

function insertPolicyAlert(textareaId) {
  const ta = document.getElementById(textareaId);
  if (!ta) return;
  const alertHtml = '<div style="background: #F8FAFC; border-left: 4px solid var(--primary-red); padding: 14px 18px; margin: 18px 0; border-radius: 4px;">\n' +
    '  <strong style="color: #0F172A; display: block; margin-bottom: 4px;">Statutory Note:</strong>\n' +
    '  <p style="margin: 0; font-size: 14px; line-height: 1.6; color: #475569;">Pursuant to applicable provisions of the Companies Act, 2013 and SEBI Listing Regulations.</p>\n' +
    '</div>\n';
  insertAtCursor(ta, alertHtml);
}

function insertAtCursor(ta, text) {
  const start = ta.selectionStart;
  const end = ta.selectionEnd;
  ta.value = ta.value.substring(0, start) + text + ta.value.substring(end);
  ta.focus();
  ta.setSelectionRange(start + text.length, start + text.length);
}

function togglePolicyPreview(taId, areaId, contentId) {
  const area = document.getElementById(areaId);
  const ta = document.getElementById(taId);
  const cnt = document.getElementById(contentId);
  if (!area || !ta || !cnt) return;
  if (area.style.display === 'none') {
    cnt.innerHTML = ta.value;
    area.style.display = 'block';
  } else {
    area.style.display = 'none';
  }
}

function applyNewPolicyPreset(preset) {
  const presets = {
    'code-of-conduct': {
      title: 'Code of Business Conduct & Ethics',
      menu: 'Code of Conduct',
      slug: 'code-of-conduct.html',
      desc: 'Principles of professional integrity, regulatory adherence, and corporate responsibility across all operations.',
      body: '<h2 style="font-size: 16px; color: var(--primary-red); font-weight: 700; margin: 0 0 10px;">1. PHILOSOPHY & OBJECTIVE</h2>\n' +
        '<p style="line-height: 1.7; color: #333; margin: 0 0 16px;">This Code of Business Conduct and Ethics applies to all directors, key managerial personnel, and employees of Vasudha Pharma Chem Limited. It reflects our unyielding commitment to uncompromising ethical standards, cGMP compliance, and workplace integrity.</p>\n\n' +
        '<h2 style="font-size: 16px; color: var(--primary-red); font-weight: 700; margin: 24px 0 10px;">2. CORE PRINCIPLES</h2>\n' +
        '<ul style="list-style: none; padding: 0; margin: 0 0 20px;">\n' +
        '  <li style="padding: 6px 0 6px 24px; position: relative; line-height: 1.6; color: #333;"><span style="position: absolute; left: 0; color: var(--primary-red); font-weight: 700;">▸</span><strong>Regulatory & Statutory Compliance:</strong> Full compliance with applicable local and international pharmaceutical regulations including USFDA, WHO-GMP, and EDQM.</li>\n' +
        '  <li style="padding: 6px 0 6px 24px; position: relative; line-height: 1.6; color: #333;"><span style="position: absolute; left: 0; color: var(--primary-red); font-weight: 700;">▸</span><strong>Conflict of Interest:</strong> Avoidance of personal interests that conflict with organizational duties and transparent disclosure to the Board.</li>\n' +
        '  <li style="padding: 6px 0 6px 24px; position: relative; line-height: 1.6; color: #333;"><span style="position: absolute; left: 0; color: var(--primary-red); font-weight: 700;">▸</span><strong>Protection of Assets & Intellectual Property:</strong> Rigorous safeguarding of intellectual property, patented processes, and data confidentiality.</li>\n' +
        '</ul>\n\n' +
        '<div style="background: #F8FAFC; border-left: 4px solid var(--primary-red); padding: 14px 18px; margin: 20px 0; border-radius: 4px;">\n' +
        '  <strong style="color: #0F172A; display: block; margin-bottom: 4px;">Enforcement & Oversight:</strong>\n' +
        '  <p style="margin: 0; font-size: 14px; line-height: 1.6; color: #475569;">The Board of Directors oversees compliance with this Code. Any suspected violation may be reported under the company’s Whistleblower / Vigil Mechanism.</p>\n' +
        '</div>'
    },
    'rpt-policy': {
      title: 'Related Party Transactions Policy',
      menu: 'RPT Policy',
      slug: 'rpt-policy.html',
      desc: 'Framework for identification, review, approval, and reporting of transactions with related parties under Section 188 of Companies Act.',
      body: '<h2 style="font-size: 16px; color: var(--primary-red); font-weight: 700; margin: 0 0 10px;">1. PREAMBLE & STATUTORY BASIS</h2>\n' +
        '<p style="line-height: 1.7; color: #333; margin: 0 0 16px;">This Policy on Related Party Transactions is framed in accordance with Section 188 of the Companies Act, 2013 read with the rules issued thereunder, to ensure proper approval and reporting of transactions between the company and its related parties.</p>\n\n' +
        '<h2 style="font-size: 16px; color: var(--primary-red); font-weight: 700; margin: 24px 0 10px;">2. APPROVAL MECHANISM</h2>\n' +
        '<ul style="list-style: none; padding: 0; margin: 0 0 20px;">\n' +
        '  <li style="padding: 6px 0 6px 24px; position: relative; line-height: 1.6; color: #333;"><span style="position: absolute; left: 0; color: var(--primary-red); font-weight: 700;">▸</span><strong>Audit Committee Approval:</strong> All Related Party Transactions require prior approval of the Audit Committee.</li>\n' +
        '  <li style="padding: 6px 0 6px 24px; position: relative; line-height: 1.6; color: #333;"><span style="position: absolute; left: 0; color: var(--primary-red); font-weight: 700;">▸</span><strong>Omnibus Approvals:</strong> Granted for repetitive transactions in ordinary course of business and on arm’s length basis.</li>\n' +
        '  <li style="padding: 6px 0 6px 24px; position: relative; line-height: 1.6; color: #333;"><span style="position: absolute; left: 0; color: var(--primary-red); font-weight: 700;">▸</span><strong>Board & Shareholder Approval:</strong> Required where transactions exceed prescribed statutory materiality thresholds.</li>\n' +
        '</ul>'
    },
    'dividend-policy': {
      title: 'Dividend Distribution Policy',
      menu: 'Dividend Policy',
      slug: 'dividend-policy.html',
      desc: 'Guidelines governing the recommendation, declaration, and distribution of dividends to shareholders.',
      body: '<h2 style="font-size: 16px; color: var(--primary-red); font-weight: 700; margin: 0 0 10px;">1. PURPOSE & SCOPE</h2>\n' +
        '<p style="line-height: 1.7; color: #333; margin: 0 0 16px;">The objective of this Policy is to establish principles for rewarding shareholders whilst retaining adequate capital to finance future pharmaceutical capacity additions, R&D expansions, and working capital requirements.</p>\n\n' +
        '<h2 style="font-size: 16px; color: var(--primary-red); font-weight: 700; margin: 24px 0 10px;">2. PARAMETERS FOR DIVIDEND DECLARATION</h2>\n' +
        '<ul style="list-style: none; padding: 0; margin: 0 0 20px;">\n' +
        '  <li style="padding: 6px 0 6px 24px; position: relative; line-height: 1.6; color: #333;"><span style="position: absolute; left: 0; color: var(--primary-red); font-weight: 700;">▸</span>Current year net profits and accumulated retained reserves.</li>\n' +
        '  <li style="padding: 6px 0 6px 24px; position: relative; line-height: 1.6; color: #333;"><span style="position: absolute; left: 0; color: var(--primary-red); font-weight: 700;">▸</span>Capital expenditure requirements for manufacturing units and cGMP upgrades.</li>\n' +
        '  <li style="padding: 6px 0 6px 24px; position: relative; line-height: 1.6; color: #333;"><span style="position: absolute; left: 0; color: var(--primary-red); font-weight: 700;">▸</span>Macroeconomic conditions, taxation implications, and cash flow liquidity.</li>\n' +
        '</ul>'
    },
    'risk-policy': {
      title: 'Risk Management Policy',
      menu: 'Risk Management',
      slug: 'risk-management-policy.html',
      desc: 'Identification, evaluation, and mitigation of operational, regulatory, environmental, and financial risks.',
      body: '<h2 style="font-size: 16px; color: var(--primary-red); font-weight: 700; margin: 0 0 10px;">1. RISK MANAGEMENT FRAMEWORK</h2>\n' +
        '<p style="line-height: 1.7; color: #333; margin: 0 0 16px;">Vasudha Pharma Chem Limited recognizes proactive risk management as crucial for sustainable business operations, supply chain resilience, and international regulatory compliance.</p>\n\n' +
        '<h2 style="font-size: 16px; color: var(--primary-red); font-weight: 700; margin: 24px 0 10px;">2. KEY RISK CATEGORIES & OVERSIGHT</h2>\n' +
        '<ul style="list-style: none; padding: 0; margin: 0 0 20px;">\n' +
        '  <li style="padding: 6px 0 6px 24px; position: relative; line-height: 1.6; color: #333;"><span style="position: absolute; left: 0; color: var(--primary-red); font-weight: 700;">▸</span><strong>Regulatory & Quality Compliance:</strong> Continuous audit readiness across all 8 manufacturing facilities.</li>\n' +
        '  <li style="padding: 6px 0 6px 24px; position: relative; line-height: 1.6; color: #333;"><span style="position: absolute; left: 0; color: var(--primary-red); font-weight: 700;">▸</span><strong>Environmental, Health & Safety (EHS):</strong> Zero liquid discharge (ZLD) maintenance and zero incident safety culture.</li>\n' +
        '  <li style="padding: 6px 0 6px 24px; position: relative; line-height: 1.6; color: #333;"><span style="position: absolute; left: 0; color: var(--primary-red); font-weight: 700;">▸</span><strong>Raw Material & Supply Continuity:</strong> Dual sourcing and strategic backward integration in Key Starting Materials (KSMs).</li>\n' +
        '</ul>'
    }
  };

  const p = presets[preset];
  if (!p) return;
  document.getElementById('np_title').value = p.title;
  document.getElementById('np_menu_label').value = p.menu;
  document.getElementById('np_slug').value = p.slug;
  document.getElementById('np_desc').value = p.desc;
  document.getElementById('np_body').value = p.body;
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
