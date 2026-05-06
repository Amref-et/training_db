<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Training Event Enrollment | {{ $websiteSettings->site_name ?? config('app.name', 'HIL Website') }}</title>
    @if($faviconUrl)
        <link rel="icon" href="{{ $faviconUrl }}">
        <link rel="shortcut icon" href="{{ $faviconUrl }}">
    @endif
    {!! \App\Support\PublicBuildManifest::tags(['resources/css/public-vendor.css', 'resources/js/public-vendor.js']) !!}
    <style>
        :root {
            --body-bg: {{ $websiteSettings->body_background_color ?: '#f8fafc' }};
            --body-text: {{ $websiteSettings->body_text_color ?: '#0f172a' }};
            --body-panel: {{ $websiteSettings->body_panel_color ?: '#ffffff' }};
            --body-accent: {{ $websiteSettings->body_accent_color ?: ($websiteSettings->primary_color ?: '#0f766e') }};
            --radius-sm: {{ max(0, (int) ($websiteSettings->radius_sm ?? 10)) }}px;
            --radius-xl: {{ max(0, (int) ($websiteSettings->radius_xl ?? 24)) }}px;
        }

        body {
            background: linear-gradient(180deg, var(--body-bg) 0%, #ffffff 100%);
            color: var(--body-text);
        }

        .join-header {
            background: #ffffff;
            border-bottom: 1px solid rgba(15, 23, 42, .08);
        }

        .join-brand {
            color: inherit;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: .75rem;
            font-weight: 700;
        }

        .join-brand-logo {
            max-height: 48px;
            max-width: 180px;
            object-fit: contain;
        }

        .join-card {
            border-radius: var(--radius-xl);
            background: color-mix(in srgb, var(--body-panel) 94%, #ffffff);
            border: 1px solid rgba(15, 23, 42, .08);
            box-shadow: 0 24px 50px rgba(15, 23, 42, .08);
        }

        .join-kicker {
            letter-spacing: .14em;
            text-transform: uppercase;
            font-size: .78rem;
            color: #64748b;
        }

        .join-submit {
            background: var(--body-accent);
            color: #ffffff;
            border: 0;
            border-radius: var(--radius-sm);
            padding: .85rem 1.15rem;
            font-weight: 600;
        }

        .join-submit:hover {
            color: #ffffff;
            opacity: .94;
        }

        .participant-search-wrap {
            position: relative;
        }

        .participant-search-results {
            display: none;
            position: absolute;
            z-index: 30;
            top: calc(100% - .25rem);
            right: 0;
            left: 0;
            max-height: 260px;
            overflow-y: auto;
            background: #ffffff;
            border: 1px solid rgba(15, 23, 42, .16);
            border-radius: var(--radius-sm);
            box-shadow: 0 18px 36px rgba(15, 23, 42, .14);
        }

        .participant-search-results.is-open {
            display: block;
        }

        .participant-search-option,
        .participant-search-empty {
            width: 100%;
            padding: .72rem .85rem;
            text-align: left;
            border: 0;
            border-bottom: 1px solid rgba(15, 23, 42, .08);
            background: #ffffff;
        }

        .participant-search-option:hover,
        .participant-search-option:focus {
            background: #f8fafc;
        }

        .participant-search-option:last-child,
        .participant-search-empty:last-child {
            border-bottom: 0;
        }

        .participant-search-label,
        .participant-search-hint {
            display: block;
        }

        .participant-search-label {
            font-weight: 600;
            color: #0f172a;
        }

        .participant-search-hint {
            margin-top: .15rem;
            font-size: .82rem;
            color: #64748b;
        }
    </style>
</head>
<body>
    <header class="join-header py-3">
        <div class="container d-flex flex-wrap gap-3 align-items-center justify-content-between">
            <a class="join-brand" href="{{ route('home') }}">
                @if($headerLogoUrl)
                    <img src="{{ $headerLogoUrl }}" alt="{{ $websiteSettings->site_name ?? config('app.name') }}" class="join-brand-logo">
                @endif
                <span>{{ $websiteSettings->site_name ?: config('app.name', 'HIL Website') }}</span>
            </a>
            <nav class="d-flex flex-wrap gap-2">
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('home') }}">Home</a>
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('participant-registration.create') }}">Participant Registration</a>
            </nav>
        </div>
    </header>

    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <section class="join-card p-4 p-lg-5">
                    <div class="join-kicker mb-2">Training Event Request</div>
                    <h1 class="h3 mb-2">Request to Join a Training Event</h1>
                    <p class="text-secondary mb-4">Search for your name and enter your registered mobile phone. Requests are reviewed by the event organizer or manager before enrollment.</p>

                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if($requestableEvents->isEmpty())
                        <div class="alert alert-warning mb-0">No training events are currently accepting join requests.</div>
                    @else
                        <form method="POST" action="{{ route('training-event-join-requests.store') }}" class="row g-3">
                            @csrf
                            <div class="col-12">
                                <label class="form-label" for="training_event_id">Training Event</label>
                                <select id="training_event_id" name="training_event_id" class="form-select @error('training_event_id') is-invalid @enderror" required>
                                    <option value="">Select training event</option>
                                    @foreach($requestableEvents as $event)
                                        <option value="{{ $event->id }}" @selected((string) $selectedEventId === (string) $event->id)>
                                            {{ $event->event_name ?: 'Event #'.$event->id }} | {{ $event->training?->title ?: 'No training' }} | {{ $event->start_date }} to {{ $event->end_date }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('training_event_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6 participant-search-wrap">
                                <label class="form-label" for="participant_name">Participant Name</label>
                                <input id="participant_id" name="participant_id" type="hidden" value="{{ old('participant_id') }}">
                                <input
                                    id="participant_name"
                                    name="participant_name"
                                    type="text"
                                    autocomplete="off"
                                    class="form-control @error('participant_name') is-invalid @enderror"
                                    value="{{ old('participant_name') }}"
                                    data-search-url="{{ route('training-event-join-requests.participant-options') }}"
                                    required
                                >
                                <div id="participant-search-results" class="participant-search-results" role="listbox"></div>
                                <div class="form-text">Start typing your name and select the matching record.</div>
                                @error('participant_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="mobile_phone">Registered Mobile Phone</label>
                                <input id="mobile_phone" name="mobile_phone" type="tel" inputmode="tel" maxlength="30" class="form-control @error('mobile_phone') is-invalid @enderror" value="{{ old('mobile_phone') }}" required>
                                @error('mobile_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="requested_message">Message to Organizer</label>
                                <textarea id="requested_message" name="requested_message" rows="4" maxlength="1000" class="form-control @error('requested_message') is-invalid @enderror">{{ old('requested_message') }}</textarea>
                                @error('requested_message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <button type="submit" class="join-submit">Submit Request</button>
                            </div>
                        </form>
                    @endif
                </section>
            </div>
        </div>
    </main>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const nameInput = document.getElementById('participant_name');
            const participantIdInput = document.getElementById('participant_id');
            const results = document.getElementById('participant-search-results');

            if (!nameInput || !participantIdInput || !results || !nameInput.dataset.searchUrl) {
                return;
            }

            let searchTimer = null;
            let activeRequest = null;

            const closeResults = () => {
                results.classList.remove('is-open');
                results.replaceChildren();
            };

            const renderEmpty = (message) => {
                const item = document.createElement('div');
                item.className = 'participant-search-empty';
                item.textContent = message;

                results.replaceChildren(item);
                results.classList.add('is-open');
            };

            const selectOption = (option) => {
                participantIdInput.value = option.value;
                nameInput.value = option.label;
                closeResults();
            };

            const renderOptions = (options) => {
                if (!options.length) {
                    renderEmpty('No matching participants found.');
                    return;
                }

                const items = options.map((option) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'participant-search-option';
                    button.setAttribute('role', 'option');

                    const label = document.createElement('span');
                    label.className = 'participant-search-label';
                    label.textContent = option.label;

                    const hint = document.createElement('span');
                    hint.className = 'participant-search-hint';
                    hint.textContent = option.hint || '';

                    button.append(label, hint);
                    button.addEventListener('click', () => selectOption(option));

                    return button;
                });

                results.replaceChildren(...items);
                results.classList.add('is-open');
            };

            const searchParticipants = () => {
                const query = nameInput.value.trim();
                participantIdInput.value = '';

                if (query.length < 2) {
                    closeResults();
                    return;
                }

                if (activeRequest) {
                    activeRequest.abort();
                }

                activeRequest = new AbortController();

                fetch(`${nameInput.dataset.searchUrl}?q=${encodeURIComponent(query)}`, {
                    headers: { Accept: 'application/json' },
                    signal: activeRequest.signal,
                })
                    .then((response) => response.ok ? response.json() : Promise.reject(response))
                    .then((data) => renderOptions(data.options || []))
                    .catch((error) => {
                        if (error.name !== 'AbortError') {
                            renderEmpty('Participant search is currently unavailable.');
                        }
                    });
            };

            nameInput.addEventListener('input', () => {
                window.clearTimeout(searchTimer);
                searchTimer = window.setTimeout(searchParticipants, 250);
            });

            nameInput.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeResults();
                }
            });

            document.addEventListener('click', (event) => {
                if (!results.contains(event.target) && event.target !== nameInput) {
                    closeResults();
                }
            });
        });
    </script>
</body>
</html>
