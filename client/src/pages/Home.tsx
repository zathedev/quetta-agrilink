/**
 * Orchard Ledger preview — an editorial B2B agricultural marketplace.
 * The composition uses warm paper, Quetta Canopy green, generous asymmetry, and operational data blocks.
 */
import { useState } from "react";
import {
  ArrowRight,
  ArrowUpRight,
  Box,
  ChevronRight,
  Leaf,
  MapPin,
  Search,
  Snowflake,
  Sprout,
  Tractor,
  Truck,
} from "lucide-react";

const marketRows = [
  { produce: "Pishin Apples", district: "Pishin", grade: "A", quantity: "2,400 kg", price: "Rs. 185/kg", tone: "apple" },
  { produce: "Mastung Grapes", district: "Mastung", grade: "A", quantity: "1,150 kg", price: "Rs. 265/kg", tone: "grape" },
  { produce: "Ziarat Apricots", district: "Ziarat", grade: "Premium", quantity: "860 kg", price: "Rs. 310/kg", tone: "apricot" },
];

const workflow = [
  ["01", "List", "Make supply visible", "Publish grade, quantity, origin, and price in a trade-ready format."],
  ["02", "Agree", "Trade with context", "Compare active produce and keep each offer tied to the listing."],
  ["03", "Protect", "Reserve capacity", "Request compatible cold storage when timing needs more control."],
  ["04", "Move", "Plan the handover", "Choose a capable vehicle and track each delivery milestone."],
];

const modules = {
  marketplace: {
    title: "Market-ready supply, not a generic catalogue.",
    copy: "Search current harvest by product, grade, origin, quantity, and expected price. Every listing has a clear commercial context.",
    action: "Explore marketplace",
    icon: Search,
  },
  storage: {
    title: "Protect value when timing matters.",
    copy: "Review compatible produce, available capacity, storage type, and daily price before sending a booking request.",
    action: "Find cold storage",
    icon: Snowflake,
  },
  transport: {
    title: "Match every crop with a capable vehicle.",
    copy: "Compare fleet capability, refrigeration, service area, and capacity before planning a pickup and delivery.",
    action: "Plan transport",
    icon: Truck,
  },
} as const;

type ModuleKey = keyof typeof modules;

