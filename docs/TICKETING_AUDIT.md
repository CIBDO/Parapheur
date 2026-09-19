# Audit Module Ticketing / Centre de Services DGTCP

Livrable Phase 0 (§5 du cadrage). Validé avant développement.

## 1. Ce qui existe et peut être réutilisé

- Utilisateurs, structures, Spatie Permission, Sanctum
- `NumberingService` + `NumberingSequence`
- `PrivateDocumentStorage` (PJ privées)
- `AuditLogger`
- Notifications Laravel (`database` + `mail`) + cloche UI
- Pattern StateMachine (Courrier / Meetings)
- Meta API `/meta/users`, `/meta/structures`
- Pattern module Courrier (routes préfixées, admin référentiels)

## 2. Ce qui existe mais doit évoluer

- `DatabaseSeeder` : permissions `ticket.*`, rôles
- `User::abilityRules()` : sujets CASL `Ticketing` / `TicketingAdmin`
- Navigation verticale/horizontale
- `NumberingSequenceCode` : ajout `TCK`
- Scheduler console : jobs SLA ticketing
- `AppServiceProvider` : enregistrement `TicketPolicy`

## 3. Ce qui manque

- Tout le métier ticketing (tables, services, UI)
- Moteur SLA calendaire
- Catalogue de services / formulaires dynamiques
- Équipes support + dispatching
- Base de connaissances, problèmes, CMDB (Phase 2)
- Email → Ticket, IA (Phases 2–3)

## 4. Risques de duplication

| Tentation | Décision |
|-----------|----------|
| Nouvelle GED pour PJ | Non — `PrivateDocumentStorage` |
| Nouveau moteur d’approbation | Non — Parapheur en Phase 2 |
| Second système de cloche | Non — notifications existantes |
| Nouveau référentiel users/structures | Non |

## 5. Modèle de données recommandé

Voir migrations `create_ticketing_module_tables` (MVP) et `create_ticketing_itsm_tables` (Phase 2).

## 6. Diagramme fonctionnel

Demande/Incident → Catalogue → Priorisation → SLA → Dispatching → Traitement → Résolution → Validation → Clôture → Capitalisation (KB Phase 2).

## 7. Permissions

`ticket.view|create|update|assign|reassign|take_charge|comment|internal_note|escalate|resolve|close|reopen|cancel|view_all|view_team|view_reports|manage_sla|manage_catalog|manage_categories|admin|audit.view` (+ `problem.*` / `knowledge.*` Phase 2).

## 8. Intégrations

- MVP : users, structures, notifs, audit, storage, numbering
- Phase 2 : Parapheur, Instructions, GED, ONLYOFFICE, templates
- Phase 3 : IA assistée

## 9. Plan par phases

1A socle → 1B cycle → 1C UX → 2 ITSM → 3 IA (voir plan Cursor).

## 10. Risques

Scope MVP, complexité SLA, fuite notes internes, spam notifs, queues cPanel — mitigations dans le plan.
