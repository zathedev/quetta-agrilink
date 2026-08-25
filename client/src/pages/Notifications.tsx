/** Orchard Ledger notification register: account-scoped workflow notices are operational records, not promotional alerts. */
import { Bell, CheckCircle2, Inbox, PackageCheck, Snowflake, Truck } from "lucide-react";
import { Link } from "wouter";
import { PreviewLayout } from "@/components/PreviewLayout";
import { getOperations, getSession } from "@/lib/operations";

const iconFor = { offer: PackageCheck, storage: Snowflake, transport: Truck } as const;

export default function Notifications() {
  const session = getSession();
  const operations = getOperations(session?.role);
  return <PreviewLayout><section className="notifications-page"><div className="notifications-head"><div><p className="eyebrow clay"><span /> Workflow register</p><h1>Notifications that<br />move work forward.</h1><p>Offers, storage bookings, and transport requests remain visible with the account that owns the next action.</p></div><div className="notification-count"><Bell size={24} /><strong>{operations.length}</strong><span>current records</span></div></div><section className="notification-ledger"><div className="notification-ledger-head"><span>Operational notice</span><span>Current context</span><span>Status</span></div>{operations.length > 0 ? operations.map((operation) => { const Icon = iconFor[operation.kind]; return <article key={operation.id} className={"notification-row " + operation.kind}><div><span className="notification-band" /><Icon size={19} /><div><strong>{operation.title}</strong><small>{new Date(operation.createdAt).toLocaleString("en-PK", { dateStyle: "medium", timeStyle: "short" })}</small></div></div><span>{operation.detail}</span><b><CheckCircle2 size={13} />{operation.status}</b></article>; }) : <div className="dormant-register"><div className="dormant-register-head"><span>Notice reference</span><span>Account context</span><span>Next status</span></div><div className="dormant-register-row"><span className="notification-band" /><div><Inbox size={22} /><div><strong>No action pending</strong><small>Account register is clear</small></div></div><p>Submit an offer or service request to create the next accountable record.</p><b>Awaiting record</b></div><Link className="button button-canopy" href="/marketplace">Browse marketplace</Link></div>}</section></section></PreviewLayout>;
}
