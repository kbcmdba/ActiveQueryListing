/* klaxon.js -- plays alert sound on critical errors   KB Benton 2025-05-13 */

(() => {
    // Set to true to enable debug logging (or use ?klaxon_debug=1 in URL)
    const DEBUG = new URLSearchParams(window.location.search).get('klaxon_debug') === '1';
    const log = (...args) => { if (DEBUG) console.log('[klaxon]', ...args); };

    log('klaxon.js loaded');

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
            const muted = expiry === 0 || Date.now() < expiry;
            log('checkMuted (URL param):', muted, 'expiry:', expiry);
            return muted;
        }
        // Legacy support: mute=1 means indefinite
        if (urlParams.get('mute') === '1') {
            log('checkMuted (legacy URL): true');
            return true;
        }
        // Check cookie for timed mute
        const match = document.cookie.match(/aql_mute_until=(\d+)/);
        if (match) {
            const expiry = parseInt(match[1], 10);
            const muted = expiry === 0 || Date.now() < expiry;
            log('checkMuted (cookie):', muted, 'expiry:', expiry, 'now:', Date.now());
            return muted;
        }
        log('checkMuted: false (no mute found)');
        return false;
    };

    // -- track which alerts have fired
    let warningFired = false;
    let klaxonFired = false;
    let timesPlayed = 0;

    const fireWarning = () => {
        log('fireWarning() called, checkMuted:', checkMuted(), 'warningFired:', warningFired);
        if (checkMuted()) return;
        if (warningFired) return;
        log('fireWarning() - PLAYING SOUND');
        warningFired = true;
        timesPlayed = 0;
        warningAudio.currentTime = 0;
        warningAudio.play().catch(() => banner());
    };

    const fireKlaxon = () => {
        log('fireKlaxon() called, checkMuted:', checkMuted(), 'klaxonFired:', klaxonFired);
        if (checkMuted()) return;
        if (klaxonFired) return;
        if (!klaxonAudio) return;
        log('fireKlaxon() - PLAYING SOUND');
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

    // -- helper to extract hostname from a table row/cell
    const getHostnameFromElement = (element) => {
        // Navigate up to find the row
        const row = element.closest('tr');
        if (!row) return null;
        // Get first cell which contains the server link
        const serverCell = row.querySelector('td:first-child a');
        if (serverCell) {
            // Extract hostname from link text (format: hostname:port)
            return serverCell.textContent.trim();
        }
        return null;
    };

    // -- check if a host is in maintenance window (database-level)
    const isHostInMaintenance = (hostname) => {
        if (typeof window.hostsInMaintenance === 'undefined') return false;
        return window.hostsInMaintenance[hostname] === true;
    };

    // -- check if a host is locally silenced (browser-level)
    const isHostLocallySilenced = (hostname) => {
        // Use hostId lookup
        if (typeof window.hostIdMap === 'undefined') return false;
        const hostId = window.hostIdMap[hostname];
        if (!hostId) return false;
        if (typeof window.isHostLocallySilenced === 'function' && window.isHostLocallySilenced(hostId)) {
            return true;
        }
        // Also check group silencing
        if (typeof window.hostGroupMap !== 'undefined' && typeof window.isHostGroupLocallySilenced === 'function') {
            const groups = window.hostGroupMap[hostname];
            if (groups && window.isHostGroupLocallySilenced(groups)) {
                return true;
            }
        }
        return false;
    };

    // -- combined check: database maintenance OR local silencing
    const isHostSilenced = (hostname) => {
        return isHostInMaintenance(hostname) || isHostLocallySilenced(hostname);
    };

    // -- check if ALL displayed hosts are silenced (maintenance or local)
    const areAllHostsSilenced = () => {
        if (typeof window.hostsInMaintenance === 'undefined' && typeof window.hostIdMap === 'undefined') return false;
        const hosts = Object.keys(window.hostsInMaintenance || window.hostIdMap || {});
        if (hosts.length === 0) return false;
        return hosts.every(h => isHostSilenced(h));
    };

    // Legacy function for compatibility
    const areAllHostsInMaintenance = () => areAllHostsSilenced();

    // -- detect alerts: errorNotice gets klaxon, level4 gets warning
    // Suppresses alerts for hosts in maintenance windows or locally silenced
    const runTest = () => {
        // Skip all alerts if all hosts are silenced
        if (areAllHostsSilenced()) {
            log('runTest: All hosts silenced, skipping alerts');
            return;
        }

        // Check for errorNotice (most severe) - gets the klaxon horn
        const errorElements = document.querySelectorAll('.errorNotice');
        let hasActiveError = false;
        errorElements.forEach(el => {
            const hostname = getHostnameFromElement(el);
            if (!hostname || !isHostSilenced(hostname)) {
                hasActiveError = true;
            }
        });

        if (hasActiveError && errorElements.length > 0) {
            log('runTest: Active error found (not silenced)');
            fireKlaxon();
            return;
        }

        // Check for level4 (critical but not error) - gets warning ding
        const level4Elements = document.querySelectorAll('.level4');
        let hasActiveLevel4 = false;
        level4Elements.forEach(el => {
            const hostname = getHostnameFromElement(el);
            if (!hostname || !isHostSilenced(hostname)) {
                hasActiveLevel4 = true;
            }
        });

        if (hasActiveLevel4 && level4Elements.length > 0) {
            log('runTest: Active level4 found (not silenced)');
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
