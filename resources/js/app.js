import { findKraftPlan, resourceBag, resourceNames, subtractBags } from './kraft.js';
import { createHistoryView } from './history.js';

// Dropdown menus (<details data-menu>) on every page: close on outside click or Escape.
document.addEventListener('click', (event) => {
    document.querySelectorAll('details[data-menu][open]').forEach((menu) => {
        if (! menu.contains(event.target)) {
            menu.removeAttribute('open');
        }
    });
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        document.querySelectorAll('details[data-menu][open]').forEach((menu) => menu.removeAttribute('open'));
    }
});

if (window.location.hash === '#new-campaign') {
    document.querySelector('#new-campaign input[name="name"]')?.focus();
}

const catalog = window.orcQuestCatalog;
const root = document.querySelector('[data-tracker-root]');

if (catalog && root) {
    const heroes = catalog.heroes;
    const items = catalog.items;
    const leftEnchants = catalog.leftEnchants;
    const rightEnchants = catalog.rightEnchants;
    const heroById = Object.fromEntries(heroes.map((hero) => [hero.id, hero]));
    const itemByIndex = Object.fromEntries(items.map((item) => [item.index, item]));

    const all = (selector, scope = document) => Array.from(scope.querySelectorAll(selector));
    const value = (element) => element?.value || '';
    const enchantCode = (enchantValue) => enchantValue.split(' - ')[0] || '';
    const itemLabel = (itemIndex) => itemByIndex[itemIndex]?.label || itemIndex;
    const resourceIconSrc = Object.fromEntries(resourceNames.map((resource) => [resource, `/images/icons/${resource}-resource.png`]));
    const healthColors = { Red: '#a8201a', Orange: '#d9741c', Yellow: '#d6b11c' };
    const saveStatusNodes = all('[data-save-status], [data-save-status-secondary]');
    const historyView = createHistoryView({ heroById });
    historyView.add(window.orcQuestHistory || []);

    // Extra context for the server-side history log, sent with the next save.
    let historyContext = {};

    const setSaveStatus = (message, tone = 'idle') => {
        saveStatusNodes.forEach((node) => {
            node.textContent = message;
            node.dataset.tone = tone;
        });
    };

    const readStored = (key) => {
        try {
            return window.localStorage?.getItem(key);
        } catch {
            return null;
        }
    };

    const writeStored = (key, stored) => {
        try {
            window.localStorage?.setItem(key, stored);
        } catch {
            // Private browsing: remembering the tab is a convenience only.
        }
    };

    const showTab = (tab) => {
        all('[data-tab]').forEach((button) => {
            button.setAttribute('aria-pressed', button.dataset.tab === tab ? 'true' : 'false');
        });

        all('[data-tab-panel]').forEach((panel) => {
            panel.hidden = panel.dataset.tabPanel !== tab;
        });

        writeStored('orcquest-active-tab', tab);
    };

    const tabs = ['characters', 'resources', 'campaign', 'history'];
    const initialTab = readStored('orcquest-active-tab');
    showTab(tabs.includes(initialTab) ? initialTab : 'characters');

    // ---- Characters: switching, adding, removing -------------------------------------------------------

    const characterCards = () => all('[data-character]');
    const activeInput = (card) => card.querySelector('[data-character-active]');
    const isActive = (card) => activeInput(card)?.value === '1';
    let shownCharacter = readStored('orcquest-active-character') || null;

    const showCharacter = (slot) => {
        const active = characterCards().filter(isActive);

        if (slot !== 'all' && ! active.some((card) => card.dataset.character === slot)) {
            slot = active[0]?.dataset.character || null;
        }

        shownCharacter = slot;
        writeStored('orcquest-active-character', slot || '');

        characterCards().forEach((card) => {
            card.hidden = ! isActive(card) || (slot !== 'all' && card.dataset.character !== slot);
        });

        all('[data-character-tab]').forEach((chip) => {
            chip.setAttribute('aria-pressed', chip.dataset.characterTab === slot ? 'true' : 'false');
        });
    };

    const refreshCharacterTabs = () => {
        const cards = characterCards();
        const activeCount = cards.filter(isActive).length;

        cards.forEach((card) => {
            const chip = document.querySelector(`[data-character-tab="${card.dataset.character}"]`);
            if (chip) {
                chip.hidden = ! isActive(card);
            }
        });

        all('[data-add-character]').forEach((button) => {
            button.hidden = activeCount >= cards.length;
        });
        document.querySelector('[data-character-tab="all"]')?.toggleAttribute('hidden', activeCount < 2);
        document.querySelector('[data-no-characters]')?.toggleAttribute('hidden', activeCount > 0);
        all('[data-remove-character]').forEach((button) => {
            button.disabled = activeCount <= 1;
        });

        showCharacter(shownCharacter);
    };

    const addCharacter = () => {
        const card = characterCards().find((candidate) => ! isActive(candidate));
        if (! card) {
            return;
        }

        activeInput(card).value = '1';
        shownCharacter = card.dataset.character;
        showTab('characters');
        refresh();
        commitHistory();
        scheduleAutoSave();
        card.querySelector('[data-hero-select]')?.focus();
    };

    const clearCharacter = (card) => {
        all('input[name], select[name], textarea[name]', card).forEach((control) => {
            if (control.matches('[data-character-active]')) {
                return;
            }

            if (control.type === 'checkbox') {
                control.checked = false;
            } else {
                control.value = '';
            }
        });
    };

    const removeCharacter = (card) => {
        const name = card.querySelector('[data-hero-name]')?.textContent || `Character ${card.dataset.character}`;
        const hasData = all('select[name], input[name]:not([type="hidden"])', card).some((control) => control.type === 'checkbox' ? control.checked : control.value);

        if (hasData && ! window.confirm(`Remove ${name} from the warband? Their skills, equipment and inventory will be cleared. (Undo can bring them back.)`)) {
            return;
        }

        clearCharacter(card);
        activeInput(card).value = '0';
        refresh();
        commitHistory();
        scheduleAutoSave();
    };

    const openModal = (name) => {
        const modal = document.querySelector(`[data-modal="${name}"]`);
        if (! modal) {
            return;
        }

        modal.classList.remove('hidden');
        document.documentElement.classList.add('overflow-hidden');
        modal.querySelector('[data-modal-close]')?.focus();
    };

    const closeModal = (modal = document.querySelector('[data-modal]:not(.hidden)')) => {
        modal?.classList.add('hidden');
        document.documentElement.classList.remove('overflow-hidden');
    };

    const showToast = (text) => {
        document.querySelector('[data-js-toast]')?.remove();
        const toast = document.createElement('div');
        toast.className = 'toast fixed inset-x-0 bottom-[5.5rem] z-50 mx-auto w-max max-w-[90vw] rounded-full bg-orc-600 px-4 py-2 text-center text-sm font-semibold text-white shadow-lg md:bottom-auto md:top-[4.25rem]';
        toast.dataset.jsToast = 'true';
        toast.setAttribute('role', 'status');
        toast.textContent = text;
        document.body.appendChild(toast);
        window.setTimeout(() => toast.remove(), 4600);
    };

    // ---- Undo / redo ---------------------------------------------------------------------------------

    const historyState = {
        undo: [],
        redo: [],
        current: null,
        restoring: false,
        limit: 25,
    };

    const historyControls = () => all('input[name], select[name], textarea[name]', root);

    const snapshotForm = () => {
        const entries = historyControls().map((control) => ({
            name: control.name,
            type: control.type,
            value: control.value,
            checked: control.matches('input[type="checkbox"], input[type="radio"]') ? control.checked : null,
        }));

        return {
            entries,
            signature: JSON.stringify(entries),
        };
    };

    const refreshHistoryButtons = () => {
        all('[data-undo]').forEach((button) => {
            button.disabled = historyState.undo.length === 0;
        });

        all('[data-redo]').forEach((button) => {
            button.disabled = historyState.redo.length === 0;
        });
    };

    const commitHistory = () => {
        if (historyState.restoring) {
            return;
        }

        const next = snapshotForm();

        if (! historyState.current) {
            historyState.current = next;
            refreshHistoryButtons();
            return;
        }

        if (next.signature === historyState.current.signature) {
            refreshHistoryButtons();
            return;
        }

        historyState.undo.push(historyState.current);
        if (historyState.undo.length > historyState.limit) {
            historyState.undo.shift();
        }

        historyState.current = next;
        historyState.redo = [];
        refreshHistoryButtons();
    };

    const restoreSnapshot = (snapshot, source) => {
        historyState.restoring = true;

        historyControls().forEach((control, index) => {
            const entry = snapshot.entries[index];
            if (! entry || entry.name !== control.name) {
                return;
            }

            if (control.matches('input[type="checkbox"], input[type="radio"]')) {
                control.checked = Boolean(entry.checked);
                return;
            }

            assign(control, entry.value);
        });

        historyState.restoring = false;
        historyContext.source = source;
        refresh();
        scheduleAutoSave();
        refreshHistoryButtons();
    };

    const undoTracker = () => {
        if (! historyState.undo.length || ! historyState.current) {
            return;
        }

        historyState.redo.push(historyState.current);
        historyState.current = historyState.undo.pop();
        restoreSnapshot(historyState.current, 'undo');
    };

    const redoTracker = () => {
        if (! historyState.redo.length || ! historyState.current) {
            return;
        }

        historyState.undo.push(historyState.current);
        historyState.current = historyState.redo.pop();
        restoreSnapshot(historyState.current, 'redo');
    };

    // ---- Item bundles (item + enchants + rekup travel together) ---------------------------------------

    const itemHands = (itemIndex) => Number(itemByIndex[itemIndex]?.hands || 0);

    const isTwoHanded = (itemIndex) => itemHands(itemIndex) >= 2;

    const itemAlwaysRekup = (itemIndex) => itemByIndex[itemIndex]?.always_rekup === true;

    const handSelect = (card, key) => card.querySelector(`select[name$="[${key}]"]`);

    const isInventorySelect = (select) => select?.name.includes('[inventory]');

    const rekupCheckboxFor = (select) => select?.closest('[data-inventory-row]')?.querySelector('[data-rekup]') || null;

    const isRekupInventoryItem = (select) => Boolean(isInventorySelect(select) && rekupCheckboxFor(select)?.checked);

    const enchantSelectsForItemSelect = (select) => {
        if (! select) {
            return { left: null, right: null };
        }

        if (isInventorySelect(select)) {
            const row = select.closest('[data-inventory-row]');
            return {
                left: row?.querySelector('select[name$="[left_enchant]"]') || null,
                right: row?.querySelector('select[name$="[right_enchant]"]') || null,
            };
        }

        const card = select.closest('[data-character]');
        const key = select.name.match(/\[equipment\]\[(.+)\]$/)?.[1];

        if (! card || ! key) {
            return { left: null, right: null };
        }

        const prefix = key.replace(/_item$/, '');

        return {
            left: card.querySelector(`select[name$="[${prefix}_left_enchant]"]`) || null,
            right: card.querySelector(`select[name$="[${prefix}_right_enchant]"]`) || null,
        };
    };

    const itemBundleForSelect = (select) => {
        const rekup = rekupCheckboxFor(select);
        const enchants = enchantSelectsForItemSelect(select);
        const item = select?.value || '';
        const canHaveEnchants = item && ! itemAlwaysRekup(item);

        return {
            item,
            rekup: Boolean(rekup?.checked),
            leftEnchant: canHaveEnchants ? (enchants.left?.value || '') : '',
            rightEnchant: canHaveEnchants ? (enchants.right?.value || '') : '',
        };
    };

    const setItemBundle = (select, bundle) => {
        if (! select) {
            return;
        }

        const item = bundle?.item || '';
        const enchants = enchantSelectsForItemSelect(select);
        const rekup = rekupCheckboxFor(select);
        const canHaveEnchants = item && ! itemAlwaysRekup(item);

        assign(select, item);

        if (enchants.left) {
            assign(enchants.left, canHaveEnchants ? bundle?.leftEnchant : '');
        }

        if (enchants.right) {
            assign(enchants.right, canHaveEnchants ? bundle?.rightEnchant : '');
        }

        if (rekup) {
            rekup.checked = item ? itemAlwaysRekup(item) || Boolean(bundle?.rekup) : false;
        }
    };

    const clearItemEnchantFields = (select) => {
        const enchants = enchantSelectsForItemSelect(select);
        if (enchants.left) {
            enchants.left.value = '';
        }
        if (enchants.right) {
            enchants.right.value = '';
        }
    };

    const firstEmptyInventorySelect = (card) => all('select[name*="[inventory]"][data-filter="item"]', card)
        .find((select) => ! select.value);

    const moveItemToInventory = (card, bundle) => {
        if (! bundle?.item) {
            return false;
        }

        const inventorySelect = firstEmptyInventorySelect(card);
        if (! inventorySelect) {
            return false;
        }

        setItemBundle(inventorySelect, bundle);

        return true;
    };

    const setFieldMuted = (control, muted) => {
        control?.closest('label')?.classList.toggle('opacity-50', muted);
    };

    // ---- Two-handed weapons ----------------------------------------------------------------------------

    let applyingHandRules = false;

    const setSecondHandMirror = (select, enabled) => {
        if (! select) {
            return;
        }

        const enchants = enchantSelectsForItemSelect(select);
        select.dataset.twoHandMirror = enabled ? 'true' : 'false';
        select.disabled = enabled || select.dataset.lockedByHero === 'true';

        [enchants.left, enchants.right].forEach((enchant) => {
            if (! enchant) {
                return;
            }

            if (enabled) {
                enchant.value = '';
            }

            enchant.disabled = enabled || enchant.dataset.lockedByHero === 'true';
            setFieldMuted(enchant, enabled || enchant.dataset.lockedByHero === 'true');
        });

        setFieldMuted(select, enabled || select.dataset.lockedByHero === 'true');
    };

    const applyHandRules = () => {
        if (applyingHandRules) {
            return;
        }

        applyingHandRules = true;

        characterCards().forEach((card) => {
            const first = handSelect(card, 'hand_item_1');
            const second = handSelect(card, 'hand_item_2');

            if (! first || ! second) {
                return;
            }

            if (! first.value && second.dataset.twoHandMirror === 'true') {
                second.value = '';
                clearItemEnchantFields(second);
                setSecondHandMirror(second, false);
                return;
            }

            if (isTwoHanded(second.value) && second.value !== first.value && second.dataset.twoHandMirror !== 'true') {
                moveItemToInventory(card, itemBundleForSelect(first));
                setItemBundle(first, itemBundleForSelect(second));
                setItemBundle(second, itemBundleForSelect(first));
            }

            if (isTwoHanded(first.value)) {
                if (second.value && second.value !== first.value) {
                    moveItemToInventory(card, itemBundleForSelect(second));
                }

                assign(second, first.value);
                clearItemEnchantFields(second);
                setSecondHandMirror(second, true);
            } else {
                if (second.dataset.twoHandMirror === 'true') {
                    second.value = '';
                    clearItemEnchantFields(second);
                }

                setSecondHandMirror(second, false);
            }
        });

        applyingHandRules = false;
    };

    // ---- Option lists (each card / hero / enchant can only be in one place) ----------------------------
    //
    // Item and enchant selects are rendered with only their current value; the full list (~250 cards) is
    // filled in when a select is opened. That keeps the page small, and availability is only computed for
    // the one list being looked at.

    const optionTemplates = {};

    const optionSource = (select) => {
        if (select.dataset.filter === 'item') {
            return 'item';
        }

        return select.dataset.filter === 'enchant' ? select.dataset.enchantSide : null;
    };

    const optionTemplate = (source) => {
        if (! optionTemplates[source]) {
            const fragment = document.createDocumentFragment();
            const entries = source === 'item'
                ? items.map((item) => [item.index, item.label])
                : (source === 'right' ? rightEnchants : leftEnchants).map((option) => [option.value, option.label]);
            entries.forEach(([optionValue, label]) => fragment.append(new Option(label, optionValue)));
            optionTemplates[source] = fragment;
        }

        return optionTemplates[source];
    };

    const optionLabel = (select, optionValue) => (select.dataset.filter === 'item' ? itemLabel(optionValue) : optionValue);

    /** Sets a select's value, adding the option first if a lazily filled list does not have it yet. */
    const assign = (control, nextValue) => {
        nextValue = nextValue || '';

        if (nextValue && control.tagName === 'SELECT' && ! Array.from(control.options).some((option) => option.value === nextValue)) {
            control.add(new Option(optionLabel(control, nextValue), nextValue));
        }

        control.value = nextValue;
    };

    const filterKey = (select) => (select.dataset.filter === 'enchant' ? enchantCode : (optionValue) => optionValue);

    /** Fills a select's full list (once) and hides options already used elsewhere (every time it opens). */
    const prepareOptions = (select) => {
        if (! select?.matches?.('select[data-filter]')) {
            return;
        }

        const source = optionSource(select);
        if (source && select.dataset.filled !== 'true') {
            const current = select.value;
            const placeholder = select.options[0]?.value === '' ? select.options[0] : new Option('None', '');
            select.replaceChildren(placeholder, optionTemplate(source).cloneNode(true));
            select.value = current;
            select.dataset.filled = 'true';
        }

        const keyOf = filterKey(select);
        const own = select.value ? keyOf(select.value) : '';
        const used = new Set(all(`select[data-filter="${select.dataset.filter}"]`)
            .filter((other) => other !== select && other.value)
            .map((other) => keyOf(other.value)));

        Array.from(select.options).forEach((option) => {
            // Never disable the select's own current option: browsers drop disabled selected options from
            // form submissions, which would erase the item.
            const unavailable = Boolean(option.value) && keyOf(option.value) !== own && used.has(keyOf(option.value));
            if (option.disabled !== unavailable) {
                option.disabled = unavailable;
                option.hidden = unavailable;
            }
        });
    };

    root.addEventListener('pointerdown', (event) => prepareOptions(event.target), true);
    root.addEventListener('focusin', (event) => prepareOptions(event.target));
    root.addEventListener('keydown', (event) => prepareOptions(event.target), true);

    // ---- Skills: hero-specific options; show filled slots plus one empty slot per tier -----------------

    const refreshSkills = () => {
        characterCards().forEach((card) => {
            const hero = heroById[value(card.querySelector('[data-hero-select]'))];
            const skills = hero?.skills || { 10: [], 20: [], 30: [] };
            const signature = `${hero?.id || ''}|${all('select[data-skill-tier]', card).map((select) => select.value).join('|')}`;

            if (card.dataset.skillSignature === signature) {
                return;
            }

            ['10', '20', '30'].forEach((tier) => {
                const selects = all(`select[data-skill-tier="${tier}"]`, card);
                let shownEmpty = false;

                selects.forEach((select) => {
                    const current = skills[tier]?.includes(select.value) ? select.value : '';
                    const used = selects
                        .filter((other) => other !== select)
                        .map((other) => other.value)
                        .filter(Boolean);

                    select.replaceChildren(new Option(hero ? 'Add a skill…' : 'Choose a hero first', ''));

                    (skills[tier] || []).forEach((skill) => {
                        if (used.includes(skill) && skill !== current) {
                            return;
                        }

                        select.add(new Option(skill, skill, false, skill === current));
                    });

                    select.value = current;

                    const slot = select.closest('[data-skill-slot]');
                    if (slot) {
                        slot.hidden = ! current && shownEmpty;
                        shownEmpty ||= ! current;
                    }
                });
            });

            card.dataset.skillSignature = `${hero?.id || ''}|${all('select[data-skill-tier]', card).map((select) => select.value).join('|')}`;
        });
    };

    // ---- Character header: facts, weights, chips, tray summaries ---------------------------------------

    const setWeightMeter = (meter, used, limit) => {
        if (! meter) {
            return false;
        }

        const over = Boolean(limit && used > limit);
        meter.dataset.over = over ? 'true' : 'false';
        meter.querySelector('[data-weight-value]').textContent = `${used}/${limit || '–'}`;
        meter.querySelector('[data-weight-bar]').style.width = `${limit ? Math.min(100, Math.round((used / limit) * 100)) : 0}%`;

        return over;
    };

    const plural = (count, singular, pluralLabel = `${singular}s`) => `${count} ${count === 1 ? singular : pluralLabel}`;

    const setTraySummary = (card, tray, text) => {
        const summary = card.querySelector(`[data-tray-summary="${tray}"]`);
        if (summary) {
            summary.textContent = text;
        }
    };

    const refreshTraySummaries = (card) => {
        const skillCounts = ['10', '20', '30'].map((tier) => {
            const count = all(`select[data-skill-tier="${tier}"]`, card).filter((select) => select.value).length;
            const counter = card.querySelector(`[data-skill-count="${tier}"]`);
            if (counter) {
                counter.textContent = count;
            }

            return count;
        });
        const totalSkills = skillCounts.reduce((total, count) => total + count, 0);
        setTraySummary(card, 'skills', totalSkills ? `${plural(totalSkills, 'card')} learned` : 'None learned');

        const equipped = all('select[data-filter="item"][name*="[equipment]"]', card)
            .filter((select) => select.value && select.dataset.twoHandMirror !== 'true').length;
        const enchantCount = all('select[data-filter="enchant"][name*="[equipment]"]', card).filter((select) => select.value).length;
        setTraySummary(card, 'equipment', equipped
            ? `${plural(equipped, 'item')} equipped${enchantCount ? ` · ${plural(enchantCount, 'enchant')}` : ''}`
            : 'Nothing equipped');

        const inventoryRows = all('[data-inventory-row]', card);
        const filledRows = inventoryRows.filter((row) => value(row.querySelector('select[data-filter="item"]')));
        const rekupRows = filledRows.filter((row) => row.querySelector('[data-rekup]')?.checked);
        setTraySummary(card, 'inventory', filledRows.length
            ? `${plural(filledRows.length - rekupRows.length, 'item')} · ${rekupRows.length} rekup · ${inventoryRows.length - filledRows.length} free`
            : 'Empty');
    };

    const heroFactPill = (text, color) => {
        const pill = document.createElement('span');
        pill.className = 'pill';

        if (color) {
            const dot = document.createElement('span');
            dot.className = 'size-2.5 rounded-full';
            dot.style.background = color;
            pill.append(dot);
        }

        pill.append(document.createTextNode(text));

        return pill;
    };

    const refreshHeroPanels = () => {
        characterCards().forEach((card) => {
            const slot = card.dataset.character;
            const hero = heroById[value(card.querySelector('[data-hero-select]'))];
            const heroIcon = card.querySelector('[data-hero-icon]');
            const facts = card.querySelector('[data-hero-facts]');
            const chip = document.querySelector(`[data-character-tab="${slot}"]`);
            const player = value(card.querySelector('[data-player-input]')).trim();

            card.querySelector('[data-hero-name]').textContent = hero?.name || 'Unassigned Hero';

            [heroIcon, chip?.querySelector('[data-chip-icon]')].forEach((icon) => {
                if (! icon) {
                    return;
                }

                if (hero?.icon) {
                    icon.src = hero.icon;
                    icon.hidden = false;
                } else {
                    icon.removeAttribute('src');
                    icon.hidden = true;
                }
            });

            if (heroIcon) {
                heroIcon.alt = hero ? `${hero.name} portrait` : '';
            }

            if (chip) {
                chip.querySelector('[data-chip-name]').textContent = hero?.name || `Character ${slot}`;
                chip.querySelector('[data-chip-player]').textContent = player || 'No player';
            }

            if (facts) {
                facts.replaceChildren();
                facts.hidden = ! hero;

                if (hero) {
                    facts.append(
                        heroFactPill(hero.class),
                        heroFactPill(`${hero.base_health} ${hero.health_color} health`, healthColors[hero.health_color] || '#776e62'),
                        heroFactPill(`Starts with ${hero.starting_stuff}`),
                    );
                }
            }

            all('[data-needs-hero]', card).forEach((control) => {
                const inventorySelect = control.matches('[data-rekup]')
                    ? control.closest('[data-inventory-row]')?.querySelector('select[data-filter="item"]')
                    : null;
                const alwaysRekup = itemAlwaysRekup(inventorySelect?.value);

                if (alwaysRekup) {
                    control.checked = true;
                }

                control.dataset.lockedByHero = hero ? 'false' : 'true';
                control.disabled = ! hero || control.dataset.twoHandMirror === 'true' || alwaysRekup;
                setFieldMuted(control, ! hero || alwaysRekup);
            });

            let inventoryWeight = 0;

            all('select[name*="[inventory]"][data-filter="item"]', card).forEach((select) => {
                const isRekup = rekupCheckboxFor(select)?.checked;
                inventoryWeight += isRekup ? 1 : Number(itemByIndex[select.value]?.weight || 0);
            });

            const firstHand = handSelect(card, 'hand_item_1');
            const secondHand = handSelect(card, 'hand_item_2');
            let handWeight = Number(itemByIndex[value(firstHand)]?.weight || 0);
            if (value(secondHand) && value(secondHand) !== value(firstHand)) {
                handWeight += Number(itemByIndex[value(secondHand)]?.weight || 0);
            }

            const armorWeight = Number(itemByIndex[value(card.querySelector('select[name$="[armor_item]"]'))]?.weight || 0);

            const overInventory = setWeightMeter(card.querySelector('[data-weight="inventory"]'), inventoryWeight, Number(hero?.inventory_weight_limit || 0));
            const overHands = setWeightMeter(card.querySelector('[data-weight="hands"]'), handWeight, Number(hero?.hand_weight_limit || 0));
            const overArmor = setWeightMeter(card.querySelector('[data-weight="armor"]'), armorWeight, Number(hero?.armor_weight_limit || 0));
            chip?.querySelector('[data-chip-alert]')?.toggleAttribute('hidden', ! (overInventory || overHands || overArmor));

            refreshTraySummaries(card);
        });
    };

    // ---- Rekup pool, upgrades and the kraft planner ----------------------------------------------------

    const characterName = (card) => heroById[value(card.querySelector('[data-hero-select]'))]?.name || `Character ${card.dataset.character}`;

    const currentResources = () => {
        const totals = resourceBag();

        all('select[name*="[inventory]"][data-filter="item"]').forEach((select) => {
            if (! rekupCheckboxFor(select)?.checked) {
                return;
            }

            const item = itemByIndex[select.value];
            resourceNames.forEach((resource) => {
                totals[resource] += Number(item?.rekup?.[resource] || 0);
            });
        });

        return totals;
    };

    const ownedItems = (card) => all('select[data-filter="item"]', card)
        .filter((select) => select.value && select.dataset.twoHandMirror !== 'true')
        .map((select) => ({
            key: `${card.dataset.character}|${select.name}`,
            index: select.value,
            item: itemByIndex[select.value],
            characterSlot: card.dataset.character,
            character: characterName(card),
            rekup: isRekupInventoryItem(select),
            location: select.dataset.location || 'Equipment',
            select,
        }));

    const canAfford = (item, resources) => resourceNames.every((resource) => Number(item?.kraft?.[resource] || 0) <= resources[resource]);

    const resourceIcon = (resource) => {
        const icon = document.createElement('img');
        icon.className = 'size-4 object-contain';
        icon.src = resourceIconSrc[resource];
        icon.alt = resource;
        icon.title = resource;

        return icon;
    };

    const appendResourceAmounts = (node, bag, options = {}) => {
        const resources = options.onlyPositive
            ? resourceNames.filter((resource) => Number(bag?.[resource] || 0) > 0)
            : resourceNames;

        if (! resources.length) {
            node.append(document.createTextNode(options.emptyText || 'Nothing missing'));
            return;
        }

        resources.forEach((resource) => {
            const amount = Number(bag?.[resource] || 0);
            const chip = document.createElement('span');
            chip.className = `inline-flex items-center gap-1 tabular-nums${amount ? '' : ' opacity-40'}`;
            chip.append(resourceIcon(resource), document.createTextNode(String(amount)));
            node.append(chip);
        });
    };

    const allOwnedUpgradeableItems = () => characterCards()
        .flatMap((card) => ownedItems(card))
        .filter((owned) => ! owned.rekup && owned.item?.can_upgrade);

    const paymentCandidatesFor = (targetKey) => characterCards().flatMap((card) => all('select[name*="[inventory]"][data-filter="item"]', card)
        .filter((select) => select.value)
        .map((select) => ({
            key: `${card.dataset.character}|${select.name}`,
            index: select.value,
            character: characterName(card),
            location: select.dataset.location,
            rekup: isRekupInventoryItem(select),
            resources: resourceBag(itemByIndex[select.value]?.rekup),
            select,
        }))
        .filter((candidate) => candidate.key !== targetKey));

    const ownedIndexes = () => new Set(all('select[data-filter="item"]').map((select) => select.value).filter(Boolean));

    const nextLevelFor = (item) => {
        if (! item?.level) {
            return null;
        }

        const owned = ownedIndexes();

        return items.find((candidate) => candidate.name === item.name && candidate.level === item.level + 1 && ! owned.has(candidate.index)) || null;
    };

    const currentKraftPlan = () => {
        const targets = allOwnedUpgradeableItems();
        const target = targets.find((owned) => owned.key === value(document.querySelector('[data-kraft-target]'))) || null;
        const strategy = value(document.querySelector('[data-kraft-strategy]')) || 'least_waste';

        return {
            target,
            plan: target ? findKraftPlan(target.item.kraft, paymentCandidatesFor(target.key), strategy) : null,
        };
    };

    const compactInventories = () => {
        characterCards().forEach((card) => {
            const selects = all('select[name*="[inventory]"][data-filter="item"]', card);
            const rows = selects
                .map((select) => itemBundleForSelect(select))
                .filter((row) => row.item);

            selects.forEach((select, index) => {
                setItemBundle(select, rows[index] || null);
            });
        });
    };

    const consumeKraftPaymentCards = () => {
        const { target, plan } = currentKraftPlan();

        if (! target || ! plan) {
            return;
        }

        const next = nextLevelFor(target.item);
        const paid = plan.cards.map((card) => `${itemLabel(card.index)} (${card.character})`).join(', ');
        const summary = next
            ? `Krafted ${target.item.label} into ${next.label}`
            : `Paid the kraft for ${target.item.label} (no free ${target.item.name} card of the next level to swap in)`;

        historyContext.kraft = {
            text: plan.cards.length ? `${summary}, consuming ${paid}` : `${summary} at no cost`,
            slot: target.characterSlot,
            consumed: plan.cards.map((card) => card.index),
        };

        plan.cards.forEach((card) => setItemBundle(card.select, null));

        // Enchanted stuff keeps its enchants when krafted, so only the item card changes.
        if (next) {
            assign(target.select, next.index);
        }

        compactInventories();
        refresh();
        commitHistory();
        scheduleAutoSave();
    };

    const emptyNote = (text, tone = 'text-muted') => {
        const note = document.createElement('p');
        note.className = `text-sm ${tone}`;
        note.textContent = text;

        return note;
    };

    const renderKraftSummary = (target, resources, plan) => {
        const summary = document.querySelector('[data-kraft-summary]');
        if (! summary) {
            return;
        }

        summary.replaceChildren();

        if (! target) {
            summary.append(emptyNote('Choose an upgrade target to plan its kraft.'));
            return;
        }

        const cost = resourceBag(target.item.kraft);
        const missing = subtractBags(cost, resources);
        const next = nextLevelFor(target.item);
        const rows = [
            ['Target', { text: `${target.item.label} · ${target.character}, ${target.location}` }],
            ['Becomes', { text: next ? next.label : `No free ${target.item.name} card of the next level` }],
            ['Kraft cost', { bag: cost }],
            ['Rekup pool', { bag: resources }],
            ['Missing now', { bag: missing, onlyPositive: true, emptyText: 'Nothing, the pool covers it', alarm: true }],
            ['Payment', { bag: plan?.resources || resourceBag() }],
            ['Wasted', { bag: plan?.waste || resourceBag() }],
        ];

        rows.forEach(([label, detail]) => {
            const alarm = detail.alarm && resourceNames.some((resource) => missing[resource] > 0);
            const row = document.createElement('div');
            row.className = 'grid grid-cols-[6.5rem_minmax(0,1fr)] items-center gap-2 border-t border-line py-1.5 first:border-t-0';
            const title = document.createElement('span');
            title.className = `text-[11px] font-semibold uppercase tracking-wide ${alarm ? 'text-danger' : 'text-muted'}`;
            title.textContent = label;
            const valueNode = document.createElement('span');
            valueNode.className = `flex flex-wrap items-center gap-x-3 gap-y-1 text-sm font-medium ${alarm ? 'text-danger' : 'text-ink'}`;

            if (detail.text) {
                valueNode.textContent = detail.text;
            } else {
                appendResourceAmounts(valueNode, detail.bag, detail);
            }

            row.append(title, valueNode);
            summary.appendChild(row);
        });
    };

    const renderKraftSuggestion = (target, plan) => {
        const suggestion = document.querySelector('[data-kraft-suggestion]');
        if (! suggestion) {
            return;
        }

        suggestion.replaceChildren();

        if (! target) {
            suggestion.append(emptyNote('The exact cards to consume will appear here.'));
            return;
        }

        if (! plan) {
            suggestion.append(emptyNote('No combination of inventory cards can pay this kraft cost yet.', 'text-danger font-medium'));
            return;
        }

        const next = nextLevelFor(target.item);
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-primary mb-3 w-full';
        button.dataset.consumeKraftCards = 'true';
        button.textContent = next
            ? (plan.cards.length ? `Kraft it: consume ${plural(plan.cards.length, 'card')} → ${next.label}` : `Kraft it → ${next.label}`)
            : `Consume ${plural(plan.cards.length, 'payment card')}`;

        if (plan.free) {
            suggestion.append(emptyNote('This upgrade has no kraft cost.', 'mb-2 text-sm font-medium text-orc-700'), button);
            return;
        }

        suggestion.append(button);

        const needsMarking = plan.cards.filter((card) => ! card.rekup).length;
        if (needsMarking) {
            suggestion.append(emptyNote(`${plural(needsMarking, 'card')} below ${needsMarking === 1 ? 'is' : 'are'} still carried as gear; the pool alone can't pay this.`, 'mb-2 text-xs font-medium text-[#8a4210]'));
        }

        const list = document.createElement('ul');
        plan.cards.forEach((card) => {
            const row = document.createElement('li');
            row.className = 'flex items-start justify-between gap-2 border-t border-line py-2 first:border-t-0';
            const left = document.createElement('div');
            left.className = 'min-w-0';
            const name = document.createElement('strong');
            name.className = 'block text-sm font-semibold';
            name.textContent = itemLabel(card.index);
            const meta = document.createElement('span');
            meta.className = 'mt-0.5 flex flex-wrap items-center gap-x-2.5 gap-y-1 text-xs text-muted';
            meta.append(document.createTextNode(`${card.character} · ${card.location}`));
            appendResourceAmounts(meta, card.resources, { onlyPositive: true });
            left.append(name, meta);

            const status = document.createElement('span');
            status.className = card.rekup
                ? 'shrink-0 rounded-full bg-sage-soft px-2 py-0.5 text-[11px] font-semibold text-orc-700'
                : 'shrink-0 rounded-full bg-ember/15 px-2 py-0.5 text-[11px] font-semibold text-[#8a4210]';
            status.textContent = card.rekup ? 'In pool' : 'Carried';
            row.append(left, status);
            list.appendChild(row);
        });
        suggestion.append(list);
    };

    const renderAlmostCraftable = (targets, resources) => {
        const list = document.querySelector('[data-almost-craftable]');
        if (! list) {
            return;
        }

        list.replaceChildren();

        const rows = targets
            .map((target) => ({ target, missing: subtractBags(resourceBag(target.item.kraft), resources) }))
            .filter(({ missing }) => resourceNames.some((resource) => missing[resource] > 0))
            .sort((left, right) => resourceNames.reduce((sum, resource) => sum + left.missing[resource], 0) - resourceNames.reduce((sum, resource) => sum + right.missing[resource], 0))
            .slice(0, 9);

        if (! rows.length) {
            list.append(emptyNote('No blocked upgrades right now.'));
            return;
        }

        rows.forEach(({ target, missing }) => {
            const card = document.createElement('button');
            card.type = 'button';
            card.className = 'rounded-md border border-line bg-white/70 p-2.5 text-left hover:border-orc-600';
            card.dataset.kraftPick = target.key;
            card.title = 'Plan this kraft';
            const title = document.createElement('strong');
            title.className = 'block text-sm font-semibold';
            title.textContent = target.item.label;
            const meta = document.createElement('span');
            meta.className = 'block text-xs text-muted';
            meta.textContent = `${target.character} · ${target.location}`;
            const need = document.createElement('span');
            need.className = 'mt-1.5 flex flex-wrap items-center gap-x-2.5 gap-y-1 text-xs font-semibold text-[#8a4210]';
            need.append(document.createTextNode('Needs'));
            appendResourceAmounts(need, missing, { onlyPositive: true });
            card.append(title, meta, need);
            list.appendChild(card);
        });
    };

    const refreshKraftPlanner = (resources) => {
        const targets = allOwnedUpgradeableItems();
        const select = document.querySelector('[data-kraft-target]');

        if (select) {
            const current = select.value;
            select.replaceChildren(new Option(targets.length ? 'Choose an owned upgradeable item' : 'No upgradeable items owned', ''));
            targets.forEach((target) => select.add(new Option(`${target.item.label} · ${target.character}, ${target.location}`, target.key)));
            select.value = targets.some((target) => target.key === current) ? current : '';
        }

        const { target, plan } = currentKraftPlan();
        renderKraftSummary(target, resources, plan);
        renderKraftSuggestion(target, plan);
        renderAlmostCraftable(targets, resources);
    };

    const refreshResourcesAndUpgrades = () => {
        const resources = currentResources();

        Object.entries(resources).forEach(([resource, amount]) => {
            const node = document.querySelector(`[data-resource="${resource}"]`);
            if (node) {
                node.textContent = amount;
            }
        });

        const upgrades = allOwnedUpgradeableItems().filter((owned) => canAfford(owned.item, resources));
        const count = document.querySelector('[data-upgrades-count]');
        if (count) {
            count.textContent = upgrades.length;
        }

        const list = document.querySelector('[data-upgrades-list]');
        if (list) {
            list.replaceChildren();

            if (! upgrades.length) {
                list.append(emptyNote('Nothing can be krafted from the current pool.'));
            }

            upgrades.forEach((upgrade) => {
                const row = document.createElement('button');
                row.type = 'button';
                row.className = 'grid w-full gap-1 border-t border-line px-1 py-2 text-left first:border-t-0 hover:bg-paper-deep sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center sm:gap-3';
                row.dataset.kraftPick = upgrade.key;
                row.title = 'Plan this kraft';
                const title = document.createElement('span');
                title.className = 'min-w-0';
                const name = document.createElement('strong');
                name.className = 'block text-sm font-semibold';
                name.textContent = upgrade.item.label;
                const where = document.createElement('span');
                where.className = 'block text-xs text-muted';
                where.textContent = `${upgrade.character} · ${upgrade.location}`;
                title.append(name, where);
                const cost = document.createElement('span');
                cost.className = 'flex flex-wrap items-center gap-x-2.5 gap-y-1 text-xs text-ink-soft';
                appendResourceAmounts(cost, upgrade.item.kraft, { onlyPositive: true, emptyText: 'Free' });
                row.append(title, cost);
                list.appendChild(row);
            });
        }

        refreshKraftPlanner(resources);
    };

    // The planner runs a search over every inventory card, so it only renders while its tab is open.
    let resourcesDirty = true;

    const refreshResourcesIfVisible = () => {
        resourcesDirty = true;

        if (! document.querySelector('[data-tab-panel="resources"]')?.hidden) {
            refreshResourcesAndUpgrades();
            resourcesDirty = false;
        }
    };

    const pickKraftTarget = (key) => {
        const select = document.querySelector('[data-kraft-target]');
        if (! select) {
            return;
        }

        select.value = key;
        refreshResourcesAndUpgrades();
        select.closest('.panel')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    const refreshCampaignCount = () => {
        const completed = all('input[name^="campaign["]:checked').length;
        const campaignCount = document.querySelector('[data-campaign-count]');
        if (campaignCount) {
            campaignCount.textContent = completed;
        }

        all('[data-campaign-group]').forEach((group) => {
            group.querySelector('[data-campaign-group-count]').textContent = all('input[type="checkbox"]:checked', group).length;
        });
    };

    // ---- Tooltips --------------------------------------------------------------------------------------

    const refreshItemTooltips = () => {
        all('select[data-filter="item"]').forEach((select) => {
            if (select.dataset.tipFor === select.value) {
                return;
            }

            select.dataset.tipFor = select.value;
            const item = itemByIndex[select.value];

            if (! item) {
                select.removeAttribute('title');
                return;
            }

            const lines = [
                item.label,
                item.level ? `Level ${item.level} of 3` : 'No kraft levels',
                `Weight: ${item.weight} · Hands: ${item.hands}`,
                `Rekup: ${resourceNames.map((resource) => `${item.rekup[resource]} ${resource}`).join(', ')}`,
            ];

            if (item.can_upgrade) {
                lines.push(`Kraft: ${resourceNames.map((resource) => `${item.kraft[resource]} ${resource}`).join(', ')}`);
            }

            if (item.notes) {
                lines.push('', item.notes);
            }

            select.title = lines.join('\n');
        });
    };

    const refreshEnchantTooltips = () => {
        all('select[data-filter="enchant"]').forEach((select) => {
            if (select.dataset.tipFor === select.value) {
                return;
            }

            select.dataset.tipFor = select.value;
            const options = select.dataset.enchantSide === 'right' ? rightEnchants : leftEnchants;
            const enchant = options.find((option) => option.value === select.value);

            if (! enchant) {
                select.removeAttribute('title');
                return;
            }

            select.title = select.dataset.enchantSide === 'right'
                ? [select.value, 'Enhanced effect:', enchant.effect || 'No effect listed.'].join('\n')
                : [select.value, 'Effect:', enchant.effect || 'No effect listed.', '', 'Enhance objective:', enchant.condition || 'No condition listed.'].join('\n');
        });
    };

    // ---- Auto-save -------------------------------------------------------------------------------------

    let autoSaveTimer = null;
    let saving = null;
    let saveAgain = false;
    let campaignVersion = Number(root.dataset.version || 1);
    let saveBlocked = null;

    const hasUnsavedWork = () => Boolean(autoSaveTimer || saving);

    const blockSaving = (reason, message, action) => {
        saveBlocked = reason;
        window.clearTimeout(autoSaveTimer);
        autoSaveTimer = null;
        setSaveStatus(reason === 'conflict' ? 'Not saved: changed elsewhere' : 'Not saved: signed out', 'error');

        document.querySelector('[data-save-banner]')?.remove();
        const banner = document.createElement('div');
        banner.dataset.saveBanner = 'true';
        banner.className = 'fixed inset-x-3 top-[4.5rem] z-50 mx-auto flex max-w-xl flex-col gap-2 rounded-lg bg-blood px-4 py-3 text-sm text-white shadow-2xl sm:flex-row sm:items-center';
        banner.setAttribute('role', 'alert');
        const text = document.createElement('p');
        text.className = 'flex-1';
        text.textContent = message;
        const button = document.createElement('a');
        button.className = 'btn btn-bone shrink-0';
        button.href = action.href;
        button.textContent = action.label;
        banner.append(text, button);
        document.body.appendChild(banner);
    };

    /** Saves are queued, never cancelled: a cancelled request may still land, which would desync versions. */
    const saveNow = async () => {
        window.clearTimeout(autoSaveTimer);
        autoSaveTimer = null;

        if (saveBlocked) {
            return;
        }

        if (saving) {
            saveAgain = true;
            return saving;
        }

        setSaveStatus('Saving…', 'pending');

        const body = new FormData(root);
        const context = historyContext;
        historyContext = {};
        body.append('version', String(campaignVersion));

        if (context.source) {
            body.append('history_source', context.source);
        }

        if (context.kraft) {
            body.append('history_kraft', context.kraft.text);
            body.append('history_kraft_slot', context.kraft.slot);
            context.kraft.consumed.forEach((index) => body.append('history_consumed[]', index));
        }

        saving = (async () => {
            try {
                const response = await fetch(root.action, {
                    method: 'POST',
                    body,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                    credentials: 'same-origin',
                });

                if (response.status === 409) {
                    blockSaving('conflict', 'This campaign was changed in another tab or on another device since you opened it, so your latest change was not saved. Reload to get the current version.', { href: window.location.href, label: 'Reload campaign' });
                    return;
                }

                if (response.status === 401 || response.status === 419) {
                    blockSaving('auth', 'Your session has ended, so your latest change was not saved. Sign in again, then redo it.', { href: '/login', label: 'Sign in' });
                    return;
                }

                if (! response.ok) {
                    throw new Error(`Save failed with status ${response.status}`);
                }

                const result = await response.json();
                campaignVersion = result.version;
                historyView.add(result.events || []);
                setSaveStatus(`Saved ${new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })}`, 'saved');
            } catch {
                historyContext = { ...context, ...historyContext };
                setSaveStatus('Save failed, retrying…', 'error');
                window.clearTimeout(autoSaveTimer);
                autoSaveTimer = window.setTimeout(saveNow, 5000);
            }
        })();

        await saving;
        saving = null;

        if (saveAgain) {
            saveAgain = false;
            await saveNow();
        }
    };

    const scheduleAutoSave = () => {
        if (saveBlocked) {
            return;
        }

        setSaveStatus('Unsaved changes…', 'pending');
        window.clearTimeout(autoSaveTimer);
        autoSaveTimer = window.setTimeout(saveNow, 900);
    };

    // Finish saving before following a link (campaign switcher, account, ...); warn if the tab is closed mid-save.
    document.addEventListener('click', async (event) => {
        const link = event.target.closest?.('a[href]');
        if (! link || link.target || event.defaultPrevented || event.metaKey || event.ctrlKey || ! hasUnsavedWork() || saveBlocked) {
            return;
        }

        event.preventDefault();
        await saveNow();
        window.location.href = link.href;
    });

    document.addEventListener('submit', async (event) => {
        if (event.target === root) {
            event.preventDefault();
            saveNow();
            return;
        }

        if (hasUnsavedWork() && ! saveBlocked) {
            event.preventDefault();
            await saveNow();
            event.target.submit();
        }
    });

    window.addEventListener('beforeunload', (event) => {
        if (hasUnsavedWork() && ! saveBlocked) {
            event.preventDefault();
        }
    });

    // ---- Clear buttons ---------------------------------------------------------------------------------

    const prepareClearButtons = () => {
        all('select[data-filter], select[data-skill-tier]').forEach((select) => {
            if (select.dataset.clearReady === 'true') {
                return;
            }

            select.dataset.clearReady = 'true';

            const wrapper = document.createElement('div');
            wrapper.className = 'select-wrap relative';
            select.replaceWith(wrapper);

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'absolute right-7 top-1/2 grid size-6 -translate-y-1/2 place-items-center rounded-full text-muted hover:bg-danger/10 hover:text-danger';
            button.dataset.clearSelect = 'true';
            button.title = 'Clear';
            button.setAttribute('aria-label', 'Clear this selection');
            button.innerHTML = '<svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>';

            wrapper.append(select, button);
        });
    };

    const refreshClearButtons = () => {
        all('[data-clear-select]').forEach((button) => {
            const select = button.previousElementSibling;
            button.classList.toggle('hidden', ! select?.value || select.disabled);
        });
    };

    // ---- Inventory locks -------------------------------------------------------------------------------

    const refreshAlwaysRekupLocks = () => {
        all('select[name*="[inventory]"][data-filter="item"]').forEach((select) => {
            const rekup = rekupCheckboxFor(select);
            const row = select.closest('[data-inventory-row]');
            const empty = ! select.value;
            const locked = itemAlwaysRekup(select.value);

            if (row) {
                row.dataset.empty = empty ? 'true' : 'false';
            }

            if (! rekup) {
                return;
            }

            if (locked) {
                rekup.checked = true;
            } else if (empty) {
                rekup.checked = false;
            }

            const disabled = empty || locked || rekup.dataset.lockedByHero === 'true';
            rekup.disabled = disabled;
            rekup.closest('label').title = locked ? 'This card is always used as rekup.' : 'Break this card down into resources';
            setFieldMuted(rekup, disabled && ! locked);
        });
    };

    const refreshItemEnchantLocks = () => {
        all('select[data-filter="item"]').forEach((select) => {
            const lockedByHero = select.dataset.lockedByHero === 'true';
            const mirrored = select.dataset.twoHandMirror === 'true';
            const emptyInventory = isInventorySelect(select) && ! select.value;
            const alwaysRekupInventory = isInventorySelect(select) && itemAlwaysRekup(select.value);
            const enchants = enchantSelectsForItemSelect(select);

            [enchants.left, enchants.right].forEach((enchant) => {
                if (! enchant) {
                    return;
                }

                if (emptyInventory || mirrored || alwaysRekupInventory) {
                    enchant.value = '';
                }

                const disabled = lockedByHero || emptyInventory || mirrored || alwaysRekupInventory;
                enchant.disabled = disabled;
                setFieldMuted(enchant, disabled);
            });
        });
    };

    // ---- Drag and drop (mouse on the item field, touch/pen on the grip handles) ------------------------

    const slotItemSelect = (element) => element?.closest?.('[data-slot]')?.querySelector('select[data-filter="item"]') || null;

    const dragSourceSelect = (target) => {
        if (target.matches?.('select[data-filter="item"]')) {
            return target;
        }

        return target.closest?.('[data-drag-handle]') ? slotItemSelect(target) : null;
    };

    /**
     * Why an item cannot go to a slot (null when it can). A card marked for rekup counts as destroyed, so it
     * stays in inventory until the player unticks Rekup; always-rekup cards (resources, gold, potions) can
     * never be equipped.
     */
    const dropBlockReason = (source, destination) => {
        if (! destination) {
            return 'Inventory full';
        }

        if (destination.dataset.lockedByHero === 'true' || source.dataset.lockedByHero === 'true') {
            return 'Choose a hero first';
        }

        if (destination.dataset.twoHandMirror === 'true') {
            return 'Two-handed weapon in Hand 1';
        }

        if (! isInventorySelect(destination) && itemAlwaysRekup(source.value)) {
            return 'Resources, gold and potions can\'t be equipped';
        }

        if (! isInventorySelect(destination) && isRekupInventoryItem(source)) {
            return 'Marked for rekup (destroyed): untick Rekup first to equip it';
        }

        if (! isInventorySelect(source) && isRekupInventoryItem(destination)) {
            return 'That slot holds a card marked for rekup';
        }

        return source.disabled || destination.disabled ? 'Unavailable' : null;
    };

    const canDropItem = (source, destination) => Boolean(source && destination && source !== destination && ! dropBlockReason(source, destination));

    let pointerDrag = null;
    let suppressNextClick = false;

    const setDropTarget = (select, state = 'true') => {
        all('[data-slot][data-drop-target]').forEach((slot) => slot.removeAttribute('data-drop-target'));
        select?.closest('[data-slot]')?.setAttribute('data-drop-target', state);
    };

    const createDragGhost = (select) => {
        const ghost = document.createElement('div');
        ghost.className = 'pointer-events-none fixed z-50 max-w-72 truncate rounded-md border border-orc-600 bg-paper px-3 py-2 text-sm font-semibold text-orc shadow-xl';
        ghost.textContent = itemLabel(select.value);
        document.body.appendChild(ghost);

        return ghost;
    };

    const moveGhost = (ghost, x, y) => {
        ghost.style.left = `${x + 14}px`;
        ghost.style.top = `${y + 14}px`;
    };

    const closeOpenDropdown = () => {
        if (document.activeElement instanceof HTMLElement) {
            document.activeElement.blur();
        }
    };

    const swapItemSelects = (source, destination) => {
        const sourceBundle = itemBundleForSelect(source);
        const destinationBundle = itemBundleForSelect(destination);

        setItemBundle(source, destinationBundle);
        setItemBundle(destination, sourceBundle);

        refresh();
        commitHistory();
        scheduleAutoSave();
    };

    const endDrag = () => {
        pointerDrag?.source.closest('[data-slot]')?.classList.remove('opacity-50');
        pointerDrag?.ghost?.remove();
        setDropTarget(null);
        pointerDrag = null;
    };

    root.addEventListener('pointerdown', (event) => {
        const select = dragSourceSelect(event.target);

        if (! select || ! select.value || event.button !== 0) {
            return;
        }

        pointerDrag = {
            source: select,
            startX: event.clientX,
            startY: event.clientY,
            active: false,
            ghost: null,
            target: null,
        };
    });

    window.addEventListener('pointermove', (event) => {
        if (! pointerDrag) {
            return;
        }

        if (! pointerDrag.active && Math.hypot(event.clientX - pointerDrag.startX, event.clientY - pointerDrag.startY) < 12) {
            return;
        }

        if (! pointerDrag.active) {
            pointerDrag.active = true;
            suppressNextClick = true;
            closeOpenDropdown();
            pointerDrag.ghost = createDragGhost(pointerDrag.source);
            pointerDrag.source.closest('[data-slot]')?.classList.add('opacity-50');
        }

        event.preventDefault();
        moveGhost(pointerDrag.ghost, event.clientX, event.clientY);

        const destination = slotItemSelect(document.elementFromPoint(event.clientX, event.clientY));
        const blocked = destination && destination !== pointerDrag.source ? dropBlockReason(pointerDrag.source, destination) : null;
        pointerDrag.target = canDropItem(pointerDrag.source, destination) ? destination : null;
        pointerDrag.blocked = blocked;
        setDropTarget(pointerDrag.target || (blocked ? destination : null), blocked ? 'blocked' : 'true');
    }, { passive: false });

    window.addEventListener('pointerup', () => {
        if (! pointerDrag) {
            return;
        }

        const wasDragging = pointerDrag.active;
        const { source, target, blocked } = pointerDrag;
        endDrag();

        if (wasDragging && canDropItem(source, target)) {
            swapItemSelects(source, target);
        } else if (wasDragging && blocked) {
            showToast(blocked);
        }

        if (wasDragging) {
            closeOpenDropdown();
            window.setTimeout(() => {
                suppressNextClick = false;
            }, 250);
        }
    });

    window.addEventListener('pointercancel', () => {
        if (! pointerDrag) {
            return;
        }

        endDrag();
        suppressNextClick = false;
    });

    // ---- Tap to move (a phone-friendly alternative to dragging across the page) ------------------------

    let moveSource = null;
    let moveTargets = [];

    const moveSourceFor = (element) => {
        let select = slotItemSelect(element);

        // The mirrored second hand stands for the two-handed weapon held in Hand 1.
        if (select?.dataset.twoHandMirror === 'true') {
            select = handSelect(select.closest('[data-character]'), 'hand_item_1');
        }

        return select?.value ? select : null;
    };

    const moveBlockReason = (source, destination) => {
        if (! destination) {
            return 'Inventory full';
        }

        if (destination.dataset.lockedByHero === 'true') {
            return 'Choose a hero first';
        }

        if (destination.dataset.twoHandMirror === 'true') {
            return 'Two-handed weapon in Hand 1';
        }

        return dropBlockReason(source, destination);
    };

    const moveOption = (label, destination, occupant, source) => {
        const reason = moveBlockReason(source, destination);
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'flex min-h-12 w-full items-center gap-3 rounded-md border border-line bg-white px-3 py-2 text-left transition-colors enabled:hover:border-orc-600 enabled:hover:bg-sage-soft enabled:active:bg-sage-soft disabled:cursor-not-allowed disabled:bg-paper-deep/60';
        button.disabled = Boolean(reason);

        if (! reason) {
            button.dataset.moveTo = String(moveTargets.push(destination) - 1);
        }

        const name = document.createElement('span');
        name.className = 'w-[4.5rem] shrink-0 text-xs font-semibold uppercase tracking-wide text-muted';
        name.textContent = label;

        const detail = document.createElement('span');
        detail.className = `min-w-0 flex-1 truncate text-sm ${occupant.filled ? 'font-medium text-ink' : 'text-muted'}`;
        detail.textContent = occupant.text;

        const action = document.createElement('span');
        action.className = `shrink-0 text-[11px] font-semibold ${reason ? 'text-muted' : 'text-orc-700'}`;
        action.textContent = reason || (occupant.filled ? 'Swap' : 'Move here');

        button.append(name, detail, action);

        return button;
    };

    const openMoveSheet = (source) => {
        const list = document.querySelector('[data-move-list]');
        if (! list) {
            return;
        }

        const sourceCard = source.closest('[data-character]');
        moveSource = source;
        moveTargets = [];
        document.querySelector('[data-move-title]').textContent = itemLabel(source.value);
        document.querySelector('[data-move-from]').textContent = `Move from ${characterName(sourceCard)} · ${source.dataset.location}`;
        list.replaceChildren();

        const cards = characterCards().filter(isActive);
        [sourceCard, ...cards.filter((card) => card !== sourceCard)].forEach((card) => {
            const section = document.createElement('section');
            section.className = 'mb-4 last:mb-0';

            const heading = document.createElement('h3');
            heading.className = 'mb-1.5 flex items-center gap-2';
            const hero = heroById[value(card.querySelector('[data-hero-select]'))];
            if (hero?.icon) {
                const icon = document.createElement('img');
                icon.className = 'size-7 rounded-full object-cover ring-1 ring-black/10';
                icon.src = hero.icon;
                icon.alt = '';
                heading.append(icon);
            }
            const name = document.createElement('span');
            name.className = 'font-display text-lg text-orc-700';
            name.textContent = characterName(card);
            heading.append(name);
            if (card === sourceCard) {
                const tag = document.createElement('span');
                tag.className = 'rounded-full bg-paper-deep px-2 py-0.5 text-[11px] font-semibold text-muted';
                tag.textContent = 'Carrying it now';
                heading.append(tag);
            }

            const options = document.createElement('div');
            options.className = 'grid gap-1.5';

            all('select[data-filter="item"][name*="[equipment]"]', card)
                .filter((select) => select !== source)
                .forEach((select) => {
                    const occupant = select.dataset.twoHandMirror === 'true'
                        ? { text: `${itemLabel(select.value)} (both hands)`, filled: true }
                        : { text: select.value ? itemLabel(select.value) : 'Empty', filled: Boolean(select.value) };
                    options.append(moveOption(select.dataset.location, select, occupant, source));
                });

            if (! (card === sourceCard && isInventorySelect(source))) {
                const free = firstEmptyInventorySelect(card);
                options.append(moveOption('Inventory', free, { text: free ? `${free.dataset.location} is free` : 'No free slot', filled: false }, source));
            }

            section.append(heading, options);
            list.append(section);
        });

        openModal('move-item');
    };

    const moveItemTo = (position) => {
        const destination = moveTargets[position];

        if (! destination || ! moveSource) {
            return;
        }

        const label = itemLabel(moveSource.value);
        const where = `${characterName(destination.closest('[data-character]'))} · ${destination.dataset.location}`;
        closeModal(document.querySelector('[data-modal="move-item"]'));
        swapItemSelects(moveSource, destination);
        moveSource = null;
        moveTargets = [];
        showToast(`Moved ${label} to ${where}`);
    };

    const refreshMoveButtons = () => {
        all('[data-move-item][data-needs-item]').forEach((button) => {
            button.hidden = ! slotItemSelect(button)?.value;
        });
    };

    // ---- Clicks ----------------------------------------------------------------------------------------

    document.addEventListener('click', (event) => {
        const target = event.target;

        if (suppressNextClick) {
            event.preventDefault();
            event.stopPropagation();
            suppressNextClick = false;
            return;
        }

        const modalOpen = target.closest?.('[data-modal-open]');
        if (modalOpen) {
            openModal(modalOpen.dataset.modalOpen);
            return;
        }

        if (target.closest?.('[data-modal-close]')) {
            closeModal(target.closest('[data-modal]'));
            return;
        }

        if (target.matches?.('[data-modal]')) {
            closeModal(target);
            return;
        }

        const moveButton = target.closest?.('[data-move-item]');
        if (moveButton) {
            const source = moveSourceFor(moveButton);
            if (source) {
                openMoveSheet(source);
            }
            return;
        }

        const moveDestination = target.closest?.('[data-move-to]');
        if (moveDestination) {
            moveItemTo(Number(moveDestination.dataset.moveTo));
            return;
        }

        const tabButton = target.closest?.('[data-tab]');
        if (tabButton) {
            showTab(tabButton.dataset.tab);
            if (tabButton.dataset.tab === 'resources' && resourcesDirty) {
                refreshResourcesIfVisible();
            }
            return;
        }

        const characterTab = target.closest?.('[data-character-tab]');
        if (characterTab) {
            showCharacter(characterTab.dataset.characterTab);
            return;
        }

        if (target.closest?.('[data-add-character]')) {
            addCharacter();
            return;
        }

        const removeButton = target.closest?.('[data-remove-character]');
        if (removeButton) {
            removeCharacter(removeButton.closest('[data-character]'));
            return;
        }

        if (target.closest?.('[data-save-now]')) {
            saveNow();
            return;
        }

        if (target.closest?.('[data-undo]')) {
            undoTracker();
            return;
        }

        if (target.closest?.('[data-redo]')) {
            redoTracker();
            return;
        }

        if (target.closest?.('[data-consume-kraft-cards]')) {
            consumeKraftPaymentCards();
            return;
        }

        const kraftPick = target.closest?.('[data-kraft-pick]');
        if (kraftPick) {
            pickKraftTarget(kraftPick.dataset.kraftPick);
            return;
        }

        const clearButton = target.closest?.('[data-clear-select]');
        if (clearButton) {
            const select = clearButton.previousElementSibling;
            if (select && ! select.disabled) {
                select.value = '';
                if (isInventorySelect(select)) {
                    rekupCheckboxFor(select).checked = false;
                }
                select.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
    }, true);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeModal();
        }
    });

    // ---- Refresh cycle ---------------------------------------------------------------------------------

    const refresh = () => {
        prepareClearButtons();
        refreshSkills();
        applyHandRules();
        refreshHeroPanels();
        refreshAlwaysRekupLocks();
        refreshItemEnchantLocks();
        refreshResourcesIfVisible();
        refreshCampaignCount();
        refreshItemTooltips();
        refreshEnchantTooltips();
        refreshClearButtons();
        refreshMoveButtons();
        refreshCharacterTabs();
    };

    root.addEventListener('change', (event) => {
        if (event.target.matches('[data-kraft-target], [data-kraft-strategy]')) {
            refreshResourcesIfVisible();
            return;
        }

        refresh();

        if (event.target.name) {
            scheduleAutoSave();
            commitHistory();
        }
    });

    root.addEventListener('input', (event) => {
        // Selects and checkboxes also fire "change", which refreshes; refreshing here too doubled the work.
        if (event.target.matches('[data-player-input]')) {
            refreshHeroPanels();
        } else if (event.target.matches('[data-campaign-name]')) {
            document.querySelector('[data-campaign-title]').textContent = event.target.value.trim() || 'Untitled Campaign';
        }

        if (event.target.name && event.target.matches('input, textarea, select')) {
            scheduleAutoSave();
            commitHistory();
        }
    });


    refresh();
    commitHistory();
}
