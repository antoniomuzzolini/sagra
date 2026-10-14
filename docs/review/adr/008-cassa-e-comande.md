# ADR-008 — Cassa e comande: affidabilità, concorrenza, accesso

- **Raccomandazione:** MODIFICARE subito con un irrobustimento server-side
  (idempotenza, numerazione, tracciabilità, accesso per dispositivo).
  Progettare i dati per un'eventuale cassa offline e decidere *dopo* aver
  misurato la connettività reale. MANTENERE il polling per il KDS.
- **Dipendenze:** ADR-001 (primo endpoint JSON, se offline), ADR-004 e ADR-005
  (accesso per dispositivo), ADR-007 (evento e cassa nel token del
  dispositivo), ADR-012 (test di concorrenza su Postgres).

## Contesto e decisione attuale

- La cassa è una pagina Inertia come le altre (`Manage/Cassa.vue`). Ogni
  vendita è una `router.post` (`Cassa.vue:95-107`) seguita da `back()`, che
  ricarica tutte le props della pagina:
  - listino e casse;
  - menu di ogni cassa, con una query per cassa (`OrderController.php:59`);
  - aree e ultimi 30 ordini.
- **Numero d'ordine:** `max(number) + 1` per evento dentro una transazione
  (`OrderController.php:121`), con indice unico `(event_id, number)`. Con due
  casse simultanee in read committed, il secondo ordine fallisce con un 500.
- **Nessuna idempotenza.** Il pulsante si disabilita durante la richiesta. Ma
  se la richiesta arriva al server e la risposta si perde (rete mobile
  instabile), il cassiere riprova e **l'ordine viene duplicato**.
- **`paid` è un booleano modificabile** da qualunque staff, su qualunque
  ordine del tenant (`OrderController.php:154-161`), senza traccia di chi e
  quando. Non esistono annullamenti né storni.
- **Accesso:** cassa e comande richiedono `staff` (organizzatore o
  responsabile). La cassa scelta vive nella **sessione della persona**
  (`TillController.php:80-91`). Un volontario di turno alla cassa non può
  usarla (01-architettura §8.2.6).
- **KDS:** polling ogni 15 secondi con `router.reload({ only: ['items'] })`
  quando la scheda è visibile (`Comande.vue:51-63`).
- **Nessuna capacità offline.** Il service worker gestisce solo il push
  (`public/sw.js:1-2`).

## Analisi dai principi

La cassa è l'unico componente con una **classe di affidabilità diversa** dal
resto dell'applicazione. Quattro proprietà del contesto reale definiscono i
requisiti.

| Proprietà della sagra | Requisito architetturale | Stato |
|---|---|---|
| **Picco concentrato** (sabato 19:30–21:30, più casse insieme) | Numerazione corretta sotto concorrenza; risposta leggera per ogni vendita | ✗ `max+1`; ✗ ricarica completa |
| **Soldi** | Ogni ordine registrato una e una sola volta; storia immutabile (annullare è un nuovo fatto, non una modifica) | ✗ nessuna idempotenza; ✗ `paid` modificabile senza traccia |
| **Connettività da campo** (4G in tensostruttura, Wi-Fi improvvisato) | Mai perdere o duplicare un ordine; idealmente continuare a vendere offline | ✗ online-only |
| **Dispositivi condivisi, volontari a rotazione** | Credenziale del **dispositivo**, con privilegio minimo (vendere o preparare), non la sessione di una persona | ✗ serve un account staff, che apre anche dati personali e gestione |

L'ultimo punto ha anche un risvolto GDPR. Oggi l'unico modo per avere una cassa
o uno schermo cucina condiviso è lasciare sul tablet la sessione di un
responsabile. Con quella sessione chiunque passi davanti al tablet vede i
telefoni dei volontari e può modificare turni (ADR-010).

## Alternative

| | A. Online + irrobustimento server | B. Cassa offline-first | C. Cassa esterna (app POS del fornitore di pagamenti) | D. Server locale alla sagra |
|---|---|---|---|---|
| Idea | UUID client per ordine (idempotenza); contatore per evento o per cassa con lock; registro degli eventi di pagamento; token del dispositivo | Client dedicato: listino in cache, ordini in IndexedDB con UUID e numero per cassa (`A-042`), sincronizzazione verso un endpoint JSON | Si incassa con SumUp, Satispay o Nexi; Fieste importa gli ordini o fa solo il KDS | Un PC sulla LAN della festa esegue l'immagine; si sincronizza col SaaS dopo |
| Complessità | Bassa | Media-alta: sincronizzazione, conflitti (prodotto disattivato offline), service worker | Media: integrazione e dipendenza dal fornitore | Alta: due fonti di verità, operatività sul posto |
| Manutenibilità | Buona | Un secondo "client" da mantenere | Dipende dalle API esterne | Pesante |
| Performance | Buona, se la risposta è parziale (`only`) | Ottima: la vendita è locale | Esterna | Ottima in LAN |
| Costo di migrazione | 2-4 giorni | 2-3 settimane | Variabile | Settimane, più supporto |
| Rischio residuo | Senza rete non si vende | Bug di sincronizzazione | Il KDS perde l'integrazione diretta; costi di commissione | Hardware e competenze sul posto |

