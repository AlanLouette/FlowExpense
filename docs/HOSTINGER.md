# Installation sur Hostinger, à côté de WordPress

FlowExpense est une application PHP indépendante. WordPress peut présenter le projet et proposer un lien vers FlowExpense. Docker est utile pour le développement local mais n’est pas nécessaire sur un hébergement PHP compatible.

L’installation n’est pas encore publiée. Les emplacements et fonctions disponibles dépendent du forfait Hostinger. Vérifier `pdo_sqlite`, la version SQLite, les autres extensions et les permissions avant de transférer des données réelles.

## 1. Préparer un sous-domaine

Créer par exemple `flowexpense.votre-domaine.be` dans hPanel et lui attribuer son propre dossier. Ne pas remplacer les fichiers WordPress. Activer le certificat HTTPS et la redirection HTTP → HTTPS.

Documentation Hostinger :
- [Création d’un sous-domaine](https://support.hostinger.com/en/articles/1583405-how-to-create-and-delete-subdomains-in-hostinger)
- [Versions PHP et réglages par sous-domaine](https://www.hostinger.com/support/php/php-versions/)

## 2. Vérifier PHP

Sélectionner PHP 8.2 ou supérieur, sans modifier la version du portfolio si possible. Extensions nécessaires : `pdo_sqlite`, `zip`, `mbstring`, `dom`, `xml`, `xmlreader`, `xmlwriter`, `gd`, `fileinfo`. La sauvegarde SQLite demande SQLite 3.27 ou supérieur.

Depuis un terminal SSH, le contrôle est :

```sh
php tools/check-hosting.php
```

Si le forfait ne fournit pas `pdo_sqlite`, contacter Hostinger avant toute installation. La prise en charge de PDO/MySQL ne confirme pas la présence du pilote SQLite. Ce projet n’a pas été converti vers MySQL.

## 3. Installer les fichiers en séparant code public et données privées

Structure indicative, à adapter au chemin de votre compte :

```text
/home/ACCOUNT/flowexpense/                   # copie complète du projet, hors du site web
/home/ACCOUNT/flowexpense-private/.env
/home/ACCOUNT/flowexpense-private/data/expenses.db
/home/ACCOUNT/flowexpense-private/data/attachments/
/home/ACCOUNT/flowexpense-private/backups/
/home/ACCOUNT/domains/DOMAIN/public_html/flowexpense/  # racine du sous-domaine
```

Décompresser l’archive dans `/home/ACCOUNT/flowexpense/`, hors des dossiers publics. Copier **le contenu de `public/`**, y compris `.htaccess` et `vendor/`, dans la racine du sous-domaine. Le projet privé contient aussi les outils CLI pour les sauvegardes. Les deux copies de code doivent provenir de la même version lors des mises à jour.

Dans le dossier public du sous-domaine, copier `hosting-config.example.php` en `hosting-config.php`, puis y renseigner le vrai chemin absolu de l’environnement privé :

```php
<?php
putenv('FLOWEXPENSE_ENV_FILE=/home/ACCOUNT/flowexpense-private/.env');
```

Les fichiers `.env`, SQLite, justificatifs et sauvegardes ne doivent jamais être placés dans `public_html`. Utiliser les permissions adaptées à l’utilisateur PHP de l’hébergement (dossiers privés 700 et fichiers 600 si PHP s’exécute sous votre compte). Ne pas utiliser 777.

Créer `.env` dans le dossier privé en partant de `.env.example` et renseigner :

```dotenv
APP_ENV=production
APP_HTTPS=1
APP_TIMEZONE=Europe/Brussels
EXPENSE_DB_PATH=/home/ACCOUNT/flowexpense-private/data/expenses.db
BACKUP_DIR=/home/ACCOUNT/flowexpense-private/backups
BACKUP_AUTOMATIC=1
ADMIN_EMAIL=votre-adresse@example.com
ADMIN_NAME=Votre nom
ADMIN_PASSWORD=UN_MOT_DE_PASSE_LONG_ET_UNIQUE_A_CHOISIR
```

Remplacer tous les exemples de chemins, adresse et mot de passe. Ne pas publier ce fichier ni le committer. Un administrateur est créé uniquement pour une nouvelle base ; la configuration ne réinitialise jamais un compte existant.

Pour les outils CLI, rendre ce fichier accessible via la variable `FLOWEXPENSE_ENV_FILE` ou un `.env` à la racine de la copie privée du projet. Une ligne `FLOWEXPENSE_ENV_FILE=...` dans une commande est un chemin privé, pas un secret.

## 4. Choisir les données de l’installation

- **Usage personnel** : changer les mots de passe courts dans l’application locale, réaliser une sauvegarde complète, puis transférer la base et ses justificatifs dans le dossier privé. Les mots de passe de moins de 12 caractères sont refusés en production. Ne jamais déposer la sauvegarde dans le dossier public du site.
- **Démo pour le portfolio** : créer une installation séparée, vide, puis y ajouter seulement des exemples fictifs. Ne pas importer votre base personnelle ni des justificatifs réels. La simple présence d’un mot de passe de connexion ne rend pas une démo anonyme.

La page WordPress peut expliquer le projet, montrer des captures et proposer un bouton « Découvrir l’application ». Choisir une application privée ou une démo séparée avant d’ajouter un accès public.

## 5. Sauvegardes et restauration

L’application crée une sauvegarde au premier accès connecté de chaque journée. Elle conserve les 14 dernières sauvegardes quotidiennes ; les sauvegardes manuelles et celles précédant une restauration sont conservées. Pour sauvegarder même sans visites, configurer une tâche cron quotidienne dans hPanel pour exécuter `tools/backup.php` de la copie privée, avec le même environnement.

[Configurer une tâche cron dans hPanel](https://www.hostinger.com/support/1583465-how-to-set-up-a-cron-job-at-hostinger/).

Si hPanel ne permet pas de fournir une variable d’environnement à une tâche PHP, placer un `.env` dans la racine privée du projet avec les mêmes chemins que l’environnement de l’application web. Le script cron trouve ce `.env` automatiquement.

L’écran **Sauvegardes** permet de télécharger les archives et de restaurer une sauvegarde locale. La restauration exige le mot de passe administrateur et le mot `RESTORE`, remplace toutes les organisations/comptes/justificatifs, conserve une sauvegarde de sécurité et déconnecte les utilisateurs.

Conserver également une copie des archives sur un autre appareil ou stockage : une archive conservée uniquement sur le même hébergement ne protège pas contre sa perte totale.

## 6. Vérification avant de partager le lien

Tester la connexion, une saisie avec TVA, l’import d’un justificatif et les exports. Vérifier que le dossier `vendor/`, `lib/`, les fichiers de configuration et les fichiers privés ne sont pas téléchargeables. Tester une restauration sur une copie de démonstration, jamais comme premier essai sur des données réelles. Les changements de configuration du serveur ne font pas partie de l’audit local du code.
