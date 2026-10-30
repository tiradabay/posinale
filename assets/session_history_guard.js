(() => {
    const script = document.currentScript;
    const sessionUrl = script.dataset.sessionUrl;
    const loginUrl = script.dataset.loginUrl;
    const allowedRoles = (script.dataset.allowedRoles || '').split(',').filter(Boolean);
    // Hide snapshots before they enter the back/forward cache.
    window.addEventListener('pagehide', () => {
        document.documentElement.style.visibility = 'hidden';
    });
    window.addEventListener('pageshow', async (event) => {
        if (!event.persisted) {
            document.documentElement.style.visibility = '';
            return;
        }
        try {
            const response = await fetch(sessionUrl, {cache: 'no-store', credentials: 'same-origin'});
            const session = response.ok ? await response.json() : null;
            if (!session || !session.loggedIn) {
                window.location.replace(loginUrl);
                return;
            }
            if (!allowedRoles.includes(session.role)) {
                // Keep the snapshot hidden; the server chooses the authorized page.
                window.location.reload();
                return;
            }
            document.documentElement.style.visibility = '';
        } catch (_) {
            window.location.replace(loginUrl);
        }
    });
})();
