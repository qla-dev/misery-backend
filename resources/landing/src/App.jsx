import { useEffect, useMemo, useState } from 'react';
import {
  ArrowDown, ArrowLeft, ArrowRight, Check, ChevronDown, Crown, Flame,
  Gamepad2, Globe2, Layers3, Menu, PackageCheck, RotateCcw, ShieldCheck,
  ShoppingBag, Sparkles, Swords, Trophy, Users, X, Zap,
} from 'lucide-react';

const GAME_PRICE = Number(import.meta.env.VITE_GAME_PRICE || 49.9);

const copy = {
  bs: {
    nav: ['Igra', 'Kako se igra', 'Karte', 'Shop'], buy: 'Kupi igru', eyebrow: 'PARTY IGRA · 2–8 IGRAČA',
    heroA: 'KOLIKO LOŠE', heroB: 'JE LOŠE?', hero: 'Složi životne katastrofe na svoju Traku Bijede. Pogriješi — i neko će ti ukrasti kartu ispred nosa.',
    play: 'Isprobaj igru', box: 'Kupi kutiju', scroll: 'Skrolaj prema nesreći',
    labTag: 'TESTIRAJ INSTINKT', labTitle: 'Gdje ova nesreća pripada?', labText: 'Pomjeri kartu na mjesto koje osjećaš. Onda otkrij stvarni Misery Rate.',
    reveal: 'Otkrij ocjenu', again: 'Nova katastrofa', yourGuess: 'Tvoja procjena', actual: 'Stvarna ocjena',
    stepsTag: 'PRAVILA BEZ PREDAVANJA', stepsTitle: 'Četiri koraka do pobjede. Ili izdaje.',
    cardsTag: '100+ RAZLOGA ZA SVAĐU', cardsTitle: 'Svaka karta krije broj. Tvoja ekipa krije loše procjene.',
    shopTag: 'OD STOLA DO TELEFONA', shopTitle: 'Izaberi svoju vrstu bijede.',
    order: 'Naruči sada', preorder: 'Narudžba · plaćanje pouzećem', included: 'U kutiji',
    app: 'MISERY PRO', appText: 'Premium špilovi, Spicy deck i sav budući Pro sadržaj u aplikaciji.', appCta: 'Otvori aplikaciju',
    rules: 'Pravila igre', faq: 'Pitanja koja spašavaju prijateljstva', close: 'Zatvori',
    privacy: 'Privatnost', terms: 'Uslovi korištenja', rights: 'Sva prava zadržana.',
    orderTitle: 'Donesi bijedu na sto.', orderText: 'Rezerviši fizičku igru. Plaćanje je pouzećem; ništa se ne naplaćuje online.',
    name: 'Ime i prezime', email: 'Email', phone: 'Telefon', address: 'Adresa za dostavu', quantity: 'Količina', total: 'Ukupno', submit: 'Potvrdi narudžbu', sending: 'Šaljem…', done: 'Narudžba je zaprimljena.', doneText: 'Javit ćemo se prije slanja radi potvrde adrese i dostave.',
  },
  en: {
    nav: ['Game', 'How to play', 'Cards', 'Shop'], buy: 'Buy the game', eyebrow: 'PARTY GAME · 2–8 PLAYERS',
    heroA: 'HOW BAD', heroB: 'IS BAD?', hero: 'Build a timeline of life disasters on your Misery Lane. Miss the spot — and someone steals the card from under you.',
    play: 'Try the game', box: 'Buy the box', scroll: 'Scroll toward misery',
    labTag: 'TEST YOUR INSTINCT', labTitle: 'Where does this disaster belong?', labText: 'Move the card where your gut tells you. Then reveal the real Misery Rate.',
    reveal: 'Reveal score', again: 'New disaster', yourGuess: 'Your guess', actual: 'Actual score',
    stepsTag: 'RULES WITHOUT A LECTURE', stepsTitle: 'Four steps to victory. Or betrayal.',
    cardsTag: '100+ REASONS TO ARGUE', cardsTitle: 'Every card hides a number. Your group hides terrible judgment.',
    shopTag: 'FROM TABLE TO PHONE', shopTitle: 'Choose your kind of misery.',
    order: 'Order now', preorder: 'Order · cash on delivery', included: 'Inside the box',
    app: 'MISERY PRO', appText: 'Premium packs, the Spicy deck, and every future Pro addition in the app.', appCta: 'Open the app',
    rules: 'Game rules', faq: 'Questions that save friendships', close: 'Close',
    privacy: 'Privacy', terms: 'Terms of use', rights: 'All rights reserved.',
    orderTitle: 'Bring misery to the table.', orderText: 'Reserve the physical game. Payment is cash on delivery; nothing is charged online.',
    name: 'Full name', email: 'Email', phone: 'Phone', address: 'Delivery address', quantity: 'Quantity', total: 'Total', submit: 'Confirm order', sending: 'Sending…', done: 'Order received.', doneText: 'We will contact you before shipping to confirm the address and delivery.',
  },
};

