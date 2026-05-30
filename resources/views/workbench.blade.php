<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Evolve Workbench</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  @fluxAppearance
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
    .toolbar [data-danger] { border-color: #7f1d1d; background: linear-gradient(180deg, #51242b, #3f1f24); color: #fecaca; }
    .toolbar [data-danger]:hover { background: linear-gradient(180deg, #632a32, #4c232a); }
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
    .sidebar li { position: relative; display: flex; gap: 8px; align-items: center; padding: 8px 12px; font-size: 13px; cursor: pointer; }
    .sidebar li:hover { background: #3f3f46; }
    .sidebar li.active { background: #4338ca; color: #fff; }
    .sidebar li[draggable="true"] { cursor: grab; }
    .sidebar li.dragging { opacity: .45; }
    .sidebar li.drop-before,
    .sidebar li.drop-after,
    .sidebar li.drop-inside { background: #34345f; }
    .sidebar li.drop-inside { box-shadow: inset 0 0 0 2px #a5b4fc; }
    .sidebar li.drop-before::before,
    .sidebar li.drop-after::after {
      content: "";
      position: absolute;
      left: 8px;
      right: 8px;
      height: 3px;
      border-radius: 999px;
      background: #a5b4fc;
      box-shadow: 0 0 0 1px #18181b, 0 0 14px rgba(165, 180, 252, .65);
      pointer-events: none;
      z-index: 2;
    }
    .sidebar li.drop-before::before { top: -2px; }
    .sidebar li.drop-after::after { bottom: -2px; }
    .sidebar .label { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .sidebar .meta { flex: 0 0 auto; white-space: nowrap; color: #a1a1aa; font-size: 11px; }
    .sidebar li.active .meta { color: #c7d2fe; }
    .empty { padding: 4px 12px 10px; color: #71717a; font-size: 12px; }
    .stage { flex: 1; display: grid; grid-template-columns: minmax(360px, var(--editor-width, 44%)) 9px minmax(320px, 1fr); min-width: 0; min-height: 0; overflow: hidden; }
    .stage.content-mode { grid-template-columns: minmax(0, 1fr); }
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
    .metadata-grid { display: grid; grid-template-columns: max-content minmax(0, 1fr); gap: 7px 12px; align-items: center; padding: 10px 12px 12px; }
    .metadata-field { display: grid; grid-template-columns: subgrid; grid-column: 1 / -1; align-items: center; min-width: 0; }
    .metadata-field[hidden] { display: none; }
    .metadata-field label { color: #a1a1aa; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
    .metadata-field input { width: 100%; padding: 7px 9px; border: 1px solid #3f3f46; border-radius: 6px; background: #27272a; color: #fafafa; font: 13px ui-monospace, "SF Mono", Menlo, monospace; }
    .sidebar li[data-depth="1"] { padding-left: 24px; }
    .sidebar li[data-depth="2"] { padding-left: 36px; }
    .sidebar li[data-depth="3"] { padding-left: 48px; }
    .sidebar li[data-depth="4"] { padding-left: 60px; }
    .content-index { flex: 1; min-height: 0; overflow: auto; padding: 12px; background: #1e1e1e; }
    .content-index[hidden] { display: none; }
    .content-page-header { display: grid; gap: 3px; padding: 2px 0 12px; }
    .content-page-header h2 { margin: 0; color: #fafafa; font-size: 18px; line-height: 1.2; }
    .content-page-header p { margin: 0; color: #71717a; font: 12px ui-monospace, "SF Mono", Menlo, monospace; }
    .content-toolbar { display: flex; gap: 8px; align-items: center; padding: 0 0 10px; }
    .content-toolbar [data-flux-input] { flex: 1; min-width: 160px; }
    .content-toolbar input { flex: 1; min-width: 160px; padding: 8px 10px; border: 1px solid #3f3f46; border-radius: 7px; background: #18181b; color: #fafafa; font: 13px system-ui, sans-serif; }
    .content-toolbar input:focus { outline: 2px solid #4f46e5; outline-offset: 0; }
    .content-toolbar button, .row-actions button { padding: 7px 10px; border: 1px solid #3f3f46; border-radius: 7px; background: #303036; color: #fafafa; cursor: pointer; font-weight: 700; }
    .content-toolbar button:hover, .row-actions button:hover { background: #3a3a42; }
    .content-index table { width: 100%; border-collapse: collapse; table-layout: fixed; color: #e4e4e7; font-size: 12px; }
    .content-index th { padding: 8px; color: #a1a1aa; font-size: 11px; text-align: left; text-transform: uppercase; letter-spacing: .04em; background: #27272a; position: sticky; top: 0; z-index: 1; }
    .content-index td { padding: 8px; border-top: 1px solid #27272a; vertical-align: top; }
    .content-index tbody tr { cursor: pointer; }
    .content-index tbody tr:hover { background: #242428; }
    .content-index tbody tr.editing { background: #252538; cursor: default; }
    .content-index .cell-title { font-weight: 700; }
    .content-index .cell-summary { color: #a1a1aa; line-height: 1.45; }
    .content-index .cell-muted { color: #71717a; }
    .content-index input, .content-index textarea { width: 100%; padding: 7px 8px; border: 1px solid #3f3f46; border-radius: 6px; background: #27272a; color: #fafafa; font: 12px ui-monospace, "SF Mono", Menlo, monospace; }
    .content-index textarea { min-height: 70px; resize: vertical; line-height: 1.4; }
    .content-index input[type="checkbox"] { width: auto; margin-top: 9px; }
    .content-index .position-cell { width: 72px; }
    .content-index .icon-cell { width: 76px; }
    .content-index .published-cell { width: 92px; text-align: center; }
    .content-index .status-cell { width: 92px; color: #71717a; font-size: 11px; }
    .content-index .actions-cell { width: 116px; }
    .row-actions { display: flex; gap: 6px; justify-content: flex-end; }
    .row-actions button { min-width: 34px; padding: 5px 8px; font-size: 12px; }
    .row-actions .danger { color: #fecaca; border-color: #7f1d1d; background: #3f1f24; }
    .row-actions .danger:hover { background: #52252b; }
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
    .stage.content-mode .stage-resize, .stage.content-mode .preview { display: none; }
    iframe { width: 100%; height: 100%; border: 0; background: #fff; }
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
    <flux:button id="btn-reload" size="sm" variant="filled">Reload</flux:button>
    <span class="spacer"></span>
    <flux:button id="btn-delete" size="sm" variant="filled" data-danger>Delete</flux:button>
    <flux:button id="btn-open" size="sm" variant="filled">Open</flux:button>
  </header>

  <div class="workspace">
    <nav class="sidebar">
      <section><header><span>Styles</span><flux:button id="btn-new-style" size="xs" variant="filled">+</flux:button></header><ul id="list-styles"></ul><div class="empty" id="empty-styles" hidden>No styles yet.</div></section>
      <section><header><span>Layouts</span><flux:button id="btn-new-layout" size="xs" variant="filled">+</flux:button></header><ul id="list-layouts"></ul><div class="empty" id="empty-layouts" hidden>No layouts yet.</div></section>
      <section><header><span>Pages</span><flux:button id="btn-new-page" size="xs" variant="filled">+</flux:button></header><ul id="list-pages"></ul><div class="empty" id="empty-pages" hidden>No pages yet.</div></section>
      <section><header><span>Snippets</span><flux:button id="btn-new-snippet" size="xs" variant="filled">+</flux:button></header><ul id="list-snippets"></ul><div class="empty" id="empty-snippets" hidden>No snippets yet.</div></section>
      <section><header><span>Components</span><flux:button id="btn-new-component" size="xs" variant="filled">+</flux:button></header><ul id="list-components"></ul><div class="empty" id="empty-components" hidden>No components yet.</div></section>
      <section><header><span>Forms</span><flux:button id="btn-new-form" size="xs" variant="filled">+</flux:button></header><ul id="list-forms"></ul><div class="empty" id="empty-forms" hidden>No forms yet.</div></section>
      <section><header><span>Content</span><flux:button id="btn-new-content" size="xs" variant="filled">+</flux:button></header><ul id="list-content"></ul><div class="empty" id="empty-content" hidden>No content yet.</div></section>
    </nav>
    <div class="sidebar-resize" id="sidebar-resize"></div>

    <main class="stage">
      <div class="panel" id="editor">
        <div class="editor-fields">
          <section class="source-section" data-block="metadata">
            <button class="field-label" type="button" data-toggle-source="metadata">Metadata</button>
            <div class="metadata-grid">
              <div class="metadata-field"><label for="meta-name">Name</label><input id="meta-name"></div>
              <div class="metadata-field" data-meta="path"><label for="meta-slug">Path</label><input id="meta-slug"></div>
              <div class="metadata-field" data-meta="route"><label for="meta-route">Route</label><input id="meta-route"></div>
              <div class="metadata-field" data-meta="route-name"><label for="meta-route-name">Route name</label><input id="meta-route-name" placeholder="auto"></div>
              <div class="metadata-field" data-meta="middleware"><label for="meta-middleware">Middleware</label><input id="meta-middleware" placeholder="auth, verified"></div>
              <div class="metadata-field" data-meta="parent"><label for="meta-parent">Parent</label><input id="meta-parent" list="page-parent-options"></div>
              <div class="metadata-field" data-meta="order"><label for="meta-order">Order</label><input id="meta-order" type="number" min="0" step="1"></div>
              <datalist id="page-parent-options"></datalist>
            </div>
          </section>
          <div class="content-index" id="content-index" hidden>
            <div class="content-page-header">
              <h2 id="content-model-name">Content</h2>
              <p id="content-model-path">Create a content model to begin.</p>
            </div>
            <div class="content-toolbar">
              <flux:input id="content-search" type="search" placeholder="Search content" size="sm" />
              <flux:button id="btn-add-content-row" type="button" size="sm" variant="filled">Add row</flux:button>
            </div>
            <table>
              <thead>
                <tr>
                  <th class="position-cell">Order</th>
                  <th class="icon-cell">Icon</th>
                  <th>Title</th>
                  <th>Summary</th>
                  <th class="published-cell">Published</th>
                  <th class="status-cell">Status</th>
                  <th class="actions-cell">Actions</th>
                </tr>
              </thead>
              <tbody id="content-rows"></tbody>
            </table>
          </div>
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
    </main>
  </div>

  <script>
    const API = '/api/library';
    const CONTENT_API = '/api/content';
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    let library = [];
    let contentModels = [];
    let contentData = {};
    let contentRows = [];
    let selectedContentId = '';
    let selectedKey = '';
    let saveTimer = 0;
    let contentSaveTimer = 0;
    let contentFilter = '';
    let editingContentKey = '';
    let contentStatus = {};
    let dragState = { kind: '', key: '', placement: 'after' };
    const frame = document.getElementById('frame');
    const workspace = document.querySelector('.workspace');
    const stage = document.querySelector('.stage');
    const editorFields = document.querySelector('#editor .editor-fields');
    const contentIndex = document.getElementById('content-index');
    const contentRowsEl = document.getElementById('content-rows');
    const contentSearch = document.getElementById('content-search');
    const contentModelName = document.getElementById('content-model-name');
    const contentModelPath = document.getElementById('content-model-path');
    const deleteButton = document.getElementById('btn-delete');
    const fields = {
      name: document.getElementById('meta-name'),
      slug: document.getElementById('meta-slug'),
      route: document.getElementById('meta-route'),
      routeName: document.getElementById('meta-route-name'),
      middleware: document.getElementById('meta-middleware'),
      parent: document.getElementById('meta-parent'),
      order: document.getElementById('meta-order'),
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
    const pageParentOptions = document.getElementById('page-parent-options');
    const sourceResizeHandles = [...document.querySelectorAll('[data-resize-source]')];
    const collapseKey = 'evolve.sfc-collapse';
    const heightsKey = 'evolve.sfc-heights';

    const artifactKey = c => c ? `${c.kind}:${c.id}` : '';
    const selected = () => library.find(c => artifactKey(c) === selectedKey) ?? null;
    const byKind = kind => library.filter(c => c.kind === kind);
    const libraryArtifacts = data => [...(data.styles ?? []), ...(data.layouts ?? []), ...(data.pages ?? []), ...(data.snippets ?? []), ...(data.components ?? []), ...(data.forms ?? [])];
    const artifactRouteId = artifact => String(artifact.previous_id || artifact.id).split('/').map(encodeURIComponent).join('/');
    const selectedContentModel = () => contentModels.find(model => model.id === selectedContentId) ?? contentModels[0] ?? null;
    const refreshContentModel = () => {
      contentModels = contentModels.map(model => ({ ...model, meta: `${(contentData[model.id] ?? []).length} rows` }));
      library = [...byKind('style'), ...byKind('layout'), ...byKind('page'), ...byKind('snippet'), ...byKind('component'), ...byKind('form'), ...contentModels];
    };
    const applyLibraryData = (data, fallbackKey = '') => {
      library = [...libraryArtifacts(data), ...contentModels];
      selectedKey = library.find(item => artifactKey(item) === selectedKey)
        ? selectedKey
        : library.find(item => artifactKey(item) === fallbackKey)
          ? fallbackKey
          : artifactKey(library[0]);
      renderLists();
      syncInputs();
    };

    async function load() {
      const [data, content] = await Promise.all([
        fetch(API, { headers: { accept: 'application/json' } }).then(r => r.json()),
        fetch(CONTENT_API, { headers: { accept: 'application/json' } }).then(r => r.json()),
      ]);
      contentModels = content.models ?? [];
      contentData = content.data ?? {};
      selectedContentId = contentModels.find(model => model.id === selectedContentId)?.id ?? contentModels[0]?.id ?? '';
      contentRows = contentData[selectedContentId] ?? [];
      library = [...libraryArtifacts(data), ...contentModels];
      selectedKey ||= artifactKey(library[0]);
      renderLists();
      syncInputs();
      renderFrame();
    }

    function save(refresh = false) {
      const artifact = selected();
      if (!artifact || artifact.kind === 'content') return Promise.resolve();

      const desiredKey = artifactKey(artifact);
      return fetch(`${API}/${artifact.kind}/${artifactRouteId(artifact)}`, {
        method: 'PUT',
        headers: { 'content-type': 'application/json', 'x-csrf-token': csrf },
        body: JSON.stringify(artifact),
      }).then(r => {
        if (!r.ok) throw new Error('Library save failed');
        return r.json();
      }).then(data => {
        delete artifact.previous_id;
        selectedKey = desiredKey;
        applyLibraryData(data);
        if (refresh) renderFrame();
      });
    }

    const contentRowKey = row => String(row.id ?? '');

    function saveContent(refresh = false) {
      const currentModel = selectedContentModel();
      if (!currentModel) return Promise.resolve();
      contentData[selectedContentId] = contentRows.map(row => ({ ...row, model: currentModel.model }));
      const shouldRebuildTable = contentRows.some(row => String(row.id ?? '').startsWith('new-')) || !contentIndex.contains(document.activeElement);
      const previousEditingKey = editingContentKey;
      if (previousEditingKey) contentStatus[previousEditingKey] = 'Saving';
      return fetch(CONTENT_API, {
        method: 'PUT',
        headers: { 'content-type': 'application/json', 'x-csrf-token': csrf },
        body: JSON.stringify({ data: contentData }),
      }).then(r => {
        if (!r.ok) throw new Error('Content save failed');
        return r.json();
      }).then(data => {
        contentModels = data.models ?? contentModels;
        contentData = data.data ?? contentData;
        const savedRows = contentData[selectedContentId] ?? [];
        const replacement = savedRows.find(row => row.client_id === previousEditingKey);
        if (replacement) editingContentKey = replacement.id;
        if (shouldRebuildTable) {
          contentRows = savedRows;
        } else {
          savedRows.forEach((saved, index) => {
            if (contentRows[index] && !String(contentRows[index].id ?? '').startsWith('new-')) {
              contentRows[index].id = saved.id;
              contentRows[index].client_id = saved.client_id;
            }
          });
        }
        if (previousEditingKey) contentStatus[editingContentKey || previousEditingKey] = 'Saved';
        refreshContentModel();
        renderLists();
        if (shouldRebuildTable) {
          renderContentIndex();
        } else {
          const statusCell = contentRowsEl.querySelector('[data-row-status]');
          if (statusCell) {
            statusCell.textContent = 'Saved';
            setTimeout(() => {
              if (statusCell.textContent === 'Saved') statusCell.textContent = '';
            }, 1200);
          }
          contentStatus = {};
        }
        if (refresh) renderFrame();
      }).catch(() => {
        if (previousEditingKey) contentStatus[previousEditingKey] = 'Error';
        const statusCell = contentRowsEl.querySelector('[data-row-status]');
        if (statusCell) statusCell.textContent = 'Error';
        else renderContentIndex();
      });
    }

    function scheduleSave(refresh = false) {
      clearTimeout(saveTimer);
      saveTimer = setTimeout(() => save(refresh), 300);
    }

    function scheduleContentSave(refresh = false) {
      clearTimeout(contentSaveTimer);
      contentSaveTimer = setTimeout(() => saveContent(refresh), 300);
    }

    function deleteSelectedArtifact() {
      const artifact = selected();
      if (!artifact || artifact.kind === 'content') return;

      const kindItems = byKind(artifact.kind);
      const index = kindItems.findIndex(item => artifactKey(item) === artifactKey(artifact));
      const fallback = kindItems[index + 1] ?? kindItems[index - 1] ?? library.find(item => item.kind !== 'content') ?? byKind('content')[0];
      const target = artifact.source_path || artifact.path || artifact.slug || artifact.id;

      if (!confirm(`Delete ${artifact.kind} "${artifact.name || artifact.id}"?\n\nThis removes ${target}.`)) return;

      clearTimeout(saveTimer);
      deleteButton.disabled = true;

      fetch(`${API}/${artifact.kind}/${artifactRouteId(artifact)}`, {
        method: 'DELETE',
        headers: { 'x-csrf-token': csrf },
      }).then(r => {
        if (!r.ok) throw new Error('Delete failed');
        return r.json();
      }).then(data => {
        selectedKey = '';
        applyLibraryData(data, artifactKey(fallback));
        renderFrame();
      }).catch(error => {
        alert(error.message);
      }).finally(() => {
        deleteButton.disabled = false;
        syncInputs();
      });
    }

    function renderLists() {
      renderList('style', 'list-styles', 'empty-styles');
      renderList('layout', 'list-layouts', 'empty-layouts');
      renderList('page', 'list-pages', 'empty-pages');
      renderList('snippet', 'list-snippets', 'empty-snippets');
      renderList('component', 'list-components', 'empty-components');
      renderList('form', 'list-forms', 'empty-forms');
      renderList('content', 'list-content', 'empty-content');
    }

    function renderList(kind, listId, emptyId) {
      const list = document.getElementById(listId);
      const items = byKind(kind);
      const metaFormatter = navigationMetaFormatter(kind, items);
      list.innerHTML = '';
      items.forEach(item => {
        const li = document.createElement('li');
        li.className = artifactKey(item) === selectedKey ? 'active' : '';
        li.dataset.key = artifactKey(item);
        if (kind === 'page') li.dataset.depth = Math.min(Number(item.depth ?? 0), 4);
        li.innerHTML = `<span class="label"></span><span class="meta"></span>`;
        li.querySelector('.label').textContent = item.name || item.id;
        li.querySelector('.meta').textContent = metaFormatter(item);
        if (['style', 'page'].includes(kind)) enableSortableItem(kind, li);
        li.onclick = () => {
          selectedKey = artifactKey(item);
          if (kind === 'content') {
            selectedContentId = item.id;
            contentRows = contentData[selectedContentId] ?? [];
          }
          syncInputs(); renderLists(); renderFrame();
        };
        list.append(li);
      });
      document.getElementById(emptyId).hidden = items.length > 0;
    }

    function enableSortableItem(kind, li) {
      li.draggable = true;
      li.ondragstart = event => {
        dragState = { kind, key: li.dataset.key, placement: 'after' };
        li.classList.add('dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', li.dataset.key);
      };
      li.ondragend = () => {
        li.classList.remove('dragging');
        clearDropIndicators();
        dragState = { kind: '', key: '', placement: 'after' };
      };
      li.ondragover = event => {
        if (dragState.kind !== kind || dragState.key === li.dataset.key) return;
        event.preventDefault();
        dragState.placement = dropPlacement(event, li);
        showDropIndicator(li, dragState.placement);
      };
      li.ondragleave = event => {
        if (!li.contains(event.relatedTarget)) {
          li.classList.remove('drop-before', 'drop-after', 'drop-inside');
        }
      };
      li.ondrop = event => {
        if (dragState.kind !== kind) return;
        event.preventDefault();
        const sourceKey = dragState.key || event.dataTransfer.getData('text/plain');
        const targetKey = li.dataset.key;
        const placement = dragState.placement || dropPlacement(event, li, kind);
        clearDropIndicators();
        if (kind === 'style') reorderStyle(sourceKey, targetKey, placement);
        if (kind === 'page') reorderPage(sourceKey, targetKey, placement);
      };
    }

    function dropPlacement(event, li, kind = dragState.kind) {
      const rect = li.getBoundingClientRect();
      if (kind === 'page') {
        const y = event.clientY - rect.top;
        if (y > rect.height * .28 && y < rect.height * .72) return 'inside';
      }

      return event.clientY < rect.top + rect.height / 2 ? 'before' : 'after';
    }

    function showDropIndicator(li, placement) {
      clearDropIndicators();
      li.classList.add(`drop-${placement}`);
    }

    function clearDropIndicators() {
      document.querySelectorAll('.sidebar li.drop-before, .sidebar li.drop-after, .sidebar li.drop-inside').forEach(item => {
        item.classList.remove('drop-before', 'drop-after', 'drop-inside');
      });
    }

    function navigationMetaFormatter(kind, items) {
      if (kind === 'content') return item => item.meta;
      if (kind === 'page') return item => item.route || item.path;
      if (['component', 'form'].includes(kind)) return item => `<livewire:${item.component} />`;
      if (kind === 'snippet') return item => `<x-${item.component} />`;
      if (kind === 'layout') return item => item.component;

      const paths = items.map(item => item.source_path || item.path || item.id).filter(Boolean);
      const prefix = commonPrefix(paths);
      const suffix = commonSuffix(paths.map(path => path.slice(prefix.length)));

      return item => {
        const value = item.source_path || item.path || item.id;
        if (!value) return '';
        const abbreviated = value.slice(prefix.length, suffix ? -suffix.length : undefined);

        return abbreviated || value;
      };
    }

    function commonPrefix(values) {
      if (!values.length) return '';
      let prefix = values[0];
      values.slice(1).forEach(value => {
        while (prefix && !value.startsWith(prefix)) prefix = prefix.slice(0, -1);
      });

      const slash = prefix.lastIndexOf('/');
      return slash >= 0 ? prefix.slice(0, slash + 1) : '';
    }

    function commonSuffix(values) {
      if (!values.length) return '';
      let suffix = values[0];
      values.slice(1).forEach(value => {
        while (suffix && !value.endsWith(suffix)) suffix = suffix.slice(1);
      });

      if (!suffix.includes('.')) return '';
      const dot = suffix.indexOf('.');
      return suffix.slice(dot);
    }

    function renderContentIndex() {
      if (!contentRowsEl) return;
      const filter = contentFilter.trim().toLowerCase();
      const model = selectedContentModel();
      if (!model) {
        contentModelName.textContent = 'Content';
        contentModelPath.textContent = 'Create a content model to begin.';
        contentSearch.placeholder = 'Search content';
        contentRowsEl.innerHTML = '<tr><td colspan="7" class="cell-muted">No content models yet.</td></tr>';
        return;
      }
      contentModelName.textContent = model.name;
      contentModelPath.textContent = model.path ?? `app/Models/${model.name}.php`;
      contentSearch.placeholder = `Search ${model.name}`;
      const rows = filter
        ? contentRows.filter(row => [row.title, row.summary, row.icon].some(value => String(value ?? '').toLowerCase().includes(filter)))
        : contentRows;
      contentRowsEl.innerHTML = '';
      if (!rows.length) {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td colspan="7" class="cell-muted">${contentRows.length ? `No matching ${escapeHtml(selectedContentModel().name)} rows.` : `No ${escapeHtml(selectedContentModel().name)} rows yet.`}</td>`;
        contentRowsEl.append(tr);
        return;
      }
      rows.forEach((row, index) => {
        const key = contentRowKey(row);
        const editing = editingContentKey === key;
        const tr = document.createElement('tr');
        tr.className = editing ? 'editing' : '';
        tr.innerHTML = editing ? `
          <td class="position-cell"><input type="number" min="0" step="1" data-field="position"></td>
          <td class="icon-cell"><input data-field="icon"></td>
          <td><input data-field="title"></td>
          <td><textarea data-field="summary"></textarea></td>
          <td class="published-cell"><input type="checkbox" data-field="is_published"></td>
          <td class="status-cell" data-row-status>${contentStatus[key] ?? ''}</td>
          <td class="actions-cell"><div class="row-actions"><button type="button" data-action="done">Done</button><button class="danger" type="button" data-action="delete">Delete</button></div></td>
        ` : `
          <td class="position-cell">${escapeHtml(row.position ?? index + 1)}</td>
          <td class="icon-cell">${escapeHtml(row.icon ?? '')}</td>
          <td class="cell-title">${escapeHtml(row.title ?? '')}</td>
          <td class="cell-summary">${escapeHtml(row.summary ?? '')}</td>
          <td class="published-cell">${row.is_published ? 'Yes' : 'No'}</td>
          <td class="status-cell">${contentStatus[key] ?? ''}</td>
          <td class="actions-cell"><div class="row-actions"><button type="button" data-action="edit">Edit</button><button class="danger" type="button" data-action="delete">Delete</button></div></td>
        `;
        if (!editing) {
          tr.addEventListener('click', event => {
            if (event.target.closest('button')) return;
            editingContentKey = key;
            renderContentIndex();
          });
        } else {
          tr.querySelector('[data-field="position"]').value = row.position ?? index + 1;
          tr.querySelector('[data-field="icon"]').value = row.icon ?? '';
          tr.querySelector('[data-field="title"]').value = row.title ?? '';
          tr.querySelector('[data-field="summary"]').value = row.summary ?? '';
          tr.querySelector('[data-field="is_published"]').checked = !!row.is_published;
          tr.querySelectorAll('[data-field]').forEach(input => {
            const eventName = input.type === 'checkbox' ? 'change' : 'input';
            input.addEventListener(eventName, () => {
              const field = input.dataset.field;
              row[field] = input.type === 'checkbox' ? input.checked : field === 'position' ? Number(input.value || 0) : input.value;
              row.name = row.title;
              contentData[selectedContentId] = contentRows;
              refreshContentModel();
              renderLists();
              contentStatus[contentRowKey(row)] = 'Saving';
              const statusCell = tr.querySelector('[data-row-status]');
              if (statusCell) statusCell.textContent = 'Saving';
              scheduleContentSave(true);
            });
          });
        }
        tr.querySelector('[data-action="edit"]')?.addEventListener('click', () => {
          editingContentKey = key;
          renderContentIndex();
        });
        tr.querySelector('[data-action="done"]')?.addEventListener('click', () => {
          editingContentKey = '';
          renderContentIndex();
        });
        tr.querySelector('[data-action="delete"]')?.addEventListener('click', () => {
          if (!confirm(`Delete "${row.title || 'this row'}"?`)) return;
          contentRows = contentRows.filter(entry => entry !== row);
          contentData[selectedContentId] = contentRows;
          if (editingContentKey === key) editingContentKey = '';
          contentStatus[key] = 'Deleting';
          refreshContentModel();
          renderLists();
          renderContentIndex();
          saveContent(true);
        });
        contentRowsEl.append(tr);
      });
    }

    function reorderStyle(sourceKey, targetKey, placement = 'before') {
      if (!sourceKey || !targetKey || sourceKey === targetKey) return;
      const styles = byKind('style');
      const from = styles.findIndex(item => artifactKey(item) === sourceKey);
      if (from < 0) return;
      const [moved] = styles.splice(from, 1);
      const to = styles.findIndex(item => artifactKey(item) === targetKey);
      if (to < 0) return;
      styles.splice(placement === 'after' ? to + 1 : to, 0, moved);
      library = [...styles, ...byKind('layout'), ...byKind('page'), ...byKind('snippet'), ...byKind('component'), ...byKind('form'), ...byKind('content')];
      renderLists();
      fetch(`${API}/styles/order`, {
        method: 'PUT',
        headers: { 'content-type': 'application/json', 'x-csrf-token': csrf },
        body: JSON.stringify({ ids: styles.map(style => style.id) }),
      }).then(r => {
        if (!r.ok) throw new Error('Style order save failed');
        return r.json();
      }).then(data => {
        applyLibraryData(data);
        renderFrame();
      });
    }

    function reorderPage(sourceKey, targetKey, placement = 'before') {
      if (!sourceKey || !targetKey || sourceKey === targetKey) return;
      const pages = byKind('page');
      const source = pages.find(item => artifactKey(item) === sourceKey);
      const target = pages.find(item => artifactKey(item) === targetKey);
      if (!source || !target) return;
      if (isPageDescendant(target, source, pages)) return;

      const targetParent = placement === 'inside' ? target.id : target.parent_id || '';
      const previousParent = source.parent_id || '';
      const siblings = pages
        .filter(page => (page.parent_id || '') === targetParent && page.id !== source.id)
        .sort(sortPagesForReorder);
      const targetIndex = placement === 'inside'
        ? siblings.length
        : siblings.findIndex(page => page.id === target.id);
      if (targetIndex < 0) return;

      source.parent_id = targetParent;
      siblings.splice(placement === 'after' ? targetIndex + 1 : targetIndex, 0, source);
      siblings.forEach((page, index) => page.order = index + 1);
      if (previousParent !== targetParent) {
        pages
          .filter(page => (page.parent_id || '') === previousParent && page.id !== source.id)
          .sort(sortPagesForReorder)
          .forEach((page, index) => page.order = index + 1);
      }

      library = [...byKind('style'), ...byKind('layout'), ...pages, ...byKind('snippet'), ...byKind('component'), ...byKind('form'), ...byKind('content')];
      renderLists();

      saveLibrary({
        styles: byKind('style'),
        layouts: byKind('layout'),
        pages,
        snippets: byKind('snippet'),
        components: byKind('component'),
        forms: byKind('form'),
      }, 'Page order save failed');
    }

    function sortPagesForReorder(a, b) {
      return (Number(a.order ?? 0) - Number(b.order ?? 0))
        || String(a.name || a.id).localeCompare(String(b.name || b.id))
        || String(a.id).localeCompare(String(b.id));
    }

    function isPageDescendant(candidate, ancestor, pages) {
      const pageById = new Map(pages.map(page => [page.id, page]));
      let parentId = candidate.parent_id || '';
      while (parentId) {
        if (parentId === ancestor.id) return true;
        parentId = pageById.get(parentId)?.parent_id || '';
      }

      return false;
    }

    function saveLibrary(payload, errorMessage) {
      fetch(API, {
        method: 'PUT',
        headers: { 'content-type': 'application/json', 'x-csrf-token': csrf },
        body: JSON.stringify(payload),
      }).then(r => {
        if (!r.ok) throw new Error(errorMessage);
        return r.json();
      }).then(data => {
        applyLibraryData(data);
        renderFrame();
      }).catch(error => alert(error.message));
    }

    function syncInputs() {
      const c = selected();
      document.getElementById('kind').textContent = c?.kind ?? '-';
      fields.name.value = c?.name ?? '';
      fields.slug.value = c?.path ?? '';
      fields.route.value = ['form', 'page'].includes(c?.kind) ? c?.route || routeFromPath(c?.path) : '';
      fields.routeName.value = ['form', 'page'].includes(c?.kind) ? c?.route_name || '' : '';
      fields.middleware.value = ['form', 'page'].includes(c?.kind) ? (Array.isArray(c?.middleware) ? c.middleware.join(', ') : '') : '';
      fields.parent.value = c?.kind === 'page' ? c?.parent_id || '' : '';
      fields.order.value = c?.kind === 'page' ? c?.order ?? 0 : '';
      fields.php.value = c?.php ?? '';
      fields.blade.value = c?.blade ?? '';
      fields.style.value = c?.style ?? '';
      fields.usage.value = c?.usage ?? '';
      const isContent = c?.kind === 'content';
      deleteButton.hidden = !c || isContent;
      stage.classList.toggle('content-mode', isContent);
      sourceSection('metadata').hidden = isContent;
      const pathField = document.querySelector('[data-meta="path"]');
      const routeField = document.querySelector('[data-meta="route"]');
      const routeNameField = document.querySelector('[data-meta="route-name"]');
      const middlewareField = document.querySelector('[data-meta="middleware"]');
      const parentField = document.querySelector('[data-meta="parent"]');
      const orderField = document.querySelector('[data-meta="order"]');
      pathField.hidden = ['content'].includes(c?.kind);
      routeField.hidden = !['form', 'page'].includes(c?.kind);
      routeNameField.hidden = !['form', 'page'].includes(c?.kind);
      middlewareField.hidden = !['form', 'page'].includes(c?.kind);
      parentField.hidden = c?.kind !== 'page';
      orderField.hidden = c?.kind !== 'page';
      pathField.querySelector('label').textContent = 'Path';
      pageParentOptions.innerHTML = byKind('page')
        .filter(page => page.id !== c?.id)
        .map(page => `<option value="${escapeHtml(page.id)}">${escapeHtml(page.name || page.id)}</option>`)
        .join('');
      contentIndex.hidden = !isContent;
      sourceSection('php').hidden = isContent || ['layout', 'style', 'snippet'].includes(c?.kind);
      sourceSection('blade').hidden = isContent || c?.kind === 'style';
      sourceSection('style').hidden = isContent || c?.kind === 'snippet';
      sourceSection('usage').hidden = isContent || c?.kind === 'style';
      updateHighlights();
      renderContentIndex();
      updateResizeHandles();
      requestAnimationFrame(fitSourceHeights);
    }

    function renderFrame() {
      const c = selected();
      if (!c) return;
      if (c.kind === 'content') return;
      frame.src = `${previewUrl(c)}?t=${Date.now()}`;
    }

    function previewUrl(c) {
      if (c.kind === 'content') return '/';
      if (c.kind === 'style') return '/';
      if (['form', 'page'].includes(c.kind)) return previewRoute(c.route || routeFromPath(c.path));
      return `/workbench/preview/${c.kind}/${c.id}`;
    }

    function previewRoute(route) {
      return String(route || '/').replace(/\{([^}/?]+)\??\}/g, (_match, key) => key);
    }

    function routeFromPath(path) {
      const id = idFromPath(path || '');

      return id ? `/${id}` : '/';
    }

    function updateSelected(source = null) {
      const c = selected();
      if (!c) return;
      if (c.kind === 'content') {
        return;
      }

      const previousId = c.id;
      const locationChanged = source === fields.slug;
      c.name = fields.name.value;
      c.slug = '';
      if (['form', 'page'].includes(c.kind)) c.path = fields.slug.value || c.path;
      if (['form', 'page'].includes(c.kind)) c.route = fields.route.value || '';
      if (['form', 'page'].includes(c.kind)) c.route_name = fields.routeName.value || '';
      if (['form', 'page'].includes(c.kind)) c.middleware = fields.middleware.value.split(',').map(s => s.trim()).filter(Boolean);
      if (['form', 'page'].includes(c.kind)) {
        c.id = idFromPath(c.path) || c.id;
        const namespace = c.kind === 'form' ? 'forms' : 'pages';
        c.usage = `<livewire:${namespace}::${c.id.replaceAll('/', '.')} />`;
        fields.usage.value = c.usage;
        selectedKey = artifactKey(c);
      } else if (!['content'].includes(c.kind)) {
        c.path = fields.slug.value || c.path;
        c.id = idFromPath(c.path) || c.id;
        if (locationChanged && c.kind === 'component') c.usage = `<livewire:${c.id.replaceAll('/', '.')} />`;
        if (locationChanged && c.kind === 'layout') c.usage = `<x-layouts::${c.id.replaceAll('/', '.')}></x-layouts::${c.id.replaceAll('/', '.')}>`;
        if (locationChanged && c.kind === 'snippet') c.usage = `<x-snippets::${c.id.replaceAll('/', '.')} />`;
        if (locationChanged && ['component', 'layout', 'snippet'].includes(c.kind)) fields.usage.value = c.usage;
        selectedKey = artifactKey(c);
      }
      if (c.kind === 'page') {
        c.parent_id = idFromPath(fields.parent.value) === c.id ? '' : idFromPath(fields.parent.value);
        c.order = Number(fields.order.value || 0);
      }
      c.php = ['layout', 'style', 'snippet'].includes(c.kind) ? '' : fields.php.value;
      c.blade = c.kind === 'style' ? '' : fields.blade.value;
      c.style = c.kind === 'snippet' ? '' : fields.style.value;
      c.usage = c.kind === 'style' ? '' : ['form', 'page'].includes(c.kind) ? c.usage : fields.usage.value;
      if (previousId !== c.id) c.previous_id = c.previous_id || previousId;
      updateHighlights();
      renderLists();
      scheduleSave(true);
    }

    Object.values(fields).forEach(input => input.addEventListener('input', () => updateSelected(input)));
    function idFromSlug(slug) {
      return String(slug ?? '').trim().toLowerCase().replace(/\\/g, '/').replace(/[^a-z0-9/-]/g, '-').replace(/\/+/g, '/').replace(/^-+|-+$/g, '').replace(/^\/|\/$/g, '');
    }
    function idFromPath(path) {
      return String(path ?? '').trim().toLowerCase()
        .replace(/\\/g, '/')
        .replace(/\.(blade\.php|css)$/g, '')
        .replace(/^(resources\/views\/(components|forms|layouts|pages|snippets)\/|resources\/css\/layouts\/|resources\/css\/)/, '')
        .replace(/[^a-z0-9/-]/g, '-')
        .replace(/\/+/g, '/')
        .replace(/^-+|-+$/g, '')
        .replace(/^\/|\/$/g, '');
    }
    function newArtifact(kind) {
      const id = `new-${crypto.randomUUID().slice(0, 8)}`;
      const component = id.replaceAll('/', '.');
      const item = {
        id, kind, name: kind === 'style' ? 'New style' : kind === 'page' ? 'New page' : kind === 'layout' ? 'New layout' : kind === 'form' ? 'New form' : kind === 'snippet' ? 'New snippet' : 'New component',
        slug: '',
        route: ['form', 'page'].includes(kind) ? `/${id}` : '',
        route_name: '',
        middleware: [],
        parent_id: '',
        order: kind === 'page' ? byKind('page').length + 1 : 0,
        path: kind === 'form' ? `resources/views/forms/${id}.blade.php` : kind === 'page' ? `resources/views/pages/${id}.blade.php` : kind === 'style' ? `resources/css/${id}.css` : kind === 'layout' ? `resources/views/layouts/${id}.blade.php` : kind === 'snippet' ? `resources/views/snippets/${id}.blade.php` : kind === 'component' ? `resources/views/components/${id}.blade.php` : '',
        php: kind === 'form' ? "use Livewire\\Attributes\\Layout;\nuse Livewire\\Attributes\\Validate;\nuse Livewire\\Component;\n\nnew #[Layout('layouts::base')] class extends Component {\n    #[Validate('required|string|max:255')]\n    public string $name = '';\n\n    public function save(): void\n    {\n        $this->validate();\n\n        $this->reset('name');\n    }\n};" : ['layout', 'style', 'snippet'].includes(kind) ? '' : "use Livewire\\Component;\n\nnew class extends Component {\n    //\n};",
        blade: kind === 'style' ? '' : kind === 'form' ? '<form wire:submit="save">\n  <label>\n    <span>Name</span>\n    <input type="text" wire:model="name">\n  </label>\n\n  @error(\'name\') <p>@{{ $message }}</p> @enderror\n\n  <button type="submit">Submit</button>\n</form>' : kind === 'component' ? '<div>New component</div>' : kind === 'snippet' ? '<div>New snippet</div>' : '@{{ $slot }}',
        style: kind === 'style' ? "/* Global styles */\n" : kind === 'form' ? "& {\n  display: grid;\n  gap: 18px;\n  max-width: 460px;\n  padding: 32px;\n  border: 1px solid #d4d4d8;\n  border-radius: 8px;\n  background: #ffffff;\n}\n\nlabel {\n  display: grid;\n  gap: 10px;\n  color: #27272a;\n  font-weight: 700;\n}\n\ninput {\n  width: 100%;\n  padding: 13px 14px;\n  border: 1px solid #a1a1aa;\n  border-radius: 6px;\n}\n\nbutton {\n  justify-self: start;\n  padding: 12px 18px;\n  border-radius: 6px;\n  background: #4338ca;\n  color: #ffffff;\n  font-weight: 800;\n}\n\np {\n  margin: -6px 0 0;\n  color: #b91c1c;\n}" : '',
        usage: kind === 'component' ? `<livewire:${component} />` : kind === 'form' ? `<livewire:forms::${component} />` : kind === 'layout' ? `<x-layouts::${component}></x-layouts::${component}>` : kind === 'page' ? `<livewire:pages::${component} />` : kind === 'snippet' ? `<x-snippets::${component} />` : '',
      };
      library.push(item);
      selectedKey = artifactKey(item);
      renderLists(); syncInputs(); save(true);
    }

    function addContentRow() {
      const model = selectedContentModel();
      if (!model) {
        addContentModel();
        return;
      }
      contentRows.push({
        id: `new-${crypto.randomUUID().slice(0, 8)}`,
        kind: 'content',
        model: model.model,
        name: `New ${model.name.toLowerCase()}`,
        title: `New ${model.name.toLowerCase()}`,
        icon: String(contentRows.length + 1).padStart(2, '0'),
        summary: 'Describe this content row.',
        position: contentRows.length + 1,
        is_published: true,
      });
      contentData[selectedContentId] = contentRows;
      editingContentKey = contentRows[contentRows.length - 1].id;
      selectedKey = `content:${selectedContentId}`;
      refreshContentModel();
      renderLists();
      syncInputs();
      saveContent(true);
    }

    function addContentModel() {
      const name = prompt('Content model name');
      if (!name?.trim()) return;
      fetch('/api/content/models', {
        method: 'POST',
        headers: { 'content-type': 'application/json', 'x-csrf-token': csrf, accept: 'application/json' },
        body: JSON.stringify({ name: name.trim() }),
      }).then(r => {
        if (!r.ok) throw new Error('Could not create content model');
        return r.json();
      }).then(data => {
        contentModels = data.models ?? contentModels;
        contentData = data.data ?? contentData;
        const created = contentModels.find(model => model.name.toLowerCase() === name.trim().replace(/[^A-Za-z0-9]+/g, '').toLowerCase())
          ?? contentModels[contentModels.length - 1];
        selectedContentId = created?.id ?? selectedContentId;
        contentRows = contentData[selectedContentId] ?? [];
        selectedKey = `content:${selectedContentId}`;
        library = [...byKind('style'), ...byKind('layout'), ...byKind('page'), ...byKind('snippet'), ...byKind('component'), ...byKind('form'), ...contentModels];
        renderLists(); syncInputs();
      }).catch(error => alert(error.message));
    }

    document.getElementById('btn-new-style').onclick = () => newArtifact('style');
    document.getElementById('btn-new-component').onclick = () => newArtifact('component');
    document.getElementById('btn-new-form').onclick = () => newArtifact('form');
    document.getElementById('btn-new-layout').onclick = () => newArtifact('layout');
    document.getElementById('btn-new-page').onclick = () => newArtifact('page');
    document.getElementById('btn-new-snippet').onclick = () => newArtifact('snippet');
    document.getElementById('btn-new-content').onclick = () => addContentModel();
    document.getElementById('btn-add-content-row').onclick = () => addContentRow();
    contentSearch.addEventListener('input', () => { contentFilter = contentSearch.value; renderContentIndex(); });
    document.getElementById('btn-reload').onclick = () => load();
    document.getElementById('btn-open').onclick = () => { const c = selected(); if (c) window.open(previewUrl(c), '_blank'); };
    deleteButton.onclick = () => deleteSelectedArtifact();

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
    load();
  </script>
</body>
</html>
