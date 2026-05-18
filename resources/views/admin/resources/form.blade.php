@extends('layouts.admin')

@section('eyebrow', 'Records')
@section('title', ($record ? 'Edit ' : 'Create ').$config['singular'])
@section('subtitle', 'Update the fields below and save.')

@php($hasFileField = collect($config['fields'])->contains(fn ($field) => ($field['type'] ?? 'text') === 'file'))
@php($hasTinyMceField = collect($config['fields'])->contains(fn ($field) => in_array(($field['type'] ?? 'text'), ['textarea', 'tinymce'], true)))
@php($hasDobAgeFields = collect($config['fields'])->pluck('name')->intersect(['date_of_birth', 'age'])->count() === 2)
@php($hasParticipantIdPreviewFields = collect($config['fields'])->pluck('name')->intersect(['first_name', 'father_name', 'grandfather_name', 'date_of_birth', 'mobile_phone'])->count() === 5)
@php($hasMultiSelectField = collect($config['fields'])->contains(fn ($field) => ($field['type'] ?? 'text') === 'multiselect'))
@php($hasRepeaterField = collect($config['fields'])->contains(fn ($field) => ($field['type'] ?? 'text') === 'repeater'))
@php($hasHierarchySelectors = collect($config['fields'])->pluck('name')->intersect(['region_id', 'zone_id', 'woreda_id'])->isNotEmpty())
@php($searchableSelectNames = $resource === 'participants' ? ['organization_id', 'project_subawardee_id'] : ['region_id', 'zone_id', 'woreda_id', 'organization_id', 'project_subawardee_id'])
@php($hasSearchableSelect = collect($config['fields'])->contains(fn ($field) => ($field['type'] ?? 'text') === 'select' && (in_array($field['name'] ?? '', $searchableSelectNames, true) || !empty($field['allow_custom']))))
@php($hasTrainingEventOrganizerFields = collect($config['fields'])->pluck('name')->intersect(['training_organizer_id', 'organizer_type', 'project_subawardee_id'])->count() === 3)
@php($fieldGroups = collect($config['fields'])->groupBy(fn ($field) => $field['tab'] ?? 'General'))
@php($useFieldTabs = $fieldGroups->count() > 1)
@php($fieldToTab = collect($config['fields'])->mapWithKeys(fn ($field) => [($field['name'] ?? '') => ($field['tab'] ?? 'General')]))
@php($errorBaseFields = collect($errors->keys())->map(fn ($key) => \Illuminate\Support\Str::before((string) $key, '.')))
@php($errorTab = $errorBaseFields->map(fn ($fieldName) => $fieldToTab[$fieldName] ?? null)->first(fn ($tabName) => !empty($tabName)))
@php($activeFieldTab = $errorTab ?: (string) $fieldGroups->keys()->first())

@section('head')
    <style>
        .required-mark {
            color: #dc3545;
            font-weight: 700;
            margin-left: .2rem;
        }
    </style>
    @if($hasSearchableSelect)
        <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
        <style>
            .ts-wrapper.single .ts-control {
                min-height: calc(2.25rem + 2px);
                border-radius: .375rem;
            }
            .ts-dropdown .option {
                white-space: normal;
            }
        </style>
    @endif
@endsection

