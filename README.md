<p align="center">
  <img src="docs/images/ticketflow-banner.svg" alt="TicketFlow v1.2.0" width="100%">
</p>

<p align="center">
  <strong>TicketFlow v1.2.0 — Stable</strong><br>
  Une plateforme simple pour créer, suivre et gérer les demandes informatiques d'une entreprise.
</p>

<p align="center">
  <img alt="Version" src="https://img.shields.io/badge/version-1.2.0-3156d9">
  <img alt="PHP" src="https://img.shields.io/badge/PHP-8.1%2B-777BB4">
  <img alt="Apache" src="https://img.shields.io/badge/Apache-2.4%2B-D22128">
  <img alt="MariaDB" src="https://img.shields.io/badge/MariaDB-compatible-003545">
  <img alt="Status" src="https://img.shields.io/badge/status-stable-16a34a">
</p>

---

# TicketFlow, c'est quoi ?

TicketFlow est un outil de **support informatique interne**.

Un salarié peut créer une demande lorsqu'il rencontre un problème ou a besoin d'un accès. L'équipe informatique peut ensuite prendre en charge la demande, discuter avec l'utilisateur, demander une validation à un Manager si nécessaire, puis proposer une résolution.

Exemples de demandes :

- « Je n'arrive plus à me connecter au VPN »
- « J'ai besoin d'un accès à une application »
- « Outlook ne fonctionne plus »
- « Je dois faire installer un logiciel »
- « Je souhaite signaler un incident informatique »

TicketFlow permet donc de remplacer les demandes dispersées par e-mail, téléphone ou messagerie par un suivi centralisé.

## Les 4 rôles

| Rôle | À quoi sert-il ? |
| --- | --- |
| **Administrateur** | Configure TicketFlow, gère les comptes, les groupes, les statistiques, les imports, les exports, les maintenances et les alertes |
| **Support IT** | Prend en charge les tickets, échange avec les utilisateurs et propose les solutions |
| **Manager** | Peut créer ses propres tickets et valider certaines demandes |
| **Collaborateur** | Crée ses tickets, suit leur avancement et confirme la résolution |

> Dans le code, le rôle **Support IT** conserve la valeur technique historique `IT`. C'est normal et cela permet de rester compatible avec les anciennes versions.

# Ce que TicketFlow sait faire

TicketFlow permet notamment de :

- créer et suivre des tickets ;
- classer une demande comme incident, requête ou changement ;
- affecter un ticket à un membre du Support IT ;
- discuter directement dans le ticket ;
- ajouter des pièces jointes ;
- envoyer des notifications ;
- suivre des délais SLA ;
- demander une validation Manager ;
- utiliser une validation Manager à deux niveaux ;
- importer plusieurs utilisateurs depuis CSV ou Excel ;
- exporter des données au format Excel ;
- consulter des statistiques ;
- gérer des alertes de service ;
- programmer une maintenance ;
- utiliser un thème clair, sombre ou automatique ;
- utiliser TicketFlow en Français ou en English (Beta).

# Installation rapide

## 1. Préparer le serveur

Sur Debian ou Ubuntu :

```bash
apt update
apt install apache2 mariadb-server php php-mysql php-mbstring php-zip php-xml php-curl curl sudo
```

## 2. Installer TicketFlow

Pour une **nouvelle installation** :

```bash
curl -fsSL https://raw.githubusercontent.com/blueGameTV/TicketFlow/main/install.sh | sudo bash
```

Le script s'occupe automatiquement de la majorité de la configuration :

- installation et préparation d'Apache ;
- préparation de MariaDB ;
- création de la base TicketFlow ;
- création du compte SQL utilisé par TicketFlow ;
- configuration PHP ;
- permissions des dossiers ;
- tâches automatiques ;
- configuration du site web TicketFlow.

À la fin, une adresse de ce type est affichée :

```text
http://IP_DU_SERVEUR/setup.php?token=...
```

Copiez cette adresse dans votre navigateur.

Vous pourrez alors créer le **premier compte Administrateur**.

Une fois le compte créé, la page d'installation est automatiquement verrouillée.

# Mettre TicketFlow à jour

Vous avez déjà TicketFlow installé ? **Ne relancez pas l'installation complète.**

Utilisez simplement :

```bash
curl -fsSL https://raw.githubusercontent.com/blueGameTV/TicketFlow/main/update.sh | sudo bash
```

Cette commande peut mettre à jour les installations suivantes vers la version actuelle :

- v1.0.0 ;
- v1.1.0 ;
- anciennes versions de développement v1.2.