const scenarios = [
  { title: 'MISS THE BUS', sub: 'You watch it pull away as you reach the stop.', score: 3.7, icon: 'bus' },
  { title: 'SPILL COFFEE ON YOUR LAPTOP', sub: 'One tipped cup heads straight for the keyboard.', score: 55.5, icon: 'coffee' },
  { title: 'GET FOOD POISONING', sub: 'Dinner fights back. All night.', score: 33.3, icon: 'food' },
  { title: 'MISS A FLIGHT', sub: 'The gate closes while the plane is still outside.', score: 72.4, icon: 'plane' },
];

const steps = [
  { n: '01', icon: Layers3, title: ['IZVUCI', 'DRAW'], text: ['Dobiješ situaciju, ali ne vidiš njenu ocjenu.', 'See the disaster, but not its score.'] },
  { n: '02', icon: ArrowDown, title: ['POSTAVI', 'PLACE'], text: ['Ubaci je tamo gdje misliš da pripada na skali 0–100.', 'Drop it where you think it belongs from 0–100.'] },
  { n: '03', icon: Swords, title: ['UKRADI', 'STEAL'], text: ['Pogrešan odgovor daje drugima šansu da je otmu.', 'A wrong guess gives everyone else a chance to steal.'] },
  { n: '04', icon: Trophy, title: ['POBIJEDI', 'WIN'], text: ['Prvi sastavi ciljanu traku i preživi ekipu.', 'Build the target lane first and survive the group.'] },
];

const cards = [
  { title: 'MISS THE BUS', score: '03.7', icon: 'bus', tone: 'calm' },
  { title: 'FOOD POISONING', score: '33.3', icon: 'food', tone: 'uneasy' },
  { title: 'LAPTOP + COFFEE', score: '55.5', icon: 'coffee', tone: 'bad' },
  { title: 'MISS A FLIGHT', score: '72.4', icon: 'plane', tone: 'awful' },
];

const faqs = [
  ['Koliko igrača može igrati?', 'Od 2 do 8 igrača. Solo mod u aplikaciji koristi tri života.'],
  ['Koliko traje jedna partija?', 'Najčešće 20–40 minuta, zavisno od broja igrača i ciljane dužine trake.'],
  ['Treba li aplikacija za fizičku igru?', 'Ne. Kutija radi samostalno. Aplikacija dodaje online multiplayer, solo mod i premium špilove.'],
  ['Šta se desi kada pogriješim?', 'Karta ide sljedećem igraču kao prilika za krađu. Ako je pravilno postavi, ostaje u njegovoj traci.'],
];

function Pictogram({ type }) {
  return <div className={`pictogram pictogram-${type}`} aria-hidden="true"><span className="person"/><span className="event"/><span className="ground"/></div>;
}

