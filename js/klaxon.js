/* klaxon.js  ── plays the horn exactly once per full reload   KB Benton 2025‑05‑13 */

(() => {
    const audio = new Audio('Images/honk-alarm-repeat-loop-101015.mp3');
    audio.preload = 'auto';

    // ── attempt autoplay on every hard refresh
    const fire = () => {
        if (fire.done) return;              // one‑shot gate
        fire.done = true;
        audio.currentTime = 0;
        audio.play().catch(() => banner()); // Chrome may block → fallback
    };

    // ── fallback banner when autoplay is blocked the very first visit
    const banner = () => {
        if (localStorage.getItem('klaxon‑unlocked')) return;       // user opted out
        const div       = Object.assign(document.createElement('div'), {
            id: 'unlock‑banner',
            textContent: '🔊 Tap once to enable sound'
        });
        Object.assign(div.style, {
            position: 'fixed', inset: 0, zIndex: 9999,
            display: 'flex', justifyContent: 'center', alignItems: 'center',
            background: '#c00', color: '#fff', font: '1.2rem system-ui',
            cursor: 'pointer'
        });
        div.onclick = () => audio.play().then(() => {
            div.remove(); localStorage.setItem('klaxon‑unlocked', 1);
        });
        document.body.appendChild(div);
    };

    // ── detect a *level-4* (critical) row as soon as it appears
    const triggerSelector = '.level4, .errorNotice';
    const runTest = () => document.querySelector(triggerSelector) && fire();

    // run immediately for already‑rendered rows
    document.addEventListener('DOMContentLoaded', runTest);

    // watch for rows added later by Ajax
    new MutationObserver(runTest).observe(document.body, { childList: true, subtree: true });
})();