@section('content')
<div class="panel p-4">
    <form id="managed-resource-form" method="POST" action="{{ $record ? route('admin.'.$config['path'].'.update', $record->getKey()) : route('admin.'.$config['path'].'.store') }}" @if($hasFileField) enctype="multipart/form-data" @endif>
        @csrf
        @if($record) @method('PUT') @endif

        @if($hasParticipantIdPreviewFields)
            <div class="d-flex justify-content-end mb-3">
                <div class="w-100" style="max-width: 360px;">
                    <div class="d-flex align-items-center gap-2">
                        <label for="participant-code-preview" class="form-label mb-0 text-nowrap">Participant ID</label>
                        <input type="text" id="participant-code-preview" class="form-control" value="{{ $record?->participant_code }}" readonly>
                    </div>
                    <div class="form-text">Auto-generated on save using initials + birth year/month + last 4 phone digits.</div>
                </div>
            </div>
        @endif

        @if($useFieldTabs)
            <ul class="nav nav-tabs mb-3" role="tablist">
                @foreach($fieldGroups as $tabName => $tabFields)
                    @php($tabId = 'resource-tab-'.\Illuminate\Support\Str::slug((string) $tabName).'-'.$loop->index)
                    <li class="nav-item" role="presentation">
                        <button
                            class="nav-link {{ $activeFieldTab === $tabName ? 'active' : '' }}"
                            id="{{ $tabId }}-tab"
                            data-bs-toggle="tab"
                            data-bs-target="#{{ $tabId }}"
                            type="button"
                            role="tab"
                            aria-controls="{{ $tabId }}"
                            aria-selected="{{ $activeFieldTab === $tabName ? 'true' : 'false' }}"
                        >
                            {{ $tabName }}
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="{{ $useFieldTabs ? 'tab-content' : '' }}">
            @foreach($fieldGroups as $tabName => $tabFields)
                @php($tabId = 'resource-tab-'.\Illuminate\Support\Str::slug((string) $tabName).'-'.$loop->index)
                <div
                    @if($useFieldTabs)
                        class="tab-pane fade {{ $activeFieldTab === $tabName ? 'show active' : '' }}"
                        id="{{ $tabId }}"
                        role="tabpanel"
                        aria-labelledby="{{ $tabId }}-tab"
                    @endif
                >
                    <div class="row g-3">
                        @foreach($tabFields as $field)
                            @php($name = $field['name'])
                            @php($type = $field['type'] ?? 'text')
                            @php($isRequired = (bool) ($field['required'] ?? false))
                            @php($defaultValue = $record ? data_get($record, $name) : ($field['default'] ?? null))
                            @if($type === 'repeater')
                                @php($defaultValue = $record ? data_get($record, $name, collect())->pluck($field['column'] ?? 'name')->all() : [])
                            @endif
                            @php($fallbackValue = $defaultValue ?? (in_array($type, ['multiselect', 'repeater'], true) ? [] : ''))
                            @php($value = old($name, $fallbackValue))
                            @if($value instanceof \Illuminate\Support\Carbon)
                                @php($value = $type === 'date' ? $value->format('Y-m-d') : $value->toDateTimeString())
                            @endif
                            @php($fieldColumnClass = in_array($type, ['textarea', 'tinymce'], true) ? 'col-12' : 'col-md-6')
                            @if($resource === 'participants' && $name === 'organization_id')
                                @php($fieldColumnClass = 'col-12')
                            @endif
                            <div class="{{ $fieldColumnClass }}">
                                <label class="form-label">
                                    {{ $field['label'] }}
                                    @if($isRequired)<span class="required-mark" aria-hidden="true">*</span>@endif
                                </label>
                                @if(in_array($type, ['textarea', 'tinymce'], true))
                                    <textarea name="{{ $name }}" rows="8" class="form-control js-tinymce" @if($isRequired) data-required="1" @endif aria-required="{{ $isRequired ? 'true' : 'false' }}">{{ $value }}</textarea>
                                @elseif($type === 'multiselect')
                                    @php($selectedValues = collect(is_array($value) ? $value : [])->map(fn ($item) => (string) $item)->all())
                                    @php($selectId = 'multiselect-'.preg_replace('/[^a-z0-9\-]+/i', '-', $name))
                                    <input
                                        type="text"
                                        class="form-control mb-2 js-multiselect-search"
                                        data-target="{{ $selectId }}"
                                        placeholder="Search {{ strtolower($field['label']) }}"
                                        autocomplete="off"
                                    >
                                    <div id="{{ $selectId }}-selected" class="d-flex flex-wrap gap-2 mb-2 js-multiselect-selected" data-target="{{ $selectId }}"></div>
                                    <select id="{{ $selectId }}" name="{{ $name }}[]" class="form-select js-filterable-multiselect" multiple size="8" @required($isRequired) aria-required="{{ $isRequired ? 'true' : 'false' }}">
                                        @foreach($fieldOptions[$name] ?? [] as $option)
                                            <option value="{{ $option['value'] }}" @selected(in_array((string) $option['value'], $selectedValues, true))>{{ $option['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">Type to filter list, then click participants to toggle selection.</div>
                                @elseif($type === 'repeater')
                                    @php($repeaterValues = collect(is_array($value) ? $value : [])->map(fn ($item) => trim((string) $item))->filter()->values())
                                    @if($repeaterValues->isEmpty())
                                        @php($repeaterValues = collect(['']))
                                    @endif
                                    @php($repeaterId = 'repeater-'.preg_replace('/[^a-z0-9\-]+/i', '-', $name))
                                    <div id="{{ $repeaterId }}" class="js-repeater" data-name="{{ $name }}" data-item-label="{{ $field['item_label'] ?? 'Item' }}" data-required="{{ $isRequired ? '1' : '0' }}">
                                        <div class="d-flex flex-column gap-2 js-repeater-list">
                                            @foreach($repeaterValues as $repeaterValue)
                                                <div class="input-group js-repeater-row">
                                                    <input type="text" name="{{ $name }}[]" value="{{ $repeaterValue }}" class="form-control" placeholder="{{ $field['item_label'] ?? 'Item' }} name" @required($isRequired) aria-required="{{ $isRequired ? 'true' : 'false' }}">
                                                    <button type="button" class="btn btn-outline-danger js-repeater-remove">Remove</button>
                                                </div>
                                            @endforeach
                                        </div>
                                        <button type="button" class="btn btn-outline-secondary btn-sm mt-2 js-repeater-add">{{ $field['add_button'] ?? 'Add Row' }}</button>
                                    </div>
                                    <div class="form-text">Add one row per {{ strtolower($field['item_label'] ?? 'item') }}. Blank rows are ignored.</div>
                                @elseif($type === 'checkbox')
                                    @php($checkboxId = 'checkbox-'.preg_replace('/[^a-z0-9\-]+/i', '-', $name))
                                    @php($isChecked = filter_var($value, FILTER_VALIDATE_BOOLEAN))
                                    <input type="hidden" name="{{ $name }}" value="0">
                                    <div class="form-check mt-2">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            name="{{ $name }}"
                                            value="1"
                                            id="{{ $checkboxId }}"
                                            @checked($isChecked)
                                            aria-required="{{ $isRequired ? 'true' : 'false' }}"
                                        >
                                        <label class="form-check-label" for="{{ $checkboxId }}">{{ $field['checkbox_label'] ?? $field['label'] }}</label>
                                    </div>
                                @elseif($type === 'select')
                                    @php($selectId = 'select-'.preg_replace('/[^a-z0-9\-]+/i', '-', $name))
                                    @php($allowsCustomSelect = (bool) ($field['allow_custom'] ?? false))
                                    @php($isSearchableSelect = $allowsCustomSelect || in_array($name, $searchableSelectNames, true))
                                    @php($isRemoteSearchableSelect = $resource === 'participants' && $name === 'organization_id')
                                    @php($remoteOptionsUrl = $isRemoteSearchableSelect ? route('admin.participants.organization-options') : ($resource === 'participants' && $name === 'zone_id' ? route('admin.participants.zone-options') : ($resource === 'participants' && $name === 'woreda_id' ? route('admin.participants.woreda-options') : null)))
                                    @php($hasSelectedCustomOption = $allowsCustomSelect && $value !== '' && !collect($fieldOptions[$name] ?? [])->contains(fn ($option) => (string) $option['value'] === (string) $value))
                                    <select
                                        id="{{ $selectId }}"
                                        name="{{ $name }}"
                                        class="form-select {{ $isSearchableSelect ? 'js-searchable-select' : '' }} {{ $allowsCustomSelect ? 'js-creatable-select' : '' }} {{ $isRemoteSearchableSelect ? 'js-remote-searchable-select' : '' }}"
                                        @required($isRequired)
                                        aria-required="{{ $isRequired ? 'true' : 'false' }}"
                                        @if($remoteOptionsUrl) data-remote-url="{{ $remoteOptionsUrl }}" @endif
                                    >
                                        <option value="">Select {{ strtolower($field['label']) }}</option>
                                        @foreach($fieldOptions[$name] ?? [] as $option)
                                            <option
                                                value="{{ $option['value'] }}"
                                                @selected((string) $value === (string) $option['value'])
                                                @if(array_key_exists('region_id', $option)) data-region-id="{{ $option['region_id'] }}" @endif
                                                @if(array_key_exists('zone_id', $option)) data-zone-id="{{ $option['zone_id'] }}" @endif
                                                @if(array_key_exists('woreda_id', $option)) data-woreda-id="{{ $option['woreda_id'] }}" @endif
                                                @if(array_key_exists('project_id', $option)) data-project-id="{{ $option['project_id'] }}" @endif
                                            >
                                                {{ $option['label'] }}
                                            </option>
                                        @endforeach
                                        @if($hasSelectedCustomOption)
                                            <option value="{{ $value }}" selected>{{ $value }}</option>
                                        @endif
                                    </select>
                                    @if($resource === 'participants' && $name === 'organization_id')
                                        <div class="form-text">Type at least 2 characters to search. Selecting an organization fills Region, Zone, and Woreda when available.</div>
                                    @endif
                                @elseif($type === 'file')
                                    <input type="file" name="{{ $name }}" class="form-control" @required($isRequired) aria-required="{{ $isRequired ? 'true' : 'false' }}" @if(!empty($field['accept'])) accept="{{ $field['accept'] }}" @endif>
                                    @if($record && data_get($record, $name))
                                        <div class="form-text mt-2">Current file: <a href="{{ route('admin.'.$config['path'].'.file', ['record' => $record->getKey(), 'field' => $name]) }}">{{ basename((string) data_get($record, $name)) }}</a></div>
                                    @endif
                                @else
                                    @if($name === 'search_existing_participant')
                                        <input type="hidden" id="existing-participant-id" name="existing_participant_id" value="">
                                        <input
                                            type="text"
                                            id="participant-search-input"
                                            value="{{ $value }}"
                                            class="form-control"
                                            placeholder="{{ $field['placeholder'] ?? 'Search...' }}"
                                            autocomplete="off"
                                            data-search-url="{{ route('admin.participants.search-options') }}"
                                            data-edit-url-template="{{ route('admin.participants.edit', ['record' => '__PARTICIPANT_ID__']) }}"
                                        >
                                        <div id="participant-search-results" class="participant-search-results list-group mt-2" role="listbox" style="max-height: 300px; overflow-y: auto; display: none;"></div>
                                        <div class="form-text">Start typing a participant name or phone number to search existing participants. If found, you can select to pre-fill the form or edit directly.</div>
                                    @else
                                        <input
                                            type="{{ $type }}"
                                            name="{{ $name }}"
                                            value="{{ $value }}"
                                            class="form-control"
                                            @required($isRequired)
                                            aria-required="{{ $isRequired ? 'true' : 'false' }}"
                                            @if($name === 'age') min="0" max="120" step="1" inputmode="numeric" pattern="[0-9]*" title="Enter a whole number between 0 and 120." @endif
                                            @if($name === 'date_of_birth') max="{{ now()->toDateString() }}" @endif
                                            @if(in_array($name, ['home_phone', 'mobile_phone'], true)) inputmode="tel" maxlength="30" pattern="(?=(?:\D*\d){7,15}\D*$)\+?\d[\d\s().-]*\d" title="Use 7 to 15 digits; spaces, dashes, parentheses, dots, and a leading + are allowed." @endif
                                        >
                                    @endif
                                @endif
                                @if($name === 'date_of_birth')
                                    <div class="form-text">Age is calculated as of July 1st of the current year.</div>
                                @elseif($name === 'age')
                                    <div class="form-text">When age is entered, DOB is approximated to July 1st of (current year - age).</div>
                                @elseif(in_array($name, ['home_phone', 'mobile_phone'], true))
                                    <div class="form-text">Use 7 to 15 digits; spaces, dashes, parentheses, dots, and a leading + are allowed.</div>
                                @endif
                                @error($name)
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                                @error($name.'.*')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-dark" type="submit">Save</button>
            <a href="{{ route('admin.'.$config['path'].'.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection

@section('scripts')
    @if($hasTinyMceField)
    <script src="https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
        tinymce.init({
            selector: 'textarea.js-tinymce',
            height: 360,
            menubar: true,
            license_key: 'gpl',
            branding: false,
            promotion: false,
            toolbar_mode: 'sliding',
            plugins: [
                'advlist', 'anchor', 'autolink', 'autoresize', 'autosave', 'charmap', 'code', 'codesample',
                'directionality', 'emoticons', 'fullscreen', 'help', 'image', 'insertdatetime', 'link',
                'lists', 'media', 'nonbreaking', 'pagebreak', 'preview', 'quickbars', 'save', 'searchreplace',
                'table', 'visualblocks', 'visualchars', 'wordcount'
            ],
            toolbar: 'undo redo restoredraft | blocks fontfamily fontsize | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | outdent indent | bullist numlist | link image media table charmap emoticons pagebreak nonbreaking insertdatetime | ltr rtl | removeformat | searchreplace visualblocks visualchars preview fullscreen code',
            contextmenu: 'undo redo | link image table',
            quickbars_selection_toolbar: 'bold italic underline | blocks | quicklink blockquote',
            quickbars_insert_toolbar: 'quickimage quicktable',
            image_title: true,
            automatic_uploads: false,
            paste_data_images: false,
            browser_spellcheck: true,
        });

        document.getElementById('managed-resource-form')?.addEventListener('submit', () => {
            if (window.tinymce) {
                tinymce.triggerSave();
            }
        });
    </script>
    @endif
    @if($hasDobAgeFields)
    <script>
        (() => {
            const form = document.getElementById('managed-resource-form');
            if (!form) {
                return;
            }

            const dobInput = form.querySelector('input[name="date_of_birth"]');
            const ageInput = form.querySelector('input[name="age"]');

            if (!dobInput || !ageInput) {
                return;
            }

            const referenceYear = new Date().getFullYear();
            let syncing = false;

            const parseDob = (value) => {
                if (!value || !/^\d{4}-\d{2}-\d{2}$/.test(value)) {
                    return null;
                }

                const [year, month, day] = value.split('-').map((item) => Number(item));
                if (!Number.isFinite(year) || !Number.isFinite(month) || !Number.isFinite(day)) {
                    return null;
                }

                return { year, month, day };
            };

            const pad = (number) => String(number).padStart(2, '0');

            const ageFromDob = (dobValue) => {
                const dob = parseDob(dobValue);
                if (!dob) {
                    return null;
                }

                let age = referenceYear - dob.year;
                if (dob.month > 7 || (dob.month === 7 && dob.day > 1)) {
                    age -= 1;
                }

                return Math.max(0, age);
            };

            const dobFromAge = (ageValue) => {
                const age = Number.parseInt(ageValue, 10);
                if (!Number.isFinite(age) || age < 0) {
                    return '';
                }

                const year = referenceYear - age;

                return `${year}-${pad(7)}-${pad(1)}`;
            };

            const syncAgeFromDob = () => {
                if (syncing) {
                    return;
                }

                syncing = true;
                const calculatedAge = ageFromDob(dobInput.value);
                ageInput.value = calculatedAge === null ? '' : String(calculatedAge);
                syncing = false;
            };

            const syncDobFromAge = () => {
                if (syncing) {
                    return;
                }

                syncing = true;
                dobInput.value = dobFromAge(ageInput.value);
                syncing = false;
            };

            dobInput.addEventListener('change', syncAgeFromDob);
            dobInput.addEventListener('input', syncAgeFromDob);
            ageInput.addEventListener('change', syncDobFromAge);
            ageInput.addEventListener('input', syncDobFromAge);

            if (dobInput.value && !ageInput.value) {
                syncAgeFromDob();
            } else if (ageInput.value && !dobInput.value) {
                syncDobFromAge();
            } else if (dobInput.value && ageInput.value) {
                syncAgeFromDob();
            }
        })();
    </script>
    @endif
    @if($hasParticipantIdPreviewFields)
    <script>
        (() => {
            const form = document.getElementById('managed-resource-form');
            if (!form) {
                return;
            }

            const firstNameInput = form.querySelector('input[name="first_name"]');
            const fatherNameInput = form.querySelector('input[name="father_name"]');
            const grandfatherNameInput = form.querySelector('input[name="grandfather_name"]');
            const dobInput = form.querySelector('input[name="date_of_birth"]');
            const ageInput = form.querySelector('input[name="age"]');
            const mobileInput = form.querySelector('input[name="mobile_phone"]');
            const previewInput = form.querySelector('#participant-code-preview');

            if (!firstNameInput || !fatherNameInput || !grandfatherNameInput || !dobInput || !mobileInput || !previewInput) {
                return;
            }

            const initial = (value) => {
                const text = (value || '').trim();
                return text.length ? text.charAt(0).toUpperCase() : 'X';
            };

            const dateParts = (dobValue) => {
                if (!dobValue || !/^\d{4}-\d{2}-\d{2}$/.test(dobValue)) {
                    return { year: '0000', month: '00' };
                }

                const [year, month] = dobValue.split('-');

                return { year, month };
            };

            const last4Digits = (phoneValue) => {
                const digits = String(phoneValue || '').replace(/\D+/g, '');
                return digits.slice(-4).padStart(4, '0');
            };

            const syncPreview = () => {
                const { year, month } = dateParts(dobInput.value);
                previewInput.value = `${initial(firstNameInput.value)}${initial(fatherNameInput.value)}${initial(grandfatherNameInput.value)}${year}${month}${last4Digits(mobileInput.value)}`;
            };

            const bindLive = (input) => {
                if (!input) {
                    return;
                }

                input.addEventListener('input', syncPreview);
                input.addEventListener('change', syncPreview);
            };

            bindLive(firstNameInput);
            bindLive(fatherNameInput);
            bindLive(grandfatherNameInput);
            bindLive(dobInput);
            bindLive(ageInput);
            bindLive(mobileInput);

            syncPreview();
        })();
    </script>
    @endif
    @if($hasMultiSelectField)
    <script>
        (() => {
            const searchInputs = document.querySelectorAll('.js-multiselect-search');
            if (!searchInputs.length) {
                return;
            }

            searchInputs.forEach((input) => {
                const targetId = input.getAttribute('data-target');
                if (!targetId) {
                    return;
                }

                const select = document.getElementById(targetId);
                if (!select) {
                    return;
                }

                const selectedWrap = document.querySelector(`.js-multiselect-selected[data-target="${targetId}"]`);

                const enableClickOnlyMultiSelect = () => {
                    if (!select.multiple || select.dataset.clickOnlyEnabled === '1') {
                        return;
                    }

                    select.dataset.clickOnlyEnabled = '1';
                    select.addEventListener('mousedown', (event) => {
                        const option = event.target;
                        if (!(option instanceof HTMLOptionElement)) {
                            return;
                        }

                        event.preventDefault();
                        option.selected = !option.selected;
                        select.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                };

                const filterOptions = () => {
                    const term = (input.value || '').trim().toLowerCase();
                    Array.from(select.options).forEach((option) => {
                        const matches = term === '' || option.text.toLowerCase().includes(term);
                        option.hidden = !matches && !option.selected;
                    });
                };

                const renderSelected = () => {
                    if (!selectedWrap) {
                        return;
                    }

                    const selectedOptions = Array.from(select.options).filter((option) => option.selected);
                    selectedWrap.innerHTML = '';

                    if (!selectedOptions.length) {
                        return;
                    }

                    selectedOptions.forEach((option) => {
                        const chip = document.createElement('span');
                        chip.className = 'badge text-bg-light border d-inline-flex align-items-center gap-2 py-2 px-2';
                        chip.textContent = option.text;

                        const removeButton = document.createElement('button');
                        removeButton.type = 'button';
                        removeButton.className = 'btn btn-sm p-0 border-0 bg-transparent text-danger fw-bold';
                        removeButton.textContent = 'X';
                        removeButton.setAttribute('aria-label', `Remove ${option.text}`);
                        removeButton.addEventListener('click', () => {
                            option.selected = false;
                            select.dispatchEvent(new Event('change', { bubbles: true }));
                        });

                        chip.appendChild(removeButton);
                        selectedWrap.appendChild(chip);
                    });
                };

                input.addEventListener('input', filterOptions);
                select.addEventListener('change', () => {
                    filterOptions();
                    renderSelected();
                });

                enableClickOnlyMultiSelect();
                filterOptions();
                renderSelected();
            });
        })();
    </script>
    @endif
    @if($hasRepeaterField)
    <script>
        (() => {
            const repeaters = document.querySelectorAll('.js-repeater');
            if (!repeaters.length) {
                return;
            }

            const rowMarkup = (name, itemLabel, required = false, value = '') => `
                <div class="input-group js-repeater-row">
                    <input type="text" name="${name}[]" value="${value.replace(/"/g, '&quot;')}" class="form-control" placeholder="${itemLabel} name" ${required ? 'required aria-required="true"' : 'aria-required="false"'}>
                    <button type="button" class="btn btn-outline-danger js-repeater-remove">Remove</button>
                </div>
            `;

            repeaters.forEach((repeater) => {
                const name = repeater.getAttribute('data-name') || 'items';
                const itemLabel = repeater.getAttribute('data-item-label') || 'Item';
                const isRequired = repeater.getAttribute('data-required') === '1';
                const list = repeater.querySelector('.js-repeater-list');
                const addButton = repeater.querySelector('.js-repeater-add');

                if (!list || !addButton) {
                    return;
                }

                addButton.addEventListener('click', () => {
                    list.insertAdjacentHTML('beforeend', rowMarkup(name, itemLabel, isRequired));
                });

                repeater.addEventListener('click', (event) => {
                    const button = event.target.closest('.js-repeater-remove');
                    if (!button) {
                        return;
                    }

                    const rows = list.querySelectorAll('.js-repeater-row');
                    if (rows.length === 1) {
                        const input = rows[0].querySelector('input');
                        if (input) {
                            input.value = '';
                        }
                        return;
                    }

                    button.closest('.js-repeater-row')?.remove();
                });
            });
        })();
    </script>
    @endif
    @if($hasSearchableSelect)
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
    <script>
        (() => {
            const searchableSelects = document.querySelectorAll('.js-searchable-select');
            if (!searchableSelects.length) {
                return;
            }

            const collectDomOptions = (select) => Array.from(select.options)
                .filter((option) => option.value !== '')
                .map((option) => ({
                    value: option.value,
                    label: option.textContent,
                    region_id: option.dataset.regionId || '',
                    zone_id: option.dataset.zoneId || '',
                    woreda_id: option.dataset.woredaId || '',
                    project_id: option.dataset.projectId || '',
                }));

            const hierarchyContext = (select) => {
                const form = select.form || document.getElementById('managed-resource-form');
                return {
                    regionSelect: form?.querySelector('select[name="region_id"]') ?? null,
                    zoneSelect: form?.querySelector('select[name="zone_id"]') ?? null,
                    woredaSelect: form?.querySelector('select[name="woreda_id"]') ?? null,
                };
            };

            const hasHierarchyFilter = (select) => {
                const context = hierarchyContext(select);
                return Boolean(
                    context.regionSelect?.value
                    || context.zoneSelect?.value
                    || context.woredaSelect?.value
                );
            };

            const buildRemoteUrl = (select, query) => {
                const url = new URL(select.dataset.remoteUrl, window.location.origin);
                const context = hierarchyContext(select);

                if (query) {
                    url.searchParams.set('q', query);
                }

                if (select.value) {
                    url.searchParams.set('selected_id', select.value);
                }

                if (context.regionSelect?.value) {
                    url.searchParams.set('region_id', context.regionSelect.value);
                }

                if (context.zoneSelect?.value) {
                    url.searchParams.set('zone_id', context.zoneSelect.value);
                }

                if (context.woredaSelect?.value) {
                    url.searchParams.set('woreda_id', context.woredaSelect.value);
                }

                return url.toString();
            };

            const initializeRemoteSearchableSelect = (select) => {
                if (select.tomselect) {
                    return;
                }

                const placeholder = select.options[0]?.textContent?.trim() || 'Search';
                const instance = new TomSelect(select, {
                    create: false,
                    allowEmptyOption: false,
                    maxOptions: 50,
                    hidePlaceholder: true,
                    placeholder,
                    valueField: 'value',
                    labelField: 'label',
                    searchField: ['label'],
                    options: collectDomOptions(select),
                    items: select.value ? [select.value] : [],
                    loadThrottle: 250,
                    shouldLoad(query) {
                        return query.length >= 2 || hasHierarchyFilter(select) || Boolean(this.getValue());
                    },
                    load(query, callback) {
                        fetch(buildRemoteUrl(select, query), {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                        })
                            .then((response) => response.ok ? response.json() : Promise.reject(new Error('Failed to load options')))
                            .then((payload) => callback(Array.isArray(payload.options) ? payload.options : []))
                            .catch(() => callback());
                    },
                    onDropdownOpen() {
                        if ((hasHierarchyFilter(select) || this.getValue()) && Object.keys(this.options).length <= 1) {
                            this.load('');
                        }
                    },
                });

                instance.removeOption('');
            };

            const initializeSearchableSelect = (select) => {
                if (!(select instanceof HTMLSelectElement)) {
                    return;
                }

                if (select.tomselect) {
                    return;
                }

                if (select.classList.contains('js-remote-searchable-select') && select.dataset.remoteUrl) {
                    initializeRemoteSearchableSelect(select);
                    return;
                }

                const allowsCustom = select.classList.contains('js-creatable-select');
                const placeholder = select.options[0]?.textContent?.trim() || 'Search';
                const instance = new TomSelect(select, {
                    create: allowsCustom,
                    createOnBlur: allowsCustom,
                    persist: true,
                    allowEmptyOption: false,
                    maxOptions: 500,
                    hidePlaceholder: true,
                    placeholder,
                    searchField: ['text'],
                    sortField: [
                        { field: '$score' },
                        { field: 'text' },
                    ],
                });

                instance.removeOption('');
            };

            const refreshSearchableSelect = (select) => {
                if (!(select instanceof HTMLSelectElement)) {
                    return;
                }

                const currentValue = select.value || '';
                if (select.tomselect) {
                    select.tomselect.destroy();
                }

                initializeSearchableSelect(select);

                if (select.tomselect) {
                    select.tomselect.setValue(currentValue, true);
                }
            };

            window.initializeManagedResourceSearchableSelect = initializeSearchableSelect;
            window.refreshManagedResourceSearchableSelect = refreshSearchableSelect;

            searchableSelects.forEach((select) => {
                initializeSearchableSelect(select);
            });
        })();
    </script>
    @endif
    @if($hasTrainingEventOrganizerFields)
    <script>
        (() => {
            const form = document.getElementById('managed-resource-form');
            if (!form) {
                return;
            }

            const organizerSelect = form.querySelector('select[name="training_organizer_id"]');
            const organizerTypeSelect = form.querySelector('select[name="organizer_type"]');
            const subawardeeSelect = form.querySelector('select[name="project_subawardee_id"]');
            const subawardeeWrap = subawardeeSelect?.closest('.col-md-6, .col-12');

            if (!organizerSelect || !organizerTypeSelect || !subawardeeSelect || !subawardeeWrap) {
                return;
            }

            const originalOptions = Array.from(subawardeeSelect.options).map((option) => ({
                value: option.value,
                label: option.textContent,
                projectId: option.dataset.projectId || '',
            }));

            const selectedValue = () => subawardeeSelect.value || '';

            const rebuildSubawardees = () => {
                const organizerId = organizerSelect.value || '';
                const current = selectedValue();
                const filtered = originalOptions.filter((option) => {
                    if (option.value === '') {
                        return true;
                    }

                    if (!organizerId) {
                        return false;
                    }

                    return String(option.projectId) === String(organizerId);
                });

                const nextValue = filtered.some((option) => String(option.value) === String(current)) ? current : '';

                subawardeeSelect.innerHTML = '';
                filtered.forEach((item) => {
                    const option = document.createElement('option');
                    option.value = item.value;
                    option.textContent = item.label;
                    if (item.projectId) {
                        option.dataset.projectId = item.projectId;
                    }
                    if (String(item.value) === String(nextValue)) {
                        option.selected = true;
                    }
                    subawardeeSelect.appendChild(option);
                });

                if (typeof window.refreshManagedResourceSearchableSelect === 'function' && subawardeeSelect.classList.contains('js-searchable-select')) {
                    window.refreshManagedResourceSearchableSelect(subawardeeSelect);
                }
            };

            const syncVisibility = () => {
                const showSubawardee = organizerTypeSelect.value === 'Subawardee';
                subawardeeWrap.style.display = showSubawardee ? '' : 'none';

                if (!showSubawardee) {
                    subawardeeSelect.value = '';
                    if (subawardeeSelect.tomselect) {
                        subawardeeSelect.tomselect.clear(true);
                    }
                }
            };

            organizerSelect.addEventListener('change', () => {
                rebuildSubawardees();
                syncVisibility();
            });

            organizerTypeSelect.addEventListener('change', () => {
                rebuildSubawardees();
                syncVisibility();
            });

            rebuildSubawardees();
            syncVisibility();
        })();
    </script>
    @endif
    @if($hasHierarchySelectors)
    <script>
        (() => {
            const form = document.getElementById('managed-resource-form');
            if (!form) {
                return;
            }

            const regionSelect = form.querySelector('select[name="region_id"]');
            const zoneSelect = form.querySelector('select[name="zone_id"]');
            const woredaSelect = form.querySelector('select[name="woreda_id"]');
            const organizationSelect = form.querySelector('select[name="organization_id"]');
            const strictParticipantHierarchy = @json($resource === 'participants');

            const captureOptions = (select) => select ? Array.from(select.options).map((option) => ({
                value: option.value,
                label: option.textContent,
                selected: option.selected,
                regionId: option.dataset.regionId || '',
                zoneId: option.dataset.zoneId || '',
                woredaId: option.dataset.woredaId || '',
            })) : [];

            const originalZoneOptions = captureOptions(zoneSelect);
            const originalWoredaOptions = captureOptions(woredaSelect);

            [regionSelect, zoneSelect, woredaSelect, organizationSelect].forEach((select) => {
                if (select && !select.dataset.placeholderLabel) {
                    select.dataset.placeholderLabel = select.options[0]?.textContent?.trim() || 'Select';
                }
            });

            const blankOption = (select) => ({
                value: '',
                label: select?.dataset?.placeholderLabel || 'Select',
                regionId: '',
                zoneId: '',
                woredaId: '',
            });

            const rebuildOptions = (select, options, selectedValue) => {
                if (!select) {
                    return;
                }

                select.innerHTML = '';

                options.forEach((item) => {
                    const regionId = item.regionId ?? item.region_id ?? '';
                    const zoneId = item.zoneId ?? item.zone_id ?? '';
                    const woredaId = item.woredaId ?? item.woreda_id ?? '';
                    const option = document.createElement('option');
                    option.value = item.value;
                    option.textContent = item.label;
                    if (regionId) {
                        option.dataset.regionId = regionId;
                    }
                    if (zoneId) {
                        option.dataset.zoneId = zoneId;
                    }
                    if (woredaId) {
                        option.dataset.woredaId = woredaId;
                    }
                    if (selectedValue !== null && selectedValue !== undefined && String(selectedValue) === String(item.value)) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                });

                if (typeof window.refreshManagedResourceSearchableSelect === 'function' && select.classList.contains('js-searchable-select')) {
                    window.refreshManagedResourceSearchableSelect(select);
                }
            };

            const selectedValue = (select) => {
                if (!select) {
                    return '';
                }

                if (select.tomselect) {
                    return String(select.tomselect.getValue() || '');
                }

                return select.value || '';
            };

            const setSelectValue = (select, value, silent = true) => {
                if (!select) {
                    return;
                }

                const nextValue = value ? String(value) : '';

                if (select.tomselect) {
                    select.tomselect.setValue(nextValue, silent);
                    return;
                }

                select.value = nextValue;
            };

            const optionExists = (select, value) => {
                if (!select || value === null || value === undefined || value === '') {
                    return false;
                }

                return Array.from(select.options).some((option) => String(option.value) === String(value));
            };

            const setSelectDisabled = (select, disabled) => {
                if (!select) {
                    return;
                }

                select.disabled = disabled;

                if (select.tomselect) {
                    if (disabled) {
                        select.tomselect.disable();
                    } else {
                        select.tomselect.enable();
                    }
                }
            };

            const selectedMeta = (select) => {
                const value = selectedValue(select);
                if (!select || !value) {
                    return { regionId: '', zoneId: '', woredaId: '' };
                }

                if (select.tomselect && select.tomselect.options && select.tomselect.options[value]) {
                    const option = select.tomselect.options[value];

                    return {
                        regionId: option.region_id || '',
                        zoneId: option.zone_id || '',
                        woredaId: option.woreda_id || '',
                    };
                }

                const selected = select.options[select.selectedIndex];

                return {
                    regionId: selected?.dataset?.regionId || '',
                    zoneId: selected?.dataset?.zoneId || '',
                    woredaId: selected?.dataset?.woredaId || '',
                };
            };

            const resetOrganizationSelect = (preserveSelection = false) => {
                if (!organizationSelect) {
                    return;
                }

                const currentValue = preserveSelection ? selectedValue(organizationSelect) : '';
                setSelectDisabled(organizationSelect, false);

                if (organizationSelect.classList.contains('js-remote-searchable-select') && organizationSelect.tomselect) {
                    const currentOption = currentValue && organizationSelect.tomselect.options[currentValue]
                        ? { ...organizationSelect.tomselect.options[currentValue] }
                        : null;

                    if (!preserveSelection) {
                        organizationSelect.tomselect.clear(true);
                    }
                    organizationSelect.tomselect.clearOptions();
                    if (currentOption) {
                        organizationSelect.tomselect.addOption(currentOption);
                        organizationSelect.tomselect.setValue(currentValue, true);
                    }
                    return;
                }

                // Fallback for non-remote selects
                Array.from(organizationSelect.options).forEach((option) => {
                    if (option.value !== '' && String(option.value) !== String(currentValue)) {
                        option.remove();
                    }
                });

                organizationSelect.value = currentValue;

                if (organizationSelect.tomselect) {
                    if (!preserveSelection) {
                        organizationSelect.tomselect.clear(true);
                    }

                    organizationSelect.tomselect.clearOptions();

                    const remaining = captureOptions(organizationSelect)
                        .filter((option) => option.value !== '');

                    remaining.forEach((option) => organizationSelect.tomselect.addOption({
                        value: option.value,
                        label: option.label,
                        region_id: option.regionId,
                        zone_id: option.zoneId,
                        woreda_id: option.woredaId,
                    }));

                    if (preserveSelection && currentValue) {
                        organizationSelect.tomselect.setValue(currentValue, true);
                    }
                }
            };

            const loadRemoteOptions = async (select, params, selectedOptionValue = '') => {
                if (!select?.dataset?.remoteUrl) {
                    return false;
                }

                const url = new URL(select.dataset.remoteUrl, window.location.origin);
                Object.entries(params).forEach(([key, value]) => {
                    if (value !== null && value !== undefined && value !== '') {
                        url.searchParams.set(key, value);
                    }
                });

                if (selectedOptionValue) {
                    url.searchParams.set('selected_id', selectedOptionValue);
                }

                setSelectDisabled(select, true);

                try {
                    const response = await fetch(url.toString(), {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                    });

                    if (!response.ok) {
                        throw new Error('Failed to load options');
                    }

                    const payload = await response.json();
                    const options = Array.isArray(payload.options) ? payload.options : [];
                    const normalized = [
                        blankOption(select),
                        ...options.map((option) => ({
                            value: option.value,
                            label: option.label,
                            regionId: option.region_id ?? option.regionId ?? '',
                            zoneId: option.zone_id ?? option.zoneId ?? '',
                            woredaId: option.woreda_id ?? option.woredaId ?? '',
                        })),
                    ];
                    const isSelectedAvailable = normalized.some((option) => String(option.value) === String(selectedOptionValue));

                    rebuildOptions(select, normalized, isSelectedAvailable ? selectedOptionValue : '');
                } catch (error) {
                    rebuildOptions(select, [blankOption(select)], '');
                }

                return true;
            };

            const filterZones = async (targetZoneId = null) => {
                if (!zoneSelect) {
                    return;
                }

                const regionId = selectedValue(regionSelect);
                const current = targetZoneId === null ? selectedValue(zoneSelect) : String(targetZoneId);

                if (strictParticipantHierarchy && zoneSelect.dataset.remoteUrl) {
                    if (!regionId) {
                        rebuildOptions(zoneSelect, [blankOption(zoneSelect)], '');
                        setSelectDisabled(zoneSelect, true);
                        return;
                    }

                    await loadRemoteOptions(zoneSelect, { region_id: regionId }, current);
                    setSelectDisabled(zoneSelect, false);
                    return;
                }

                const filtered = originalZoneOptions.filter((option) => {
                    if (option.value === '') {
                        return true;
                    }
                    if (strictParticipantHierarchy && !regionId) {
                        return false;
                    }
                    if (!regionId) {
                        return true;
                    }
                    return option.regionId === '' || String(option.regionId) === String(regionId);
                });

                const isCurrentAvailable = filtered.some((option) => String(option.value) === String(current));
                rebuildOptions(zoneSelect, filtered, isCurrentAvailable ? current : '');
                setSelectDisabled(zoneSelect, strictParticipantHierarchy && !selectedValue(regionSelect));
            };

            const filterWoredas = async (targetWoredaId = null) => {
                if (!woredaSelect) {
                    return;
                }

                const zoneId = selectedValue(zoneSelect);
                const regionId = selectedValue(regionSelect);
                const current = targetWoredaId === null ? selectedValue(woredaSelect) : String(targetWoredaId);

                if (strictParticipantHierarchy && woredaSelect.dataset.remoteUrl) {
                    if (!zoneId) {
                        rebuildOptions(woredaSelect, [blankOption(woredaSelect)], '');
                        setSelectDisabled(woredaSelect, true);
                        return;
                    }

                    await loadRemoteOptions(woredaSelect, { region_id: regionId, zone_id: zoneId }, current);
                    setSelectDisabled(woredaSelect, false);
                    return;
                }

                const filtered = originalWoredaOptions.filter((option) => {
                    if (option.value === '') {
                        return true;
                    }

                    if (strictParticipantHierarchy && !zoneId) {
                        return false;
                    }

                    if (zoneId) {
                        return option.zoneId === '' || String(option.zoneId) === String(zoneId);
                    }

                    if (regionId) {
                        return option.regionId === '' || String(option.regionId) === String(regionId);
                    }

                    return true;
                });

                const isCurrentAvailable = filtered.some((option) => String(option.value) === String(current));
                rebuildOptions(woredaSelect, filtered, isCurrentAvailable ? current : '');
                setSelectDisabled(woredaSelect, strictParticipantHierarchy && !selectedValue(zoneSelect));
            };

            let organizationDrivenSync = false;

            if (regionSelect) {
                regionSelect.addEventListener('change', async () => {
                    await filterZones();
                    await filterWoredas();
                    resetOrganizationSelect(organizationDrivenSync);
                });
            }

            if (zoneSelect) {
                zoneSelect.addEventListener('change', async () => {
                    const selected = zoneSelect.options[zoneSelect.selectedIndex];
                    const regionId = selected?.dataset?.regionId || '';
                    if (regionSelect && regionId) {
                        setSelectValue(regionSelect, regionId);
                    }
                    await filterWoredas();
                    resetOrganizationSelect(organizationDrivenSync);
                });
            }

            if (woredaSelect) {
                woredaSelect.addEventListener('change', async () => {
                    const selected = woredaSelect.options[woredaSelect.selectedIndex];
                    const zoneId = selected?.dataset?.zoneId || '';
                    const regionId = selected?.dataset?.regionId || '';
                    if (zoneSelect && zoneId) {
                        setSelectValue(zoneSelect, zoneId);
                    }
                    if (regionSelect && regionId) {
                        setSelectValue(regionSelect, regionId);
                    }
                    await filterZones(zoneId || null);
                    await filterWoredas(selectedValue(woredaSelect) || null);
                    resetOrganizationSelect(organizationDrivenSync);
                });
            }

            if (organizationSelect) {
                organizationSelect.addEventListener('change', async () => {
                    if (strictParticipantHierarchy && !selectedValue(organizationSelect)) {
                        organizationDrivenSync = true;

                        if (regionSelect) {
                            setSelectValue(regionSelect, '');
                        }
                        if (zoneSelect) {
                            setSelectValue(zoneSelect, '');
                        }
                        if (woredaSelect) {
                            setSelectValue(woredaSelect, '');
                        }

                        await filterZones();
                        await filterWoredas();
                        resetOrganizationSelect(true);
                        organizationDrivenSync = false;
                        return;
                    }

                    const { regionId, zoneId, woredaId } = selectedMeta(organizationSelect);

                    organizationDrivenSync = true;

                    if (regionSelect && regionId) {
                        setSelectValue(regionSelect, regionId);
                    }

                    await filterZones(zoneId || null);
                    if (zoneSelect && zoneId && optionExists(zoneSelect, zoneId)) {
                        setSelectValue(zoneSelect, zoneId);
                    }

                    await filterWoredas(woredaId || null);
                    if (woredaSelect && woredaId && optionExists(woredaSelect, woredaId)) {
                        setSelectValue(woredaSelect, woredaId);
                    }

                    resetOrganizationSelect(true);
                    organizationDrivenSync = false;
                });
            }

            void (async () => {
                await filterZones(selectedValue(zoneSelect) || null);
                await filterWoredas(selectedValue(woredaSelect) || null);
                resetOrganizationSelect(true);
            })();
        })();
    </script>
    @endif
    @if($resource === 'participants')
    <script>
        (() => {
            const searchInput = document.getElementById('participant-search-input');
            const resultsContainer = document.getElementById('participant-search-results');
            const searchUrl = searchInput?.dataset.searchUrl;
            const editUrlTemplate = searchInput?.dataset.editUrlTemplate;
            if (!searchInput || !searchUrl || !editUrlTemplate) {
                return;
            }

            let searchTimeout;

            const displayResults = (options) => {
                resultsContainer.innerHTML = '';
                if (options.length === 0) {
                    resultsContainer.innerHTML = '<div class="list-group-item text-muted">No participants found</div>';
                    resultsContainer.style.display = 'block';
                    return;
                }

                options.forEach((option) => {
                    const item = document.createElement('button');
                    item.type = 'button';
                    item.className = 'list-group-item list-group-item-action text-start';
                    item.innerHTML = `
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong>${option.label}</strong><br>
                                <small class="text-muted">${option.hint}</small>
                            </div>
                            <span class="badge bg-secondary">#${option.value}</span>
                        </div>
                    `;
                    item.addEventListener('click', (e) => {
                        e.preventDefault();
                        selectParticipant(option);
                    });
                    resultsContainer.appendChild(item);
                });

                resultsContainer.style.display = 'block';
            };

            const selectParticipant = (option) => {
                // Navigate to edit the selected participant
                window.location.href = editUrlTemplate.replace('__PARTICIPANT_ID__', encodeURIComponent(option.value));
            };

            const performSearch = async (query) => {
                if (query.length < 2) {
                    resultsContainer.style.display = 'none';
                    return;
                }

                try {
                    const response = await fetch(`${searchUrl}?q=${encodeURIComponent(query)}`);
                    const data = await response.json();
                    displayResults(data.options || []);
                } catch (error) {
                    console.error('Search error:', error);
                    resultsContainer.innerHTML = '<div class="list-group-item text-danger">Search error</div>';
                    resultsContainer.style.display = 'block';
                }
            };

            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                const query = e.target.value.trim();
                searchTimeout = setTimeout(() => {
                    performSearch(query);
                }, 300);
            });

            document.addEventListener('click', (e) => {
                if (!e.target.closest('#participant-search-input') && !e.target.closest('#participant-search-results')) {
                    resultsContainer.style.display = 'none';
                }
            });

            searchInput.addEventListener('focus', () => {
                if (resultsContainer.innerHTML) {
                    resultsContainer.style.display = 'block';
                }
            });
        })();
    </script>
    @endif
@endsection
