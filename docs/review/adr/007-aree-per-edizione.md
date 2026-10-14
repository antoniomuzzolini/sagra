# ADR-007 — Aree legate all'edizione e contesto "evento corrente"

- **Raccomandazione:** MODIFICARE. Introdurre un'identità stabile del reparto a
  livello di associazione, sopra le aree per edizione, che restano. MANTENERE
  l'evento corrente in sessione per lo staff, ma calcolandolo una volta per
  richiesta.
- **Dipendenze:** ADR-005 (ruoli per edizione), ADR-006 (replica tramite
  contratto), ADR-009 (destinatari delle notifiche), domanda aperta sul
  catalogo e su `AreaFamily`.

## Contesto e decisione attuale

- `areas.event_id`: **ogni edizione ha le sue aree**, e da quell'id dipende
  quasi tutto:
  - `shifts.area_id`, `sub_areas.area_id`, `person_roles.area_id`;
  - `products.area_id`, `tills.area_id`, `order_items.area_id`;
  - `supplies.area_id`.
- L'unica cosa stabile tra le edizioni è il **nome** dell'area.
- La continuità tra un anno e l'altro passa dalla **replica**
  (`EventController.php:160-241`), che copia fasi, aree, responsabili e turni,
  ma non sotto-reparti, listino e casse.
- **Appartenenza morbida (D18):** "le tue aree" sono le aree in cui ti sei
  iscritto, confrontate **per `area_id`**:
  - `Volunteer/HomeController.php:42-47` e `:98` per `isMyArea`;
  - `NewShiftsNotifier.php:31` per i destinatari dei "nuovi turni";
  - `ManagerScope::roster` per il roster del responsabile.
- **Evento corrente** in sessione (`current_event_id`, D20), risolto da
  `CurrentEvent::resolve` fino a 4 volte per richiesta.

## Evidenza verificata

Ho scritto un test temporaneo, fuori dal repository, su SQLite in memoria. Lo
scenario:

1. Un volontario ha lavorato nella "Cucina" della Sagra 2025.
2. L'organizzatore replica l'edizione nella Sagra 2026.
3. L'organizzatore crea un turno futuro nella nuova "Cucina".

Risultato:

- il veterano **non riceve** la notifica `NewShiftsAvailable`. La ricevono
  solo i nuovi arrivati senza alcuna iscrizione;
- su `/me` i turni della nuova Cucina hanno `isMyArea = false`.

In altre parole, il meccanismo che D18 descrive come "prima i turni dei reparti
dove hai già lavorato" **funziona solo all'interno di una stessa edizione**. Ma
il suo caso d'uso principale è proprio il volontario che torna l'anno dopo. Al
secondo anno di uso, i volontari più fedeli sono quelli che il sistema avvisa
meno.

## Analisi dai principi

Il modello unisce in un solo concetto due cose diverse:

- il **reparto** dell'associazione: la Cucina, con la sua storia, i suoi
  volontari abituali, il suo responsabile storico, la sua famiglia nel
  catalogo. È stabile negli anni;
- l'**area dell'edizione**: la Cucina 2026, con i suoi turni, le sue casse e il
  suo listino. È un'istanza.

Esiste solo la seconda. Tutto ciò che attraversa gli anni deve ricostruire
l'identità copiando (replica) o confrontando i nomi:

- l'appartenenza morbida;
- le statistiche "confronti tra edizioni" (DD:331);
- il catalogo e il setup guidato (`AreaFamily`);
- la replica.

Ogni nuovo modulo che referenzia `area_id` aumenta il costo di correggere
questa scelta.

## Alternative

| | A. Status quo + abbinamento per nome | B. Linea di discendenza: `areas.source_area_id` | C. Reparto di tenant: `departments` + `areas.department_id` | D. Aree di tenant (senza `event_id`) e attivazione per evento |
|---|---|---|---|---|
| Idea | "Cucina" 2025 = "Cucina" 2026 se il nome coincide | La replica scrive da quale area deriva la nuova | Entità stabile per associazione; l'area resta l'istanza annuale | L'area è unica; turni, prodotti e casse referenziano area + evento |
| Complessità | Minima | Bassa | Media-bassa | Alta |
| Manutenibilità | Fragile: rinominare un'area rompe la storia | Query ricorsive per "tutte le edizioni"; nessun posto per gli attributi stabili | Query semplici (`department_id IN …`); `family` e catalogo trovano casa | Coerente, ma si perde la variazione per edizione (aree solo di un anno) |
| Performance | = | Ricorsione (CTE) | Join semplice | = |
| Costo di migrazione | Ore | 1 giorno + backfill per nome | 2-3 giorni + backfill per nome per tenant | 1-2 settimane, tocca tutti i moduli |

## Raccomandazione

**MODIFICARE con C.**

- Nuova tabella `departments` (`tenant_id`, `name`, `family`) e colonna
  `areas.department_id` (nullable). La valorizzano la creazione dell'area (con
  scelta o creazione del reparto) e la replica. Il backfill raggruppa le aree
  esistenti per nome all'interno del tenant.
- **Appartenenza morbida per reparto:** "le tue aree" sono i reparti in cui hai
  lavorato in qualsiasi edizione. Lo stesso criterio vale per i destinatari di
  `NewShiftsNotifier` e per il roster del responsabile.
- **I ruoli restano per edizione** (ADR-005): la responsabilità è annuale. Il
  reparto rende però banale proporre "lo stesso responsabile dell'anno scorso".
- **`AreaFamily` si sposta sul reparto.** Il catalogo e il setup guidato
  (DD:289-310) diventano possibili senza altro schema.
- **Replica tramite contratto** (ADR-006). Il core copia fasi, aree e
  sotto-reparti, e passa ai moduli la mappa vecchia area → nuova area. Turni
  copia i turni con `sub_area_id`; Ordini copia listino e casse. Così si chiude
  anche il §5.8 di 01-architettura senza che il core conosca i moduli.

**Evento corrente: MANTENERE** la sessione per l'interfaccia staff. Separa
bene "allestire" da "prenotarsi" (D20), e una sagra lavora su un'edizione alla
volta. Due correzioni:

- calcolarlo **una volta per richiesta** (in `Access`, ADR-005);
- per gli **schermi dei dispositivi** (cassa, cucina, ADR-008) mettere evento
  e cassa **nell'URL o nel token del dispositivo**, non nella sessione di una
  persona. Un tablet in cucina non deve cambiare evento perché qualcuno ha
  usato il selettore su un'altra scheda.

Alternativa scartata: l'evento sempre nell'URL (`/events/{id}/…`) per tutte le
pagine. È più robusta con più schede e con i link condivisi, ma costa un
refactor di tutte le rotte e dei link, per un problema che oggi riguarda solo i
dispositivi.

## Rischi e dipendenze

- **Il backfill per nome è euristico.** Va mostrato all'organizzatore ("queste
  aree sono lo stesso reparto?") oppure accettato con la possibilità di
  correggerlo. Con pochi tenant in test si fa anche a mano.
- **Il caso "un'area si divide in due" (Cucina → Cucina + Griglia)** va
  deciso: un reparto nuovo con un collegamento al precedente, oppure un
  sotto-reparto. Non serve risolverlo subito: `department_id` nullable lo
  permette.
- Il fix minimo e immediato del problema verificato, cioè confrontare per
  `department_id` invece che per `area_id`, dipende da C. Se C viene rinviato,
  una toppa temporanea è confrontare per **nome** dell'area nello stesso
  tenant (variante A), sapendo che è fragile.