### Dettagli di A

- **Idempotenza:** `orders.client_uuid` unico, generato dal client prima
  dell'invio. Il server risponde con l'ordine esistente se lo trova. Prepara
  anche la strada a B.
- **Numerazione:**
  - un **contatore per cassa**, con il numero in forma `A-042`, elimina la
    coordinazione tra casse ed è leggibile per chi ritira al bancone;
  - in alternativa, una riga contatore per evento con
    `SELECT … FOR UPDATE`, che serializza solo l'incremento;
  - in ogni caso, un test di concorrenza su Postgres (ADR-012).
- **Pagamenti come fatti:** una tabella `order_payments`, oppure almeno
  `paid_at`, `paid_by` e un registro delle modifiche. "Annullato" come stato
  esplicito con autore e motivo. Se un giorno servirà lo scontrino, si parte
  da qui.
- **Risposta leggera:** dopo una vendita si ricaricano solo `orders`
  (`only: ['orders']`). Il listino non cambia a ogni vendita.
- **Accesso per dispositivo** ("punto cassa" e "schermo cucina"):
  - l'organizzatore o il responsabile dell'area genera un link o un QR di
    abbinamento;
  - il tablet riceve un token legato a **una cassa** (o a un'area o
    sotto-reparto per la cucina) e a **un evento**;
  - il token permette solo di vendere o preparare, si revoca dalla pagina della
    cassa e non apre nessun'altra parte dell'app.

  È il concetto che D21 nomina già ("la cassa si instrada al
  dispositivo/area"). Risolve il volontario alla cassa senza promuoverlo e
  senza introdurre ruoli "cassiere".

### KDS: polling o tempo reale

Il polling a 15 secondi va **mantenuto**: è robusto, non ha stato, si
riprende da solo dopo un'interruzione e non richiede un processo in più.
Laravel Reverb o un servizio di websocket aggiungerebbero un servizio da far
girare e da far passare attraverso i tunnel, per guadagnare meno di 15 secondi
su una comanda. Se in cucina la latenza dà fastidio, si scende a 5 secondi: la
ricarica parziale costa poco. Va invece aggiunto un **indicatore di
connessione** ("ultimo aggiornamento: 2 minuti fa"), così la cucina capisce
quando lo schermo ha smesso di aggiornarsi.

## Raccomandazione

**MODIFICARE: A prima del prossimo evento con più casse.** Ordine suggerito:

1. idempotenza;
2. numerazione;
3. accesso per dispositivo;
4. tracciabilità dei pagamenti;
5. risposta leggera.

**B solo dopo una misura reale.** Durante un evento, registrare quante
richieste falliscono o vanno in timeout (log lato server più un contatore lato
client). Se la rete cade spesso, B è giustificato e con A i dati sono già
pronti: UUID e numero per cassa. Se non cade, B è complessità inutile.

**C e D scartati per ora.**

- C rinuncia al valore principale del modulo, cioè cassa e cucina come un
  solo ordine (D21).
- D è un prodotto diverso.

**Fiscalità:** non va modellata finché non è chiaro il regime fiscale dei
clienti tipici. Va però evitato oggi ciò che la renderebbe impossibile domani:
ordini modificabili senza traccia. Il regime (per esempio associazioni in
regime forfettario 398/91, ETS, obbligo o meno di memorizzazione e invio
telematico dei corrispettivi) **va verificato con un commercialista** prima di
progettare la parte fiscale. È una domanda aperta.

## Rischi e dipendenze

- **L'accesso per dispositivo è un secondo meccanismo di autenticazione.** Va
  progettato con la stessa cura del magic link: token con hash, revocabile,
  limitato a una cassa e a un evento, senza accesso a `/me` né alla gestione.
  Dipende da ADR-005: la Policy deve riconoscere un "attore dispositivo".
- B richiederebbe il primo endpoint JSON (ADR-001) e un service worker con
  cache: è un cambio di natura del frontend, solo per questa pagina.
- La numerazione per cassa cambia ciò che vedono i clienti al bancone. È una
  decisione di prodotto (domanda aperta).
