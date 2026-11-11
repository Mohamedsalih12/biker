/*
 * Fieldt360 Command Palette (Ctrl+K / Cmd+K)
 * Vanilla JS — no dependencies. Data comes from window.T360_COMMANDS:
 *   [{ title, group, icon, url, keywords, form? }]
 * If item.form is set, activating submits the DOM element with that id instead
 * of navigating.
 */
(function () {
    'use strict';

    var GROUP_ORDER = ['Navigate', 'Stocks', 'Create', 'MARN Integration', 'Agreement', 'User Pages', 'Project & Interaction', 'Admin Audit', 'Session', 'Recent'];
    var RECENT_KEY = 't360_recent_pages';
    var RECENT_LIMIT = 8;
    var MAX_ITEMS = 18;

    var palette, input, listEl;
    var items = [];
    var activeIndex = 0;
    var lastRendered = [];
    var isOpen = false;

    function els() {
        palette = document.getElementById('t360CommandPalette');
        input = document.getElementById('t360PaletteInput');
        listEl = document.getElementById('t360PaletteList');
        return palette && input && listEl;
    }

    function allItems() {
        var commands = (window.T360_COMMANDS || []).filter(function (c) {
            return c && c.title && c.group;
        });
        return commands;
    }

    /* ------------ fuzzy scoring ------------ */
    function scoreItem(item, q) {
        var title = item.title.toLowerCase();
        var hay = title;

        // Exact substring of the title wins outright.
        var idx = title.indexOf(q);
        if (idx >= 0) {
            return 300 - idx * 2;
        }

        // Keyword / synonym substring, match at word start preferred.
        var keywords = (item.keywords || '').toLowerCase();
        var kwIdx = keywords.indexOf(q);
        if (kwIdx >= 0) {
            return 180 - Math.min(kwIdx, 50);
        }

        // Fuzzy: every query char must appear in order in the title.
        var ti = 0;
        var streak = 0;
        var best = 0;
        for (var i = 0; i < q.length; i++) {
            var ch = q[i];
            if (ch === ' ') { continue; }
            var found = title.indexOf(ch, ti);
            if (found < 0) { return 0; }
            streak = found === ti ? streak + 1 : 1;
            best = Math.max(best, streak);
            ti = found + 1;
        }
        return 40 + best * 12;
    }

    function search(q) {
        var results;
        if (!q) {
            results = recentsRanked(allItems());
        } else {
            results = allItems()
                .map(function (item) {
                    var s = scoreItem(item, q);
                    return s > 0 ? { item: item, score: s } : null;
                })
                .filter(Boolean)
                .sort(function (a, b) { return b.score - a.score; })
                .slice(0, MAX_ITEMS)
                .map(function (r) { return r.item; });
        }
        return results.slice(0, MAX_ITEMS);
    }

    /* Merge recent URLs into the no-query view (most recent first). */
    function recentsRanked(commands) {
        var recents = readRecents();
        var out = [];
        var seen = {};
        recents.forEach(function (r) {
            if (seen[r.url]) { return; }
            seen[r.url] = true;
            out.push(r);
        });
        if (out.length && out.length < MAX_ITEMS) {
            commands.forEach(function (c) {
                if (out.length >= MAX_ITEMS) { return; }
                if (!c.form && !seen[c.url]) { out.push(c); seen[c.url] = true; }
            });
        }
        return out;
    }

    function readRecents() {
        try {
            var raw = window.localStorage.getItem(RECENT_KEY);
            var arr = raw ? JSON.parse(raw) : [];
            return Array.isArray(arr) ? arr.slice(0, RECENT_LIMIT) : [];
        } catch (e) {
            return [];
        }
    }

    function recordCurrentPage() {
        try {
            var here = window.location.pathname + window.location.search;
            if (window.location.pathname === '/login') { return; }
            var recents = readRecents().filter(function (r) {
                return r.url !== here;
            });
            recents.unshift({ url: here, title: document.title || here, group: 'Recent', icon: 'fas fa-clock' });
            window.localStorage.setItem(RECENT_KEY, JSON.stringify(recents.slice(0, RECENT_LIMIT)));
        } catch (e) { /* storage unavailable — recents simply stay empty */ }
    }

    /* ------------ rendering ------------ */
    function esc(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function titleHtml(item, q) {
        var safe = q ? q.toLowerCase().trim() : '';
        var shown = esc(item.title);
        if (safe) {
            var lower = item.title.toLowerCase();
            var at = lower.indexOf(safe);
            if (at >= 0) {
                shown =
                    esc(item.title.slice(0, at)) +
                    '<span class="t360-mark">' + esc(item.title.slice(at, at + safe.length)) + '</span>' +
                    esc(item.title.slice(at + safe.length));
            }
        }
        return shown;
    }

    function render() {
        var q = input.value.trim().toLowerCase();
        var results = search(q);
        lastRendered = results;
        activeIndex = Math.min(activeIndex, Math.max(0, results.length - 1));

        if (!results.length) {
            listEl.innerHTML =
                '<div class="t360-palette-empty">' +
                '<i class="fas fa-search"></i>' +
                'Nothing matches &ldquo;' + esc(q) + '&rdquo; — try a name, route or module.' +
                '</div>';
            return;
        }

        var groups = [];
        results.forEach(function (item) {
            var key = item.group || 'Navigate';
            if (!groups[key]) { groups[key] = []; }
            groups[key].push(item);
        });

        var order = GROUP_ORDER.filter(function (g) { return groups[g]; });
        Object.keys(groups).forEach(function (g) {
            if (order.indexOf(g) < 0) { order.push(g); }
        });

        var html = order.map(function (g) {
            var rows = groups[g].map(function (item) {
                var sub = q ? 'Press Enter to open' : (item.group === 'Recent' ? item.url : item.sub || item.url || '');
                if (item.group === 'Recent') {
                    var shown = item.url;
                    sub = shown.length > 58 ? '…' + shown.slice(-57) : shown;
                }
                return (
                    '<a class="t360-palette-item" role="option" href="' + esc(item.form ? 'javascript:void(0)' : item.url) + '" data-t360-idx="' + itemsIndexOf(results, item) + '">' +
                    '<i class="' + esc(item.icon || 'fas fa-arrow-right') + ' t360-item-icon"></i>' +
                    '<span class="t360-item-text">' +
                    '<span class="t360-item-title">' + titleHtml(item, q) + '</span>' +
                    '<span class="t360-item-sub">' + (q ? 'Press Enter to open' : esc(sub)) + '</span>' +
                    '</span>' +
                    '<kbd>↵</kbd>' +
                    '</a>'
                );
            }).join('');
            return '<div class="t360-palette-group">' + esc(g) + '</div>' + rows;
        }).join('');

            listEl.innerHTML = html;
            listEl.scrollTop = 0;
            refreshSelection(false);
        }

    function itemsIndexOf(results, item) {
        return results.indexOf(item);
    }

    function itemsByIndex(idx) {
        return lastRendered[idx];
    }

    /* ------------ selection & navigation ------------
       IMPORTANT: never call scrollIntoView() on hover changes — it scrolls
       ALL scrollable ancestors (including the page behind the modal) and the
       shifted rows re-synthesize mousemove events, creating a feedback loop
       of endless scrolling with un-clickable items. Keyboard navigation is
       the only scrolling caller, and it scrolls ONLY the list container. */
    function refreshSelection(scroll) {
        var nodes = listEl.querySelectorAll('.t360-palette-item');
        nodes.forEach(function (node, i) {
            node.setAttribute('aria-selected', i === activeIndex ? 'true' : 'false');
        });

        if (!scroll) { return; }

        var node = listEl.querySelector('.t360-palette-item[aria-selected="true"]');
        if (!node) { return; }

        var lr = listEl.getBoundingClientRect();
        var r = node.getBoundingClientRect();
        if (r.top < lr.top) {
            listEl.scrollTop += r.top - lr.top;
        } else if (r.bottom > lr.bottom) {
            listEl.scrollTop += r.bottom - lr.bottom;
        }
    }

    function move(delta) {
        if (!lastRendered.length) { return; }
        activeIndex = (activeIndex + delta + lastRendered.length) % lastRendered.length;
        refreshSelection(true);
    }

    function activate(item) {
        if (!item) { return; }
        if (item.form) {
            var form = document.getElementById(item.form);
            if (form) { form.submit(); }
            return;
        }
        window.location.href = item.url;
    }

    /* ------------ open / close ------------ */
    function open() {
        palette.classList.add('is-open');
        isOpen = true;
        input.value = '';
        activeIndex = 0;
        render();
        input.focus();
    }

    function close() {
        palette.classList.remove('is-open');
        isOpen = false;
        input.blur();
    }

    function toggle() {
        isOpen ? close() : open();
    }

    function init() {
        if (!els()) { return; }
        items = allItems();
        recordCurrentPage();

        document.addEventListener('keydown', function (e) {
            var inField = /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName) ||
                document.activeElement.isContentEditable;

            if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
                e.preventDefault();
                toggle();
                return;
            }

            if (!isOpen) { return; }

            if (e.key === 'Escape') {
                e.preventDefault();
                close();
                return;
            }

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                move(1);
                return;
            }

            if (e.key === 'ArrowUp') {
                e.preventDefault();
                move(-1);
                return;
            }

            if (e.key === 'Enter' && document.activeElement === input) {
                e.preventDefault();
                activate(itemsByIndex(activeIndex));
                return;
            }

            // Typing anywhere while open routes keystrokes into the search box.
            if (inField !== true && e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
                e.preventDefault();
                input.focus();
                input.value += e.key;
                render();
            }
        });

        input.addEventListener('input', function () {
            activeIndex = 0;
            render();
        });

        listEl.addEventListener('mousedown', function (e) {
            var itemNode = e.target.closest('.t360-palette-item');
            if (!itemNode) { return; }
            e.preventDefault();
            var chosen = itemsByIndex(parseInt(itemNode.getAttribute('data-t360-idx'), 10));
            if (!chosen) { return; }
            activate(chosen);
            if (!chosen.form) { close(); }
        });

        listEl.addEventListener('mousemove', function (e) {
            var itemNode = e.target.closest('.t360-palette-item');
            if (!itemNode) { return; }
            var idx = parseInt(itemNode.getAttribute('data-t360-idx'), 10);
            if (idx !== activeIndex) {
                activeIndex = idx;
                refreshSelection(false);
            }
        });

        var backdrop = palette.querySelector('.t360-palette-backdrop');
        if (backdrop) {
            backdrop.addEventListener('mousedown', close);
        }

        var trigger = document.getElementById('t360LauncherOpen');
        if (trigger) {
            trigger.addEventListener('click', toggle);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
