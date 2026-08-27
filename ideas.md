# Quetta AgriLink — Design Direction

## Three candidate approaches

| Theme Name | Very Brief Intro | Probability |
|---|---|---:|
| Orchard Ledger | An editorial, information-forward marketplace that feels grounded in a trusted regional commodity exchange. It pairs agricultural texture with disciplined operational clarity. | 0.06 |
| Valley Wayfinding | A light, map-led service directory that makes the crop journey feel geographic, connected, and locally rooted. | 0.03 |
| Cold Chain Signal | A restrained industrial logistics system built around status, capacity, and movement. It uses utilitarian hierarchy without slipping into a generic dashboard. | 0.08 |

## Chosen approach: Orchard Ledger

### Design Movement

**Contemporary editorial commerce** inspired by agricultural trade bulletins, field ledgers, and clear B2B procurement interfaces. It should feel established and trustworthy rather than fashionable or decorative.

### Core Principles

1. **Commodity first:** Product, grade, quantity, origin, and availability must be easy to scan before decoration.
2. **Measured regional character:** Agricultural material and understated Balochistan context appear through photography, crop imagery, fine rules, and earth-toned details—not visual clichés.
3. **Operational certainty:** Status, capacity, price movements, and actions have an explicit visual hierarchy and plain-language labels.
4. **Purposeful restraint:** An 8px spacing rhythm, modest 10px component radius, thin borders, and selective shadows avoid a generic SaaS-card aesthetic.

### Color Philosophy

The visual foundation is **warm off-white** like agricultural paper and produce labels, avoiding sterile pure white. **Deep forest green** provides a dependable institutional anchor for navigation and primary actions. **Orchard green** communicates healthy supply, while **weathered clay** identifies origin and secondary context. **Amber** is reserved for attention, price movement, and pending operational states. Text remains near-charcoal for legibility and no large gradients will be used.

### Layout Paradigm

Public pages use a **field-to-market narrative**: wide, image-led bands transition into aligned ledger-like rows, split columns, and asymmetric information rails. Marketplace pages prioritize a persistent filter rail beside an adaptable product ledger. Authenticated workspaces use a quiet, narrow sidebar, practical header, and an activity-driven content canvas—never a collection of oversized dashboard cards.

### Signature Elements

1. **Harvest lines:** fine horizontal rules and row dividers recalling produce-grade sheets.
2. **Origin stamps:** compact metadata labels that surface district, grade, season, and availability with an agricultural-trade vocabulary.
3. **Crop bands:** small solid color indicators based on crop family, used consistently in listings, charts, and status summaries.

### Interaction Philosophy

The interface responds like a reliable market clerk: immediate, concise, and confirmatory. Search and filtering update results asynchronously with clear loading and empty states. Actions require unambiguous confirmation and return readable success or error feedback. Keyboard focus, touch target size, and responsive table alternatives are treated as core requirements.

### Animation

Motion is limited to useful feedback. Buttons use a 120–160ms press response, dropdowns and drawers enter over 180–220ms with a decisive ease-out, and filtered results use a subtle 160ms opacity transition. No parallax, looping decorative animation, or large entrance sequences. All non-essential motion respects `prefers-reduced-motion`.

### Typography System

**Noto Serif** provides an authoritative editorial display face for primary headlines and key commodity names. **Noto Sans** handles UI, data, labels, tables, and forms for excellent English and Urdu-script compatibility when localization is added. Heading scale is deliberate rather than oversized: page headlines 40–52px, section headings 28–32px, interface headings 16–20px, and metadata 12–13px with controlled letter-spacing.

### Brand Essence

**Quetta AgriLink is the practical post-harvest marketplace for Balochistan growers and trade partners who need selling, storage, and transport to work together.**

Personality: **grounded, dependable, exacting**.

### Brand Voice

Headlines are direct and outcome-led. CTAs use concrete verbs and microcopy explains what happens next in simple commercial language—never vague startup language.

