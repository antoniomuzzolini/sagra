# Schema dati del core — proposta

> Approvato (giugno 2026, decisione D14): è la base delle migrazioni in
> `database/migrations` e va tenuto allineato a `dominio-e-decisioni.md`.
>
> Ottobre 2026 (revisione architetturale, step D): i punti superati dal
> codice sono marcati, non cancellati. Le altre tabelle e colonne aggiunte
> dopo la proposta (per esempio link magici, notifiche push e preferenze di
> notifica, sotto-reparti, impostazioni del tenant, moduli attivi
> dell'evento, moduli Forniture e Ordini/Cassa) non sono descritte qui: per
> quelle fanno fede le migrazioni.

## Convenzioni generali

- Nomi di tabelle e colonne in inglese, snake_case, tabelle al plurale.
- Ogni tabella di dominio porta `tenant_id` (denormalizzato anche dove
  ricavabile via join): semplifica i global scope di tenancy e rende
  impossibile un join cross-tenant per errore. Indici composti che
  iniziano per `tenant_id`. *(Applicato solo a due entità
  dell'associazione: gli indici unici di `people` e quello di `suppliers`.
  Le altre tabelle sono indicizzate per la FK con cui si interrogano
  (`event_id`, `area_id`, `shift_id`, `order_id`...), ed `events` non ha un
  indice su `tenant_id`.)*
- FK con `ON DELETE CASCADE` dentro lo stesso aggregato (es. fasi di un
  evento), `RESTRICT` tra aggregati (es. non si cancella una persona con
  assegnazioni).
- `created_at`/`updated_at` ovunque; soft delete solo dove serve davvero
  (per ora: `people`, per GDPR e storico).

## Tabelle

### tenants — l'associazione

| Colonna | Tipo | Note |
|---|---|---|
| id | bigint PK | |
| name | string | |
| slug | string unique | per URL e futuri sottodomini |
| invite_token | string unique, nullable | link d'invito condiviso per l'auto-registrazione dei volontari (D16); rigenerabile |

### users — account di accesso (organizzatori) — *ritirata da D19*

*(Superata da D19: la migrazione `2026_07_22_000001_unify_identity_into_people`
ha portato gli organizzatori in `people` con `is_organizer = true` e ha
eliminato la tabella. Password e verifica dell'email stanno ora su
`people`. La migrazione dello starter kit crea ancora `users`, che quella di
D19 poi elimina.)*

Tabella dello starter kit, estesa. Gli **utenti** sono chi accede con
credenziali (organizzatori, responsabili); i **volontari semplici non
hanno un utente**: entreranno via magic link agganciato a `people`
(progettazione nel passo auth).

| Colonna | Tipo | Note |
|---|---|---|
| tenant_id | FK tenants | |
| person_id | FK people, nullable | l'eventuale anagrafica collegata |
| ...colonne starter kit (name, email, password...) | | |

### people — anagrafica volontari e organizzatori

Minimizzazione GDPR: solo ciò che serve per turni e notifiche.

| Colonna | Tipo | Note |
|---|---|---|
| id | bigint PK | |
| tenant_id | FK tenants | |
| name | string | nome e cognome, campo unico |
| phone | string, nullable | canale primario (magic link via WhatsApp) |
| email | string, nullable | canale alternativo |
| password | string, nullable | D19: chi ha completato la registrazione, un invito account o un reset. Oggi anche un volontario con email, da «password dimenticata» |
| email_verified_at | timestamp, nullable | D19 |
| is_organizer | boolean, default false | D19: l'organizzatore è un flag della persona valido per tutta l'associazione, non un ruolo per evento |
| deleted_at | soft delete | cancellazione GDPR senza rompere lo storico *(D34: l'unico percorso sarà l'anonimizzazione, che svuota i dati e poi fa il soft delete; oggi l'organizzatore cancella anche con il solo soft delete, che conserva i dati. Step 1.4.)* |

Vincoli: unique parziale `(tenant_id, phone)` e `(tenant_id, email)`
dove non null. *(Nel codice sono indici unici normali, che contano anche le
persone cancellate; D34 li sostituisce con indici parziali
`WHERE deleted_at IS NULL`, step 1.4.)* Il contatto è richiesto
(validazione) solo per le persone create dall'organizzatore, che deve
recapitare il link d'accesso; chi si auto-registra via invito (D16) può non
lasciarne e aggiungerlo poi.
Un'email con password è unica su tutta l'istanza (D25). Oggi lo garantisce
solo il codice: `App\Support\Credentials` (invito account e cambi d'email;
login e recupero scelgono la riga con password) e la regola
`unique:people,email` della registrazione. Lo step 6 della roadmap completa
la validazione (reset), aggiunge il comando di controllo e l'indice unico
parziale.

### events — l'edizione della sagra

| Colonna | Tipo | Note |
|---|---|---|
| id | bigint PK | |
| tenant_id | FK tenants | |
| name | string | es. "Sagra 2026" |

Niente date proprie: **le date dell'evento sono derivate dalle fasi**
(min/max), per non avere due fonti di verità.

### phases — le fasi tipizzate dell'evento

| Colonna | Tipo | Note |
|---|---|---|
| id | bigint PK | |
| tenant_id | FK tenants | |
| event_id | FK events, cascade | |
| type | enum: `preparation` \| `service` \| `teardown` | insieme chiuso (preparazione / fruizione / smaltimento) |
| starts_on | date | |
| ends_on | date | >= starts_on |

Vincolo: le fasi di uno stesso evento non si sovrappongono. In Postgres
si può imporre a livello DB con un constraint `EXCLUDE USING gist` su
`daterange(starts_on, ends_on)` — da valutare; in ogni caso validazione
applicativa.

### areas — le unità organizzative

| Colonna | Tipo | Note |
|---|---|---|
| id | bigint PK | |
| tenant_id | FK tenants | |
| event_id | FK events, cascade | |
| name | string | libero (da catalogo template in fase setup) |
| family | enum nullable: `food_service` \| `logistics` \| `entertainment` \| `support` | solo per setup guidato e raggruppamenti UI |

### shifts — i turni

| Colonna | Tipo | Note |
|---|---|---|
| id | bigint PK | |
| tenant_id | FK tenants | |
| area_id | FK areas, cascade | |
| starts_at | timestamptz | |
| ends_at | timestamptz | > starts_at |
| needed_people | smallint | >= 1 |
| notes | text, nullable | |

**Nessuna colonna `phase_id`**: la fase è derivata confrontando
`starts_at` con gli intervalli delle fasi dell'evento (regola
architetturale 3). Metodo di dominio, es. `$shift->phase()`.

### shift_signups — disponibilità e assegnazioni

Una sola tabella con `status`, perché l'assegnazione è l'evoluzione
della disponibilità (stessa relazione persona↔turno) e lo stato unico
rende banale la vista copertura.

| Colonna | Tipo | Note |
|---|---|---|
| id | bigint PK | |
| tenant_id | FK tenants | |
| shift_id | FK shifts, cascade | |
| person_id | FK people, restrict | |
| status | enum: `available` \| `assigned` \| `declined` | `available` = disponibilità dichiarata; `assigned` = confermata; `declined` = rifiutata/ritirata (tiene traccia, evita ri-proposte) |
| assigned_at | timestamptz, nullable | |
| assigned_by | FK people, nullable, null on delete | chi ha confermato l'assegnazione (la persona stessa se conferma da sé, come organizzatore o responsabile dell'area); null se non assegnato. *(Era FK `users`: D19 l'ha ricreata verso `people`, azzerando i valori storici sulle installazioni esistenti.)* |
| substitution_requested_at | timestamptz, nullable | richiesta di sostituzione di chi è assegnato |
| reminded_at | timestamptz, nullable | quando è stato inviato il promemoria del turno |

Vincoli: unique `(shift_id, person_id)`.
Copertura turno = `count(status = assigned)` vs `needed_people`.
Le sostituzioni (MVP) si modellano sopra questa tabella in un secondo
momento (probabile tabella `substitution_requests`), non ora.
*(Superato: nessuna tabella `substitution_requests`. La richiesta è la
colonna `substitution_requested_at`, aggiunta con `reminded_at` dalla
migrazione `2026_06_11_000012_add_substitution_and_reminder_to_shift_signups_table`:
chi è assegnato la segna, i responsabili dell'area ricevono una notifica e
la risolvono moderando l'iscrizione.)*

### person_roles — ruoli scoped

| Colonna | Tipo | Note |
|---|---|---|
| id | bigint PK | |
| tenant_id | FK tenants | |
| person_id | FK people, cascade | |
| event_id | FK events, cascade | |
| role | enum: `organizer` \| `area_manager` | *(Superato da D19: resta solo `area_manager`, enum `App\Enums\Role`. L'organizzatore è il flag `people.is_organizer`.)* |
| area_id | FK areas, nullable | richiesto per `area_manager`, null per `organizer` *(Con D19 c'è solo `area_manager`: il codice la valorizza sempre, anche se la colonna resta nullable.)* |

Il "volontario semplice" non è un ruolo: chiunque in `people` può
dichiarare disponibilità. I permessi si applicano con le Policy native
Laravel leggendo questa tabella.

## Diagramma

```
tenants 1—N people, events          (users ritirata da D19)
events  1—N phases (tipizzate, non sovrapposte)
events  1—N areas 1—N shifts        (fase del turno derivata dalla data)
shifts  N—M people  via shift_signups (status: available/assigned/declined)
people  N—M (events, areas) via person_roles (solo area_manager, D19)
```

## Fuori da questo schema (rimandati)

- Magic link / token di accesso → passo auth. *(Fatto: tabella
  `magic_links`, vedi le migrazioni.)*
- Notifiche → tabella nativa Laravel quando servirà.
- Moduli e binding modulo→area → post-MVP. *(I moduli ci sono, accesi per
  evento in `events.enabled_modules`; il binding no: le entità dei moduli
  portano direttamente `area_id`, vedi il glossario di
  `dominio-e-decisioni.md`.)*
- Tabella sostituzioni → quando si implementa il flusso. *(Superato: è una
  colonna di `shift_signups`, vedi sopra.)*
