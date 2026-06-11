# Schema dati del core — proposta

> Bozza in revisione. Quando approvata, diventa la base delle migrazioni
> e va tenuta allineata a `dominio-e-decisioni.md`.

## Convenzioni generali

- Nomi di tabelle e colonne in inglese, snake_case, tabelle al plurale.
- Ogni tabella di dominio porta `tenant_id` (denormalizzato anche dove
  ricavabile via join): semplifica i global scope di tenancy e rende
  impossibile un join cross-tenant per errore. Indici composti che
  iniziano per `tenant_id`.
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

### users — account di accesso (organizzatori)

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
| deleted_at | soft delete | cancellazione GDPR senza rompere lo storico |

Vincoli: almeno un contatto tra `phone` ed `email` (check applicativo);
unique parziale `(tenant_id, phone)` e `(tenant_id, email)` dove non null.

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
| assigned_by | FK users, nullable | null se auto-assegnazione/conferma automatica |

Vincoli: unique `(shift_id, person_id)`.
Copertura turno = `count(status = assigned)` vs `needed_people`.
Le sostituzioni (MVP) si modellano sopra questa tabella in un secondo
momento (probabile tabella `substitution_requests`), non ora.

### person_roles — ruoli scoped

| Colonna | Tipo | Note |
|---|---|---|
| id | bigint PK | |
| tenant_id | FK tenants | |
| person_id | FK people, cascade | |
| event_id | FK events, cascade | |
| role | enum: `organizer` \| `area_manager` | |
| area_id | FK areas, nullable | richiesto per `area_manager`, null per `organizer` |

Il "volontario semplice" non è un ruolo: chiunque in `people` può
dichiarare disponibilità. I permessi si applicano con le Policy native
Laravel leggendo questa tabella.

## Diagramma

```
tenants 1—N users, people, events
events  1—N phases (tipizzate, non sovrapposte)
events  1—N areas 1—N shifts        (fase del turno derivata dalla data)
shifts  N—M people  via shift_signups (status: available/assigned/declined)
people  N—M (events[, areas]) via person_roles
users   0..1—1 people
```

## Fuori da questo schema (rimandati)

- Magic link / token di accesso → passo auth.
- Notifiche → tabella nativa Laravel quando servirà.
- Moduli e binding modulo→area → post-MVP.
- Tabella sostituzioni → quando si implementa il flusso.
