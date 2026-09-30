<?php

declare(strict_types=1);

namespace App\Support;

final class Translator
{
    private array $messages = [];
    private string $locale;
    private string $langDir;

    public function __construct(string $locale, string $langDir)
    {
        $this->locale = in_array($locale, ['fr','en'], true) ? $locale : 'fr';
        $this->langDir = $langDir;
        $file = rtrim($langDir, '/\\') . '/' . $this->locale . '.php';
        if (is_file($file)) {
            $messages = require $file;
            if (is_array($messages)) $this->messages = $messages;
        }
    }

    public function locale(): string { return $this->locale; }

    public function get(string $key, array $params = []): string
    {
        $text = (string)($this->messages[$key] ?? $key);
        foreach ($params as $name => $value) {
            $text = str_replace('{' . $name . '}', (string)$value, $text);
        }
        return $text;
    }

    /**
     * Compatibility layer for legacy pages that still contain French labels.
     * It only runs for English HTML rendering and does not affect API/Excel output.
     */
    public function translateRenderedHtml(string $html): string
    {
        if ($this->locale !== 'en' || $html === '') return $html;

        $frFile = rtrim($this->langDir, '/\\') . '/fr.php';
        $enFile = rtrim($this->langDir, '/\\') . '/en.php';
        $fr = is_file($frFile) ? require $frFile : [];
        $en = is_file($enFile) ? require $enFile : [];

        $map = [];
        if (is_array($fr) && is_array($en)) {
            foreach ($fr as $key => $value) {
                if (!isset($en[$key]) || !is_string($value) || !is_string($en[$key])) continue;
                if ($value === '' || $value === $en[$key] || str_contains($value, '{')) continue;
                $map[$value] = $en[$key];
            }
        }

        $legacy = [
            'Changer mon mot de passe' => 'Change my password',
            'Renforcez la sécurité de votre compte avec un mot de passe unique et robuste.' => 'Strengthen your account security with a unique, strong password.',
            '12 caractères minimum' => '12 characters minimum',
            'Utilisez au minimum 12 caractères avec une majuscule, une minuscule et un chiffre.' => 'Use at least 12 characters with an uppercase letter, a lowercase letter and a number.',
            '12+ caractères' => '12+ characters',
            'Majuscule' => 'Uppercase',
            'Minuscule' => 'Lowercase',
            'Chiffre' => 'Number',
            'Confirmez le nouveau mot de passe' => 'Confirm the new password',
            '12 caractères minimum, avec majuscule, minuscule et chiffre.' => 'Minimum 12 characters, including uppercase, lowercase and a number.',
            'Mot de passe actuel' => 'Current password',
            'Nouveau mot de passe' => 'New password',
            'Modifier le mot de passe' => 'Change password',
            'Retour' => 'Back',
            'Informations' => 'Information',
            'Catégorie' => 'Category',
            'Importance' => 'Priority',
            'Demandeur' => 'Requester',
            'Support IT assigné' => 'Assigned IT Support',
            'Prise en charge SLA' => 'SLA response',
            'Résolution SLA' => 'SLA resolution',
            'Première prise en charge' => 'First response',
            'Dernière mise à jour' => 'Last update',
            'Autre' => 'Other',
            'Faible' => 'Low',
            'Normale' => 'Normal',
            'Haute' => 'High',
            'Critique' => 'Critical',
            'Non assigné' => 'Unassigned',
            'Dépassé' => 'Overdue',
            'En cours' => 'In progress',
            'Nouveau' => 'New',
            'Aucun message pour le moment.' => 'No messages yet.',
            'Nouveau message' => 'New message',
            'Ajoutez une réponse pour poursuivre les échanges.' => 'Add a reply to continue the conversation.',
            'Écrivez votre message…' => 'Write your message…',
            'Note interne, invisible pour le demandeur' => 'Internal note, hidden from the requester',
            'Envoyer' => 'Send',
            'Pièces jointes' => 'Attachments',
            'Aucune pièce jointe pour ce ticket.' => 'No attachments for this ticket.',
            'Ajouter des fichiers' => 'Add files',
            'Ajouter les pièces jointes' => 'Upload attachments',
            'Aucun fichier choisi' => 'No file selected',
            'Sélect. fichiers' => 'Choose files',
            '5 fichiers maximum' => '5 files maximum',
            'Historique' => 'History',
            'Ticket créé' => 'Ticket created',
            'Actions Support IT' => 'IT Support actions',
            'Transférer à' => 'Transfer to',
            'Choisir un technicien Support IT' => 'Choose an IT Support technician',
            'Transférer' => 'Transfer',
            'Validation Manager à deux niveaux' => 'Two-level Manager approval',
            'Étape 1' => 'Step 1',
            'Manager N+1 du demandeur' => "Requester's N+1 Manager",
            'Manager pour la validation finale *' => 'Manager for final approval *',
            'Sélectionner un Manager' => 'Select a Manager',
            'Le Manager sélectionné ne recevra la demande qu’après validation du N+1.' => 'The selected Manager will only receive the request after N+1 approval.',
            'Envoyer au N+1 puis au Manager sélectionné' => 'Send to N+1, then to the selected Manager',
            'Proposer la résolution' => 'Propose resolution',
            'Le ticket passera en attente de confirmation du demandeur.' => 'The ticket will move to awaiting requester confirmation.',
            'Expliquez la solution appliquée…' => 'Explain the solution applied…',
            'Proposer au demandeur' => 'Send proposal to requester',
            'Modifier un groupe' => 'Edit group',
            'Le responsable doit posséder le rôle Manager et avoir un compte actif.' => 'The responsible person must have the Manager role and an active account.',
            'Informations du groupe' => 'Group information',
            'Définissez le service, son responsable et son état.' => 'Define the service, its Manager and its status.',
            'Nom du groupe *' => 'Group name *',
            'Manager responsable *' => 'Responsible Manager *',
            'Pour créer un groupe, il faut donc d’abord disposer d’au moins un compte Manager.' => 'To create a group, at least one Manager account must already exist.',
            'Groupe actif' => 'Active group',
            'Annuler' => 'Cancel',
            'Enregistrer les modifications' => 'Save changes',
            'Modifier un utilisateur' => 'Edit user',
            'Gérez l’identité, l’organisation et la sécurité du compte depuis un formulaire structuré.' => 'Manage identity, organization and account security from a structured form.',
            'Identité' => 'Identity',
            'Informations utilisées pour identifier l’utilisateur dans TicketFlow.' => 'Information used to identify the user in TicketFlow.',
            'Prénom *' => 'First name *',
            'Nom *' => 'Last name *',
            'Identifiant *' => 'Username *',
            'Adresse e-mail *' => 'Email address *',
            'Organisation' => 'Organization',
            'Rôle *' => 'Role *',
            'Groupe' => 'Group',
            'Date d’arrivée' => 'Arrival date',
            'Compte actif' => 'Active account',
            'L’utilisateur peut se connecter à TicketFlow.' => 'The user can sign in to TicketFlow.',
            'Mot de passe et obligations de sécurité lors de la prochaine connexion.' => 'Password and security requirements for the next sign-in.',
            'Laisser vide pour conserver le mot de passe actuel.' => 'Leave blank to keep the current password.',
            'Forcer le changement de mot de passe' => 'Force password change',
            'L’utilisateur devra définir un nouveau mot de passe à sa prochaine connexion.' => 'The user must set a new password at the next sign-in.',
            'Alertes de service globales' => 'Global service alerts',
            'Informez immédiatement les utilisateurs d’un incident, d’une dégradation ou d’une maintenance.' => 'Immediately inform users about an incident, degradation or maintenance.',
            'Créer une alerte' => 'Create an alert',
            'La banderole apparaît automatiquement pour les utilisateurs connectés.' => 'The banner automatically appears for signed-in users.',
            'Application / service *' => 'Application / service *',
            'Niveau *' => 'Severity *',
            'Titre *' => 'Title *',
            '80 caractères maximum' => '80 characters maximum',
            'Indisponibilité en cours' => 'Service disruption in progress',
            'Message *' => 'Message *',
            '2000 caractères maximum' => '2000 characters maximum',
            'L’équipe Support IT analyse actuellement le problème…' => 'IT Support is currently investigating the issue…',
            'Début' => 'Start',
            'Fin prévue' => 'Expected end',
            'État initial' => 'Initial status',
            'Publier l’alerte' => 'Publish alert',
            'Alertes actives' => 'Active alerts',
            'Aucune alerte active.' => 'No active alerts.',
            'Retrouvez les dernières alertes publiées et leur état.' => 'Review the latest published alerts and their status.',
            'Résolue' => 'Resolved',
            'Centre de notifications' => 'Notification center',
            'Retrouvez les événements importants liés à vos tickets. Les notifications lues sont supprimées automatiquement après 24 heures.' => 'Review important events related to your tickets. Read notifications are automatically deleted after 24 hours.',
            'Toutes' => 'All',
            'Non lues' => 'Unread',
            'Aucune notification dans cette vue.' => 'No notifications in this view.',
            'Les nouveaux événements apparaîtront automatiquement ici.' => 'New events will automatically appear here.',
            'Aucune alerte n’a encore été publiée.' => 'No alerts have been published yet.',
            'Alerte en cours' => 'Alert in progress',
            'Créée par' => 'Created by',
            'Marquer résolue' => 'Mark resolved',
            'Supprimer' => 'Delete',
            'Supprimer l’utilisateur' => 'Delete user',
            'La suppression est disponible uniquement si le compte ne possède aucun historique métier. Sinon, utilisez la désactivation pour préserver la traçabilité.' => 'Deletion is available only when the account has no business history. Otherwise, deactivate it to preserve traceability.',
            'Supprimer tout l’historique' => 'Delete all history',
            'Information' => 'Information',
            'Dégradation' => 'Degradation',
            'Incident majeur' => 'Major incident',
            'Incident critique' => 'Critical incident',
            'Maintenance' => 'Maintenance',
            'Surveillance' => 'Monitoring',
            'Confirmation' => 'Confirmation',
            'Administrateur' => 'Administrator',
            'Collaborateur' => 'Employee',
            'Tous les rôles' => 'All roles',
            'Tous les groupes' => 'All groups',
            'Tous les états' => 'All statuses',
            'Tous les statuts' => 'All statuses',
            'Toutes les importances' => 'All priorities',
            'Mes tickets' => 'My tickets',
            'Équipe Support IT' => 'IT Support team',
            'Archives des tickets' => 'Ticket archives',
            'Aucun ticket dans cette vue.' => 'No tickets in this view.',
            'Modifier les filtres ou choisissez une autre file.' => 'Change the filters or choose another queue.',
            'Rechercher et filtrer' => 'Search and filter',
            'Ajouter un utilisateur' => 'Add user',
            'Importer CSV / Excel' => 'Import CSV / Excel',
            'Utilisateurs' => 'Users',
            'Groupes' => 'Groups',
            'Statistiques' => 'Statistics',
            'Extractions' => 'Exports',
            'Paramètres' => 'Settings',
            'Aucune maintenance planifiée.' => 'No scheduled maintenance.',
            'Enregistrer le mode maintenance' => 'Save maintenance mode',
            'Planifier' => 'Schedule',
            'Bloquer l’accès à TicketFlow pendant cette fenêtre' => 'Block access to TicketFlow during this window',
        ];
        $map = array_merge($map, $legacy);

        // Longest phrases first to avoid partial replacements.
        uksort($map, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));
        $html = strtr($html, $map);

        $regex = [
            '/Créé le\s+/u' => 'Created on ',
            '/Créée le\s+/u' => 'Created on ',
            '/Résolue le\s+/u' => 'Resolved on ',
            '/Échéance\s*:\s*/u' => 'Due: ',
            '/Statut\s*:\s*/u' => 'Status: ',
            '/(\d+)\s+message(?:s)?/u' => '$1 message(s)',
            '/(\d+)\s+fichier(?:s)?/u' => '$1 file(s)',
            '/(\d+)\s+alerte(?:s)?/u' => '$1 alert(s)',
            '/(\d+)\s+notification(?:s)?\s+non\s+lue(?:s)?/u' => '$1 unread notification(s)',
            '/alerte\(s\) actuellement visible\(s\)\./u' => 'alert(s) currently visible.',
            '/fichier(?:s)? maximum/u' => 'files maximum',
            '/caractères maximum/u' => 'characters maximum',
        ];
        foreach ($regex as $pattern => $replacement) {
            $html = preg_replace($pattern, $replacement, $html) ?? $html;
        }

        return $html;
    }
}
