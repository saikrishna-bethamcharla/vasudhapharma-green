/**
 * ============================================================================
 * VASUDHA PHARMA CHEM LIMITED | UI Controller & Interaction Layer
 * ============================================================================
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', () => {
        // 1. Initialize 3D Engine
        if (window.VasudhaEngine) {
            window.VasudhaEngine.init('canvas-container');

            function renderLoop(time) {
                requestAnimationFrame(renderLoop);
                updateScrollState();
                window.VasudhaEngine.render(time);
            }
            requestAnimationFrame(renderLoop);
        }

        // Cache UI elements
        const scrollPercentEl = document.getElementById('scroll-percent');
        const scrollBarIndicatorEl = document.getElementById('scroll-bar-indicator');
        const toggleWireframeBtn = document.getElementById('toggle-wireframe');
        const wireBtnText = document.getElementById('wire-btn-text');
        const heroResetBtn = document.getElementById('hero-reset-view');

        /**
         * Calculates normalized scroll progress and updates HUD indicators
         */
        function updateScrollState() {
            const scrollTop = window.scrollY;
            const docHeight = document.documentElement.scrollHeight - window.innerHeight;
            const progress = Math.max(0, Math.min(1, scrollTop / (docHeight || 1)));

            // Update 3D Engine
            if (window.VasudhaEngine) {
                window.VasudhaEngine.updateScroll(progress);
            }

            // Update Right HUD Rail
            if (scrollPercentEl) {
                const percentStr = Math.round(progress * 100).toString().padStart(2, '0') + '%';
                scrollPercentEl.innerText = percentStr;
            }

            if (scrollBarIndicatorEl) {
                scrollBarIndicatorEl.style.top = `${progress * 80}%`;
            }
        }

        /**
         * Wireframe Toggle Handler
         */
        if (toggleWireframeBtn && wireBtnText) {
            toggleWireframeBtn.addEventListener('click', () => {
                if (window.VasudhaEngine) {
                    const isWire = window.VasudhaEngine.toggleWireframe();
                    wireBtnText.innerText = isWire ? "MESH: SUBD 1" : "RENDER: SOLID";
                }
            });
        }

        /**
         * Reset Viewport to Hero Top
         */
        if (heroResetBtn) {
            heroResetBtn.addEventListener('click', () => {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        }

        /**
         * Interactive Country Pills on Hero Section
         */
        const countryPills = document.querySelectorAll('.country-pill');
        countryPills.forEach(pill => {
            pill.addEventListener('click', function () {
                countryPills.forEach(p => p.classList.remove('active'));
                this.classList.add('active');

                const lat = parseFloat(this.getAttribute('data-lat'));
                const lng = parseFloat(this.getAttribute('data-lng'));

                if (!isNaN(lat) && !isNaN(lng) && window.VasudhaEngine) {
                    window.VasudhaEngine.focusCountry(lat, lng);
                }
            });
        });

        /**
         * Quote Request / B2B Modal Trigger
         */
        const quoteBtns = document.querySelectorAll('.trigger-quote-modal');
        const partnerModal = document.getElementById('vpPartnerModal');
        const closeModalBtn = document.getElementById('closePartnerModal');

        quoteBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                if (partnerModal) {
                    partnerModal.classList.remove('hidden');
                    partnerModal.classList.add('flex');
                }
            });
        });

        if (closeModalBtn && partnerModal) {
            closeModalBtn.addEventListener('click', () => {
                partnerModal.classList.add('hidden');
                partnerModal.classList.remove('flex');
            });
        }

        window.addEventListener('scroll', updateScrollState, { passive: true });
    });
})();
