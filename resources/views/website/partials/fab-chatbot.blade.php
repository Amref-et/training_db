@php
    $fabSettings = $settings ?? \App\Models\WebsiteSetting::current();
    $fabEnabled = (bool) ($fabSettings->fab_chat_enabled ?? false);
    $fabFaqTree = $fabEnabled
        ? \App\Models\FabFaqItem::tree(true)->map(fn ($item) => $item->toChatbotNode())->values()->all()
        : [];
@endphp

@if($fabEnabled)
<div class="hil-fab-chatbot" data-fab-chatbot>
    <button class="hil-fab-chatbot__button" type="button" aria-label="Open FAQ chatbot" aria-expanded="false" data-fab-toggle>
        <span aria-hidden="true">?</span>
    </button>

    <section class="hil-fab-chatbot__panel" aria-label="FAQ chatbot" hidden data-fab-panel>
        <header class="hil-fab-chatbot__header">
            <div>
                <div class="hil-fab-chatbot__eyebrow">Help</div>
                <div class="hil-fab-chatbot__title">FAQ Assistant</div>
            </div>
            <button class="hil-fab-chatbot__close" type="button" aria-label="Close FAQ chatbot" data-fab-close>&times;</button>
        </header>
        <div class="hil-fab-chatbot__messages" data-fab-messages></div>
        <div class="hil-fab-chatbot__options" data-fab-options></div>
    </section>

    <script type="application/json" data-fab-faq-json>@json($fabFaqTree)</script>
</div>

<style>
    .hil-fab-chatbot {
        position: fixed;
        right: 1.25rem;
        bottom: 1.25rem;
        z-index: 1040;
        font-family: inherit;
    }
    .hil-fab-chatbot__button {
        width: 3.75rem;
        height: 3.75rem;
        border: 0;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: {{ $fabSettings->body_accent_color ?: '#0f766e' }};
        color: #fff;
        box-shadow: 0 18px 38px rgba(15, 23, 42, .24);
        font-size: 1.6rem;
        font-weight: 800;
    }
    .hil-fab-chatbot__button:hover,
    .hil-fab-chatbot__button:focus {
        transform: translateY(-1px);
        box-shadow: 0 22px 42px rgba(15, 23, 42, .28);
    }
    .hil-fab-chatbot__panel {
        position: absolute;
        right: 0;
        bottom: 4.75rem;
        width: min(24rem, calc(100vw - 2rem));
        max-height: min(38rem, calc(100vh - 7rem));
        display: flex;
        flex-direction: column;
        overflow: hidden;
        background: #fff;
        border: 1px solid rgba(15, 23, 42, .12);
        border-radius: 16px;
        box-shadow: 0 24px 60px rgba(15, 23, 42, .22);
    }
    .hil-fab-chatbot__panel[hidden] {
        display: none;
    }
    .hil-fab-chatbot__header {
        padding: 1rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        background: #0f172a;
        color: #fff;
    }
    .hil-fab-chatbot__eyebrow {
        font-size: .72rem;
        letter-spacing: .1em;
        text-transform: uppercase;
        color: rgba(255, 255, 255, .72);
    }
    .hil-fab-chatbot__title {
        font-weight: 700;
    }
    .hil-fab-chatbot__close {
        width: 2rem;
        height: 2rem;
        border: 0;
        border-radius: 8px;
        background: rgba(255, 255, 255, .12);
        color: #fff;
        font-size: 1.25rem;
        line-height: 1;
    }
    .hil-fab-chatbot__messages {
        padding: 1rem;
        overflow-y: auto;
        display: grid;
        gap: .65rem;
        background: #f8fafc;
    }
    .hil-fab-chatbot__message {
        max-width: 88%;
        border-radius: 14px;
        padding: .72rem .82rem;
        font-size: .92rem;
        line-height: 1.45;
        white-space: pre-wrap;
    }
    .hil-fab-chatbot__message--bot {
        justify-self: start;
        background: #fff;
        color: #1f2937;
        border: 1px solid rgba(15, 23, 42, .08);
    }
    .hil-fab-chatbot__message--user {
        justify-self: end;
        background: {{ $fabSettings->body_accent_color ?: '#0f766e' }};
        color: #fff;
    }
    .hil-fab-chatbot__options {
        padding: .85rem;
        display: grid;
        gap: .5rem;
        background: #fff;
        border-top: 1px solid rgba(15, 23, 42, .08);
    }
    .hil-fab-chatbot__option {
        width: 100%;
        border: 1px solid rgba(15, 23, 42, .12);
        border-radius: 10px;
        background: #fff;
        color: #1f2937;
        padding: .68rem .78rem;
        text-align: left;
        font-size: .92rem;
    }
    .hil-fab-chatbot__option:hover,
    .hil-fab-chatbot__option:focus {
        border-color: {{ $fabSettings->body_accent_color ?: '#0f766e' }};
        background: #f8fafc;
    }
    .hil-fab-chatbot__option--secondary {
        color: #475569;
        background: #f8fafc;
    }
    @media (max-width: 575.98px) {
        .hil-fab-chatbot {
            right: 1rem;
            bottom: 1rem;
        }
        .hil-fab-chatbot__panel {
            position: fixed;
            right: 1rem;
            left: 1rem;
            bottom: 5rem;
            width: auto;
        }
    }
