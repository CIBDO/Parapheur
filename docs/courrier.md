# Module Courrier DGTCP

## Architecture

Le module **Courrier** porte le cycle administratif de la correspondance. Il ne duplique pas la GED ni le Parapheur :

- `correspondences` — cycle métier (entrant / sortant / interne)
- `documents` + `document_versions` — fichiers, scans, versions ONLYOFFICE
- Instructions existantes — suites à donner après imputation
- Parapheur — visa / validation / signature applicative des projets de réponse
- `document_templates` + `document_template_versions` — modèles DOCX versionnés

## Numérotation

Format transactionnel : `ARR/2026/000001`, `DEP/2026/000001`, `BT/2026/000001`, `FC/2026/000001` via `NumberingService` + `numbering_sequences` (`lockForUpdate`).

## API principale

Préfixe authentifié : `/api/mail`

| Zone | Endpoints |
|------|-----------|
| Dashboards | `GET /dashboard/order-office`, `/dg`, `/direction` |
| Méta | `GET /meta/channels\|categories\|qualifications\|actions` |
| Admin référentiels | CRUD `/admin/channels\|categories\|qualifications\|actions` (`mail.admin`) |
| Registres | `POST /registers/incoming\|outgoing\|internal` |
| Courriers | CRUD `/correspondences`, `history`, `reply`, `submit-to-parapheur`, `dispatch`, `acknowledge`, `archive`, `parties`, `print`, `attach-signed-version` |
| Affectations | `POST .../assignments`, `take-charge`, `reassign`, `return`, `request-complement` |
| Relances | `GET/POST .../reminders`, `POST .../reminders/schedule` — job `parapheur:remind-mail` (08:15) |
| Bordereaux | `/transmission-slips` (+ generate, validate, print, send, acknowledge + preuve) |
| Fiches | `/circulation-sheets`, `POST .../circulation-sheet` (+ generate DOCX / fallback blank) |
| Impression | `GET /print-logs`, `POST .../print` (reason, is_reprint) |
| Modèles | `/document-templates` (+ versions, publish, generate) |

Permissions Spatie : `mail.*` (dont `mail.admin`), `document_template.*`. Subjects CASL : `Courrier`, `CourrierAdmin`, `DocumentTemplate`.

## UI

Menu **Courrier** : tableau de bord (vues Bureau d'ordre / DG / Direction), entrants/sortants/internes, à affecter/traiter, en retard, bordereaux, fiches, recherche, archives, modèles, admin.

Fiche 360° partagée (`CorrespondenceDetailView`) : synthèse, parties, document ONLYOFFICE, affectations, relances, fiche circulation, historique, chaîne.

## Notifications

`MailCorrespondenceNotification` : affectation, prise en charge, projet de réponse, expédition, relances échéance.

## Guide utilisateur

1. Bureau d'ordre : **Enregistrer un entrant** → n° `ARR/...`
2. Compléter les parties (expéditeur / destinataires / ampliations)
3. Affecter à une structure/agent avec instruction → prise en charge
4. Demander un complément si besoin ; créer des relances manuelles ou planifier J-3/J-1/J
5. **Préparer une réponse** → sortant lié ; éditer via ONLYOFFICE ; signature physique si besoin
6. Soumettre au Parapheur si requis, puis expédition (`DEP/...`)
7. Multi-sélection → bordereau `BT/...` → génération → impression / AR avec preuve scan
8. Fiche de circulation `FC/...` générée depuis la fiche courrier

## Admin

Page `/courrier/admin` : CRUD canaux, catégories, qualifications, actions d'affectation, correspondants.

## Tests

```bash
php artisan test --filter=Mail
```

Couvre fondation (numérotation, affectation, BT, modèles, ACL) et compléments (relances, parties, complément, signature, print logs, admin, dashboards).

## Limites Phase 2 / 3

OCR, QR code, conversion PDF serveur, import e-mail entrant, dossiers/affaires 360°, règles d'affectation auto, suggestions IA (propose-only).
