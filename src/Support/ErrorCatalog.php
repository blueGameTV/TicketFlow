<?php
declare(strict_types=1);

namespace App\Support;

use Throwable;

final class ErrorCatalog
{
    private const ERRORS = [
        'TE-001' => ['area'=>'Système','title'=>'Configuration TicketFlow manquante','public'=>'TicketFlow ne peut pas charger sa configuration.','admin'=>'Vérifier config/config.php et ses permissions.'],
        'TE-002' => ['area'=>'Base de données','title'=>'Connexion à la base de données impossible','public'=>'TicketFlow ne peut pas accéder à sa base de données.','admin'=>'Vérifier MariaDB/MySQL, hôte, port, base et identifiants SQL.'],
        'TE-003' => ['area'=>'Base de données','title'=>'Erreur de requête SQL','public'=>'Une opération de base de données a échoué.','admin'=>'Consulter technical-errors.log et vérifier le schéma SQL.'],
        'TE-004' => ['area'=>'Base de données','title'=>'Migration ou schéma incompatible','public'=>'La structure de la base TicketFlow doit être vérifiée.','admin'=>'Vérifier les migrations et la version du schéma.'],
        'TE-005' => ['area'=>'Système','title'=>'Écriture sur le stockage impossible','public'=>'TicketFlow ne peut pas écrire un fichier nécessaire.','admin'=>'Vérifier storage/, logs/, cache/, sessions/ et uploads/.'],
        'TE-101' => ['area'=>'Sécurité','title'=>'Session ou CSRF invalide','public'=>'Votre session ou le formulaire n’est plus valide.','admin'=>'Vérifier les sessions PHP et la validation CSRF.'],
        'TE-102' => ['area'=>'Authentification','title'=>'Authentification impossible','public'=>'La connexion n’a pas pu être vérifiée.','admin'=>'Vérifier le compte et les journaux d’authentification.'],
        'TE-103' => ['area'=>'Authentification','title'=>'État de compte incompatible','public'=>'Le compte ne permet pas cette opération.','admin'=>'Vérifier activation, verrouillage et changement forcé du mot de passe.'],
        'TE-104' => ['area'=>'Sécurité','title'=>'Permission insuffisante','public'=>'Vous n’êtes pas autorisé à effectuer cette opération.','admin'=>'Vérifier le rôle et le périmètre utilisateur.'],
        'TE-105' => ['area'=>'Authentification','title'=>'Réinitialisation du mot de passe impossible','public'=>'La procédure de mot de passe n’a pas pu être terminée.','admin'=>'Vérifier jeton, expiration et état du compte.'],
        'TE-201' => ['area'=>'Tickets','title'=>'Création de ticket impossible','public'=>'Le ticket n’a pas pu être créé.','admin'=>'Vérifier TicketService, type, catégorie, priorité et base de données.'],
        'TE-202' => ['area'=>'Tickets','title'=>'Modification de ticket impossible','public'=>'Le ticket n’a pas pu être modifié.','admin'=>'Vérifier workflow, statut et permissions.'],
        'TE-203' => ['area'=>'Tickets','title'=>'Affectation ou transfert impossible','public'=>'Le ticket n’a pas pu être affecté ou transféré.','admin'=>'Vérifier le Support IT cible et les règles d’affectation.'],
        'TE-204' => ['area'=>'Tickets','title'=>'Message de ticket impossible','public'=>'Le message n’a pas pu être ajouté.','admin'=>'Vérifier ticket_messages et les permissions.'],
        'TE-205' => ['area'=>'Workflow','title'=>'Validation Manager impossible','public'=>'La validation Manager n’a pas pu être traitée.','admin'=>'Vérifier N+1, Manager final, étape et manager_approvals.'],
        'TE-206' => ['area'=>'Workflow','title'=>'Résolution impossible','public'=>'La résolution n’a pas pu être enregistrée.','admin'=>'Vérifier le statut et la confirmation utilisateur.'],
        'TE-301' => ['area'=>'Administration','title'=>'Opération utilisateur impossible','public'=>'L’opération sur le compte utilisateur a échoué.','admin'=>'Vérifier les dépendances du compte et les contraintes SQL.'],
        'TE-302' => ['area'=>'Administration','title'=>'Opération groupe impossible','public'=>'L’opération sur le groupe a échoué.','admin'=>'Vérifier le Manager, les membres et les contraintes.'],
        'TE-303' => ['area'=>'Administration','title'=>'Import utilisateurs impossible','public'=>'Le fichier utilisateurs n’a pas pu être importé.','admin'=>'Vérifier CSV/XLSX, colonnes et données.'],
        'TE-401' => ['area'=>'Fichiers','title'=>'Upload impossible','public'=>'Le fichier n’a pas pu être envoyé.','admin'=>'Vérifier taille, MIME et storage/uploads/.'],
        'TE-402' => ['area'=>'Fichiers','title'=>'Pièce jointe indisponible','public'=>'La pièce jointe ne peut pas être ouverte.','admin'=>'Vérifier le fichier, son enregistrement et les permissions.'],
        'TE-403' => ['area'=>'Exports','title'=>'Export Excel impossible','public'=>'Le fichier Excel n’a pas pu être généré.','admin'=>'Vérifier ExcelExportService et les sorties PHP parasites.'],
        'TE-501' => ['area'=>'E-mail','title'=>'Envoi e-mail impossible','public'=>'L’e-mail TicketFlow n’a pas pu être envoyé.','admin'=>'Vérifier SMTP, file e-mail et mail.log.'],
        'TE-502' => ['area'=>'Notifications','title'=>'Notification impossible','public'=>'La notification n’a pas pu être créée.','admin'=>'Vérifier NotificationService et les préférences.'],
        'TE-601' => ['area'=>'Service','title'=>'Alerte de service impossible','public'=>'L’alerte n’a pas pu être traitée.','admin'=>'Vérifier ServiceAlertService et les dates.'],
        'TE-602' => ['area'=>'Service','title'=>'Maintenance impossible','public'=>'La maintenance n’a pas pu être traitée.','admin'=>'Vérifier MaintenanceService et les dates.'],
        'TE-701' => ['area'=>'API','title'=>'Erreur API TicketFlow','public'=>'La requête TicketFlow n’a pas pu être traitée.','admin'=>'Vérifier endpoint API, session et technical-errors.log.'],
        'TE-702' => ['area'=>'Temps réel','title'=>'Synchronisation temps réel impossible','public'=>'La synchronisation en direct a rencontré un problème.','admin'=>'Vérifier les endpoints live-* et les réponses HTTP.'],
        'TE-801' => ['area'=>'Installation','title'=>'Installation ou setup impossible','public'=>'L’installation TicketFlow n’a pas pu être terminée.','admin'=>'Vérifier setup.php, prérequis, base et permissions.'],
        'TE-802' => ['area'=>'Mise à jour','title'=>'Mise à jour impossible','public'=>'La mise à jour TicketFlow n’a pas pu être terminée.','admin'=>'Vérifier update.sh, migrations et sauvegarde.'],
        'TE-999' => ['area'=>'Système','title'=>'Erreur technique inattendue','public'=>'Une erreur technique inattendue est survenue.','admin'=>'Rechercher TE-999 et sa référence dans technical-errors.log.'],
    ];

