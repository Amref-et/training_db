@extends('layouts.admin')

@section('eyebrow', 'CMS')
@section('title', $page->exists ? 'Edit Page' : 'Create Page')
@section('subtitle', 'Dynamic website content managed through the CMS.')

@section('content')
<div class="panel p-4">
    <form method="POST" action="{{ $page->exists ? route('admin.pages.update', $page) : route('admin.pages.store') }}" class="row g-3">
        @csrf
        @if($page->exists) @method('PUT') @endif
        <div class="col-md-6"><label class="form-label">Title</label><input class="form-control" type="text" name="title" value="{{ old('title', $page->title) }}"></div>
        <div class="col-md-6"><label class="form-label">Slug</label><input class="form-control" type="text" name="slug" value="{{ old('slug', $page->slug) }}"></div>
        <div class="col-md-6"><label class="form-label">Meta Title</label><input class="form-control" type="text" name="meta_title" value="{{ old('meta_title', $page->meta_title) }}"></div>
        <div class="col-md-6"><label class="form-label">Status</label><select name="status" class="form-select"><option value="draft" @selected(old('status', $page->status ?: 'draft') === 'draft')>Draft</option><option value="published" @selected(old('status', $page->status) === 'published')>Published</option></select></div>
        <div class="col-12"><label class="form-label">Summary</label><textarea class="form-control js-cms-tinymce" name="summary" rows="3">{{ old('summary', $page->summary) }}</textarea></div>
        <div class="col-12">
            <hr class="my-2">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <label class="form-label mb-1">Page Sections</label>
                    <div class="form-text">A page contains multiple ordered sections, and each section contains multiple ordered blocks.</div>
                </div>
                <button type="button" class="btn btn-outline-dark" id="add-section-button">Add Section</button>
            </div>
            <input type="hidden" name="sections_payload" id="sections_payload" value="{{ old('sections_payload') }}">
            <div id="sections-builder" class="d-grid gap-4 mt-3"></div>
            <div id="sections-empty-state" class="border rounded-4 p-4 text-secondary bg-light mt-3">No sections yet. Add a section, then add blocks inside that section.</div>
        </div>
        <div class="col-12"><label class="form-label">Legacy Body</label><textarea class="form-control js-cms-tinymce" name="body" rows="8">{{ old('body', $page->body) }}</textarea><div class="form-text">Optional fallback HTML for older pages. The public website renders sections and blocks first.</div></div>
        <div class="col-md-6">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="show_page_heading" value="1" id="show_page_heading" @checked(old('show_page_heading', $page->show_page_heading ?? true))>
                <label class="form-check-label" for="show_page_heading">Show page heading section</label>
                <div class="form-text">Controls the top hero/title block with the page title and summary.</div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_homepage" value="1" id="is_homepage" @checked(old('is_homepage', $page->is_homepage))>
                <label class="form-check-label" for="is_homepage">Set as homepage</label>
            </div>
        </div>
        <div class="col-12 d-flex gap-2"><button class="btn btn-dark" type="submit">Save Page</button><a href="{{ route('admin.pages.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
    </form>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js" referrerpolicy="origin"></script>
