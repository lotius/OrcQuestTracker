# OrcQuest Upgrade Tracker

OrcQuest Upgrade Tracker is a local Laravel/Vite app for tracking an OrcQuest campaign party, inventory, rekup resources, upgrade readiness, quest progress, and kraft planning.

The app is built for campaign-night use: update characters as items move around, let auto-save preserve the tracker, and use the kraft planner to choose exactly which inventory cards should be consumed for upgrades.

## Running The App

### First-Time Setup

Install these tools first:

- PHP 8.3 or newer
- Composer
- Node.js and npm

On Windows, the simplest PHP path is usually Laravel Herd or XAMPP. Composer and Node.js can be installed from their official installers.

Clone or download this project, then open a terminal in the project folder.

Install PHP dependencies:

```bash
composer install
```

Install frontend dependencies:

```bash
npm install
```

Create your local Laravel environment file:

```bash
copy .env.example .env
php artisan key:generate
```

This tracker stores campaign data as local JSON files through Laravel storage, so no database is required for normal use.

If Laravel asks for a database during setup or testing, SQLite is the lightest local option:

```bash
type nul > database\database.sqlite
```

Then set these values in `.env`:

```text
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

MySQL through XAMPP, Herd, or another local server also works for Laravel's default tables, but it is not needed for tracker campaign storage.

### Daily Development

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
npm test
```

`npm test` checks the kraft planner against an exhaustive search over thousands of random card hands.

## Main Tabs

The tracker is split into four tabs:

- `Characters`: party sheets, heroes, skills, equipment, inventory, and rekup flags.
- `Kraft & Rekup`: rekup pool totals, upgrades you can afford, and the kraft planner.
- `Campaign`: campaign name, quest checklist, and notes.
- `History`: a searchable log of everything that has happened in the campaign.

The layout is responsive: on phones the tabs move to a bottom bar, inventory slots stack onto two lines, and drag handles work with touch. The active tab and character are remembered in the browser.

The visual style follows the OrcQuest WarPath rule book: parchment pages, Metal Mania headings, orc-green panels and the spiked skull emblem.

## Characters

The Characters tab supports up to six characters. New campaigns start with four; use `Add orc` to bring in more and the trash icon to remove one (this clears their sheet; Undo brings them back). A row of portrait chips switches between characters, and `Party` shows everyone at once (handy for dragging items between orcs).

Each character tracks:

- player name
- selected hero
- hero icon, class, health, and starting items
- skill cards purchased at 10, 20, and 30 badass points
- hand items, armor, artifact item, and enchants
- 15 inventory slots
- rekup status for inventory items

Hero-specific skill choices update automatically when a hero is selected. Each skill tier shows the cards learned plus one empty slot.

## Item Levels

Item names show their kraft level as stars, e.g. `BB 019 - Axe ★`. Levels are worked out from the catalog: kraft chains share an item name, cards that cannot be upgraded are level 3, the lowest-coded upgradeable card (and copies with the same kraft plan) is level 1, and the rest are level 2. Potions, resources, gold and relics have no level.

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

Items cards marked as rekup count as 1 inventory weight instead of their printed item weight.

Some cards are automatically locked into Rekup status because they are not meaningful inventory/equipment choices in this tracker. These include resource-only cards, gold cards, and potions:

- Gold Nuggets
- Gold Teeth
- Resources cards, such as `Resources - 1 Metal`
- Potion cards, such as `Potion of Usoholi`

These cards appear with the Rekup checkbox already checked and disabled. Their enchant slots are also cleared and disabled because automatic-Rekup cards are not usable gear. Empty inventory slots also keep the Rekup checkbox disabled until an item is selected.

Inventory items can be dragged between inventory slots and equipment slots, either by the item field (mouse) or by the grip handle beside each slot (mouse, touch or pen). When items are moved, their rekup state is preserved where appropriate. Always-rekup items stay checked and locked even after being moved.

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

Click an item under `Ready to Kraft` or `Almost Kraftable` to load it into the planner.

## Kraft Strategies

Strategies decide which exact cards should be consumed. Every strategy first prefers cards already in the rekup pool; cards still carried as gear are only used when the pool cannot pay, and are flagged `Carried`.

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

After the kraft planner suggests payment cards, click `Kraft it`. This will:

- remove the suggested payment cards from their inventory slots
- swap the target for a free card of the next level of the same item, keeping its enchants (if no such card is free, only the payment is made)
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

## History

Every save is compared with the previous one and the differences are logged as readable entries: characters joining or leaving, hero and player changes, skills learned, items added, removed, moved or traded between orcs, rekup flags, enchants, upgrades, kraft payments, quests and notes. Changes made through Undo or Redo are tagged as such, and rapid typing in a text field is folded into a single entry.

The History tab groups entries by day and can be searched or filtered by type and character. History is stored with each campaign; `Save as new campaign` carries it over and `Reset campaign` keeps it, adding a reset entry.

## Campaign Management

The header includes `Save`, Undo and Redo. The `⋮` menu holds:

- campaign selector and `Load`
- `Save as new campaign`
- `Reset campaign` (asks for confirmation)

Campaigns are stored locally in Laravel storage as JSON.

## Auto-Save

The tracker auto-saves shortly after edits.

The header shows current save status:

- `Auto-save ready`
- `Unsaved changes...`
- `Saving...`
- `Saved [time]`
- failure message if save fails

The manual `Save` button remains available as a safety fallback.

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
