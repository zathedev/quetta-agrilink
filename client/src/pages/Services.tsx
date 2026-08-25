/** Orchard Ledger logistics: capacity, refrigeration, and vehicle facts precede every request action. */
import { useState } from "react";
import { ArrowRight, Check, CheckCircle2, Clock3, MapPin, Snowflake, Truck } from "lucide-react";
import { PreviewLayout, PageHero } from "@/components/PreviewLayout";
import { addOperation, getSession, type OperationRecord } from "@/lib/operations";

const storageFacts = [["Available capacity", "76,000 kg"], ["Storage type", "Cold room"], ["Daily price", "Rs. 3.50/kg"], ["Compatible", "Apple, grape, apricot"]];
const transportFacts = [["Available fleet", "3 vehicles"], ["Maximum capacity", "10,000 kg"], ["Temperature control", "Refrigerated"], ["Service coverage", "Quetta & Balochistan"]];

function ServiceRequest({ kind }: { kind: "storage" | "transport" }) {
  const storage = kind === "storage";
  const Icon = storage ? Snowflake : Truck;
  const facts = storage ? storageFacts : transportFacts;
  const [requested, setRequested] = useState<OperationRecord | null>(null);
  const [requiresFarmer, setRequiresFarmer] = useState(false);
  const submitRequest = () => {
    const session = getSession();
    if (session?.role !== "farmer") { setRequiresFarmer(true); return; }
    setRequested(addOperation({ kind, ownerRole: "farmer", title: storage ? "Cold storage request" : "Transport request", detail: storage ? "Pishin Red Apples · 2,400 kg · 12–19 Sep 2026" : "Mastung Seedless Grapes · 1,150 kg · 12 Sep 2026", status: "Requested" }));
  };
  return <PreviewLayout><PageHero eyebrow={storage ? "Cold-storage exchange" : "Transport exchange"} title={storage ? <>Reserve capacity that keeps a harvest <i>market-ready.</i></> : <>Match the crop to a <i>capable vehicle.</i></>} copy={storage ? "Review storage type, compatible produce, available capacity, and daily pricing before you request space." : "Compare fleet capability, capacity, refrigeration and service coverage before you request a pickup."} image={kind} /><section className="service-page"><div className="service-page-main"><div className="service-page-head"><div><p className="eyebrow clay"><span /> Available provider</p><h2>{storage ? "Quetta Valley Cold Store" : "Baloch Route Transport"}</h2><p><MapPin size={15} /> Quetta, Balochistan</p></div><span className="provider-status"><Check size={14} /> Available to review requests</span></div><div className="service-facts">{facts.map(([label, value]) => <div key={label}><small>{label}</small><strong>{value}</strong></div>)}</div><div className="service-detail"><Icon size={28} /><div><h3>{storage ? "Terms visible before the booking." : "Operational detail before dispatch."}</h3><p>{storage ? "A booking remains requested until the facility provider checks dates, capacity, and compatible produce. Each status change appears in the storage register." : "A provider-owned request can move through acceptance, driver assignment, pickup, transit and delivery with each milestone documented."}</p></div></div></div><aside className="request-card"><p className="eyebrow clay"><span /> {storage ? "Booking request" : "Pickup request"}</p><h3>{storage ? "Plan cold storage" : "Plan a produce pickup"}</h3><p>Confirm the crop, quantity, and dates before you send a clear request to the provider.</p><div className="request-fields"><span>Produce <b>{storage ? "Pishin Red Apples" : "Mastung Seedless Grapes"}</b></span><span>{storage ? "Requested capacity" : "Pickup quantity"}<b>{storage ? "2,400 kg" : "1,150 kg"}</b></span><span>{storage ? "Proposed dates" : "Pickup date"}<b>{storage ? "12–19 Sep 2026" : "12 Sep 2026"}</b></span></div>{requested ? <div className="request-success"><CheckCircle2 size={20} /><div><strong>Request sent to provider.</strong><span>{requested.detail} is now shown in the farmer workspace.</span><a href="/farmer">Open farmer workspace</a></div></div> : <button className="button button-canopy button-large" type="button" onClick={submitRequest}>Send request <ArrowRight size={17} /></button>}{requiresFarmer && !requested && <div className="action-gate"><Check size={17} /><span>Sign in with a farmer account to send a request.</span><a href="/sign-in">Sign in</a></div>}<p className="request-note"><Clock3 size={13} /> Capacity and vehicles remain available until provider acceptance.</p></aside></section></PreviewLayout>;
}

export function StoragePage() { return <ServiceRequest kind="storage" />; }
export function TransportPage() { return <ServiceRequest kind="transport" />; }

const serviceRecords = [
  {
    kind: "Cold storage record",
    title: "Capacity held for the harvest window.",
    copy: "Review refrigerated capacity, compatible produce, location, and booking terms before sending a storage request.",
    label: "Storage capacity",
    value: "76,000 kg available",
    meta: [["Location", "Quetta"], ["Status", "Available"], ["Booking lead", "Same-day review"]],
    href: "/storage",
    action: "Review cold storage",
    Icon: Snowflake,
    tone: "storage",
  },
  {
    kind: "Transport record",
    title: "Vehicles matched to accountable movement.",
    copy: "Review fleet capacity, temperature control, service coverage, and pickup terms before requesting a vehicle.",
    label: "Available fleet",
    value: "3 refrigerated vehicles",
    meta: [["Coverage", "Quetta & Balochistan"], ["Status", "Ready for requests"], ["Dispatch", "Provider confirmed"]],
    href: "/transport",
    action: "Review transport",
    Icon: Truck,
    tone: "transport",
  },
] as const;

export function ServicesOverview() {
  return <PreviewLayout><section className="services-ledger-hero"><div><p className="eyebrow clay"><span /> Post-harvest service exchange</p><h1>Storage and transport, recorded before the handover.</h1><p>Choose the next operational record for a ready harvest: capacity, location, timing, provider status, and a clear request action remain visible together.</p></div><aside><span>Quetta, Balochistan</span><strong>Two accountable routes after harvest.</strong><small>Capacity and movement are recorded trade terms, not separate service categories.</small></aside></section><section className="services-ledger"><div className="services-ledger-intro"><div><p className="eyebrow clay"><span /> Available operational records</p><h2>Pick the trade record your harvest needs next.</h2></div><p>Each record keeps availability, accountable next steps, and commercial terms in one practical view.</p></div><div className="service-record-grid">{serviceRecords.map(({ Icon, meta, ...record }) => <article className={`service-record ${record.tone}`} key={record.kind}><div className="service-record-mark"><Icon size={24} /></div><div className="service-record-copy"><p className="eyebrow clay"><span /> {record.kind}</p><h3>{record.title}</h3><p>{record.copy}</p></div><div className="service-record-value"><small>{record.label}</small><strong>{record.value}</strong></div><dl className="service-record-meta">{meta.map(([label, value]) => <div key={label}><dt>{label}</dt><dd>{value}</dd></div>)}</dl><a className="button button-canopy" href={record.href}>{record.action} <ArrowRight size={16} /></a></article>)}</div></section></PreviewLayout>;
}
