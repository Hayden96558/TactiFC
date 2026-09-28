# TactiFC

**Find Your Perfect FC Formation**

TactiFC is a football tactics website for **EA Sports FC25, FC26 and FC27**. It lets you
explore formations, legendary manager tactics and a full **Player Roles & Focuses**
system — including version-specific roles — with an interactive tactic builder.

It runs directly on **Apache + PHP** through **MAMP**. There is **no Node.js, no npm,
no Composer, no build step and no database required**. Just drop the folder in `htdocs`
and open it in your browser.

---

## 1. Requirements

* **MAMP** (or MAMP PRO) — <https://www.mamp.info/>
* **PHP 8.0 or newer** (MAMP ships with PHP 8.x by default — no installation needed)
* A modern browser (Chrome, Safari, Firefox or Edge)

That is all. You do **not** need MySQL: the first version uses JSON files as its database.

---

## 2. Install MAMP

1. Download MAMP for macOS from <https://www.mamp.info/en/downloads/>.
2. Open the downloaded `.dmg` and drag **MAMP** into your **Applications** folder.
3. Launch **MAMP** from Applications.

---

## 3. Where to put the TactiFC folder

MAMP serves files from a **document root**. By default this is:

```
/Applications/MAMP/htdocs
```

Copy the whole `TactiFC` project folder into that directory so you end up with:

```
/Applications/MAMP/htdocs/TactiFC/index.php
```

### Changing the document root (optional)

If you prefer a different folder:

1. Open MAMP and click **Preferences** (or **Settings**).
2. Go to the **Web Server** tab.
3. Click the folder icon next to **Document Root**.
4. Choose your folder (for example your own `htdocs`).
5. Click **OK** and restart the servers.

If you change the document root, still place `TactiFC` inside that new folder.

---

## 4. Start the servers and open the site

1. In MAMP, click **Start Servers** (both Apache and MySQL will start — MySQL is not
   required by TactiFC but it does no harm).
2. Wait until both indicator lights are **green**.
3. Open your browser and go to:

   ```
   http://localhost:8888/TactiFC/
   ```

   > **Port note:** MAMP's default Apache port is **8888**. If you changed it (MAMP
   > sometimes uses 80), just replace `:8888` with your port. TactiFC works on any port —
   > nothing is hard-coded. You can check the current port on the MAMP start screen.

4. The TactiFC homepage should load immediately.

### Quick troubleshooting

| Problem | Fix |
|---|---|
| “Not found” at `/TactiFC/` | Make sure the folder is named exactly `TactiFC` and sits directly inside `htdocs`. |
| Browser downloads the PHP file | Apache is not running — click **Start Servers** in MAMP. |
| Page loads but looks unstyled | Hard-refresh with **Cmd + Shift + R** to clear cached CSS. |
| Port 8888 already in use | In MAMP → Preferences → Ports, change Apache to **80** or another free port. |

---

## 5. How to use the site

| Page | What it does |
|---|---|
| **Home** (`index.php`) | Hero search, stats and recommended tactics. |
| **Formations** (`formations.php`) | All formations with an interactive pitch preview. |
| **Formation detail** (`formation.php?id=4-3-3`) | Base / with-ball / without-ball shapes, strengths, limitations, common roles, variations and example tactics. |
| **Managers** (`managers.php`) | Browse managers and open their profiles. |
| **Manager profile** (`manager.php?id=jose-mourinho`) | Characteristics, formations used, average DNA, a clickable season timeline and all tactics. |
| **Tactics** (`tactics.php`) | Browse every tactic with full filters (manager, club, season, formation, playstyle, player role, position, FC version, **game mode**, **tag**). |
| **Tactic detail** (`tactic.php?id=...`) | Overview strip, Tactic DNA, phases, how-to-play, adjustments, with/without-ball shapes, movement overlay, variations, share/print/duplicate. |
| **Player Roles** (`roles.php`) | Browse every role, filter by FC version and position, and compare roles. |
| **Recommendations** (`recommendations.php`) | Style-based and role-based suggestions. |
| **Generator** (`generator.php`) | Generate a tactic from your preferences, or get explained database recommendations. |
| **Tactic Builder** (`builder.php`) | Design your own tactic: draggable players, version-specific roles, instructions, base/with/without-ball views, save/duplicate/rename/import/export. |
| **Player Fit** (`player-fit.php`) | Enter your squad and get suggested roles with a transparent TactiFC Tactical Fit estimate. |
| **Compare** (`compare.php`) | Comparison 2.0: DNA comparison, with/without-ball shapes, side-by-side pitches, roles, philosophy. |
| **Favorites** (`favorites.php`) | Your favorites, recently viewed (last 10, clearable) and custom tactics. |
| **Trending** (`trending.php`) | Curated editor's picks plus your own local activity (clearly labelled demo statistics). |

