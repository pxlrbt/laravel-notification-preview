<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>Notification Viewer &ndash; {{ config('app.name') }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }

        :root {
            --nv-bg: #f1f1f1;
            --nv-surface: #ffffff;
            --nv-muted: #fafafa;
            --nv-border: #e4e4e7;
            --nv-border-strong: #d4d4d8;
            --nv-text: #18181b;
            --nv-text-soft: #52525b;
            --nv-text-faint: #a1a1aa;
            --nv-accent: #4f46e5;
            --nv-accent-soft: #eef2ff;
            --nv-danger: #dc2626;
            --nv-radius: 10px;
            --nv-font: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            --nv-mono: ui-monospace, SFMono-Regular, "SF Mono", Menlo, monospace;
        }

        body {
            margin: 0;
            padding: 24px;
            min-height: 100vh;
            background: var(--nv-bg);
            color: var(--nv-text);
            font-family: var(--nv-font);
            font-size: 14px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        h1 {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0 0 20px;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        h1 svg { width: 26px; height: 26px; }

        .nv-shell {
            display: grid;
            grid-template-columns: 320px minmax(0, 1fr);
            height: calc(100vh - 100px);
            background: var(--nv-surface);
            border: 1px solid var(--nv-border);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 1px 2px rgb(0 0 0 / 0.04);
        }

        /* ---------- sidebar ---------- */

        .nv-sidebar {
            display: flex;
            flex-direction: column;
            min-height: 0;
            border-right: 1px solid var(--nv-border);
            background: var(--nv-surface);
        }

        .nv-search {
            position: relative;
            padding: 16px;
            border-bottom: 1px solid var(--nv-border);
        }

        .nv-search svg {
            position: absolute;
            top: 50%;
            left: 28px;
            width: 15px;
            height: 15px;
            transform: translateY(-50%);
            color: var(--nv-text-faint);
            pointer-events: none;
        }

        .nv-search input {
            width: 100%;
            padding: 9px 12px 9px 34px;
            border: 1px solid var(--nv-border-strong);
            border-radius: var(--nv-radius);
            font: inherit;
            color: inherit;
            background: var(--nv-surface);
        }

        .nv-list {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            padding: 12px;
        }

        .nv-group-label {
            padding: 12px 6px 6px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--nv-text-faint);
        }

        .nv-item {
            display: block;
            width: 100%;
            margin-bottom: 8px;
            padding: 12px 14px;
            border: 1px solid var(--nv-border);
            border-radius: var(--nv-radius);
            background: var(--nv-surface);
            font: inherit;
            text-align: left;
            cursor: pointer;
            transition: border-color 0.12s, background 0.12s;
        }

        .nv-item:hover { background: var(--nv-muted); }

        .nv-item[aria-current="true"] {
            border-color: var(--nv-accent);
            background: var(--nv-accent-soft);
        }

        .nv-item-title {
            font-weight: 600;
            color: var(--nv-text);
        }

        .nv-item-subject {
            margin-top: 2px;
            color: var(--nv-text-soft);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .nv-item-subject[data-error="true"] { color: var(--nv-danger); }

        .nv-item-path {
            margin-top: 4px;
            font-family: var(--nv-mono);
            font-size: 12px;
            color: var(--nv-text-faint);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            direction: rtl;
            text-align: left;
        }

        .nv-empty {
            padding: 24px 8px;
            color: var(--nv-text-faint);
            text-align: center;
        }

        /* ---------- main ---------- */

        .nv-main {
            display: flex;
            flex-direction: column;
            min-width: 0;
            min-height: 0;
        }

        .nv-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            padding: 12px 16px;
            border-bottom: 1px solid var(--nv-border);
        }

        .nv-toolbar .nv-spacer { flex: 1; }

        .nv-segmented {
            display: inline-flex;
            padding: 3px;
            border: 1px solid var(--nv-border);
            border-radius: var(--nv-radius);
            background: var(--nv-muted);
        }

        .nv-segmented button {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border: 0;
            border-radius: 7px;
            background: transparent;
            font: inherit;
            color: var(--nv-text-soft);
            cursor: pointer;
        }

        .nv-segmented button[aria-pressed="true"] {
            background: var(--nv-surface);
            color: var(--nv-text);
            box-shadow: 0 1px 2px rgb(0 0 0 / 0.08);
        }

        .nv-segmented svg { width: 15px; height: 15px; }

        .nv-button {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border: 1px solid var(--nv-border-strong);
            border-radius: var(--nv-radius);
            background: var(--nv-surface);
            font: inherit;
            color: var(--nv-text);
            cursor: pointer;
        }

        .nv-button:hover { background: var(--nv-muted); }

        .nv-button-primary {
            border-color: var(--nv-accent);
            background: var(--nv-accent);
            color: #fff;
        }

        .nv-button-primary:hover { background: #4338ca; }

        .nv-button svg { width: 15px; height: 15px; }

        select.nv-select {
            padding: 8px 10px;
            border: 1px solid var(--nv-border-strong);
            border-radius: var(--nv-radius);
            background: var(--nv-surface);
            font: inherit;
            color: inherit;
            cursor: pointer;
        }

        .nv-envelope {
            display: flex;
            gap: 14px;
            padding: 16px;
            border-bottom: 1px solid var(--nv-border);
        }

        .nv-avatar {
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

        .nv-envelope-title { font-weight: 600; }
        .nv-envelope-line { color: var(--nv-text-soft); }

        .nv-pane {
            flex: 1;
            min-height: 0;
            overflow: auto;
            background: var(--nv-muted);
        }

        .nv-frame-wrap {
            height: 100%;
            margin: 0 auto;
            transition: max-width 0.15s ease;
        }

        .nv-frame-wrap iframe {
            width: 100%;
            height: 100%;
            border: 0;
            background: var(--nv-surface);
        }

        /* ---------- details ---------- */

        .nv-details { padding: 20px; background: var(--nv-surface); }

        .nv-details h2 {
            margin: 24px 0 10px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--nv-text-faint);
        }

        .nv-details h2:first-child { margin-top: 0; }

        .nv-table {
            width: 100%;
            border: 1px solid var(--nv-border);
            border-radius: var(--nv-radius);
            border-collapse: separate;
            border-spacing: 0;
            overflow: hidden;
        }

        .nv-table th,
        .nv-table td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--nv-border);
            text-align: left;
            vertical-align: top;
        }

        .nv-table tr:last-child th,
        .nv-table tr:last-child td { border-bottom: 0; }

        .nv-table th {
            width: 190px;
            font-weight: 500;
            color: var(--nv-text-soft);
            white-space: nowrap;
        }

        .nv-table td { font-family: var(--nv-mono); font-size: 13px; word-break: break-word; }

        .nv-table input[type="text"],
        .nv-table input[type="number"],
        .nv-table input[type="datetime-local"],
        .nv-table select {
            width: 100%;
            padding: 6px 9px;
            border: 1px solid var(--nv-border-strong);
            border-radius: 7px;
            font: inherit;
            color: inherit;
            background: var(--nv-surface);
        }

        .nv-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            background: var(--nv-accent-soft);
            color: var(--nv-accent);
            font-family: var(--nv-font);
            font-size: 12px;
            font-weight: 500;
        }

        .nv-readonly { color: var(--nv-text-faint); }

        .nv-error-banner {
            margin-bottom: 16px;
            padding: 12px 14px;
            border: 1px solid #fecaca;
            border-radius: var(--nv-radius);
            background: #fef2f2;
            color: #991b1b;
            font-family: var(--nv-mono);
            font-size: 13px;
        }

        .nv-status {
            margin-bottom: 16px;
            padding: 10px 14px;
            border: 1px solid #bbf7d0;
            border-radius: var(--nv-radius);
            background: #f0fdf4;
            color: #166534;
        }

        dialog {
            border: 1px solid var(--nv-border);
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
            border: 1px solid var(--nv-border-strong);
            border-radius: var(--nv-radius);
            font: inherit;
        }

        .nv-dialog-actions { display: flex; justify-content: flex-end; gap: 8px; }

        [hidden] { display: none !important; }
    </style>
</head>
<body>
    @yield('content')
</body>
</html>
