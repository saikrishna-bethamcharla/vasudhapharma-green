</div><!-- /.wrap -->
<footer style="border-top: 1px solid var(--sp-border); padding: 24px 32px; color: var(--sp-text-muted); font-size: 12.5px; background: #FFFFFF; margin-top: 48px;">
  <div style="max-width: 1240px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
    <a href="<?php echo htmlspecialchars(staff_default_desk_url($u ?? null)); ?>" class="logo" style="transform: scale(0.9); transform-origin: left center;" title="Vasudha Operations Portal">
      <div class="logo-hex-wrap" style="width:40px; height:40px;">
        <div class="logo-hex-body">
          <img src="../assets/vasudha-logo.jpg" alt="Vasudha Pharma Chem Limited" class="logo-img">
        </div>
      </div>
      <div class="logo-text">
        <strong style="font-size:13.5px;">VASUDHA PHARMA CHEM LIMITED</strong>
        <span style="font-size:10.5px;">Contributing to affordable health care...</span>
      </div>
    </a>
    <div style="color: #64748B; text-align: right; font-size: 12px; line-height: 1.5;">
      <div style="font-weight:600; color:var(--sp-text-main);">&copy; <?php echo date('Y'); ?> Vasudha Pharma Chem Limited &bull; Operations Portal</div>
      <div style="font-size: 11px; color:#94A3B8; margin-top: 2px;">Strictly Confidential &bull; Authorized Personnel Only &bull; TLS 1.3 Encrypted</div>
    </div>
  </div>
</footer>
<script>
// Table Live Search
document.querySelectorAll('[data-search-target]').forEach(input => {
  input.addEventListener('input', function() {
    const term = this.value.toLowerCase().trim();
    const table = document.querySelector(this.dataset.searchTarget);
    if (!table) return;
    const rows = table.querySelectorAll('tbody tr');
    let visible = 0;
    rows.forEach(r => {
      // Ignore empty placeholder rows
      if (r.querySelector('td[colspan]')) return;
      const text = r.textContent.toLowerCase();
      if (text.includes(term)) {
        r.style.display = '';
        visible++;
      } else {
        r.style.display = 'none';
      }
    });
    const emptyRow = table.querySelector('.search-no-results');
    if (visible === 0 && rows.length > 0 && term !== '') {
      if (!emptyRow) {
        const tr = document.createElement('tr');
        tr.className = 'search-no-results';
        tr.innerHTML = `<td colspan="10" style="text-align:center;padding:32px;color:#94A3B8;font-size:13px;">No records found matching "<strong>${term}</strong>"</td>`;
        table.querySelector('tbody').appendChild(tr);
      }
    } else if (emptyRow) {
      emptyRow.remove();
    }
  });
});

// Auto-fade success alert
setTimeout(() => {
  document.querySelectorAll('.ok').forEach(el => {
    el.style.transition = 'opacity 0.6s ease';
    el.style.opacity = '0.35';
  });
}, 5000);
</script>
</body>
</html>
