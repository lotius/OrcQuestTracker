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

    const handSelect = (card, key) => card.querySelector(`select[name$="[${key}]"]`);

    const firstEmptyInventorySelect = (card) => all('select[name*="[inventory]"][data-filter="item"]', card)
        .find((select) => ! select.value);

    const setSelectValue = (select, nextValue) => {
        if (! select) {
            return;
        }

        select.value = nextValue || '';
        select.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const moveItemToInventory = (card, itemIndex) => {
        if (! itemIndex) {
            return false;
        }

        const inventorySelect = firstEmptyInventorySelect(card);
        if (! inventorySelect) {
            return false;
        }

        inventorySelect.value = itemIndex;
        const rekup = inventorySelect.closest('.flex')?.querySelector('[data-rekup]');
        if (rekup) {
            rekup.checked = false;
        }

        return true;
    };

    let applyingHandRules = false;

    const setSecondHandMirror = (select, enabled) => {
        if (! select) {
            return;
        }

        select.dataset.twoHandMirror = enabled ? 'true' : 'false';
        select.disabled = enabled || select.dataset.lockedByHero === 'true';
        select.classList.toggle('opacity-50', enabled);
        select.classList.toggle('cursor-not-allowed', enabled);

        const fieldShell = select.closest('label, .flex');
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
                setSecondHandMirror(second, false);
                return;
            }

            if (isTwoHanded(second.value) && second.value !== first.value && second.dataset.twoHandMirror !== 'true') {
                moveItemToInventory(card, first.value);
                first.value = second.value;
                second.value = first.value;
            }

            if (isTwoHanded(first.value)) {
                if (second.value && second.value !== first.value) {
                    moveItemToInventory(card, second.value);
                }

                second.value = first.value;
                setSecondHandMirror(second, true);
            } else {
                if (second.dataset.twoHandMirror === 'true') {
                    second.value = '';
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
                control.dataset.lockedByHero = hero ? 'false' : 'true';
                control.disabled = ! hero || control.dataset.twoHandMirror === 'true';
                control.classList.toggle('cursor-not-allowed', ! hero);
                control.classList.toggle('opacity-50', ! hero);

                const fieldShell = control.closest('label, .flex');
                if (fieldShell) {
                    fieldShell.classList.toggle('opacity-50', ! hero);
                    fieldShell.classList.toggle('grayscale', ! hero);
                }
            });

            let inventoryWeight = 0;
            let handWeight = 0;
            let armorWeight = 0;

            all('select[name*="[inventory]"][data-filter="item"]', card).forEach((select) => {
                const row = select.closest('.flex');
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
                const row = select.closest('.flex');
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

            const row = select.closest('.flex');
            const isInventory = select.name.includes('[inventory]');
            owned.push({
                index: select.value,
                rekup: Boolean(isInventory && row?.querySelector('[data-rekup]')?.checked),
                location: isInventory ? 'Inventory' : select.previousElementSibling?.textContent || 'Equipment',
            });
        });

        return owned;
    };

    const canAfford = (item, resources) => Object.keys(resources)
        .every((resource) => Number(item?.kraft?.[resource] || 0) <= resources[resource]);

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

    const rekupCheckboxFor = (select) => select?.closest('.flex')?.querySelector('[data-rekup]') || null;

    const isRekupInventoryItem = (select) => Boolean(isInventorySelect(select) && rekupCheckboxFor(select)?.checked);

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
        const sourceValue = source.value;
        const destinationValue = destination.value;
        const sourceRekup = rekupCheckboxFor(source);
        const destinationRekup = rekupCheckboxFor(destination);
        const sourceRekupChecked = Boolean(sourceRekup?.checked);
        const destinationRekupChecked = Boolean(destinationRekup?.checked);

        source.value = destinationValue;
        destination.value = sourceValue;

        if (isInventorySelect(source) && isInventorySelect(destination)) {
            sourceRekup.checked = destinationValue ? destinationRekupChecked : false;
            destinationRekup.checked = sourceValue ? sourceRekupChecked : false;
        } else if (! isInventorySelect(source) && isInventorySelect(destination)) {
            destinationRekup.checked = false;
        }

        if (isInventorySelect(source) && ! isInventorySelect(destination)) {
            sourceRekup.checked = false;
        }

        [source, destination].forEach((select) => {
            if (isInventorySelect(select) && ! select.value) {
                rekupCheckboxFor(select).checked = false;
            }
        });

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

    root.addEventListener('click', (event) => {
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

    const refresh = () => {
        prepareClearButtons();
        prepareItemDragSources();
        refreshSkills();
        applyHandRules();
        refreshFilteredSelects();
        refreshHeroPanels();
        clearEmptyInventoryRekup();
        refreshResourcesAndUpgrades();
        refreshCampaignCount();
        refreshItemTooltips();
        refreshEnchantTooltips();
        refreshClearButtons();
    };

    root.addEventListener('change', refresh);
    root.addEventListener('input', (event) => {
        if (event.target.matches('select, input[type="checkbox"]')) {
            refresh();
        }
    });

    refresh();
}
//
