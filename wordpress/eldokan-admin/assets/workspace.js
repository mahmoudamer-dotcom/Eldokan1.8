/* global eldokanAdminUI */
(() => {
    'use strict';
    if (!document.body.classList.contains('eda-admin-screen')) return;
    const strings = window.eldokanAdminUI || {};
    const roots = [...document.querySelectorAll('.eda')];
    // Third-party admin notices must not be displayed inside the ElDokan hero.
    // Preserve their original nodes, dismissal behavior and WordPress classes.
    function repositionInjectedNotices() {
        document.querySelectorAll('.eda-admin-screen .wrap.eda .eda-hero').forEach(hero => {
            const root = hero.closest('.wrap.eda');
            if (!root) return;
            hero.querySelectorAll('.notice, .updated, .error').forEach(notice => {
                const region = document.querySelector('.eda-notice-region');
                if (region) region.append(notice);
                else root.before(notice);
            });
        });
    }
    repositionInjectedNotices();
    window.addEventListener('load', repositionInjectedNotices, { once: true });
    document.querySelectorAll('.wrap.eda .eda-hero').forEach(hero => {
        new MutationObserver(repositionInjectedNotices).observe(hero, { childList: true, subtree: true });
    });
    const make = (tag, text, className) => {
        const el = document.createElement(tag);
        if (text) el.textContent = text;
        if (className) el.className = className;
        return el;
    };
    // Native Stories list/edit controls continue to be owned by WordPress.
    if (document.body.classList.contains('post-type-eldokan_story')) {
        const wrap = document.querySelector('#wpbody-content > .wrap');
        if (wrap) { wrap.classList.add('eda'); roots.push(wrap); }
    }
    for (const root of new Set(roots)) {
        const sellerNavigation = root.querySelector('.eda-seller-control-links');
        if (sellerNavigation) {
            const panels = [];
            sellerNavigation.querySelectorAll('a[href^="#"]').forEach(link => {
                const section = root.querySelector(link.getAttribute('href'));
                if (!section) { link.remove(); return; }
                if (!section.classList.contains('eda-card')) return;
                const panel = make('details', '', 'eda-seller-panel'); panel.open = true;
                const summary = make('summary', link.textContent);
                section.before(panel); panel.append(summary, section); panels.push(panel);
                link.addEventListener('click', () => { panel.open = true; });
            });
            if (panels.length) {
                const toggle = make('button', strings.collapseSections, 'button'); toggle.type = 'button';
                toggle.addEventListener('click', () => {
                    const expand = panels.some(panel => !panel.open);
                    panels.forEach(panel => { panel.open = expand; });
                    toggle.textContent = expand ? strings.collapseSections : strings.expandSections;
                });
                sellerNavigation.append(toggle);
                // A URL fragment is not necessarily a valid CSS selector.
                if (window.location.hash) {
                    try {
                        document.getElementById(decodeURIComponent(window.location.hash.slice(1)))?.closest('.eda-seller-panel')?.setAttribute('open', '');
                    } catch (_) { /* Ignore malformed percent-encoding in an external URL. */ }
                }
            }
        }
        root.querySelectorAll('.notice').forEach(notice => {
            if (!notice.hasAttribute('role')) notice.setAttribute('role', notice.classList.contains('notice-error') ? 'alert' : 'status');
        });
        root.querySelectorAll('#wMsg,#mMsg').forEach(status => {
            status.setAttribute('role', 'status'); status.setAttribute('aria-live', 'polite');
        });
        root.querySelectorAll('form[method="get"],form[method="GET"]').forEach(form => {
            if (form.id !== 'posts-filter') form.classList.add('eda-filter-bar');
        });
        root.querySelectorAll('.eda-inline-commission-form').forEach(form => {
            form.addEventListener('submit', event => {
                if (event.defaultPrevented) return;
                if (form.dataset.submitting === '1') { event.preventDefault(); return; }
                form.dataset.submitting = '1';
                form.setAttribute('aria-busy', 'true');
                form.querySelector('button[type="submit"],button:not([type])')?.setAttribute('disabled', '');
            });
        });
        root.querySelectorAll('table.eda-table,table.widefat').forEach((table, index) => {
            let wrap = table.parentElement;
            if (!wrap.classList.contains('eda-table-scroll')) {
                wrap = make('div', '', 'eda-table-scroll');
                table.before(wrap); wrap.append(table);
            }
            wrap.tabIndex = 0; wrap.setAttribute('role', 'region'); wrap.setAttribute('aria-label', strings.table);
            const head = table.rows[0];
            if (head) [...head.cells].forEach(cell => { if (cell.tagName === 'TH') cell.scope = 'col'; });
            // Existing server filters/pagination remain authoritative. Never filter
            // tables containing editable controls or the native WP bulk-action list.
            if (!head || table.id === 'the-list' || table.classList.contains('wp-list-table') || table.querySelector('input,select,textarea,form') || table.closest('form')) return;
            const rows = [...table.rows].filter(row => row.querySelector('td'));
            if (!rows.length) {
                const row = table.insertRow(); const cell = row.insertCell();
                cell.colSpan = Math.max(head.cells.length, 1); cell.className = 'eda-table-empty'; cell.textContent = strings.empty;
                return;
            }
            if (rows.length < 12) return;
            const tools = make('div', '', 'eda-table-tools');
            const label = make('label', strings.find);
            const search = make('input'); search.type = 'search'; label.append(search);
            const help = make('small', strings.loaded); help.id = `eda-table-help-${index}`;
            search.setAttribute('aria-describedby', help.id);
            const compactLabel = make('label'); const compact = make('input'); compact.type = 'checkbox';
            compactLabel.append(compact, document.createTextNode(strings.compact));
            const status = make('span'); status.setAttribute('role', 'status');
            tools.append(label, help, compactLabel, status); wrap.before(tools);
            compact.addEventListener('change', () => wrap.classList.toggle('eda-compact', compact.checked));
            search.addEventListener('input', () => {
                const term = search.value.trim().toLocaleLowerCase();
                let count = 0;
                rows.forEach(row => { row.hidden = !row.textContent.toLocaleLowerCase().includes(term); if (!row.hidden) count++; });
                status.textContent = count ? `${count} / ${rows.length}` : strings.noMatches;
            });
        });
        root.querySelectorAll('.eda-badge').forEach(badge => {
            if (['pending', 'draft', 'on-hold', 'Pending', 'مسودة', 'قيد الانتظار'].includes(badge.textContent.trim())) badge.classList.add('eda-pending');
        });
        // Accessible descriptions for existing unlabeled search controls, without
        // replacing labels, names, IDs, values, hidden nonces or form destinations.
        root.querySelectorAll('input[placeholder]').forEach(input => {
            if (!input.labels?.length && !input.hasAttribute('aria-label')) input.setAttribute('aria-label', input.placeholder);
        });
        root.addEventListener('invalid', event => {
            const field = event.target; field.classList.add('eda-invalid');
            field.closest('.eda-seller-panel')?.setAttribute('open', '');
            field.setAttribute('aria-invalid', 'true');
            let notice = field.form?.querySelector('.eda-validation');
            if (!notice && field.form) { notice = make('p', strings.invalid, 'eda-validation'); notice.setAttribute('role', 'alert'); field.form.prepend(notice); }
        }, true);
        root.addEventListener('input', event => {
            const field = event.target;
            if (field.validity?.valid) { field.classList.remove('eda-invalid'); field.removeAttribute('aria-invalid'); }
            const form = field.form;
            if (form && !form.querySelector('.eda-invalid')) form.querySelector('.eda-validation')?.remove();
        });
    }
    // Browser Back after a failed save can restore the form from its page cache.
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('.eda-inline-commission-form').forEach(form => {
            delete form.dataset.submitting;
            form.removeAttribute('aria-busy');
            form.querySelector('button[type="submit"],button:not([type])')?.removeAttribute('disabled');
        });
    });
    // Native <details> supports keyboard interaction without a custom menu role.
    document.querySelectorAll('.eda-nav-group').forEach(group => {
        group.addEventListener('toggle', () => {
            if (group.open) document.querySelectorAll('.eda-nav-group').forEach(other => { if (other !== group) other.open = false; });
        });
        group.addEventListener('keydown', event => { if (event.key === 'Escape') { group.open = false; group.querySelector('summary').focus(); } });
    });
    document.addEventListener('click', event => {
        if (!event.target.closest('.eda-nav-group')) document.querySelectorAll('.eda-nav-group[open]').forEach(group => { group.open = false; });
    });
    // Add a confirmation only where the baseline had none. Existing inline
    // confirmations and all server nonce/capability checks remain untouched.
    function confirmAction(trigger, proceed) {
        if (!window.HTMLDialogElement || !HTMLDialogElement.prototype.showModal) {
            if (window.confirm(strings.delete)) proceed();
            return;
        }
        const dialog = make('dialog', '', 'eda-confirm eda');
        const title = make('h2', strings.confirm); title.id = 'eda-confirm-title';
        dialog.setAttribute('aria-labelledby', title.id);
        const actions = make('div', '', 'eda-confirm-actions');
        const cancel = make('button', strings.cancel, 'button'); cancel.type = 'button'; cancel.autofocus = true;
        const accept = make('button', strings.continue, 'button eda-danger-button'); accept.type = 'button';
        actions.append(cancel, accept); dialog.append(title, make('p', strings.delete), actions); document.body.append(dialog);
        const dismiss = () => { dialog.close(); dialog.remove(); trigger.focus(); };
        cancel.addEventListener('click', dismiss);
        dialog.addEventListener('cancel', event => { event.preventDefault(); dismiss(); });
        accept.addEventListener('click', () => { dismiss(); proceed(); });
        dialog.showModal();
    }
    document.addEventListener('click', event => {
        const link = event.target.closest('.eda a.submitdelete,.eda a[href*="action=eldokan_admin_delete_commission"],.eda a[href*="action=eldokan_home_delete_item"]');
        if (!link || link.onclick || link.hasAttribute('onclick')) return;
        event.preventDefault(); confirmAction(link, () => { window.location.assign(link.href); });
    });
})();
