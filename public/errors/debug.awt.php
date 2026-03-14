<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $WEB_NAME }} — Runtime Exception</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:        #111318;
            --surface:   #16191f;
            --raised:    #1c2028;
            --border:    #2a2f3d;
            --border-hi: #383f52;
            --red:       #e05252;
            --red-soft:  rgba(224, 82, 82, 0.12);
            --amber:     #d4913a;
            --amber-soft:rgba(212, 145, 58, 0.12);
            --blue:      #5b8dee;
            --green:     #45c78a;
            --text:      #bec6d8;
            --muted:     #4e576e;
            --faint:     #2e3446;
            --mono:      'IBM Plex Mono', monospace;
            --sans:      'IBM Plex Sans', sans-serif;
        }

        html, body { height: 100%; }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: var(--mono);
            font-size: 12.5px;
            line-height: 1.65;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* ── HEADER ── */
        .header {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 0 24px;
            height: 44px;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
        }
        .header-sig {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .sig-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--red);
        }
        .sig-label {
            font-family: var(--mono);
            font-size: 11px;
            font-weight: 500;
            letter-spacing: 0.06em;
            color: var(--red);
            text-transform: uppercase;
        }
        .header-sep {
            width: 1px;
            height: 18px;
            background: var(--border);
        }
        .header-path {
            font-size: 11.5px;
            color: var(--muted);
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .header-app {
            font-size: 11px;
            color: var(--muted);
            letter-spacing: 0.04em;
        }

        /* ── EXCEPTION BANNER ── */
        .exception-banner {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
        }
        .exception-inner {
            padding: 18px 28px 20px;
        }
        .exception-kind {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }
        .exception-label {
            font-size: 10.5px;
            font-weight: 500;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--red);
        }
        .exception-toggle-btn {
            margin-left: auto;
            font-size: 10.5px;
            color: var(--muted);
            background: none;
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 2px 9px;
            cursor: pointer;
            font-family: var(--mono);
            transition: border-color 0.15s, color 0.15s;
        }
        .exception-toggle-btn:hover { border-color: var(--border-hi); color: var(--text); }
        .exception-message {
            font-family: var(--sans);
            font-size: 18px;
            font-weight: 500;
            color: #e8eaf0;
            line-height: 1.4;
            overflow: hidden;
            transition: max-height 0.25s ease, opacity 0.2s;
            max-height: 200px;
            opacity: 1;
        }
        .exception-message.hidden { max-height: 0; opacity: 0; }

        /* ── LOCATION BAR ── */
        .location-bar {
            display: flex;
            align-items: center;
            gap: 0;
            padding: 0 28px;
            height: 36px;
            background: var(--raised);
            border-bottom: 1px solid var(--border);
            font-size: 11.5px;
            overflow: hidden;
        }
        .loc-chunk {
            display: flex;
            align-items: center;
            gap: 0;
            color: var(--muted);
            white-space: nowrap;
            overflow: hidden;
        }
        .loc-chunk:first-child { flex: 1; overflow: hidden; text-overflow: ellipsis; }
        .loc-sep { margin: 0 10px; color: var(--faint); }
        .loc-line-badge {
            font-weight: 500;
            background: var(--red-soft);
            color: var(--red);
            border: 1px solid rgba(224, 82, 82, 0.25);
            border-radius: 3px;
            padding: 0 7px;
            height: 20px;
            line-height: 20px;
            font-size: 11px;
        }
        .loc-file-text {
            color: var(--text);
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* ── MAIN LAYOUT ── */
        .layout {
            display: grid;
            grid-template-columns: 300px 1fr;
            flex: 1;
            overflow: hidden;
            min-height: 0;
        }

        /* ── FRAMES SIDEBAR ── */
        .frames-panel {
            background: var(--surface);
            border-right: 1px solid var(--border);
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }
        .panel-cap {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
        }
        .panel-cap-label {
            font-size: 10px;
            font-weight: 500;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--muted);
        }
        .panel-cap-count {
            margin-left: auto;
            font-size: 10px;
            color: var(--faint);
            background: var(--raised);
            border: 1px solid var(--border);
            border-radius: 3px;
            padding: 0 5px;
            line-height: 16px;
        }

        .frame-list { list-style: none; }
        .frame-item {
            padding: 9px 16px;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: background 0.1s;
            position: relative;
        }
        .frame-item:hover { background: var(--raised); }
        .frame-item.active {
            background: var(--raised);
        }
        .frame-item.active::before {
            content: '';
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 2px;
            background: var(--blue);
        }
        .frame-idx { font-size: 10px; color: var(--faint); margin-bottom: 2px; }
        .frame-fn { color: var(--blue); font-size: 12px; }
        .frame-class { color: var(--text); font-size: 12px; }
        .frame-connector { color: var(--muted); }
        .frame-loc {
            font-size: 10.5px;
            color: var(--muted);
            margin-top: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .frame-loc-line { color: var(--amber); }

        /* ── DETAIL PANEL ── */
        .detail-panel {
            overflow-y: auto;
            background: var(--bg);
            display: flex;
            flex-direction: column;
        }

        /* ── SOURCE VIEW ── */
        .source-view {
            border-bottom: 1px solid var(--border);
        }
        .source-cap {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 16px;
            background: var(--raised);
            border-bottom: 1px solid var(--border);
        }
        .source-cap-label {
            font-size: 10px;
            font-weight: 500;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--muted);
        }
        .source-code { overflow-x: auto; }
        .code-row { display: flex; min-height: 21px; }
        .code-row.err-line { background: var(--red-soft); }
        .code-row.err-line .gutter { background: rgba(224,82,82,0.18); color: var(--red); }
        .gutter {
            width: 50px;
            flex-shrink: 0;
            text-align: right;
            padding: 1px 12px 1px 0;
            font-size: 11.5px;
            color: var(--muted);
            background: var(--surface);
            border-right: 1px solid var(--border);
            user-select: none;
        }
        .src { padding: 1px 16px; font-size: 12px; white-space: pre; color: var(--text); }
        .code-row.err-line .src { color: #f0f2f8; }
        .err-arrow { display: inline-block; margin-left: 10px; color: var(--red); font-size: 10.5px; }
        .no-src { padding: 14px 20px; color: var(--muted); font-size: 12px; }

        /* ── RUNTIME INFO ── */
        .runtime-section { padding: 20px 24px; }
        .rt-heading {
            font-size: 10px;
            font-weight: 500;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 12px;
        }
        .rt-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 8px;
            margin-bottom: 20px;
        }
        .rt-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 5px;
            padding: 9px 12px;
        }
        .rt-key { font-size: 10.5px; color: var(--muted); margin-bottom: 2px; }
        .rt-val { font-size: 12px; color: var(--green); word-break: break-all; }

        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }

        @media (max-width: 768px) {
            .layout { grid-template-columns: 1fr; }
            .frames-panel { border-right: none; border-bottom: 1px solid var(--border); max-height: 260px; }
        }
    </style>
