/** Orchard Ledger listing detail: commercial terms are set against a specific product record. */
import { useMemo, useState } from "react";
import { ArrowLeft, CheckCircle2, Heart, MapPin, PackageCheck, Send, ShieldCheck } from "lucide-react";
import { Link, useRoute } from "wouter";
import { PreviewLayout } from "@/components/PreviewLayout";
import { addOperation, getSession, type OperationRecord } from "@/lib/operations";

const records: Record<string, { name: string; district: string; grade: string; quantity: number; price: number; harvest: string; tone: string; note: string }> = {
  "1": { name: "Pishin Red Apples", district: "Pishin", grade: "Grade A", quantity: 2400, price: 185, harvest: "Harvested 2 days ago", tone: "apple", note: "Firm, cleanly sorted red apples with a consistent Grade A pack profile." },
  "2": { name: "Mastung Seedless Grapes", district: "Mastung", grade: "Grade A", quantity: 1150, price: 265, harvest: "Harvested today", tone: "grape", note: "Seedless table grapes prepared for immediate cold-chain handling." },
  "3": { name: "Ziarat Dried Apricots", district: "Ziarat", grade: "Premium", quantity: 860, price: 310, harvest: "Prepared this week", tone: "apricot", note: "Premium dried apricots packed after an inspected preparation cycle." },
};

export default function ListingDetail() {
  const [, params] = useRoute<{ id: string }>("/marketplace/:id");
  const record = records[params?.id ?? "1"] ?? records["1"];
  const [quantity, setQuantity] = useState(Math.min(500, record.quantity));
  const [price, setPrice] = useState(record.price);
  const [saved, setSaved] = useState(false);
  const [submitted, setSubmitted] = useState<OperationRecord | null>(null);
  const [requiresBuyer, setRequiresBuyer] = useState(false);
  const total = useMemo(() => Math.max(0, quantity) * Math.max(0, price), [quantity, price]);
  const submitOffer = () => {
    const session = getSession();
    if (session?.role !== "buyer") { setRequiresBuyer(true); return; }
    setSubmitted(addOperation({ kind: "offer", ownerRole: "buyer", title: `Offer for ${record.name}`, detail: `${quantity.toLocaleString()} kg at Rs. ${price}/kg`, status: "Sent to farmer" }));
  };

  return <PreviewLayout><section className="listing-detail"><Link className="back-link" href="/marketplace"><ArrowLeft size={16} /> Back to marketplace</Link><div className="listing-detail-grid"><div className="listing-hero-record"><div className={`listing-image ${record.tone}`}><span className="listing-crop-band" /><span className="origin-stamp">{record.district} · {record.harvest}</span><button type="button" className={saved ? "is-saved" : ""} onClick={() => setSaved(!saved)} aria-label="Save listing"><Heart size={18} fill={saved ? "currentColor" : "none"} /></button></div><div className="listing-record-copy"><p className="eyebrow clay"><span /> Active marketplace listing</p><h1>{record.name}</h1><p className="listing-origin"><MapPin size={15} /> {record.district}, Balochistan · {record.harvest}</p><p>{record.note}</p><div className="listing-record-facts"><div><small>Grade</small><strong>{record.grade}</strong></div><div><small>Available</small><strong>{record.quantity.toLocaleString()} kg</strong></div><div><small>Expected price</small><strong>Rs. {record.price}/kg</strong></div></div></div></div><aside className="offer-composer"><div className="trade-ticket-head"><p className="eyebrow clay"><span /> Offer ticket</p><span>{record.grade} · {record.district}</span></div><h2>Set terms with context.</h2><p>Set your quantity and price against current supply. The farmer receives the same terms you see here.</p><label>Offer quantity (kg)<input type="number" min="1" max={record.quantity} value={quantity} onChange={(event) => setQuantity(Number(event.target.value))} /></label><label>Offer price (Rs. / kg)<input type="number" min="1" value={price} onChange={(event) => setPrice(Number(event.target.value))} /></label><div className="offer-total"><span>Proposed total <small>{quantity.toLocaleString()} kg × Rs. {price}/kg</small></span><strong>Rs. {total.toLocaleString()}</strong></div>{submitted ? <div className="offer-success"><CheckCircle2 size={20} /><div><strong>Offer sent to farmer.</strong><span>{submitted.detail} is now listed in the buyer workspace.</span><Link href="/buyer">Open buyer workspace</Link></div></div> : <button type="button" className="button button-canopy button-large" onClick={submitOffer}>Submit offer <Send size={16} /></button>}{requiresBuyer && !submitted && <div className="action-gate"><ShieldCheck size={17} /><span>Sign in with a buyer account to send terms.</span><Link href="/sign-in">Sign in</Link></div>}<p className="offer-security"><ShieldCheck size={14} /> Commercial terms are recorded in your buyer register.</p></aside></div><section className="listing-detail-notes"><div><PackageCheck size={25} /><h3>Trade-ready record</h3><p>Product, grade, origin, quantity, price expectation, and the next commercial action stay together.</p></div><div><ShieldCheck size={25} /><h3>Clear offer path</h3><p>Each offer is assigned to the buyer, farmer, listing, and a recorded status.</p></div><div><Heart size={25} /><h3>Save for later</h3><p>Keep supply in your saved register and return when purchasing terms are ready.</p></div></section></section></PreviewLayout>;
}