function Brand() {
  return <a className="brand" href="/" aria-label="Misery Meter home"><span className="bolt">ϟ</span><span>MISERY</span><small>METER</small></a>;
}

function GameCard({ card, hidden = false, className = '' }) {
  return <article className={`game-card ${className}`}>
    <div className="card-top"><span>MISERY</span><span>MM–{String(Math.round((card.score || 0) * 10)).padStart(3, '0')}</span></div>
    <div className="art-disc"><Pictogram type={card.icon}/></div>
    <div className="card-copy"><p>{card.title}</p>{card.sub && <small>{card.sub}</small>}</div>
    <div className={`score-orbit ${hidden ? 'hidden-score' : ''}`}><b>{hidden ? '?' : card.score}</b><span>MISERY RATE</span></div>
  </article>;
}

function LegalPage({ type, lang, goHome }) {
  const isPrivacy = type === 'privacy';
  const title = isPrivacy ? copy[lang].privacy : copy[lang].terms;
  const sections = lang === 'bs' ? (isPrivacy ? [
    ['Podaci koje obrađujemo', 'Kada koristite web, možemo obraditi osnovne tehničke podatke potrebne za sigurnost i stabilnost. Kod narudžbe obrađujemo ime, email, telefon, adresu i količinu isključivo radi potvrde, dostave i korisničke podrške.'],
    ['Plaćanje i kupovina', 'Web trenutno ne prikuplja niti čuva podatke platnih kartica. Narudžbe fizičke igre plaćaju se pouzećem. Kupovine u mobilnoj aplikaciji obrađuju Apple, Google i RevenueCat prema vlastitim pravilima.'],
    ['Kako koristimo podatke', 'Podatke koristimo za izvršenje narudžbe, podršku, sprečavanje zloupotrebe i zakonske obaveze. Lične podatke ne prodajemo trećim stranama.'],
    ['Čuvanje i prava', 'Podatke čuvamo samo koliko je potrebno za navedenu svrhu i zakonske obaveze. Možete tražiti pristup, ispravku ili brisanje kontaktiranjem qla.dev tima.'],
    ['Djeca i vanjski servisi', 'Igra je porodična, ali narudžbe trebaju vršiti punoljetne osobe. Vanjski linkovi i prodavnice aplikacija imaju vlastite politike privatnosti.'],
  ] : [
    ['Prihvatanje uslova', 'Korištenjem stranice prihvatate ove uslove. Fizičku igru mogu naručiti punoljetne osobe ili osobe uz saglasnost roditelja/staratelja.'],
    ['Narudžbe i dostava', 'Slanje obrasca predstavlja zahtjev za narudžbu, ne automatsku naplatu. Cijena i detalji dostave potvrđuju se prije slanja. Plaćanje se vrši pouzećem, osim ako se naknadno dogovori drugačije.'],
    ['Povrat i otkazivanje', 'Narudžbu možete otkazati prije slanja. Prava na reklamaciju, povrat i zamjenu primjenjuju se u skladu sa važećim propisima i stanjem vraćenog proizvoda.'],
    ['Digitalni sadržaj', 'Misery Pro kupovine, obnove i povrati u aplikaciji podliježu pravilima Applea, Googlea, RevenueCata ili drugog procesora plaćanja.'],
    ['Intelektualno vlasništvo', 'Naziv, dizajn, pravila, ilustracije, kod i sadržaj ne smiju se kopirati, preprodavati ili distribuirati bez dozvole. Servis se može mijenjati radi sigurnosti, održavanja ili razvoja.'],
  ]) : (isPrivacy ? [
    ['Data we process', 'We may process basic technical data required for security and stability. For an order, we process your name, email, phone, address, and quantity only for confirmation, delivery, and support.'],
    ['Payments and purchases', 'The website does not collect or store payment-card data. Physical orders are paid cash on delivery. Mobile purchases are handled by Apple, Google, and RevenueCat under their own policies.'],
    ['How data is used', 'Data is used to fulfil orders, provide support, prevent abuse, and meet legal obligations. We do not sell personal data.'],
    ['Retention and rights', 'Data is retained only as needed for its purpose and legal obligations. You may request access, correction, or deletion through the qla.dev team.'],
    ['Children and external services', 'The game is family-friendly, but orders should be placed by adults. External links and app stores have their own privacy policies.'],
  ] : [
    ['Acceptance', 'By using this website, you accept these terms. Physical orders must be placed by an adult or with a parent or guardian’s consent.'],
    ['Orders and delivery', 'Submitting the form is an order request, not an automatic charge. Price and delivery details are confirmed before dispatch. Payment is cash on delivery unless otherwise agreed.'],
    ['Returns and cancellation', 'You may cancel before dispatch. Returns, replacements, and complaints follow applicable consumer law and the condition of the returned product.'],
    ['Digital content', 'Misery Pro purchases, renewals, and refunds are also governed by Apple, Google, RevenueCat, or another payment processor.'],
    ['Intellectual property', 'The name, design, rules, illustrations, code, and content may not be copied, resold, or distributed without permission. The service may change for security, maintenance, or development.'],
  ]);
  return <main className="legal-page"><nav><Brand/><button className="ghost-button" onClick={goHome}><ArrowLeft size={17}/>{copy[lang].close}</button></nav><section><p className="kicker">LEGAL · UPDATED 13 JULY 2026</p><h1>{title}</h1><p className="legal-intro">{lang === 'bs' ? 'Jasno, kratko i bez sitnih slova koja pokušavaju nešto sakriti.' : 'Clear, concise, and without fine print designed to hide things.'}</p><div className="legal-grid">{sections.map(([heading, body], i)=><article key={heading}><b>{String(i+1).padStart(2,'0')}</b><div><h2>{heading}</h2><p>{body}</p></div></article>)}</div></section></main>;
}