### Advanced features explained

**Tactic DNA** — every tactic has a visual tactical profile across Possession, Pressing, Directness, Defensive Solidity, Width, Tempo, Counter-Attacking and Creativity. These are **subjective fan-recreation profiles** for EA Sports FC, clearly labelled *"Tactical Profile — Fan Recreation"*, not objective measurements of the original historical teams.

**In possession / out of possession** — every tactic has **BASE**, **WITH BALL** and **WITHOUT BALL** shapes. Switch between them to see how the pitch morphs. A **Show Movement** toggle overlays arrows that are a visual interpretation of the selected roles, not a simulation of the game's animation.

**Tactical phases** — the "How This Tactic Works" section breaks each tactic into Build Up, Progression, Final Third, Defensive Transition and Defensive Shape.

**How To Play** — practical guidance for when you have the ball, lose it, or are defending, plus winning/losing adjustments.

**Tactical Adjustments** — suggested options for common situations (opponent pressing high, defending deep, protecting a lead). Presented as options, not guaranteed counters.

**Difficulty** — Beginner / Intermediate / Advanced, with an explanation of what the setup requires. This describes complexity, not how "good" a tactic is.

**Game mode filter** — tactics specify the modes they suit: Career Mode, Ultimate Team, Clubs, Kick-Off.

**Tags** — tactics carry clickable tags (e.g. Counter Attack, Wide, Fast Wingers) that link to related tactics.

**Share & export** — every tactic has Copy Link, Copy Tactic (a readable summary), Print (printer-friendly), and Duplicate & Edit. In the Builder you can Export as JSON and Import JSON to restore a tactic.

**Recently Viewed & Trending** — the last 10 tactics you open are stored locally and shown on the Favorites page (with Clear History). Trending combines curated picks with your own local activity — there are **no fake site-wide statistics** since no analytics backend exists yet.

### Searching

The search supports partial terms, combined words, and grouped autocomplete suggestions (Managers / Clubs / Tactics / Formations / Roles / Tags). Examples:

* `mour` → José Mourinho
* `inter` → Inter Milan
* `2009` → 2009/10
* `433` → 4-3-3
* `counter` → Counter Attack tactics
* `pep barca` → Guardiola Barcelona tactics
* `Deep-Lying Playmaker` → tactics using that role
* `Compact` → tactics tagged or styled Compact

---

## 6. How the Player Roles system works

Player roles are a core feature. They live in:

```
data/player_roles.json
```

The data is **keyed by game version** (`fc25`, `fc26`, `fc27`) and then by position:

```json
{
  "fc26": {
    "GK": [
      { "role": "Ball-Playing Keeper", "focuses": ["Build-Up"], "description": "...", "attacking": ["..."], "defending": ["..."] }
    ]
  }
}
```

This means a role available in FC26 might **not** exist in FC25. For example:

* **FC25** uses roles such as *Sweeper Keeper* and *Wing Back*.
* **FC26** adds *Ball-Playing Keeper*, *Wide Back*, *Inverted Wingback* and *Box Crasher*.

The Tactic Builder and the tactic detail pages always read the available roles from
this file, filtered by the selected position and FC version, so **invalid combinations
are impossible** (e.g. you cannot give a striker the "Holding" role).

Each tactic stores its players **inside each version**, so the same historical tactic
can have a different recreation per game:

```json
"versions": {
  "fc25": { "players": [ { "position": "CDM", "role": "Holding", "focus": "Defend", "familiarity": "Role++" } ] },
  "fc26": { "players": [ { "position": "CDM", "role": "Deep-Lying Playmaker", "focus": "Defend", "familiarity": "Role+" } ] },
  "fc27": { "players": [ ... ] }
}
```

---

## 7. How to add a new tactic

1. Open `data/tactics.json`.
2. Copy an existing tactic object and paste it as a new entry (make sure to add a comma
   between objects).
