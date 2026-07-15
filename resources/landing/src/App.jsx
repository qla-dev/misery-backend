import { useEffect, useMemo, useRef, useState } from 'react';
import lottie from 'lottie-web/build/player/lottie_light';
import mascotAnimation from '../../../../frontend/assets/animations/mascot_lottie.json';
import rulebookSpectrumIllustration from '../../../../frontend/assets/images/rulebook-misery-spectrum.jpg';
import {
  Apple, ArrowDown, ArrowLeft, Check, ChevronDown, Crown, Flame,
  Gamepad2, Globe2, Menu, Play, RotateCcw,
  Sparkles, X, Zap,
} from 'lucide-react';

const APP_STORE_URL = import.meta.env.VITE_APP_STORE_URL || 'https://apps.apple.com/us/search?term=Misery%20Meter';
const PLAY_STORE_URL = import.meta.env.VITE_PLAY_STORE_URL || 'https://play.google.com/store/apps/details?id=misery.qla.dev';

const copy = {
  bs: {
    nav: ['Igra', 'Misery Lane', 'Karte', 'Preuzmi'], buy: 'Preuzmi', eyebrow: 'PARTY IGRA · 2–8 IGRAČA',
    heroA: 'KOLIKO LOŠE', heroB: 'JE LOŠE?', hero: 'Složi životne katastrofe na svoju Traku Bijede. Pogriješi, i neko će ti ukrasti kartu ispred nosa.',
    play: 'Isprobaj igru', box: 'Preuzmi aplikaciju', scroll: 'Skrolaj prema nesreći',
    labTag: 'TESTIRAJ INSTINKT', labTitle: 'Gdje ova nesreća pripada?', labText: 'Pomjeri kartu na mjesto koje osjećaš. Onda otkrij stvarni Misery Rate.',
    reveal: 'Otkrij ocjenu', again: 'Nova katastrofa', yourGuess: 'Tvoja procjena', actual: 'Stvarna ocjena',
    stepsTag: 'UPOZNAJ MISERY LANE', stepsTitle: 'Četiri koraka kroz Misery Lane. Ili do izdaje.',
    cardsTag: '100+ RAZLOGA ZA SVAĐU', cardsTitle: 'Svaka karta krije broj. Tvoja ekipa krije loše procjene.',
    shopTag: 'CIJELA IGRA U DŽEPU', shopTitle: 'Tvoj Misery Lane. Gdje god je ekipa.',
    order: 'Naruči sada', preorder: 'Narudžba · plaćanje pouzećem', included: 'U kutiji',
    app: 'MISERY PRO', appText: 'Premium špilovi, Spicy deck i sav budući Pro sadržaj u aplikaciji.', appCta: 'Preuzmi aplikaciju',
    rules: 'Pravila igre', faq: 'Pitanja koja spašavaju prijateljstva', close: 'Zatvori',
    privacy: 'Privatnost', terms: 'Uslovi korištenja', cookies: 'Kolačići', rights: 'Sva prava zadržana.',
    orderTitle: 'Donesi bijedu na sto.', orderText: 'Rezerviši fizičku igru. Plaćanje je pouzećem; ništa se ne naplaćuje online.',
    name: 'Ime i prezime', email: 'Email', phone: 'Telefon', address: 'Adresa za dostavu', quantity: 'Količina', total: 'Ukupno', submit: 'Potvrdi narudžbu', sending: 'Šaljem…', done: 'Narudžba je zaprimljena.', doneText: 'Javit ćemo se prije slanja radi potvrde adrese i dostave.',
  },
  en: {
    nav: ['Game', 'Misery Lane', 'Cards', 'Download'], buy: 'Download', eyebrow: 'PARTY GAME · 2–8 PLAYERS',
    heroA: 'HOW BAD', heroB: 'IS BAD?', hero: 'Build a timeline of life disasters on your Misery Lane. Miss the spot, and someone steals the card from under you.',
    play: 'Try the game', box: 'Download the app', scroll: 'Scroll toward misery',
    labTag: 'TEST YOUR INSTINCT', labTitle: 'Where does this disaster belong?', labText: 'Move the card where your gut tells you. Then reveal the real Misery Rate.',
    reveal: 'Reveal score', again: 'New disaster', yourGuess: 'Your guess', actual: 'Actual score',
    stepsTag: 'MEET MISERY LANE', stepsTitle: 'Four steps through Misery Lane. Or betrayal.',
    cardsTag: '100+ REASONS TO ARGUE', cardsTitle: 'Every card hides a number. Your group hides terrible judgment.',
    shopTag: 'THE WHOLE GAME IN YOUR POCKET', shopTitle: 'Your Misery Lane. Wherever the group is.',
    order: 'Order now', preorder: 'Order · cash on delivery', included: 'Inside the box',
    app: 'MISERY PRO', appText: 'Premium packs, the Spicy deck, and every future Pro addition in the app.', appCta: 'Download the app',
    rules: 'Game rules', faq: 'Questions that save friendships', close: 'Close',
    privacy: 'Privacy', terms: 'Terms of use', cookies: 'Cookies', rights: 'All rights reserved.',
    orderTitle: 'Bring misery to the table.', orderText: 'Reserve the physical game. Payment is cash on delivery; nothing is charged online.',
    name: 'Full name', email: 'Email', phone: 'Phone', address: 'Delivery address', quantity: 'Quantity', total: 'Total', submit: 'Confirm order', sending: 'Sending…', done: 'Order received.', doneText: 'We will contact you before shipping to confirm the address and delivery.',
  },
};

