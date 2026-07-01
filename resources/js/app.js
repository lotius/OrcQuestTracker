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
    const resourceNames = ['wood', 'metal', 'leather', 'gold'];
    const saveStatusNodes = all('[data-save-status], [data-save-status-secondary]');
    const activeTabClasses = 'min-h-10 rounded-md bg-emerald-800 px-4 text-sm font-extrabold text-white';
    const inactiveTabClasses = 'min-h-10 rounded-md px-4 text-sm font-extrabold text-emerald-950 hover:bg-emerald-50';

    const setSaveStatus = (message, tone = 'idle') => {
        saveStatusNodes.forEach((node) => {
            node.textContent = message;
            node.classList.toggle('text-slate-500', tone === 'idle');
            node.classList.toggle('text-amber-700', tone === 'pending');
            node.classList.toggle('text-emerald-800', tone === 'saved');
            node.classList.toggle('text-red-700', tone === 'error');
        });
    };

    const showTab = (tab) => {
        all('[data-tab]').forEach((button) => {
            const active = button.dataset.tab === tab;
            button.className = active ? activeTabClasses : inactiveTabClasses;
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });

        all('[data-tab-panel]').forEach((panel) => {
            panel.hidden = panel.dataset.tabPanel !== tab;
        });

        window.localStorage?.setItem('orcquest-active-tab', tab);
    };

    const initialTab = window.localStorage?.getItem('orcquest-active-tab') || 'characters';
    showTab(['characters', 'resources', 'campaign'].includes(initialTab) ? initialTab : 'characters');

    const openModal = (name) => {
        const modal = document.querySelector(`[data-modal="${name}"]`);
        if (! modal) {
            return;
        }

        modal.classList.remove('hidden');
        modal.querySelector('[data-modal-close]')?.focus();
    };

    const closeModal = (modal = document.querySelector('[data-modal]:not(.hidden)')) => {
        modal?.classList.add('hidden');
    };

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

    const restoreSnapshot = (snapshot) => {
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

            control.value = entry.value;
        });

        historyState.restoring = false;
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
        restoreSnapshot(historyState.current);
    };

    const redoTracker = () => {
        if (! historyState.redo.length || ! historyState.current) {
            return;
        }

        historyState.undo.push(historyState.current);
        historyState.current = historyState.redo.pop();
        restoreSnapshot(historyState.current);
    };

    const selectFromElement = (target) => {
        if (target.matches?.('select')) {
            return target;
        }

        return null;
    };

    const selectedValues = (selector) => all(selector)
        .map((select) => value(select))
        .filter(Boolean);

    const selectedValuesExcept = (selector, current) => all(selector)
        .filter((select) => select !== current)
        .map((select) => value(select))
        .filter(Boolean);

    const selectedEnchantCodesExcept = (current) => all('select[data-filter="enchant"]')
        .filter((select) => select !== current)
        .map((select) => enchantCode(value(select)))
        .filter(Boolean);

    const itemHands = (itemIndex) => Number(itemByIndex[itemIndex]?.hands || 0);

    const isTwoHanded = (itemIndex) => itemHands(itemIndex) >= 2;

    const itemAlwaysRekup = (itemIndex) => itemByIndex[itemIndex]?.always_rekup === true;

    const handSelect = (card, key) => card.querySelector(`select[name$="[${key}]"]`);

    const enchantSelectsForItemSelect = (select) => {
        if (! select) {
            return { left: null, right: null };
        }

        if (select.name.includes('[inventory]')) {
            const row = select.closest('[data-inventory-row]');
            return {
                left: row?.querySelector('select[name$="[left_enchant]"]') || null,
                right: row?.querySelector('select[name$="[right_enchant]"]') || null,
            };
        }

        const card = select.closest('[data-character]');
        const match = select.name.match(/\[equipment\]\[(.+)\]$/);
        const key = match?.[1];

        if (! card || ! key) {
            return { left: null, right: null };
        }

        if (key === 'armor_item') {
            return {
                left: card.querySelector('select[name$="[armor_left_enchant]"]') || null,
                right: card.querySelector('select[name$="[armor_right_enchant]"]') || null,
            };
        }

        if (key === 'artifact_item') {
            return {
                left: card.querySelector('select[name$="[artifact_left_enchant]"]') || null,
                right: card.querySelector('select[name$="[artifact_right_enchant]"]') || null,
            };
        }

        return {
            left: card.querySelector(`select[name$="[${key}_left_enchant]"]`) || null,
            right: card.querySelector(`select[name$="[${key}_right_enchant]"]`) || null,
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

        select.value = item;

        if (enchants.left) {
            enchants.left.value = canHaveEnchants ? (bundle?.leftEnchant || '') : '';
        }

        if (enchants.right) {
            enchants.right.value = canHaveEnchants ? (bundle?.rightEnchant || '') : '';
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

    const setSelectValue = (select, nextValue) => {
        if (! select) {
            return;
        }

        select.value = nextValue || '';
        select.dispatchEvent(new Event('change', { bubbles: true }));
    };

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

    let applyingHandRules = false;

    const setSecondHandMirror = (select, enabled) => {
        if (! select) {
            return;
        }

        const enchants = enchantSelectsForItemSelect(select);
        select.dataset.twoHandMirror = enabled ? 'true' : 'false';
        select.disabled = enabled || select.dataset.lockedByHero === 'true';
        select.classList.toggle('opacity-50', enabled);
        select.classList.toggle('cursor-not-allowed', enabled);

        [enchants.left, enchants.right].forEach((enchant) => {
            if (! enchant) {
                return;
            }

            if (enabled) {
                enchant.value = '';
            }

            enchant.disabled = enabled || enchant.dataset.lockedByHero === 'true';
            enchant.classList.toggle('opacity-50', enabled);
            enchant.classList.toggle('cursor-not-allowed', enabled);

            const enchantShell = enchant.closest('label');
            enchantShell?.classList.toggle('opacity-50', enabled || enchant.dataset.lockedByHero === 'true');
            enchantShell?.classList.toggle('grayscale', enabled || enchant.dataset.lockedByHero === 'true');
        });

        const fieldShell = select.closest('label, [data-inventory-row]');
        if (fieldShell) {
            fieldShell.classList.toggle('opacity-50', enabled || select.dataset.lockedByHero === 'true');
            fieldShell.classList.toggle('grayscale', enabled || select.dataset.lockedByHero === 'true');
        }
    };

    const applyHandRules = () => {
        if (applyingHandRules) {
            return;
        }

        applyingHandRules = true;

        all('[data-character]').forEach((card) => {
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

                second.value = first.value;
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

    const setOptionAvailability = (select, isUnavailable) => {
        Array.from(select.options).forEach((option) => {
            if (! option.value) {
                option.disabled = false;
                option.hidden = false;
                return;
            }

            const unavailable = isUnavailable(option.value) && option.value !== select.value;
            option.disabled = unavailable;
            option.hidden = unavailable;
        });
    };

    const refreshFilteredSelects = () => {
        all('select[data-filter="hero"]').forEach((select) => {
            const used = selectedValuesExcept('select[data-filter="hero"]', select);
            setOptionAvailability(select, (optionValue) => used.includes(optionValue));
        });

        all('select[data-filter="item"]').forEach((select) => {
            const used = selectedValuesExcept('select[data-filter="item"]', select);
            setOptionAvailability(select, (optionValue) => used.includes(optionValue));
        });

        all('select[data-filter="enchant"]').forEach((select) => {
            const usedCodes = selectedEnchantCodesExcept(select);
            setOptionAvailability(select, (optionValue) => usedCodes.includes(enchantCode(optionValue)));
        });
    };

    const refreshSkills = () => {
        all('[data-character]').forEach((card) => {
            const hero = heroById[value(card.querySelector('[data-hero-select]'))];
            const skills = hero?.skills || { 10: [], 20: [], 30: [] };

            all('select[data-skill-tier]', card).forEach((select) => {
                const tier = select.dataset.skillTier;
                const current = skills[tier]?.includes(select.value) ? select.value : '';
                const used = all(`select[data-skill-tier="${tier}"]`, card)
                    .filter((other) => other !== select)
                    .map((other) => other.value)
                    .filter(Boolean);

                select.replaceChildren(new Option('Empty', ''));

                (skills[tier] || []).forEach((skill) => {
                    if (used.includes(skill) && skill !== current) {
                        return;
                    }

                    select.add(new Option(skill, skill, false, skill === current));
                });

                select.value = current;
            });
        });
    };

    const setWeightBadge = (badge, label, used, limit) => {
        badge.textContent = `${label} ${used}/${limit || '-'}`;
        const over = Boolean(limit && used > limit);
        badge.classList.toggle('bg-red-50', over);
        badge.classList.toggle('text-red-700', over);
        badge.classList.toggle('bg-emerald-50', ! over);
        badge.classList.toggle('text-emerald-950', ! over);
    };

    const refreshHeroPanels = () => {
        all('[data-character]').forEach((card) => {
            const hero = heroById[value(card.querySelector('[data-hero-select]'))];
            const heroName = card.querySelector('[data-hero-name]');
            const heroIcon = card.querySelector('[data-hero-icon]');
            const facts = card.querySelector('[data-hero-facts]');

            heroName.textContent = hero?.name || 'Unassigned Hero';

            if (heroIcon) {
                if (hero?.icon) {
                    heroIcon.src = hero.icon;
                    heroIcon.alt = `${hero.name} icon`;
                    heroIcon.hidden = false;
                } else {
                    heroIcon.removeAttribute('src');
                    heroIcon.alt = 'No hero selected';
                    heroIcon.hidden = true;
                }
            }

            if (facts) {
                facts.replaceChildren();

                if (hero) {
                    [hero.class, `${hero.base_health} ${hero.health_color} health`, hero.starting_stuff].forEach((fact) => {
                        const pill = document.createElement('span');
                        pill.className = 'rounded-md bg-amber-50 px-2 py-1.5 text-xs font-extrabold text-amber-900';
                        pill.textContent = fact;
                        facts.appendChild(pill);
                    });
                    facts.hidden = false;
                } else {
                    facts.hidden = true;
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
                control.classList.toggle('cursor-not-allowed', ! hero);
                control.classList.toggle('opacity-50', ! hero || alwaysRekup);

                const fieldShell = control.closest('label, [data-inventory-row]');
                if (fieldShell) {
                    fieldShell.classList.toggle('opacity-50', ! hero || alwaysRekup);
                    fieldShell.classList.toggle('grayscale', ! hero || alwaysRekup);
                }
            });

            let inventoryWeight = 0;
            let handWeight = 0;
            let armorWeight = 0;

            all('select[name*="[inventory]"][data-filter="item"]', card).forEach((select) => {
                const row = select.closest('[data-inventory-row]');
                const isRekup = row?.querySelector('[data-rekup]')?.checked;
                inventoryWeight += isRekup ? 1 : Number(itemByIndex[select.value]?.weight || 0);
            });

            const firstHand = handSelect(card, 'hand_item_1');
            const secondHand = handSelect(card, 'hand_item_2');
            handWeight += Number(itemByIndex[value(firstHand)]?.weight || 0);
            if (value(secondHand) && value(secondHand) !== value(firstHand)) {
                handWeight += Number(itemByIndex[value(secondHand)]?.weight || 0);
            }

            armorWeight = Number(itemByIndex[value(card.querySelector('select[name$="[armor_item]"]'))]?.weight || 0);

            setWeightBadge(card.querySelector('[data-weight="inventory"]'), 'Inv', inventoryWeight, Number(hero?.inventory_weight_limit || 0));
            setWeightBadge(card.querySelector('[data-weight="hands"]'), 'Hands', handWeight, Number(hero?.hand_weight_limit || 0));
            setWeightBadge(card.querySelector('[data-weight="armor"]'), 'Armor', armorWeight, Number(hero?.armor_weight_limit || 0));
        });
    };

    const currentResources = () => {
        const totals = { wood: 0, metal: 0, leather: 0, gold: 0 };

        all('[data-character]').forEach((card) => {
            all('select[name*="[inventory]"][data-filter="item"]', card).forEach((select) => {
                const row = select.closest('[data-inventory-row]');
                if (! row?.querySelector('[data-rekup]')?.checked) {
                    return;
                }

                const item = itemByIndex[select.value];
                Object.keys(totals).forEach((resource) => {
                    totals[resource] += Number(item?.rekup?.[resource] || 0);
                });
            });
        });

        return totals;
    };

    const ownedItems = (card) => {
        const owned = [];

        all('select[data-filter="item"]', card).forEach((select) => {
            if (! select.value) {
                return;
            }

            if (select.dataset.twoHandMirror === 'true') {
                return;
            }

            const row = select.closest('[data-inventory-row]');
            const isInventory = select.name.includes('[inventory]');
            owned.push({
                key: `${card.dataset.character}|${select.name}`,
                index: select.value,
                characterSlot: card.dataset.character,
                rekup: Boolean(isInventory && row?.querySelector('[data-rekup]')?.checked),
                location: isInventory ? 'Inventory' : select.previousElementSibling?.textContent || 'Equipment',
                select,
            });
        });

        return owned;
    };

    const canAfford = (item, resources) => Object.keys(resources)
        .every((resource) => Number(item?.kraft?.[resource] || 0) <= resources[resource]);

    const resourceBag = (source = {}) => Object.fromEntries(
        resourceNames.map((resource) => [resource, Number(source?.[resource] || 0)])
    );

    const addBags = (left, right) => Object.fromEntries(
        resourceNames.map((resource) => [resource, Number(left[resource] || 0) + Number(right[resource] || 0)])
    );

    const subtractBags = (left, right) => Object.fromEntries(
        resourceNames.map((resource) => [resource, Math.max(0, Number(left[resource] || 0) - Number(right[resource] || 0))])
    );

    const resourceText = (bag) => resourceNames
        .map((resource) => `${resource[0].toUpperCase()} ${Number(bag?.[resource] || 0)}`)
        .join(' / ');

    const missingText = (missing) => {
        const rows = resourceNames
            .filter((resource) => Number(missing[resource] || 0) > 0)
            .map((resource) => `${missing[resource]} ${resource}`);

        return rows.length ? rows.join(', ') : 'Nothing missing';
    };

    const allOwnedUpgradeableItems = () => all('[data-character]').flatMap((card) => {
        const hero = heroById[value(card.querySelector('[data-hero-select]'))];

        return ownedItems(card)
            .map((owned) => ({
                ...owned,
                item: itemByIndex[owned.index],
                character: hero?.name || `Character ${card.dataset.character}`,
            }))
            .filter((owned) => ! owned.rekup && owned.item?.can_upgrade);
    });

    const paymentCandidatesFor = (targetKey) => all('[data-character]').flatMap((card) => {
        const hero = heroById[value(card.querySelector('[data-hero-select]'))];

        return all('select[name*="[inventory]"][data-filter="item"]', card)
            .map((select) => {
                const row = select.closest('[data-inventory-row]');
                const item = itemByIndex[select.value];

                return {
                    key: `${card.dataset.character}|${select.name}`,
                    index: select.value,
                    character: hero?.name || `Character ${card.dataset.character}`,
                    location: `Inventory ${all('select[name*="[inventory]"][data-filter="item"]', card).indexOf(select) + 1}`,
                    rekup: Boolean(row?.querySelector('[data-rekup]')?.checked),
                    item,
                    resources: resourceBag(item?.rekup),
                    select,
                };
            })
            .filter((candidate) => candidate.index && candidate.key !== targetKey)
            .filter((candidate) => resourceNames.some((resource) => candidate.resources[resource] > 0));
    });

    const scorePlan = (plan, strategy, cost) => {
        const wasted = resourceNames.reduce((total, resource) => total + Math.max(0, plan.resources[resource] - cost[resource]), 0);
        const totalResources = resourceNames.reduce((total, resource) => total + plan.resources[resource], 0);

        const base = {
            least_waste: [wasted, plan.cards.length, totalResources],
            retain_wood: [plan.resources.wood, wasted, plan.cards.length, totalResources],
            retain_gold: [plan.resources.gold, wasted, plan.cards.length, totalResources],
            retain_metal: [plan.resources.metal, wasted, plan.cards.length, totalResources],
            retain_leather: [plan.resources.leather, wasted, plan.cards.length, totalResources],
        };

        return base[strategy] || base.least_waste;
    };

    const isBetterPlan = (next, current, strategy, cost) => {
        if (! current) {
            return true;
        }

        const nextScore = scorePlan(next, strategy, cost);
        const currentScore = scorePlan(current, strategy, cost);

        return nextScore.some((score, index) => score < currentScore[index] && nextScore.slice(0, index).every((valueAtIndex, previousIndex) => valueAtIndex === currentScore[previousIndex]));
    };

    const findKraftPlan = (target, resources, strategy) => {
        const cost = resourceBag(target.item.kraft);
        const missing = subtractBags(cost, resources);

        const candidates = paymentCandidatesFor(target.key)
            .filter((candidate) => resourceNames.some((resource) => cost[resource] > 0 && candidate.resources[resource] > 0));

        if (! candidates.length) {
            return { missing, cards: [], resources: resourceBag(), cost, waste: resourceBag(), craftableNow: false };
        }

        const caps = Object.fromEntries(resourceNames.map((resource) => [
            resource,
            cost[resource] + Math.max(...candidates.map((candidate) => candidate.resources[resource]), 0),
        ]));
        const stateKey = (bag) => resourceNames.map((resource) => bag[resource]).join('|');
        const capBag = (bag) => Object.fromEntries(resourceNames.map((resource) => [
            resource,
            Math.min(caps[resource], Number(bag[resource] || 0)),
        ]));
        const coversCost = (bag) => resourceNames.every((resource) => bag[resource] >= cost[resource]);
        const bestForState = new Map([[stateKey(resourceBag()), { cards: [], resources: resourceBag() }]]);

        candidates.forEach((candidate) => {
            Array.from(bestForState.values()).forEach((plan) => {
                if (plan.cards.some((card) => card.key === candidate.key)) {
                    return;
                }

                const next = {
                    cards: [...plan.cards, candidate],
                    resources: capBag(addBags(plan.resources, candidate.resources)),
                };
                const key = stateKey(next.resources);
                const current = bestForState.get(key);

                if (! current || next.cards.length < current.cards.length) {
                    bestForState.set(key, next);
                }
            });
        });

        const plan = Array.from(bestForState.values())
            .filter((candidatePlan) => candidatePlan.cards.length && coversCost(candidatePlan.resources))
            .reduce((best, candidatePlan) => (isBetterPlan(candidatePlan, best, strategy, cost) ? candidatePlan : best), null);

        if (! plan) {
            return { missing, cards: [], resources: resourceBag(), cost, waste: resourceBag(), craftableNow: false };
        }

        return {
            ...plan,
            missing,
            cost,
            waste: subtractBags(plan.resources, cost),
            craftableNow: plan.cards.every((card) => card.rekup),
        };
    };

    const refreshKraftTargetOptions = (targets) => {
        const select = document.querySelector('[data-kraft-target]');
        if (! select) {
            return null;
        }

        const current = select.value;
        select.replaceChildren(new Option('Choose an owned upgradeable item', ''));

        targets.forEach((target) => {
            select.add(new Option(`${target.item.index} - ${target.character} / ${target.location}`, target.key));
        });

        select.value = targets.some((target) => target.key === current) ? current : '';

        return targets.find((target) => target.key === select.value) || null;
    };

    const currentKraftPlan = (resources = currentResources()) => {
        const targets = allOwnedUpgradeableItems();
        const targetSelect = document.querySelector('[data-kraft-target]');
        const target = targets.find((owned) => owned.key === targetSelect?.value) || null;
        const strategy = value(document.querySelector('[data-kraft-strategy]')) || 'least_waste';

        return {
            target,
            plan: target ? findKraftPlan(target, resources, strategy) : null,
        };
    };

    const compactInventories = () => {
        all('[data-character]').forEach((card) => {
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
        const { plan } = currentKraftPlan();

        if (! plan?.cards?.length) {
            return;
        }

        plan.cards.forEach((card) => {
            if (! card.select) {
                return;
            }

            setItemBundle(card.select, null);
        });

        compactInventories();
        refresh();
        commitHistory();
        scheduleAutoSave();
    };

    const renderKraftSummary = (target, resources, plan) => {
        const summary = document.querySelector('[data-kraft-summary]');
        if (! summary) {
            return;
        }

        summary.replaceChildren();

        if (! target) {
            const empty = document.createElement('p');
            empty.className = 'text-sm font-bold text-slate-500';
            empty.textContent = 'Choose a target to plan a kraft.';
            summary.appendChild(empty);
            return;
        }

        const cost = resourceBag(target.item.kraft);
        const rows = [
            ['Target', `${target.item.index} - ${target.character} / ${target.location}`],
            ['Current pool', resourceText(resources)],
            ['Kraft cost', resourceText(cost)],
            ['Missing now', missingText(plan.missing)],
            ['Suggested payment', resourceText(plan.resources)],
            ['Payment waste', resourceText(plan.waste)],
        ];

        rows.forEach(([label, text]) => {
            const hasMissingResources = label === 'Missing now'
                && resourceNames.some((resource) => Number(plan.missing?.[resource] || 0) > 0);
            const row = document.createElement('div');
            row.className = 'grid gap-1 border-t border-slate-200 py-2 first:border-t-0 sm:grid-cols-[9rem_1fr]';
            const title = document.createElement('strong');
            title.className = hasMissingResources
                ? 'text-xs uppercase text-red-700'
                : 'text-xs uppercase text-slate-500';
            title.textContent = label;
            const valueNode = document.createElement('span');
            valueNode.className = hasMissingResources
                ? 'text-sm font-bold text-red-700'
                : 'text-sm font-bold text-slate-900';
            valueNode.textContent = text;
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
            const empty = document.createElement('p');
            empty.className = 'text-sm font-bold text-slate-500';
            empty.textContent = 'Exact payment cards will appear here.';
            suggestion.appendChild(empty);
            return;
        }

        if (! plan.cards.length) {
            const blocked = document.createElement('p');
            blocked.className = 'text-sm font-extrabold text-red-700';
            blocked.textContent = 'No available inventory card combination can pay this kraft cost.';
            suggestion.appendChild(blocked);
            return;
        }

        const intro = document.createElement('p');
        intro.className = 'mb-2 text-sm font-extrabold text-emerald-950';
        intro.textContent = `Consume these ${plan.cards.length} card${plan.cards.length === 1 ? '' : 's'} to pay:`;
        suggestion.appendChild(intro);

        const consumeButton = document.createElement('button');
        consumeButton.className = 'mb-2 min-h-10 rounded-md bg-red-700 px-4 text-sm font-extrabold text-white hover:bg-red-800';
        consumeButton.type = 'button';
        consumeButton.dataset.consumeKraftCards = 'true';
        consumeButton.textContent = 'Consume Payment Cards';
        suggestion.appendChild(consumeButton);

        plan.cards.forEach((card) => {
            const row = document.createElement('div');
            row.className = 'grid gap-1 border-t border-slate-200 py-2 first:border-t-0 md:grid-cols-[1fr_auto]';
            const item = document.createElement('strong');
            item.className = 'block';
            item.textContent = card.index;
            const meta = document.createElement('small');
            meta.className = 'mt-1 block text-slate-500';
            meta.textContent = `${card.character} / ${card.location} / ${resourceText(card.resources)}`;
            const status = document.createElement('span');
            status.className = card.rekup
                ? 'w-max rounded-md bg-emerald-50 px-2 py-1 text-xs font-extrabold text-emerald-800'
                : 'w-max rounded-md bg-amber-50 px-2 py-1 text-xs font-extrabold text-amber-800';
            status.textContent = card.rekup ? 'In rekup pool' : 'Mark rekup first';
            const left = document.createElement('div');
            left.append(item, meta);
            row.append(left, status);
            suggestion.appendChild(row);
        });
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
            .slice(0, 9);

        if (! rows.length) {
            const empty = document.createElement('p');
            empty.className = 'text-sm font-bold text-slate-500';
            empty.textContent = 'No blocked owned upgrades right now.';
            list.appendChild(empty);
            return;
        }

        rows.forEach(({ target, missing }) => {
            const card = document.createElement('div');
            card.className = 'rounded-md border border-slate-200 bg-white p-3';
            const title = document.createElement('strong');
            title.className = 'block text-sm';
            title.textContent = target.item.index;
            const meta = document.createElement('small');
            meta.className = 'mt-1 block text-slate-500';
            meta.textContent = `${target.character} / ${target.location}`;
            const need = document.createElement('span');
            need.className = 'mt-2 block text-xs font-extrabold text-amber-700';
            need.textContent = `Missing ${missingText(missing)}`;
            card.append(title, meta, need);
            list.appendChild(card);
        });
    };

    const refreshKraftPlanner = (resources) => {
        const targets = allOwnedUpgradeableItems();
        const target = refreshKraftTargetOptions(targets);
        const strategy = value(document.querySelector('[data-kraft-strategy]')) || 'least_waste';
        const plan = target ? findKraftPlan(target, resources, strategy) : null;

        renderKraftSummary(target, resources, plan || { missing: resourceBag(), resources: resourceBag(), waste: resourceBag(), cards: [] });
        renderKraftSuggestion(target, plan || { cards: [] });
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

        const upgrades = [];
        all('[data-character]').forEach((card) => {
            const hero = heroById[value(card.querySelector('[data-hero-select]'))];
            ownedItems(card).forEach((owned) => {
                const item = itemByIndex[owned.index];
                if (! owned.rekup && item?.can_upgrade && canAfford(item, resources)) {
                    upgrades.push({
                        character: hero?.name || `Character ${card.dataset.character}`,
                        location: owned.location,
                        item,
                    });
                }
            });
        });

        const list = document.querySelector('[data-upgrades-list]');
        const count = document.querySelector('[data-upgrades-count]');
        if (count) {
            count.textContent = upgrades.length;
        }

        if (list) {
            list.replaceChildren();

            if (! upgrades.length) {
                const empty = document.createElement('p');
                empty.className = 'text-slate-500';
                empty.textContent = 'No items can be upgraded with the current pooled resources.';
                list.appendChild(empty);
            }

            upgrades.forEach((upgrade) => {
                const row = document.createElement('div');
                row.className = 'grid min-h-10 items-center gap-1 border-t border-slate-200 py-2 first:border-t-0 md:grid-cols-[1fr_1.3fr_auto] md:gap-3';

                const title = document.createElement('strong');
                title.textContent = upgrade.item.index;
                const location = document.createElement('span');
                location.className = 'text-slate-500';
                location.textContent = `${upgrade.character} · ${upgrade.location}`;
                const cost = document.createElement('small');
                cost.className = 'text-slate-500';
                cost.textContent = `W ${upgrade.item.kraft.wood} / M ${upgrade.item.kraft.metal} / L ${upgrade.item.kraft.leather} / G ${upgrade.item.kraft.gold}`;

                row.append(title, location, cost);
                list.appendChild(row);
            });
        }

        refreshKraftPlanner(resources);
    };

    const refreshCampaignCount = () => {
        const completed = all('input[name^="campaign["]:checked').length;
        const campaignCount = document.querySelector('[data-campaign-count]');
        if (campaignCount) {
            campaignCount.textContent = completed;
        }
    };

    const refreshItemTooltips = () => {
        all('select[data-filter="item"]').forEach((select) => {
            const item = itemByIndex[select.value];

            if (! item) {
                select.removeAttribute('title');
                return;
            }

            const lines = [
                item.index,
                `Weight: ${item.weight}`,
                `Hands required: ${item.hands}`,
                '',
                'Breaks down to:',
                `Wood: ${item.rekup.wood}`,
                `Metal: ${item.rekup.metal}`,
                `Leather: ${item.rekup.leather}`,
                `Gold: ${item.rekup.gold}`,
                '',
                'Upgradeability:',
            ];

            if (! item.can_upgrade) {
                select.title = [
                    ...lines,
                    'No upgrade is possible for this item.',
                ].join('\n');
                return;
            }

            select.title = [
                ...lines,
                'Can be upgraded.',
                'Resources required to upgrade:',
                `Wood: ${item.kraft.wood}`,
                `Metal: ${item.kraft.metal}`,
                `Leather: ${item.kraft.leather}`,
                `Gold: ${item.kraft.gold}`,
            ].join('\n');
        });
    };

    const refreshEnchantTooltips = () => {
        all('select[data-filter="enchant"]').forEach((select) => {
            if (! select.value) {
                select.removeAttribute('title');
                return;
            }

            const options = select.dataset.enchantSide === 'right' ? rightEnchants : leftEnchants;
            const enchant = options.find((option) => option.value === select.value);

            if (! enchant) {
                select.removeAttribute('title');
                return;
            }

            if (select.dataset.enchantSide === 'right') {
                select.title = [
                    select.value,
                    'Right enchant effect:',
                    enchant.effect || 'No effect listed.',
                ].join('\n');
                return;
            }

            select.title = [
                select.value,
                'Upgrade condition:',
                enchant.condition || 'No condition listed.',
                '',
                'Left enchant effect:',
                enchant.effect || 'No effect listed.',
            ].join('\n');
        });
    };

    let autoSaveTimer = null;
    let autoSaveController = null;

    const saveNow = async () => {
        window.clearTimeout(autoSaveTimer);
        autoSaveTimer = null;
        autoSaveController?.abort();
        autoSaveController = new AbortController();
        setSaveStatus('Saving...', 'pending');

        try {
            const response = await fetch(root.action, {
                method: root.method || 'POST',
                body: new FormData(root),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: autoSaveController.signal,
            });

            if (! response.ok) {
                throw new Error(`Save failed with status ${response.status}`);
            }

            setSaveStatus(`Saved ${new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })}`, 'saved');
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            setSaveStatus('Auto-save failed. Use Save Tracker.', 'error');
        } finally {
            autoSaveController = null;
        }
    };

    const scheduleAutoSave = () => {
        setSaveStatus('Unsaved changes...', 'pending');
        window.clearTimeout(autoSaveTimer);
        autoSaveTimer = window.setTimeout(saveNow, 900);
    };

    const clearableSelects = () => all('select[data-filter], select[data-skill-tier]');

    const prepareClearButtons = () => {
        clearableSelects().forEach((select) => {
            if (select.dataset.clearReady === 'true') {
                return;
            }

            select.dataset.clearReady = 'true';

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'grid h-7 w-7 shrink-0 place-items-center rounded-full border border-slate-300 bg-slate-50 text-sm font-black leading-none text-slate-500 hover:border-red-300 hover:bg-red-50 hover:text-red-700 disabled:cursor-not-allowed disabled:opacity-40';
            button.textContent = 'x';
            button.dataset.clearSelect = 'true';
            button.title = 'Clear this selection';

            const parent = select.parentElement;

            if (parent?.classList.contains('flex')) {
                select.insertAdjacentElement('afterend', button);
                return;
            }

            const wrapper = document.createElement('div');
            wrapper.className = 'flex items-center gap-2';
            select.replaceWith(wrapper);
            wrapper.append(select, button);
        });
    };

    const refreshClearButtons = () => {
        all('[data-clear-select]').forEach((button) => {
            const select = button.previousElementSibling?.matches('select') ? button.previousElementSibling : null;

            if (! select) {
                button.classList.add('hidden');
                return;
            }

            button.classList.toggle('hidden', ! select.value);
            button.disabled = select.disabled;
        });
    };

    const prepareItemDragSources = () => {
        all('select[data-filter="item"]').forEach((select) => {
            if (select.dataset.dragReady === 'true') {
                return;
            }

            select.dataset.dragReady = 'true';
            select.classList.add('cursor-grab', 'active:cursor-grabbing');
        });
    };

    const dragTargetSelect = (target) => {
        const directSelect = selectFromElement(target);
        if (directSelect?.matches('select[data-filter="item"]')) {
            return directSelect;
        }

        return target.closest?.('label, .flex')?.querySelector('select[data-filter="item"]') || null;
    };

    const setDragHighlight = (target, enabled) => {
        const select = dragTargetSelect(target);
        if (! select) {
            return;
        }

        select.classList.toggle('ring-2', enabled);
        select.classList.toggle('ring-emerald-700', enabled);
        select.classList.toggle('border-emerald-700', enabled);
    };

    const isInventorySelect = (select) => select?.name.includes('[inventory]');

    const rekupCheckboxFor = (select) => select?.closest('[data-inventory-row]')?.querySelector('[data-rekup]') || null;

    const isRekupInventoryItem = (select) => Boolean(isInventorySelect(select) && rekupCheckboxFor(select)?.checked);

    const refreshAlwaysRekupLocks = () => {
        all('select[name*="[inventory]"][data-filter="item"]').forEach((select) => {
            const rekup = rekupCheckboxFor(select);
            if (! rekup) {
                return;
            }

            const empty = ! select.value;
            const locked = itemAlwaysRekup(select.value);
            if (locked) {
                rekup.checked = true;
            } else if (empty) {
                rekup.checked = false;
            }

            const disabled = empty || locked || rekup.dataset.lockedByHero === 'true';
            rekup.disabled = disabled;
            rekup.title = locked ? 'This item is always used as rekup.' : '';

            const fieldShell = rekup.closest('label, [data-inventory-row]');
            if (fieldShell) {
                fieldShell.classList.toggle('opacity-50', disabled);
                fieldShell.classList.toggle('grayscale', disabled);
            }
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
                enchant.classList.toggle('opacity-50', disabled);
                enchant.classList.toggle('cursor-not-allowed', disabled);

                const shell = enchant.closest('label');
                shell?.classList.toggle('opacity-50', disabled);
                shell?.classList.toggle('grayscale', disabled);
            });
        });
    };

    const clearEmptyInventoryRekup = () => {
        all('select[name*="[inventory]"][data-filter="item"]').forEach((select) => {
            if (select.value) {
                return;
            }

            const rekup = rekupCheckboxFor(select);
            if (rekup) {
                rekup.checked = false;
            }
        });
    };

    const canDropItem = (source, destination) => {
        if (! source || ! destination || source === destination) {
            return false;
        }

        if (source.disabled || destination.disabled) {
            return false;
        }

        if (isRekupInventoryItem(source) && ! isInventorySelect(destination)) {
            return false;
        }

        if (! isInventorySelect(source) && isRekupInventoryItem(destination)) {
            return false;
        }

        return true;
    };

    let pointerDrag = null;
    let suppressNextClick = false;

    const clearDragHighlights = () => {
        all('select[data-filter="item"]').forEach((select) => setDragHighlight(select, false));
    };

    const createDragGhost = (select) => {
        const ghost = document.createElement('div');
        ghost.className = 'pointer-events-none fixed z-50 max-w-80 rounded-md border border-emerald-800 bg-white px-3 py-2 text-sm font-extrabold text-emerald-950 shadow-xl';
        ghost.textContent = select.value;
        document.body.appendChild(ghost);

        return ghost;
    };

    const moveGhost = (ghost, x, y) => {
        ghost.style.left = `${x + 12}px`;
        ghost.style.top = `${y + 12}px`;
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

        source.dispatchEvent(new Event('change', { bubbles: true }));
        destination.dispatchEvent(new Event('change', { bubbles: true }));
        applyHandRules();
        refresh();
    };

    root.addEventListener('pointerdown', (event) => {
        const select = dragTargetSelect(event.target);

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

        const distance = Math.hypot(event.clientX - pointerDrag.startX, event.clientY - pointerDrag.startY);

        if (! pointerDrag.active && distance < 14) {
            return;
        }

        if (! pointerDrag.active) {
            pointerDrag.active = true;
            suppressNextClick = true;
            closeOpenDropdown();
            pointerDrag.ghost = createDragGhost(pointerDrag.source);
            pointerDrag.source.classList.add('opacity-60');
        }

        event.preventDefault();
        moveGhost(pointerDrag.ghost, event.clientX, event.clientY);
        clearDragHighlights();

        const destination = dragTargetSelect(document.elementFromPoint(event.clientX, event.clientY));
        if (canDropItem(pointerDrag.source, destination)) {
            pointerDrag.target = destination;
            setDragHighlight(destination, true);
        } else {
            pointerDrag.target = null;
        }
    }, { passive: false });

    window.addEventListener('pointerup', () => {
        if (! pointerDrag) {
            return;
        }

        const wasDragging = pointerDrag.active;

        if (wasDragging && canDropItem(pointerDrag.source, pointerDrag.target)) {
            swapItemSelects(pointerDrag.source, pointerDrag.target);
        }

        pointerDrag.source.classList.remove('opacity-60');
        pointerDrag.ghost?.remove();
        if (wasDragging) {
            closeOpenDropdown();
            window.setTimeout(() => {
                suppressNextClick = false;
            }, 250);
        }
        clearDragHighlights();
        pointerDrag = null;
    });

    document.addEventListener('click', (event) => {
        const modalOpen = event.target.closest?.('[data-modal-open]');
        if (modalOpen) {
            openModal(modalOpen.dataset.modalOpen);
            return;
        }

        const tabButton = event.target.closest?.('[data-tab]');
        if (tabButton) {
            showTab(tabButton.dataset.tab);
            return;
        }

        if (event.target.closest?.('[data-undo]')) {
            undoTracker();
            return;
        }

        if (event.target.closest?.('[data-redo]')) {
            redoTracker();
            return;
        }

        if (event.target.closest?.('[data-consume-kraft-cards]')) {
            consumeKraftPaymentCards();
            return;
        }

        const clearButton = event.target.closest?.('[data-clear-select]');
        if (clearButton) {
            const select = clearButton.previousElementSibling?.matches('select') ? clearButton.previousElementSibling : null;
            if (select && ! select.disabled) {
                select.value = '';
                if (isInventorySelect(select)) {
                    rekupCheckboxFor(select).checked = false;
                }
                select.dispatchEvent(new Event('change', { bubbles: true }));
                refresh();
            }
            return;
        }

        if (! suppressNextClick) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        suppressNextClick = false;
    }, true);

    window.addEventListener('pointercancel', () => {
        if (! pointerDrag) {
            return;
        }

        pointerDrag.source.classList.remove('opacity-60');
        pointerDrag.ghost?.remove();
        closeOpenDropdown();
        suppressNextClick = false;
        clearDragHighlights();
        pointerDrag = null;
    });

    document.addEventListener('click', (event) => {
        const closeButton = event.target.closest?.('[data-modal-close]');
        if (closeButton) {
            closeModal(closeButton.closest('[data-modal]'));
            return;
        }

        const modal = event.target.matches?.('[data-modal]') ? event.target : null;
        if (modal) {
            closeModal(modal);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeModal();
        }
    });

    const refresh = () => {
        prepareClearButtons();
        prepareItemDragSources();
        refreshSkills();
        applyHandRules();
        refreshFilteredSelects();
        refreshHeroPanels();
        clearEmptyInventoryRekup();
        refreshAlwaysRekupLocks();
        refreshItemEnchantLocks();
        refreshResourcesAndUpgrades();
        refreshCampaignCount();
        refreshItemTooltips();
        refreshEnchantTooltips();
        refreshClearButtons();
    };

    root.addEventListener('change', (event) => {
        refresh();

        if (event.target.name) {
            scheduleAutoSave();
            commitHistory();
        }
    });

    root.addEventListener('input', (event) => {
        if (event.target.matches('select, input[type="checkbox"]')) {
            refresh();
        }

        if (event.target.name && event.target.matches('input, textarea, select')) {
            scheduleAutoSave();
            commitHistory();
        }
    });

    root.addEventListener('submit', () => {
        window.clearTimeout(autoSaveTimer);
        autoSaveController?.abort();
        setSaveStatus('Saving...', 'pending');
    });

    refresh();
    commitHistory();
}
//
