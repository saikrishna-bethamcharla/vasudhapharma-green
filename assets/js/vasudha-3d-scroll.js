/**
 * Vasudha Pharma — Modern 3D Smooth Scrolling & Interactive Physics Engine
 * High-performance WebGL / 2.5D Canvas Molecular Field + Inertia Momentum + Magnetic Card Tilt
 */
(function () {
  'use strict';

  /* ==========================================================================
     1. Kinetic 3D Scroll Progress Bar (Universal - Active on All Pages)
     ========================================================================== */
  var progressBar = document.getElementById('vp3dProgressBar');
  if (!progressBar) {
    progressBar = document.createElement('div');
    progressBar.id = 'vp3dProgressBar';
    progressBar.setAttribute('aria-hidden', 'true');
    if (document.body) {
      document.body.appendChild(progressBar);
    } else {
      document.addEventListener('DOMContentLoaded', function () {
        if (!document.getElementById('vp3dProgressBar')) {
          document.body.appendChild(progressBar);
        }
      });
    }
  }

  function updateProgressBar() {
    var winScroll = window.scrollY || document.documentElement.scrollTop || 0;
    var docHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
    var scrolled = docHeight > 0 ? (winScroll / docHeight) * 100 : 0;
    if (progressBar) {
      progressBar.style.width = Math.min(100, Math.max(0, scrolled)) + '%';
    }
  }

  window.addEventListener('scroll', updateProgressBar, { passive: true });
  window.addEventListener('resize', updateProgressBar, { passive: true });
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', updateProgressBar);
  } else {
    updateProgressBar();
  }

  // Check accessibility preferences for intensive 3D canvas animation & momentum
  var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (prefersReducedMotion) {
    console.log('[Vasudha 3D] Reduced motion detected, 3D physics disabled.');
    return;
  }

  /* ==========================================================================
     2. Ambient 3D Molecular Depth Canvas
     ========================================================================== */
  var canvas = document.createElement('canvas');
  canvas.id = 'vp3dBackgroundCanvas';
  document.body.insertBefore(canvas, document.body.firstChild);

  var ctx = canvas.getContext('2d');
  var width = (canvas.width = window.innerWidth);
  var height = (canvas.height = window.innerHeight);

  var particles = [];
  var particleCount = Math.min(48, Math.floor(window.innerWidth / 35));
  var mouse = { x: width / 2, y: height / 2, targetX: width / 2, targetY: height / 2 };
  var scrollVelocity = 0;
  var lastScrollY = window.scrollY;

  // Particle constructor
  function Particle() {
    this.x = Math.random() * width;
    this.y = Math.random() * height;
    this.z = Math.random() * 800 + 100; // 3D depth axis
    this.radius = Math.random() * 2.8 + 1.2;
    this.baseSpeedX = (Math.random() - 0.5) * 0.4;
    this.baseSpeedY = (Math.random() - 0.5) * 0.4;
    this.color = Math.random() > 0.4 ? 'rgba(29, 184, 138,' : 'rgba(23, 165, 137,'; // Emerald / Teal
    this.pulse = Math.random() * Math.PI * 2;
  }

  Particle.prototype.update = function (speedMultiplier, depthShift) {
    this.pulse += 0.02;
    this.x += this.baseSpeedX * speedMultiplier;
    this.y += (this.baseSpeedY + depthShift * 0.08) * speedMultiplier;

    // Wrap around screen boundaries with depth
    if (this.x < -40) this.x = width + 40;
    if (this.x > width + 40) this.x = -40;
    if (this.y < -40) this.y = height + 40;
    if (this.y > height + 40) this.y = -40;
  };

  Particle.prototype.draw = function () {
    // 3D perspective projection
    var fov = 450;
    var scale = fov / (fov + this.z);
    var projX = (this.x - width / 2) * scale + width / 2 + (mouse.x - width / 2) * 0.025 * scale;
    var projY = (this.y - height / 2) * scale + height / 2 + (mouse.y - height / 2) * 0.025 * scale;
    var r = this.radius * scale;
    var alpha = (0.28 + Math.sin(this.pulse) * 0.08) * scale;

    ctx.beginPath();
    ctx.arc(projX, projY, Math.max(0.5, r), 0, Math.PI * 2);
    ctx.fillStyle = this.color + Math.min(0.65, Math.max(0.08, alpha)) + ')';
    ctx.fill();
  };

  for (var i = 0; i < particleCount; i++) {
    particles.push(new Particle());
  }

  // Draw connecting molecular bonds between close nodes
  function drawBonds() {
    var maxDist = 110;
    for (var i = 0; i < particles.length; i++) {
      for (var j = i + 1; j < particles.length; j++) {
        var p1 = particles[i];
        var p2 = particles[j];
        var dx = p1.x - p2.x;
        var dy = p1.y - p2.y;
        var dist = Math.sqrt(dx * dx + dy * dy);
        if (dist < maxDist) {
          var fov = 450;
          var s1 = fov / (fov + p1.z);
          var s2 = fov / (fov + p2.z);
          var x1 = (p1.x - width / 2) * s1 + width / 2;
          var y1 = (p1.y - height / 2) * s1 + height / 2;
          var x2 = (p2.x - width / 2) * s2 + width / 2;
          var y2 = (p2.y - height / 2) * s2 + height / 2;
          var alpha = (1 - dist / maxDist) * 0.16 * Math.min(s1, s2);

          ctx.beginPath();
          ctx.moveTo(x1, y1);
          ctx.lineTo(x2, y2);
          ctx.strokeStyle = 'rgba(29, 184, 138, ' + alpha + ')';
          ctx.lineWidth = 0.8 * Math.min(s1, s2);
          ctx.stroke();
        }
      }
    }
  }

  // Animation Loop
  var rafId;
  function renderLoop() {
    ctx.clearRect(0, 0, width, height);

    // Smooth mouse lerp
    mouse.x += (mouse.targetX - mouse.x) * 0.05;
    mouse.y += (mouse.targetY - mouse.y) * 0.05;

    // Measure scroll momentum
    var currentScrollY = window.scrollY;
    var deltaScroll = currentScrollY - lastScrollY;
    lastScrollY = currentScrollY;
    scrollVelocity += (deltaScroll - scrollVelocity) * 0.12;

    for (var i = 0; i < particles.length; i++) {
      particles[i].update(1, scrollVelocity);
      particles[i].draw();
    }
    drawBonds();

    rafId = requestAnimationFrame(renderLoop);
  }

  renderLoop();

  window.addEventListener('resize', function () {
    width = canvas.width = window.innerWidth;
    height = canvas.height = window.innerHeight;
  });

  window.addEventListener('mousemove', function (e) {
    mouse.targetX = e.clientX;
    mouse.targetY = e.clientY;
  }, { passive: true });

  /* ==========================================================================
     3. Interactive 3D Magnetic Card Tilt
     ========================================================================== */
  var tiltSelector = '.feature-card, .product-card, .stat-item, .vp-event-card, .vp-unit-card, .pillar-card, .cert-card';
  var tiltCards = document.querySelectorAll(tiltSelector);

  tiltCards.forEach(function (card) {
    card.classList.add('vp-3d-tilt');

    card.addEventListener('mousemove', function (e) {
      var rect = card.getBoundingClientRect();
      var x = e.clientX - rect.left;
      var y = e.clientY - rect.top;
      var centerX = rect.width / 2;
      var centerY = rect.height / 2;

      var rotateX = ((y - centerY) / centerY) * -6.5; // Max 6.5deg tilt
      var rotateY = ((x - centerX) / centerX) * 6.5;

      card.style.transform = 'perspective(1000px) rotateX(' + rotateX.toFixed(2) + 'deg) rotateY(' + rotateY.toFixed(2) + 'deg) translateZ(10px)';
    });

    card.addEventListener('mouseleave', function () {
      card.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg) translateZ(0px)';
    });
  });

  /* ==========================================================================
     4. Scroll-Triggered 3D Reveal
     ========================================================================== */
  if ('IntersectionObserver' in window) {
    var revealObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('vp-3d-visible');
        }
      });
    }, {
      rootMargin: '0px 0px -60px 0px',
      threshold: 0.12
    });

    var revealTargets = document.querySelectorAll('.feature-card, .product-card, .stat-item, .vp-event-card, .section-header, .vp-unit-card, .timeline-item');
    revealTargets.forEach(function (el, index) {
      el.classList.add('vp-3d-reveal');
      // Stagger nearby items
      var delayClass = 'vp-3d-delay-' + ((index % 4) + 1);
      el.classList.add(delayClass);
      revealObserver.observe(el);
    });
  }

  /* ==========================================================================
     5. Smooth Momentum / Lerp Inertia Scroll
     ========================================================================== */
  // Seamless virtual-scroll momentum without interfering with native inputs or anchors
  var isTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
  if (!isTouch && window.innerWidth > 992) {
    var targetY = window.scrollY;
    var currentY = window.scrollY;
    var ease = 0.082; // Butter smooth damping
    var isMoving = false;

    window.addEventListener('wheel', function (e) {
      // Don't hijack if scrolling inside an internal scrollable container
      var path = e.composedPath ? e.composedPath() : [];
      for (var i = 0; i < path.length; i++) {
        var node = path[i];
        if (node === document.body || node === document.documentElement) break;
        if (node.scrollHeight > node.clientHeight) {
          var overflowY = window.getComputedStyle(node).overflowY;
          if (overflowY === 'auto' || overflowY === 'scroll') return;
        }
      }

      e.preventDefault();
      targetY += e.deltaY * 0.95;
      var maxY = document.documentElement.scrollHeight - window.innerHeight;
      targetY = Math.max(0, Math.min(targetY, maxY));

      if (!isMoving) {
        isMoving = true;
        requestAnimationFrame(updateSmoothScroll);
      }
    }, { passive: false });

    function updateSmoothScroll() {
      var diff = targetY - currentY;
      currentY += diff * ease;

      window.scrollTo(0, Math.round(currentY));

      if (Math.abs(diff) > 0.5) {
        requestAnimationFrame(updateSmoothScroll);
      } else {
        currentY = targetY;
        isMoving = false;
      }
    }

    // Sync on native jump (e.g. anchor clicks, page keys)
    window.addEventListener('scroll', function () {
      if (!isMoving) {
        targetY = window.scrollY;
        currentY = window.scrollY;
      }
    }, { passive: true });
  }

  console.log('[Vasudha 3D] 3D Smooth Scrolling Engine initialized successfully.');
})();