<script>
    const blockDefinitions = @json($blockDefinitions);
    const sectionStyles = @json($sectionStyles);
    const oldSections = @json(old('sections'));
    const initialSections = Array.isArray(oldSections)
        ? oldSections
        : @json(old('sections_payload') ? json_decode(old('sections_payload'), true) : $formSections);
    const sectionsBuilder = document.getElementById('sections-builder');
    const sectionsPayload = document.getElementById('sections_payload');
    const addSectionButton = document.getElementById('add-section-button');
    const emptyState = document.getElementById('sections-empty-state');
    const pageForm = sectionsBuilder.closest('form');
    let sections = Array.isArray(initialSections) ? initialSections : [];

    const cmsEditorPlugins = [
        'advlist', 'anchor', 'autolink', 'autoresize', 'autosave', 'charmap', 'code', 'codesample',
        'directionality', 'emoticons', 'fullscreen', 'help', 'hr', 'image', 'insertdatetime', 'link',
        'lists', 'media', 'nonbreaking', 'pagebreak', 'preview', 'quickbars', 'save', 'searchreplace',
        'table', 'visualblocks', 'visualchars', 'wordcount'
    ];

    const syncCmsEditorValue = (editor) => {
        editor.save();

        const textarea = editor.getElement();

        if (!textarea) {
            return;
        }

        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    };

    const ensureTinyMceId = (textarea) => {
        if (!textarea.id) {
            textarea.id = `cms-editor-${Math.random().toString(36).slice(2)}`;
        }

        return textarea.id;
    };

    const initTinyMceEditors = (root = document) => {
        if (!window.tinymce) {
            return;
        }

        root.querySelectorAll('textarea.js-cms-tinymce, textarea.js-cms-builder-tinymce').forEach((textarea) => {
            const id = ensureTinyMceId(textarea);

            if (window.tinymce.get(id)) {
                return;
            }

            tinymce.init({
                target: textarea,
                height: 320,
                menubar: true,
                toolbar_mode: 'sliding',
                branding: false,
                promotion: false,
                convert_urls: false,
                relative_urls: false,
                remove_script_host: false,
                plugins: cmsEditorPlugins,
                toolbar: 'undo redo restoredraft | blocks fontfamily fontsize | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | outdent indent | bullist numlist | link image media table charmap emoticons hr pagebreak nonbreaking insertdatetime | ltr rtl | removeformat | searchreplace visualblocks visualchars preview fullscreen code',
                contextmenu: 'undo redo | link image table',
                quickbars_selection_toolbar: 'bold italic underline | blocks | quicklink blockquote',
                quickbars_insert_toolbar: 'quickimage quicktable',
                image_title: true,
                automatic_uploads: false,
                paste_data_images: false,
                browser_spellcheck: true,
                setup: (editor) => {
                    editor.on('change input undo redo keyup', () => syncCmsEditorValue(editor));
                },
            });
        });
    };

    const destroyBuilderTinyMceEditors = () => {
        if (!window.tinymce) {
            return;
        }

        document.querySelectorAll('textarea.js-cms-builder-tinymce').forEach((textarea) => {
            const editor = textarea.id ? window.tinymce.get(textarea.id) : null;

            if (editor) {
                editor.remove();
            }
        });
    };

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const fieldHelp = (field) => {
        let help = '';

        if (field.type === 'stats-items') {
            help += '<div class="form-text">Use one item per line in the format <code>Label | Value</code>.</div>';
        }

        if (field.type === 'list-items') {
            help += '<div class="form-text">Use one line per list item.</div>';
        }

        if (field.type === 'gallery-items') {
            help += '<div class="form-text">Use one image per line in the format <code>Image URL | Caption</code>.</div>';
        }

        if (field.help) {
            help += `<div class="form-text">${escapeHtml(field.help)}</div>`;
        }

        return help;
    };

    const defaultValueForField = (field) => {
        if (field.default !== undefined) {
            return field.default;
        }

        if (field.type === 'checkbox-group') {
            return [];
        }

        if (field.type === 'select' && Array.isArray(field.choices) && field.choices.length > 0) {
            return field.choices[0].value;
        }

        return '';
    };

    const defaultSection = () => ({
        title: '',
        anchor: '',
        intro: '',
        style: 'default',
        blocks: [],
    });

    const defaultBlock = (type) => {
        const definition = blockDefinitions[type];
        const block = { type };

        (definition?.fields || []).forEach((field) => {
            const value = defaultValueForField(field);
            block[field.name] = Array.isArray(value) ? [...value] : value;
        });

        return block;
    };

    const sectionFieldMarkup = (field, value, sectionIndex) => {
        const inputId = `section-${sectionIndex}-${field.name}-${Math.random().toString(36).slice(2)}`;
        const inputName = `sections[${sectionIndex}][${field.name}]`;

        if (field.type === 'textarea') {
            return `
                <div class="col-12">
                    <label class="form-label" for="${inputId}">${escapeHtml(field.label)}</label>
                    <textarea class="form-control section-field-input js-cms-builder-tinymce" name="${inputName}" data-field="${escapeHtml(field.name)}" id="${inputId}" rows="4" placeholder="${escapeHtml(field.placeholder || '')}">${escapeHtml(value)}</textarea>
                </div>
            `;
        }

        if (field.type === 'select') {
            const options = (field.choices || []).map((choice) => `<option value="${escapeHtml(choice.value)}" ${String(value) === String(choice.value) ? 'selected' : ''}>${escapeHtml(choice.label)}</option>`).join('');

            return `
                <div class="col-md-4">
                    <label class="form-label" for="${inputId}">${escapeHtml(field.label)}</label>
                    <select class="form-select section-field-input" name="${inputName}" data-field="${escapeHtml(field.name)}" id="${inputId}">${options}</select>
                </div>
            `;
        }

        return `
            <div class="col-md-4">
                <label class="form-label" for="${inputId}">${escapeHtml(field.label)}</label>
                <input class="form-control section-field-input" name="${inputName}" data-field="${escapeHtml(field.name)}" id="${inputId}" type="text" value="${escapeHtml(value)}" placeholder="${escapeHtml(field.placeholder || '')}">
            </div>
        `;
    };

    const blockFieldMarkup = (field, value, sectionIndex, blockIndex) => {
        const inputId = `block-${sectionIndex}-${blockIndex}-${field.name}-${Math.random().toString(36).slice(2)}`;
        const inputName = `sections[${sectionIndex}][blocks][${blockIndex}][${field.name}]`;

        if (field.type === 'checkbox-group') {
            const selectedValues = Array.isArray(value) ? value.map(String) : [];
            const options = (field.choices || []).map((choice, choiceIndex) => {
                const checkboxId = `${inputId}-${choiceIndex}`;

                return `
                    <div class="col-md-6">
                        <label class="border rounded-4 p-3 bg-white d-flex gap-2 h-100" for="${checkboxId}">
                            <input class="form-check-input mt-1 block-field-checkbox" type="checkbox" name="${inputName}[]" data-field="${escapeHtml(field.name)}" id="${checkboxId}" value="${escapeHtml(choice.value)}" ${selectedValues.includes(String(choice.value)) ? 'checked' : ''}>
                            <span>
                                <span class="fw-semibold d-block">${escapeHtml(choice.label)}</span>
                            </span>
                        </label>
                    </div>
                `;
            }).join('');

            return `
                <div class="col-12">
                    <label class="form-label d-block">${escapeHtml(field.label)}</label>
                    <div class="row g-2">${options}</div>
                    ${fieldHelp(field)}
                </div>
            `;
        }

        if (field.type === 'textarea') {
            return `
                <div class="col-12">
                    <label class="form-label" for="${inputId}">${escapeHtml(field.label)}</label>
                    <textarea class="form-control block-field-input js-cms-builder-tinymce" name="${inputName}" data-field="${escapeHtml(field.name)}" id="${inputId}" rows="5" placeholder="${escapeHtml(field.placeholder || '')}">${escapeHtml(value)}</textarea>
                    ${fieldHelp(field)}
                </div>
            `;
        }

        if (field.type === 'stats-items' || field.type === 'list-items' || field.type === 'gallery-items') {
            return `
                <div class="col-12">
                    <label class="form-label" for="${inputId}">${escapeHtml(field.label)}</label>
                    <textarea class="form-control block-field-input js-cms-builder-tinymce" name="${inputName}" data-field="${escapeHtml(field.name)}" id="${inputId}" rows="4" placeholder="${escapeHtml(field.placeholder || '')}">${escapeHtml(value)}</textarea>
                    ${fieldHelp(field)}
                </div>
            `;
        }

        if (field.type === 'select') {
            const options = (field.choices || []).map((choice) => `<option value="${escapeHtml(choice.value)}" ${String(value) === String(choice.value) ? 'selected' : ''}>${escapeHtml(choice.label)}</option>`).join('');

            return `
                <div class="col-md-4">
                    <label class="form-label" for="${inputId}">${escapeHtml(field.label)}</label>
                    <select class="form-select block-field-input" name="${inputName}" data-field="${escapeHtml(field.name)}" id="${inputId}">${options}</select>
                    ${fieldHelp(field)}
                </div>
            `;
        }

        return `
            <div class="col-md-6">
                <label class="form-label" for="${inputId}">${escapeHtml(field.label)}</label>
                <input class="form-control block-field-input" name="${inputName}" data-field="${escapeHtml(field.name)}" id="${inputId}" type="${field.type === 'url' ? 'url' : 'text'}" value="${escapeHtml(value)}" placeholder="${escapeHtml(field.placeholder || '')}">
                ${fieldHelp(field)}
            </div>
        `;
    };

    const sectionFields = [
        { name: 'title', label: 'Section Title', type: 'text', placeholder: 'Program Overview' },
        { name: 'anchor', label: 'Anchor ID', type: 'text', placeholder: 'program-overview' },
        { name: 'style', label: 'Section Style', type: 'select', choices: sectionStyles },
        { name: 'intro', label: 'Section Intro', type: 'textarea', placeholder: 'Optional introductory copy for this section.' },
    ];

    const renderSections = () => {
        destroyBuilderTinyMceEditors();
        sectionsBuilder.innerHTML = '';
        emptyState.classList.toggle('d-none', sections.length > 0);

        sections.forEach((section, sectionIndex) => {
            const sectionCard = document.createElement('div');
            sectionCard.className = 'border rounded-4 p-3 bg-white shadow-sm';
            sectionCard.dataset.sectionIndex = sectionIndex;

            const sectionFieldsMarkup = sectionFields
                .map((field) => sectionFieldMarkup(field, section[field.name] ?? (field.name === 'style' ? 'default' : ''), sectionIndex))
                .join('');

            const blockCards = (section.blocks || []).map((block, blockIndex) => {
                const definition = blockDefinitions[block.type] || blockDefinitions.hero;
                const fieldMarkupHtml = definition.fields
                    .map((field) => blockFieldMarkup(field, block[field.name] ?? defaultValueForField(field), sectionIndex, blockIndex))
                    .join('');

                return `
                    <div class="border rounded-4 p-3 bg-light" data-block-index="${blockIndex}">
                        <input type="hidden" name="sections[${sectionIndex}][blocks][${blockIndex}][type]" value="${escapeHtml(block.type)}">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                            <div>
                                <div class="small text-uppercase text-secondary">Block ${blockIndex + 1}</div>
                                <div class="fw-semibold">${escapeHtml(definition.label)}</div>
                                <div class="text-secondary small">${escapeHtml(definition.description || '')}</div>
                            </div>
                            <div class="d-flex gap-2 flex-wrap">
                                <select class="form-select form-select-sm block-type-select" style="min-width: 210px;">
                                    ${Object.entries(blockDefinitions).map(([type, typeDefinition]) => `<option value="${escapeHtml(type)}" ${type === block.type ? 'selected' : ''}>${escapeHtml(typeDefinition.label)}</option>`).join('')}
                                </select>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-action="block-up" ${blockIndex === 0 ? 'disabled' : ''}>Up</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-action="block-down" ${blockIndex === (section.blocks || []).length - 1 ? 'disabled' : ''}>Down</button>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-action="block-remove">Remove</button>
                            </div>
                        </div>
                        <div class="row g-3">${fieldMarkupHtml}</div>
                    </div>
                `;
            }).join('');

            sectionCard.innerHTML = `
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                    <div>
                        <div class="small text-uppercase text-secondary">Section ${sectionIndex + 1}</div>
                        <div class="fw-semibold">${escapeHtml(section.title || 'Untitled Section')}</div>
                        <div class="text-secondary small">Blocks in this section render together on the page.</div>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-action="section-up" ${sectionIndex === 0 ? 'disabled' : ''}>Up</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-action="section-down" ${sectionIndex === sections.length - 1 ? 'disabled' : ''}>Down</button>
                        <button type="button" class="btn btn-sm btn-outline-danger" data-action="section-remove">Remove Section</button>
                    </div>
                </div>
                <div class="row g-3 mb-4">${sectionFieldsMarkup}</div>
                <div class="border-top pt-3">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                        <div>
                            <div class="fw-semibold">Blocks</div>
                            <div class="text-secondary small">Assign multiple blocks inside this section.</div>
                        </div>
                        <div class="d-flex gap-2 flex-wrap align-items-center">
                            <select class="form-select form-select-sm section-block-type-select" style="min-width: 210px;">
                                ${Object.entries(blockDefinitions).map(([type, definition]) => `<option value="${escapeHtml(type)}">${escapeHtml(definition.label)}</option>`).join('')}
                            </select>
                            <button type="button" class="btn btn-sm btn-outline-dark" data-action="add-block">Add Block</button>
                        </div>
                    </div>
                    <div class="d-grid gap-3 section-blocks">${blockCards}</div>
                    <div class="text-secondary small mt-3 ${((section.blocks || []).length > 0) ? 'd-none' : ''}" data-empty-blocks>No blocks in this section yet.</div>
                </div>
            `;

            sectionsBuilder.appendChild(sectionCard);
        });

        syncPayload();
        initTinyMceEditors(sectionsBuilder);
    };

    const syncPayload = () => {
        sectionsPayload.value = JSON.stringify(sections);
    };

    addSectionButton.addEventListener('click', () => {
        sections.push(defaultSection());
        renderSections();
    });

    sectionsBuilder.addEventListener('input', (event) => {
        const sectionCard = event.target.closest('[data-section-index]');

        if (!sectionCard) {
            return;
        }

        const sectionIndex = Number(sectionCard.dataset.sectionIndex);
        const blockCard = event.target.closest('[data-block-index]');

        if (event.target.classList.contains('section-field-input')) {
            sections[sectionIndex][event.target.dataset.field] = event.target.value;
            syncPayload();
            return;
        }

        if (event.target.classList.contains('block-field-input') && blockCard) {
            const blockIndex = Number(blockCard.dataset.blockIndex);
            sections[sectionIndex].blocks[blockIndex][event.target.dataset.field] = event.target.value;
            syncPayload();
        }
    });

    sectionsBuilder.addEventListener('change', (event) => {
        const sectionCard = event.target.closest('[data-section-index]');

        if (!sectionCard) {
            return;
        }

        const sectionIndex = Number(sectionCard.dataset.sectionIndex);
        const blockCard = event.target.closest('[data-block-index]');

        if (event.target.classList.contains('section-field-input')) {
            sections[sectionIndex][event.target.dataset.field] = event.target.value;
            syncPayload();
            return;
        }

        if (event.target.classList.contains('block-type-select') && blockCard) {
            const blockIndex = Number(blockCard.dataset.blockIndex);
            sections[sectionIndex].blocks[blockIndex] = defaultBlock(event.target.value);
            renderSections();
            return;
        }

        if (event.target.classList.contains('block-field-checkbox') && blockCard) {
            const blockIndex = Number(blockCard.dataset.blockIndex);
            const field = event.target.dataset.field;
            sections[sectionIndex].blocks[blockIndex][field] = Array.from(
                blockCard.querySelectorAll(`.block-field-checkbox[data-field="${field}"]:checked`)
            ).map((input) => input.value);
            syncPayload();
            return;
        }

        if (event.target.classList.contains('block-field-input') && blockCard) {
            const blockIndex = Number(blockCard.dataset.blockIndex);
            sections[sectionIndex].blocks[blockIndex][event.target.dataset.field] = event.target.value;
            syncPayload();
        }
    });

    sectionsBuilder.addEventListener('click', (event) => {
        const action = event.target.dataset.action;
        const sectionCard = event.target.closest('[data-section-index]');

        if (!action || !sectionCard) {
            return;
        }

        const sectionIndex = Number(sectionCard.dataset.sectionIndex);
        const blockCard = event.target.closest('[data-block-index]');

        if (action === 'section-remove') {
            sections.splice(sectionIndex, 1);
            renderSections();
            return;
        }

        if (action === 'section-up' && sectionIndex > 0) {
            [sections[sectionIndex - 1], sections[sectionIndex]] = [sections[sectionIndex], sections[sectionIndex - 1]];
            renderSections();
            return;
        }

        if (action === 'section-down' && sectionIndex < sections.length - 1) {
            [sections[sectionIndex + 1], sections[sectionIndex]] = [sections[sectionIndex], sections[sectionIndex + 1]];
            renderSections();
            return;
        }

        if (action === 'add-block') {
            const typeSelect = sectionCard.querySelector('.section-block-type-select');
            sections[sectionIndex].blocks = Array.isArray(sections[sectionIndex].blocks) ? sections[sectionIndex].blocks : [];
            sections[sectionIndex].blocks.push(defaultBlock(typeSelect.value));
            renderSections();
            return;
        }

        if (!blockCard) {
            return;
        }

        const blockIndex = Number(blockCard.dataset.blockIndex);

        if (action === 'block-remove') {
            sections[sectionIndex].blocks.splice(blockIndex, 1);
            renderSections();
            return;
        }

        if (action === 'block-up' && blockIndex > 0) {
            [sections[sectionIndex].blocks[blockIndex - 1], sections[sectionIndex].blocks[blockIndex]] = [sections[sectionIndex].blocks[blockIndex], sections[sectionIndex].blocks[blockIndex - 1]];
            renderSections();
            return;
        }

        if (action === 'block-down' && blockIndex < sections[sectionIndex].blocks.length - 1) {
            [sections[sectionIndex].blocks[blockIndex + 1], sections[sectionIndex].blocks[blockIndex]] = [sections[sectionIndex].blocks[blockIndex], sections[sectionIndex].blocks[blockIndex + 1]];
            renderSections();
        }
    });

    pageForm.addEventListener('submit', () => {
        if (window.tinymce) {
            tinymce.triggerSave();
        }

        syncPayload();
    });

    initTinyMceEditors(document);
    renderSections();
</script>
@endsection