const scenarios = [
  { title: 'MISS THE BUS', sub: 'You watch it pull away as you reach the stop.', score: 3.7, icon: 'bus' },
  { title: 'STRUCK BY LIGHTNING IN BROAD DAYLIGHT', titleBs: 'POGODI VAS MUNJA USRED BIJELA DANA', sub: 'Clear skies. No shelter. Just a lightning bolt that chose you.', subBs: 'Vedro nebo. Nema zaklona. Samo munja koja je izabrala baš vas.', score: 55.5, icon: 'lightning' },
  { title: 'GET FOOD POISONING', sub: 'Dinner fights back. All night.', score: 33.3, icon: 'food' },
  { title: 'MISS A FLIGHT', sub: 'The gate closes while the plane is still outside.', score: 72.4, icon: 'plane' },
];

const tickerCopy = {
  bs: ['OCIJENI BIJEDU', 'POSTAVI KARTU', 'IZGRADI TRAKU', 'UKRADI KARTU', 'PRVI DO CILJA POBJEĐUJE', 'UŽASNI DOGAĐAJI. ODLIČNA IGRA.'],
  en: ['RATE THE MISERY', 'PLACE THE CARD', 'BUILD YOUR LANE', 'STEAL THE CARD', 'FIRST TO THE TARGET WINS', 'TERRIBLE EVENTS. GREAT GAME.'],
};

const cards = [
  { title: 'SPILL COFFEE ON YOUR LAPTOP', score: '55.5', icon: 'coffee', tone: 'bad' },
  { title: 'FOOD POISONING', score: '33.3', icon: 'food', tone: 'uneasy' },
  { title: 'LAPTOP + COFFEE', score: '55.5', icon: 'coffee', tone: 'bad' },
  { title: 'MISS A FLIGHT', score: '72.4', icon: 'plane', tone: 'awful' },
];

const faqs = [
  ['Koliko igrača može igrati?', 'Od 2 do 8 igrača u online multiplayer sobi.'],
  ['Koliko traje jedna partija?', 'Najčešće 20–40 minuta, zavisno od broja igrača i ciljane dužine trake.'],
  ['Gdje mogu igrati?', 'Misery Meter je dostupan kao aplikacija za iOS i Android, sa online multiplayerom i premium špilovima.'],
  ['Šta se desi kada pogriješim?', 'Karta ide sljedećem igraču kao prilika za krađu. Ako je pravilno postavi, ostaje u njegovoj traci.'],
];

function Pictogram({ type }) {
  return <div className={`pictogram pictogram-${type}`} aria-hidden="true"><span className="person"/><span className="event"/><span className="ground"/></div>;
}
function MascotLetter({ className = 'brand-mascot' }) {
  const container = useRef(null);
  useEffect(() => {
    if (!container.current) return undefined;
    const animation = lottie.loadAnimation({
      animationData: mascotAnimation,
      autoplay: true,
      container: container.current,
      loop: true,
      renderer: 'svg',
    });
    return () => animation.destroy();
  }, []);
  return <span className={className} ref={container} aria-hidden="true"/>;
}

function Brand() {
  return <a className="brand native-brand" href="/" aria-label="Misery Meter home"><span className="brand-main"><span>M</span><MascotLetter/><span>SERY</span></span><span className="brand-meter">METER</span></a>;
}

function detectStorePlatform() {
  if (typeof navigator === 'undefined' || typeof window === 'undefined') return null;
  const userAgent = navigator.userAgent || '';
  const platform = navigator.platform || '';
  const hasTouch = navigator.maxTouchPoints > 0 || window.matchMedia('(pointer: coarse)').matches;
  const isIpadOS = platform === 'MacIntel' && navigator.maxTouchPoints > 1;
  const isSafari = /^((?!chrome|android|crios|fxios|edg).)*safari/i.test(userAgent);
  if (/Android/i.test(userAgent)) return 'android';
  if (isSafari && (hasTouch || /iPhone|iPad|iPod/i.test(userAgent) || isIpadOS)) return 'ios';
  if (/iPhone|iPad|iPod/i.test(userAgent) || isIpadOS) return 'ios';
  return null;
}

function StoreButton({ platform, compact = false, lang = 'en', light = false }) {
  const ios = platform === 'ios';
  const Icon = ios ? Apple : Play;
  const label = ios ? 'iOS' : 'Android';
  return <a className={`store-button ${compact ? 'compact' : ''} ${light ? 'light' : ''}`} href={ios ? APP_STORE_URL : PLAY_STORE_URL} target="_blank" rel="noreferrer"><Icon/><span><small>{lang === 'bs' ? 'PREUZMI ZA' : 'DOWNLOAD FOR'}</small><b>{label}</b></span></a>;
}

function StoreButtons({ compact = false, lang = 'en', light = false, platformAware = false }) {
  const [platform, setPlatform] = useState(null);
  useEffect(() => setPlatform(detectStorePlatform()), []);
  const visible = platformAware && platform ? [platform] : ['ios', 'android'];
  return <div className={`store-buttons ${compact ? 'compact' : ''}`}>{visible.map(item => <StoreButton compact={compact} key={item} lang={lang} light={light} platform={item}/>)}</div>;
}

