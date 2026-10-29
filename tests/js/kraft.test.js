import { test } from 'node:test';
import assert from 'node:assert/strict';
import { compareScores, findKraftPlan, resourceBag, scorePlan, strategies } from '../../resources/js/kraft.js';

let nextKey = 0;
const card = (name, resources, rekup = true) => ({ key: `${name}#${nextKey++}`, index: name, resources: resourceBag(resources), rekup });
const names = (plan) => plan.cards.map((entry) => entry.index).sort();

// Exhaustive search over every subset: the reference answer for small hands.
const bruteForce = (cost, candidates, strategy) => {
    cost = resourceBag(cost);
    let best = null;

    for (let mask = 1; mask < 1 << candidates.length; mask++) {
        const cards = candidates.filter((_, index) => mask & (1 << index));
        const resources = cards.reduce((bag, entry) => resourceBag({
            wood: bag.wood + entry.resources.wood,
            metal: bag.metal + entry.resources.metal,
            leather: bag.leather + entry.resources.leather,
            gold: bag.gold + entry.resources.gold,
        }), resourceBag());

        if (Object.keys(cost).some((resource) => resources[resource] < cost[resource])) {
            continue;
        }

        const score = scorePlan({ cards, resources }, strategy, cost);
        if (! best || compareScores(score, best.score) < 0) {
            best = { cards, resources, score };
        }
    }

    return best;
};

// Small seeded PRNG so failures are reproducible.
const random = (seed) => () => {
    seed = (seed * 1664525 + 1013904223) % 4294967296;
    return seed / 4294967296;
};

test('least waste picks the exact-fit combination over a bigger single card', () => {
    const plan = findKraftPlan({ metal: 4 }, [
        card('Metal 5', { metal: 5 }),
        card('Metal 2a', { metal: 2 }),
        card('Metal 2b', { metal: 2 }),
    ], 'least_waste');

    assert.deepEqual(names(plan), ['Metal 2a', 'Metal 2b']);
    assert.equal(plan.waste.metal, 0);
});

test('least waste breaks ties on waste by using fewer cards', () => {
    const plan = findKraftPlan({ metal: 4 }, [
        card('Metal 4', { metal: 4 }),
        card('Metal 2a', { metal: 2 }),
        card('Metal 2b', { metal: 2 }),
    ], 'least_waste');

    assert.deepEqual(names(plan), ['Metal 4']);
});

test('retain wood avoids wood-bearing cards even when that wastes more', () => {
    const hand = [
        card('Wood+Metal', { wood: 2, metal: 3 }),
        card('Metal 5', { metal: 5 }),
    ];

    assert.deepEqual(names(findKraftPlan({ metal: 3 }, hand, 'least_waste')), ['Wood+Metal']);
    assert.deepEqual(names(findKraftPlan({ metal: 3 }, hand, 'retain_wood')), ['Metal 5']);
});

test('each retain strategy keeps its own resource', () => {
    const hand = [
        card('Metal+Wood', { metal: 3, wood: 1 }),
        card('Metal+Gold', { metal: 3, gold: 1 }),
        card('Metal+Leather', { metal: 3, leather: 1 }),
        card('Metal only', { metal: 2 }),
    ];
    const cost = { metal: 3 };

    for (const resource of ['wood', 'gold', 'leather']) {
        const plan = findKraftPlan(cost, hand, `retain_${resource}`);
        assert.equal(plan.resources[resource], 0, `retain_${resource} spent ${resource}`);
    }

    // Keeping metal is impossible below the cost itself, so it spends exactly the 3 metal required.
    assert.equal(findKraftPlan(cost, hand, 'retain_metal').resources.metal, 3);
});

test('reports real waste for big overpayments instead of capped totals', () => {
    const plan = findKraftPlan({ wood: 1, metal: 6 }, [
        card('Wood 6', { wood: 6 }),
        card('Metal 6', { metal: 6 }),
    ], 'least_waste');

    assert.deepEqual(plan.resources, resourceBag({ wood: 6, metal: 6 }));
    assert.equal(plan.waste.wood, 5);
});

test('retain strategies compare exact totals above the cap', () => {
    // Both plans exceed the internal wood cap (cost 2 + biggest card 6 = 8); only exact totals tell them apart.
    const plan = findKraftPlan({ wood: 2, metal: 6 }, [
        card('Wood6 Metal3 A', { wood: 6, metal: 3 }),
        card('Wood6 Metal3 B', { wood: 6, metal: 3 }),
        card('Wood3 Metal3', { wood: 3, metal: 3 }),
    ], 'retain_wood');

    assert.equal(plan.resources.wood, 9);
    assert.ok(names(plan).includes('Wood3 Metal3'));
});

test('prefers cards already in the rekup pool over carried gear', () => {
    const plan = findKraftPlan({ metal: 2 }, [
        card('Carried Sword', { metal: 2 }, false),
        card('Pool Metal 3', { metal: 3 }, true),
    ], 'least_waste');

    assert.deepEqual(names(plan), ['Pool Metal 3']);
});

test('falls back to carried gear when the pool cannot pay', () => {
    const plan = findKraftPlan({ metal: 4 }, [
        card('Carried Sword', { metal: 2 }, false),
        card('Pool Metal 3', { metal: 3 }, true),
    ], 'least_waste');

    assert.deepEqual(names(plan), ['Carried Sword', 'Pool Metal 3']);
});

test('returns null when nothing can cover the cost', () => {
    assert.equal(findKraftPlan({ gold: 5 }, [card('Gold 2', { gold: 2 }), card('Wood 9', { wood: 9 })]), null);
    assert.equal(findKraftPlan({ gold: 1 }, []), null);
});

test('zero-cost upgrades need no payment cards', () => {
    const plan = findKraftPlan({}, [card('Metal 2', { metal: 2 })]);

    assert.equal(plan.free, true);
    assert.equal(plan.cards.length, 0);
});

test('matches exhaustive search on 3000 random hands for every strategy', () => {
    const next = random(20261029);
    const amount = (max) => (next() < 0.45 ? 0 : 1 + Math.floor(next() * max));

    for (let round = 0; round < 3000; round++) {
        const hand = Array.from({ length: 2 + Math.floor(next() * 9) }, (_, index) => card(`c${index}`, {
            wood: amount(4), metal: amount(4), leather: amount(3), gold: amount(3),
        }, next() < 0.7));
        const cost = { wood: amount(8), metal: amount(9), leather: amount(6), gold: amount(7) };

        if (Object.values(cost).every((value) => value === 0)) {
            assert.equal(findKraftPlan(cost, hand).free, true);
            continue;
        }

        for (const strategy of strategies) {
            const expected = bruteForce(cost, hand, strategy);
            const actual = findKraftPlan(cost, hand, strategy);

            if (! expected) {
                assert.equal(actual, null, `round ${round} ${strategy}: found a plan where none exists`);
                continue;
            }

            assert.ok(actual, `round ${round} ${strategy}: missed a valid plan`);
            assert.deepEqual(
                scorePlan(actual, strategy, resourceBag(cost)),
                expected.score,
                `round ${round} ${strategy}: suboptimal plan`,
            );
        }
    }
});