3. Give it a **unique `id`**. Use lowercase words separated by dashes, e.g.
   `guardiola-city-2024`.
4. Fill in the fields:

```json
{
  "id": "guardiola-city-2024",
  "manager": "Pep Guardiola",
  "club": "Manchester City",
  "season": "2023/24",
  "formation": "4-3-3",
  "style": ["Possession", "High Press"],
  "difficulty": "Advanced",
  "description": "Short summary shown on tactic cards.",
  "philosophy": "Longer explanation shown on the tactic page.",
  "slots": [ { "pos": "GK", "x": 50, "y": 92 } ],
  "versions": {
    "fc25": { "formation": "4-3-3", "buildUp": "Short Passing", "defensiveApproach": "High Press", "width": 7, "depth": 8, "attackingFocus": "Possession", "notes": "...", "playerInstructions": "...", "players": [ { "position": "GK", "role": "Sweeper Keeper", "focus": "Balanced", "familiarity": "Role++" } ] },
    "fc26": { "...": "..." },
    "fc27": { "...": "..." }
  }
}
```

5. Save the file. Refresh the site — your tactic appears automatically in browse,
   search and filters.

> **Important:** roles must exist for that position in `data/player_roles.json` for the
> chosen version. If you use a role that does not exist, it simply won't be offered in
> the builder. Historical tactics should be described as **inspired by** the manager or
> team — they are approximations for the game, not exact recreations.

You can also regenerate role data for all tactics with the helper script:

```bash
php tools/generate_role_data.php
```

(Run from the project folder; it rewrites the `players` arrays in `tactics.json`.)

---

## 8. How to edit the JSON data

All initial data lives in the `data/` folder:

| File | Contents |
|---|---|
| `data/tactics.json` | All tactics, versions and player roles per version. |
| `data/managers.json` | Managers, their clubs, styles and bios. |
| `data/clubs.json` | Clubs and leagues (used for reference). |
| `data/formations.json` | Formations and their pitch coordinates. |
| `data/player_roles.json` | The version-specific roles and focuses database. |

**JSON tips**

* Use a good editor (VS Code, Sublime, or an online JSON validator).
* Strings must use **double quotes** `"`.
* No trailing commas after the last item in a list or object.
* Validate after editing (see below).

Validate all JSON files quickly with PHP:

```bash
for f in data/*.json; do php -r "json_decode(file_get_contents('$f')); echo '$f: ', json_last_error_msg(), PHP_EOL;"; done
```

---

## 9. How to customise the website

* **Colours & theme** — edit the CSS variables at the top of
  `assets/css/style.css` (the `:root { ... }` block). Changing `--brand` and `--brand-2`
  re-skins the whole site.
* **Navigation** — edit the `$navItems` array in `includes/header.php`.
* **Footer** — edit `includes/footer.php`.
* **Site name / tagline / meta** — see `includes/data.php` and the top of
  `includes/header.php`.
* **Pitch look** — the pitch markings are inline SVG in `includes/pitch.php`; the colours
  are the `--pitch-*` variables in the stylesheet.
* **Homepage examples** — edit the `$exampleSearches` array in `index.php`.
* **DNA categories / shape labels** — edit `data/tactical_intel.json` (see below).
* **Print layout** — the `@media print` block at the bottom of `style.css`.

---

## 10. Project structure