function Ticker({ lang }) {
  const items = tickerCopy[lang] || tickerCopy.en;

  return <div className="ticker" aria-hidden="true">
    <div className="ticker-track">
      {[0, 1].map(group => <div className="ticker-group" key={group}>
        {items.map(item => <span key={`${group}-${item}`}>{item} ✦</span>)}
      </div>)}
    </div>
  </div>;
}

function LegacyGameCard({ card, hidden = false, className = '', animatedArtwork = false }) {
  return <article className={`game-card ${className}`}>
    <div className="card-top"><span>MISERY</span><span>MM–{String(Math.round((card.score || 0) * 10)).padStart(3, '0')}</span></div>
    <div className="art-disc">{animatedArtwork || className.includes('hero-card') || card.icon === 'lightning' ? <MascotLetter className="card-mascot"/> : <Pictogram type={card.icon}/>}</div>
    <div className="card-copy"><p>{card.title}</p>{card.sub && <small>{card.sub}</small>}</div>
    <div className={`score-orbit ${hidden ? 'hidden-score' : ''}`}><b>{hidden ? '?.??' : Number(card.score).toFixed(2)}</b><span>MISERY RATE</span></div>
  </article>;
}

function GameCard({ card, hidden = false, className = '', logoArtwork = false }) {
  return <article className={`game-card main-game-card ${className}`}>
    <div className="main-card-content">
      <p>{card.title}</p>
      {card.sub && <small>{card.sub}</small>}
      <div className={`main-card-artwork ${logoArtwork ? 'logo-artwork' : ''}`}>
        {logoArtwork
          ? <MascotLetter className="card-mascot"/>
          : card.image
            ? <img alt="" src={card.image}/>
            : <Pictogram type={card.icon}/>
        }
      </div>
    </div>
    <div className="main-card-score">
      <span>MISERY RATE</span>
      <div><b>{hidden ? '?.??' : Number(card.score).toFixed(2)}</b></div>
    </div>
  </article>;
}

function GameCardBack({ className = '' }) {
  return <article className={`game-card main-game-card game-card-back ${className}`} aria-label="Face-down Misery Meter card">
    <div className="card-back-logo">
      <div className="card-back-logo-main"><span>M</span><MascotLetter className="card-back-mascot"/><span>SERY</span></div>
      <strong>METER</strong>
    </div>
    <small>TAP CARD TO REVEAL</small>
  </article>;
}

function RuleBand({ number, children }) {
  return <div className="rulebook-band"><b>{number}</b><h3>{children}</h3></div>;
}

function RuleLane({ cards, hidden = false, lang }) {
  return <div className="rulebook-lane-cards">{cards.map((rawCard, index)=>{const card={...rawCard,title:lang==='bs'&&rawCard.titleBs?rawCard.titleBs:rawCard.title,sub:lang==='bs'&&rawCard.subBs?rawCard.subBs:rawCard.sub};return <div className={hidden&&index===1?'mystery':''} key={card.id||`${card.title}-${index}`}><GameCard card={card} className="rulebook-lane-card" hidden={hidden&&index===1}/>{index<cards.length-1&&<i>›</i>}</div>})}</div>;
}