Avant la mise à jour, TicketFlow crée automatiquement une sauvegarde.

La mise à jour conserve notamment :

- les utilisateurs ;
- les tickets ;
- les groupes ;
- les paramètres ;
- les pièces jointes ;
- la configuration ;
- la base de données.

Les sauvegardes sont stockées par défaut dans :

```text
/var/backups/ticketflow/
```

Pour plus d'informations : [Guide de mise à jour](docs/upgrade.md).

# Comment fonctionne un ticket ?

Exemple simple :

```text
Collaborateur
    │
    ▼
Crée un ticket
    │
    ▼
Support IT
    │
    ├── échange avec l'utilisateur
    ├── peut demander une validation Manager
    └── travaille sur la demande
    │
    ▼
Proposition de résolution
    │
    ▼
L'utilisateur confirme
    │
    ▼
Ticket résolu / archivé
```

# Validation Manager à deux niveaux

Certaines demandes peuvent nécessiter deux validations.

```text
Support IT
    │
    ├── choisit le Manager final
    │
    ▼
Manager N+1 du demandeur
    │
    ├── refuse → la validation s'arrête
    │
    └── accepte
          │
          ▼
Manager sélectionné
    │
    ├── accepte
    └── refuse
```

Le second Manager n'est contacté qu'après l'accord du Manager N+1.

# Importer plusieurs utilisateurs

Un Administrateur peut importer des utilisateurs depuis :

- un fichier CSV ;
- un fichier Excel `.xlsx`.

TicketFlow fournit des modèles prêts à remplir.

Avant de créer les comptes, TicketFlow vérifie les données et affiche les erreurs éventuelles.

Guide complet : [Import utilisateurs CSV / Excel](docs/user-import.md).

# Alertes et maintenance

TicketFlow peut afficher une alerte à tous les utilisateurs lorsqu'un service rencontre un problème.

Exemple :

> Microsoft 365 est actuellement perturbé. L'équipe Support IT analyse l'incident.

L'Administrateur peut également activer ou programmer une maintenance TicketFlow.

Guide : [Alertes de service et maintenance](docs/service-alerts-maintenance.md).

# Thème et langue

Chaque utilisateur peut choisir :

- **Clair**
- **Sombre**
- **Système** : TicketFlow suit automatiquement le thème de l'appareil

Langues disponibles :

- **Français**
- **English (Beta)**

Ces préférences sont enregistrées dans le compte utilisateur.

# Se connecter

Après installation :

```text
http://IP_DU_SERVEUR/login.php
```

Utilisez l'identifiant ou l'adresse e-mail de votre compte TicketFlow.

# Vérifier que TicketFlow fonctionne correctement

Pour un Administrateur système :

```bash
cd /var/www/ticketflow
php scripts/healthcheck.php
php scripts/security_audit.php
php scripts/route_check.php
php scripts/release_check.php
```

Si vous n'êtes pas informaticien, vous n'avez normalement pas besoin d'utiliser ces commandes.

# Sécurité

Quelques règles importantes :

- ne partagez jamais votre mot de passe TicketFlow ;
- n'envoyez jamais le fichier `config/config.php` publiquement ;
- ne publiez pas les sauvegardes de TicketFlow ;
- utilisez HTTPS pour un serveur accessible en production ;
- maintenez Debian/Ubuntu, Apache, PHP et MariaDB à jour ;
- effectuez régulièrement des sauvegardes.

Pour les détails : [SECURITY.md](SECURITY.md).

# Documentation

La documentation complète est disponible ici :

➡️ [Documentation TicketFlow](docs/README.md)

Guides principaux :

- [Installation](docs/installation.md)
- [Mise à jour](docs/upgrade.md)
- [Rôles et permissions](docs/roles-permissions.md)
- [Workflow des tickets](docs/ticket-workflow.md)
- [Import CSV / Excel](docs/user-import.md)
- [Alertes et maintenance](docs/service-alerts-maintenance.md)
- [Notes de version v1.2.0](docs/release-notes-v120.md)

# Contribuer

Vous souhaitez corriger un bug, améliorer la documentation ou proposer une fonctionnalité ?

Consultez [CONTRIBUTING.md](CONTRIBUTING.md).

# Version actuelle

**TicketFlow v1.2.0 Stable**

---

<p align="center">
  <strong>TicketFlow</strong><br>
  Centraliser les demandes IT, suivre leur traitement et simplifier les échanges entre utilisateurs, Managers et Support IT.
</p>
