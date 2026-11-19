<?php
/**
 * Site Synchronization Engine for Vasudha Pharma Staff Portal
 *
 * Synchronizes staff portal changes across:
 * 1. Homepage Bottom Foundation Marquee (index.html & founder.html)
 * 2. Product Category Cards (index.html & products.html)
 * 3. Header Buttons, Navigation & Mega-Menus (index.html & products.html)
 */

if (!defined('STAFF_SYNC_LOADED')) {
  define('STAFF_SYNC_LOADED', true);
}

/**
 * Sync Foundation Marquees to founder.html AND index.html
 */
function staff_sync_foundation_marquees_to_html($data, $siteRoot) {
  $success = true;

  // 1. Vasudha Stream
  $vasudha = $data['vasudha'] ?? [];
  $vHtml = '';
  foreach ($vasudha as $src) {
    $vHtml .= '          <div class="vf-shot"><img src="' . htmlspecialchars($src) . '" alt="Vasudha Foundation program" loading="lazy"></div>' . "\n";
  }
  foreach ($vasudha as $src) {
    $vHtml .= '          <div class="vf-shot"><img src="' . htmlspecialchars($src) . '" alt="" loading="lazy" aria-hidden="true"></div>' . "\n";
  }

  // 2. VRRV Stream
  $vrrv = $data['vrrv'] ?? [];
  $vrrvHtml = '';
  foreach ($vrrv as $src) {
    $vrrvHtml .= '          <div class="vf-shot"><img src="' . htmlspecialchars($src) . '" alt="VRRV Family Vasudha Foundation event" loading="lazy"></div>' . "\n";
  }
  foreach ($vrrv as $src) {
    $vrrvHtml .= '          <div class="vf-shot"><img src="' . htmlspecialchars($src) . '" alt="" loading="lazy" aria-hidden="true"></div>' . "\n";
  }

  // 3. Moments Stream
  $moments = $data['moments'] ?? [];
  $mHtml = '';
  foreach ($moments as $idx => $src) {
    $cls = ($idx === 0) ? 'fg-slide is-active' : 'fg-slide';
    $mHtml .= '          <div class="' . $cls . '"><img src="' . htmlspecialchars($src) . '" alt="Vasudha Foundation moment ' . ($idx + 1) . '"></div>' . "\n";
  }
  foreach ($moments as $src) {
    $mHtml .= '          <div class="fg-slide" aria-hidden="true"><img src="' . htmlspecialchars($src) . '" alt="" loading="lazy"></div>' . "\n";
  }

  // Sync to founder.html
  $founderPath = $siteRoot . '/founder.html';
  if (is_file($founderPath)) {
    $fHtml = file_get_contents($founderPath);
    if ($fHtml) {
      $fHtml = preg_replace('/(<div class="vf-track" id="vfTrackVasudha">)[\s\S]*?(<\/div>\s*<\/div>\s*<div id="vrrv-family")/i', '$1' . "\n" . $vHtml . '        $2', $fHtml);
      $fHtml = preg_replace('/(<div class="vf-track" id="vfTrackVrrv">)[\s\S]*?(<\/div>\s*<\/div>\s*<\/div>\s*<\/section>)/i', '$1' . "\n" . $vrrvHtml . '        $2', $fHtml);
      $fHtml = preg_replace('/(<div class="fg-track" id="fgTrackMoments">)[\s\S]*?(<\/div>\s*<button type="button" class="fg-nav fg-prev")/i', '$1' . "\n" . $mHtml . '        $2', $fHtml);
      @file_put_contents($founderPath, $fHtml);
    }
  }

  // Sync to index.html (Homepage Bottom Moments Marquee)
  $indexPath = $siteRoot . '/index.html';
  if (is_file($indexPath)) {
    $indexHtml = file_get_contents($indexPath);
    if ($indexHtml) {
      $indexHtml = preg_replace('/(<div class="fg-track" id="fgTrackMoments">)[\s\S]*?(<\/div>\s*<\/div>\s*<\/div>\s*<\/section>\s*<!-- CTA Banner)/i', '$1' . "\n" . $mHtml . '        </div>' . "\n" . '      </div>' . "\n" . '    </div>' . "\n" . '  </section>' . "\n\n  <!-- CTA Banner', $indexHtml);
      @file_put_contents($indexPath, $indexHtml);
    }
  }

  return $success;
}

/**
 * Render Product Grid Cards HTML
 */
function staff_render_prod_grid_html($cards) {
  $out = '';
  foreach ($cards as $c) {
    $link = htmlspecialchars($c['link'] ?? '#');
    $img = htmlspecialchars($c['image'] ?? '');
    $video = htmlspecialchars($c['video'] ?? '');
    $title = htmlspecialchars($c['title'] ?? '');
    $total = htmlspecialchars($c['total'] ?? '');
    $desc = htmlspecialchars($c['desc'] ?? '');
    $btn = !empty($c['btn_text']) ? $c['btn_text'] : 'View &rarr;';

    $out .= "        <a class=\"prod-card\" href=\"{$link}\">\n";
    $out .= "          <div class=\"prod-media\">";
    if (!empty($video)) {
      $out .= "<video autoplay loop muted playsinline poster=\"{$img}\"><source src=\"{$video}\" type=\"video/mp4\"><img src=\"{$img}\" alt=\"{$title}\" loading=\"lazy\"></video>";
    } else {
      $out .= "<img src=\"{$img}\" alt=\"{$title}\" loading=\"lazy\">";
    }
    $out .= "</div>\n";
    $out .= "          <div class=\"prod-body\">\n";
    $out .= "            <div class=\"prod-head\">\n";
    $out .= "              <h3>{$title}</h3>\n";
    if (!empty($total)) {
      $out .= "              <span class=\"prod-total\">{$total}</span>\n";
    }
    $out .= "            </div>\n";
    $out .= "            <p>{$desc}</p>\n";
    $out .= "            <span class=\"prod-more\">{$btn}</span>\n";
    $out .= "          </div>\n";
    $out .= "        </a>\n";
  }
  return $out;
}

/**
 * Synchronize Product Cards to index.html AND products.html
 */
function staff_sync_product_cards_to_html($cards, $siteRoot) {
  $cardsHtml = staff_render_prod_grid_html($cards);
  $pages = [$siteRoot . '/index.html', $siteRoot . '/products.html'];

  foreach ($pages as $p) {
    if (!is_file($p)) continue;
    $content = file_get_contents($p);
    if (!$content) continue;

    if (strpos($content, '<!-- VP_PRODUCT_CARDS_START -->') !== false && strpos($content, '<!-- VP_PRODUCT_CARDS_END -->') !== false) {
      $pattern = '/(<!-- VP_PRODUCT_CARDS_START -->)[\s\S]*?(<!-- VP_PRODUCT_CARDS_END -->)/i';
      $content = preg_replace($pattern, "$1\n" . rtrim($cardsHtml) . "\n        $2", $content);
    } else {
      // Regex match within the focus-areas products section
      $pattern = '/(<section[^>]*id="products"[^>]*>[\s\S]*?<div class="prod-grid">)[\s\S]*?(<\/div>\s*<\/div>\s*<\/section>)/i';
      $content = preg_replace($pattern, "$1\n        <!-- VP_PRODUCT_CARDS_START -->\n" . rtrim($cardsHtml) . "\n        <!-- VP_PRODUCT_CARDS_END -->\n      $2", $content);
    }
    @file_put_contents($p, $content);
  }
  return true;
}

/**
 * Synchronize Header Mega-Menu feature cards and utility buttons
 */
function staff_sync_header_nav_to_html($navData, $siteRoot) {
  $pages = [
    $siteRoot . '/index.html',
    $siteRoot . '/products.html',
    $siteRoot . '/about-us.html',
    $siteRoot . '/manufacturing.html',
    $siteRoot . '/ehs.html',
    $siteRoot . '/rnd.html',
    $siteRoot . '/founder.html'
  ];

  foreach ($pages as $p) {
    if (!is_file($p)) continue;
    $content = file_get_contents($p);
    if (!$content) continue;

    // 1. Sync mega menu feature cards if defined
    if (!empty($navData['nav_sections'])) {
      foreach ($navData['nav_sections'] as $sec) {
        if (empty($sec['feature_cards'])) continue;
        foreach ($sec['feature_cards'] as $card) {
          $url = $card['url'] ?? '';
          $img = htmlspecialchars($card['image'] ?? '');
          $title = htmlspecialchars($card['title'] ?? '');
          $sub = htmlspecialchars($card['sub'] ?? '');

          if ($url !== '') {
            $cardRegex = '/(<a href="' . preg_quote($url, '/') . '" class="mm-feature-card">)[\s\S]*?(<\/a>)/i';
            if (preg_match($cardRegex, $content)) {
              $replacement = "$1\n" .
                '            <img src="' . $img . '" alt="' . $title . '" onerror="this.parentElement.classList.add(\'no-img\'); this.remove();">' . "\n" .
                '            <span class="mm-feature-text">' . "\n" .
                '              <span class="mm-feature-title">' . $title . '</span>' . "\n" .
                '              <span class="mm-feature-sub">' . $sub . '</span>' . "\n" .
                '            </span>' . "\n" .
                '          $2';
              $content = preg_replace($cardRegex, $replacement, $content, 1);
            }
          }
        }
      }
    }

    @file_put_contents($p, $content);
  }
  return true;
}
