# ADR-009 — Notifiche: canali, portata, destinatari

- **Raccomandazione:** MANTENERE l'infrastruttura (notifiche Laravel in coda,
  push web VAPID, mail, throttle via cache). MODIFICARE la strategia dei
  canali: una classe base con instradamento per persona, misura della portata
  e un ripiego manuale via WhatsApp.
- **Dipendenze:** ADR-006 (le notifiche rispettano il flag del modulo),
  ADR-007 (destinatari per reparto), ADR-010 (dati di contatto).

## Contesto e decisione attuale

- **D10:** push web come canale primario, mail come secondario. WhatsApp
  Business API rimandata per costi e vincoli.
- **Otto notifiche in coda** (`app/Notifications`). Ognuna ripete `via()`
  (push se c'è una sottoscrizione, mail se c'è l'email) e il proprio
  formattatore di date. Il filtro sulle preferenze `wantsNotification` c'è
  solo in alcune (per esempio `SeatFreed.php:28`).
- **Throttle dei "nuovi turni"** via `Cache::add` per persona e area,
  12 ore (`NewShiftsNotifier.php:34-36`). La cache è su database.
- **Chi non ha né push né email non riceve nulla.** Il promemoria lo esclude
  in modo esplicito (`SendShiftReminders.php:24-27`). Per questi casi resta
  il gesto manuale dell'organizzatore (condividi su WhatsApp dall'interfaccia).
- **Il service worker** apre o mette a fuoco `/me` (`public/sw.js:26-32`),
  anche per notifiche che riguardano la moderazione, che ora è in
  `/manage/shifts`.

## Analisi dai principi

Il valore del prodotto, scritto in DD:250-252, è eliminare "una persona che
raccoglie disponibilità via WhatsApp e rincorre i buchi". Il rincorrere *è* il
sistema di notifiche. Quindi la domanda architetturale non è "quale libreria",
ma **quanti volontari raggiungiamo davvero**.

Per il push web ci sono due vincoli esterni:

- su iPhone il push web funziona solo per una PWA **aggiunta alla schermata
  Home** (iOS 16.4 e successivi);
- il volontario tipico apre il magic link **dal browser interno di WhatsApp**,
  dove non si installa nulla.

Il canale "primario" potrebbe quindi raggiungere una minoranza. Non lo so, e
nemmeno il codice lo sa: oggi non si misura. La riga `hasPush` esiste per la
singola persona (`Volunteer/HomeController.php:72`), non come metrica.

**Cosa è fatto bene:**

- coda su database, nessun servizio in più;
- throttle approssimativo, che è accettabile perché perdere un throttle
  produce al massimo una notifica in più;
- opt-out per tipo con "chiave assente = attivo";
- tutte le notifiche sono asincrone.

## Alternative

| | A. Status quo | B. Classe base + instradamento per persona + ripiego manuale + metrica | C. Canali a pagamento (WhatsApp Business API, SMS) | D. Bot Telegram |
|---|---|---|---|---|
| Portata | Sconosciuta, probabilmente parziale su iOS | La stessa di A, **ma misurata**; chi resta fuori finisce in una lista "da avvisare a mano" | Quasi totale | Media: diffuso tra i giovani, poco tra gli anziani |
| Complessità | — | Bassa | Media: template approvati, numero verificato, webhook | Media: bot, collegamento dell'account |
| Costo operativo | 0 | 0 | Per messaggio | 0 |
| Manutenibilità | Duplicazione in 8 classi | Una classe base: canali, preferenze, formattazione | Fornitore esterno | Fornitore esterno |
| Costo di migrazione | — | 1-2 giorni | 1-2 settimane | 1 settimana |
| Valore commerciale | — | — | **Possibile funzione a pagamento del SaaS** (copre il costo per messaggio) | — |

### Forma di B

- `FiesteNotification` astratta, con:
  - `via()` unico: preferenza, poi push se c'è, poi mail se c'è;
  - formattazione delle date condivisa;
  - URL di destinazione corretto per tipo (moderazione verso
    `/manage/shifts`).
- **Ripiego manuale.** Le notifiche importanti (promemoria, posto liberato,
  conferma) destinate a persone senza canale finiscono in una lista
  "da avvisare" per l'organizzatore o il responsabile, con il link `wa.me`
  precompilato. È il flusso WhatsApp che già usano, reso sistematico e senza
  costi. In più rende evidente chi non è raggiungibile.
- **Metrica:** percentuale di volontari attivi con almeno un canale
  automatico, per evento. Da mostrare all'organizzatore (spinge a completare i
  contatti) e a te (decide se C vale la spesa).
- Le notifiche automatiche **rispettano il flag del modulo** (ADR-006) e i
  destinatari si calcolano **per reparto** (ADR-007).

## Raccomandazione

**MODIFICARE con B.** Rimandare C a dopo la misura, e valutarlo come funzione
a pagamento del SaaS: è una leva naturale dell'open core, perché il costo è
per messaggio e chi lo vuole lo paga. D non porta abbastanza rispetto a B.

**MANTENERE** la coda su database e il throttle via cache. Non servono Redis
né Horizon a questi volumi: una sagra genera centinaia di notifiche, non
milioni.

## Rischi e dipendenze

- **La lista "da avvisare" deve essere breve.** Solo i tipi che richiedono
  un'azione. Altrimenti diventa un'altra incombenza per chi organizza.
- I link `wa.me` portano dati personali (numero, nome) in un servizio
  esterno, ma su iniziativa dell'utente, come avviene oggi con la
  condivisione. Va citato nell'informativa (ADR-010).
- La correzione dei destinatari tra le edizioni (ADR-007) è la modifica con
  più impatto su questo ADR: oggi i veterani non ricevono i "nuovi turni"
  della nuova edizione.