function Checkout({ open, onClose, lang }) {
  const t = copy[lang];
  const [quantity, setQuantity] = useState(1);
  const [state, setState] = useState('idle');
  const [error, setError] = useState('');
  useEffect(() => { if (open) { setState('idle'); setError(''); document.body.classList.add('locked'); } else document.body.classList.remove('locked'); return () => document.body.classList.remove('locked'); }, [open]);
  async function submit(event) {
    event.preventDefault(); setState('sending'); setError('');
    const form = new FormData(event.currentTarget);
    try {
      const response = await fetch('/api/store-orders', { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(Object.fromEntries(form)) });
      const body = await response.json();
      if (!response.ok) throw new Error(body.message || 'Order could not be submitted.');
      setState('done');
    } catch (e) { setState('idle'); setError(e.message); }
  }
  if (!open) return null;
  return <div className="checkout-backdrop" role="dialog" aria-modal="true"><button className="checkout-dismiss" aria-label={t.close} onClick={onClose}/><aside className="checkout-panel"><button className="close-round" onClick={onClose}><X/></button>{state === 'done' ? <div className="order-success"><div className="success-mark"><Check/></div><p className="kicker">ORDER CONFIRMED</p><h2>{t.done}</h2><p>{t.doneText}</p><button className="primary-button" onClick={onClose}>{t.close}</button></div> : <><p className="kicker">MISERY METER · FIRST EDITION</p><h2>{t.orderTitle}</h2><p className="checkout-copy">{t.orderText}</p><div className="checkout-product"><div className="mini-box"><span>ϟ</span><b>MISERY<br/>METER</b></div><div><strong>Misery Meter — Box Edition</strong><small>{t.preorder}</small></div><b>{GAME_PRICE.toFixed(2)} KM</b></div><form onSubmit={submit}><div className="form-two"><label>{t.name}<input name="name" required maxLength="120"/></label><label>{t.email}<input name="email" type="email" required maxLength="190"/></label></div><div className="form-two"><label>{t.phone}<input name="phone" required maxLength="40"/></label><label>{t.quantity}<select name="quantity" value={quantity} onChange={e=>setQuantity(Number(e.target.value))}>{[1,2,3,4].map(x=><option key={x}>{x}</option>)}</select></label></div><label>{t.address}<textarea name="address" rows="3" required maxLength="500"/></label><input name="language" type="hidden" value={lang}/>{error && <p className="form-error">{error}</p>}<div className="checkout-total"><span>{t.total}</span><b>{(GAME_PRICE*quantity).toFixed(2)} KM</b></div><button className="primary-button full" disabled={state==='sending'}>{state==='sending'?t.sending:t.submit}<ArrowRight size={18}/></button></form><p className="checkout-fine"><ShieldCheck size={14}/> {lang==='bs'?'Podaci kartice nisu potrebni. Plaćanje pouzećem.':'No card details needed. Cash on delivery.'}</p></>}</aside></div>;
}

