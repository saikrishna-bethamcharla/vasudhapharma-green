<?php
require __DIR__ . '/includes/auth.php';
staff_require_login();
$u = staff_user();

if (!staff_can('navigation', $u)) {
  header('Location: ' . staff_default_desk_url($u));
  exit;
}

$navData = staff_load_header_nav();
if (empty($navData)) {
  $seed = json_decode(@file_get_contents(__DIR__ . '/data/header-nav.json'), true);
  if (is_array($seed)) {
    $navData = $seed;
    staff_save_header_nav($navData);
  }
}

$currentTab = $_GET['tab'] ?? 'mega';
if (!in_array($currentTab, ['mega', 'buttons', 'columns'], true)) {
  $currentTab = 'mega';
}

$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';

// =========================================================================
// POST ACTIONS
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  staff_verify_csrf();
  $action = $_POST['action'] ?? '';

  // 1. Save / Replace Mega Menu Feature Card (Image, Title, Sub-label, URL)
  if ($action === 'save_mega_card') {
    $secId = trim($_POST['sec_id'] ?? '');
    $cardId = trim($_POST['card_id'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $sub = trim($_POST['sub'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $imagePath = trim($_POST['image_path'] ?? '');

    // Handle Image Upload
    if (!empty($_FILES['card_image_file']['tmp_name']) && is_uploaded_file($_FILES['card_image_file']['tmp_name'])) {
      $ext = strtolower(pathinfo($_FILES['card_image_file']['name'], PATHINFO_EXTENSION));
      $imgInfo = @getimagesize($_FILES['card_image_file']['tmp_name']);
      if (!in_array($ext, ['jpg','jpeg','png','webp','gif'], true) || !$imgInfo) {
        $err = 'Unsupported format. Please upload a valid JPG, PNG, WEBP, or GIF image.';
      } elseif ($_FILES['card_image_file']['size'] > 8 * 1024 * 1024) {
        $err = 'Image must be under 8 MB.';
      } else {
        $siteRoot = dirname(staff_root());
        $dir = $siteRoot . '/assets/img/modern';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $cleanId = preg_replace('/[^a-z0-9_-]/i', '', strtolower($cardId ?: 'navcard'));
        $fileName = 'nav-' . $cleanId . '-' . date('Ymd-His') . '.' . $ext;
        $dest = $dir . '/' . $fileName;
        if (move_uploaded_file($_FILES['card_image_file']['tmp_name'], $dest)) {
          $imagePath = 'assets/img/modern/' . $fileName;
        } else {
          $err = 'Could not save uploaded mega-menu image.';
        }
      }
    }

    if (!$err && $secId !== '') {
      $found = false;
      if (!empty($navData['nav_sections'])) {
        foreach ($navData['nav_sections'] as &$sec) {
          if (($sec['id'] ?? '') === $secId && !empty($sec['feature_cards'])) {
            foreach ($sec['feature_cards'] as &$fc) {
              if (($fc['id'] ?? '') === $cardId) {
                $fc['title'] = $title;
                $fc['sub'] = $sub;
                $fc['url'] = $url;
                if ($imagePath !== '') $fc['image'] = $imagePath;
                $found = true;
                break 2;
              }
            }
          }
        }
      }

      if ($found) {
        staff_save_header_nav($navData);
        $siteRoot = dirname(staff_root());
        staff_sync_header_nav_to_html($navData, $siteRoot);
        staff_log_audit('mega_card_updated', "Updated mega menu feature card '{$title}' in {$secId}", $cardId, 'Header Navigation');
        $msg = 'Mega-menu feature card "' . htmlspecialchars($title) . '" successfully saved and synchronized across the website.';
      } else {
        $err = 'Card record not found in navigation registry.';
      }
    }

    header('Location: navigation.php?tab=mega&msg=' . urlencode($msg) . ($err ? ('&err=' . urlencode($err)) : ''));
    exit;
  }

  // 2. Save Header Top Utility Button
  if ($action === 'save_utility_button') {
    $btnId = trim($_POST['btn_id'] ?? '');
    $label = trim($_POST['label'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $target = trim($_POST['target'] ?? '_self');

    if ($btnId !== '' && $label !== '') {
      $found = false;
      if (!empty($navData['utility_buttons'])) {
        foreach ($navData['utility_buttons'] as &$btn) {
          if (($btn['id'] ?? '') === $btnId) {
            $btn['label'] = $label;
            $btn['url'] = $url;
            $btn['target'] = $target;
            $found = true;
            break;
          }
        }
      }
      if ($found) {
        staff_save_header_nav($navData);
        $siteRoot = dirname(staff_root());
        staff_sync_header_nav_to_html($navData, $siteRoot);
        staff_log_audit('utility_btn_updated', "Updated utility button '{$label}'", $btnId, 'Header Navigation');
        $msg = 'Header utility button "' . htmlspecialchars($label) . '" successfully updated.';
      }
    }
    header('Location: navigation.php?tab=buttons&msg=' . urlencode($msg));
    exit;
  }

  // 3. Re-sync Header Navigation across all site pages
  if ($action === 'resync_header_nav') {
    $siteRoot = dirname(staff_root());
    staff_sync_header_nav_to_html($navData, $siteRoot);
    staff_log_audit('header_nav_resync', "Re-synchronized header and mega-menus across site", 'header_nav', 'Header Navigation');
    $msg = 'Header navigation and mega-menus successfully re-synchronized across all website pages.';
    header('Location: navigation.php?tab=' . urlencode($currentTab) . '&msg=' . urlencode($msg));
    exit;
  }
}

$staff_title = 'Header, Buttons & Sub-Menus';
require __DIR__ . '/includes/header.php';
?>

<!-- Executive Desk Header Banner -->
<div class="desk-hero-banner" style="margin-bottom: 22px;">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; position:relative; z-index:1;">
    <div>
      <div style="text-transform:uppercase; font-size:11px; letter-spacing:0.12em; opacity:0.85; font-weight:700; margin-bottom:8px; display:flex; align-items:center; gap:8px;">
        <span style="display:inline-block; width:7px; height:7px; background:#10B981; border-radius:50%; box-shadow:0 0 0 2px rgba(16,185,129,0.3);"></span>
        <span>Site Presentation &bull; Vasudha Pharma</span>
      </div>
      <h1 style="margin:0 0 6px; font-size:24px; color:#FFFFFF; letter-spacing:-0.03em;">Header Buttons, Navigation &amp; Mega-Menus</h1>
      <p style="margin:0; opacity:0.9; font-size:13px; color:#E2E8F0;">
        Manage header buttons, sub-menus, and upload or replace mega-menu images across the entire website.
      </p>
    </div>

    <div style="display:flex; gap:10px; flex-wrap:wrap;">
      <form method="post" style="display:inline;">
        <?php echo staff_csrf_field(); ?>
        <input type="hidden" name="action" value="resync_header_nav">
        <button type="submit" class="btn" style="background:rgba(255,255,255,0.14); color:#FFFFFF; border:1px solid rgba(255,255,255,0.22); font-weight:600; font-size:12.5px;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
          Re-sync All Pages
        </button>
      </form>
      <a href="../index.html" target="_blank" rel="noopener" class="btn" style="background:#FFFFFF; color:var(--sp-primary-dark); font-weight:700; font-size:12.5px; box-shadow:0 4px 12px rgba(0,0,0,0.15);">
        Live Header &rarr;
      </a>
    </div>
  </div>
</div>

<?php if ($msg): ?>
  <div class="card ok" style="margin-bottom:20px;">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
    <span><?php echo htmlspecialchars($msg); ?></span>
  </div>
<?php endif; ?>

<?php if ($err): ?>
  <div class="card danger" style="margin-bottom:20px; background:#FEF2F2; border-color:#FECACA; color:#991B1B;">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <span><?php echo htmlspecialchars($err); ?></span>
  </div>
<?php endif; ?>

<!-- Sub-Desk Navigation Tabs -->
<div style="display:inline-flex; background:#E2E8F0; padding:4px; border-radius:10px; gap:4px; margin-bottom:22px; flex-wrap:wrap;">
  <a href="navigation.php?tab=mega" class="btn" style="<?php echo $currentTab === 'mega' ? 'background:var(--sp-primary); color:#FFFFFF; font-weight:600; box-shadow:0 1px 3px rgba(0,0,0,0.1);' : 'background:transparent; color:#475569; font-weight:500; border:none; box-shadow:none;'; ?> font-size:12.5px; border-radius:8px; padding:7px 14px;">
    <span>Mega-Menu Feature Images &amp; Cards</span>
  </a>
  <a href="navigation.php?tab=buttons" class="btn" style="<?php echo $currentTab === 'buttons' ? 'background:var(--sp-primary); color:#FFFFFF; font-weight:600; box-shadow:0 1px 3px rgba(0,0,0,0.1);' : 'background:transparent; color:#475569; font-weight:500; border:none; box-shadow:none;'; ?> font-size:12.5px; border-radius:8px; padding:7px 14px;">
    <span>Header Top Utility Buttons</span>
    <span style="background:<?php echo $currentTab === 'buttons' ? 'rgba(255,255,255,0.25)' : '#CBD5E1'; ?>; color:<?php echo $currentTab === 'buttons' ? '#FFFFFF' : '#334155'; ?>; padding:1px 7px; border-radius:10px; font-size:11px; margin-left:4px;"><?php echo count($navData['utility_buttons'] ?? []); ?></span>
  </a>
  <a href="navigation.php?tab=columns" class="btn" style="<?php echo $currentTab === 'columns' ? 'background:var(--sp-primary); color:#FFFFFF; font-weight:600; box-shadow:0 1px 3px rgba(0,0,0,0.1);' : 'background:transparent; color:#475569; font-weight:500; border:none; box-shadow:none;'; ?> font-size:12.5px; border-radius:8px; padding:7px 14px;">
    <span>Sub-Menu Links Overview</span>
  </a>
</div>

<?php if ($currentTab === 'mega'): ?>
  <!-- ======================================================================= -->
  <!-- TAB 1: MEGA-MENU FEATURE IMAGES & CARDS                                 -->
  <!-- ======================================================================= -->

  <div class="card hint" style="margin-bottom:20px;">
    <strong>Mega-Menu Visual Showcase:</strong> Every primary menu item on the website (About Us, Products, Manufacturing, Quality, EHS, R&amp;D, Foundation) has high-impact featured image cards inside its drop-down mega menu. Uploading or changing an image here immediately updates that mega-menu across the entire site.
  </div>

  <?php if (!empty($navData['nav_sections'])): ?>
    <?php foreach ($navData['nav_sections'] as $sec):
      if (empty($sec['has_mega']) || empty($sec['feature_cards'])) continue;
    ?>
      <div class="card" style="margin-bottom:24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; border-bottom:1px solid #E2E8F0; padding-bottom:12px;">
          <div style="display:flex; align-items:center; gap:10px;">
            <span style="background:#ECFDF5; color:#065F46; border:1px solid #A7F3D0; font-size:12px; font-weight:700; padding:2px 10px; border-radius:12px;">
              Menu: <?php echo htmlspecialchars($sec['label']); ?>
            </span>
            <span style="font-size:12px; color:#64748B;"><code><?php echo htmlspecialchars($sec['url']); ?></code></span>
          </div>
          <span style="font-size:12px; font-weight:600; color:#0E8F6C;">
            <?php echo count($sec['feature_cards']); ?> Featured Cards
          </span>
        </div>

        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap:18px;">
          <?php foreach ($sec['feature_cards'] as $cIdx => $fc): ?>
            <div style="border:1px solid var(--sp-border); border-radius:10px; padding:16px; background:#FAFAFA; display:flex; flex-direction:column; justify-content:space-between;">
              <div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                  <span style="font-size:11px; font-weight:700; color:#64748B; text-transform:uppercase;">Feature Card #<?php echo ($cIdx + 1); ?></span>
                  <code style="font-size:11px; background:#E2E8F0; padding:2px 6px; border-radius:4px;"><?php echo htmlspecialchars($fc['id'] ?? ''); ?></code>
                </div>

                <div style="position:relative; height:140px; border-radius:8px; overflow:hidden; background:#0F172A; margin-bottom:12px; border:1px solid #E2E8F0;">
                  <img src="../<?php echo htmlspecialchars($fc['image']); ?>" alt="<?php echo htmlspecialchars($fc['title']); ?>" style="width:100%; height:100%; object-fit:cover;">
                  <div style="position:absolute; bottom:0; inset-inline:0; padding:8px 10px; background:linear-gradient(to top, rgba(0,0,0,0.8) 0%, transparent 100%);">
                    <div style="font-size:13px; font-weight:700; color:#fff;"><?php echo htmlspecialchars($fc['title']); ?></div>
                    <div style="font-size:11px; color:rgba(255,255,255,0.8);"><?php echo htmlspecialchars($fc['sub']); ?></div>
                  </div>
                </div>

                <div style="font-size:11.5px; color:#475569; margin-bottom:12px; line-height:1.5;">
                  <div><strong>Destination URL:</strong> <code><?php echo htmlspecialchars($fc['url']); ?></code></div>
                  <div style="margin-top:2px;"><strong>Image Path:</strong> <code style="word-break:break-all;"><?php echo htmlspecialchars($fc['image']); ?></code></div>
                </div>
              </div>

              <div>
                <button type="button" class="btn btn-primary" style="width:100%; font-size:12px; font-weight:600; justify-content:center;" onclick='openEditMegaCardModal("<?php echo htmlspecialchars($sec['id']); ?>", <?php echo json_encode($fc, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES); ?>)'>
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  Replace Photo &amp; Edit Card
                </button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <!-- Edit Mega Menu Card Modal -->
  <div id="editMegaCardModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.65); z-index:9999; align-items:center; justify-content:center; padding:20px; backdrop-filter:blur(3px);">
    <div class="card" style="max-width:520px; width:100%; box-shadow:var(--sp-shadow-lg); position:relative; border-radius:12px; background:#fff; padding:24px;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; border-bottom:1px solid #E2E8F0; padding-bottom:12px;">
        <h3 style="margin:0; font-size:17px; color:#0F172A; font-weight:700;">Edit Mega-Menu Feature Card</h3>
        <button type="button" onclick="closeEditMegaCardModal()" style="background:none; border:none; font-size:24px; cursor:pointer; color:#64748B; line-height:1;">&times;</button>
      </div>

      <form method="post" enctype="multipart/form-data">
        <?php echo staff_csrf_field(); ?>
        <input type="hidden" name="action" value="save_mega_card">
        <input type="hidden" name="sec_id" id="editSecId" value="">
        <input type="hidden" name="card_id" id="editCardId" value="">

        <div style="margin-bottom:12px;">
          <label style="font-weight:700; font-size:12.5px; color:#1E293B;">Card Headline / Title *</label>
          <input type="text" name="title" id="editCardTitle" required style="width:100%;">
        </div>

        <div style="margin-bottom:12px;">
          <label style="font-weight:700; font-size:12.5px; color:#1E293B;">Sub-title / Tagline</label>
          <input type="text" name="sub" id="editCardSub" style="width:100%;">
        </div>

        <div style="margin-bottom:14px;">
          <label style="font-weight:700; font-size:12.5px; color:#1E293B;">Destination URL / Anchor</label>
          <input type="text" name="url" id="editCardUrl" style="width:100%;">
        </div>

        <!-- Image Upload Section -->
        <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:14px; margin-bottom:16px;">
          <label style="font-weight:700; font-size:12.5px; color:#006039; display:flex; align-items:center; gap:6px; margin-top:0;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            Card Image (Upload New or Choose Asset)
          </label>
          <div style="display:flex; gap:12px; align-items:center; margin-top:8px;">
            <img id="editCardImgPreview" src="" alt="Preview" style="width:80px; height:56px; object-fit:cover; border-radius:6px; border:1px solid #CBD5E1; background:#fff;">
            <div style="flex:1;">
              <span style="font-size:11px; font-weight:600; color:#475569; display:block; margin-bottom:3px;">Upload new photo:</span>
              <input type="file" name="card_image_file" accept="image/jpeg,image/png,image/webp,image/gif" style="font-size:12px; width:100%;">
            </div>
          </div>
          <div style="margin-top:10px;">
            <span style="font-size:11px; font-weight:600; color:#475569; display:block; margin-bottom:3px;">Or existing asset path:</span>
            <input type="text" name="image_path" id="editCardImagePath" style="width:100%; font-family:'JetBrains Mono', monospace; font-size:12px;">
          </div>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:10px; border-top:1px solid #E2E8F0; padding-top:14px;">
          <button type="button" class="btn btn-ghost" onclick="closeEditMegaCardModal()">Cancel</button>
          <button type="submit" class="btn btn-primary" style="font-weight:700;">Save &amp; Sync Card</button>
        </div>
      </form>
    </div>
  </div>

  <script>
  function openEditMegaCardModal(secId, card) {
    document.getElementById('editSecId').value = secId || '';
    document.getElementById('editCardId').value = card.id || '';
    document.getElementById('editCardTitle').value = card.title || '';
    document.getElementById('editCardSub').value = card.sub || '';
    document.getElementById('editCardUrl').value = card.url || '';
    document.getElementById('editCardImagePath').value = card.image || '';
    document.getElementById('editCardImgPreview').src = card.image ? ('../' + card.image) : '';
    document.getElementById('editMegaCardModal').style.display = 'flex';
  }
  function closeEditMegaCardModal() {
    document.getElementById('editMegaCardModal').style.display = 'none';
  }
  window.addEventListener('click', function(e) {
    if (e.target === document.getElementById('editMegaCardModal')) closeEditMegaCardModal();
  });
  </script>

<?php elseif ($currentTab === 'buttons'): ?>
  <!-- ======================================================================= -->
  <!-- TAB 2: HEADER TOP UTILITY BUTTONS                                       -->
  <!-- ======================================================================= -->

  <div class="card hint" style="margin-bottom:20px;">
    <strong>Header Top Bar Utility Buttons:</strong> Manage the links and buttons that sit on the top bar of the website (above the main navigation bar).
  </div>

  <div class="card">
    <h2 style="margin:0 0 16px; font-size:17px;">Top Bar Utility Buttons &amp; Action Links</h2>

    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>Button ID</th>
            <th>Button Label</th>
            <th>Target URL</th>
            <th>Window Target</th>
            <th style="text-align:right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($navData['utility_buttons'] as $btn): ?>
            <tr>
              <td><code><?php echo htmlspecialchars($btn['id']); ?></code></td>
              <td><strong><?php echo htmlspecialchars($btn['label']); ?></strong></td>
              <td><code style="color:#006039;"><?php echo htmlspecialchars($btn['url']); ?></code></td>
              <td><span class="badge-open"><?php echo htmlspecialchars($btn['target'] ?? '_self'); ?></span></td>
              <td style="text-align:right;">
                <button type="button" class="btn btn-primary" style="font-size:11.5px; padding:4px 10px;" onclick='openEditUtilityBtnModal(<?php echo json_encode($btn, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES); ?>)'>
                  Edit Button
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Edit Utility Button Modal -->
  <div id="editUtilityBtnModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.65); z-index:9999; align-items:center; justify-content:center; padding:20px; backdrop-filter:blur(3px);">
    <div class="card" style="max-width:440px; width:100%; box-shadow:var(--sp-shadow-lg); position:relative; border-radius:12px; background:#fff; padding:24px;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; border-bottom:1px solid #E2E8F0; padding-bottom:12px;">
        <h3 style="margin:0; font-size:17px; color:#0F172A; font-weight:700;">Edit Header Utility Button</h3>
        <button type="button" onclick="closeEditUtilityBtnModal()" style="background:none; border:none; font-size:24px; cursor:pointer; color:#64748B; line-height:1;">&times;</button>
      </div>

      <form method="post">
        <?php echo staff_csrf_field(); ?>
        <input type="hidden" name="action" value="save_utility_button">
        <input type="hidden" name="btn_id" id="editBtnId" value="">

        <div style="margin-bottom:12px;">
          <label style="font-weight:700; font-size:12.5px; color:#1E293B;">Button Label *</label>
          <input type="text" name="label" id="editBtnLabel" required style="width:100%;">
        </div>

        <div style="margin-bottom:12px;">
          <label style="font-weight:700; font-size:12.5px; color:#1E293B;">Target URL *</label>
          <input type="text" name="url" id="editBtnUrl" required style="width:100%;">
        </div>

        <div style="margin-bottom:16px;">
          <label style="font-weight:700; font-size:12.5px; color:#1E293B;">Target Behavior</label>
          <select name="target" id="editBtnTarget" style="width:100%;">
            <option value="_self">Same Tab (_self)</option>
            <option value="_blank">New Tab (_blank)</option>
          </select>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:10px; border-top:1px solid #E2E8F0; padding-top:14px;">
          <button type="button" class="btn btn-ghost" onclick="closeEditUtilityBtnModal()">Cancel</button>
          <button type="submit" class="btn btn-primary" style="font-weight:700;">Save Button</button>
        </div>
      </form>
    </div>
  </div>

  <script>
  function openEditUtilityBtnModal(btn) {
    document.getElementById('editBtnId').value = btn.id || '';
    document.getElementById('editBtnLabel').value = btn.label || '';
    document.getElementById('editBtnUrl').value = btn.url || '';
    document.getElementById('editBtnTarget').value = btn.target || '_self';
    document.getElementById('editUtilityBtnModal').style.display = 'flex';
  }
  function closeEditUtilityBtnModal() {
    document.getElementById('editUtilityBtnModal').style.display = 'none';
  }
  </script>

<?php elseif ($currentTab === 'columns'): ?>
  <!-- ======================================================================= -->
  <!-- TAB 3: SUB-MENU COLUMNS & LINKS OVERVIEW                                -->
  <!-- ======================================================================= -->

  <div class="card hint" style="margin-bottom:20px;">
    <strong>Mega-Menu Navigation Columns:</strong> Overview of all sub-menu links organized by menu category.
  </div>

  <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap:20px;">
    <?php foreach ($navData['nav_sections'] as $sec):
      if (empty($sec['columns'])) continue;
    ?>
      <div class="card" style="padding:18px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; border-bottom:1px solid #E2E8F0; padding-bottom:10px;">
          <h3 style="margin:0; font-size:16px; color:var(--sp-primary); font-weight:700;">
            <?php echo htmlspecialchars($sec['label']); ?>
          </h3>
          <span class="badge-open"><?php echo count($sec['columns']); ?> Columns</span>
        </div>

        <?php foreach ($sec['columns'] as $col): ?>
          <div style="margin-bottom:14px;">
            <h5 style="margin:0 0 6px; font-size:13px; color:#1E293B; font-weight:700; text-transform:uppercase; letter-spacing:0.04em;">
              <?php echo htmlspecialchars($col['heading']); ?>
            </h5>
            <ul style="margin:0; padding-left:18px; font-size:12.5px; color:#475569;">
              <?php foreach ($col['links'] as $link): ?>
                <li style="margin-bottom:4px;">
                  <strong><?php echo htmlspecialchars($link['label']); ?></strong>
                  <?php if (!empty($link['sub'])): ?>
                    <span style="color:#64748B;">&mdash; <?php echo htmlspecialchars($link['sub']); ?></span>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
