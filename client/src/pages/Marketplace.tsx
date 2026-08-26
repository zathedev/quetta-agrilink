/** Market Desk marketplace: the search route makes filter choices, active criteria, and inspection actions obvious to an ordinary buyer. */
import { useMemo, useState } from "react";
import { ArrowRight, Heart, MapPin, Search, SlidersHorizontal } from "lucide-react";
import { Link } from "wouter";
import { PreviewLayout } from "@/components/PreviewLayout";

const listings = [
  { id: 1, name: "Pishin Red Apples", kind: "Fruit", district: "Pishin", grade: "Grade A", qty: "2,400 kg", price: 185, harvest: "Harvested 2 days ago", image: "apple" },
  { id: 2, name: "Mastung Seedless Grapes", kind: "Fruit", district: "Mastung", grade: "Grade A", qty: "1,150 kg", price: 265, harvest: "Harvested today", image: "grape" },
  { id: 3, name: "Ziarat Dried Apricots", kind: "Fruit", district: "Ziarat", grade: "Premium", qty: "860 kg", price: 310, harvest: "Prepared this week", image: "apricot" },
  { id: 4, name: "Kalat Red Potatoes", kind: "Vegetable", district: "Kalat", grade: "Grade A", qty: "4,800 kg", price: 74, harvest: "Harvested 3 days ago", image: "potato" },
  { id: 5, name: "Quetta Almonds", kind: "Nuts", district: "Quetta", grade: "Premium", qty: "520 kg", price: 940, harvest: "Prepared this week", image: "almond" },
  { id: 6, name: "Kharan Onions", kind: "Vegetable", district: "Kharan", grade: "Grade B", qty: "3,200 kg", price: 68, harvest: "Harvested today", image: "onion" },
];

export default function Marketplace() {
  const [search, setSearch] = useState("");
  const [type, setType] = useState("All produce");
  const [sort, setSort] = useState("Newest");
  const [saved, setSaved] = useState<number[]>([]);
  const visible = useMemo(() => {
    return listings
      .filter((item) => (type === "All produce" || item.kind === type) && `${item.name} ${item.district}`.toLowerCase().includes(search.toLowerCase()))
      .sort((a, b) => sort === "Price: low to high" ? a.price - b.price : sort === "Price: high to low" ? b.price - a.price : a.id - b.id);
  }, [search, type, sort]);

  const toggleSaved = (listingId: number) => {
    setSaved((current) => current.includes(listingId) ? current.filter((id) => id !== listingId) : [...current, listingId]);
  };

  const hasFilters = search.trim() !== "" || type !== "All produce" || sort !== "Newest";
  const reset = () => { setSearch(""); setType("All produce"); setSort("Newest"); };
  return <PreviewLayout><section className="desk-market-hero"><div><p className="desk-kicker">Marketplace</p><h1>Find produce with the terms already visible.</h1><p>Search by crop or district, then compare origin, grade, available quantity, price, and freshness before you inspect a listing.</p></div><aside className="desk-market-origin-signal"><div className="desk-market-origin-art" /><div><span>Origin handling signal</span><strong>Pishin to Quetta market desk</strong><small>Grade, harvest timing, storage readiness, and delivery context follow the crop record.</small></div><ol><li><b>1</b> Search supply</li><li><b>2</b> Compare terms</li><li><b>3</b> Open a listing</li></ol></aside></section>
      <section className="market-page desk-market-page">
        <aside className="filter-rail">
          <div className="filter-title"><SlidersHorizontal size={16} /><strong>Refine supply</strong></div>
          <label>Search produce<input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Apple, grape, district…" /></label>
          <label>Product type<select value={type} onChange={(event) => setType(event.target.value)}><option>All produce</option><option>Fruit</option><option>Vegetable</option><option>Nuts</option></select></label>
          <label>Sort results<select value={sort} onChange={(event) => setSort(event.target.value)}><option>Newest</option><option>Price: low to high</option><option>Price: high to low</option></select></label>
          <p><MapPin size={14} /> Balochistan origins</p>{hasFilters && <button type="button" className="desk-clear-filter" onClick={reset}>Clear filters</button>}
        </aside>
        <div className="market-results">
          <div className="market-results-head"><div><p className="desk-kicker">Available now</p><h2>Compare supply before you contact a grower.</h2>{hasFilters && <div className="desk-filter-chips">{search && <span>“{search}”</span>}{type !== "All produce" && <span>{type}</span>}{sort !== "Newest" && <span>{sort}</span>}</div>}</div><span>{visible.length} of {listings.length} listings</span></div>
          <div className="market-ledger">
            <div className="market-ledger-head"><span>Produce &amp; origin</span><span>Grade</span><span>Available</span><span>Expected price</span><span>Availability</span><span /></div>
            {visible.map((item) => (
              <article className={"market-ledger-row " + item.image} key={item.id}>
                <div className="market-ledger-produce"><span className="crop-band" /><div className="ledger-crop-art" /><div><h3>{item.name}</h3><p>{item.district}, Balochistan</p></div></div>
                <span><b className="grade-tag">{item.grade}</b></span>
                <span>{item.qty}</span>
                <strong>Rs. {item.price}/kg</strong>
                <span className="listing-availability">{item.harvest}</span>
                <div className="ledger-actions"><button type="button" aria-label={"Save " + item.name} className={saved.includes(item.id) ? "is-saved" : ""} onClick={() => toggleSaved(item.id)}><Heart size={16} fill={saved.includes(item.id) ? "currentColor" : "none"} /></button><Link href={`/marketplace/${item.id}`} aria-label={`Inspect ${item.name}`}><ArrowRight size={16} /></Link></div>
              </article>
            ))}
          </div>
          {visible.length === 0 && <div className="empty-market"><Search size={26} /><h3>No matching supply</h3><p>Try clearing a filter or searching a different product name.</p></div>}
        </div>
      </section>
    </PreviewLayout>;
}