export default function App() {
  const initialPath = window.location.pathname.replace(/\/$/, '') || '/';
  const [page, setPage] = useState(initialPath === '/privacy' ? 'privacy' : initialPath === '/terms' ? 'terms' : 'home');
  const [lang, setLang] = useState('bs');
  const [menu, setMenu] = useState(false);
  const [checkout, setCheckout] = useState(false);
  const [scenarioIndex, setScenarioIndex] = useState(1);
  const [guess, setGuess] = useState(46);
  const [revealed, setRevealed] = useState(false);
  const [faqOpen, setFaqOpen] = useState(0);
  const t = copy[lang];
  const scenario = scenarios[scenarioIndex];
  const delta = Math.abs(guess - scenario.score);
  const verdict = delta < 8 ? (lang==='bs'?'ZASTRAŠUJUĆE PRECIZNO':'SCARILY ACCURATE') : delta < 20 ? (lang==='bs'?'BLIZU. DOVOLJNO BLIZU.':'CLOSE. UNCOMFORTABLY CLOSE.') : (lang==='bs'?'TVOM INSTINKTU TREBA POMOĆ':'YOUR INSTINCT NEEDS HELP');
  const links = useMemo(() => [['#game',t.nav[0]],['#how',t.nav[1]],['#cards',t.nav[2]],['#shop',t.nav[3]]], [t]);
  useEffect(() => { const onPop=()=>setPage(location.pathname.startsWith('/privacy')?'privacy':location.pathname.startsWith('/terms')?'terms':'home'); addEventListener('popstate',onPop); return()=>removeEventListener('popstate',onPop); }, []);
  function navigate(next) { const path=next==='home'?'/':`/${next}`; history.pushState({},'',path); setPage(next); scrollTo(0,0); }
  function nextScenario() { setScenarioIndex(x=>(x+1)%scenarios.length); setGuess(Math.round(20+Math.random()*60)); setRevealed(false); }
  if (page !== 'home') return <LegalPage type={page} lang={lang} goHome={()=>navigate('home')}/>;

  return <div className="site-shell">
    <header className="site-header"><Brand/><nav className={menu?'open':''}>{links.map(([href,label])=><a href={href} key={href} onClick={()=>setMenu(false)}>{label}</a>)}</nav><div className="header-actions"><button className="language" onClick={()=>setLang(lang==='bs'?'en':'bs')}><Globe2 size={15}/>{lang==='bs'?'BS':'EN'}</button><button className="nav-buy" onClick={()=>setCheckout(true)}><ShoppingBag size={16}/>{t.buy}</button><button className="menu-button" onClick={()=>setMenu(!menu)}>{menu?<X/>:<Menu/>}</button></div></header>

    <main>
      <section className="hero" id="game"><div className="hero-grid"/><div className="hero-copy"><p className="kicker"><span/> {t.eyebrow}</p><h1><span>{t.heroA}</span><em>{t.heroB}</em></h1><p className="hero-lead">{t.hero}</p><div className="hero-buttons"><a className="primary-button" href="#playground">{t.play}<Zap size={18}/></a><button className="secondary-button" onClick={()=>setCheckout(true)}>{t.box}<ArrowRight size={18}/></button></div><div className="hero-stats"><div><b>100+</b><span>CARDS</span></div><div><b>2–8</b><span>PLAYERS</span></div><div><b>20′</b><span>CHAOS</span></div></div></div><div className="hero-deck"><div className="halo"/><GameCard card={scenarios[2]} className="card-back-left"/><GameCard card={scenarios[0]} className="card-back-right"/><GameCard card={scenario} hidden className="hero-card"/><div className="floating-score"><span>SECRET</span><b>?</b><small>MISERY RATE</small></div></div><a className="scroll-cue" href="#playground"><span>{t.scroll}</span><ArrowDown/></a></section>

      <div className="ticker"><div>{Array.from({length:2}).flatMap((_,i)=>['LOSE YOUR KEYS','✦','MISS A FLIGHT','✦','SPILL COFFEE','✦','STEAL THE CARD','✦'].map((x,j)=><span key={`${i}-${j}`}>{x}</span>))}</div></div>

      <section className="playground" id="playground"><div className="section-heading"><p className="kicker">{t.labTag}</p><h2>{t.labTitle}</h2><p>{t.labText}</p></div><div className="guess-stage"><div className="guess-card-wrap"><GameCard card={scenario} hidden={!revealed} className={revealed?'revealed':''}/></div><div className="meter-panel"><div className="meter-labels"><span>BARELY BAD</span><span>ABSOLUTE MISERY</span></div><div className="meter-track"><div className="meter-fill" style={{width:`${guess}%`}}/><input aria-label="Misery guess" type="range" min="0" max="100" value={guess} onChange={e=>{setGuess(Number(e.target.value));setRevealed(false)}}/><div className="meter-ticks">{[0,20,40,60,80,100].map(x=><i key={x} style={{left:`${x}%`}}><span>{x}</span></i>)}</div></div><div className="guess-readout"><div><span>{t.yourGuess}</span><b>{guess}</b></div>{revealed&&<><div className="score-divider"/><div className="actual"><span>{t.actual}</span><b>{scenario.score}</b></div></>}</div>{revealed&&<p className={`verdict ${delta<8?'great':''}`}>{verdict}</p>}<button className="primary-button full" onClick={()=>revealed?nextScenario():setRevealed(true)}>{revealed?t.again:t.reveal}{revealed?<RotateCcw size={18}/>:<Sparkles size={18}/>}</button></div></div></section>

      <section className="how" id="how"><div className="how-intro"><p className="kicker">{t.stepsTag}</p><h2>{t.stepsTitle}</h2></div><div className="steps">{steps.map((step,i)=>{const Icon=step.icon;return <article key={step.n} style={{'--delay':`${i*.08}s`}}><span className="step-number">{step.n}</span><div className="step-icon"><Icon/></div><h3>{step.title[lang==='bs'?0:1]}</h3><p>{step.text[lang==='bs'?0:1]}</p>{i<3&&<ArrowRight className="step-arrow"/>}</article>})}</div></section>

      <section className="cards-section" id="cards"><div className="section-heading"><p className="kicker">{t.cardsTag}</p><h2>{t.cardsTitle}</h2></div><div className="card-gallery">{cards.map((card,i)=><div className={`gallery-card gc-${i}`} key={card.title}><GameCard card={{...card, score:card.score}}/><div className="gallery-caption"><span>{card.tone}</span><b>{card.score}</b></div></div>)}</div></section>

      <section className="shop" id="shop"><div className="shop-heading"><p className="kicker">{t.shopTag}</p><h2>{t.shopTitle}</h2></div><div className="product-grid"><article className="box-product"><span className="product-badge">FIRST EDITION</span><div className="product-visual"><div className="box-3d"><div className="box-front"><span className="bolt big">ϟ</span><h3>MISERY<br/><em>METER</em></h3><small>HOW BAD IS BAD?</small></div><div className="box-side">100+ CARDS · 2–8 PLAYERS</div></div></div><div className="product-copy"><p className="kicker">PHYSICAL GAME</p><h3>Misery Meter<br/>Box Edition</h3><p>{lang==='bs'?'Kompletna party igra za stol. Bez ekrana, bez dugog objašnjavanja i bez garancije da ćete ostati prijatelji.':'The complete tabletop party game. No screen, no long explanations, and no guarantee you remain friends.'}</p><ul><li><Check/>100+ situation cards</li><li><Check/>2–8 players</li><li><Check/>Quick-start rules</li><li><Check/>Delivery across BiH</li></ul><div className="price-row"><div><span>{t.preorder}</span><b>{GAME_PRICE.toFixed(2)} KM</b></div><button className="primary-button" onClick={()=>setCheckout(true)}>{t.order}<ShoppingBag size={18}/></button></div></div></article><article className="pro-product"><div className="pro-glow"/><div className="pro-icon"><Crown/></div><p className="kicker">IN THE APP</p><h3>{t.app}</h3><p>{t.appText}</p><div className="pro-perks"><span><Flame/>Spicy deck</span><span><Gamepad2/>Online play</span><span><Sparkles/>Future packs</span></div><a className="secondary-button light" href="/simulator"><span>{t.appCta}</span><ArrowRight size={18}/></a><small>{lang==='bs'?'Pretplata se sigurno aktivira unutar mobilne aplikacije.':'Subscription is securely activated inside the mobile app.'}</small></article></div></section>

      <section className="rules-faq"><div className="rules-card"><div><p className="kicker">FULL RULEBOOK</p><h2>{t.rules}</h2><p>{lang==='bs'?'Počni s tri poredane karte. Na potezu postavi novu kartu između dvije ocjene. Tačno? Zadrži je. Pogrešno? Sljedeći igrač može ukrasti. Prvi do cilja pobjeđuje.':'Start with three ordered cards. On your turn, place a new one between two scores. Correct? Keep it. Wrong? The next player can steal. First to the target wins.'}</p></div><div className="rule-lane"><span>03.7</span><i/><span>?</span><i/><span>55.5</span><i/><span>72.4</span></div></div><div className="faq"><p className="kicker">FAQ</p><h2>{t.faq}</h2>{faqs.map(([q,a],i)=><article className={faqOpen===i?'open':''} key={q}><button onClick={()=>setFaqOpen(faqOpen===i?-1:i)}><span>{lang==='bs'?q:[['How many can play?'],['How long is a game?'],['Do I need the app for the box?'],['What happens when I am wrong?']][i]}</span><ChevronDown/></button><div><p>{lang==='bs'?a:[['Two to eight. Solo mode in the app gives you three lives.'],['Usually 20–40 minutes depending on player count and target lane length.'],['No. The box works on its own. The app adds online play, solo mode, and premium decks.'],['The next player gets a chance to steal it by placing it correctly in their lane.']][i]}</p></div></article>)}</div></section>
    </main>

    <footer><div className="footer-top"><Brand/><p>{lang==='bs'?'Party igra o tome ko najbolje razumije koliko život može biti loš.':'The party game about who truly understands how bad life can get.'}</p><button className="primary-button" onClick={()=>setCheckout(true)}>{t.buy}<ArrowRight size={18}/></button></div><div className="footer-bottom"><span>© 2026 MISERY METER · {t.rights}</span><div><button onClick={()=>navigate('privacy')}>{t.privacy}</button><button onClick={()=>navigate('terms')}>{t.terms}</button><a href="https://qla.dev" target="_blank" rel="noreferrer">qla.dev ↗</a></div></div></footer>
    <Checkout open={checkout} onClose={()=>setCheckout(false)} lang={lang}/>
  </div>;
}
