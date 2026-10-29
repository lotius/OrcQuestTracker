// Kraft payment planner. Pure functions so the strategies can be tested in isolation (see tests/js/kraft.test.js).

export const resourceNames = ['wood', 'metal', 'leather', 'gold'];

export const resourceBag = (source = {}) => Object.fromEntries(
    resourceNames.map((resource) => [resource, Number(source?.[resource] || 0)])
);

export const addBags = (left, right) => Object.fromEntries(
    resourceNames.map((resource) => [resource, Number(left[resource] || 0) + Number(right[resource] || 0)])
);

export const subtractBags = (left, right) => Object.fromEntries(
    resourceNames.map((resource) => [resource, Math.max(0, Number(left[resource] || 0) - Number(right[resource] || 0))])
);

const sumBag = (bag) => resourceNames.reduce((total, resource) => total + bag[resource], 0);

export const strategies = ['least_waste', 'retain_wood', 'retain_metal', 'retain_leather', 'retain_gold'];

/**
 * Lexicographic score, lower is better. Every strategy first prefers cards already in the rekup pool
 * (cards still carried as gear are a last resort), then applies its own priority.
 */
export const scorePlan = (plan, strategy, cost) => {
    const wasted = resourceNames.reduce((total, resource) => total + Math.max(0, plan.resources[resource] - cost[resource]), 0);
    const notInPool = plan.cards.filter((card) => ! card.rekup).length;
    const tail = [plan.cards.length, sumBag(plan.resources)];
    const retained = strategy.startsWith('retain_') ? strategy.slice('retain_'.length) : null;

    return retained && resourceNames.includes(retained)
        ? [notInPool, plan.resources[retained], wasted, ...tail]
        : [notInPool, wasted, ...tail];
};

export const compareScores = (left, right) => {
    for (let index = 0; index < left.length; index++) {
        if (left[index] !== right[index]) {
            return left[index] - right[index];
        }
    }

    return 0;
};

const covers = (bag, cost) => resourceNames.every((resource) => bag[resource] >= cost[resource]);

/**
 * Finds the best set of whole cards whose rekup resources cover `cost`.
 *
 * Dynamic programming over resource totals. Totals are capped at cost + the largest single card so the
 * state space stays small; plans that share a capped state extend identically, and each keeps its exact
 * totals so it is scored on the real waste/retention, never on the capped numbers.
 *
 * @param {object} cost       {wood, metal, leather, gold}
 * @param {Array}  candidates [{key, resources: {wood, ...}, rekup: bool, ...}]
 * @returns {{cards: Array, resources: object, waste: object, free: boolean}|null} null when nothing can pay
 */
export const findKraftPlan = (cost, candidates, strategy = 'least_waste') => {
    cost = resourceBag(cost);

    if (sumBag(cost) === 0) {
        return { cards: [], resources: resourceBag(), waste: resourceBag(), free: true };
    }

    const useful = candidates
        .map((candidate) => ({ ...candidate, resources: resourceBag(candidate.resources) }))
        .filter((candidate) => resourceNames.some((resource) => cost[resource] > 0 && candidate.resources[resource] > 0));

    if (! useful.length) {
        return null;
    }

    const caps = Object.fromEntries(resourceNames.map((resource) => [
        resource,
        cost[resource] + Math.max(0, ...useful.map((candidate) => candidate.resources[resource])),
    ]));
    const stateKey = (bag) => resourceNames.map((resource) => Math.min(caps[resource], bag[resource])).join('|');
    const empty = { cards: [], resources: resourceBag() };
    const best = new Map([[stateKey(empty.resources), { plan: empty, score: scorePlan(empty, strategy, cost) }]]);

    useful.forEach((candidate) => {
        Array.from(best.values()).forEach(({ plan }) => {
            const next = { cards: [...plan.cards, candidate], resources: addBags(plan.resources, candidate.resources) };
            const key = stateKey(next.resources);
            const score = scorePlan(next, strategy, cost);
            const current = best.get(key);

            if (! current || compareScores(score, current.score) < 0) {
                best.set(key, { plan: next, score });
            }
        });
    });

    const winner = Array.from(best.values())
        .filter(({ plan }) => plan.cards.length && covers(plan.resources, cost))
        .reduce((champion, entry) => (! champion || compareScores(entry.score, champion.score) < 0 ? entry : champion), null);

    return winner
        ? { ...winner.plan, waste: subtractBags(winner.plan.resources, cost), free: false }
        : null;
};