> “Know what is ready. Move it with confidence.”

> “Compare available Grade A apples from Quetta growers.”

### Wordmark & Logo

The mark is an interlocking **leaf and route pin** built from two solid, geometric shapes: one points upward like a leaf vein while the negative space forms a road. It signals crop origin and connected delivery without an illustrated farm scene. The wordmark will pair a refined Noto Serif treatment for “Agri” with a firm Noto Sans “Link.”

### Signature Brand Color

**Quetta Canopy — #1D4A36**. This deep green is the unmistakable anchor for the platform’s navigation, primary actions, and brand mark.

## Implementation guardrails

- Use only the project brief’s required PHP, MySQL, vanilla JavaScript/AJAX, HTML5, CSS3, and Tailwind CSS architecture for the deployable product package.
- Keep all demonstration records explicitly identified as **demo data** and document local credentials. Never use fabricated reviews, ratings, testimonials, or customer logos.
- Create reusable elements for headers, navigation, sidebars, alerts, modals, forms, tables, and pagination; server state remains authoritative.
- Every role-specific experience must preserve the Orchard Ledger visual system while prioritizing the role’s actual operational information.

## Style Decisions

- Marketplace screens use a ledger scan order: product, grade, origin, available quantity, expected price, and availability read before supporting imagery.
- Fixed crop-family bands appear consistently on marketplace records, price rows, and workspace records using restrained apple, grape, apricot, root-crop, and nut earth tones.
- Commodity-facing public pages retain regional crop-handling, storage, or transport imagery; purely abstract hero treatments are avoided.
- Primary user-facing copy stays commercial and operational. Implementation details and development terminology are excluded from customer-facing content.
- Transactional screens behave as trade tickets: crop context, accountable terms, calculated total, and recorded status belong to one visual ledger unit.
- The Quetta AgriLink leaf-route mark and mixed serif/sans wordmark are deliberately prominent at public and workspace entry points.
- Functional and workspace screens use upright, exacting ledger hierarchy; expressive italic display is reserved for public storytelling.
- Empty states remain dormant trade registers with operational columns, accountable status language, and a clear next action.
- Noto Serif display remains sturdy and trade-bulletin-like; expressive italics are occasional storytelling emphasis only, never the dominant marketplace or operational voice.
- Every public route and fallback state inherits the warm paper ground, Quetta Canopy anchor, harvest rules, operational labels, and a commercial next action.
- Storage and transport present capacity, location, timing, status, and accountable next steps as one post-harvest trade record rather than generic service cards.
- Workspace typography uses Noto Sans for dashboard structure, guidance, controls, metrics, status, and summaries; Noto Serif is reserved for page titles, brand moments, and commodity names.
- Every role dashboard leads with the actionable attention queue, followed by its direct trade action; onboarding and quick guidance remain compact support layers.
- Workspace headers retain the Quetta AgriLink leaf-route mark and mixed serif/sans wordmark; a task control supports navigation but never replaces branded entry context.
- At mobile widths, trade ledgers become self-contained tickets: product, origin or trade context, grade or quantity, status, and the next review step remain visible without horizontal table dependence.
- Operational visual weight follows the working sequence: attention queue first, direct commercial action second, then activity, guidance, shortcuts, metrics, and supporting record ledgers.
- Primary workspace actions use concrete commercial verbs such as publish availability, review offer, arrange storage, confirm booking, and review delivery request—never generic “Open task” language.
- Functional screens use Noto Sans for their primary hierarchy; any Noto Serif treatment is limited to the brand, a restrained title moment, or commodity names.
- Workspace shortcuts, metrics, guidance, and form surfaces are treated as linked trade-register units through fine rules, crop/status bands, explicit record labels, and accountable commercial actions.
- Marketplace hero surfaces retain at least one visible Balochistan commodity-handling or origin signal, such as produce, storage, transport, harvest timing, or district context, rather than relying on abstract copy alone.
- Functional pages open with a visible working state and one accountable next action before any broad editorial framing.
- Dormant states retain the columns and ownership language of a trade register, state the current status, and name one concrete commercial next step.
- Administrative workspaces lead with their attention queue; static metrics follow as supporting proof rather than defining the first action.

