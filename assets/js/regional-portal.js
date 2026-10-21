/**
 * Vasudha Pharma Chem Limited — Regional Portal Interactive Logic
 * Handles language picker, category filtering, CAS copy, and direct RFQ pre-fill
 * Fully integrated with enquiry.php for real-time lead dispatch and dual email alerts.
 */

document.addEventListener('DOMContentLoaded', function () {
  // 1. Language Dropdown Toggle
  var langPicker = document.querySelector('.rp-lang-picker');
  var langBtn = document.querySelector('.rp-lang-btn');

  if (langPicker && langBtn) {
    langBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      langPicker.classList.toggle('is-open');
    });

    document.addEventListener('click', function (e) {
      if (!langPicker.contains(e.target)) {
        langPicker.classList.remove('is-open');
      }
    });
  }

  // 2. Product Category Filtering
  var filterBtns = document.querySelectorAll('.rp-filter-btn');
  var productRows = document.querySelectorAll('.rp-table tbody tr');

  filterBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      filterBtns.forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');

      var cat = btn.getAttribute('data-filter');

      productRows.forEach(function (row) {
        if (cat === 'all' || row.getAttribute('data-category') === cat) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    });
  });

  // 3. CAS Number 1-Click Copy
  var casPills = document.querySelectorAll('.rp-cas-pill');
  casPills.forEach(function (pill) {
    pill.addEventListener('click', function () {
      var text = pill.getAttribute('data-cas') || pill.textContent.trim();
      navigator.clipboard.writeText(text).then(function () {
        var orig = pill.innerHTML;
        pill.innerHTML = '✓ Copied';
        setTimeout(function () {
          pill.innerHTML = orig;
        }, 1500);
      });
    });
  });

  // 4. Inquire Button Pre-fill
  var inquireBtns = document.querySelectorAll('.rp-inquire-btn');
  var productInput = document.getElementById('rpInquiryProduct');
  var contactSection = document.getElementById('contact');

  inquireBtns.forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      var mol = btn.getAttribute('data-mol');
      if (mol && productInput) {
        productInput.value = mol;
      }
      if (contactSection) {
        contactSection.scrollIntoView({ behavior: 'smooth' });
      }
    });
  });

  // 5. Contact Form Handler (Direct Backend Integration)
  var form = document.getElementById('rpInquiryForm');
  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var submitBtn = form.querySelector('.rp-form-submit');
      var origText = submitBtn ? submitBtn.textContent : 'Submit';

      var product = ((document.getElementById('rpInquiryProduct') || {}).value || '').trim();
      var quantity = ((document.getElementById('rpQuantity') || {}).value || '').trim();
      var grade = ((document.getElementById('rpGrade') || {}).value || '').trim();
      var name = ((document.getElementById('rpName') || {}).value || '').trim();
      var company = ((document.getElementById('rpCompany') || {}).value || '').trim();
      var email = ((document.getElementById('rpEmail') || {}).value || '').trim();
      var phone = ((document.getElementById('rpPhone') || {}).value || '').trim();
      var message = ((document.getElementById('rpMessage') || {}).value || '').trim();

      if (!name || !email || !product) {
        alert('Please fill in the required fields: Product, Name, and Email.');
        return;
      }

      var lang = (document.documentElement.lang || '').toLowerCase();
      if (!lang) {
        var path = window.location.pathname.toLowerCase();
        if (path.indexOf('de.html') !== -1) lang = 'de';
        else if (path.indexOf('es.html') !== -1) lang = 'es';
        else if (path.indexOf('ar.html') !== -1) lang = 'ar';
        else if (path.indexOf('ja.html') !== -1) lang = 'ja';
        else if (path.indexOf('hi.html') !== -1) lang = 'hi';
        else lang = 'en';
      }

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Transmitting / Senden...';
      }

      var payload = {
        enquiryType: 'Regional Portal Inquiry',
        lastName: name,
        email: email,
        phone: phone,
        company: company,
        productName: product,
        quantityRequired: quantity,
        specifications: 'Pharmacopoeia: ' + (grade || 'Standard'),
        description: [
          '=== REGIONAL PORTAL INQUIRY (' + lang.toUpperCase() + ') ===',
          'Product / Molecule: ' + product,
          'Required Quantity: ' + (quantity || 'Not specified'),
          'Pharmacopoeia Standard: ' + (grade || 'Standard'),
          'Company: ' + (company || 'Not provided'),
          'Phone: ' + (phone || 'Not provided'),
          'Client Requirements: ' + (message || 'Regional sourcing enquiry')
        ].join('\n'),
        leadSource: 'Regional Portal (' + lang.toUpperCase() + ')'
      };

      fetch('enquiry.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data && data.ok) {
          var refId = data.id || 'VP-ENQ';
          var confirmMsg = 'Thank you! Your regional inquiry has been registered (Ref: ' + refId + '). Our team will contact you within 24 business hours.';
          if (lang === 'de') {
            confirmMsg = 'Vielen Dank! Ihre Anfrage für ' + product + ' wurde registriert (Ref: ' + refId + '). Unser Vertriebsteam wird sich innerhalb von 24 Stunden mit Ihnen in Verbindung setzen.';
          } else if (lang === 'es') {
            confirmMsg = '¡Gracias! Su consulta para ' + product + ' ha sido registrada (Ref: ' + refId + '). Nuestro equipo comercial se comunicará dentro de las 24 horas hábiles.';
          } else if (lang === 'ar') {
            confirmMsg = 'شكراً لك! تم تسجيل طلبك لـ ' + product + ' بنجاح (المرجع: ' + refId + '). سيتواصل معك فريقنا خلال 24 ساعة عمل.';
          } else if (lang === 'ja') {
            confirmMsg = 'お問い合わせありがとうございます。' + product + ' のお見積り依頼を承りました（参照ID: ' + refId + '）。24営業時間内に担当者よりご連絡いたします。';
          } else if (lang === 'hi') {
            confirmMsg = 'धन्यवाद! ' + product + ' के लिए आपका अनुरोध दर्ज कर लिया गया है (संदर्भ: ' + refId + ')। हमारी टीम 24 कार्य घंटों में आपसे संपर्क करेगी।';
          }

          var successBox = document.createElement('div');
          successBox.className = 'rp-inquiry-success';
          successBox.style.cssText = 'background:#DCFCE7; border:1px solid #86EFAC; border-radius:10px; padding:18px; color:#166534; margin-top:16px; font-size:13.5px; line-height:1.6;';
          successBox.innerHTML = '<strong style="display:block; font-size:15px; margin-bottom:4px;"><i class="fas fa-check-circle"></i> ' + confirmMsg + '</strong>' +
            '<span style="font-size:12px; color:#15803D;">A technical dossier and quotation will be forwarded to <strong>' + email + '</strong>.</span>';

          form.style.display = 'none';
          form.parentNode.appendChild(successBox);
        } else {
          throw new Error((data && data.error) || 'Failed to submit inquiry.');
        }
      })
      .catch(function (err) {
        alert('Notice: Unable to send inquiry at this moment. Please email wisdom@vasudhapharma.com directly.');
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = origText;
        }
      });
    });
  }
});
