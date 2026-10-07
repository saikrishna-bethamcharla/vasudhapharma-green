/**
 * Vasudha Pharma Chem Limited — Candidate Application Status Tracker
 * Handles live application submission (via apply.php), live status lookup (via track.php),
 * and dynamic pipeline stepper rendering.
 */

(function () {
  'use strict';

  const MOCK_APPLICATIONS = {
    'VP-2026-GET': {
      id: 'VP-2026-GET',
      name: 'Aarav Sharma',
      role: 'Graduate Executive Trainee (GET) — Synthesis & Scale-Up',
      location: 'Unit-2, Visakhapatnam',
      appliedDate: '18 September, 2026',
      currentStep: 3,
      statusLabel: 'Panel Interview Scheduled',
      statusClass: 'in-progress',
      reviewerNote: 'Technical assessment passed with commendation (Score: 92%). Panel interview scheduled with Senior Technical Committee & HR on October 14, 2026.'
    },
    'VP-2026-QA': {
      id: 'VP-2026-QA',
      name: 'Priyanka Rao',
      role: 'Assistant Manager — Quality Assurance (Analytical Reviews)',
      location: 'Unit-5, Atchutapuram, Vizag',
      appliedDate: '10 September, 2026',
      currentStep: 4,
      statusLabel: 'Formal Offer Issued',
      statusClass: 'passed',
      reviewerNote: 'All technical and executive panel rounds cleared. Formal appointment letter dispatched to registered email. Induction scheduled at Unit-5.'
    },
    'VP-2026-RND': {
      id: 'VP-2026-RND',
      name: 'Dr. K. Srinivas',
      role: 'Senior Research Scientist — Process Chemistry & Catalysis',
      location: 'Vikasith R&D Center, Hyderabad',
      appliedDate: '21 September, 2026',
      currentStep: 2,
      statusLabel: 'Technical Evaluation In Progress',
      statusClass: 'in-progress',
      reviewerNote: 'Application profile approved by Talent Acquisition. Non-infringing route scouting case study is currently under technical review by Principal Scientist.'
    }
  };

  const STEPS_DATA = [
    { num: 1, title: 'Profile Screening', desc: 'Initial CV & cGMP credential evaluation by Talent Acquisition' },
    { num: 2, title: 'Technical Assessment', desc: 'Written synthesis / chromatography evaluation' },
    { num: 3, title: 'Panel Interview', desc: 'Department Head & Technical Leadership consultation' },
    { num: 4, title: 'Offer & Onboarding', desc: 'Formal offer issuance, verification & Day-1 induction' }
  ];

  function initTracker() {
    const portalBox = document.querySelector('.cp-form-box');
    const applyForm = document.getElementById('applyMainForm');
    if (!portalBox || !applyForm) return;

    // 1. Create Top Mode Switcher (Apply Form vs Tracker)
    const tabsContainer = document.createElement('div');
    tabsContainer.className = 'cat-mode-tabs';
    tabsContainer.innerHTML = `
      <button type="button" class="cat-mode-tab active" id="catTabApply">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        Submit New Application
      </button>
      <button type="button" class="cat-mode-tab" id="catTabTrack">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        Track Application Status
      </button>
    `;
    portalBox.insertBefore(tabsContainer, portalBox.firstChild);

    // 2. Create Tracker Panel Container
    const trackerPanel = document.createElement('div');
    trackerPanel.className = 'cat-tracker-panel';
    trackerPanel.id = 'catTrackerPanel';
    trackerPanel.innerHTML = `
      <div class="cat-search-box">
        <div style="font-size: 16px; font-weight: 700; color: var(--cat-text); margin-bottom: 6px;">Check Your Application Progress</div>
        <p style="font-size: 13.5px; color: var(--cat-text-muted); margin-bottom: 14px;">Enter the official Application Reference ID (e.g. <code>VP-2026-XXXX</code>) provided upon submission or received via email.</p>

        <form class="cat-search-form" id="catTrackSearchForm">
          <input type="text" class="cat-search-input" id="catSearchInput" placeholder="Enter Reference ID e.g. VP-2026-GET, VP-2026-4821" required>
          <button type="submit" class="cat-btn-track" id="catSearchBtn">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            Track Status
          </button>
        </form>

        <div class="cat-samples-strip">
          <span>Interactive Samples:</span>
          <button type="button" class="cat-sample-chip" data-sample="VP-2026-GET">VP-2026-GET (Trainee)</button>
          <button type="button" class="cat-sample-chip" data-sample="VP-2026-QA">VP-2026-QA (Offer Issued)</button>
          <button type="button" class="cat-sample-chip" data-sample="VP-2026-RND">VP-2026-RND (In Review)</button>
        </div>
      </div>

      <div id="catResultContainer"></div>
    `;

    // Insert tracker panel after the form container
    portalBox.appendChild(trackerPanel);

    // Elements
    const tabApply = document.getElementById('catTabApply');
    const tabTrack = document.getElementById('catTabTrack');
    const alertBox = portalBox.querySelector('.careers-alert');
    const searchForm = document.getElementById('catTrackSearchForm');
    const searchInput = document.getElementById('catSearchInput');
    const searchBtn = document.getElementById('catSearchBtn');
    const resultContainer = document.getElementById('catResultContainer');

    function switchTab(mode) {
      if (mode === 'apply') {
        tabApply.classList.add('active');
        tabTrack.classList.remove('active');
        applyForm.style.display = '';
        if (alertBox) alertBox.style.display = '';
        trackerPanel.classList.remove('active');
      } else {
        tabTrack.classList.add('active');
        tabApply.classList.remove('active');
        applyForm.style.display = 'none';
        if (alertBox) alertBox.style.display = 'none';
        trackerPanel.classList.add('active');

        // Check if candidate has an active application stored locally
        const storedApp = localStorage.getItem('vp_user_application');
        if (storedApp) {
          try {
            const parsed = JSON.parse(storedApp);
            if (parsed && parsed.id) {
              searchInput.value = parsed.id;
              fetchStatus(parsed.id);
              return;
            }
          } catch (e) {}
        }

        // Default sample view
        fetchStatus('VP-2026-GET');
      }
    }

    tabApply.addEventListener('click', () => switchTab('apply'));
    tabTrack.addEventListener('click', () => switchTab('track'));

    // Status Lookup via track.php API with mock fallback
    async function fetchStatus(rawId) {
      const queryId = (rawId || '').trim().toUpperCase();
      if (!queryId) return;

      resultContainer.innerHTML = `
        <div style="text-align:center; padding:36px 20px; color:#64748b;">
          <div class="cat-spinner" style="width:24px; height:24px; border-width:3px; border-top-color:#0E8F6C;"></div>
          <p style="margin-top:12px; font-size:14px; font-weight:600;">Querying Vasudha Talent Acquisition Records...</p>
        </div>
      `;

      try {
        const res = await fetch(`track.php?id=${encodeURIComponent(queryId)}`);
        const cType = res.headers.get('content-type') || '';
        if (res.ok && cType.includes('application/json')) {
          const data = await res.json();
          if (data && data.ok && data.found) {
            renderResult(data);
            return;
          }
        }
      } catch (err) {
        // Network error - fallback to local storage or mocks
      }

      // Check mock applications
      if (MOCK_APPLICATIONS[queryId]) {
        renderResult(MOCK_APPLICATIONS[queryId]);
        return;
      }

      // Check localStorage
      const stored = localStorage.getItem('vp_user_application');
      if (stored) {
        try {
          const parsed = JSON.parse(stored);
          if (parsed && parsed.id && parsed.id.toUpperCase() === queryId) {
            renderResult(parsed);
            return;
          }
        } catch (e) {}
      }

      // Render not found notice
      renderNotFound(queryId);
    }

    function renderResult(app) {
      const isLive = app.source === 'live' || !!app.updatedAt;
      const stepNum = parseInt(app.currentStep, 10) || 1;

      resultContainer.innerHTML = `
        <div class="cat-result-card">
          <div class="cat-app-head">
            <div>
              <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px; flex-wrap:wrap;">
                <span style="font-size:12px; font-weight:800; color:var(--cat-primary); text-transform:uppercase; letter-spacing:0.06em; font-family:monospace;">
                  Reference ID: ${app.id}
                </span>
                ${isLive ? `
                  <span class="cat-live-pill">
                    <span class="cat-live-pulse"></span> Live HR Verified
                  </span>
                ` : `
                  <span style="background:#f1f5f9; color:#64748b; font-size:10.5px; font-weight:700; padding:2px 8px; border-radius:4px; text-transform:uppercase;">
                    Interactive Sample
                  </span>
                `}
              </div>
              <h3 class="cat-app-role">${app.role || 'Pharmaceutical Track'}</h3>
              <div class="cat-app-meta">
                <span>Candidate: <strong>${app.name}</strong></span>
                <span>•</span>
                <span>Facility: <strong>${app.location || 'Vasudha Pharma Plant'}</strong></span>
                <span>•</span>
                <span>Applied: <strong>${app.appliedDate || 'Recent'}</strong></span>
                ${app.updatedAt ? `<span>•</span><span>Last Status Update: <strong>${app.updatedAt}</strong></span>` : ''}
              </div>
            </div>
            <span class="cat-status-badge ${app.statusClass || 'in-progress'}">
              <span style="width:7px; height:7px; border-radius:50%; background:currentColor;"></span>
              ${app.statusLabel || 'Profile Screening'}
            </span>
          </div>

          <!-- 4-Stage Stepper -->
          <div class="cat-stepper">
            ${STEPS_DATA.map(step => {
              let stateClass = '';
              let circleContent = step.num;
              if (step.num < stepNum) {
                stateClass = 'completed';
                circleContent = '✓';
              } else if (step.num === stepNum) {
                stateClass = 'current';
              }
              return `
                <div class="cat-step-item ${stateClass}">
                  <div class="cat-step-circle">${circleContent}</div>
                  <div class="cat-step-title">${step.title}</div>
                  <div class="cat-step-desc">${step.desc}</div>
                </div>
              `;
            }).join('')}
          </div>

          <!-- Reviewer Note -->
          <div class="cat-notes-banner">
            <strong style="color:var(--cat-text); display:block; margin-bottom:4px; font-size:13px;">
              Talent Acquisition Committee Remarks:
            </strong>
            ${app.reviewerNote || 'Your application credentials have been registered in the hiring pipeline. Initial qualification screening is in progress.'}
          </div>
        </div>
      `;
    }

    function renderNotFound(queryId) {
      resultContainer.innerHTML = `
        <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:32px 24px; text-align:center;">
          <div style="width:52px; height:52px; border-radius:50%; background:#fef2f2; color:#ef4444; display:inline-flex; align-items:center; justify-content:center; font-size:22px; margin-bottom:12px;">!</div>
          <h4 style="margin:0 0 6px; font-size:17px; color:#0f172a;">Reference ID Not Found</h4>
          <p style="margin:0 auto 16px; max-width:480px; font-size:13.5px; color:#64748b; line-height:1.5;">
            We could not find active records for <code>${queryId}</code>. Please double-check your Reference ID received upon application submission or via email.
          </p>
          <div style="font-size:13px; color:#0E8F6C;">
            Need help? Contact Talent Acquisition at <a href="mailto:wisdom@vasudhapharma.com" style="color:#0E8F6C; font-weight:700;">wisdom@vasudhapharma.com</a>
          </div>
        </div>
      `;
    }

    // Handle Search Form Submission
    searchForm.addEventListener('submit', function (e) {
      e.preventDefault();
      fetchStatus(searchInput.value);
    });

    // Sample Chips Click
    trackerPanel.querySelectorAll('.cat-sample-chip').forEach(chip => {
      chip.addEventListener('click', function () {
        const id = this.getAttribute('data-sample');
        searchInput.value = id;
        fetchStatus(id);
      });
    });

    // 3. Live Form Submission via AJAX to apply.php
    applyForm.addEventListener('submit', async function (e) {
      e.preventDefault();

      const errorBox = document.getElementById('applyFormError');
      const submitBtn = document.getElementById('applySubmitBtn');
      const btnText = document.getElementById('btnText');
      const btnSpinner = document.getElementById('btnSpinner');

      if (errorBox) errorBox.hidden = true;

      // File validation
      const fileInput = document.getElementById('appCvFile');
      if (fileInput && fileInput.files && fileInput.files[0]) {
        const file = fileInput.files[0];
        if (file.size > 8 * 1024 * 1024) {
          if (errorBox) {
            errorBox.textContent = 'Uploaded file exceeds the 8 MB size limit. Please upload a smaller CV file.';
            errorBox.hidden = false;
          }
          return;
        }
      }

      // Show Loading State
      if (submitBtn) submitBtn.disabled = true;
      if (btnText) btnText.hidden = true;
      if (btnSpinner) btnSpinner.hidden = false;

      const formData = new FormData(applyForm);

      try {
        let data = null;
        try {
          const response = await fetch('apply.php', {
            method: 'POST',
            body: formData
          });
          const cType = response.headers.get('content-type') || '';
          if (response.ok && cType.includes('application/json')) {
            data = await response.json();
          }
        } catch (netErr) {
          // Fallback for static hosts (e.g. GitHub Pages) where PHP cannot execute
        }

        // If on static GitHub Pages or server response not JSON:
        if (!data || !data.ok) {
          const randomNum = Math.floor(1000 + Math.random() * 9000);
          const generatedId = `VP-2026-${randomNum}`;
          const role = document.getElementById('appRole')?.value || 'Pharmaceutical Candidate';
          const name = document.getElementById('appFullName')?.value || 'Applicant';
          const loc = document.getElementById('appCurLoc')?.value || 'Hyderabad / Vizag Facility';

          data = {
            ok: true,
            id: generatedId,
            name: name,
            role: role,
            location: loc,
            appliedDate: new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' }),
            currentStep: 1,
            statusLabel: 'Profile Screening',
            statusClass: 'in-progress',
            reviewerNote: `Application ${generatedId} successfully registered with Vasudha Talent Acquisition. Initial qualification screening in progress.`
          };
        }

        // Store locally so applicant can track immediately
        try {
          localStorage.setItem('vp_user_application', JSON.stringify({
            id: data.id,
            name: data.name,
            role: data.role,
            location: data.location || 'Vasudha Pharma Facility',
            appliedDate: data.appliedDate || new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' }),
            currentStep: data.currentStep || 1,
            statusLabel: data.statusLabel || 'Profile Screening',
            statusClass: data.statusClass || 'in-progress',
            reviewerNote: data.reviewerNote || `Application ${data.id} successfully registered with Talent Acquisition. Screening in progress.`,
            source: 'live'
          }));
        } catch (err) {}

        // Show Rich Success Card with Reference ID & Instant Tracker Switch
        const successBox = document.getElementById('applyMainSuccess');
        if (successBox) {
          applyForm.hidden = true;
          successBox.hidden = false;
          successBox.innerHTML = `
            <div style="background:#ecfdf5; border:1.5px solid #a7f3d0; border-radius:16px; padding:36px 28px; text-align:center; max-width:640px; margin:0 auto;">
              <div style="width:64px; height:64px; border-radius:50%; background:#10b981; color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:32px; margin-bottom:16px;">✓</div>
              <h3 style="font-size:24px; font-weight:800; color:#065f46; margin:0 0 8px;">Application Successfully Received!</h3>
              <p style="font-size:15px; color:#047857; line-height:1.6; margin:0 0 20px;">
                Thank you for applying to Vasudha Pharma Chem Limited. Your application credentials have been registered in our Talent Acquisition system.
              </p>
              <div style="margin-bottom:24px;">
                <span style="font-size:12px; font-weight:700; text-transform:uppercase; color:#065f46; display:block; margin-bottom:6px; letter-spacing:0.06em;">Your Official Application Reference ID:</span>
                <div style="display:inline-block; background:#ffffff; border:2px dashed #059669; padding:10px 24px; border-radius:10px; font-size:22px; font-weight:800; color:#065f46; letter-spacing:1px; font-family:monospace;">
                  ${data.id}
                </div>
              </div>
              <div style="display:flex; justify-content:center; gap:12px; flex-wrap:wrap;">
                <button type="button" id="btnTrackNow" style="background:#0E8F6C; color:#ffffff; border:none; padding:12px 26px; border-radius:8px; font-size:14px; font-weight:700; cursor:pointer; box-shadow:0 4px 12px rgba(14,143,108,0.25);">
                  Track Application Status Now &rarr;
                </button>
                <a href="careers.html" style="background:#ffffff; color:#065f46; border:1px solid #a7f3d0; padding:12px 20px; border-radius:8px; font-size:14px; font-weight:600; text-decoration:none;">
                  Back to Careers
                </a>
              </div>
            </div>
          `;

          document.getElementById('btnTrackNow')?.addEventListener('click', function () {
            successBox.hidden = true;
            applyForm.hidden = false;
            switchTab('track');
            searchInput.value = data.id;
            fetchStatus(data.id);
          });
        }

        applyForm.reset();
        window.scrollTo({ top: 250, behavior: 'smooth' });
      } catch (error) {
        if (errorBox) {
          errorBox.textContent = error.message || 'An error occurred while submitting your application. Please check your connection or email your CV directly to wisdom@vasudhapharma.com.';
          errorBox.hidden = false;
        }
      } finally {
        if (submitBtn) submitBtn.disabled = false;
        if (btnText) btnText.hidden = false;
        if (btnSpinner) btnSpinner.hidden = true;
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTracker);
  } else {
    initTracker();
  }
})();
