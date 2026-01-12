/* klaxon.js -- plays alert sound on critical errors   KB Benton 2025-05-13 */

(() => {
    // Set to true to enable debug logging (or use ?klaxon_debug=1 in URL)
    const DEBUG = new URLSearchParams(window.location.search).get('klaxon_debug') === '1';
    const log = (...args) => { if (DEBUG) console.log('[klaxon]', ...args); };

    // User-friendly debug mode (use ?debugAlerts=1 in URL) - shows alert() popup
    const DEBUG_ALERTS = new URLSearchParams(window.location.search).get('debugAlerts') === '1';
    let debugShown = false;

    log('klaxon.js loaded');

    const warningAudio = new Audio('Images/warning-ding.mp3');  // level4 (critical)
    let klaxonAudio = null;  // errorNotice (error/level9) - set after DOM ready
    const playCount = 3;  // number of times to play the alert
    warningAudio.preload = 'auto';

    // -- speech synthesis for announcing affected hosts
    const canSpeak = () => {
        if (typeof window.speechAlertsEnabled !== 'undefined' && !window.speechAlertsEnabled) {
            return false;
        }
        return 'speechSynthesis' in window;
    };

    // Track hosts that are alerting for speech announcement
    let alertingHosts = { error: [], level4: [] };

    const collectAlertingHosts = () => {
        alertingHosts = { error: [], level4: [] };

        // Collect error hosts - only from table rows, not UI badges
        const errorElements = document.querySelectorAll('tr .errorNotice, tr.level9 .errorNotice');
        errorElements.forEach(el => {
            const hostname = getHostnameFromElement(el);
            if (hostname && !isHostSilenced(hostname) && alertingHosts.error.indexOf(hostname) === -1) {
                alertingHosts.error.push(hostname);
            }
        });

        // Collect level4 hosts (only if not already in error) - exclude UI badges
        const level4Elements = document.querySelectorAll('tr.level4:not(.scoreboard-row)');
        level4Elements.forEach(el => {
            const hostname = getHostnameFromElement(el);
            if (hostname && !isHostSilenced(hostname) &&
                alertingHosts.error.indexOf(hostname) === -1 &&
                alertingHosts.level4.indexOf(hostname) === -1) {
                alertingHosts.level4.push(hostname);
            }
        });

        log('collectAlertingHosts:', alertingHosts);
    };

    const speakAlert = (type) => {
        if (!canSpeak()) {
            log('speakAlert: speech not available or disabled');
            return;
        }

        const hosts = type === 'error' ? alertingHosts.error : alertingHosts.level4;
        if (hosts.length === 0) return;

        let message;
        const alertType = type === 'error' ? 'Error' : 'Critical';

        // Clean up hostnames for speech (remove port, replace dots)
        const cleanHost = (h) => {
            let name = h.split(':')[0];  // Remove port
            name = name.replace(/\./g, ' dot ');  // Make dots pronounceable
            return name;
        };

        if (hosts.length === 1) {
            message = alertType + ' on ' + cleanHost(hosts[0]);
        } else if (hosts.length <= 3) {
            message = alertType + ' on ' + hosts.length + ' hosts: ' +
                hosts.map(cleanHost).join(', ');
        } else {
            message = alertType + ' on ' + hosts.length + ' hosts: ' +
                hosts.slice(0, 3).map(cleanHost).join(', ') +
                ', and ' + (hosts.length - 3) + ' more';
        }

        log('speakAlert:', message);

        const utterance = new SpeechSynthesisUtterance(message);
        utterance.rate = 1.0;
        utterance.pitch = 1.0;
        utterance.volume = 0.8;

        // Speak after a delay to let the alert sound finish
        setTimeout(() => {
            speechSynthesis.speak(utterance);
        }, 2000);
    };

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
        collectAlertingHosts();
        speakAlert('level4');
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
        collectAlertingHosts();
        speakAlert('error');
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
        // Check for data-hostname attribute first (used on error rows)
        if (row.dataset.hostname) {
            return row.dataset.hostname;
        }
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

    // -- get silencing reason for debug display
    const getSilenceReason = (hostname) => {
        const reasons = [];
        if (isHostInMaintenance(hostname)) reasons.push('maintenance window');
        if (isHostLocallySilenced(hostname)) reasons.push('local silence');
        return reasons.length > 0 ? reasons.join(' + ') : null;
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

    // -- show debug box (only once per page load, inserted below graphs)
    const showDebugBox = () => {
        if (!DEBUG_ALERTS || debugShown) return;
        debugShown = true;

        // Wait a moment for data to load
        setTimeout(() => {
            // Build content
            let html = '<strong>Global mute:</strong> ' + (checkMuted() ? 'ON' : 'OFF') + '<br><br>';

            // Collect silenced hosts (deduplicated)
            const silencedSet = {};
            const alertingSet = {};

            // Check error elements - only table rows, not UI badges
            const errorElements = document.querySelectorAll('tr .errorNotice, tr.level9 .errorNotice');
            errorElements.forEach(el => {
                const hostname = getHostnameFromElement(el);
                if (hostname && !silencedSet[hostname] && !alertingSet[hostname]) {
                    const reason = getSilenceReason(hostname);
                    if (reason) {
                        silencedSet[hostname] = '[ERROR] (' + reason + ')';
                    } else {
                        alertingSet[hostname] = '[ERROR]';
                    }
                }
            });

            // Check level4 elements - exclude UI badges
            const level4Elements = document.querySelectorAll('tr.level4:not(.scoreboard-row)');
            level4Elements.forEach(el => {
                const hostname = getHostnameFromElement(el);
                if (hostname && !silencedSet[hostname] && !alertingSet[hostname]) {
                    const reason = getSilenceReason(hostname);
                    if (reason) {
                        silencedSet[hostname] = '[LEVEL4] (' + reason + ')';
                    } else {
                        alertingSet[hostname] = '[LEVEL4]';
                    }
                }
            });

            // Show maintenance window status for debugging
            html += '<strong>hostsInMaintenance:</strong><br>';
            if (typeof window.hostsInMaintenance !== 'undefined') {
                const entries = Object.entries(window.hostsInMaintenance).sort((a, b) => a[0].localeCompare(b[0]));
                html += entries.length > 0 ? entries.map(([h, v]) => '  ' + h + ': ' + v).join('<br>') : '  (empty)';
            } else {
                html += '  (undefined)';
            }
            html += '<br><br>';

            html += '<strong>SILENCED (no alert):</strong><br>';
            const silencedList = Object.entries(silencedSet).map(([h, v]) => h + ' ' + v).sort();
            html += silencedList.length > 0 ? silencedList.join('<br>') : '  (none)';
            html += '<br><br>';

            html += '<strong>ALERTING:</strong><br>';
            const alertingList = Object.entries(alertingSet).map(([h, v]) => h + ' ' + v).sort();
            html += alertingList.length > 0 ? alertingList.join('<br>') : '  (none)';

            // Create the debug box
            const box = document.createElement('div');
            box.id = 'alertDebugBox';
            box.className = 'alert-debug-box';
            box.innerHTML = '<div class="alert-debug-header">'
                + '<span class="alert-debug-title">Alert Debug</span>'
                + '<div>'
                + '<button id="debugCopyBtn" class="alert-debug-btn" onclick="copyToClipboard(\'debugContent\', \'debugCopyBtn\')">Copy</button>'
                + '<button class="alert-debug-btn" onclick="this.closest(\'.alert-debug-box\').remove()">Close</button>'
                + '</div></div>'
                + '<div id="debugContent">' + html + '</div>';

            // Find insertion point - look for noteworthy section or header table
            const noteworthy = document.getElementById('noteworthy');
            if (noteworthy) {
                noteworthy.parentNode.insertBefore(box, noteworthy);
            } else {
                // Fallback - insert after header table
                const headerTable = document.querySelector('.headerTableTd')?.closest('table');
                if (headerTable) {
                    headerTable.parentNode.insertBefore(box, headerTable.nextSibling);
                } else {
                    document.body.insertBefore(box, document.body.firstChild);
                }
            }
        }, 3000);  // Wait 3 seconds for AJAX data to load
    };

    // -- detect alerts: errorNotice gets klaxon, level4 gets warning
    // Suppresses alerts for hosts in maintenance windows or locally silenced
    // Only checks actual data rows, not UI elements like scoreboard badges
    const runTest = () => {
        // Skip all alerts if all hosts are silenced
        if (areAllHostsSilenced()) {
            log('runTest: All hosts silenced, skipping alerts');
            return;
        }

        // Check for errorNotice (most severe) - gets the klaxon horn
        // Only look in table rows, not scoreboard/overview badges
        const errorElements = document.querySelectorAll('tr .errorNotice, tr.level9 .errorNotice');
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
        // Exclude scoreboard and dbtype badges which also use level classes
        const level4Elements = document.querySelectorAll('tr.level4:not(.scoreboard-row)');
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

        // Show debug box if enabled
        if (DEBUG_ALERTS) {
            showDebugBox();
        }
    });
})();
