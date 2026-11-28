/**
 * Vasudha Pharma Chem Limited — Sustainability Report Request Gate
 * Intercepts clicks on Sustainability Report links, collects Name, Email ID,
 * Contact Number, and Product Details, emails cro@vasudhapharma.com via enquiry.php,
 * and unlocks the 2025-26 Sustainability Report PDF.
 */
(function () {
  'use strict';

  var REPORT_URL = 'assets/Vasudha_Sustainability_Report_2025-26.pdf';
  var modalEl = null;

  function ensureModal() {
    if (modalEl) return modalEl;

    var style = document.createElement('style');
    style.textContent = [
      '.vp-sust-backdrop{position:fixed;inset:0;background:rgba(15,23,42,0.72);backdrop-filter:blur(4px);z-index:99999;display:none;align-items:center;justify-content:center;padding:16px;}',
      '.vp-sust-backdrop.open{display:flex;}',
      '.vp-sust-modal{background:#ffffff;color:#0f172a;width:100%;max-width:480px;border-radius:16px;box-shadow:0 24px 60px rgba(0,0,0,0.28);overflow:hidden;font-family:inherit;position:relative;animation:vpSustPop 0.2s ease-out;}',
      '@keyframes vpSustPop{from{opacity:0;transform:translateY(10px) scale(0.98);}to{opacity:1;transform:translateY(0) scale(1);}}',
      '.vp-sust-head{background:linear-gradient(135deg,#00482B,#006039);color:#ffffff;padding:20px 24px;display:flex;align-items:center;justify-content:space-between;gap:12px;}',
      '.vp-sust-head h3{margin:0;font-size:18px;font-weight:700;color:#ffffff;}',
      '.vp-sust-head p{margin:4px 0 0;font-size:12.5px;color:#D1FAE5;}',
      '.vp-sust-close{background:rgba(255,255,255,0.16);border:none;color:#ffffff;width:32px;height:32px;border-radius:50%;font-size:18px;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;}',
      '.vp-sust-close:hover{background:rgba(255,255,255,0.28);}',
      '.vp-sust-body{padding:22px 24px;}',
      '.vp-sust-field{margin-bottom:14px;}',
      '.vp-sust-field label{display:block;font-size:13px;font-weight:600;color:#1e293b;margin-bottom:5px;}',
      '.vp-sust-field label span{color:#dc2626;}',
      '.vp-sust-field input,.vp-sust-field textarea{width:100%;box-sizing:border-box;padding:10px 12px;border:1.5px solid #cbd5e1;border-radius:8px;font-size:14px;font-family:inherit;color:#0f172a;background:#f8fafc;transition:border-color 0.15s;}',
      '.vp-sust-field input:focus,.vp-sust-field textarea:focus{outline:none;border-color:#006039;background:#ffffff;}',
      '.vp-sust-submit{width:100%;padding:12px 18px;background:#006039;color:#ffffff;border:none;border-radius:8px;font-size:14.5px;font-weight:700;cursor:pointer;transition:background 0.15s;}',
      '.vp-sust-submit:hover{background:#00482B;}',
      '.vp-sust-submit:disabled{opacity:0.65;cursor:not-allowed;}',
      '.vp-sust-msg{padding:12px 14px;border-radius:8px;font-size:13.5px;margin-bottom:14px;display:none;}',
      '.vp-sust-msg.err{display:block;background:#FEF2F2;border:1px solid #FCA5A5;color:#991B1B;}',
      '.vp-sust-msg.ok{display:block;background:#ECFDF5;border:1px solid #A7F3D0;color:#065F46;}'
    ].join('');
    document.head.appendChild(style);

    var backdrop = document.createElement('div');
    backdrop.className = 'vp-sust-backdrop';
    backdrop.setAttribute('role', 'dialog');
    backdrop.setAttribute('aria-modal', 'true');
    backdrop.setAttribute('aria-labelledby', 'vpSustModalTitle');
    backdrop.innerHTML = [
      '<div class="vp-sust-modal">',
      '  <div class="vp-sust-head">',
      '    <div>',
      '      <h3 id="vpSustModalTitle">Sustainability Report 2025&ndash;26</h3>',
      '      <p>Please share your details to access &amp; download the report</p>',
      '    </div>',
      '    <button type="button" class="vp-sust-close" aria-label="Close modal">&times;</button>',
      '  </div>',
      '  <div class="vp-sust-body">',
      '    <div class="vp-sust-msg" id="vpSustStatus"></div>',
      '    <form id="vpSustForm" autocomplete="on">',
      '      <div style="opacity:0;position:absolute;top:0;left:0;height:0;width:0;z-index:-1;overflow:hidden;" aria-hidden="true">',
      '        <input type="text" name="website_url_hp" tabindex="-1" autocomplete="off">',
      '      </div>',
      '      <div class="vp-sust-field">',
      '        <label for="vpSustName">Name <span>*</span></label>',
      '        <input id="vpSustName" name="name" type="text" required placeholder="Your full name">',
      '      </div>',
      '      <div class="vp-sust-field">',
      '        <label for="vpSustEmail">Email ID <span>*</span></label>',
      '        <input id="vpSustEmail" name="email" type="email" required placeholder="name@company.com">',
      '      </div>',
      '      <div class="vp-sust-field">',
      '        <label for="vpSustPhone">Contact Number <span>*</span></label>',
      '        <input id="vpSustPhone" name="phone" type="tel" required placeholder="+91 98765 43210">',
      '      </div>',
      '      <div class="vp-sust-field">',
      '        <label for="vpSustProduct">Product Details (Interested Product) <span>*</span></label>',
      '        <textarea id="vpSustProduct" name="product" rows="2" required placeholder="e.g. Amitriptyline HCl, Pellets, or CDMO enquiry"></textarea>',
      '      </div>',
      '      <button type="submit" class="vp-sust-submit" id="vpSustSubmitBtn">Submit &amp; Download Report</button>',
      '    </form>',
      '  </div>',
      '</div>'
    ].join('');

    document.body.appendChild(backdrop);

    var closeBtn = backdrop.querySelector('.vp-sust-close');
    var form = backdrop.querySelector('#vpSustForm');
    var statusBox = backdrop.querySelector('#vpSustStatus');
    var submitBtn = backdrop.querySelector('#vpSustSubmitBtn');

    function closeModal() {
      backdrop.classList.remove('open');
    }

    closeBtn.addEventListener('click', closeModal);
    backdrop.addEventListener('click', function (e) {
      if (e.target === backdrop) closeModal();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && backdrop.classList.contains('open')) closeModal();
    });

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      statusBox.className = 'vp-sust-msg';
      statusBox.textContent = '';

      var payload = {
        type: 'Sustainability Report',
        name: form.name.value.trim(),
        email: form.email.value.trim(),
        phone: form.phone.value.trim(),
        product: form.product.value.trim(),
        website_url_hp: form.website_url_hp.value
      };

      if (!payload.name || !payload.email || !payload.phone || !payload.product) {
        statusBox.className = 'vp-sust-msg err';
        statusBox.textContent = 'Please fill in all required fields.';
        return;
      }

      submitBtn.disabled = true;
      submitBtn.textContent = 'Submitting...';

      fetch('enquiry.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      })
        .then(function (res) {
          return res.json().then(function (data) {
            return { ok: res.ok, data: data };
          });
        })
        .then(function (result) {
          if (result.ok && result.data && result.data.ok) {
            var dlUrl = result.data.downloadUrl || REPORT_URL;
            statusBox.className = 'vp-sust-msg ok';
            statusBox.innerHTML = 'Thank you! Your enquiry has been sent. <a href="' + dlUrl + '" target="_blank" rel="noopener" style="color:#065F46;font-weight:700;text-decoration:underline;">Click here if the Sustainability Report does not open automatically.</a>';
            form.reset();
            window.open(dlUrl, '_blank', 'noopener');
          } else {
            statusBox.className = 'vp-sust-msg err';
            statusBox.textContent = (result.data && result.data.error) ? result.data.error : 'Unable to submit request. Please try again.';
          }
        })
        .catch(function () {
          statusBox.className = 'vp-sust-msg err';
          statusBox.textContent = 'Network error occurred. Please check your connection and try again.';
        })
        .finally(function () {
          submitBtn.disabled = false;
          submitBtn.textContent = 'Submit & Download Report';
        });
    });

    modalEl = backdrop;
    return modalEl;
  }

  document.addEventListener('click', function (e) {
    var link = e.target && e.target.closest ? e.target.closest('a[href*="Vasudha_Sustainability_Report"]') : null;
    if (!link) return;
    // Allow direct download link inside our own modal success message
    if (link.closest && link.closest('.vp-sust-modal')) return;
    e.preventDefault();
    var m = ensureModal();
    m.classList.add('open');
    var firstInput = m.querySelector('#vpSustName');
    if (firstInput) setTimeout(function () { firstInput.focus(); }, 50);
  });
})();