## UX Redesign Decision — Market Desk

The current colors, material feeling, and regional trade identity remain. The redesign corrects the experience rather than replacing the brand: **Market Desk** turns Orchard Ledger from an editorial brochure into an approachable working tool for an ordinary grower, buyer, storage operator, transporter, or administrator.

### Structure and hierarchy

1. **Lead with a task, not a slogan.** Every public page starts with a compact page purpose, one primary next step, and plain-language choices such as *Find produce*, *Book storage*, *Arrange transport*, or *Publish availability*.
2. **Make the working state visible.** Dashboard pages open with a short “Needs your attention” queue, then show essential metrics and recent records. Status is explained in human language beside the action it affects.
3. **Reduce reading load.** Serif display text is kept to a restrained page title or section moment. Forms, filters, dashboards, and data registers use sturdy sans-serif hierarchy, larger labels, generous click targets, and one clear primary action per panel.
4. **Make navigation self-evident.** Public navigation is shortened around core tasks. Account pages use a clear role selector and sign-in/sign-up switch. Workspaces use a compact, labelled task navigation with a visible return route and notification status.

### Layout grammar

Public screens use a **task launcher → proof of availability → guided next step** sequence instead of multiple long editorial bands. Authentication becomes a two-column orientation screen where the role choice and form are easily understood. Workspaces use a practical top command bar, an attention panel, readable metrics, and a responsive record list; a dark rail is a supporting navigation element, not the dominant page surface.

### Interaction and accessibility

Controls are grouped by intent, inline validation explains recovery, filter chips show active criteria, and destructive or state-changing actions remain explicit. On small screens, important actions and status appear before secondary detail; navigation becomes a compact menu rather than a squeezed desktop sidebar. The optional notification sound remains opt-in.

### Typography refinement

Noto Serif stays for brand moments and selected page headings, capped to a calm 42–48px desktop range. Noto Sans becomes the dominant operational face, with 16px body copy, 14px labels, high-contrast form text, and clear section labels. Italics are removed from functional screens.

## Redesign Decision — Quetta Workbench

### Three candidate approaches

| Theme Name | Very Brief Intro | Probability |
|---|---|---:|
| Quetta Workbench | A quiet local-market work tool that puts one real task, readable records, and short language ahead of decorative framing. | 0.04 |
| Civic Service Desk | A public-service-inspired system of simple panels and unambiguous steps, built for first-time users. | 0.07 |
| Field Notebook | A practical, tactile record book with calm paper surfaces and clear handwritten-scale emphasis. | 0.02 |

### Chosen approach: Quetta Workbench

**Design Movement.** Swiss service design and practical regional-market signage replace the previous editorial-commerce emphasis. The product should read as an ordinary working tool, not as a branded concept page.

**Core Principles.** Every screen names the current task in a plain heading, shows one primary action, keeps related controls together, and moves supporting details below the working record. Navigation is short and consistent. Tables, forms, and status rows use familiar office-tool patterns rather than ornamental cards.

**Color Philosophy.** Retain Quetta Canopy as the ownership and action color, but move it out of large dark surfaces. Pale mineral backgrounds, white work panels, charcoal text, and a single restrained clay accent make the interface lighter, calmer, and easier to scan.

**Layout Paradigm.** Public pages use a short task bar followed by a single primary work area. Authenticated pages use a slim light navigation rail, a compact page bar, and stacked work panels. Dashboards lead with an actionable queue and simple numeric strips; large introductory blocks, decorative hero cards, and repeated explanatory bands are removed.

**Signature Elements.** A small green action marker identifies the active task; one-pixel rules separate records; and compact status chips give state meaning without large colored containers. These motifs must remain subtle and functional.

