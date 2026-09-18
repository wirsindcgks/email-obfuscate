/**
 * "Website pruefen": holt die URL-Liste, prueft die Seiten parallel ueber die
 * REST-Endpunkte des Plugins und zeigt den Bericht. Texte kommen aus PHP
 * (window.emailObfuscateScan), Inhalte werden nur als Text eingesetzt.
 */
(() => {
    'use strict';

    const config = window.emailObfuscateScan;
    const button = document.getElementById('eo-scan-start');
    if (!config || !button) {
        return;
    }

    const strings = config.strings;
    const status = document.getElementById('eo-scan-status');
    const progress = document.getElementById('eo-scan-progress');
    const report = document.getElementById('eo-scan-report');

    const format = (text, ...values) => {
        let next = 0;
        return text.replace(/%(?:(\d+)\$)?[ds]/g, (match, position) =>
            String(values[position ? Number(position) - 1 : next++]));
    };

    const element = (tag, attributes = {}, ...children) => {
        const node = document.createElement(tag);
        Object.entries(attributes).forEach(([name, value]) => node.setAttribute(name, value));
        children.filter((child) => child !== null && child !== undefined)
            .forEach((child) => node.append(child));
        return node;
    };

    const api = async (path, options = {}) => {
        const response = await fetch(config.root + path, {
            credentials: 'same-origin',
            ...options,
            headers: { 'X-WP-Nonce': config.nonce, 'Content-Type': 'application/json' },
        });
        const data = await response.json().catch(() => null);
        if (!response.ok) {
            throw new Error((data && data.message) || `HTTP ${response.status}`);
        }
        return data;
    };

    const pathOf = (url) => {
        try {
            const parsed = new URL(url);
            return decodeURI(parsed.pathname + parsed.search);
        } catch (error) {
            return url;
        }
    };

    const countOf = (pages, test) => pages.reduce((sum, page) =>
        sum + (page.findings || []).filter(test).reduce((total, finding) => total + (finding.count || 1), 0), 0);

    const addressLabel = (finding) => {
        const parts = [finding.address];
        if (finding.count > 1) {
            parts.push(`×${finding.count}`);
        }
        if (finding.context && finding.context !== 'html') {
            parts.push(strings.contexts[finding.context] || finding.context);
        }
        return parts.join(' ');
    };

    const render = (result) => {
        const pages = result.pages || [];
        const isOpen = (finding) => finding.status === 'open';
        const open = countOf(pages, isOpen);
        const encoded = countOf(pages, (finding) => finding.status === 'encoded');
        const partial = countOf(pages, (finding) => finding.status === 'partial');
        const errors = pages.filter((page) => page.error).length;
        const cache = pages.some((page) => (page.findings || []).some((finding) => finding.reason === 'cache'));

        const notices = [];
        const summary = [
            format(strings.summaryHead, new Date(result.time * 1000).toLocaleString(document.documentElement?.lang || undefined), pages.length),
            open ? format(strings.summaryOpen, open) : strings.allClean,
            format(strings.summaryEncoded, encoded),
        ];
        if (partial) {
            summary.push(format(strings.summaryPartial, partial));
        }
        if (errors) {
            summary.push(format(strings.summaryErrors, errors));
        }
        const level = open ? 'error' : (errors ? 'warning' : 'success');
        notices.push(element('div', { class: `notice notice-${level} inline` }, element('p', {}, summary.join(' · '))));
        if (cache) {
            notices.push(element('div', { class: 'notice notice-warning inline' }, element('p', {}, strings.hintCache)));
        }
        if (pages.length && errors === pages.length) {
            notices.push(element('div', { class: 'notice notice-error inline' }, element('p', {}, strings.hintLoopback)));
        }
        (result.errors || []).forEach((message) => {
            notices.push(element('div', { class: 'notice notice-warning inline' }, element('p', {}, message)));
        });

        const relevant = pages.filter((page) => page.error || (page.findings || []).length);
        if (!relevant.length) {
            report.replaceChildren(...notices, pages.length ? element('p', {}, strings.noAddresses) : '');
            return;
        }

        const filter = element('input', { type: 'checkbox', id: 'eo-scan-only-open' });
        filter.checked = open > 0;
        const filterLabel = element('label', { for: 'eo-scan-only-open' }, filter, ` ${strings.onlyOpen}`);

        const body = element('tbody');
        relevant.forEach((page) => {
            const findings = page.findings || [];
            const openFindings = findings.filter(isOpen);
            const row = element('tr', { class: openFindings.length || page.error ? 'eo-scan-problem' : 'eo-scan-clean' },
                element('td', {}, element('a', { href: page.url, target: '_blank', rel: 'noopener' }, pathOf(page.url))));

            const openCell = element('td');
            if (page.error) {
                openCell.append(element('span', { class: 'eo-scan-error' }, page.error));
            } else if (openFindings.length) {
                const list = element('ul');
                openFindings.forEach((finding) => list.append(element('li', {},
                    element('strong', { class: 'eo-scan-open' }, addressLabel(finding)),
                    finding.reason ? element('br') : null,
                    finding.reason ? element('span', { class: 'description' }, strings.reasons[finding.reason] || finding.reason) : null)));
                openCell.append(list);
            } else {
                openCell.append('–');
            }

            const encodedCell = element('td');
            const hidden = findings.filter((finding) => !isOpen(finding));
            if (hidden.length) {
                const list = element('ul');
                hidden.forEach((finding) => list.append(element('li', {},
                    addressLabel(finding),
                    finding.status === 'partial' ? element('span', { class: 'eo-scan-partial' }, ` (${strings.partial})`) : null)));
                encodedCell.append(list);
            } else {
                encodedCell.append('–');
            }

            row.append(openCell, encodedCell);
            body.append(row);
        });

        const table = element('table', { class: 'widefat striped eo-scan-table' },
            element('thead', {}, element('tr', {},
                element('th', {}, strings.colPage),
                element('th', {}, strings.colOpen),
                element('th', {}, strings.colEncoded))),
            body);

        const applyFilter = () => table.classList.toggle('eo-scan-only-open', filter.checked);
        filter.addEventListener('change', applyFilter);
        applyFilter();

        report.replaceChildren(...notices, element('p', {}, filterLabel), table);
    };

    button.addEventListener('click', async () => {
        button.disabled = true;
        status.textContent = strings.collecting;
        progress.hidden = true;

        let list;
        try {
            list = await api('scan/urls');
        } catch (error) {
            status.textContent = `${strings.failed} ${error.message}`;
            button.disabled = false;
            return;
        }

        const urls = list.urls;
        const pages = new Array(urls.length);
        let next = 0;
        let done = 0;
        progress.max = urls.length;
        progress.value = 0;
        progress.hidden = false;
        status.textContent = format(strings.progress, 0, urls.length);

        const worker = async () => {
            while (next < urls.length) {
                const index = next++;
                try {
                    pages[index] = await api('scan/page', { method: 'POST', body: JSON.stringify({ url: urls[index] }) });
                } catch (error) {
                    pages[index] = { url: urls[index], error: error.message };
                }
                progress.value = ++done;
                status.textContent = format(strings.progress, done, urls.length);
            }
        };
        await Promise.all(Array.from({ length: Math.min(config.concurrency, urls.length) }, worker));

        const result = { time: Math.floor(Date.now() / 1000), pages, errors: list.errors || [] };
        render(result);
        progress.hidden = true;
        status.textContent = '';
        button.disabled = false;

        // Speichern ist Komfort - schlaegt es fehl, bleibt der Bericht trotzdem stehen.
        api('scan/result', { method: 'POST', body: JSON.stringify(result) }).catch(() => {});
    });

    if (config.last && config.last.pages) {
        render(config.last);
    }
})();
