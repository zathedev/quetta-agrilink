/** Orchard Ledger account entry: role determines the work register opened after a successful account session. */
import { type FormEvent, useState } from "react";
import { ArrowRight, CheckCircle2, ShieldCheck } from "lucide-react";
import { useLocation } from "wouter";
import { Brand } from "@/components/PreviewLayout";
import { startSession, type AccountRole } from "@/lib/operations";

const destinations: Record<AccountRole, string> = { farmer: "/farmer", buyer: "/buyer", storage: "/storage-provider", transport: "/transport-provider", admin: "/admin" };
const roleLabels: Record<AccountRole, string> = { farmer: "Farmer", buyer: "Buyer", storage: "Storage provider", transport: "Transport provider", admin: "Administrator" };

export default function SignIn() {
  const [, setLocation] = useLocation();
  const [email, setEmail] = useState("");
  const [role, setRole] = useState<AccountRole>("farmer");
  const [entered, setEntered] = useState(false);
  const submit = (event: FormEvent<HTMLFormElement>) => { event.preventDefault(); startSession(role, email.trim() || "account@quettaagrilink.pk"); setEntered(true); window.setTimeout(() => setLocation(destinations[role]), 650); };
  return <div className="sign-in-page"><header><Brand /><a href="/marketplace">Browse marketplace</a></header><main className="sign-in-layout"><section className="sign-in-intro"><p className="eyebrow"><span /> Account entry</p><h1>Open the work<br />that is <i>yours.</i></h1><p>Use your account role to enter the records, offers, capacity, or fleet activity that require your attention.</p><div className="entry-principles"><div><ShieldCheck size={19} /><span><strong>Role-specific access</strong>Each work area is built around the account’s operational responsibility.</span></div><div><CheckCircle2 size={19} /><span><strong>Clear next steps</strong>Commercial records stay visible with their current status and owner.</span></div></div></section><section className="entry-card"><p className="eyebrow clay"><span /> Sign in</p><h2>Continue to a workspace.</h2><p>Select the account role that matches your work in the agricultural supply chain.</p><form onSubmit={submit}><label>Email address<input type="email" value={email} onChange={(event) => setEmail(event.target.value)} placeholder="name@company.pk" autoComplete="email" /></label><label>Account role<select value={role} onChange={(event) => setRole(event.target.value as AccountRole)}>{(Object.keys(roleLabels) as AccountRole[]).map((value) => <option value={value} key={value}>{roleLabels[value]}</option>)}</select></label><button className="button button-canopy button-large" type="submit">Open {roleLabels[role]} workspace <ArrowRight size={17} /></button></form>{entered && <div className="entry-confirm"><CheckCircle2 size={18} /><span>Workspace selected. Opening your current register.</span></div>}<p className="entry-note">Working accounts require verified credentials before operational actions are accepted.</p></section></main></div>;
}