**Interaction Philosophy.** Each click should answer a practical question: where to go, what requires attention, or what happens next. Forms give labels, a short helper sentence only where needed, and one concrete submit label. Hover and motion are restrained; keyboard focus stays prominent.

**Animation.** Use only 120–160ms opacity and transform feedback on buttons and menus. No entrance reveals, parallax, pulsing, or decorative motion. Respect reduced-motion preferences.

**Typography System.** DM Sans is the default UI face for all headings, records, forms, and actions. DM Mono is reserved for dates, references, and compact metadata. Playfair Display remains only in the existing brand wordmark; it must not lead operational pages. Page titles range from 28–36px, section titles from 18–22px, and body text defaults to 14–16px.

**Brand Essence.** Quetta AgriLink is the local working place for growers and trade partners to manage produce, storage, transport, and support. Personality: **clear, practical, dependable**.

**Brand Voice.** Use short verbs, familiar nouns, and direct outcomes. Headlines say what a person can do, and helper text tells them only what is needed. Examples: “Review new storage requests.” and “Add a price record from an approved source.” Generic slogans, development language, and fabricated reassurance are prohibited.

**Wordmark & Logo.** Keep the existing leaf-route mark and two-line Quetta AgriLink wordmark, but present it as a compact navigation anchor—not a dominant decorative feature.

**Signature Brand Color.** Quetta Canopy — `#1D4A36` — is reserved for active navigation, primary actions, and meaningful data emphasis.

## Quetta Workbench Style Decisions

- Remove every rendered `.eyebrow` and `.desk-kicker` label from authoritative PHP pages; do not merely hide them with CSS.
- Use plain-language page titles and compact helper text; keep explanatory paragraphs to one or two sentences.
- Replace dark, dominant workspace rails with light navigation and a clear active task marker.
- Keep one primary task panel per page above supporting records and avoid large decorative hero treatments on operational screens.
- Use DM Sans in operational and account screens; reserve Playfair only for the fixed brand wordmark.
- Review finding: the local PHP home capture showed that the former remote image path can render as an empty dark block in an XAMPP context. The public entry panel must communicate through useful local-work context without relying on a remote hero image.
- Review finding: the light workspace rail, single primary action, collapsed supporting activity, direct metric strip, and simpler dashboard titles materially reduce the former dark, dense dashboard treatment and remain the direction for all role pages.
- Review finding: the administrator dashboard retains a scan-friendly record table and compact task bar even with its larger navigation set. Remaining guidance copy should be reduced to short record-specific sentences rather than editorial explanations.
- Review finding: the mobile buyer dashboard keeps the active task, two-column status summary, recent record, and direct links visible without the prior dominant sidebar. The shared footer still contained generic brand language and must use the same practical wording.

## Layout Repair Decisions

- Remove all decorative image columns and large dark authentication panels when no useful local content occupies that space.
- Keep public content inside one responsive working width with a consistent horizontal gutter; service pages use a short text-only heading instead of a full-bleed split hero.
- Center account forms in a single compact panel and place role guidance inside the form only when it supports completing the task.
- Keep dashboards as a narrow navigation rail plus a padded, width-limited work area. Tables, summaries, and task panels must align to the same inner edge.

## Layout Repair Review

- The sign-up screen now keeps the form in a single centered work panel, removing the unused dark half-screen and placing every required field inside an easy-to-scan reading width.
- The buyer dashboard now has a visible inner gutter around the header, task panel, activity controls, summaries, record table, and direct links; its workspace content no longer touches the sidebar or browser edge.

## Discovery and Summary Review

- Storage discovery keeps filters in a compact left rail and renders populated facilities as independent vertical cards with capacity, price, compatible produce, and a clear next action.
- Role dashboards group factual record counts beneath one “At a glance” heading, preserving the existing account-scoped figures while making the first scan more direct.
