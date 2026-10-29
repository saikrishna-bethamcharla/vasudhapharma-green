/**
 * Vasudha Pharma — AI Career Fit & Resume Matcher
 * Enterprise Client-Side NLP skill extraction, multi-format CV parsing (.pdf, .doc, .docx, .txt),
 * compatibility scoring against jobs.json, and 1-click tailored application routing.
 */
(function () {
  'use strict';

  // Comprehensive Pharmaceutical API & CRAMS Domain Taxonomy (130+ terms)
  var PHARMA_SKILLS = [
    // Analytical & QC Instrumentation
    'hplc', 'uhplc', 'uplc', 'gc', 'gc-ms', 'gcms', 'lc-ms', 'lcms', 'icp-ms', 'uv', 'uv-vis',
    'ir', 'ftir', 'nmr', 'xrd', 'titration', 'potentiometry', 'karl fischer', 'kf titration',
    'dissolution', 'wet chemistry', 'empower', 'empower 3', 'chemstation', 'chromeleon',
    'chromatography', 'method validation', 'method development', 'stability studies', 'ich guidelines',
    'forced degradation', 'oos', 'oot', 'lims', '21 cfr part 11', 'data integrity',

    // Quality Assurance & Regulatory Affairs
    'qc', 'qa', 'quality control', 'quality assurance', 'gmp', 'cgmp', 'who-gmp', 'usfda',
    'edqm', 'eu-gmp', 'anvisa', 'pmda', 'schedule m', 'cep', 'usdmf', 'dmf', 'asmf',
    'ctd', 'ectd', 'regulatory affairs', 'audit', 'regulatory audit', 'compliance',
    'sop', 'deviations', 'investigations', 'capa', 'change control', 'risk assessment',
    'apqr', 'annual product quality review', 'vendor qualification',

    // API Production & Chemical Engineering
    'production', 'plant', 'synthesis', 'scale-up', 'pilot plant', 'kilo lab', 'bulk drugs',
    'active pharmaceutical ingredient', 'api', 'intermediates', 'heterocyclic', 'piperidone',
    'hydrogenation', 'catalysis', 'chlorination', 'bromination', 'alkylation', 'grignard',
    'chiral resolution', 'reactor', 'glr', 'glass lined reactor', 'ssr', 'stainless steel reactor',
    'hastelloy', 'centrifuge', 'dryer', 'anfd', 'agitated nutsche filter dryer', 'fluid bed dryer',
    'vacuum tray dryer', 'vtd', 'rcvd', 'rotary cone vacuum dryer', 'micronization',
    'crystallization', 'polymorphism', 'particle size', 'solvent recovery', 'distillation',
    'bmr', 'bpr', 'batch record', 'batch manufacturing record', 'cleaning validation',
    'process validation', 'qualification', 'iq', 'oq', 'pq', 'shift operations',

    // EHS, Safety & Plant Engineering
    'ehs', 'safety', 'environment', 'effluent', 'hazmat', 'permits', 'fire safety', 'zld',
    'zero liquid discharge', 'etp', 'effluent treatment plant', 'stp', 'scrubber', 'ro plant',
    'hazardous waste', 'hazop', 'process safety', 'osha', 'iso 14001', 'iso 45001',

    // Qualifications & Academic Degrees
    'b.pharm', 'bpharm', 'm.pharm', 'mpharm', 'm.sc', 'msc', 'b.sc', 'bsc', 'ph.d', 'phd',
    'chemistry', 'organic chemistry', 'analytical chemistry', 'chemical engineering', 'b.tech',
    'm.tech', 'diploma'
  ];

  var activeJobs = [];

  function loadJobs() {
    return fetch('jobs.json?v=' + Date.now())
      .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
      .then(function (data) {
        activeJobs = (data.jobs || []).filter(function (j) { return (j.status || 'open') === 'open'; });
        return activeJobs;
      })
      .catch(function () {
        // Fallback sample jobs
        activeJobs = [
          {
            id: "VP-QC-001",
            title: "Quality Control Officer",
            department: "Quality",
            location: "Hyderabad, Telangana, IN",
            skills: ["QC", "GMP", "HPLC", "analysis", "QA", "B.Pharm", "M.Sc", "titration", "method validation"],
            role: "Support routine QC testing of active pharmaceutical ingredients (APIs) and chemical intermediates."
          },
          {
            id: "VP-PRD-002",
            title: "Production Chemist",
            department: "Manufacturing",
            location: "Visakhapatnam, Andhra Pradesh, IN",
            skills: ["production", "BMR", "BPR", "reactors", "chemistry", "shift", "GLR", "centrifuge"],
            role: "Manage shift operations in bulk API synthesis and reaction suites under plant operations."
          },
          {
            id: "VP-RND-004",
            title: "Senior Research Chemist — Process R&D",
            department: "R&D",
            location: "Hyderabad (Vikasith R&D Center)",
            skills: ["R&D", "synthesis", "process development", "organic chemistry", "M.Sc", "Ph.D", "scale-up", "heterocyclic"],
            role: "Design, synthesize, and optimize non-infringing synthetic routes for APIs and key intermediates."
          }
        ];
        return activeJobs;
      });
  }

  function extractKeywords(text) {
    if (!text || typeof text !== 'string') return [];
    var lower = text.toLowerCase();
    var found = [];

    PHARMA_SKILLS.forEach(function (skill) {
      // Word boundary or token boundary match
      var escaped = skill.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
      var regex = new RegExp('(?:^|[^a-zA-Z0-9])' + escaped + '(?:$|[^a-zA-Z0-9])', 'i');
      if (regex.test(lower)) {
        found.push(skill);
      }
    });

    return Array.from(new Set(found));
  }

  function calculateMatch(candidateSkills, job) {
    var jobSkills = (job.skills || []).map(function (s) { return s.toLowerCase().trim(); });
    var jobText = ((job.title || '') + ' ' + (job.department || '') + ' ' + (job.role || '') + ' ' + (job.duties || '') + ' ' + (job.profile || '')).toLowerCase();

    // Dynamically mine implicit skills from requisition text
    PHARMA_SKILLS.forEach(function (s) {
      if (jobText.includes(s) && !jobSkills.includes(s)) {
        jobSkills.push(s);
      }
    });

    if (jobSkills.length === 0) {
      return { score: 75, matched: candidateSkills.slice(0, 3), missing: [] };
    }

    var matched = [];
    var missing = [];

    jobSkills.forEach(function (js) {
      var hit = candidateSkills.some(function (cs) {
        return cs === js || cs.includes(js) || js.includes(cs);
      });
      if (hit) matched.push(js);
      else missing.push(js);
    });

    var score = Math.round((matched.length / jobSkills.length) * 100);

    // Domain competence boost: candidates with 4+ pharmaceutical skills get realistic weighting
    if (candidateSkills.length >= 4 && score < 70) {
      score = Math.min(88, score + 22);
    } else if (candidateSkills.length >= 2 && score < 50) {
      score = Math.min(65, score + 15);
    }

    if (score > 98) score = 98; // Realistic ceiling

    return {
      score: Math.max(25, score),
      matched: Array.from(new Set(matched)),
      missing: Array.from(new Set(missing))
    };
  }

  /**
   * Client-Side Multi-Format CV Parser
   * Extracts clean text tokens from .pdf, .docx, and .txt files without external libraries.
   */
  function parseResumeFile(file, callback) {
    var ext = (file.name.split('.').pop() || '').toLowerCase();

    if (ext === 'txt') {
      var reader = new FileReader();
      reader.onload = function (e) {
        callback(null, e.target.result || '');
      };
      reader.onerror = function () {
        callback(new Error('Could not read text file.'));
      };
      reader.readAsText(file);
      return;
    }

    // Binary file reader for PDF / DOCX
    var bufReader = new FileReader();
    bufReader.onload = function (e) {
      try {
        var buffer = e.target.result;
        var bytes = new Uint8Array(buffer);
        var extractedWords = [];

        if (ext === 'pdf') {
          // Extract plain-text string tokens from PDF stream and text operators
          var binaryStr = '';
          var chunkSize = 8192;
          for (var i = 0; i < bytes.length; i += chunkSize) {
            binaryStr += String.fromCharCode.apply(null, bytes.subarray(i, Math.min(i + chunkSize, bytes.length)));
          }

          // 1. Look for text operators like (Text) Tj, [(T)(e)(x)(t)] TJ, and BT ... ET
          var textMatches = binaryStr.match(/\(([^()]{2,100})\)\s*Tj/g) || [];
          textMatches.forEach(function (m) {
            var clean = m.replace(/^\(/, '').replace(/\)\s*Tj$/, '').trim();
            if (clean.length > 2) extractedWords.push(clean);
          });

          // 2. Also harvest all alphanumeric strings of length 3-30
          var asciiWords = binaryStr.match(/[A-Za-z0-9\-\.\+\/]{3,35}/g) || [];
          // Filter out typical PDF operators and syntax noise
          var noise = /^(obj|endobj|stream|endstream|xref|trailer|startxref|Length|Filter|FlateDecode|Font|Type|Pages|Catalog|Kids|Count|MediaBox|ProcSet|Resources|FontDescriptor|BaseFont)$/i;
          asciiWords.forEach(function (w) {
            if (!noise.test(w)) extractedWords.push(w);
          });
        } else {
          // DOCX / DOC: Extract alphanumeric tokens from XML / binary stream
          var binaryStr = '';
          var chunkSize = 8192;
          for (var j = 0; j < bytes.length; j += chunkSize) {
            binaryStr += String.fromCharCode.apply(null, bytes.subarray(j, Math.min(j + chunkSize, bytes.length)));
          }
          var words = binaryStr.match(/[A-Za-z0-9\-\.\+\/]{3,35}/g) || [];
          extractedWords = words.filter(function (w) {
            return !/^(xmlns|w14|w15|w16|xml|rel|type|content|pk|word|docx)$/i.test(w);
          });
        }

        var textContent = extractedWords.slice(0, 3000).join(' ');
        callback(null, textContent || file.name);
      } catch (err) {
        callback(err, file.name);
      }
    };
    bufReader.onerror = function () {
      callback(new Error('Failed to read file contents.'));
    };
    bufReader.readAsArrayBuffer(file);
  }

  function initMatcher() {
    loadJobs();

    var analyzeBtn = document.getElementById('vpResumeAnalyzeBtn');
    var resumeInput = document.getElementById('vpResumeText');
    var fileInput = document.getElementById('vpResumeFileInput');
    var resultsBox = document.getElementById('vpResumeResults');

    if (!analyzeBtn || !resumeInput || !resultsBox) return;

    // File input handler for PDF, DOC, DOCX, TXT
    if (fileInput) {
      fileInput.addEventListener('change', function (e) {
        var file = e.target.files && e.target.files[0];
        if (!file) return;

        // Visual status on the button
        var uploadLabel = fileInput.closest('.vp-file-upload-btn');
        var origLabelHtml = uploadLabel ? uploadLabel.innerHTML : '';
        if (uploadLabel) {
          uploadLabel.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Parsing ' + file.name + '...';
        }

        parseResumeFile(file, function (err, text) {
          if (uploadLabel) uploadLabel.innerHTML = origLabelHtml;
          if (err || !text) {
            alert('Could not extract text from ' + file.name + '. Please paste your CV summary or skills directly.');
            return;
          }

          var detected = extractKeywords(text);
          if (detected.length > 0) {
            resumeInput.value = 'CV File: ' + file.name + '\n' +
              'Detected Technical Profile & Competencies:\n' +
              detected.map(function (s) { return '• ' + s.toUpperCase(); }).join('\n') + '\n\n' +
              'Extracted Summary Content:\n' + text.slice(0, 600) + '...';
          } else {
            resumeInput.value = text.slice(0, 1500);
          }

          runAnalysis();
        });
      });
    }

    function runAnalysis() {
      var text = resumeInput.value.trim();
      if (!text) {
        alert('Please paste your resume summary or upload a CV file first.');
        return;
      }

      var candidateSkills = extractKeywords(text);
      if (candidateSkills.length === 0) {
        resultsBox.innerHTML = '<div class="vp-matcher-empty" style="background:#FFFBEB; border:1px solid #FDE68A; color:#B45309; padding:20px; border-radius:12px; text-align:center;">' +
          '<div style="font-size:24px; margin-bottom:8px;">⚠️</div>' +
          '<strong>No Pharmaceutical Competencies Detected Yet</strong>' +
          '<p style="margin:6px auto 0; max-width:540px; font-size:13px; line-height:1.5;">We could not identify standard API manufacturing, quality control, or synthetic chemistry keywords in the text. Try adding skills like <strong>HPLC, cGMP, BMR, GLR, Method Validation, M.Sc, or Organic Chemistry</strong>, or click one of the quick test profiles above.</p>' +
          '</div>';
        resultsBox.hidden = false;
        return;
      }

      if (activeJobs.length === 0) {
        resultsBox.innerHTML = '<div class="vp-matcher-empty" style="padding:20px; text-align:center; color:#64748B;">No open requisitions are currently active to match against.</div>';
        resultsBox.hidden = false;
        return;
      }

      // Rank jobs
      var rankings = activeJobs.map(function (j) {
        var res = calculateMatch(candidateSkills, j);
        return {
          job: j,
          score: res.score,
          matched: res.matched,
          missing: res.missing
        };
      });

      rankings.sort(function (a, b) { return b.score - a.score; });

      // Render top matches
      var html = '<div class="vp-matcher-card" style="background:#FFFFFF; border:1.5px solid #0E8F6C; border-radius:16px; padding:26px; box-shadow:0 8px 30px rgba(14,143,108,0.08); margin-top:20px;">';
      html += '  <div class="vp-matcher-header" style="border-bottom:1px solid #E2E8F0; padding-bottom:16px; margin-bottom:18px;">';
      html += '    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">';
      html += '      <span style="background:#ECFDF5; color:#0E8F6C; font-weight:700; font-size:12px; padding:4px 12px; border-radius:20px; letter-spacing:0.04em; text-transform:uppercase;">AI ATS Fit Complete</span>';
      html += '      <span style="font-size:12px; color:#64748B;"><i class="fas fa-check-circle" style="color:#0E8F6C;"></i> Matched against ' + activeJobs.length + ' active plant requisitions</span>';
      html += '    </div>';
      html += '    <h3 style="margin:10px 0 4px; font-size:20px; color:#0F172A; font-weight:800;">Identified ' + candidateSkills.length + ' Verified Technical Competencies</h3>';
      html += '    <div style="display:flex; gap:6px; flex-wrap:wrap; margin-top:10px;">';
      candidateSkills.forEach(function (s) {
        html += '<span style="background:#F1F5F9; color:#0F172A; font-size:11.5px; font-weight:600; padding:4px 10px; border-radius:6px; border:1px solid #CBD5E1;">' + s.toUpperCase() + '</span>';
      });
      html += '    </div>';
      html += '  </div>';

      html += '  <div class="vp-matcher-jobs-list" style="display:flex; flex-direction:column; gap:16px;">';
      rankings.slice(0, 3).forEach(function (r) {
        var color = r.score >= 80 ? '#059669' : (r.score >= 60 ? '#254696' : '#D97706');
        var bg = r.score >= 80 ? '#ECFDF5' : (r.score >= 60 ? '#EEF2FF' : '#FFFBEB');
        var border = r.score >= 80 ? '#A7F3D0' : (r.score >= 60 ? '#C7D2FE' : '#FDE68A');

        html += '<div style="background:#F8FAFC; border:1px solid ' + border + '; border-radius:12px; padding:18px 20px;">';
        html += '  <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:14px; flex-wrap:wrap;">';
        html += '    <div>';
        html += '      <span style="font-size:11.5px; font-weight:700; color:#64748B; text-transform:uppercase;">' + (r.job.department || 'Operations') + ' &bull; ' + r.job.id + '</span>';
        html += '      <h4 style="margin:2px 0 4px; font-size:17px; font-weight:700; color:#0F172A;">' + r.job.title + '</h4>';
        html += '      <div style="font-size:13px; color:#64748B;"><i class="fas fa-map-marker-alt" style="color:#0E8F6C;"></i> ' + (r.job.location || 'Hyderabad') + '</div>';
        html += '    </div>';
        html += '    <div style="text-align:center; min-width:76px; background:' + bg + '; border:1.5px solid ' + border + '; padding:8px 12px; border-radius:10px;">';
        html += '      <span style="display:block; font-size:20px; font-weight:800; color:' + color + '; line-height:1;">' + r.score + '%</span>';
        html += '      <small style="font-size:9.5px; font-weight:700; color:' + color + '; letter-spacing:0.05em;">MATCH FIT</small>';
        html += '    </div>';
        html += '  </div>';

        html += '  <div style="margin:12px 0 10px; font-size:12.5px;">';
        html += '    <strong style="color:#334155;">Key Strengths Aligned: </strong>';
        if (r.matched.length > 0) {
          r.matched.slice(0, 6).forEach(function (m) {
            html += '<span style="background:#DCFCE7; color:#15803D; padding:2px 8px; border-radius:4px; font-weight:600; font-size:11px; margin-right:4px;">✓ ' + m.toUpperCase() + '</span> ';
          });
        } else {
          html += '<span style="color:#94A3B8;">Broad pharmaceutical suitability</span>';
        }
        html += '  </div>';

        // 1-Click Tailored Apply link passing job title, id, and candidate skills
        var applyUrl = 'apply.html?job=' + encodeURIComponent(r.job.id) +
          '&role=' + encodeURIComponent(r.job.title) +
          '&skills=' + encodeURIComponent(candidateSkills.join(','));

        html += '  <div style="margin-top:14px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">';
        html += '    <span style="font-size:12px; color:#64748B;">Requisition Status: <strong style="color:#0E8F6C;">Actively Interviewing</strong></span>';
        html += '    <a href="' + applyUrl + '" class="cp-btn-primary" style="padding:8px 20px; font-size:13px; text-decoration:none;">Apply with this Profile &rarr;</a>';
        html += '  </div>';
        html += '</div>';
      });
      html += '  </div>';
      html += '</div>';

      resultsBox.innerHTML = html;
      resultsBox.hidden = false;
      resultsBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    var SAMPLES = {
      qc: "Experienced QC Chemist with 4 years in API quality control. Proficient in HPLC, GC-MS, UV-Vis spectrophotometry, wet chemistry titration, method validation, stability studies, and cGMP compliance under USFDA & EU guidelines. M.Sc Analytical Chemistry.",
      production: "API Production Shift Executive with hands-on expertise in batch manufacturing records (BMR), SS316 & Glass-Lined Reactors (GLR), fluid bed dryers, centrifuges, cGMP manufacturing operations, solvent recovery, and plant safety SOPs.",
      regulatory: "Regulatory Affairs Specialist managing USDMF, EDQM CEP, and ASMF submissions for active pharmaceutical ingredients. Comprehensive knowledge of CTD modules, audit responses, change controls, CAPA, and post-approval variations.",
      rnd: "Synthetic Organic Chemist specializing in heterocyclic synthesis, piperidone derivatives, high-pressure catalytic hydrogenation, chiral resolution, route scouting, and technology transfer from lab to kilo-scale."
    };

    document.querySelectorAll('[data-sample]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var key = this.getAttribute('data-sample');
        if (SAMPLES[key]) {
          resumeInput.value = SAMPLES[key];
          runAnalysis();
        }
      });
    });

    analyzeBtn.addEventListener('click', runAnalysis);
  }

  // Export helper globally so inline scripts in careers.html can use the multi-format parser
  window.vpParseResumeFile = parseResumeFile;
  window.vpExtractPharmaKeywords = extractKeywords;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMatcher);
  } else {
    initMatcher();
  }
})();