</head>
<body>

@php
    function buildSnippet(string $filePath, int $focusLine, int $ctx = 7): array {
        if (!$filePath || !file_exists($filePath)) {
            return ['start' => 0, 'focus' => $focusLine, 'lines' => []];
        }
        $all   = file($filePath, FILE_IGNORE_NEW_LINES);
        $start = max(0, $focusLine - $ctx - 1);
        $slice = array_slice($all, $start, $ctx * 2 + 1);
        return [
            'start' => $start + 1,
            'focus' => $focusLine,
            'lines' => array_values($slice),
        ];
    }

    $frameList = [];
    $frameList[] = [
        'index'    => 0,
        'class'    => '',
        'type'     => '',
        'function' => '(exception origin)',
        'file'     => $file,
        'line'     => (int)$line,
        'snippet'  => buildSnippet($file, (int)$line),
    ];

    foreach ($trace as $i => $frame) {
        $f = $frame['file'] ?? '';
        $l = isset($frame['line']) ? (int)$frame['line'] : 0;
        $frameList[] = [
            'index'    => $i + 1,
            'class'    => $frame['class']    ?? '',
            'type'     => $frame['type']     ?? '',
            'function' => $frame['function'] ?? '{closure}',
            'file'     => $f,
            'line'     => $l,
            'snippet'  => buildSnippet($f, $l),
        ];
    }
@endphp

<header class="header">
    <div class="header-sig">
        <span class="sig-dot"></span>
        <span class="sig-label">Unhandled exception</span>
    </div>
    <div class="header-sep"></div>
    <span class="header-path">{{ $file }}:{{ $line }}</span>
    <span class="header-app">{{ $WEB_NAME }}</span>
</header>

<div class="exception-banner">
    <div class="exception-inner">
        <div class="exception-kind">
            <span class="exception-label">RuntimeException</span>
            <button class="exception-toggle-btn" id="msgToggle">collapse</button>
        </div>
        <div class="exception-message" id="msgBody">{{ $error }}</div>
    </div>
