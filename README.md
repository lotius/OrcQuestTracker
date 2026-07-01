# Next Steps

The "Skill Cards Purchased", "Weapons, Armor, Enchants", and "Inventory Items" sections need to be more visually distinct.

Also, I want each section to be an openable tray. So that each character card can be condensed down to just some basic information when not actively viewing that character's information.

# OrcQuest Upgrade Tracker

OrcQuest Upgrade Tracker is a local Laravel/Vite app for tracking an OrcQuest campaign party, inventory, rekup resources, upgrade readiness, quest progress, and kraft planning.

The app is built for campaign-night use: update characters as items move around, let auto-save preserve the tracker, and use the kraft planner to choose exactly which inventory cards should be consumed for upgrades.

## Running The App

Install dependencies if needed:

```bash
composer install
npm install
```

Start the local development server:

```bash
composer run dev
```

Open the app:

```text
http://127.0.0.1:8000
```

Build frontend assets:

```bash
npm run build
```

Run tests:

```bash
php artisan test
```

## Main Tabs

The tracker is split into three tabs:

- `Characters`: party sheets, heroes, skills, equipment, inventory, and rekup flags.
- `Resources & Upgrades`: rekup pool totals, eligible upgrades, and the kraft planner.
- `Campaign`: campaign name, quest checklist, and notes.

The active tab is remembered in the browser.

## Characters

The Characters tab supports up to six character slots.

Each character tracks:

- player name
- selected hero
- hero icon, class, health, and starting items
- skill cards purchased at 10, 20, and 30 badass points
- hand items, armor, artifact item, and enchants
- 15 inventory slots
- rekup status for inventory items

Hero-specific skill choices update automatically when a hero is selected.

## Inventory And Equipment

Inventory and equipment item selectors are filtered so the same item card cannot be assigned to multiple places at once.

Each hand item has its own enchant slots:

- Hand item 1 left enchant
- Hand item 1 right enchant
- Hand item 2 left enchant
- Hand item 2 right enchant

Armor and artifact items also keep their own left and right enchant slots. Inventory rows use the same pattern, with the item in the center, the left enchant on the left, and the right enchant on the right, so enchants can follow an item when it moves between equipment and inventory.

Inventory slots include a `Rekup` toggle. Turning this on means the card is being broken down for its rekup resources instead of being kept as usable gear. Rekuped cards contribute their listed wood, metal, leather, and gold values to the shared Rekup Pool on the Resources & Upgrades tab.

Use the Rekup toggle when:

- a character is carrying a card only to break it down
- you want its resources counted toward available upgrades
- you want the kraft planner to treat it as part of the current payment pool

Leave Rekup off when:

- the item is still being carried or equipped for use
- you do not want its resources counted yet
- you are still deciding whether to spend it

Items marked as rekup count as 1 inventory weight instead of their printed item weight.

Some cards are automatically locked into Rekup status because they are not meaningful inventory/equipment choices in this tracker. These include resource-only cards, gold cards, and potions:

- Gold Nuggets
- Gold Teeth
- Resources cards, such as `Resources - 1 Metal`
- Potion cards, such as `Potion of Usoholi`

These cards appear with the Rekup checkbox already checked and disabled. Their enchant slots are also cleared and disabled because automatic-Rekup cards are not usable gear. Empty inventory slots also keep the Rekup checkbox disabled until an item is selected.

Inventory items can be dragged between inventory slots and equipment slots. When items are moved, their rekup state is preserved where appropriate. Always-rekup items stay checked and locked even after being moved.

Two-handed item handling is automatic:

- selecting a two-handed item, such as a Bow, Crossbow, or Heavy Crossbow, mirrors it into both hand slots
- the real item bundle lives in Hand item 1
- Hand item 2 becomes a locked mirror/occupancy slot
- Hand item 2 enchant fields are cleared and disabled while mirrored
- conflicting hand items are moved into the first available inventory slot when possible
- displaced items keep their left and right enchants when moved to inventory

