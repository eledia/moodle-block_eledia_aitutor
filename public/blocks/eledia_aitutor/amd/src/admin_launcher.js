// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Moves the admin-only eLeDia.ai Tutor launcher into the site navbar.
 *
 * @module     block_eledia_aitutor/admin_launcher
 * @copyright  2026 eLeDia GmbH, Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    /**
     * Ensure the shared stylesheet is present even when this hook runs after
     * Moodle has already printed the document head.
     */
    const ensureStylesheet = () => {
        const href = M.cfg.wwwroot + '/blocks/eledia_aitutor/styles.css';
        if (document.querySelector('link[href="' + href + '"]')) {
            return;
        }
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = href;
        document.head.appendChild(link);
    };

    /**
     * Initialise the launcher placement.
     */
    const init = () => {
        window.setTimeout(() => {
            ensureStylesheet();
            const host = document.getElementById('eat-admin-launcher-host');
            if (!host) {
                return;
            }

            const aiHost = document.getElementById('lh-ai-launcher-host');
            if (aiHost && aiHost.parentNode) {
                aiHost.parentNode.insertBefore(host, aiHost.nextSibling);
            } else {
                const usermenu = document.querySelector('.usermenu');
                if (usermenu && usermenu.parentNode) {
                    usermenu.parentNode.insertBefore(host, usermenu);
                } else {
                    const navbar = document.querySelector('.navbar .container-fluid, .navbar .container, .navbar');
                    if (navbar) {
                        navbar.appendChild(host);
                    }
                }
            }
            host.hidden = false;
        }, 0);
    };

    return {init};
});