export default function Home() {
  const [activeModule, setActiveModule] = useState<ModuleKey>("marketplace");
  const module = modules[activeModule];
  const ModuleIcon = module.icon;

  return (
    <div className="agri-shell">
      <header className="agri-header">
        <a className="agri-brand" href="#top" aria-label="Quetta AgriLink home">
          <span className="agri-brand-mark"><Leaf size={21} strokeWidth={2.4} /></span>
          <span>Quetta<br /><em>AgriLink</em></span>
        </a>
        <nav className="agri-nav" aria-label="Primary navigation">
          <a href="#market">Marketplace</a>
          <a href="#workflow">How it works</a>
          <a href="#intelligence">Market prices</a>
          <a href="#services">Services</a>
        </nav>
        <div className="agri-nav-actions">
          <a className="text-link" href="#services">Sign in</a>
          <a className="button button-canopy" href="#market">Create account <ArrowUpRight size={15} /></a>
        </div>
      </header>

      <main id="top">
        <section className="hero-section">
          <div className="hero-copy">
            <p className="eyebrow"><span /> Quetta’s agricultural trade network</p>
            <h1>Connect.<br />Store. Sell.<br /><i>Grow.</i></h1>
            <p className="hero-deck">A practical marketplace connecting Balochistan’s growers with buyers, cold storage, and reliable transportation.</p>
            <div className="hero-actions">
              <a className="button button-canopy button-large" href="#market">Explore marketplace <ArrowRight size={18} /></a>
              <a className="button button-ghost button-large" href="#workflow">See how it works <ChevronRight size={18} /></a>
            </div>
            <div className="hero-stats" aria-label="Platform statistics">
              <div><strong>4</strong><span>active produce<br />listings</span></div>
              <div><strong>1</strong><span>available storage<br />facility</span></div>
              <div><strong>1</strong><span>transport<br />provider</span></div>
            </div>
          </div>
          <div className="hero-visual" aria-label="Agricultural storage and trade visual">
            <div className="hero-visual-image" />
            <div className="hero-stamp"><Sprout size={18} /><span>From harvest<br />to handover</span></div>
            <div className="hero-location"><MapPin size={15} /><span>Quetta, Balochistan</span></div>
          </div>
        </section>

        <section className="workflow-section" id="workflow">
          <div className="section-intro split-intro">
            <div><p className="eyebrow clay"><span /> The practical path</p><h2>From harvest to handover,<br />with a clear next step.</h2></div>
            <p>Quetta AgriLink keeps the after-harvest workflow connected: supply, demand, storage, transport, and delivery status remain visible to the people doing the work.</p>
          </div>
          <div className="workflow-grid">
            {workflow.map(([number, label, title, copy]) => <article className="workflow-card" key={number}><span>{number} / {label}</span><h3>{title}</h3><p>{copy}</p></article>)}
          </div>
        </section>

        <section className="market-section" id="market">
          <div className="section-intro market-intro"><div><p className="eyebrow clay"><span /> Available produce</p><h2>Recent entries from the marketplace.</h2></div><a href="#services" className="button button-outline">View all produce <ArrowRight size={16} /></a></div>
          <div className="ledger-table">
            <div className="ledger-head"><span>Produce &amp; origin</span><span>Grade</span><span>Available</span><span>Expected price</span><span /></div>
            {marketRows.map((row) => <article className="ledger-row" key={row.produce}>
              <div className="produce-cell"><span className={`produce-dot ${row.tone}`} /><div><h3>{row.produce}</h3><p>{row.district}, Balochistan</p></div></div>
              <span><b className="grade-tag">Grade {row.grade}</b></span><span>{row.quantity}</span><strong>{row.price}</strong><a href="#services" aria-label={`Inspect ${row.produce}`}><ArrowUpRight size={18} /></a>
            </article>)}
          </div>
        </section>

        <section className="service-section" id="services">
          <div className="service-image" />
          <div className="service-panel">
            <div className="module-tabs" role="tablist" aria-label="Platform services">
              {(Object.keys(modules) as ModuleKey[]).map((key) => <button key={key} className={activeModule === key ? "active" : ""} onClick={() => setActiveModule(key)} role="tab" aria-selected={activeModule === key}>{key === "marketplace" ? "Marketplace" : key === "storage" ? "Cold storage" : "Transport"}</button>)}
            </div>
            <ModuleIcon className="module-icon" size={31} strokeWidth={1.55} />
            <p className="eyebrow light"><span /> {activeModule === "storage" ? "Capacity exchange" : activeModule === "transport" ? "Transport exchange" : "Commerce exchange"}</p>
            <h2>{module.title}</h2><p>{module.copy}</p>
            <a className="button button-light" href="#market">{module.action} <ArrowRight size={17} /></a>
          </div>
        </section>

        <section className="intelligence-section" id="intelligence">
          <div className="intelligence-copy"><p className="eyebrow"><span /> Trade intelligence</p><h2>Price awareness belongs alongside the crop, not after the deal.</h2><p>Use recorded market ranges as a reference when planning listings, purchase decisions, storage time, and transport requirements.</p><a className="button button-outline light-outline" href="#market">View market prices <ArrowRight size={17} /></a></div>
          <div className="price-board"><div className="price-board-label"><Tractor size={20} /><span>Recent reference ranges</span></div><div><span>Apples</span><strong>Rs. 185 <em>/kg</em></strong><small>Pishin market · recorded today</small></div><div><span>Grapes</span><strong>Rs. 265 <em>/kg</em></strong><small>Mastung market · recorded today</small></div><div><span>Apricots</span><strong>Rs. 310 <em>/kg</em></strong><small>Ziarat market · recorded today</small></div></div>
        </section>

        <section className="closing-cta"><p className="eyebrow clay"><span /> Bring the chain together</p><h2>Start with the work<br />you need to do next.</h2><p>Create the right account type and build a profile that reflects your role in the agricultural supply chain.</p><a className="button button-canopy button-large" href="#top">Join Quetta AgriLink <ArrowUpRight size={18} /></a></section>
      </main>
      <footer className="agri-footer"><div className="agri-brand footer-brand"><span className="agri-brand-mark"><Leaf size={19} strokeWidth={2.4} /></span><span>Quetta<br /><em>AgriLink</em></span></div><p>One platform for everything after harvest.</p><span>© 2026 Quetta AgriLink</span></footer>
    </div>
  );
}