</div>

<div class="location-bar">
    <div class="loc-chunk">
        <span class="loc-file-text" id="barFile">{{ $file }}</span>
    </div>
    <span class="loc-sep">/</span>
    <div class="loc-chunk">
        line&nbsp;<span class="loc-line-badge" id="barLine">{{ $line }}</span>
    </div>
</div>

<div class="layout">
    <aside class="frames-panel">
        <div class="panel-cap">
            <span class="panel-cap-label">Call frames</span>
            <span class="panel-cap-count" id="frameCount">{{ count($frameList) }}</span>
        </div>
        <ul class="frame-list" id="frameList">
            @foreach($frameList as $idx => $f)
                <li class="frame-item {{ $idx === 0 ? 'active' : '' }}" data-frame="{{ $idx }}">
                    <div class="frame-idx">#{{ $f['index'] }}</div>
                    <div>
                        @if($f['class'])
                            <span class="frame-class">{{ $f['class'] }}</span><span class="frame-connector">{{ $f['type'] ?: '::' }}</span>
                        @endif
                        <span class="frame-fn">{{ $f['function'] }}</span>()
                    </div>
                    @if($f['file'])
                        <div class="frame-loc">
                            {{ basename($f['file']) }}@if($f['line'])<span class="frame-loc-line">:{{ $f['line'] }}</span>@endif
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    </aside>

    <main class="detail-panel">
        <div class="source-view">
            <div class="source-cap">
                <span class="source-cap-label">Source</span>
            </div>
            <div class="source-code" id="srcBlock"></div>
        </div>

        <div class="runtime-section">
            <div class="rt-heading">Environment</div>
            <div class="rt-grid">
                <div class="rt-card"><div class="rt-key">php</div><div class="rt-val">{{ PHP_VERSION }}</div></div>
                <div class="rt-card"><div class="rt-key">timestamp</div><div class="rt-val">{{ date('Y-m-d H:i:s') }}</div></div>
                <div class="rt-card"><div class="rt-key">app</div><div class="rt-val">{{ $WEB_NAME }}</div></div>
                <div class="rt-card"><div class="rt-key">signal</div><div class="rt-val">E_FATAL</div></div>
            </div>
            <div class="rt-heading">Context</div>
            <div class="rt-grid">
                <div class="rt-card"><div class="rt-key">package</div><div class="rt-val">{{ $context }}</div></div>
                <div class="rt-card"><div class="rt-key">path</div><div class="rt-val">{{ $contextPath }}</div></div>
                <div class="rt-card"><div class="rt-key">id</div><div class="rt-val">{{ $contextId }}</div></div>
            </div>
        </div>
    </main>
</div>

<script>
    const FRAMES = @json($frameList);

    let msgOpen = true;
    document.getElementById('msgToggle').addEventListener('click', () => {
        msgOpen = !msgOpen;
        document.getElementById('msgBody').classList.toggle('hidden', !msgOpen);
        document.getElementById('msgToggle').textContent = msgOpen ? 'collapse' : 'expand';
    });

    function esc(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function renderFrame(idx) {
        const f = FRAMES[idx];
        document.getElementById('barFile').textContent = f.file || '(no file)';
        document.getElementById('barLine').textContent = f.line || '—';

        const block = document.getElementById('srcBlock');
        const sn = f.snippet;

        if (!sn.lines || sn.lines.length === 0) {
            block.innerHTML = '<div class="no-src">Source unavailable.</div>';
            return;
        }

        let html = '';
        sn.lines.forEach((text, i) => {
            const n  = sn.start + i;
            const hi = n === sn.focus;
            const arrow = hi ? '<span class="err-arrow">← here</span>' : '';
            html += `<div class="${hi ? 'code-row err-line' : 'code-row'}">` +
                `<div class="gutter">${n}</div>` +
                `<div class="src">${esc(text)}${arrow}</div>` +
                `</div>`;
        });
        block.innerHTML = html;

        const hi = block.querySelector('.err-line');
        if (hi) hi.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }

    document.getElementById('frameList').addEventListener('click', e => {
        const item = e.target.closest('.frame-item');
        if (!item) return;
        document.querySelectorAll('.frame-item').forEach(el => el.classList.remove('active'));
        item.classList.add('active');
        renderFrame(parseInt(item.dataset.frame, 10));
    });

    renderFrame(0);
</script>
</body>
</html>