    public static function all(): array { return self::ERRORS; }
    public static function get(string $code): array { return self::ERRORS[$code] ?? self::ERRORS['TE-999']; }

    public static function log(string $code, Throwable|string|null $error = null, array $context = []): string
    {
        if (!isset(self::ERRORS[$code])) $code = 'TE-999';
        try { $reference = strtoupper(bin2hex(random_bytes(4))); }
        catch (Throwable) { $reference = strtoupper(substr(sha1(uniqid('', true)), 0, 8)); }

        $dir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($dir)) @mkdir($dir, 0770, true);
        $message = '';
        if ($error instanceof Throwable) {
            $message = $error->getMessage();
            $context['exception'] = $error::class;
            $context['file'] = $error->getFile();
            $context['line'] = $error->getLine();
            $context['trace'] = $error->getTraceAsString();
        } elseif (is_string($error)) $message = $error;

        @file_put_contents($dir . '/technical-errors.log', json_encode([
            'timestamp'=>date('c'),'code'=>$code,'reference'=>$reference,'message'=>$message,'context'=>$context
        ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND|LOCK_EX);
        return $reference;
    }

    public static function publicText(string $code, string $reference): string
    {
        return self::get($code)['public'] . ' Code : ' . $code . ' · Référence : ' . $reference;
    }
}
