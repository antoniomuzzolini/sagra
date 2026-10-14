# ADR-005 — Autorizzazione

- **Raccomandazione:** MODIFICARE. Policy native per "posso agire su questo
  record", più un oggetto di accesso calcolato una volta per richiesta per
  "quali record vedo". Il ruolo di responsabile deve valere per edizione.
- **Dipendenze:** ADR-002 (casi d'uso), ADR-003 (tenancy), ADR-007 (ambito
  per edizione), ADR-008 (accesso per dispositivo).

## Contesto e decisione attuale

L'autorizzazione è distribuita su quattro livelli.

1. **Middleware di ruolo:** `organizer`, `staff`, `manager`
   (`app/Http/Middleware/Ensure*.php`).
2. **`authorizeTenant`** nella classe base.
3. **Helper privati copiati**: `authorizeArea` e `authorizeManager` in
   `SupplyController`, `TillController`, `SupplyAttachmentController`,
   `Volunteer/SignupController`, `Volunteer/ManagerShiftController` e
   `KitchenController`.
4. **Filtri di visibilità** costruiti a mano in ogni controller
   (`managedAreaIds()` più `event_id` corrente).

Non ci sono Policy né Gate, mentre lo schema le prevedeva
(`docs/schema-core.md:146-148`).

Effetti concreti:

- `OrderController::update` (`:154-161`) verifica solo il tenant. Qualunque
  "staff", anche il responsabile dei parcheggi, può segnare pagato o non
  pagato qualunque ordine di qualunque edizione.
- `EnsureStaff` concede l'accesso a chi ha avuto un ruolo di responsabile **in
  qualsiasi edizione**, anche passata (`EnsureStaff.php:22`); lo stesso vale
  per `managedAreaIds()` (`Person.php:178-189`). Il ruolo non scade.
- `managesArea()` esegue una query a ogni chiamata, ed è chiamata in cicli
  (`OrderController.php:58`).

## Analisi dai principi

Le domande di autorizzazione sono due, distinte:

1. **Posso fare X su questo record?** È un'autorizzazione puntuale (Policy).
2. **Quali record vedo?** È lo scope delle query. Oggi viene ricostruito a
   mano in ogni controller.

Le Policy rispondono solo alla prima. Chi introduce le Policy e si ferma lì
lascia la seconda domanda dispersa, ed è quella che produce le fughe.

La regola di dominio è una sola, ed è buona: **"gestisci quest'area?"**.
Organizzatore significa tutte le aree; responsabile significa le aree del suo
ruolo; la cassa appartiene all'area di chi la configura. Non serve un modello
di permessi più ricco. Serve applicare questa regola in un punto solo.

## Alternative

| | A. Status quo | B. Policy native + `Gate::before` per l'organizzatore | C. Pacchetto ruoli/permessi (es. spatie/laravel-permission con team) | D. B + oggetto `Access` per richiesta |
|---|---|---|---|---|
| Complessità | Nascosta: sparsa | Bassa: una Policy per modello di dominio | Media: tabelle e cache dei permessi, concetti generici sopra un dominio semplice | Bassa |
| Manutenibilità | Ogni nuova azione è un'occasione di errore | Un punto per regola; `authorize()` e `@can` uniformi | Ruoli configurabili, ma l'ambito "area per edizione" va comunque scritto a mano | Le Policy e le query leggono lo stesso oggetto |
| Performance | N+1 su `managesArea` | Come A, se non si memorizza | Cache dei permessi | Un calcolo per richiesta (aree gestite nell'evento corrente) |
| Costo di migrazione | — | 2-3 giorni, coperto dai test esistenti | 1 settimana; dipendenza esterna | B + mezza giornata |
| Utile se… | — | — | Servono ruoli personalizzabili dall'associazione (cassiere, magazziniere…) | — |

## Raccomandazione

**MODIFICARE con D**, cioè Policy native più un oggetto di accesso.

- `Access` (o `PersonScope`) si calcola una volta per richiesta, legato a
  persona ed evento corrente:
  - `isOrganizer`;
  - `managedAreaIds` **dell'evento corrente**;
  - `canSell` e `canPrepare` (ADR-008).

  Ha un metodo `scope(Builder)` per le query di elenco. Sostituisce le
  chiamate ripetute di `CurrentEvent::resolve` e `managedAreaIds`
  (01-architettura §5.9).
- **Policy** per Area, Shift, ShiftSignup, Supply, Till, Order e OrderItem.
  Tutte leggono `Access`. `Gate::before` gestisce solo il caso
  dell'organizzatore del tenant giusto.
- **Ruolo per edizione.** "Staff" significa avere un ruolo in un'edizione non
  conclusa, oppure in quella selezionata. I ruoli passati restano come storico
  e non danno accesso. Da decidere con te (domanda aperta): un responsabile
  deve poter consultare le edizioni passate in sola lettura?
- I **middleware di ruolo** restano per le macro-aree (`organizer`), ma
  `staff` e `manager` diventano `can:` sulle Policy.
- Nessun pacchetto di permessi (C) finché non c'è la richiesta di **ruoli
  definiti dall'associazione**. Il "cassiere" non lo richiede: si risolve con
  l'accesso per dispositivo (ADR-008).

## Rischi e dipendenze

- **Rischio:** riscrivere l'autorizzazione è il tipo di refactor in cui un
  controllo si perde. Mitigazione: prima di toccare il codice, una matrice di
  test "ruolo × azione × area propria/altrui × evento corrente/altro"
  (parametrizzata, poche decine di casi).
- ADR-003 viene prima: le Policy presumono che il record appartenga al tenant
  e non ripetono il controllo.
- ADR-007: se nasce il "reparto" stabile tra le edizioni, va deciso se il
  ruolo resta per edizione (raccomandato: sì, la responsabilità è annuale)
  o diventa per reparto.
