# Architecture initiale

Le projet suit une séparation simple inspirée MVC :

- `public/` : point d'entrée web et assets
- `src/Controllers/` : logique HTTP
- `src/Models/` : accès et représentation des données
- `src/Services/` : logique métier
- `src/Security/` : authentification, sessions, CSRF, autorisations
- `src/Database/` : connexion PDO
- `templates/` : vues selon le rôle
- `database/` : schéma SQL et données de démonstration
- `storage/` : uploads et logs hors dossier public
