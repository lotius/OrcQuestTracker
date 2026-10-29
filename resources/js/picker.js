// Searchable picker for <select> elements. The select stays the source of truth (form data, undo, rules);
// the picker only offers a searchable list and sets the select's value when something is chosen.

const PHONE = window.matchMedia('(max-width: 639px)');

const normalise = (text) => text.toLowerCase().normalize('NFKD').replace(/[̀-ͯ]/g, '');

/**
 * @param {object}   options
 * @param {Function} options.prepare  (select) => void   fill/filter the select's options before showing them
 * @param {Function} options.choose   (select, value) => void   apply a choice
 * @param {Function} options.describe (select, value) => string|null   optional detail line per option
 * @param {Function} options.title    (select) => string   heading for the picker
 */
export const createPicker = ({ prepare, choose, describe, title }) => {
    let state = null;

    const close = (restoreFocus = true) => {
        if (! state) {
            return;
        }

        const { panel, backdrop, trigger } = state;
        panel.remove();
        backdrop.remove();
        document.removeEventListener('keydown', onKeydown, true);
        window.removeEventListener('resize', onReflow);
        window.removeEventListener('scroll', onReflow, true);
        state = null;

        if (restoreFocus) {
            trigger?.focus({ preventScroll: true });
        }
    };

    const position = () => {
        if (! state || state.sheet) {
            return;
        }

        const { panel, select } = state;
        const box = select.getBoundingClientRect();
        const width = Math.min(Math.max(box.width, 300), window.innerWidth - 16);
        const below = window.innerHeight - box.bottom - 8;
        const above = box.top - 8;
        const height = Math.min(380, Math.max(below, above));

        panel.style.width = `${width}px`;
        panel.style.left = `${Math.min(Math.max(8, box.left), window.innerWidth - width - 8)}px`;
        panel.style.maxHeight = `${height}px`;

        if (below >= Math.min(260, above) || below >= 380) {
            panel.style.top = `${box.bottom + 4}px`;
            panel.style.bottom = '';
        } else {
            panel.style.top = '';
            panel.style.bottom = `${window.innerHeight - box.top + 4}px`;
        }
    };

    const onReflow = () => position();

    const render = () => {
        const { list, select, input } = state;
        const words = normalise(input.value).split(/\s+/).filter(Boolean);
        const matches = state.entries.filter((entry) => words.every((word) => entry.haystack.includes(word)));

        state.matches = matches;
        state.active = Math.min(state.active, Math.max(0, matches.length - 1));
        list.replaceChildren();

        if (! matches.length) {
            const empty = document.createElement('li');
            empty.className = 'px-3 py-6 text-center text-sm text-muted';
            empty.textContent = 'Nothing matches. Try part of the name or the card code.';
            list.append(empty);
            return;
        }

        matches.forEach((entry, position) => {
            const item = document.createElement('li');
            item.id = `picker-option-${position}`;
            item.setAttribute('role', 'option');
            item.setAttribute('aria-selected', entry.value === select.value ? 'true' : 'false');
            item.dataset.position = String(position);
            item.className = [
                'flex cursor-pointer flex-col gap-0.5 rounded-md px-3 py-2',
                position === state.active ? 'bg-sage-soft' : 'hover:bg-paper-deep',
                entry.value === select.value ? 'font-semibold text-orc' : 'text-ink',
            ].join(' ');

            const label = document.createElement('span');
            label.className = `text-sm ${entry.value ? '' : 'italic text-muted'}`;
            label.textContent = entry.label;
            item.append(label);

            if (entry.detail) {
                const detail = document.createElement('span');
                detail.className = 'text-xs font-normal leading-snug text-muted';
                detail.textContent = entry.detail;
                item.append(detail);
            }

            list.append(item);
        });

        input.setAttribute('aria-activedescendant', `picker-option-${state.active}`);
        list.querySelector(`[data-position="${state.active}"]`)?.scrollIntoView({ block: 'nearest' });
    };

    const pick = (entry) => {
        if (! state || ! entry) {
            return;
        }

        const { select } = state;
        close();

        if (entry.value !== select.value) {
            choose(select, entry.value);
        }
    };

    const onKeydown = (event) => {
        if (! state) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            close();
        } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            const step = event.key === 'ArrowDown' ? 1 : -1;
            state.active = Math.min(Math.max(0, state.active + step), state.matches.length - 1);
            render();
        } else if (event.key === 'Enter') {
            event.preventDefault();
            pick(state.matches[state.active]);
        } else if (event.key === 'Tab') {
            close(false);
        }
    };

    const open = (select, trigger, initialQuery = '') => {
        close(false);
        prepare(select);

        const sheet = PHONE.matches;
        const entries = Array.from(select.options)
            .filter((option) => ! option.disabled && ! option.hidden)
            .map((option) => {
                const detail = option.value ? describe?.(select, option.value) : null;

                return {
                    value: option.value,
                    label: option.text,
                    detail,
                    haystack: normalise(`${option.text} ${detail || ''}`),
                };
            });

        const backdrop = document.createElement('div');
        backdrop.className = sheet ? 'fixed inset-0 z-50 bg-ink/50' : 'fixed inset-0 z-50';
        backdrop.addEventListener('pointerdown', (event) => {
            event.preventDefault();
            close();
        });

        const panel = document.createElement('div');
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-label', title(select));
        panel.className = sheet
            ? 'fixed inset-x-0 bottom-0 z-50 flex max-h-[80dvh] flex-col rounded-t-2xl bg-paper pb-[env(safe-area-inset-bottom)] shadow-2xl'
            : 'fixed z-50 flex flex-col overflow-hidden rounded-lg border border-line-strong bg-paper shadow-2xl';

        const header = document.createElement('div');
        header.className = sheet ? 'border-b border-line p-3' : 'border-b border-line p-2';

        if (sheet) {
            const heading = document.createElement('p');
            heading.className = 'mb-2 flex items-center justify-between gap-2';
            const name = document.createElement('span');
            name.className = 'font-display text-lg text-orc-700';
            name.textContent = title(select);
            const done = document.createElement('button');
            done.type = 'button';
            done.className = 'rounded-md px-2 py-1 text-sm font-semibold text-muted hover:bg-paper-deep';
            done.textContent = 'Cancel';
            done.addEventListener('click', () => close());
            heading.append(name, done);
            header.append(heading);
        }

        const input = document.createElement('input');
        input.type = 'search';
        input.className = 'control';
        input.placeholder = 'Search…';
        input.value = initialQuery;
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-expanded', 'true');
        input.setAttribute('aria-controls', 'picker-list');
        input.setAttribute('aria-autocomplete', 'list');
        input.autocomplete = 'off';
        input.spellcheck = false;
        header.append(input);

        const list = document.createElement('ul');
        list.id = 'picker-list';
        list.setAttribute('role', 'listbox');
        list.className = 'min-h-0 flex-1 overflow-y-auto overscroll-contain p-1.5';
        list.addEventListener('pointerdown', (event) => event.preventDefault());
        list.addEventListener('click', (event) => {
            const item = event.target.closest('[data-position]');
            if (item) {
                pick(state.matches[Number(item.dataset.position)]);
            }
        });

        panel.append(header, list);
        document.body.append(backdrop, panel);

        const current = entries.findIndex((entry) => entry.value === select.value);
        state = { select, trigger, panel, backdrop, list, input, entries, matches: entries, active: Math.max(0, current), sheet };

        input.addEventListener('input', () => {
            state.active = 0;
            render();
        });
        document.addEventListener('keydown', onKeydown, true);
        window.addEventListener('resize', onReflow);
        window.addEventListener('scroll', onReflow, true);

        position();
        render();
        input.focus({ preventScroll: true });
        input.setSelectionRange(input.value.length, input.value.length);
    };

    return { open, close, isOpen: () => Boolean(state) };
};
