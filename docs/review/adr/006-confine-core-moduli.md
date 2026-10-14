# ADR-006 — Confine tra core e moduli, e il posto dei Turni

- **Raccomandazione:** MANTENERE l'attivazione per evento (enum, JSON,
  middleware). MODIFICARE il confine: introdurre un contratto leggero tra core
  e moduli nei punti in cui il core deve chiamarli. RIPROGETTARE la decisione
  D21 sui Turni: oggi il testo promette una simmetria che non conviene
  costruire.
- **Dipendenze:** ADR-001, ADR-007 (replica dell'edizione), ADR-010
  (anonimizzazione ed export), domanda aperta sull'open core.

## Contesto e decisione attuale

- **Attivazione per evento** (D21):
  - `events.enabled_modules` (JSON, dove `null` vale "default = turni");
  - enum chiuso `Module` (`app/Enums/Module.php`);
  - middleware `module:<chiave>` che risponde 404;
  - sidebar filtrata (`AppSidebar.vue:33-55`).

  Spegnere un modulo nasconde i dati, non li cancella.
- **Nessuna struttura per modulo** (D13: niente `Modules/` "finché non esiste
  il primo verticale"). I verticali oggi sono tre, quindi la condizione di D13
  è scaduta.
- **Dipendenze dal core verso i moduli** (dovrebbero essere vietate dalla
  regola 1):
  - `Tenant::suppliers()` (`Tenant.php:61-64`);
  - `Area::shifts()`, `Person::signups()`, `Person::anonymize()` che cancella
    le iscrizioni;
  - `EventController::replicate` copia i turni e quindi conosce il modulo
    Turni; il giorno in cui copierà listino e casse (01-architettura §5.8)
    conoscerà anche Ordini.
- **Statistiche** (non implementata): secondo DD:281-284 leggerà "le tabelle
  dei moduli attraverso il core". È in contrasto con la regola "il core non sa
  cosa fanno i moduli".
- **Turni:** D21 li dichiara "modulo peer, spegnibile come gli altri". Ma la
  mappa dei moduli dice "sempre attivo per ora", e il codice spegne solo
  Gestione turni. `/me`, i promemoria, la Dashboard e il roster continuano a
  usarli (01-architettura §5.3).

## Analisi dai principi

Un confine tra moduli può servire a tre scopi diversi, ognuno con un costo
diverso.

| Scopo | Cosa richiede | Vale qui? |
|---|---|---|
| **UX**: un'edizione mostra solo ciò che usa | Flag per evento + sidebar + 404 | Sì, ed è già fatto bene |
| **Commerciale**: moduli venduti o chiusi (open core) | Moduli installabili separatamente: niente enum chiuso, registrazione dinamica di rotte, menu e migrazioni | **Dipende dalla tua risposta** sull'open core |
| **Ingegneristico**: dipendenze in una direzione sola, moduli rimovibili | Il core non importa mai classi di modulo; i punti di contatto sono contratti | Sì, ma solo come **direzione delle dipendenze**, non come pacchetti separati |

**Turni: metto in discussione la premessa di D21.** D21 afferma che "nessun
modulo operativo ha bisogno di sapere chi è di turno", e da qui conclude che i
Turni sono un modulo come gli altri. Ma proprio il problema aperto della Cassa,
cioè il volontario di turno alla cassa che non può aprirla (ADR-008), è un caso
in cui "chi è di turno qui adesso" è l'informazione naturale. Inoltre:

- l'appartenenza morbida (D18) deriva dai turni;
- il roster deriva dai turni;
- i promemoria sono turni;
- il prodotto nasce come gestore di turni (D4, `Module::defaults()`).

Rendere i Turni davvero spegnibili richiede di condizionare `/me`, il
promemoria, la Dashboard, il Calendario, il roster e il notifier. Tutto per un
caso marginale ("evento solo cassa") che il flag di UI copre già quasi del
tutto. È la simmetria per la simmetria che D21 stessa critica nella sua prima
stesura.

## Alternative

| | A. Status quo | B. Convenzione leggera + contratto `ModuleDefinition` | C. Modularizzazione completa (pacchetti interni o `nwidart/laravel-modules`) | D. Abolire i moduli (tutto attivo, impostazioni per nascondere) |
|---|---|---|---|---|
| Complessità | Minima | Bassa: un'interfaccia, un registro, un file di rotte per modulo | Alta: provider, migrazioni e asset per modulo, build frontend modulare | Minima |
| Manutenibilità | La deriva cresce: ogni modulo aggiunge `if` nel core (replica, anonimizzazione, statistiche) | Il core chiama `foreach (modules) → replicate()/anonymize()/metrics()` | Confini forti, ma molta cerimonia per uno sviluppatore solo | Semplice, ma si perde l'interfaccia per evento |
| Performance | = | = | = | = |
| Costo di migrazione | — | 2-3 giorni | 1-2 settimane | Ore, ma regressione UX |
| Abilita moduli chiusi? | No | Parzialmente (un modulo esterno può registrarsi se l'enum diventa un registro) | Sì | No |

### Forma di B

- Un file di rotte e un namespace per modulo (`routes/modules/orders.php`,
  `App\Http\Controllers\Orders\…`). I modelli possono restare in `app/Models`:
  conta la direzione delle dipendenze, non la cartella.
- Un'interfaccia `ModuleDefinition` con **solo** i punti in cui il core deve
  chiamare il modulo:
  - `key`, `label` e `navItems`;
  - `replicate(Event $from, Event $to, AreaMap $map)` (ADR-007);
  - `anonymize(Person $p)` ed `export(Person $p)` (ADR-010);
  - `metrics(Event $e)`: il contratto "pull" per Statistiche, che risolve la
    contraddizione tra D3 e DD:281-284 senza un layer di push.
- Un registro (`config/modules.php` o un service provider) al posto dell'enum
  chiuso. L'enum può restare come elenco dei moduli interni.
- **Un test architetturale**, anche senza Pest: un test PHPUnit che scandisce
  i file del core e fallisce se importano un namespace di modulo. È quello che
  D12 voleva ottenere con un servizio separato ("valida i confini"), a costo
  quasi zero.

## Raccomandazione

**MANTENERE** il meccanismo di attivazione, che è semplice e giusto.

**MODIFICARE il confine con B.** Si comincia dai punti in cui il core sta già
per conoscere i moduli: replica, anonimizzazione e, presto, Statistiche.
`Tenant::suppliers()` va tolta, o spostata in un metodo del modulo.

**RIPROGETTARE D21 sui Turni**, a livello di decisione:

- dichiarare i Turni **modulo di base**: sempre presente nel codice, e il core
  può dipenderne (relazioni su `Person` e `Area`);
- il flag per evento ne nasconde solo l'interfaccia;
- anche le **notifiche automatiche** (promemoria, "nuovi turni") devono
  rispettare il flag. Costa poco e risolve lo scenario reale "evento solo
  cassa con turni rimasti in giro";
- in compenso si toglie l'obbligo di estrarre i Turni dal core, che non porta
  valore.

C solo se la risposta sull'open core è "moduli chiusi distribuiti
separatamente". In quel caso B è comunque il passo intermedio giusto.

## Rischi e dipendenze

- **Rischio di B:** il contratto cresce fino a diventare un framework di
  plugin. Regola: un metodo entra nell'interfaccia solo quando il core ha un
  chiamante reale.
- Le rotte di modulo devono restare dietro `module:<chiave>` e le Policy
  (ADR-005). Il registro deve applicare il middleware in automatico, così non
  si dimentica.
- Dichiarare i Turni "modulo di base" è una decisione di prodotto, oltre che
  tecnica: va confermata da te (domanda aperta).
