/**
 * Vasudha Pharma — CDMO / Custom Synthesis Project Feasibility Estimator
 * Real-time calculation matching chemical reactions, reactor metallurgy, plant capacity,
 * cGMP standards, and project dossier generation.
 */
(function () {
  'use strict';

  var SCALES = {
    scouting: { label: 'Route Scouting & Kilo Lab (< 1 kg)', factor: 1, duration: '4 – 6 Weeks', batch: '< 5 kg' },
    pilot: { label: 'Clinical / Pilot Plant Scale-up (10 kg – 100 kg)', factor: 2, duration: '8 – 12 Weeks', batch: '10 – 100 kg' },
    commercial: { label: 'Commercial Multi-Ton Production (> 1,000 kg / year)', factor: 3, duration: '12 – 16 Weeks (Validation)', batch: '500 – 2,500 kg' }
  };

  var CHEMISTRIES = {
    piperidone: {
      name: 'Piperidone / Piperidine Derivative Synthesis',
      core: true,
      metallurgy: 'Glass-Lined Reactor (GLR) & Stainless Steel (SS316)',
      plant: 'Unit-I (Hyderabad) & Unit-III (Vizag)',
      notes: 'Vasudha’s flagship core specialty with backwards-integrated raw material pipeline.'
    },
    hydrogenation: {
      name: 'Catalytic Hydrogenation',
      core: false,
      metallurgy: 'High-Pressure Autoclave & Hastelloy C-276',
      plant: 'Unit-III (Vizag) Dedicated Hydrogenation Suite',
      notes: 'Equipped with precision Parr autoclaves and dedicated hydrogen utility headers.'
    },
    low_temp: {
      name: 'Low Temperature Synthesis',
      core: false,
      metallurgy: 'Glass-Lined and Stainless Steel Reactors with Chilling Systems',
      plant: 'Unit-V (Vizag SEZ)',
      notes: 'Controlled reaction handling with continuous parameter monitoring.'
    },
    halogenation: {
      name: 'Chlorination / Bromination / Halogenation',
      core: true,
      metallurgy: 'Corrosion-Resistant Glass-Lined (GLR) & Teflon Piping',
      plant: 'Shriram Chlorochem & Unit-II',
      notes: 'Dedicated captive chlorination infrastructure with integrated scrubber units.'
    },
    chiral: {
      name: 'Complex Chiral Resolution & Multi-Step API',
      core: false,
      metallurgy: 'Stainless Steel SS316 & Agitated Nutsche Filter Dryers (ANFD)',
      plant: 'Unit-III & R&D Center',
      notes: 'Supported by advanced HPLC/GC-MS chiral chromatography suites.'
    }
  };

  var REG_STANDARDS = {
    usfda: { label: 'USFDA cGMP (United States — Type II USDMF)', unit: 'Unit-III / Unit-I / Unit-V', audit: 'Audited & Approved by USFDA' },
    edqm: { label: 'EDQM / EU-GMP (Europe — CEP Filings)', unit: 'Unit-III / Unit-I', audit: 'EU-GMP Certified Sites' },
    pmda: { label: 'PMDA (Japan) & MFDS (Korea)', unit: 'Unit-III (Vizag)', audit: 'PMDA & MFDS Compliant' },
    who: { label: 'WHO-GMP & Schedule M (Global / Domestic)', unit: 'All Operational Units (1 to 5)', audit: 'WHO-GMP Approved' }
  };

  function initEstimator() {
    var form = document.getElementById('vpCdmoForm');
    if (!form) return;

    function recalculate() {
      var scaleRadio = form.querySelector('input[name="scale"]:checked');
      var chemRadio = form.querySelector('input[name="chemistry"]:checked');
      var regRadio = form.querySelector('input[name="regulatory"]:checked');

      var scaleKey = scaleRadio ? scaleRadio.value : 'pilot';
      var chemKey = chemRadio ? chemRadio.value : 'piperidone';
      var regKey = regRadio ? regRadio.value : 'usfda';

      var scale = SCALES[scaleKey] || SCALES.pilot;
      var chem = CHEMISTRIES[chemKey] || CHEMISTRIES.piperidone;
      var reg = REG_STANDARDS[regKey] || REG_STANDARDS.usfda;

      // Update Dossier Summary elements
      var elScale = document.getElementById('resScaleLabel');
      var elChem = document.getElementById('resChemLabel');
      var elReg = document.getElementById('resRegLabel');
      var elFac = document.getElementById('resFacility');
      var elMet = document.getElementById('resMetallurgy');
      var elTime = document.getElementById('resTimeline');
      var elBatch = document.getElementById('resBatchSize');
      var elNotes = document.getElementById('resSpecialNotes');

      if (elScale) elScale.textContent = scale.label;
      if (elChem) elChem.textContent = chem.name;
      if (elReg) elReg.textContent = reg.label;
      if (elFac) elFac.textContent = chem.plant + ' — ' + reg.audit;
      if (elMet) elMet.textContent = chem.metallurgy;
      if (elTime) elTime.textContent = scale.duration;
      if (elBatch) elBatch.textContent = scale.batch;
      if (elNotes) elNotes.textContent = chem.notes;

      // Update hidden inquiry fields
      var hiddenSpec = document.getElementById('cdmoLeadSpec');
      if (hiddenSpec) {
        hiddenSpec.value = 'Scale: ' + scale.label + ' | Chemistry: ' + chem.name + ' | Regulatory: ' + reg.label + ' | Plant: ' + chem.plant;
      }
    }

    form.querySelectorAll('input[type="radio"]').forEach(function (radio) {
      radio.addEventListener('change', recalculate);
    });

    // Run initial calculation
    recalculate();

    // Direct Lead submission handler
    var leadForm = document.getElementById('cdmoLeadForm');
    if (leadForm) {
      leadForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var submitBtn = document.getElementById('cdmoSubmitBtn');
        var successBox = document.getElementById('cdmoLeadSuccess');
        var name = ((document.getElementById('cdmoLeadName') || {}).value || '').trim();
        var email = ((document.getElementById('cdmoLeadEmail') || {}).value || '').trim();
        var company = ((document.getElementById('cdmoLeadCompany') || {}).value || '').trim();
        var message = ((document.getElementById('cdmoLeadMessage') || {}).value || '').trim();
        var spec = ((document.getElementById('cdmoLeadSpec') || {}).value || '').trim();

        var scaleLabel = ((document.getElementById('resScaleLabel') || {}).textContent || '').trim();
        var chemLabel = ((document.getElementById('resChemLabel') || {}).textContent || '').trim();
        var facility = ((document.getElementById('resFacility') || {}).textContent || '').trim();
        var metallurgy = ((document.getElementById('resMetallurgy') || {}).textContent || '').trim();
        var timeline = ((document.getElementById('resTimeline') || {}).textContent || '').trim();
        var batchSize = ((document.getElementById('resBatchSize') || {}).textContent || '').trim();
        var regLabel = ((document.getElementById('resRegLabel') || {}).textContent || '').trim();

        if (!name || !email) {
          alert('Please provide your name and work email address.');
          return;
        }

        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Transmitting Proposal...';
        }

        var technicalDossier = [
          '=== CDMO / CMO PROJECT FEASIBILITY DOSSIER ===',
          'Target Scale: ' + (scaleLabel || 'Pilot / Commercial'),
          'Reaction Chemistry: ' + (chemLabel || 'Custom Synthesis'),
          'Regulatory Compliance: ' + (regLabel || 'USFDA / EDQM'),
          'Allocated Facility: ' + (facility || 'Unit-I / Unit-III'),
          'Assigned Metallurgy: ' + (metallurgy || 'GLR & SS316'),
          'Nominal Batch Capacity: ' + (batchSize || 'Standard'),
          'Estimated Tech Transfer Timeline: ' + (timeline || '8 - 12 Weeks'),
          'Direct Parameters: ' + spec,
          'Sponsor Requirements / Timeline: ' + (message || 'CDA requested prior to tech pack transfer.')
        ].join('\n');

        var payload = {
          enquiryType: 'CDMO Feasibility & CDA Request',
          lastName: name,
          email: email,
          company: company,
          productName: 'CDMO Campaign — ' + (chemLabel || 'Custom Synthesis'),
          productCategory: 'CDMO / CMO Services',
          specifications: spec || (scaleLabel + ' | ' + chemLabel),
          regulatoryStatus: regLabel,
          description: technicalDossier,
          leadSource: 'Website CDMO Estimator'
        };

        fetch('enquiry.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        })
        .then(function (res) {
          return res.json();
        })
        .then(function (data) {
          if (data && data.ok) {
            if (submitBtn) submitBtn.style.display = 'none';
            if (successBox) {
              successBox.innerHTML = '<strong><i class="fas fa-check-circle"></i> CDMO Dossier Dispatched Successfully!</strong><br>' +
                'Reference Tracking ID: <strong>' + (data.id || 'VP-ENQ-CDMO') + '</strong><br>' +
                'Our Technical Operations Team and CDMO Committee have received your campaign parameters. A mutual Non-Disclosure Agreement (CDA) and facility tech pack will be dispatched to <strong>' + email + '</strong> within 24 business hours.';
              successBox.style.display = 'block';
            }
            leadForm.reset();
          } else {
            throw new Error((data && data.error) || 'Failed to submit CDMO inquiry.');
          }
        })
        .catch(function (err) {
          alert('Notice: We encountered a temporary delay submitting your request. Please email cdmo@vasudhapharma.com or contact us directly.');
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-file-contract"></i> Submit Inquiry &amp; Request CDA';
          }
        });
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initEstimator);
  } else {
    initEstimator();
  }
})();