```
TactiFC/
├── index.php               Homepage
├── formations.php          Formation explorer
├── formation.php           Single formation (base/with/without ball)
├── managers.php            Manager database
├── manager.php             Manager profile (timeline, characteristics)
├── tactics.php             Browse + filter all tactics
├── tactic.php              Tactic detail (DNA, phases, shapes, roles)
├── search.php              Advanced search
├── recommendations.php     Style & role recommendations
├── roles.php               Player role catalogue
├── role.php                Single role + role comparison
├── generator.php           Tactic generator + recommendation engine
├── player-fit.php          Player Fit system
├── builder.php             Interactive tactic builder
├── compare.php             Comparison 2.0
├── favorites.php           Favorites / recent / custom tactics
├── trending.php            Curated picks + local activity
├── .htaccess               Optional Apache rules
│
├── api/
│   └── search-index.php    JSON endpoint for live search suggestions
│
├── assets/
│   ├── css/style.css       All styling (dark theme, pitch, DNA, print, responsive)
│   ├── js/
│   │   ├── app.js            Nav, tabs, filters, toasts
│   │   ├── search.js         Grouped live search suggestions
│   │   ├── favorites.js      localStorage favorites / recent / views / custom
│   │   ├── roles.js          Tactic-page role tables + modal
│   │   ├── tactic.js         Tactic shapes + movement overlay
│   │   ├── share.js          Copy link / copy tactic / print
│   │   ├── builder.js        Tactic Builder logic
│   │   ├── player-fit.js     Squad editor + fit estimator
│   │   ├── formation-shapes.js  Formation shape switching
│   │   └── trending.js       Personal activity renderer
│   └── images/             favicon.svg, og-default.svg
│
├── data/
│   ├── tactics.json        Tactics + light intel (DNA, tags, shapes, modes)
│   ├── tactical_intel.json Phases, how-to-play, adjustments, full DNA
│   ├── managers.json       Managers
│   ├── clubs.json          Clubs
│   ├── formations.json     Formations
│   └── player_roles.json   Version-specific roles & focuses
│
├── includes/
│   ├── header.php          Shared header + navigation
│   ├── footer.php          Shared footer + scripts
│   ├── data.php            Bootstrap: base path, URL helpers, metadata
│   ├── functions.php       Core PHP functions (data, search, roles, DNA, generator)
│   ├── cards.php           Reusable card renderers
│   ├── pitch.php           Reusable pitch, DNA and overview components
│   └── share.php           Share / print / duplicate action bar
│
├── tools/
│   ├── generate_role_data.php   Regenerate version-specific role data
│   └── merge_tactic_meta.php    Merge intel metadata into tactics.json
│
└── README.md
```

### Data architecture (MySQL-ready)

All data access goes through reusable functions in `includes/functions.php` — pages never
read JSON files directly. This means the storage layer can later be swapped from JSON to
MySQL without rebuilding the pages. The functions to replace are the small group of
loaders near the top (`loadJson`, `loadTactics`, `loadManagers`, `loadClubs`,
`loadFormations`, `loadPlayerRoles`, `loadTacticalIntel`).

Lightweight per-tactic intelligence (DNA, tags, game modes, shape labels, variations)
lives on each record in `tactics.json` so list pages stay fast. The heavier narrative
intelligence (phases, how-to-play, adjustments, full DNA) lives in
`data/tactical_intel.json` and is only loaded by pages that display it.

### Tactical intelligence file

`data/tactical_intel.json` holds, per tactic id:

* `dna` — 0–100 values for the eight DNA categories
* `shapes` — base / withBall / withoutBall labels
* `gameModes`, `tags`
* `phases` — buildUp, progression, finalThird, defensiveTransition, defensiveShape
* `howToPlay` — haveBall, loseBall, opponentBall, winning, losing
* `adjustments` — situations with suggested options

After editing `tactical_intel.json`, run the merge tool to copy the light fields onto
the tactic records:

```bash
php tools/merge_tactic_meta.php
```

---

## 11. Notes on data accuracy

Historical tactics are **inspired by** the managers and teams named. They are
approximations designed for EA Sports FC, not exact reproductions of real-world systems.
Player roles and focuses reflect the systems available in each game version as modelled
in this project's data file — they are not official EA ratings, and no real-player
familiarity ratings are invented.

Specific disclaimers shown in the interface:

* **Tactic DNA** — labelled *"Tactical Profile — Fan Recreation"*. Subjective, not objective measurements of real teams.
* **With / without-ball shapes** — a visual interpretation of the selected roles, not a claim to reproduce the game's internal animation.
* **Show Movement** — the same: an interpretation, not a simulation.
* **Difficulty** — describes the complexity of the setup, not how "good" the tactic is.
* **Tactical Adjustments** — options to consider, not guaranteed counters.
* **Manager characteristics** — broad tactical descriptions, not absolute descriptions of every team coached.
* **Player Fit** — labelled *"TactiFC Tactical Fit"*. A transparent weighted estimate from the attributes you enter, not an official EA rating.
* **Generator & Recommendations** — rule-based and transparent. No tactic is presented as "objectively optimal".
* **Trending** — no fake site-wide statistics. It uses curated picks plus your own local (browser-only) activity, clearly labelled.

TactiFC is an unofficial fan resource and is **not affiliated with EA Sports**.
EA Sports FC is a trademark of Electronic Arts.
