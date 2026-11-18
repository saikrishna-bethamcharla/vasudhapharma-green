/**
 * ==============================================================================
 * VASUDHA PHARMA CHEM LIMITED — ASTRYX COMMAND PALETTE (CTRL+K)
 * Accessible, Keyboard-Navigable Quick Search & Molecule Finder
 * Powered by Astryx (@astryxdesign/core CommandPalette Spec)
 * ==============================================================================
 */

(function () {
  'use strict';

  // Comprehensive index of searchable molecules, units, pages & statutory links
  const SEARCH_ITEMS = [
    // APIs & Intermediates
    { title: 'Febuxostat', subtitle: 'API • Hyperuricemia & Gout • USFDA / CEP', category: 'apis', url: 'apis.html#product-api-febuxostat', badge: 'API' },
    { title: 'Diatrizoic Acid', subtitle: 'API • Diagnostic Radiopaque Contrast Medium', category: 'apis', url: 'apis.html#product-api-diatrizoic-acid', badge: 'API' },
    { title: 'Diatrizoate Meglumine', subtitle: 'API • Contrast Agent • High Purity', category: 'apis', url: 'apis.html#product-api-diatrizoate-meglumine', badge: 'API' },
    { title: 'Telmisartan', subtitle: 'API • Angiotensin II Receptor Blocker (Antihypertensive)', category: 'apis', url: 'apis.html#product-api-telmisartan', badge: 'API' },
    { title: 'Fexofenadine HCl', subtitle: 'API • Second-Generation Antihistamine', category: 'apis', url: 'apis.html#product-api-fexofenadine', badge: 'API' },
    { title: 'Drotaverine Hydrochloride', subtitle: 'API • Antispasmodic / Smooth Muscle Relaxant', category: 'apis', url: 'apis.html#product-api-drotaverine', badge: 'API' },
    { title: 'Mebeverine Hydrochloride', subtitle: 'API • Gastrointestinal Antispasmodic', category: 'apis', url: 'apis.html#product-api-mebeverine', badge: 'API' },
    { title: 'Clopidogrel Bisulfate', subtitle: 'API • Antiplatelet Agent (Cardiovascular)', category: 'apis', url: 'apis.html', badge: 'API' },
    { title: 'Piperidone & Piperidine Chemistry', subtitle: 'Global Leading Synthesis Capacity (90+ Countries)', category: 'piperidones', url: 'piperidone-derivatives.html', badge: 'Specialty' },
    { title: '1-Benzyl-4-Piperidone', subtitle: 'Key Heterocyclic Intermediate • CAS: 3612-20-2', category: 'intermediates', url: 'piperidone-derivatives.html', badge: 'Intermediate' },
    { title: 'Omeprazole Pellets (8.5%, 10%)', subtitle: 'Modified Release Pellets • MUPS Ready', category: 'pellets', url: 'pellets.html', badge: 'Pellet' },
    { title: 'Esomeprazole Magnesium Pellets', subtitle: 'Enteric Coated Pellets • MUPS Grade', category: 'pellets', url: 'pellets.html', badge: 'Pellet' },
    { title: 'Itraconazole Pellets (20%, 22%)', subtitle: 'Antifungal Sustained Release Pellets', category: 'pellets', url: 'pellets.html', badge: 'Pellet' },
    { title: 'Under-Development API Pipeline', subtitle: 'Next-Gen Molecules, DMF Filings & Scale-Up', category: 'apis', url: 'under-development.html', badge: 'R&D Pipeline' },

    // Facilities & Manufacturing
    { title: 'Unit I Synthesis Plant', subtitle: 'Foundation Chemical Facility • IDA Jeedimetla, Hyderabad', category: 'facilities', url: 'manufacturing.html#unit-1', badge: 'Plant' },
    { title: 'Unit II cGMP API Campus', subtitle: 'Multi-Ton Bulk Active Ingredients • JNPC, Visakhapatnam', category: 'facilities', url: 'manufacturing.html#unit-2', badge: 'cGMP Plant' },
    { title: 'Unit III API Facility', subtitle: 'High-Throughput Synthesis & Intermediates • JNPC, Vizag', category: 'facilities', url: 'manufacturing.html#unit-3', badge: 'Plant' },
    { title: 'Unit IV Pellets & Formulation Hub', subtitle: 'Specialized Pelletization & MUPS • JNPC, Parawada', category: 'facilities', url: 'pellets.html', badge: 'Pellets Hub' },
    { title: 'Unit V APSEZ Mega Campus', subtitle: 'Flagship Export Manufacturing • APSEZ, Atchutapuram', category: 'facilities', url: 'manufacturing.html#unit-5', badge: 'APSEZ Export' },
    { title: 'Central R&D & Discovery Center', subtitle: 'DSIR-Recognized Synthetic Research • Hyderabad', category: 'facilities', url: 'rnd.html', badge: 'R&D Center' },

    // Careers & Recruitment
    { title: 'Active Job Requisitions', subtitle: 'Explore Open Careers in QC, QA, Production & Synthesis', category: 'careers', url: 'careers.html', badge: 'Careers' },
    { title: 'Submit Job Application & CV', subtitle: 'Apply directly for open vacancies with resume upload', category: 'careers', url: 'apply.html', badge: 'Apply' },
    { title: 'Track Candidate Application Status', subtitle: 'Real-time hiring pipeline tracking via Reference ID', category: 'careers', url: 'apply.html#track', badge: 'Tracker' },
    { title: 'Join Talent Community Network', subtitle: 'Register prospective profile for future openings', category: 'careers', url: 'talent-community.html', badge: 'Talent Pool' },

    // Corporate Governance & Sustainability
    { title: 'Board of Directors & Management', subtitle: 'Leadership Profiles, Whole-Time & Independent Directors', category: 'governance', url: 'about-us.html#board-of-directors', badge: 'Leadership' },
    { title: 'Statutory Regulations & Unit Consents', subtitle: 'Download PCB, EIA, and cGMP Clearances (Unit I - V)', category: 'governance', url: 'statutory-regulations.html', badge: 'Statutory' },
    { title: 'Corporate Social Responsibility (CSR)', subtitle: 'Vasudha Foundation: Akshara, Aarogya & Jalam Initiatives', category: 'governance', url: 'founder.html#vasudha-foundation', badge: 'CSR' },
    { title: 'Global Trade Shows & CPHI Conferences', subtitle: 'CPHI Milan, Bio-Europe, DCAT Week • Book Meeting Slots', category: 'governance', url: 'events.html', badge: 'Events' },
    { title: 'Request Commercial Quote (RFQ)', subtitle: 'Direct Sales & Business Development Commercial Desk', category: 'governance', url: 'contact.html', badge: 'Quote Desk' }
  ];

  let currentCategory = 'all';
  let selectedIndex = 0;
  let filteredItems = [...SEARCH_ITEMS];

  // Create Palette DOM Elements
  function createPalette() {
    if (document.getElementById('astryxCommandPalette')) return;

    // 1. Palette Overlay Container
    const overlay = document.createElement('div');
    overlay.id = 'astryxCommandPalette';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-label', 'Global Search and Command Palette');

    overlay.innerHTML = `
      <div class="astryx-palette-box" role="combobox" aria-expanded="true" aria-haspopup="listbox">
        <div class="astryx-palette-search-wrap">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
          <input 
            type="text" 
            class="astryx-palette-input" 
            id="astryxPaletteInput" 
            placeholder="Search APIs, intermediates, facilities, careers, or statutory files..." 
            autocomplete="off"
            spellcheck="false"
          >
          <span class="astryx-palette-kbd">ESC</span>
        </div>

        <div class="astryx-palette-chips" role="tablist">
          <button type="button" class="astryx-palette-chip active" data-cat="all">All Items</button>
          <button type="button" class="astryx-palette-chip" data-cat="apis">APIs &amp; Intermediates</button>
          <button type="button" class="astryx-palette-chip" data-cat="pellets">Pellets &amp; MUPS</button>
          <button type="button" class="astryx-palette-chip" data-cat="facilities">Plants &amp; R&amp;D</button>
          <button type="button" class="astryx-palette-chip" data-cat="careers">Careers</button>
          <button type="button" class="astryx-palette-chip" data-cat="governance">Governance</button>
        </div>

        <div class="astryx-palette-results" id="astryxPaletteResults" role="listbox"></div>

        <div class="astryx-palette-footer">
          <div class="astryx-palette-hints">
            <span><kbd class="astryx-palette-kbd">↑</kbd> <kbd class="astryx-palette-kbd">↓</kbd> Navigate</span>
            <span><kbd class="astryx-palette-kbd">↵</kbd> Select</span>
            <span><kbd class="astryx-palette-kbd">ESC</kbd> Close</span>
          </div>
          <span style="font-weight: 500; color: #0E8F6C;">Vasudha Pharma Chem</span>
        </div>
      </div>
    `;

    document.body.appendChild(overlay);

    // 2. Floating Quick Trigger Pill
    const pill = document.createElement('div');
    pill.className = 'astryx-search-pill-trigger';
    pill.setAttribute('role', 'button');
    pill.setAttribute('aria-label', 'Quick Search Command Palette');
    pill.innerHTML = `
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#0E8F6C" stroke-width="2.4">
        <circle cx="11" cy="11" r="8"></circle>
        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
      </svg>
      <span>Search</span>
      <kbd class="astryx-palette-kbd" style="font-size: 10px; padding: 2px 5px;">Ctrl+K</kbd>
    `;
    pill.addEventListener('click', openPalette);
    document.body.appendChild(pill);

    // Event Listeners
    const input = document.getElementById('astryxPaletteInput');
    input.addEventListener('input', handleSearch);
    input.addEventListener('keydown', handleKeydown);

    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) closePalette();
    });

    overlay.querySelectorAll('.astryx-palette-chip').forEach(chip => {
      chip.addEventListener('click', function () {
        overlay.querySelectorAll('.astryx-palette-chip').forEach(c => c.classList.remove('active'));
        this.classList.add('active');
        currentCategory = this.getAttribute('data-cat') || 'all';
        handleSearch();
      });
    });
  }

  function renderResults() {
    const container = document.getElementById('astryxPaletteResults');
    if (!container) return;

    if (filteredItems.length === 0) {
      container.innerHTML = `
        <div class="astryx-palette-empty">
          <p style="margin: 0 0 4px; font-weight: 600; color: #18181B;">No matching results found</p>
          <p style="margin: 0; font-size: 12.5px;">Try searching for chemical name, CAS number, plant location, or careers.</p>
        </div>
      `;
      return;
    }

    let html = '';
    filteredItems.forEach((item, index) => {
      const isSelected = (index === selectedIndex);
      html += `
        <a 
          href="${item.url}" 
          class="astryx-result-item ${isSelected ? 'selected' : ''}" 
          role="option" 
          aria-selected="${isSelected}"
          data-index="${index}"
        >
          <div class="astryx-result-item-main">
            <span class="astryx-result-title">${item.title}</span>
            <span class="astryx-result-subtitle">${item.subtitle}</span>
          </div>
          <span class="astryx-result-badge">${item.badge}</span>
        </a>
      `;
    });

    container.innerHTML = html;

    // Ensure selected item is scrolled into view
    const selEl = container.querySelector('.astryx-result-item.selected');
    if (selEl) {
      selEl.scrollIntoView({ block: 'nearest' });
    }
  }

  function handleSearch() {
    const input = document.getElementById('astryxPaletteInput');
    const q = (input ? input.value : '').toLowerCase().trim();

    filteredItems = SEARCH_ITEMS.filter(item => {
      const matchesCat = (currentCategory === 'all') || 
        (currentCategory === 'apis' && (item.category === 'apis' || item.category === 'intermediates')) ||
        (item.category === currentCategory);

      if (!matchesCat) return false;
      if (!q) return true;

      return item.title.toLowerCase().includes(q) || 
             item.subtitle.toLowerCase().includes(q) ||
             item.badge.toLowerCase().includes(q);
    });

    selectedIndex = 0;
    renderResults();
  }

  function handleKeydown(e) {
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      if (filteredItems.length > 0) {
        selectedIndex = (selectedIndex + 1) % filteredItems.length;
        renderResults();
      }
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      if (filteredItems.length > 0) {
        selectedIndex = (selectedIndex - 1 + filteredItems.length) % filteredItems.length;
        renderResults();
      }
    } else if (e.key === 'Enter') {
      e.preventDefault();
      if (filteredItems[selectedIndex]) {
        window.location.href = filteredItems[selectedIndex].url;
      }
    } else if (e.key === 'Escape') {
      closePalette();
    }
  }

  function openPalette() {
    createPalette();
    const overlay = document.getElementById('astryxCommandPalette');
    const input = document.getElementById('astryxPaletteInput');
    if (overlay && input) {
      overlay.classList.add('open');
      input.value = '';
      currentCategory = 'all';
      const allChip = overlay.querySelector('.astryx-palette-chip[data-cat="all"]');
      if (allChip) {
        overlay.querySelectorAll('.astryx-palette-chip').forEach(c => c.classList.remove('active'));
        allChip.classList.add('active');
      }
      handleSearch();
      setTimeout(() => input.focus(), 80);
      document.body.style.overflow = 'hidden';
    }
  }

  function closePalette() {
    const overlay = document.getElementById('astryxCommandPalette');
    if (overlay) {
      overlay.classList.remove('open');
      document.body.style.overflow = '';
    }
  }

  // Global Keyboard Shortcut: Ctrl+K / Cmd+K / Slash key
  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
      e.preventDefault();
      const overlay = document.getElementById('astryxCommandPalette');
      if (overlay && overlay.classList.contains('open')) {
        closePalette();
      } else {
        openPalette();
      }
    } else if (e.key === 'Escape') {
      closePalette();
    }
  });

  // Initialize on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', createPalette);
  } else {
    createPalette();
  }
})();