function WebRulebook({ cards, lang }) {
  const bs = lang === 'bs';
  return <section className="how rulebook-section" id="how">
    <div className="rulebook-intro"><div className="rulebook-logo"><Brand/></div><p className="kicker">{bs?'KAKO SE IGRA':'HOW TO PLAY'}</p><p>{bs?'Originalni izgled knjižice pravila, prilagođen igri u aplikaciji.':'The original rulebook treatment, rebuilt for the game in your pocket.'}</p></div>
    <div className="rulebook-paper">
      <article className="rulebook-block rulebook-wide">
        <RuleBand number="1">{bs?'ŠTA POKUŠAVAM POSTIĆI?':'WHAT AM I TRYING TO ACCOMPLISH?'}</RuleBand>
        <p>{bs?'Budi najbolji u rangiranju nesretnih životnih događaja, od ':'Be the best at ranking miserable real-life events from '}<em>{bs?'jedva loših':'barely bad'}</em>{bs?' do ':' to '}<em>{bs?'potpune bijede':'absolute misery'}</em>{bs?', i izgradi svoj Misery Lane prije svih ostalih.':', and build your Misery Lane before everyone else.'}</p>
      </article>

      <article className="rulebook-block rulebook-wide">
        <RuleBand number="2">{bs?'ŠTA JE U IGRI?':"WHAT'S IN THE GAME?"}</RuleBand>
        <p><b>100+ {bs?'KARTICA NESRETNIH DOGAĐAJA':'MISERABLE EVENT CARDS'}</b>{bs?'; svaka prikazuje događaj koji se dogodio ili bi se vrlo lako mogao dogoditi.':'; each one shows something that happened or very easily could happen.'}</p>
        <div className="rulebook-callout"><div className="rulebook-generated-art"><img alt="" src={rulebookSpectrumIllustration}/></div><p>{bs?'Kao što ćeš vidjeti, neki događaji su prilično sitni ':"As you'll see, some events are pretty minor "}<em>{bs?'(poput propuštenog autobusa)':'(like missing the bus)'}</em>{bs?', dok su drugi mnogo gori ':', while others are far more miserable '}<em>{bs?'(poput udara munje)':'(like being struck by lightning)'}</em>{bs?'. Svaka kartica ima svoje mjesto na ':'. Every card has a fixed place on the '}<b>Misery Rate</b>{bs?' skali.':'.'}</p></div>
      </article>

      <article className="rulebook-block rulebook-wide">
        <RuleBand number="3">THE MISERY RATE</RuleBand>
        <p><b>Misery Rate</b>{bs?' je sistem rangiranja koji ide od ':' is the game’s ranking system that runs from '}<b>{bs?'0 do 100':'0 to 100'}</b>{bs?'. Niska ocjena znači neugodnu sitnicu, a visoka ocjena znači bijedu koja mijenja život.':'. A low score means an annoying inconvenience; a high score means life-changing misery.'}</p>
        <div className="rulebook-scale"><h4>{bs?'MISERY RATE OBJAŠNJEN':'THE MISERY RATE DEMYSTIFIED'}</h4><div className="rulebook-scale-track">{[0,20,40,60,80,100].map(x=><span key={x}>{x}</span>)}</div><div className="rulebook-pointers">{cards.map(card=><div className="rulebook-pointer" key={card.id||card.title}><b>{bs&&card.titleBs?card.titleBs:card.title}</b></div>)}</div><small><b>{bs?'JEDVA LOŠE':'BARELY BAD'}</b><b>{bs?'POTPUNA BIJEDA':'ABSOLUTE MISERY'}</b></small></div>
        <p>{bs?'Ne moraš pogoditi ':'You never need the '}<b>{bs?'TAČAN BROJ':'EXACT NUMBER'}</b>{bs?'. Trebaš samo odlučiti gdje ':'. You only need to decide where the '}<b>{bs?'SKRIVENA OCJENA':'HIDDEN SCORE'}</b>{bs?' pripada među karticama koje su već u tvojoj stazi.':' belongs among the cards already in your lane.'}</p>
      </article>

      <article className="rulebook-block rulebook-anatomy">
        <RuleBand number="4">{bs?'ANATOMIJA KARTICE':'CARD ANATOMY'}</RuleBand>
        <p>{bs?'Kartice u Misery Meteru nisu komplikovane.':'Misery Meter cards aren’t complicated.'}</p>
        <div className="anatomy-demo"><div className="anatomy-labels"><b>{bs?'NESRETNI DOGAĐAJ':'MISERABLE EVENT'}</b><b>{bs?'ILUSTRACIJA':'ILLUSTRATION'}</b><b>MISERY RATE</b></div><GameCard card={{...cards[2],title:bs&&cards[2].titleBs?cards[2].titleBs:cards[2].title,sub:bs&&cards[2].subBs?cards[2].subBs:cards[2].sub}} className="rulebook-anatomy-card"/></div>
      </article>

      <article className="rulebook-block">
        <RuleBand number="5">{bs?'NEKA BIJEDA POČNE':"LET'S GET MISERABLE"}</RuleBand>
        <p>{bs?'Svaki igrač počinje s tri kartice koje su već poredane od najniže do najviše Misery Rate ocjene. One čine početak tvog Misery Lanea.':'Each player starts with three cards already arranged from the lowest to the highest Misery Rate. Those cards form the beginning of your Misery Lane.'}</p>
        <div className="rulebook-lane"><strong>MISERY LANE</strong><RuleLane cards={cards} hidden lang={lang}/><p>{bs?'Nova kartica prvo ulazi u stazu sa znakom ?. Izaberi mjesto između poznatih ocjena gdje misliš da pripada. Ne pogađaš broj, nego njen pravilan položaj.':'The new card first enters the lane with a ?. Choose the place between the known scores where you think it belongs. You are not guessing the number, only its correct position.'}</p><RuleLane cards={cards} lang={lang}/><p>{bs?'Game Master zatim otkriva stvarnu Misery Rate ocjenu i pokazuje da li je položaj tačan.':'The Game Master then reveals the real Misery Rate and shows whether the position is correct.'}</p></div>
      </article>

      <article className="rulebook-block">
        <RuleBand number="6">GAME MASTER</RuleBand>
        <p>{bs?'Aplikacija je vaš Game Master. Vodi redoslijed poteza, prikazuje čiji je potez, zaključava nedostupne akcije, otkriva ocjene i kroz posebne overlay poruke objašnjava svaki rezultat, krađu i pobjedu.':'The app is your Game Master. It manages turn order, shows whose turn it is, locks unavailable actions, reveals scores, and uses dedicated overlays to explain every result, steal, and victory.'}</p>
        <div className="rulebook-timeout"><b>{bs?'NEAKTIVNOST · 60 SEKUNDI':'INACTIVITY · 60 SECONDS'}</b><p>{bs?'Aktivni igrač dobija upozorenja nakon 15, 30 i 45 sekundi. Ako ne reaguje u roku od 60 sekundi, izbacuje se. Igra se nastavlja ako je otišao obični igrač; ako host napusti igru ili postane neaktivan, cijela igra se završava.':'The active player is warned after 15, 30, and 45 seconds. If they do not act within 60 seconds, they are removed. The game continues when a regular player leaves; if the host leaves or becomes inactive, the entire game ends.'}</p></div>
      </article>

      <article className="rulebook-block">
        <RuleBand number="7">{bs?'TAČNO ILI POGREŠNO':'RIGHT OR WRONG'}</RuleBand>
        <p>{bs?'Nakon otkrivanja ocjene, Game Master prikazuje rezultat poteza preko cijelog ekrana:':'After revealing the score, the Game Master displays the result in a full overlay:'}</p>
        <div className="rulebook-outcomes"><div className="correct"><i/><section><b>{bs?'TAČNO':'RIGHT'}</b><p>{bs?'Zeleni overlay znači da je položaj tačan. Kartica ostaje u tvojoj stazi.':'A green overlay means the position is correct. The card stays in your lane.'}</p></section></div><div className="wrong"><i/><section><b>{bs?'POGREŠNO':'WRONG'}</b><p>{bs?'Crveni overlay znači da je procjena bila previsoka ili preniska. Kartica ne ulazi u tvoju stazu.':'A red overlay means your guess was too high or too low. The card does not enter your lane.'}</p></section></div></div>
      </article>

      <article className="rulebook-block">
        <RuleBand number="8">{bs?'KRAĐA':'STEALING'}</RuleBand>
        <div className="rulebook-outcomes"><div className="steal"><i/><section><b>{bs?'PRILIKA ZA KRAĐU':'STEAL CHANCE'}</b><p>{bs?'Nakon pogrešnog poteza, ostali igrači redom dobijaju posebni overlay sa izborom da prihvate ili preskoče krađu. Ko prihvati, pokušava pravilno postaviti istu karticu u svoju stazu. Uspješna krađa dodaje karticu kradljivcu; ako svi preskoče ili pogriješe, kartica se odbacuje.':'After a wrong move, the other players receive a dedicated overlay in order and may accept or pass the steal. Whoever accepts tries to place the same card correctly in their own lane. A successful steal adds it to the stealer’s lane; if everyone passes or misses, the card is discarded.'}</p></section></div></div>
      </article>

      <article className="rulebook-block rulebook-win">
        <RuleBand number="9">{bs?'KAKO POBIJEDITI':'HOW TO WIN'}</RuleBand>
        <div><b>{bs?'PRVI IGRAČ KOJI DODA CILJANI BROJ KARTICA U SVOJU STAZU POBJEĐUJE.':'THE FIRST PLAYER TO ADD THE TARGET NUMBER OF CARDS TO THEIR LANE WINS.'}</b><p>{bs?'Ciljani broj kartica bira se prije početka igre.':'The target number of cards is selected before the game starts.'}</p></div>
      </article>
    </div>
  </section>;
}

