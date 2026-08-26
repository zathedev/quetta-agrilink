/** Market Desk account entry: a clear, calm account choice and form reduce cognitive load before opening a workspace. */
import { type FormEvent, useState } from "react";
import { ArrowRight, CheckCircle2, ShieldCheck, Sprout, Warehouse, Truck, ShoppingBasket } from "lucide-react";
import { useLocation } from "wouter";
import { Brand } from "@/components/PreviewLayout";
import { startSession, type AccountRole } from "@/lib/operations";

const destinations: Record<AccountRole, string> = { farmer: "/farmer", buyer: "/buyer", storage: "/storage-provider", transport: "/transport-provider", admin: "/admin" };
const roleLabels: Record<AccountRole, string> = { farmer: "Farmer", buyer: "Buyer", storage: "Storage provider", transport: "Transport provider", admin: "Administrator" };

export default function SignIn() {
  const [, setLocation] = useLocation();
  const [email, setEmail] = useState("");
  const [role, setRole] = useState<AccountRole>("farmer");
  const [entered, setEntered] = useState(false); const roles = [{ value: "farmer" as const, icon: Sprout }, { value: "buyer" as const, icon: ShoppingBasket }, { value: "storage" as const, icon: Warehouse }, { value: "transport" as const, icon: Truck }];
  const submit = (event: FormEvent<HTMLFormElement>) => { event.preventDefault(); startSession(role, email.trim() || "account@quettaagrilink.pk"); setEntered(true); window.setTimeout(() => setLocation(destinations[role]), 500); };
  return <div className="market-desk-auth"><header><Brand /><a href="/marketplace">Browse the marketplace</a></header><main className="desk-auth-layout"><section className="desk-auth-intro"><p className="desk-kicker">Account access</p><h1>Return to the work that needs you.</h1><p>Sign in to see a clear list of offers, capacity, delivery requests, and records connected to your role.</p><div className="desk-auth-points"><div><ShieldCheck size={19} /><span><strong>One clear workspace</strong>Only the records and tasks relevant to your role are shown.</span></div><div><CheckCircle2 size={19} /><span><strong>Know what comes next</strong>Each status points to the next action you can take.</span></div></div></section><section className="desk-auth-card"><p className="desk-kicker">Sign in</p><h2>Open your workspace</h2><p>Enter your account details, then choose the workspace you want to preview.</p><form onSubmit={submit}><label>Email address<input type="email" value={email} onChange={(event) => setEmail(event.target.value)} placeholder="name@company.pk" autoComplete="email" required /></label><label>Password<input type="password" placeholder="Your password" autoComplete="current-password" required /></label><label>Workspace role</label><div className="desk-role-choice">{roles.map(({ value, icon: Icon }) => <button type="button" className={role === value ? "active" : ""} onClick={() => setRole(value)} key={value}><Icon size={15} />{roleLabels[value]}</button>)}</div><button className="button button-canopy button-large" type="submit">Continue as {roleLabels[role]} <ArrowRight size={17} /></button></form>{entered && <div className="entry-confirm"><CheckCircle2 size={17} /><span>Workspace selected. Opening your current view.</span></div>}<div className="desk-auth-footer"><span>New to Quetta AgriLink? <a href="/sign-up">Create an account</a></span><a href="/recover">Need help accessing your account?</a></div><p className="desk-preview-note">Preview mode shows the role journey. The local PHP application remains the production sign-in authority.</p></section></main></div>;
}
