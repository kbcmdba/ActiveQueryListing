/* klaxon.js -- plays alert sound on critical errors   KB Benton 2025-05-13 */

(() => {
    const warningAudio = new Audio('Images/warning-ding.mp3');  // level4 (critical)
    let klaxonAudio = null;  // errorNotice (error/level9) - set after DOM ready
    const playCount = 3;  // number of times to play the alert
    warningAudio.preload = 'auto';

    // -- check if muted via URL parameter or cookie (supports timed mute)
    const checkMuted = () => {
        const urlParams = new URLSearchParams(window.location.search);
        // Check URL params first
        const urlMuteUntil = urlParams.get('mute_until');
        if (urlMuteUntil !== null) {
            const expiry = parseInt(urlMuteUntil, 10);
            return expiry === 0 || Date.now() < expiry;
        }
        // Legacy support: mute=1 means indefinite
        if (urlParams.get('mute') === '1') {
            return true;
        }
        // Check cookie for timed mute
        const match = document.cookie.match(/aql_mute_until=(\d+)/);
        if (match) {
            const expiry = parseInt(match[1], 10);
            return expiry === 0 || Date.now() < expiry;
        }
        return false;
    };

    // -- track which alerts have fired
    let warningFired = false;
    let klaxonFired = false;
    let timesPlayed = 0;

    const fireWarning = () => {
        if (checkMuted()) return;
        if (warningFired) return;
        warningFired = true;
        timesPlayed = 0;
        warningAudio.currentTime = 0;
        warningAudio.play().catch(() => banner());
    };

    const fireKlaxon = () => {
        if (checkMuted()) return;
        if (klaxonFired) return;
        if (!klaxonAudio) return;
        klaxonFired = true;
        klaxonAudio.currentTime = 0;
        klaxonAudio.play().catch(() => banner());
    };

    // -- replay warning until we've played the desired number of times
    warningAudio.addEventListener('ended', () => {
        timesPlayed++;
        if (timesPlayed < playCount) {
            warningAudio.currentTime = 0;
            warningAudio.play();
        }
    });

    // -- fallback banner when autoplay is blocked the very first visit
    const banner = () => {
        if (localStorage.getItem('klaxon-unlocked')) return;
        const div = Object.assign(document.createElement('div'), {
            id: 'unlock-banner',
            textContent: 'Tap once to enable sound'
        });
        Object.assign(div.style, {
            position: 'fixed', inset: 0, zIndex: 9999,
            display: 'flex', justifyContent: 'center', alignItems: 'center',
            background: '#c00', color: '#fff', font: '1.2rem system-ui',
            cursor: 'pointer'
        });
        div.onclick = () => {
            warningAudio.play().then(() => {
                div.remove();
                localStorage.setItem('klaxon-unlocked', 1);
            });
        };
        document.body.appendChild(div);
    };

    // -- detect alerts: errorNotice gets klaxon, level4 gets warning
    const runTest = () => {
        // errorNotice (most severe) gets the klaxon horn
        if (document.querySelector('.errorNotice')) {
            fireKlaxon();
        }
        // level4 (critical but not error) gets warning ding
        else if (document.querySelector('.level4')) {
            fireWarning();
        }
    };

    // run when DOM is ready and watch for rows added later by Ajax
    document.addEventListener('DOMContentLoaded', () => {
        klaxonAudio = document.getElementById('klaxon');
        runTest();
        new MutationObserver(runTest).observe(document.body, { childList: true, subtree: true });
    });
})();