## Rekup Pool

The Resources & Upgrades tab shows the current pooled rekup totals:

- wood
- metal
- leather
- gold

These totals come from inventory items marked as rekup, including always-rekup cards that are checked and locked automatically.

## Weight Warnings

Each character card shows inventory, hands, and armor weight totals.

Badges turn red when a character exceeds their hero's limit:

- `Inv`
- `Hands`
- `Armor`

Items marked as rekup count as 1 inventory weight.

## Eligible Upgrades

The Eligible Upgrades panel lists owned upgradeable items that can currently be upgraded using the pooled rekup resources.

Each row shows:

- item card
- character and location
- kraft cost

This list updates live as inventory and rekup choices change.

## Kraft Planner

The Kraft Planner helps choose exact inventory cards to consume when paying for an upgrade.

Use it by selecting:

1. `Upgrade Target`: an owned upgradeable item.
2. `Strategy`: how the payment should be optimized.

The planner shows:

- target item
- current resource pool
- kraft cost
- missing resources
- suggested payment resources
- wasted resources
- exact cards to consume

The `Missing now` row turns red when the target cannot be paid from the current rekup pool.

## Kraft Strategies

Strategies decide which exact cards should be consumed.

`Least amount of wasted resources`

Finds the payment closest to the kraft cost. If multiple options waste the same amount, it prefers fewer cards.

`Retain the most wood`

Avoids consuming cards that generate wood when possible.

`Retain the most metal`

Avoids consuming cards that generate metal when possible.

`Retain the most leather`

Avoids consuming cards that generate leather when possible.

`Retain the most gold`

Avoids consuming cards that generate gold when possible.

The `?` button beside Strategy opens a modal explaining these options.

## Consuming Payment Cards

After the kraft planner suggests payment cards, click:

```text
Consume Payment Cards
```

This will:

- remove the suggested payment cards from their inventory slots
- leave the target upgrade item in place
- compact each character's inventory so remaining items move into the earliest slots
- refresh resources and upgrade availability
- auto-save the result

## Campaign Tracking

The Campaign tab tracks quest progress by quest book:

- Core Quest Book
- Gorbag's Tales
- Sewer Fever
- Royal Mines
- Elven Vestiges
- Beast in the Woods
- Blood for Blood
- Death Crypt
- The Bacon & Eggs Conspiracy
- Greedy & Doomed
- A Shadow Over Ratsmouth
- Wings & Claws

The Campaign tab also includes freeform notes for campaign memory.

## Campaign Management

The header includes campaign controls:

- campaign selector
- `Load`
- `Reset`
- `Save Tracker`

The bottom of the form includes:

- `Save As New Campaign`

Campaigns are stored locally in Laravel storage as JSON.

## Auto-Save

The tracker auto-saves shortly after edits.

The header shows current save status:

- `Auto-save ready`
- `Unsaved changes...`
- `Saving...`
- `Saved [time]`
- failure message if save fails

The manual `Save Tracker` button remains available as a safety fallback.

## Undo And Redo

The header includes tracker-wide:

- `Undo`
- `Redo`

Undo/redo tracks the last 25 browser-session actions across the full tracker form, including:

- character choices
- skills
- equipment
- inventory
- rekup flags
- quest checks
- notes
- campaign name
- consume-payment-card actions

Restoring an undo/redo state refreshes derived values and auto-saves the restored tracker state.

## Data Source

Catalog data lives in:

```text
resources/data/orcquest.json
```

That file contains:

- heroes
- items
- enchants
- campaign quests

Item metadata powers weight calculations, rekup values, kraft costs, upgrade eligibility, always-rekup behavior, and the kraft planner.

## Tech Stack

- Laravel
- Blade
- Vite
- Tailwind CSS
- Vanilla JavaScript

No database is required for the current tracker state; campaign data is persisted to local JSON storage.