</style>

<script>
    (() => {
        document.querySelectorAll('[data-fab-chatbot]').forEach((root) => {
            if (root.dataset.fabInitialized === '1') {
                return;
            }

            root.dataset.fabInitialized = '1';

            const toggle = root.querySelector('[data-fab-toggle]');
            const close = root.querySelector('[data-fab-close]');
            const panel = root.querySelector('[data-fab-panel]');
            const messages = root.querySelector('[data-fab-messages]');
            const options = root.querySelector('[data-fab-options]');
            const json = root.querySelector('[data-fab-faq-json]');
            const tree = JSON.parse(json?.textContent || '[]');
            const stack = [];
            let currentItems = tree;
            let started = false;

            const scrollMessages = () => {
                messages.scrollTop = messages.scrollHeight;
            };

            const addMessage = (text, from = 'bot') => {
                const bubble = document.createElement('div');
                bubble.className = `hil-fab-chatbot__message hil-fab-chatbot__message--${from}`;
                bubble.textContent = text;
                messages.appendChild(bubble);
                scrollMessages();
            };

            const renderOptions = (items) => {
                options.innerHTML = '';

                if (items.length === 0) {
                    const empty = document.createElement('div');
                    empty.className = 'text-secondary small';
                    empty.textContent = 'FAQ content is not available yet.';
                    options.appendChild(empty);
                    return;
                }

                items.forEach((item) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'hil-fab-chatbot__option';
                    button.textContent = item.title;
                    button.addEventListener('click', () => selectItem(item));
                    options.appendChild(button);
                });

                if (stack.length > 0) {
                    const back = document.createElement('button');
                    back.type = 'button';
                    back.className = 'hil-fab-chatbot__option hil-fab-chatbot__option--secondary';
                    back.textContent = 'Back';
                    back.addEventListener('click', () => {
                        const previous = stack.pop();
                        currentItems = previous || tree;
                        addMessage('Back', 'user');
                        addMessage('Choose another option.');
                        renderOptions(currentItems);
                    });
                    options.appendChild(back);
                }
            };

            const selectItem = (item) => {
                addMessage(item.title, 'user');

                if (Array.isArray(item.children) && item.children.length > 0) {
                    stack.push(currentItems);
                    currentItems = item.children;
                    addMessage(`Select an option under ${item.title}.`);
                    renderOptions(currentItems);
                    return;
                }

                addMessage(item.answer || 'No answer has been added for this question yet.');
                renderOptions(stack.length > 0 ? currentItems : tree);
            };

            const start = () => {
                if (started) {
                    return;
                }

                started = true;
                messages.innerHTML = '';
                addMessage('Hello. Choose a topic to find an answer.');
                renderOptions(currentItems);
            };

            toggle?.addEventListener('click', () => {
                const open = panel.hasAttribute('hidden');
                panel.toggleAttribute('hidden', !open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');

                if (open) {
                    start();
                }
            });

            close?.addEventListener('click', () => {
                panel.setAttribute('hidden', 'hidden');
                toggle?.setAttribute('aria-expanded', 'false');
            });
        });
    })();
</script>
@endif
