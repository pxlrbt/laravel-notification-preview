@extends('notification-viewer::layout')

@section('content')
    <div class="nv-shell">
        <aside class="nv-sidebar">
            <div class="nv-sidebar-header">
                <div class="nv-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
                    </svg>
                    <input id="nv-search" type="search" placeholder="Search" autocomplete="off" spellcheck="false">
                </div>

                <div class="nv-segmented nv-kind-filter" id="nv-kind-filter" role="group" aria-label="Type" hidden>
                    <button type="button" data-kind="all">All</button>
                    <button type="button" data-kind="notification">Notifications</button>
                    <button type="button" data-kind="mailable">Mailables</button>
                </div>
            </div>
            <nav class="nv-list" id="nv-list"></nav>
        </aside>

        <main class="nv-main">
            <div class="nv-toolbar">
                <div class="nv-segmented" role="group" aria-label="Pane">
                    <button type="button" data-pane="preview">Preview</button>
                    <button type="button" data-pane="details">Details</button>
                </div>

                <div class="nv-spacer"></div>

                <select class="nv-select" id="nv-variation" hidden aria-label="Variation"></select>

                @if (count($locales) > 1)
                    <select class="nv-select" id="nv-locale" aria-label="Locale">
                        @foreach ($locales as $locale)
                            <option value="{{ $locale }}">{{ strtoupper($locale) }}</option>
                        @endforeach
                    </select>
                @endif

                <div class="nv-segmented nv-segmented-icons" role="group" aria-label="Viewport">
                    <button type="button" data-viewport="desktop" data-tooltip="Desktop" aria-label="Desktop">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8M12 17v4"/>
                        </svg>
                    </button>
                    <button type="button" data-viewport="tablet" data-tooltip="Tablet" aria-label="Tablet">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/>
                        </svg>
                    </button>
                    <button type="button" data-viewport="mobile" data-tooltip="Mobile" aria-label="Mobile">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="7" y="2" width="10" height="20" rx="2"/><path d="M12 18h.01"/>
                        </svg>
                    </button>
                </div>

                <button type="button" class="nv-button nv-button-primary" id="nv-send">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 3 3 10.5l7 3 3 7L21 3Z"/>
                    </svg>
                    Send Test
                </button>
            </div>

            <div class="nv-envelope" id="nv-envelope">
                <div class="nv-avatar" id="nv-initials"></div>
                <div>
                    <div class="nv-envelope-title" id="nv-label"></div>
                    <div class="nv-envelope-line" id="nv-subject"></div>
                    <div class="nv-envelope-line" id="nv-from"></div>
                </div>
            </div>

            <div class="nv-pane" id="nv-pane-preview">
                <div class="nv-segmented nv-format-floating" id="nv-format" role="group" aria-label="Body">
                    <button type="button" data-format="html">HTML</button>
                    <button type="button" data-format="text">Text</button>
                </div>

                <button type="button" class="nv-button nv-copy-floating" id="nv-copy" data-tooltip="Copy the rendered body">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/>
                    </svg>
                    <span>Copy HTML</span>
                </button>
                <div class="nv-frame-wrap" id="nv-frame-wrap">
                    <iframe id="nv-frame" sandbox="allow-same-origin" title="Notification preview"></iframe>
                </div>
            </div>

            <div class="nv-pane nv-details" id="nv-pane-details" hidden>
                @if (session('notification-viewer.status'))
                    <div class="nv-status">{{ session('notification-viewer.status') }}</div>
                @endif
                <div id="nv-details-body"></div>
            </div>
        </main>
    </div>

    <div id="nv-tooltip" class="nv-tooltip" popover="manual" role="tooltip"></div>

    <dialog id="nv-send-dialog">
        <form method="POST" action="{{ route('notification-viewer.send') }}" id="nv-send-form">
            @csrf
            <h2>Send test mail</h2>
            <input type="email" name="email" required placeholder="you@example.com" value="{{ $testEmail }}">
            <input type="hidden" name="class" id="nv-send-class">
            <input type="hidden" name="variation" id="nv-send-variation">
            <input type="hidden" name="locale" id="nv-send-locale">
            <div id="nv-send-values"></div>
            <div class="nv-dialog-actions">
                <button type="button" class="nv-button" id="nv-send-cancel">Cancel</button>
                <button type="submit" class="nv-button nv-button-primary">Send</button>
            </div>
        </form>
    </dialog>

    <script>
        (function () {
            const ENTRIES = @json($entries);
            const PREVIEW_URL = @json(route('notification-viewer.preview'));
            const VIEWPORTS = { desktop: '100%', tablet: '640px', mobile: '390px' };

            const KINDS = {
                notification: {
                    label: 'Notification',
                    icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>',
                },
                mailable: {
                    label: 'Mailable',
                    icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="m2 7 9.2 5.6a1.5 1.5 0 0 0 1.6 0L22 7"/></svg>',
                },
            };

            const state = {
                selected: ENTRIES[0] || null,
                variation: null,
                pane: 'preview',
                format: 'html',
                viewport: 'desktop',
                search: '',
                kind: 'all',
                values: {},
            };

            const el = (id) => document.getElementById(id);
            const frame = el('nv-frame');
            const localeSelect = el('nv-locale');
            const variationSelect = el('nv-variation');

            const locale = () => (localeSelect ? localeSelect.value : '');

            function previewUrl() {
                if (!state.selected) return 'about:blank';

                const url = new URL(PREVIEW_URL, window.location.origin);
                url.searchParams.set('class', state.selected.class);
                if (state.variation) url.searchParams.set('variation', state.variation);
                if (state.format === 'text') url.searchParams.set('format', 'text');
                if (locale()) url.searchParams.set('locale', locale());
                Object.entries(state.values).forEach(([name, value]) => {
                    if (value !== null && value !== undefined) {
                        url.searchParams.set(`values[${name}]`, String(value));
                    }
                });

                return url.toString();
            }

            function initials(label) {
                return (label.match(/\p{L}+/gu) || []).slice(0, 2).map((w) => w[0].toUpperCase()).join('');
            }

            function matches(item, term) {
                return [item.label, item.class, item.path, item.subject, item.kind]
                    .filter(Boolean)
                    .some((value) => value.toLowerCase().includes(term));
            }

            function renderList() {
                const term = state.search.trim().toLowerCase();
                const visible = ENTRIES
                    .filter((item) => state.kind === 'all' || item.kind === state.kind)
                    .filter((item) => !term || matches(item, term));
                const list = el('nv-list');
                list.innerHTML = '';

                if (!visible.length) {
                    list.innerHTML = '<p class="nv-empty">Nothing found.</p>';
                    return;
                }

                const grouped = visible.some((item) => item.group);
                let currentGroup;

                visible.forEach((item, index) => {
                    const group = item.group || 'Allgemein';

                    if (grouped && (index === 0 || group !== currentGroup)) {
                        currentGroup = group;
                        const heading = document.createElement('p');
                        heading.className = 'nv-group-label';
                        heading.textContent = group;
                        list.appendChild(heading);
                    }

                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'nv-item';
                    button.setAttribute('aria-current', String(state.selected?.class === item.class));

                    const title = document.createElement('div');
                    title.className = 'nv-item-title';

                    const label = document.createElement('span');
                    label.textContent = item.label;
                    title.appendChild(label);

                    const kind = KINDS[item.kind];

                    if (kind) {
                        const badge = document.createElement('span');
                        badge.className = 'nv-kind';
                        badge.dataset.tooltip = kind.label;
                        badge.setAttribute('aria-label', kind.label);
                        badge.innerHTML = kind.icon;
                        title.appendChild(badge);
                    }

                    const subject = document.createElement('div');
                    subject.className = 'nv-item-subject';
                    subject.textContent = item.error ? 'Failed to render' : (item.subject || '—');
                    subject.dataset.error = String(Boolean(item.error));

                    const path = document.createElement('div');
                    path.className = 'nv-item-path';
                    path.textContent = item.path;
                    path.dataset.tooltip = item.path;

                    button.append(title, subject, path);
                    button.addEventListener('click', () => select(item));
                    list.appendChild(button);
                });
            }

            function renderVariations() {
                const variations = state.selected?.variations || [];
                variationSelect.hidden = variations.length < 2;
                variationSelect.innerHTML = '';
                variations.forEach((name) => {
                    const option = document.createElement('option');
                    option.value = name;
                    option.textContent = name;
                    option.selected = name === state.variation;
                    variationSelect.appendChild(option);
                });
            }

            function renderEnvelope() {
                const item = state.selected;
                el('nv-initials').textContent = item ? initials(item.label) : '';
                el('nv-label').textContent = item ? item.label : '';
                el('nv-subject').textContent = item ? (item.subject || '—') : '';
                el('nv-from').textContent = item && item.from ? `From: ${item.from}` : '';
            }

            function row(label, value) {
                const tr = document.createElement('tr');
                const th = document.createElement('th');
                th.textContent = label;
                const td = document.createElement('td');

                if (value instanceof Node) {
                    td.appendChild(value);
                } else {
                    td.textContent = value === null || value === '' ? '—' : value;
                }

                tr.append(th, td);
                return tr;
            }

            function paramInput(param) {
                let input;

                if (param.input === 'select') {
                    input = document.createElement('select');
                    param.options.forEach((option) => {
                        const node = document.createElement('option');
                        node.value = option.value;
                        node.textContent = option.label;
                        input.appendChild(node);
                    });
                    input.value = state.values[param.name] ?? param.value;
                } else if (param.input === 'checkbox') {
                    input = document.createElement('input');
                    input.type = 'checkbox';
                    input.checked = (state.values[param.name] ?? param.value) === 'true';
                } else {
                    input = document.createElement('input');
                    input.type = param.input;
                    input.value = state.values[param.name] ?? param.value ?? '';
                }

                let timer;
                input.addEventListener('input', () => {
                    state.values[param.name] = input.type === 'checkbox' ? String(input.checked) : input.value;
                    clearTimeout(timer);
                    timer = setTimeout(refreshPreview, 300);
                });

                return input;
            }

            function renderDetails() {
                const body = el('nv-details-body');
                body.innerHTML = '';
                const item = state.selected;
                if (!item) return;

                if (item.error) {
                    const banner = document.createElement('div');
                    banner.className = 'nv-error-banner';
                    banner.textContent = item.error;
                    body.appendChild(banner);
                }

                if (item.params.length) {
                    body.append(paramsHeading(), paramsTable(item));
                }

                const meta = document.createElement('table');
                meta.className = 'nv-table';
                meta.append(
                    row('Class', item.class),
                    row('Kind', KINDS[item.kind]?.label ?? item.kind),
                    row('File', item.path),
                    row('Subject', item.subject),
                    row('From', item.from),
                    row('Template', item.view),
                    row('Channels', (item.channels || []).join(', ')),
                    row('Queued', item.queued ? 'yes' : 'no'),
                    row('Variations', (item.variations || []).join(', ')),
                );

                const metaHeading = document.createElement('h2');
                metaHeading.textContent = KINDS[item.kind]?.label ?? 'Details';
                body.append(metaHeading, meta);
            }

            function paramsHeading() {
                const heading = document.createElement('h2');
                heading.textContent = 'Constructor parameters';

                return heading;
            }

            function paramsTable(item) {
                const params = document.createElement('table');
                params.className = 'nv-table';

                item.params.forEach((param) => {
                    const label = document.createElement('span');
                    label.textContent = `${param.name} `;
                    const badge = document.createElement('span');
                    badge.className = 'nv-badge';
                    badge.textContent = param.type;
                    label.appendChild(badge);

                    const tr = document.createElement('tr');
                    const th = document.createElement('th');
                    th.appendChild(label);
                    const td = document.createElement('td');

                    if (param.editable) {
                        td.appendChild(paramInput(param));
                    } else {
                        td.className = 'nv-readonly';
                        td.textContent = param.preview ?? '—';
                    }

                    tr.append(th, td);
                    params.appendChild(tr);
                });

                return params;
            }

            function refreshPreview() {
                frame.src = previewUrl();
            }

            /*
             * Mail templates usually paint their background on an inner wrapper
             * rather than on <body>, so the frame would show white below the
             * message. Copy that colour onto the frame and the pane instead.
             */
            function matchPreviewBackground() {
                const pane = el('nv-pane-preview');
                let color = '';

                try {
                    const doc = frame.contentDocument;
                    // The wrapper element carries the page colour; <body> is usually plain white.
                    const candidates = [...doc.body.children, doc.body].filter(Boolean);

                    for (const node of candidates) {
                        const background = getComputedStyle(node).backgroundColor;

                        if (background && !/^(transparent|rgba\(0, 0, 0, 0\))$/.test(background)) {
                            color = background;
                            break;
                        }
                    }

                    // An opaque <body> paints the whole frame, hiding the colour we just picked.
                    doc.documentElement.style.background = 'transparent';
                    doc.body.style.background = 'transparent';

                    /*
                     * Plain text starts hard against the top-left corner, where the
                     * floating controls sit. HTML mails keep their own exact layout.
                     */
                    doc.body.style.padding = state.format === 'text' ? '54px 16px 16px' : '';
                } catch (error) {
                    color = '';
                }

                frame.style.background = color || 'var(--nv-surface)';
                pane.style.background = color || 'var(--nv-muted)';
            }

            frame.addEventListener('load', matchPreviewBackground);

            function renderPane() {
                document.querySelectorAll('[data-pane]').forEach((button) => {
                    button.setAttribute('aria-pressed', String(button.dataset.pane === state.pane));
                });
                el('nv-pane-preview').hidden = state.pane !== 'preview';
                el('nv-pane-details').hidden = state.pane !== 'details';

                document.querySelectorAll('[data-format]').forEach((button) => {
                    button.setAttribute('aria-pressed', String(button.dataset.format === state.format));
                });
                el('nv-copy').querySelector('span').textContent =
                    state.format === 'text' ? 'Copy text' : 'Copy HTML';

                document.querySelectorAll('[data-viewport]').forEach((button) => {
                    button.setAttribute('aria-pressed', String(button.dataset.viewport === state.viewport));
                });
                el('nv-frame-wrap').style.maxWidth = VIEWPORTS[state.viewport];
            }

            function select(item) {
                state.selected = item;
                state.variation = item.variations[0] || null;
                state.values = {};
                renderList();
                renderVariations();
                renderEnvelope();
                renderDetails();
                refreshPreview();
            }

            el('nv-search').addEventListener('input', (event) => {
                state.search = event.target.value;
                renderList();
            });

            // The filter only earns its space when both kinds are actually present.
            el('nv-kind-filter').hidden = new Set(ENTRIES.map((item) => item.kind)).size < 2;

            document.querySelectorAll('[data-kind]').forEach((button) => {
                button.addEventListener('click', () => {
                    state.kind = button.dataset.kind;
                    document.querySelectorAll('[data-kind]').forEach((other) => {
                        other.setAttribute('aria-pressed', String(other.dataset.kind === state.kind));
                    });
                    renderList();
                });
            });

            document.querySelector('[data-kind="all"]').setAttribute('aria-pressed', 'true');

            variationSelect.addEventListener('change', () => {
                state.variation = variationSelect.value;
                refreshPreview();
            });

            localeSelect?.addEventListener('change', refreshPreview);

            document.querySelectorAll('[data-pane]').forEach((button) => {
                button.addEventListener('click', () => {
                    state.pane = button.dataset.pane;
                    renderPane();
                });
            });

            document.querySelectorAll('[data-viewport]').forEach((button) => {
                button.addEventListener('click', () => {
                    state.viewport = button.dataset.viewport;
                    renderPane();
                });
            });

            document.querySelectorAll('[data-format]').forEach((button) => {
                button.addEventListener('click', () => {
                    state.format = button.dataset.format;
                    renderPane();
                    refreshPreview();
                });
            });

            el('nv-copy').addEventListener('click', async () => {
                const button = el('nv-copy').querySelector('span');
                const original = button.textContent;

                try {
                    const response = await fetch(previewUrl());
                    await navigator.clipboard.writeText(await response.text());
                    button.textContent = 'Copied!';
                } catch (error) {
                    button.textContent = 'Failed';
                }

                setTimeout(() => { button.textContent = original; }, 1500);
            });

            /*
             * A single popover reused for every trigger. The top layer keeps it
             * clear of the sidebar's overflow, which would clip a CSS tooltip.
             */
            const tooltip = el('nv-tooltip');
            const canPopover = typeof tooltip.showPopover === 'function';

            function showTooltip(target) {
                if (!canPopover) return;

                tooltip.textContent = target.dataset.tooltip;
                tooltip.showPopover();

                const anchor = target.getBoundingClientRect();
                const self = tooltip.getBoundingClientRect();
                const left = anchor.left + anchor.width / 2 - self.width / 2;
                const top = anchor.top - self.height - 8;

                tooltip.style.left = `${Math.max(8, Math.min(left, window.innerWidth - self.width - 8))}px`;
                tooltip.style.top = `${top < 8 ? anchor.bottom + 8 : top}px`;
            }

            function hideTooltip() {
                if (canPopover && tooltip.matches(':popover-open')) tooltip.hidePopover();
            }

            const triggerFor = (event) => event.target?.closest?.('[data-tooltip]');

            document.addEventListener('mouseover', (event) => {
                const trigger = triggerFor(event);
                if (trigger) showTooltip(trigger);
            });

            document.addEventListener('focusin', (event) => {
                const trigger = triggerFor(event);
                if (trigger) showTooltip(trigger);
            });

            document.addEventListener('mouseout', (event) => triggerFor(event) && hideTooltip());
            document.addEventListener('focusout', (event) => triggerFor(event) && hideTooltip());
            document.addEventListener('scroll', hideTooltip, true);

            const dialog = el('nv-send-dialog');

            el('nv-send').addEventListener('click', () => {
                if (!state.selected) return;

                el('nv-send-class').value = state.selected.class;
                el('nv-send-variation').value = state.variation || '';
                el('nv-send-locale').value = locale();

                const values = el('nv-send-values');
                values.innerHTML = '';
                Object.entries(state.values).forEach(([name, value]) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = `values[${name}]`;
                    input.value = value;
                    values.appendChild(input);
                });

                dialog.showModal();
            });

            el('nv-send-cancel').addEventListener('click', () => dialog.close());

            if (state.selected) {
                select(state.selected);
            } else {
                el('nv-list').innerHTML = '<p class="nv-empty">Nothing discovered.</p>';
            }

            renderPane();
        })();
    </script>
@endsection
