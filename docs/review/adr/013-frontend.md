# ADR-013 — Frontend: Vue 3, shadcn-vue, Tailwind 3, PWA minimale

- **Raccomandazione:** MANTENERE lo stack. MODIFICARE alcuni punti: separare
  l'interfaccia "operativa" (cassa e cucina) dalla configurazione, tipizzare
  le props condivise, rimuovere i residui dello starter kit. L'aggiornamento a
  Tailwind 4 e reka-ui va pianificato, non è urgente.
- **Dipendenze:** ADR-001 (props come contratto), ADR-008 (modalità
  dispositivo ed eventuale offline), ADR-009 (service worker).

## Contesto e decisione attuale

- **Stack:**
  - Vue 3 + TypeScript via Inertia 2 (`@inertiajs/vue3` 2.0.3);
  - componenti shadcn-vue su **radix-vue 1.9** (109 file in
    `components/ui/`);
  - **Tailwind 3.4**;
  - Ziggy per le rotte;
  - partenza dallo starter kit ufficiale Laravel Vue.
- **Una pagina per vista**, più alcuni componenti di dominio riusati tra ruoli:
  `AreaShiftBoard`, `OverviewDashboard`, `PeopleRoster`, `ScheduleTimeline`.
- **Testi in italiano scritti direttamente nei componenti**, senza i18n. Il
  locale di Laravel è `en` (`.env.example`), quindi i messaggi del framework
  escono in inglese.
- **PWA minimale:** manifest dinamico e service worker solo per il push.
- **Pagine grandi che mescolano operatività e configurazione:** `Cassa.vue`
  (446 righe) contiene insieme il POS, la gestione delle casse, il menu delle
  casse e il listino.
- **Residui dello starter kit**: layout e componenti mai importati, la
  citazione `Inspiring` calcolata a ogni richiesta (01-architettura §5.13).

## Cosa è fatto bene

- **Componenti di dominio condivisi tra organizzatore e responsabile:** è la
  traduzione nel frontend di D20 ("una shell, scope diversi"). Non ci sono
  pagine duplicate per ruolo.
- **shadcn-vue:** i componenti sono copiati nel progetto, non sono una
  dipendenza. Si modificano senza aspettare nessuno e l'accessibilità di base
  arriva dalle primitive radix.
- **Mobile-first reale:** il flusso del volontario (`Volunteer/Home.vue`) è una
  sola pagina con azioni a un tocco.

## Analisi dai principi

Gli utenti del frontend sono due, e molto diversi:

1. **Persone** (volontari, responsabili, organizzatori) su smartphone personali,
   in momenti sparsi: prenotare, organizzare, consultare. Qui Inertia con
   pagine server-driven è ideale.
2. **Postazioni** (cassa, schermo cucina) su tablet condivisi, in uso continuo
   per ore, sotto pressione, con rete incerta. Qui servono:
   - un'interfaccia a schermo pieno, senza sidebar né selettore evento;
   - pulsanti grandi e uno stato di connessione visibile;
   - in futuro, forse l'offline (ADR-008).

Oggi la seconda categoria è servita come la prima, dentro la shell staff.

## Alternative

| | A. Status quo | B. Stack attuale + "modalità postazione" + pulizia | C. Migrazione a Tailwind 4 + reka-ui ora | D. Client separato per la cassa (SPA/PWA dedicata) |
|---|---|---|---|---|
| Complessità | — | Bassa: un layout dedicato e pagine operative separate dalla configurazione | Media: 109 componenti generati, configurazione Tailwind | Media-alta: seconda app, build separata, endpoint JSON |
| Manutenibilità | Pagine operative appesantite dalla configurazione | Ogni pagina ha un solo scopo | Allineamento con lo starter kit attuale e con la documentazione di shadcn-vue | Due frontend |
| Performance | = | Postazioni più leggere (meno props, ADR-008) | = | La migliore per l'offline |
| Costo di migrazione | — | 2-3 giorni | 2-4 giorni, senza valore per gli utenti | Settimane |

Nota su C: radix-vue è stato rinominato **reka-ui** con la versione 2, e lo
starter kit Laravel Vue attuale usa Tailwind 4 e reka-ui. Restare indietro
significa scostarsi un po' alla volta dalla documentazione e dagli esempi. Non
è un rischio funzionale.

## Raccomandazione

**MANTENERE** lo stack.

**MODIFICARE con B:**

1. **Layout "postazione"** per cassa e cucina, attivato dall'accesso per
   dispositivo (ADR-008). La configurazione di casse e listino si sposta in
   una pagina di configurazione separata.
2. **Props condivise tipizzate:**
   - campi espliciti per `auth.user` (ADR-001 e ADR-010);
   - un tipo TypeScript che le descrive davvero (oggi `User` in
     `types/index.ts` non corrisponde);
   - `vue-tsc --noEmit` in CI (ADR-012).
3. **`APP_LOCALE=it`** e i file di traduzione standard di Laravel per i
   messaggi del framework. Nessuna i18n del frontend finché il mercato è solo
   italiano: i testi scritti nei componenti sono una scelta legittima per un
   prodotto monolingua.
4. **Rimuovere i residui** dello starter kit (layout non usati, `Inspiring`,
   `AuthSplitLayout`, ecc.).
5. **Pianificare C** in un momento senza eventi, come lavoro separato, quando
   capita di toccare molti componenti. Non mescolarlo con i refactoring di
   dominio.

**D** solo se ADR-008 conclude che l'offline serve. Anche in quel caso
basterebbe una pagina Vue con la propria logica offline, dentro la stessa
build, invece di una seconda applicazione.

## Rischi e dipendenze

- La modalità postazione dipende dall'accesso per dispositivo (ADR-008,
  ADR-005). Senza quello, resta una pagina a schermo pieno dentro una
  sessione personale.
- Inertia 2 è stata fissata quando era ancora in beta (`^2.0.0-beta.3` in
  `package.json`). Il lock è su 2.0.3; conviene alzare il vincolo a `^2.0`
  stabile per evitare sorprese con `npm install` senza lock.
