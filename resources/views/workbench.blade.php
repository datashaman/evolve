<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Evolve Workbench</title>
  <style>
    * { box-sizing: border-box; }
    html, body { height: 100%; margin: 0; }
    body { display: flex; flex-direction: column; font-family: system-ui, sans-serif; background: #18181b; }
    .toolbar { display: flex; gap: 8px; align-items: center; padding: 8px 12px; background: #18181b; color: #fafafa; }
    .toolbar h1 { font-size: 14px; font-weight: 650; margin: 0 12px 0 0; white-space: nowrap; }
    .kind { font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: #a1a1aa; padding: 2px 8px; border: 1px solid #3f3f46; border-radius: 999px; }
    button { font: inherit; }
    .toolbar button, .sidebar header button {
      font-size: 13px; font-weight: 700; min-height: 32px; padding: 0 12px; border: 1px solid #3f3f46; border-radius: 7px;
      background: linear-gradient(180deg, #303036, #242428); color: #fafafa; cursor: pointer;
    }
    .toolbar button:hover, .sidebar header button:hover { background: linear-gradient(180deg, #3a3a42, #2d2d33); }
    .spacer { flex: 1; }
    .workspace { flex: 1; display: flex; min-height: 0; }
    .workspace.resizing, .stage.resizing { cursor: col-resize; user-select: none; }
    .workspace.resizing iframe, .stage.resizing iframe, .editor-fields.resizing textarea { pointer-events: none; }
    .sidebar { width: var(--sidebar-width, 240px); flex: 0 0 auto; background: #27272a; color: #e4e4e7; overflow-y: auto; }
    .sidebar-resize, .stage-resize { flex: 0 0 9px; background: #18181b; cursor: col-resize; }
    .sidebar-resize:hover, .stage-resize:hover, .workspace.resizing .sidebar-resize, .stage.resizing .stage-resize { background: #24242a; }
    .sidebar section { display: flex; flex-direction: column; }
    .sidebar header { display: flex; align-items: center; justify-content: space-between; padding: 10px 12px; color: #a1a1aa; font-size: 12px; letter-spacing: .05em; text-transform: uppercase; }
    .sidebar header button { width: 24px; min-height: 24px; padding: 0; font-size: 16px; }
    .sidebar ul { list-style: none; margin: 0; padding: 0; }
    .sidebar li { display: flex; gap: 8px; align-items: center; padding: 8px 12px; font-size: 13px; cursor: pointer; }
    .sidebar li:hover { background: #3f3f46; }
    .sidebar li.active { background: #4338ca; color: #fff; }
    .sidebar .label { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .sidebar .meta { max-width: 96px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #a1a1aa; font-size: 11px; }
    .sidebar li.active .meta { color: #c7d2fe; }
    .empty { padding: 4px 12px 10px; color: #71717a; font-size: 12px; }
    .stage { flex: 1; display: grid; grid-template-columns: minmax(360px, var(--editor-width, 44%)) 9px minmax(320px, 1fr); min-width: 0; min-height: 0; overflow: hidden; }
    .panel { display: flex; min-width: 0; min-height: 0; flex-direction: column; background: #1e1e1e; }
    .editor-fields { flex: 1; display: flex; flex-direction: column; min-height: 0; overflow: hidden; }
    .editor-fields.resizing { cursor: row-resize; user-select: none; }
    .source-section { display: flex; flex-direction: column; min-height: 38px; }
    .source-section[hidden] { display: none; }
    .source-section[data-block="metadata"] { flex: 0 0 auto; }
    .source-section[data-block="php"] { flex: 1.2 1 0; }
    .source-section[data-block="blade"] { flex: 2 1 0; }
    .source-section[data-block="style"], .source-section[data-block="usage"] { flex: 1 1 0; }
    .source-section.collapsed { flex: 0 0 auto !important; }
    .source-section[data-block="usage"].collapsed { margin-top: auto; }
    .source-section.collapsed .metadata-grid, .source-section.collapsed .code-editor { display: none; }
    .section-resize { flex: 0 0 6px; background: #18181b; cursor: row-resize; }
    .section-resize:hover, .editor-fields.resizing .section-resize { background: #24242a; }
    .section-resize[hidden] { display: none; }
    .field-label {
      display: flex; align-items: center; gap: 8px; padding: 8px 12px; border: 0; border-bottom: 1px solid #3f3f46; cursor: pointer;
      font: inherit; font-size: 11px; text-align: left; text-transform: uppercase; letter-spacing: .05em; color: #d4d4d8;
      background: linear-gradient(180deg, #32323a, #282830);
    }
    .field-label::before { content: "▾"; display: grid; place-items: center; width: 22px; height: 22px; border-radius: 6px; background: #18181b; color: #a5b4fc; font-size: 16px; }
    .source-section.collapsed .field-label::before { content: "▸"; }
    .metadata-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; padding: 10px 12px 12px; }
    .metadata-field { display: grid; gap: 5px; min-width: 0; }
    .metadata-field[hidden] { display: none; }
    .metadata-field span { color: #a1a1aa; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
    .metadata-field input { width: 100%; padding: 7px 9px; border: 1px solid #3f3f46; border-radius: 6px; background: #27272a; color: #fafafa; font: 13px ui-monospace, "SF Mono", Menlo, monospace; }
    .code-editor { position: relative; flex: 1; min-height: 60px; overflow: hidden; background: #1e1e1e; }
    .code-editor textarea, .syntax-highlight {
      position: absolute; inset: 0; width: 100%; height: 100%; margin: 0; padding: 8px 16px 12px; border: 0; overflow: auto;
      white-space: pre-wrap; overflow-wrap: anywhere; font: 13px/1.5 ui-monospace, "SF Mono", Menlo, monospace; tab-size: 2;
    }
    .code-editor textarea { color: transparent; caret-color: #e4e4e7; background: transparent; resize: none; }
    .code-editor textarea:focus { outline: none; }
    .syntax-highlight { pointer-events: none; color: #d4d4d8; }
    .tok-tag { color: #7dd3fc; } .tok-attr { color: #f9a8d4; } .tok-str { color: #facc15; } .tok-comment { color: #71717a; }
    .tok-rule { color: #c4b5fd; } .tok-prop { color: #93c5fd; } .tok-var { color: #86efac; } .tok-key { color: #f0abfc; }
    .preview { min-width: 0; min-height: 0; background: #fff; }
    iframe { width: 100%; height: 100%; border: 0; background: #fff; }
    #token-editor { position: absolute; inset: 0; z-index: 5; display: none; }
    #token-editor.open { display: flex; }
    #tokens { flex: 1; border: 0; resize: none; padding: 14px 16px; background: #1e1e1e; color: #e4e4e7; font: 13px/1.5 ui-monospace, "SF Mono", Menlo, monospace; }
    .panel-bar { display: flex; gap: 8px; align-items: center; padding: 8px 12px; background: #111; }
    .panel-bar .hint { flex: 1; color: #71717a; font-size: 12px; }
    .panel-bar button { padding: 6px 14px; border: 0; border-radius: 6px; cursor: pointer; }
    .btn-token-apply { background: #22c55e; color: #052e16; }
    .btn-cancel { background: #3f3f46; color: #fafafa; }
    @media (max-width: 900px) {
      .stage { grid-template-columns: 1fr; grid-template-rows: minmax(360px, 50%) minmax(0, 1fr); }
      .stage-resize { display: none; }
    }
  </style>
</head>
<body>
  <header class="toolbar">
    <h1>Evolve Workbench</h1>
    <span class="kind" id="kind">component</span>
    <button id="btn-tokens">Tokens</button>
    <button id="btn-reload">Reload</button>
    <span class="spacer"></span>
    <button id="btn-open">Open</button>
  </header>

  <div class="workspace">
    <nav class="sidebar">
      <section><header><span>Components</span><button id="btn-new-component">+</button></header><ul id="list-components"></ul><div class="empty" id="empty-components" hidden>No components yet.</div></section>
      <section><header><span>Layouts</span><button id="btn-new-layout">+</button></header><ul id="list-layouts"></ul><div class="empty" id="empty-layouts" hidden>No layouts yet.</div></section>
      <section><header><span>Pages</span><button id="btn-new-page">+</button></header><ul id="list-pages"></ul><div class="empty" id="empty-pages" hidden>No pages yet.</div></section>
    </nav>
    <div class="sidebar-resize" id="sidebar-resize"></div>

    <main class="stage">
      <div class="panel" id="editor">
        <div class="editor-fields">
          <section class="source-section" data-block="metadata">
            <button class="field-label" type="button" data-toggle-source="metadata">Metadata</button>
            <div class="metadata-grid">
              <label class="metadata-field"><span>Name</span><input id="meta-name"></label>
              <label class="metadata-field" data-meta="slug"><span>Slug</span><input id="meta-slug"></label>
            </div>
          </section>
          <section class="source-section" data-block="php"><button class="field-label" type="button" data-toggle-source="php">PHP</button><div class="code-editor"><pre class="syntax-highlight" id="php-highlight"></pre><textarea id="php-source" spellcheck="false"></textarea></div></section>
          <div class="section-resize" data-resize-source data-before="php" data-after="blade"></div>
          <section class="source-section" data-block="blade"><button class="field-label" type="button" data-toggle-source="blade">Blade</button><div class="code-editor"><pre class="syntax-highlight" id="blade-highlight"></pre><textarea id="blade-source" spellcheck="false"></textarea></div></section>
          <div class="section-resize" data-resize-source data-before="blade" data-after="style"></div>
          <section class="source-section" data-block="style"><button class="field-label" type="button" data-toggle-source="style">Style</button><div class="code-editor"><pre class="syntax-highlight" id="style-highlight"></pre><textarea id="style-source" spellcheck="false"></textarea></div></section>
          <div class="section-resize" data-resize-source data-before="style" data-after="usage"></div>
          <section class="source-section" data-block="usage"><button class="field-label" type="button" data-toggle-source="usage">Usage</button><div class="code-editor"><pre class="syntax-highlight" id="usage-highlight"></pre><textarea id="usage" spellcheck="false"></textarea></div></section>
        </div>
      </div>
      <div class="stage-resize" id="stage-resize"></div>
      <div class="preview"><iframe id="frame" sandbox="allow-scripts allow-same-origin allow-forms" title="preview"></iframe></div>

      <div class="panel" id="token-editor">
        <div class="editor-fields"><div class="field-label">Tokens</div><textarea id="tokens" spellcheck="false"></textarea></div>
        <div class="panel-bar"><span class="hint">Saved to resources/css/tokens.css.</span><button class="btn-cancel" id="btn-token-cancel">Cancel</button><button class="btn-token-apply" id="btn-token-apply">Apply</button></div>
      </div>
    </main>
  </div>

  <script>
    const API = '/api/library';
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    let library = [];
    let selectedKey = '';
    let saveTimer = 0;
    let savedTokens = '';
    const frame = document.getElementById('frame');
    const workspace = document.querySelector('.workspace');
    const stage = document.querySelector('.stage');
    const editorFields = document.querySelector('#editor .editor-fields');
    const tokenEditor = document.getElementById('token-editor');
    const fields = {
      name: document.getElementById('meta-name'),
      slug: document.getElementById('meta-slug'),
      php: document.getElementById('php-source'),
      blade: document.getElementById('blade-source'),
      style: document.getElementById('style-source'),
      usage: document.getElementById('usage'),
    };
    const highlights = {
      php: document.getElementById('php-highlight'),
      blade: document.getElementById('blade-highlight'),
      style: document.getElementById('style-highlight'),
      usage: document.getElementById('usage-highlight'),
    };
    const sourceSections = [...document.querySelectorAll('.source-section')];
    const sourceResizeHandles = [...document.querySelectorAll('[data-resize-source]')];
    const collapseKey = 'evolve.sfc-collapse';
    const heightsKey = 'evolve.sfc-heights';

    const artifactKey = c => c ? `${c.kind}:${c.id}` : '';
    const selected = () => library.find(c => artifactKey(c) === selectedKey) ?? null;
    const byKind = kind => library.filter(c => c.kind === kind);

    async function load() {
      const data = await fetch(API, { headers: { accept: 'application/json' } }).then(r => r.json());
      library = [...data.components, ...data.layouts, ...data.pages];
      selectedKey ||= artifactKey(library[0]);
      renderLists();
      syncInputs();
      renderFrame();
    }

    async function loadTokens() {
      savedTokens = await fetch('/tokens.css', { cache: 'no-store' }).then(r => r.text());
      document.getElementById('tokens').value = savedTokens;
    }

    function save(refresh = false) {
      const payload = {
        components: byKind('component'),
        layouts: byKind('layout'),
        pages: byKind('page'),
      };
      return fetch(API, {
        method: 'PUT',
        headers: { 'content-type': 'application/json', 'x-csrf-token': csrf },
        body: JSON.stringify(payload),
      }).then(() => { if (refresh) renderFrame(); });
    }

    function scheduleSave(refresh = false) {
      clearTimeout(saveTimer);
      saveTimer = setTimeout(() => save(refresh), 300);
    }

    function renderLists() {
      renderList('component', 'list-components', 'empty-components');
      renderList('layout', 'list-layouts', 'empty-layouts');
      renderList('page', 'list-pages', 'empty-pages');
    }

    function renderList(kind, listId, emptyId) {
      const list = document.getElementById(listId);
      const items = byKind(kind);
      list.innerHTML = '';
      items.forEach(item => {
        const li = document.createElement('li');
        li.className = artifactKey(item) === selectedKey ? 'active' : '';
        li.innerHTML = `<span class="label"></span><span class="meta"></span>`;
        li.querySelector('.label').textContent = item.name || item.id;
        li.querySelector('.meta').textContent = kind === 'page' ? item.slug : item.id;
        li.onclick = () => { selectedKey = artifactKey(item); syncInputs(); renderLists(); renderFrame(); };
        list.append(li);
      });
      document.getElementById(emptyId).hidden = items.length > 0;
    }

    function syncInputs() {
      const c = selected();
      document.getElementById('kind').textContent = c?.kind ?? '-';
      fields.name.value = c?.name ?? '';
      fields.slug.value = c?.slug ?? '';
      fields.php.value = c?.php ?? '';
      fields.blade.value = c?.blade ?? '';
      fields.style.value = c?.style ?? '';
      fields.usage.value = c?.usage ?? '';
      document.querySelector('[data-meta="slug"]').hidden = c?.kind !== 'page';
      sourceSection('php').hidden = c?.kind === 'layout';
      sourceSection('usage').hidden = c?.kind === 'layout';
      updateHighlights();
      updateResizeHandles();
      requestAnimationFrame(fitSourceHeights);
    }

    function renderFrame() {
      const c = selected();
      if (!c) return;
      frame.src = `${previewUrl(c)}?t=${Date.now()}`;
    }

    function previewUrl(c) {
      if (c.kind === 'page') return c.slug || '/';
      return `/workbench/preview/${c.kind}/${c.id}`;
    }

    function updateSelected() {
      const c = selected();
      if (!c) return;
      c.name = fields.name.value;
      c.slug = fields.slug.value || '/';
      c.php = c.kind === 'layout' ? '' : fields.php.value;
      c.blade = fields.blade.value;
      c.style = fields.style.value;
      c.usage = c.kind === 'layout' ? '' : fields.usage.value;
      updateHighlights();
      renderLists();
      scheduleSave(true);
    }

    Object.values(fields).forEach(input => input.addEventListener('input', updateSelected));

    function newArtifact(kind) {
      const id = `new-${crypto.randomUUID().slice(0, 8)}`;
      const component = id.replaceAll('/', '.');
      const item = {
        id, kind, name: kind === 'page' ? 'New page' : kind === 'layout' ? 'New layout' : 'New component',
        slug: kind === 'page' ? `/${id}` : '',
        php: kind === 'layout' ? '' : "use Livewire\\Component;\n\nnew class extends Component {\n    //\n};",
        blade: kind === 'component' ? '<div>New component</div>' : '@{{ $slot }}',
        style: '',
        usage: kind === 'component' ? `<livewire:${component} />` : kind === 'layout' ? '' : `<livewire:pages::${component} />`,
      };
      library.push(item);
      selectedKey = artifactKey(item);
      renderLists(); syncInputs(); save(true);
    }
    document.getElementById('btn-new-component').onclick = () => newArtifact('component');
    document.getElementById('btn-new-layout').onclick = () => newArtifact('layout');
    document.getElementById('btn-new-page').onclick = () => newArtifact('page');
    document.getElementById('btn-reload').onclick = () => { load(); loadTokens(); };
    document.getElementById('btn-open').onclick = () => { const c = selected(); if (c) window.open(previewUrl(c), '_blank'); };
    document.getElementById('btn-tokens').onclick = () => tokenEditor.classList.add('open');
    document.getElementById('btn-token-cancel').onclick = () => { document.getElementById('tokens').value = savedTokens; tokenEditor.classList.remove('open'); };
    document.getElementById('btn-token-apply').onclick = async () => {
      const css = document.getElementById('tokens').value;
      await fetch('/api/tokens', { method: 'PUT', headers: { 'content-type': 'text/css', 'x-csrf-token': csrf }, body: css });
      savedTokens = css;
      tokenEditor.classList.remove('open');
      renderFrame();
    };

    function escapeHtml(value) { return String(value ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch])); }
    function highlightHtml(source) {
      return escapeHtml(source)
        .replace(/(&lt;!--[\s\S]*?--&gt;|\{\{--[\s\S]*?--\}\})/g, '<span class="tok-comment">$1</span>')
        .replace(/(&lt;\/?)([a-zA-Z][\w:.-]*)([\s\S]*?)(\/?&gt;)/g, (_m, open, tag, attrs, close) => `${open}<span class="tok-tag">${tag}</span>${attrs.replace(/([:@a-zA-Z_][\w:.-]*)(=)(&quot;.*?&quot;|&#39;.*?&#39;)/g, '<span class="tok-attr">$1</span>$2<span class="tok-str">$3</span>')}${close}`);
    }
    function highlightCss(source) { return escapeHtml(source).replace(/(\/\*[\s\S]*?\*\/)/g, '<span class="tok-comment">$1</span>').replace(/(--[\w-]+)/g, '<span class="tok-var">$1</span>').replace(/([{}])/g, '<span class="tok-rule">$1</span>').replace(/([a-z-]+)(\s*:)/gi, '<span class="tok-prop">$1</span>$2'); }
    function highlightPhp(source) { return escapeHtml(source).replace(/\b(use|new|class|extends|public|protected|private|function|return|string|int|bool|array)\b/g, '<span class="tok-key">$1</span>').replace(/(\/\/.*$)/gm, '<span class="tok-comment">$1</span>').replace(/(&quot;.*?&quot;|&#39;.*?&#39;)/g, '<span class="tok-str">$1</span>'); }
    function updateHighlights() {
      highlights.php.innerHTML = highlightPhp(fields.php.value);
      highlights.blade.innerHTML = highlightHtml(fields.blade.value);
      highlights.style.innerHTML = highlightCss(fields.style.value);
      highlights.usage.innerHTML = highlightHtml(fields.usage.value);
    }
    ['php','blade','style','usage'].forEach(key => {
      fields[key].addEventListener('scroll', () => { highlights[key].scrollTop = fields[key].scrollTop; highlights[key].scrollLeft = fields[key].scrollLeft; });
    });

    document.querySelectorAll('[data-toggle-source]').forEach(button => {
      button.onclick = () => {
        const block = button.dataset.toggleSource;
        const state = { usage: true, ...JSON.parse(localStorage.getItem(collapseKey) || '{}') };
        state[block] = !button.closest('.source-section').classList.contains('collapsed');
        localStorage.setItem(collapseKey, JSON.stringify(state));
        applyCollapse();
      };
    });
    function applyCollapse() {
      const state = { usage: true, ...JSON.parse(localStorage.getItem(collapseKey) || '{}') };
      sourceSections.forEach(section => section.classList.toggle('collapsed', !!state[section.dataset.block]));
      updateResizeHandles(); fitSourceHeights();
    }
    function sourceSection(block) { return sourceSections.find(section => section.dataset.block === block); }
    function visibleCodeSections() { return ['php','blade','style','usage'].map(sourceSection).filter(section => section && !section.hidden && !section.classList.contains('collapsed')); }
    function availableCodeHeight() {
      const fixed = [...editorFields.children].reduce((sum, child) => child.hidden || (child.classList.contains('source-section') && ['php','blade','style','usage'].includes(child.dataset.block) && !child.classList.contains('collapsed')) ? sum : sum + child.getBoundingClientRect().height, 0);
      return Math.max(0, editorFields.clientHeight - fixed);
    }
    function fitSourceHeights() {
      const sections = visibleCodeSections();
      if (!sections.length) return;
      const available = availableCodeHeight();
      const total = sections.reduce((sum, section) => sum + section.getBoundingClientRect().height, 0);
      if (Math.abs(total - available) < 1) return;
      if (total < available) { const last = sections[sections.length - 1]; last.style.flex = `0 0 ${last.getBoundingClientRect().height + available - total}px`; return; }
      let remainingHeight = available, remainingTotal = total;
      sections.forEach((section, index) => {
        const height = index === sections.length - 1 ? remainingHeight : Math.max(44, (section.getBoundingClientRect().height / remainingTotal) * remainingHeight);
        section.style.flex = `0 0 ${height}px`; remainingHeight -= height; remainingTotal -= section.getBoundingClientRect().height;
      });
    }
    function updateResizeHandles() {
      sourceResizeHandles.forEach(handle => {
        const before = sourceSection(handle.dataset.before), after = sourceSection(handle.dataset.after);
        handle.hidden = !before || !after || before.hidden || after.hidden || before.classList.contains('collapsed') || after.classList.contains('collapsed');
      });
    }
    function initSectionResize() {
      let active = null;
      sourceResizeHandles.forEach(handle => {
        handle.onpointerdown = event => {
          const before = sourceSection(handle.dataset.before), after = sourceSection(handle.dataset.after);
          if (!before || !after || before.classList.contains('collapsed') || after.classList.contains('collapsed')) return;
          active = { id: event.pointerId, y: event.clientY, before, after, bh: before.getBoundingClientRect().height, ah: after.getBoundingClientRect().height };
          handle.setPointerCapture(event.pointerId); editorFields.classList.add('resizing');
        };
        handle.onpointermove = event => {
          if (!active || active.id !== event.pointerId) return;
          const total = active.bh + active.ah, min = Math.min(96, Math.max(44, total / 2)), delta = event.clientY - active.y;
          const beforeHeight = Math.min(Math.max(active.bh + delta, min), total - min);
          active.before.style.flex = `0 0 ${beforeHeight}px`; active.after.style.flex = `0 0 ${total - beforeHeight}px`; fitSourceHeights();
        };
        handle.onpointerup = event => { if (active?.id === event.pointerId) { active = null; editorFields.classList.remove('resizing'); fitSourceHeights(); } };
      });
    }
    function initColumnResize(handle, owner, setter) {
      let id = null;
      handle.onpointerdown = event => { id = event.pointerId; handle.setPointerCapture(id); owner.classList.add('resizing'); };
      handle.onpointermove = event => { if (id === event.pointerId) setter(event); };
      handle.onpointerup = event => { if (id === event.pointerId) { id = null; owner.classList.remove('resizing'); } };
    }
    initColumnResize(document.getElementById('sidebar-resize'), workspace, event => {
      const left = workspace.getBoundingClientRect().left, width = Math.min(Math.max(event.clientX - left, 180), 420);
      workspace.style.setProperty('--sidebar-width', `${width}px`); localStorage.setItem('evolve.sidebar-width', String(Math.round(width)));
    });
    initColumnResize(document.getElementById('stage-resize'), stage, event => {
      const box = stage.getBoundingClientRect(), width = Math.min(Math.max(event.clientX - box.left, 360), box.width - 329);
      stage.style.setProperty('--editor-width', `${width}px`); localStorage.setItem('evolve.stage-editor-width', String(Math.round(width)));
    });
    const sidebarWidth = Number(localStorage.getItem('evolve.sidebar-width')); if (sidebarWidth) workspace.style.setProperty('--sidebar-width', `${sidebarWidth}px`);
    const editorWidth = Number(localStorage.getItem('evolve.stage-editor-width')); if (editorWidth) stage.style.setProperty('--editor-width', `${editorWidth}px`);
    initSectionResize();
    applyCollapse();
    loadTokens();
    load();
  </script>
</body>
</html>
