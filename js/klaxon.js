/* klaxon.js -- plays alert sound on critical errors   KB Benton 2025-05-13 */

(() => {
    const audio = new Audio('Images/warning-ding.mp3');
    const playCount = 3;  // number of times to play the alert
    audio.preload = 'auto';

    // -- check if muted via URL parameter
    const urlParams = new URLSearchParams(window.location.search);
    const isMuted = urlParams.get('mute') === '1';

    // -- attempt autoplay on every hard refresh
    let timesPlayed = 0;
    const fire = () => {
        if (isMuted) return;                // respect mute setting
        if (fire.done) return;              // one-shot gate
        fire.done = true;
        timesPlayed = 0;
        audio.currentTime = 0;
        audio.play().catch(() => banner()); // Chrome may block - fallback
    };

    // -- replay until we've played the desired number of times
    audio.addEventListener('ended', () => {
        timesPlayed++;
        if (timesPlayed < playCount) {
            audio.currentTime = 0;
            audio.play();
        }
    });

    // -- fallback banner when autoplay is blocked the very first visit
    const banner = () => {
        if (localStorage.getItem('klaxon-unlocked')) return;       // user opted out
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
        div.onclick = () => audio.play().then(() => {
            div.remove(); localStorage.setItem('klaxon-unlocked', 1);
        });
        document.body.appendChild(div);
    };

    // -- detect a *level-4* (critical) row as soon as it appears
    const triggerSelector = '.level4, .errorNotice';
    const runTest = () => document.querySelector(triggerSelector) && fire();

    // run when DOM is ready and watch for rows added later by Ajax
    document.addEventListener('DOMContentLoaded', () => {
        runTest();
        new MutationObserver(runTest).observe(document.body, { childList: true, subtree: true });
    });
})();