// Retained as a backup for the previous website-specific card treatment.
void LegacyGameCard;

function LegalPage({ type, lang, goHome }) {
  const isPrivacy = type === 'privacy';
  const isCookies = type === 'cookies';
  const title = isPrivacy ? copy[lang].privacy : isCookies ? copy[lang].cookies : copy[lang].terms;
  const legalSections = lang === 'bs' ? (isPrivacy ? [
    ['Podaci koje obrađujemo', 'Kada koristite web ili aplikaciju, možemo obraditi osnovne tehničke podatke potrebne za sigurnost, stabilnost, multiplayer i korisničku podršku.'],
    ['Plaćanje i kupovina', 'Web ne prikuplja niti čuva podatke platnih kartica. Kupovine u mobilnoj aplikaciji obrađuju Apple, Google i RevenueCat prema vlastitim pravilima.'],
    ['Kako koristimo podatke', 'Podatke koristimo za izvršenje narudžbe, podršku, sprečavanje zloupotrebe i zakonske obaveze. Lične podatke ne prodajemo trećim stranama.'],
    ['Čuvanje i prava', 'Podatke čuvamo samo koliko je potrebno za navedenu svrhu i zakonske obaveze. Možete tražiti pristup, ispravku ili brisanje kontaktiranjem qla.dev tima.'],
    ['Djeca i vanjski servisi', 'Kupovine trebaju vršiti punoljetne osobe. Vanjski linkovi i prodavnice aplikacija imaju vlastite politike privatnosti.'],
  ] : [
    ['Prihvatanje uslova', 'Korištenjem stranice ili aplikacije prihvatate ove uslove. Kupovine trebaju vršiti punoljetne osobe ili osobe uz saglasnost roditelja/staratelja.'],
    ['Nalozi i multiplayer', 'Odgovorni ste za tačnost podataka naloga i fer korištenje multiplayer funkcija. Zabranjeno je ometati servis, zaobilaziti zaštitu ili pokušavati neovlašten pristup.'],
    ['Pretplate i otkazivanje', 'Misery Pro pretplatom upravljate u prodavnici u kojoj je kupljena. Otkazivanje zaustavlja narednu obnovu, dok pristup traje do kraja plaćenog perioda.'],
    ['Digitalni sadržaj', 'Misery Pro kupovine, obnove i povrati u aplikaciji podliježu pravilima Applea, Googlea, RevenueCata ili drugog procesora plaćanja.'],
    ['Intelektualno vlasništvo', 'Naziv, dizajn, pravila, ilustracije, kod i sadržaj ne smiju se kopirati, preprodavati ili distribuirati bez dozvole. Servis se može mijenjati radi sigurnosti, održavanja ili razvoja.'],
  ]) : (isPrivacy ? [
    ['Data we process', 'We may process basic technical data required for security, stability, multiplayer, and customer support.'],
    ['Payments and purchases', 'The website does not collect or store payment-card data. Mobile purchases are handled by Apple, Google, and RevenueCat under their own policies.'],
    ['How data is used', 'Data is used to fulfil orders, provide support, prevent abuse, and meet legal obligations. We do not sell personal data.'],
    ['Retention and rights', 'Data is retained only as needed for its purpose and legal obligations. You may request access, correction, or deletion through the qla.dev team.'],
    ['Children and external services', 'Purchases should be made by adults. External links and app stores have their own privacy policies.'],
  ] : [
    ['Acceptance', 'By using this website or app, you accept these terms. Purchases must be made by an adult or with a parent or guardian’s consent.'],
    ['Accounts and multiplayer', 'You are responsible for accurate account data and fair use of multiplayer. Disrupting the service, bypassing safeguards, or attempting unauthorized access is prohibited.'],
    ['Subscriptions and cancellation', 'Misery Pro is managed through the store where it was purchased. Cancellation stops the next renewal while access remains until the end of the paid period.'],
    ['Digital content', 'Misery Pro purchases, renewals, and refunds are also governed by Apple, Google, RevenueCat, or another payment processor.'],
    ['Intellectual property', 'The name, design, rules, illustrations, code, and content may not be copied, resold, or distributed without permission. The service may change for security, maintenance, or development.'],
  ]);
  const cookieSections = lang === 'bs' ? [
    ['Šta su kolačići', 'Kolačići su male tekstualne datoteke koje web stranica može sačuvati u pregledniku radi osnovnog rada, sigurnosti i pamćenja izbora.'],
    ['Neophodni kolačići', 'Koristimo samo tehnički neophodne podatke za sigurnost stranice, usmjeravanje i stabilan rad. Bez njih pojedine funkcije ne bi radile ispravno.'],
    ['Analitika i oglašavanje', 'Trenutno ne koristimo reklamne kolačiće niti prodajemo podatke za oglašavanje. Ako uvedemo analitiku koja zahtijeva saglasnost, ova politika i izbori korisnika bit će ažurirani prije aktivacije.'],
    ['Mobilna aplikacija', 'Native iOS i Android aplikacija ne koristi web kolačiće za igranje. Apple, Google, RevenueCat i vanjski servisi mogu obrađivati vlastite tehničke identifikatore prema svojim pravilima.'],
    ['Kontrola i kontakt', 'Kolačiće možete obrisati ili blokirati kroz postavke preglednika. Za pitanja o ovoj politici kontaktirajte qla.dev tim.'],
  ] : [
    ['What cookies are', 'Cookies are small text files a website may store in your browser for essential operation, security, and remembering choices.'],
    ['Essential cookies', 'We use only technically necessary data for site security, routing, and stable operation. Some functions would not work correctly without it.'],
    ['Analytics and advertising', 'We currently use no advertising cookies and do not sell data for advertising. If consent-based analytics are introduced, this policy and user choices will be updated before activation.'],
    ['Mobile application', 'The native iOS and Android app does not use web cookies for gameplay. Apple, Google, RevenueCat, and external services may process their own technical identifiers under their policies.'],
    ['Control and contact', 'You can delete or block cookies in your browser settings. Contact the qla.dev team with questions about this policy.'],
  ];
  const sections = isCookies ? cookieSections : legalSections;
  return <main className="legal-page"><nav><Brand/><button className="ghost-button" onClick={goHome}><ArrowLeft size={17}/>{copy[lang].close}</button></nav><section><p className="kicker">LEGAL · UPDATED 13 JULY 2026</p><h1>{title}</h1><p className="legal-intro">{lang === 'bs' ? 'Jasno, kratko i bez sitnih slova koja pokušavaju nešto sakriti.' : 'Clear, concise, and without fine print designed to hide things.'}</p><div className="legal-grid">{sections.map(([heading, body], i)=><article key={heading}><b>{String(i+1).padStart(2,'0')}</b><div><h2>{heading}</h2><p>{body}</p></div></article>)}</div></section></main>;
}

