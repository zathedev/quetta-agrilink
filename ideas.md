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
