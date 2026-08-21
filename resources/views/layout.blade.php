<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>Notification Preview &ndash; {{ config('app.name') }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }

        :root {
            --np-bg: #f1f1f1;
            --np-surface: #ffffff;
            --np-muted: #fafafa;
            --np-border: #e4e4e7;
            --np-border-strong: #d4d4d8;
            --np-text: #18181b;
            --np-text-soft: #52525b;
            --np-text-faint: #a1a1aa;
            --np-accent: #4f46e5;
            --np-accent-soft: #eef2ff;
            --np-danger: #dc2626;
            --np-radius: 10px;
            --np-control: 38px;
            --np-font: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            --np-mono: ui-monospace, SFMono-Regular, "SF Mono", Menlo, monospace;
        }

        html { height: 100%; }

        body {
            display: flex;
            flex-direction: column;
            height: 100%;
            margin: 0;
            padding: 16px;
            overflow: hidden;
            background: var(--np-bg);
            color: var(--np-text);
            font-family: var(--np-font);
            font-size: 14px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        .np-shell {
            display: grid;
            grid-template-columns: 320px minmax(0, 1fr);
            flex: 1;
            min-height: 0;
            background: var(--np-surface);
            border: 1px solid var(--np-border);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 1px 2px rgb(0 0 0 / 0.04);
        }

        /* ---------- sidebar ---------- */

        .np-sidebar {
            display: flex;
            flex-direction: column;
            min-height: 0;
            border-right: 1px solid var(--np-border);
            background: var(--np-surface);
        }

        .np-sidebar-header {
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding: 16px;
            border-bottom: 1px solid var(--np-border);
        }

        .np-search {
            position: relative;
        }

        .np-search svg {
            position: absolute;
            top: 50%;
            left: 12px;
            width: 15px;
            height: 15px;
            transform: translateY(-50%);
            color: var(--np-text-faint);
            pointer-events: none;
        }

        .np-search input {
            width: 100%;
            padding: 9px 12px 9px 34px;
            border: 1px solid var(--np-border-strong);
            border-radius: var(--np-radius);
            font: inherit;
            color: inherit;
            background: var(--np-surface);
        }

        .np-list {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            padding: 12px 14px 12px 12px;
            scrollbar-gutter: stable;
        }

        .np-group-label {
            padding: 12px 6px 6px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--np-text-faint);
        }

        .np-item {
            display: block;
            width: 100%;
            margin-bottom: 8px;
            padding: 12px 14px;
            border: 1px solid var(--np-border);
            border-radius: var(--np-radius);
            background: var(--np-surface);
            font: inherit;
            text-align: left;
            cursor: pointer;
            transition: border-color 0.12s, background 0.12s;
        }

        .np-item:hover { background: var(--np-muted); }

        .np-item[aria-current="true"] {
            border-color: var(--np-accent);
            background: var(--np-accent-soft);
        }

        .np-item-title {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
            font-weight: 600;
            color: var(--np-text);
        }

        .np-kind {
            display: grid;
            place-items: center;
            flex-shrink: 0;
            width: 22px;
            height: 22px;
            border: 1px solid var(--np-border-strong);
            border-radius: 6px;
            background: var(--np-muted);
            color: var(--np-text-soft);
        }

        .np-kind svg { width: 13px; height: 13px; }

        .np-item-subject {
            margin-top: 2px;
            color: var(--np-text-soft);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .np-item-subject[data-error="true"] { color: var(--np-danger); }

        .np-item-path {
            margin-top: 4px;
            font-family: var(--np-mono);
            font-size: 12px;
            color: var(--np-text-faint);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            direction: rtl;
            text-align: left;
        }

        .np-empty {
            padding: 24px 8px;
            color: var(--np-text-faint);
            text-align: center;
        }

        /* ---------- main ---------- */

        .np-main {
            display: flex;
            flex-direction: column;
            min-width: 0;
            min-height: 0;
        }

        .np-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            padding: 12px 16px;
            border-bottom: 1px solid var(--np-border);
        }

        .np-toolbar .np-spacer { flex: 1; }

        .np-segmented {
            display: inline-flex;
            height: var(--np-control);
            padding: 3px;
            border: 1px solid var(--np-border);
            border-radius: var(--np-radius);
            background: var(--np-muted);
        }

        .np-segmented button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            height: 100%;
            padding: 0 12px;
            border: 0;
            border-radius: 7px;
            background: transparent;
            font: inherit;
            color: var(--np-text-soft);
            cursor: pointer;
        }

        .np-segmented button[aria-pressed="true"] {
            background: var(--np-surface);
            color: var(--np-text);
            box-shadow: 0 1px 2px rgb(0 0 0 / 0.08);
        }

        .np-segmented svg { width: 15px; height: 15px; }

        .np-segmented-icons button { width: 38px; padding: 0; }

        .np-kind-filter { width: 100%; }

        .np-kind-filter button {
            flex: 1;
            padding: 0 4px;
            font-size: 12px;
        }

        .np-button {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            height: var(--np-control);
            padding: 0 14px;
            border: 1px solid var(--np-border-strong);
            border-radius: var(--np-radius);
            background: var(--np-surface);
            font: inherit;
            color: var(--np-text);
            cursor: pointer;
        }

        .np-button:hover { background: var(--np-muted); }

        .np-button-primary {
            border-color: var(--np-accent);
            background: var(--np-accent);
            color: #fff;
        }

        .np-button-primary:hover { background: #4338ca; }

        .np-button svg { width: 15px; height: 15px; }

        select.np-select,
        .np-table select {
            appearance: none;
            padding-right: 32px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2352525b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 14px;
        }

        select.np-select {
            max-width: 220px;
            height: var(--np-control);
            padding: 0 32px 0 14px;
            border: 1px solid var(--np-border-strong);
            border-radius: var(--np-radius);
            background-color: var(--np-surface);
            font: inherit;
            color: var(--np-text);
            text-overflow: ellipsis;
            cursor: pointer;
        }

        select.np-select:hover { background-color: var(--np-muted); }

        .np-envelope {
            display: flex;
            gap: 14px;
            padding: 16px;
            border-bottom: 1px solid var(--np-border);
        }

        .np-avatar {
            display: grid;
            place-items: center;
            flex-shrink: 0;
            width: 46px;
            height: 46px;
            border-radius: 12px;
            background: linear-gradient(135deg, #e11d63, #be185d);
            color: #fff;
            font-weight: 700;
            letter-spacing: 0.03em;
        }

        .np-envelope-title { font-weight: 600; }
        .np-envelope-line { color: var(--np-text-soft); }

        .np-pane {
            flex: 1;
            min-height: 0;
            overflow: auto;
            background: var(--np-muted);
        }

        #np-pane-preview { position: relative; overflow: hidden; }

        .np-format-floating,
        .np-copy-floating {
            position: absolute;
            top: 12px;
            z-index: 1;
            height: 30px;
            background: rgb(255 255 255 / 0.9);
            backdrop-filter: blur(6px);
            box-shadow: 0 1px 3px rgb(0 0 0 / 0.1);
        }

        .np-format-floating {
            left: 12px;
            padding: 3px;
        }

        .np-format-floating button {
            padding: 0 10px;
            font-size: 13px;
        }

        .np-copy-floating {
            right: 12px;
            padding: 0 11px;
        }

        .np-frame-wrap {
            height: 100%;
            margin: 0 auto;
            transition: max-width 0.15s ease;
        }

        .np-frame-wrap iframe {
            display: block;
            width: 100%;
            height: 100%;
            border: 0;
        }

        /* ---------- details ---------- */

        .np-details { padding: 20px; background: var(--np-surface); }

        .np-details h2 {
            margin: 24px 0 10px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--np-text-faint);
        }

        .np-details h2:first-child { margin-top: 0; }

        .np-table {
            width: 100%;
            border: 1px solid var(--np-border);
            border-radius: var(--np-radius);
            border-collapse: separate;
            border-spacing: 0;
            overflow: hidden;
        }

        .np-table th,
        .np-table td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--np-border);
            text-align: left;
            vertical-align: top;
        }

        .np-table tr:last-child th,
        .np-table tr:last-child td { border-bottom: 0; }

        .np-table th {
            width: 190px;
            font-weight: 500;
            color: var(--np-text-soft);
            white-space: nowrap;
        }

        .np-table td { font-family: var(--np-mono); font-size: 13px; word-break: break-word; }

        .np-table input[type="text"],
        .np-table input[type="number"],
        .np-table input[type="datetime-local"],
        .np-table select {
            width: 100%;
            padding: 6px 9px;
            border: 1px solid var(--np-border-strong);
            border-radius: 7px;
            font: inherit;
            color: inherit;
            background: var(--np-surface);
        }

        .np-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            background: var(--np-accent-soft);
            color: var(--np-accent);
            font-family: var(--np-font);
            font-size: 12px;
            font-weight: 500;
        }

        .np-readonly { color: var(--np-text-faint); }

        .np-error-banner {
            margin-bottom: 16px;
            padding: 12px 14px;
            border: 1px solid #fecaca;
            border-radius: var(--np-radius);
            background: #fef2f2;
            color: #991b1b;
            font-family: var(--np-mono);
            font-size: 13px;
        }

        .np-status {
            margin-bottom: 16px;
            padding: 10px 14px;
            border: 1px solid #bbf7d0;
            border-radius: var(--np-radius);
            background: #f0fdf4;
            color: #166534;
        }

        .np-tooltip {
            position: fixed;
            inset: auto;
            width: max-content;
            max-width: 320px;
            margin: 0;
            padding: 5px 9px;
            border: 0;
            border-radius: 6px;
            background: var(--np-text);
            color: #fff;
            font-family: var(--np-font);
            font-size: 12px;
            line-height: 1.4;
            /* File paths have no natural break opportunities. */
            overflow-wrap: anywhere;
            pointer-events: none;
        }

        dialog {
            border: 1px solid var(--np-border);
            border-radius: 14px;
            padding: 20px;
            width: min(380px, 90vw);
            box-shadow: 0 20px 40px rgb(0 0 0 / 0.15);
        }

        dialog::backdrop { background: rgb(0 0 0 / 0.35); }

        dialog h2 { margin: 0 0 12px; font-size: 16px; }

        dialog input[type="email"] {
            width: 100%;
            margin-bottom: 16px;
            padding: 9px 12px;
            border: 1px solid var(--np-border-strong);
            border-radius: var(--np-radius);
            font: inherit;
        }

        .np-dialog-actions { display: flex; justify-content: flex-end; gap: 8px; }

        [hidden] { display: none !important; }
    </style>
</head>
<body>
    @yield('content')
</body>
</html>