export default function App() {
  const initialPath = window.location.pathname.replace(/\/$/, '') || '/';
  const [page, setPage] = useState(initialPath === '/privacy' ? 'privacy' : initialPath === '/terms' ? 'terms' : initialPath === '/cookies' ? 'cookies' : 'home');
  const [lang, setLang] = useState('en');
  const [menu, setMenu] = useState(false);
  const [scenarioIndex, setScenarioIndex] = useState(1);
  const [guess, setGuess] = useState(46);
  const [revealed, setRevealed] = useState(false);
  const [faqOpen, setFaqOpen] = useState(0);
  const [apiCards, setApiCards] = useState([]);
  const t = copy[lang];
  const realCards = useMemo(() => apiCards.map(card => ({
    id: card.id,
    image: card.image,
    score: Number(card.score),
    sub: card.subtitle || '',
    subBs: card.subtitle_bs || card.subtitle || '',
    title: card.title,
    titleBs: card.title_bs || card.title,
  })).filter(card => card.image && Number.isFinite(card.score)), [apiCards]);
  const scenarioPool = realCards.length ? realCards : scenarios;
  const rawScenario = scenarioPool[scenarioIndex % scenarioPool.length];
  const scenario = { ...rawScenario, title: lang === 'bs' && rawScenario.titleBs ? rawScenario.titleBs : rawScenario.title, sub: lang === 'bs' && rawScenario.subBs ? rawScenario.subBs : rawScenario.sub };
  const galleryCards = realCards.length >= 4
    ? [0, .33, .66, 1].map(position => realCards[Math.min(realCards.length - 1, Math.round((realCards.length - 1) * position))])
    : cards;
  const delta = Math.abs(guess - scenario.score);
  const verdict = delta < 8 ? (lang==='bs'?'ZASTRAŠUJUĆE PRECIZNO':'SCARILY ACCURATE') : delta < 20 ? (lang==='bs'?'BLIZU. DOVOLJNO BLIZU.':'CLOSE. UNCOMFORTABLY CLOSE.') : (lang==='bs'?'TVOM INSTINKTU TREBA POMOĆ':'YOUR INSTINCT NEEDS HELP');
  const links = useMemo(() => [['#game',t.nav[0]],['#how',t.nav[1]],['#cards',t.nav[2]],['#shop',t.nav[3]]], [t]);
  useEffect(() => {
    const titles = { home: 'Misery Meter: The party game of terrible decisions', privacy: 'Privacy Policy | Misery Meter', terms: 'Terms of Use | Misery Meter', cookies: 'Cookie Policy | Misery Meter' };
    document.title = titles[page];
    document.documentElement.lang = lang;
  }, [lang, page]);
  useEffect(() => {
    let active = true;
    fetch('/api/cards', { headers: { Accept: 'application/json' } })
      .then(response => {
        if (!response.ok) throw new Error(`Cards request failed (${response.status})`);
        return response.json();
      })
      .then(payload => {
        if (active) setApiCards(Array.isArray(payload?.data) ? payload.data : Array.isArray(payload) ? payload : []);
      })
      .catch(error => console.warn('[Landing] Real cards unavailable; using bundled fallback cards.', error));
    return () => { active = false; };
  }, []);
  useEffect(() => { const onPop=()=>setPage(location.pathname.startsWith('/privacy')?'privacy':location.pathname.startsWith('/terms')?'terms':location.pathname.startsWith('/cookies')?'cookies':'home'); addEventListener('popstate',onPop); return()=>removeEventListener('popstate',onPop); }, []);
  function navigate(next) { const path=next==='home'?'/':`/${next}`; history.pushState({},'',path); setPage(next); scrollTo(0,0); }
  function nextScenario() { setScenarioIndex(x=>(x+1)%scenarioPool.length); setGuess(Math.round(20+Math.random()*60)); setRevealed(false); }
  if (page !== 'home') return <LegalPage type={page} lang={lang} goHome={()=>navigate('home')}/>;

  return <div className="site-shell">
    <header className="site-header"><Brand/><nav className={menu?'open':''}>{links.map(([href,label])=><a href={href} key={href} onClick={()=>setMenu(false)}>{label}</a>)}</nav><div className="header-actions"><button className="language" onClick={()=>setLang(lang==='bs'?'en':'bs')}><Globe2 size={15}/>{lang==='bs'?'BS':'EN'}</button><StoreButtons compact lang={lang} platformAware/><button className="menu-button" onClick={()=>setMenu(!menu)}>{menu?<X/>:<Menu/>}</button></div></header>

    <main>
      <section className="hero" id="game"><div className="hero-grid"/><div className="hero-copy"><p className="kicker"><span/> {t.eyebrow}</p><h1><span>{t.heroA}</span><em>{t.heroB}</em></h1><p className="hero-lead">{t.hero}</p><div className="hero-buttons"><a className="primary-button" href="#playground">{t.play}<Zap size={18}/></a><StoreButtons lang={lang} platformAware/></div><div className="hero-stats"><div><b>100+</b><span>CARDS</span></div><div><b>2–8</b><span>PLAYERS</span></div><div><b>20′</b><span>CHAOS</span></div></div></div><div className="hero-deck"><div className="halo"/><GameCard card={scenarioPool[2 % scenarioPool.length]} className="card-back-left"/><GameCard card={scenarioPool[0]} className="card-back-right"/><GameCardBack className="hero-card"/><div className="floating-score"><span>SECRET</span><b>?.??</b><small>MISERY RATE</small></div></div><a className="scroll-cue" href="#playground"><span>{t.scroll}</span><ArrowDown/></a></section>

      <Ticker lang={lang}/>

      <section className="playground" id="playground"><div className="section-heading"><p className="kicker">{t.labTag}</p><h2>{t.labTitle}</h2><p>{t.labText}</p></div><div className="guess-stage"><div className="guess-card-wrap"><GameCard card={scenario} hidden={!revealed} className={revealed?'revealed':''}/></div><div className="meter-panel"><div className="meter-labels"><span>BARELY BAD</span><span>ABSOLUTE MISERY</span></div><div className="meter-track"><div className="meter-fill" style={{width:`${guess}%`}}/><input aria-label="Misery guess" type="range" min="0" max="100" value={guess} onChange={e=>{setGuess(Number(e.target.value));setRevealed(false)}}/><div className="meter-ticks">{[0,20,40,60,80,100].map(x=><i key={x} style={{left:`${x}%`}}><span>{x}</span></i>)}</div></div><div className="guess-readout"><div><span>{t.yourGuess}</span><b>{guess}</b></div>{revealed&&<><div className="score-divider"/><div className="actual"><span>{t.actual}</span><b>{scenario.score}</b></div></>}</div>{revealed&&<p className={`verdict ${delta<8?'great':''}`}>{verdict}</p>}<button className="primary-button full" onClick={()=>revealed?nextScenario():setRevealed(true)}>{revealed?t.again:t.reveal}{revealed?<RotateCcw size={18}/>:<Sparkles size={18}/>}</button></div></div></section>

      <WebRulebook cards={galleryCards} lang={lang}/>

      <section className="cards-section" id="cards"><div className="section-heading"><p className="kicker">{t.cardsTag}</p><h2>{t.cardsTitle}</h2></div><div className="card-gallery">{galleryCards.map((rawCard,i)=>{const card={...rawCard,title:lang==='bs'&&rawCard.titleBs?rawCard.titleBs:rawCard.title,sub:lang==='bs'&&rawCard.subBs?rawCard.subBs:rawCard.sub};return <div className={`gallery-card gc-${i}`} key={card.id || card.title}><GameCard card={card}/><div className="gallery-caption"><span>{card.tone}</span><b>{card.score}</b></div></div>})}</div></section>

      <section className="shop" id="shop"><div className="shop-heading"><p className="kicker">{t.shopTag}</p><h2>{t.shopTitle}</h2></div><div className="app-product-grid"><article className="phone-product"><div className="phone-stage"><div className="phone"><div className="phone-island"/><div className="phone-brand"><span>M</span><MascotLetter/><span>SERY</span><small>METER</small></div><div className="phone-lane"><i>03.7</i><b>?</b><i>55.5</i><i>72.4</i></div><button>PLACE ON MISERY LANE</button></div><div className="phone-ring"/></div><div className="app-copy"><p className="kicker">FREE TO START</p><h3>MISERY METER<br/>APP</h3><p>{lang==='bs'?'Kreiraj sobu, pozovi ekipu i gradi svoj Misery Lane uživo. Igra vodi poteze, otkriva ocjene i daje svima priliku za krađu.':'Create a room, invite the group, and build your Misery Lane live. The app runs turns, reveals scores, and gives everyone a chance to steal.'}</p><ul><li><Check/>{lang==='bs'?'Online multiplayer za 2–8 igrača':'Online multiplayer for 2–8 players'}</li><li><Check/>{lang==='bs'?'Vođenje poteza i ocjena uživo':'Live turn and score handling'}</li><li><Check/>{lang==='bs'?'Brze sobe preko koda':'Fast rooms with a code'}</li></ul><StoreButtons lang={lang}/></div></article><article className="pro-product"><div className="pro-glow"/><div className="pro-icon"><Crown/></div><p className="kicker">IN THE APP</p><h3>{t.app}</h3><p>{t.appText}</p><div className="pro-perks"><span><Flame/>Spicy deck</span><span><Gamepad2/>Online play</span><span><Sparkles/>Future packs</span></div><StoreButtons lang={lang} light/><small>{lang==='bs'?'Pretplata se sigurno aktivira unutar mobilne aplikacije.':'Subscription is securely activated inside the mobile app.'}</small></article></div></section>

      <section className="rules-faq"><div className="rules-card"><div><p className="kicker">FULL RULEBOOK · MISERY LANE</p><h2>{t.rules}</h2><p>{lang==='bs'?'Počni s tri poredane karte na svom Misery Laneu. Na potezu postavi novu kartu između dvije ocjene. Tačno? Zadrži je. Pogrešno? Sljedeći igrač može ukrasti. Prvi koji izgradi ciljani Misery Lane pobjeđuje.':'Start with three ordered cards on your Misery Lane. On your turn, place a new one between two scores. Correct? Keep it. Wrong? The next player can steal. First to build the target Misery Lane wins.'}</p></div><div className="rule-lane"><span>03.7</span><i/><span>?</span><i/><span>55.5</span><i/><span>72.4</span></div></div><div className="faq"><p className="kicker">FAQ</p><h2>{t.faq}</h2>{faqs.map(([q,a],i)=><article className={faqOpen===i?'open':''} key={q}><button onClick={()=>setFaqOpen(faqOpen===i?-1:i)}><span>{lang==='bs'?q:[['How many can play?'],['How long is a game?'],['Where can I play?'],['What happens when I am wrong?']][i]}</span><ChevronDown/></button><div><p>{lang==='bs'?a:[['Two to eight players in an online multiplayer room.'],['Usually 20–40 minutes depending on player count and target lane length.'],['Misery Meter is available for iOS and Android with online multiplayer and premium decks.'],['The next player gets a chance to steal it by placing it correctly in their lane.']][i]}</p></div></article>)}</div></section>
    </main>

    <footer><div className="footer-top"><Brand/><p>{lang==='bs'?'Party igra o tome ko najbolje razumije koliko život može biti loš.':'The party game about who truly understands how bad life can get.'}</p><StoreButtons compact lang={lang}/></div><div className="footer-bottom"><span>© 2026 MISERY METER · {t.rights}</span><div><button onClick={()=>navigate('privacy')}>{t.privacy}</button><button onClick={()=>navigate('terms')}>{t.terms}</button><button onClick={()=>navigate('cookies')}>{t.cookies}</button><a href="https://qla.dev" target="_blank" rel="noreferrer">qla.dev ↗</a></div></div></footer>
  </div>;
}
