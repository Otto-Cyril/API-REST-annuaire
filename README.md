# Annuaire des gardes et astreintes

Annuaire du personnel de garde de l'Institut Mutualiste Montsouris (IMM) : recherche du personnel, numéros de garde et numéros d'urgence. La consultation est publique ; l'administration (création, modification, suppression, journal des actions) est réservée aux comptes Active Directory.

Le dépôt contient deux applications :

| Dossier | Contenu | Technologies |
|---|---|---|
| [`API REST/`](API%20REST/README.md) | API JSON, authentification LDAP + JWT, journal d'audit | Symfony 7.4, Doctrine, SQL Server |
| [`frontend/`](frontend/) | Interface web (annuaire, fiches, administration) | Vue 3, Vue Router, Pinia, Vite |

## Démarrage rapide

1. **API** : suivre l'installation détaillée dans [`API REST/README.md`](API%20REST/README.md) (configuration `.env.local`, clés JWT, base SQL Server, migrations), puis :

   ```bash
   cd "API REST"
   php -S 127.0.0.1:8000 -t public      # ou : symfony serve
   ```

2. **Frontend** (Node.js requis) :

   ```bash
   cd frontend
   npm install
   npm run dev
   ```

   En développement, Vite relaie `/api` vers `http://127.0.0.1:8000` (voir `frontend/vite.config.js`) : l'API doit donc tourner sur ce port.

## Fonctionnalités du frontend

- **Public** : annuaire du personnel (recherche et filtres), personnel de garde, fiches, barre des numéros d'urgence, mode clair/sombre.
- **Administration** (connexion requise, menu « Administration ») : services, métiers, numéros d'urgence, personnel de garde, numéros de garde, annuaire du personnel, et journal des actions (`/admin/traces`).

## Tests

```bash
cd frontend && npm test                    # tests Vitest
cd "API REST" && php bin/phpunit           # tests PHPUnit (base de test requise, voir le README de l'API)
```

## Avant une mise en production

Voir la section dédiée du [README de l'API](API%20REST/README.md#avant-une-mise-en-production). Point à ne pas oublier : `LDAP_ADMIN_GROUP_DN` est vide pour l'instant, donc tout compte AD valide a les droits d'écriture.

Pour le frontend, `npm run build` produit le dossier `dist/`, à servir avec `/api` relayé vers l'API.
