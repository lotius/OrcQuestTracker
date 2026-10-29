// Campaign history timeline: renders server-recorded events with search and filters.

const TYPES = {
    character: { label: 'Character', color: '#2f6b3a' },
    item: { label: 'Item', color: '#55636f' },
    rekup: { label: 'Rekup', color: '#d9741c' },
    enchant: { label: 'Enchant', color: '#1f7f7a' },
    skill: { label: 'Skill', color: '#3f6b3f' },
    kraft: { label: 'Kraft', color: '#6d3a8f' },
    quest: { label: 'Quest', color: '#b58a12' },
    campaign: { label: 'Campaign', color: '#776e62' },
    notes: { label: 'Notes', color: '#776e62' },
};

const PAGE_SIZE = 300;

export const createHistoryView = ({ heroById }) => {
    const list = document.querySelector('[data-history-list]');
    const search = document.querySelector('[data-history-search]');
    const typeFilter = document.querySelector('[data-history-type]');
    const characterFilter = document.querySelector('[data-history-character]');

    if (! list) {
        return { add: () => {} };
    }

    const events = [];
    let limit = PAGE_SIZE;

    const matches = (event) => {
        const type = typeFilter?.value || '';
        const who = characterFilter?.value || '';
        const query = (search?.value || '').trim().toLowerCase();

        if (type && event.type !== type && ! (type === 'campaign' && event.type === 'notes')) {
            return false;
        }

        if (who && event.who !== who) {
            return false;
        }

        return ! query || `${event.who || ''} ${event.text}`.toLowerCase().includes(query);
    };

    const refreshCharacterOptions = () => {
        if (! characterFilter) {
            return;
        }

        const current = characterFilter.value;
        const names = [...new Set(events.map((event) => event.who).filter(Boolean))].sort();
        characterFilter.replaceChildren(new Option('Whole warband', ''), ...names.map((name) => new Option(name, name)));
        characterFilter.value = names.includes(current) ? current : '';
    };

    const entryNode = (event) => {
        const type = TYPES[event.type] || TYPES.campaign;
        const row = document.createElement('li');
        row.className = 'grid grid-cols-[3.25rem_minmax(0,1fr)] gap-x-3 border-t border-line py-2.5 first:border-t-0 sm:grid-cols-[4rem_minmax(0,1fr)]';

        const time = document.createElement('time');
        time.className = 'pt-0.5 text-xs tabular-nums text-muted';
        time.dateTime = event.at;
        time.textContent = new Date(event.at).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });

        const body = document.createElement('div');
        body.className = 'min-w-0';

        const meta = document.createElement('div');
        meta.className = 'mb-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs';

        const badge = document.createElement('span');
        badge.className = 'inline-flex items-center gap-1.5 font-semibold uppercase tracking-wide';
        badge.style.color = type.color;
        const dot = document.createElement('span');
        dot.className = 'size-2 rounded-full';
        dot.style.background = type.color;
        badge.append(dot, document.createTextNode(type.label));
        meta.append(badge);

        if (event.who) {
            const who = document.createElement('span');
            who.className = 'inline-flex items-center gap-1.5 font-semibold text-ink-soft';
            const hero = heroById[event.hero_id];
            if (hero?.icon) {
                const icon = document.createElement('img');
                icon.className = 'size-4 rounded-full object-cover';
                icon.src = hero.icon;
                icon.alt = '';
                who.append(icon);
            }
            who.append(document.createTextNode(event.who));
            meta.append(who);
        }

        if (event.source) {
            const source = document.createElement('span');
            source.className = 'rounded bg-paper-deep px-1.5 py-px font-medium text-muted';
            source.textContent = event.source === 'undo' ? 'via undo' : 'via redo';
            meta.append(source);
        }

        const text = document.createElement('p');
        text.className = 'text-sm text-ink';
        text.textContent = event.text;

        body.append(meta, text);
        row.append(time, body);

        return row;
    };

    const render = () => {
        // Newest save first, but entries from one save keep their recorded order (summary before details).
        const batches = [];
        events.filter(matches).forEach((event) => {
            const last = batches[batches.length - 1];
            if (last && last[0].at === event.at) {
                last.push(event);
            } else {
                batches.push([event]);
            }
        });
        const visible = batches.reverse().flat();
        list.replaceChildren();

        if (! visible.length) {
            const empty = document.createElement('p');
            empty.className = 'py-10 text-center text-sm text-muted';
            empty.textContent = events.length ? 'Nothing matches these filters.' : 'No history yet. Changes are recorded here as you play.';
            list.appendChild(empty);
            return;
        }

        let currentDay = null;
        let group = null;

        visible.slice(0, limit).forEach((event) => {
            const day = new Date(event.at).toLocaleDateString([], { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });

            if (day !== currentDay) {
                currentDay = day;
                const heading = document.createElement('h3');
                heading.className = 'font-display sticky top-[3.5rem] z-10 -mx-1 mt-3 bg-paper/95 px-1 py-1 text-base text-orc-700 first:mt-0';
                heading.textContent = day;
                group = document.createElement('ol');
                list.append(heading, group);
            }

            group.appendChild(entryNode(event));
        });

        if (visible.length > limit) {
            const more = document.createElement('button');
            more.type = 'button';
            more.className = 'btn btn-outline mt-3 w-full';
            more.textContent = `Show older entries (${visible.length - limit} more)`;
            more.addEventListener('click', () => {
                limit += PAGE_SIZE;
                render();
            });
            list.appendChild(more);
        }
    };

    const add = (incoming = []) => {
        incoming.forEach((event) => {
            const existing = events.findIndex((candidate) => candidate.id === event.id);
            if (existing >= 0) {
                events[existing] = event;
            } else {
                events.push(event);
            }
        });

        refreshCharacterOptions();
        render();
    };

    [search, typeFilter, characterFilter].forEach((control) => control?.addEventListener('input', () => {
        limit = PAGE_SIZE;
        render();
    }));

    return { add };
};
