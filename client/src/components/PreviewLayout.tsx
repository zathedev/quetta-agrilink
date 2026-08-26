/** Market Desk shell: short task navigation makes public routes understandable before a user knows the platform. */
import { useState, type ReactNode } from "react";
import { Leaf, Menu, X, ArrowUpRight } from "lucide-react";
import { Link } from "wouter";

const navigation = [
  ["Find produce", "/marketplace"],
  ["Storage", "/storage"],
  ["Transport", "/transport"],
  ["Market prices", "/market-prices"],
  ["Guides", "/how-it-works"],
] as const;

export function Brand() {
  return <Link href="/" className="agri-brand" aria-label="Quetta AgriLink home"><span className="agri-brand-mark"><Leaf size={21} strokeWidth={2.4} /></span><span>Quetta<br /><em>AgriLink</em></span></Link>;
}

export function PreviewLayout({ children }: { children: ReactNode }) {
  const [open, setOpen] = useState(false);
  return <div className="agri-shell">
    <header className="agri-header">
      <Brand />
      <nav className="agri-nav" aria-label="Primary navigation">{navigation.map(([label, href]) => <Link key={href} href={href}>{label}</Link>)}</nav>
      <div className="agri-nav-actions"><Link className="text-link" href="/sign-in">Sign in</Link><Link className="button button-canopy" href="/sign-up">Create account <ArrowUpRight size={15} /></Link></div>
      <button type="button" className="mobile-menu-toggle" aria-label={open ? "Close navigation" : "Open navigation"} aria-expanded={open} onClick={() => setOpen(!open)}>{open ? <X size={21} /> : <Menu size={21} />}</button>
      {open && <nav className="mobile-nav" aria-label="Mobile navigation">{navigation.map(([label, href]) => <Link key={href} href={href} onClick={() => setOpen(false)}>{label}</Link>)}<Link href="/sign-in" onClick={() => setOpen(false)}>Open my workspace</Link><Link href="/sign-up" onClick={() => setOpen(false)}>Create an account</Link></nav>}
    </header>
    <main>{children}</main>
    <footer className="agri-footer"><Brand /><p>One platform for everything after harvest.</p><span>© 2026 Quetta AgriLink</span></footer>
  </div>;
}

export function PageHero({ eyebrow, title, copy, image }: { eyebrow: string; title: ReactNode; copy: string; image?: "storage" | "transport" | "market" }) {
  return <section className={`page-hero ${image ? `page-hero-${image}` : ""}`}><div><p className="eyebrow clay"><span /> {eyebrow}</p><h1>{title}</h1><p>{copy}</p></div>{image && <div className="page-hero-image" />}</section>;
}
