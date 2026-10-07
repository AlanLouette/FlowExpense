# Sécurité de FlowExpense

Cette version protège les mutations par POST et jeton CSRF, contrôle l'organisation et le propriétaire des dépenses, vérifie les fichiers téléversés et limite les redirections à l'application. Les sessions utilisent des cookies HttpOnly/SameSite, une expiration après inactivité et une version invalidée lors des changements sensibles. Les échecs de connexion sont limités par compte et adresse IP.

En production, HTTPS et un stockage privé explicitement configuré sont obligatoires. Un mot de passe de moins de 12 caractères ne permet pas la connexion en production. Le compte administrateur historique avec son mot de passe par défaut est désactivé lorsqu'un autre administrateur actif existe. Les mots de passe ne sont jamais enregistrés dans l'historique.

Les suppressions de dépenses et justificatifs passent par la corbeille. La restauration d'une sauvegarde complète nécessite le mot de passe actuel de l'administrateur et une confirmation explicite. Elle crée une sauvegarde de sécurité et déconnecte tous les utilisateurs. Les sauvegardes contiennent des informations personnelles : conserver base, justificatifs et archives hors de la racine publique et garder une copie externe.

Les requêtes sont sérialisées par un verrou de fichier pour empêcher une écriture concurrente pendant une restauration. Ce choix convient à une petite application ; une forte fréquentation nécessiterait une architecture différente.

Le 6 octobre 2026, l'audit Composer a identifié des avis de sécurité dans les anciennes versions des bibliothèques PDF et Excel. Dompdf a été mis à jour en 3.1.6 et PhpSpreadsheet en 5.10.0, avec résolution des dépendances pour PHP 8.2. Aucun avis connu n'était signalé après mise à jour. Relancer régulièrement `composer audit --locked` dans `public` et mettre les dépendances à jour.

Les tests automatisés utilisent uniquement une base temporaire. Ces vérifications ne constituent pas un test d'intrusion du serveur Hostinger : la configuration HTTPS, les extensions, les permissions et le refus d'accès HTTP aux fichiers privés restent à vérifier après installation.

## Accès invité public

Le mode `FLOWEXPENSE_DEMO=1` n'utilise jamais la base configurée pour l'application privée : avant l'ouverture de SQLite, il choisit un répertoire aléatoire conservé uniquement dans la session PHP. Le stockage doit être dédié et extérieur à la racine publique. Les cookies de démo utilisent un nom distinct et le chemin de l'installation. Chaque base contient un membre sans droits administrateur, et aucune sauvegarde automatique n'est créée. Les espaces sont limités en nombre, en durée et en volume ; le nettoyage coordonne ses verrous avec les requêtes en cours.

Le paquet public ne doit pas recevoir de base réelle ou d'identifiants administrateur. Les limites intégrées ne remplacent pas les protections de trafic du serveur. Le 6 octobre 2026, les 49 vérifications invités ont validé l'isolation entre deux visiteurs, la conservation des données privées, les justificatifs PDF fictifs, les vrais exports, les mutations, CSRF, le refus de l'administration, les limites, la réinitialisation et l'expiration. Un test supplémentaire du ZIP a validé le chemin `/flowexpense/`, la configuration de production et les cookies Secure dans une copie temporaire de la structure d'hébergement.

## Connexion commune aux comptes et invités

Le mode `GUEST_ENABLED=1` conserve la même adresse et une session applicative dédiée. L'entrée Guest efface l'identité réelle de la session et régénère son identifiant avant de sélectionner la base temporaire. Quitter le Guest détruit cette session ; la connexion suivante sélectionne la base réelle et applique ses rôles. Le chemin des bases Guest ne provient jamais d'un paramètre HTTP. Les espaces expirés ne sont pas convertis en comptes réels. Les tests du mode mixte vérifient ces transitions, les privilèges et l'absence de lecture croisée. Le paquet complet a aussi été exécuté dans une structure temporaire `public_html/flowexpense`, avec initialisation d'un compte via un `.env` privé.
