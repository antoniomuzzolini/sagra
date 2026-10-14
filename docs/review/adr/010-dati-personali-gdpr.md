# ADR-010 — Dati personali: ciclo di vita e minimizzazione

- **Raccomandazione:** MODIFICARE. Un unico percorso di anonimizzazione usato
  ovunque, una retention automatica, l'export per il diritto di accesso e la
  minimizzazione dei dati esposti a interfaccia e ruoli. MANTENERE
  l'anonimizzazione come alternativa alla cancellazione fisica.
- **Dipendenze:** ADR-004 (unicità e cancellazione), ADR-006 (i moduli
  contribuiscono ad anonimizzazione ed export), ADR-008 (accesso per
  dispositivo), ADR-011 (backup).

## Contesto e decisione attuale

- **Anonimizzazione** `Person::anonymize()` (`Person.php:124-153`):
  - sostituisce i dati personali con "Utente rimosso";
  - cancella le iscrizioni future, i link, le sottoscrizioni push e i ruoli;
  - fa il soft delete della riga, conservando lo storico delle iscrizioni
    passate per gli aggregati.

  La usa solo l'**auto-cancellazione** (volontario e account).
- **La cancellazione da parte dell'organizzatore** (`PersonController.php:63-71`)
  fa solo il soft delete: nome, telefono ed email **restano**. Verificato in
  01-architettura §8.5.
- **Nessuna retention.** Le iscrizioni restano nominative per sempre, e la
  politica è una domanda aperta (DD:278-281).
- **Nessun export** dei dati di una persona (artt. 15 e 20 GDPR).
- **Esposizione:**
  - `auth.user` serializza l'intero modello `Person` a ogni richiesta
    (`HandleInertiaRequests.php:49`);
  - il roster del responsabile mostra telefono ed email dei volontari delle
    sue aree (`PersonRoster.php:55-56`);
  - le sessioni dei responsabili restano su tablet condivisi in cassa e in
    cucina (ADR-008).
- **Titolarità:** l'associazione è titolare del trattamento (DD:276-277).
  Fieste SaaS è quindi **responsabile del trattamento** (art. 28), cosa che ha
  conseguenze contrattuali.

## Cosa è fatto bene

- L'**anonimizzazione al posto della cancellazione fisica** è la scelta giusta
  per un gestionale con storico: conserva gli aggregati, rimuove l'identità e
  non rompe le chiavi esterne.
- L'**auto-cancellazione self-service** è rara anche in prodotti molto più
  grandi.
- Le iscrizioni future vengono annullate, non lasciate orfane.
- Il blocco dell'ultimo organizzatore protegge il tenant.
- La minimizzazione all'origine è reale: nome unico, contatto facoltativo, e
  `/me` non mostra i dati degli altri (`HomeController.php:34-36`).

## Analisi dai principi

La privacy by design (art. 25) chiede tre proprietà: **minimizzazione**,
**limitazione della conservazione** e **coerenza dei diritti**.

- La prima è buona all'origine, ma non nell'esposizione.
- La seconda manca del tutto.
- La terza è rotta: due percorsi di cancellazione con effetti diversi.

Il modello modulare aggiunge un requisito: ogni modulo che salva riferimenti
a persone deve partecipare a export e anonimizzazione:

- Ordini: `orders.created_by`;
- Forniture: fornitori che sono persone fisiche (ditte individuali) e fatture
  allegate.

## Alternative

| | A. Status quo + correzione della cancellazione organizzatore | B. Ciclo di vita unico: anonimizzazione, retention, export, contributi dei moduli | C. Cancellazione fisica + snapshot aggregati |
|---|---|---|---|
| Idea | `PersonController::destroy` chiama `anonymize()` | Un caso d'uso `ErasePerson`; un job di retention; un caso d'uso `ExportPerson`; `ModuleDefinition::anonymize/export` | A fine edizione si calcolano gli aggregati e si cancellano le righe personali |
| Complessità | Minima | Media-bassa | Media: definire *prima* quali aggregati servono |
| Conformità | Migliora la coerenza; manca la retention | Copre coerenza, retention e accesso | Massima minimizzazione |
| Manutenibilità | — | Ogni modulo dichiara i propri dati personali | Ogni nuova statistica richiede di ricalcolare dati che non esistono più |
| Costo di migrazione | Ore | 3-5 giorni | 1 settimana + perdita irreversibile di dettaglio |

## Raccomandazione

**MODIFICARE con B.**

1. **Un solo percorso di cancellazione**, sia per l'organizzatore sia per
   l'auto-cancellazione, con gli indici parziali di ADR-004.
2. **Retention configurabile per tenant**, con un default ragionevole da
   definire con te, per esempio "iscrizioni nominative per 3 edizioni o 3 anni,
   poi pseudonimizzate". Un job schedulato anonimizza le persone senza
   attività da N anni.
3. **Export JSON** dei dati di una persona, richiedibile dall'organizzatore
   per conto del volontario. I moduli contribuiscono tramite il contratto di
   ADR-006.
4. **Minimizzazione nell'interfaccia:**
   - `auth.user` con campi espliciti (ADR-001);
   - telefono ed email nel roster solo dove servono (contattare il volontario),
     eventualmente mascherati con "mostra";
   - nessuna sessione personale su dispositivi condivisi (ADR-008).
5. **Documentazione e contratti** (fuori dal codice, ma bloccanti per il
   SaaS): registro dei trattamenti, informativa che il tenant mostra ai
   volontari (link d'invito, `/join`), accordo art. 28 tra te e le
   associazioni, elenco dei sub-responsabili:
   - hosting e database;
   - mail;
   - servizi push dei browser (Google, Apple, Mozilla, che ricevono payload
     cifrati);
   - Cloudflare, se usato;
   - Bunny Fonts.

C è scartata: la perdita di dettaglio è irreversibile, e lo storico
nominativo delle edizioni recenti ha valore legittimo (chi chiamare l'anno
prossimo).

## Rischi e dipendenze

- **Backup (ADR-011):** i dati anonimizzati restano nei backup fino alla loro
  scadenza. Serve una rotazione dichiarata (per esempio 30 giorni) e
  menzionata nell'informativa.
- **Fatture:** le fatture allegate hanno obblighi di conservazione fiscale
  (tipicamente 10 anni) che **prevalgono** sulla cancellazione. La retention
  di Forniture va quindi separata da quella dei volontari.
- La retention richiede una definizione di "attività" (ultima iscrizione,
  ultimo accesso). Va decisa con te.
