# ONLYOFFICE — édition collaborative (Temps 2)

Périmètre : éditeur DOCX / XLSX / PPTX, commentaires contextualisés, suivi des modifications (suggestions), comparaison de versions. Hors périmètre : LDAP/SSO, OCR, SAE, PWA.

## Architecture

- **Laravel** reste le cœur métier (ACL, versions immuables, audit).
- **ONLYOFFICE Document Server** (Community) tourne à part (Docker local ou VPS).
- Les commentaires ancrés et le mode révision vivent **dans le fichier Office** (natif Docs).
- Les commentaires Laravel (`comments`) restent les avis généraux de dossier (CDC §14.1).
- Chaque sauvegarde Docs crée une **nouvelle** `document_versions` (jamais d’écrasement).

## Prérequis de joignabilité

1. Le **navigateur** doit atteindre `ONLYOFFICE_URL` (ex. `http://localhost:8080`).
2. Le **Document Server** doit atteindre `ONLYOFFICE_APP_URL` pour :
   - télécharger le fichier (URL signée) ;
   - poster le callback de sauvegarde.
3. En production : **HTTPS** des deux côtés (recommandé / souvent exigé).

## Développement local (Docker)

```bash
# 1. Définir le secret dans .env (doit matcher docker-compose)
ONLYOFFICE_ENABLED=true
ONLYOFFICE_URL=http://localhost:8080
ONLYOFFICE_JWT_SECRET=change-me-onlyoffice-jwt-secret-32b
ONLYOFFICE_JWT_HEADER=Authorization
ONLYOFFICE_APP_URL=http://host.docker.internal:8000

# 2. Démarrer Docs
docker compose -f docker-compose.yml up -d

# 3. Lancer Laravel (port 8000) + frontend
composer run dev
```

Sur Linux (sans `host.docker.internal`), utilisez l’IP de la machine hôte ou `extra_hosts` dans `docker-compose.yml`.

Premier démarrage Docs : 1–2 minutes. Vérifier `http://localhost:8080/healthcheck`.

## Bascule VPS

1. Installer Document Server sur le VPS (Docker recommandé).
2. Configurer le même `JWT_SECRET` et `JWT_HEADER=Authorization`.
3. Mettre à jour `.env` de Laravel :

```env
ONLYOFFICE_ENABLED=true
ONLYOFFICE_URL=https://docs.votre-domaine.gouv
ONLYOFFICE_APP_URL=https://parapheur.votre-domaine.gouv
ONLYOFFICE_JWT_SECRET=<secret-partage>
```

4. Ouvrir le firewall entre VPS Docs ↔ serveur Laravel (callback + download).

## Désactivation

`ONLYOFFICE_ENABLED=false` : comportement Temps 1 (téléchargement Office, PDF iframe). L’app reste utilisable si Docs est arrêté.

## Formats

| Format | Comportement |
|--------|--------------|
| PDF | iframe native (Temps 1) |
| DOCX, XLSX, PPTX | éditeur ONLYOFFICE si activé |
| DOC, XLS, PPT (hérités) | téléchargement seul |

## Recette manuelle

1. Déposer un DOCX sur un dossier.
2. Ouvrir la fiche → éditeur intégré (bandeau « Éditeur ONLYOFFICE »).
3. Deux utilisateurs : coédition + commentaire sur une phrase.
4. Mode Révision : suggérer, accepter / rejeter.
5. Fermer l’éditeur → nouvelle version dans l’historique.
6. Comparer deux versions via le menu Historique Docs.
7. Vérifier qu’un PDF n’ouvre pas l’éditeur.

## Dépannage (zone grise sans barre d’outils)

Symptôme typique : bandeau « Éditeur ONLYOFFICE » visible mais squelette gris sans ruban Docs.

1. **Laravel doit écouter sur toutes les interfaces** (sinon Docker ne joint pas le port) :
   ```bash
   php artisan serve --host=0.0.0.0 --port=8000
   ```
   ou `composer run dev` (déjà configuré ainsi).
2. `ONLYOFFICE_ENABLED=true` puis `php artisan config:clear`.
3. `APP_URL=http://localhost:8000` et `ONLYOFFICE_APP_URL=http://host.docker.internal:8000`.
4. Health Docs : `http://localhost:8080/healthcheck` → 200.
5. Test depuis le conteneur :
   ```bash
   docker exec eparapheur-onlyoffice curl -s -o /dev/null -w "%{http_code}" http://host.docker.internal:8000/up
   ```
6. JWT : header `Authorization` (standard DocsAPI). Secret ≥ 32 caractères, identique `.env` ↔ conteneur :
   ```bash
   docker compose -f docker-compose.yml up -d --force-recreate
   ```
   Attendre 1–2 min (`http://localhost:8080/healthcheck`).
7. Recharger la fiche (Ctrl+F5). Attendre le statut « Document ouvert ».

## Dépannage (sauvegarde / « copie de sauvegarde »)

Symptôme : avertissement « fichier ouvert depuis une copie de sauvegarde » ; pas de nouvelle version Laravel.

Cause typique : le callback Docs atteint Laravel, mais le **téléchargement** du DOCX modifié depuis `/cache/files/...` échoue (JWT inbox manquant ou URL injoignable).

1. Vérifier Laravel écoute `0.0.0.0:8000` et `ONLYOFFICE_APP_URL=http://host.docker.internal:8000`.
2. Secret JWT identique `.env` ↔ conteneur ; header `Authorization`.
3. Si besoin, forcer l’URL de téléchargement serveur : `ONLYOFFICE_INTERNAL_URL=http://localhost:8080`.
4. Vider le cache oublié Docs si une session est coincée :
   ```bash
   docker exec eparapheur-onlyoffice bash -lc "rm -rf /var/lib/onlyoffice/documentserver/App_Data/cache/files/forgotten/*"
   ```
   puis recharger la fiche (nouvelle clé document après sauvegarde OK).

Note : un bug Vue effaçait l’iframe DocsAPI à chaque re-render ; le conteneur éditeur utilise désormais `v-once`.