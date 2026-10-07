# FlowExpense complet : comptes et Guest à la même adresse

**Adresse unique : https://alanlouette.be/flowexpense/**

Le paquet **flowexpense-complete-hostinger.zip** remplace la configuration exclusivement Guest. Il contient la même application PHP, une page de connexion avec identifiant/mot de passe et un bouton **Essayer en invité**. Il ne contient aucune donnée personnelle ni mot de passe.

## Installation et remplacement de la version Guest

1. Vérifier PHP 8.2+ et les extensions `pdo_sqlite`, `zip`, `mbstring`, `dom`, `xml`, `xmlreader`, `xmlwriter`, `gd`, `fileinfo` sur votre offre Hostinger.
2. Garder une copie des fichiers actuels si souhaité, puis extraire le nouveau ZIP directement dans **public_html/flowexpense/**. Si Hostinger crée un sous-dossier, déplacer tout son contenu, fichiers cachés compris, au bon niveau.
3. Remplacer aussi **hosting-config.php** et **.htaccess**. C'est le changement de configuration qui permet les comptes réels. Vérifier que **index.php**, **login.php**, **guest.php**, **lib/** et **vendor/** se trouvent directement dans `flowexpense`.
4. Ouvrir **https://alanlouette.be/flowexpense/**. La page affiche la connexion et **Essayer en invité**. Les invités peuvent déjà essayer l'application ; un message indique si les comptes réels ne sont pas encore configurés.
5. Dans Bricks, conserver l'URL **https://alanlouette.be/flowexpense/** pour le bouton.

La configuration crée deux dossiers séparés, **à côté de public_html**, jamais dedans :

```text
racine-du-site/
├── public_html/flowexpense/        code PHP public
├── flowexpense-private/           comptes réels, base, justificatifs, sauvegardes
│   ├── expenses.db
│   ├── attachments/
│   ├── backups/
│   └── .env
└── flowexpense-guest-data/         espaces invités temporaires
```

HTTPS est obligatoire. La configuration incluse cible la structure `public_html/flowexpense` de Hostinger ; si le stockage privé ne peut pas être créé, adapter les chemins privés avec votre hébergement.

## Créer votre premier compte réel sur le serveur

Si vous commencez sans importer les données locales, créer **flowexpense-private/.env**, hors de `public_html`, avec :

```dotenv
ADMIN_EMAIL=VOTRE_ADRESSE_EMAIL
ADMIN_NAME=VOTRE_NOM
ADMIN_PASSWORD=VOTRE_MOT_DE_PASSE_FORT_DE_12_CARACTERES_MINIMUM
DEFAULT_ORG_NAME=VOTRE_ORGANISATION
BACKUP_AUTOMATIC=1
```

Remplacer les valeurs par vos propres informations. Utiliser un mot de passe long et unique (le générateur du gestionnaire de mots de passe convient). Le premier chargement de la page crée cet administrateur ; connectez-vous ensuite. Les changements futurs de mot de passe ne se font pas en modifiant `.env` : celui-ci ne réinitialise jamais un compte existant.

Après vérification de la première connexion, retirer `ADMIN_PASSWORD` du `.env` : la base conserve uniquement son empreinte. Donner au `.env` des permissions privées adaptées à l'utilisateur PHP, idéalement 600. Ne jamais téléverser ce fichier dans le dossier web. L'administrateur peut créer les autres comptes dans **Administration → Utilisateurs** et choisir leur rôle et leurs organisations. Il n'y a pas d'inscription publique automatique.

## Conserver votre compte et vos données Docker

Le ZIP ne transfère pas votre base actuelle. Pour retrouver votre compte, vos dépenses et justificatifs existants :

1. Faire une sauvegarde complète depuis l'application locale et conserver une copie intacte.
2. Choisir un mot de passe d'au moins 12 caractères pour votre compte local avant le transfert (la production refuse les anciens mots de passe courts).
3. Arrêter les écritures de l'application locale pendant la copie. Transférer **expenses.db** et **attachments/** depuis le dossier `data` local vers **flowexpense-private/**. Si des utilisateurs ont déjà saisi des données sur le serveur, sauvegarder cette base avant de la remplacer : la copie n'effectue pas de fusion.
4. Ne pas copier la base dans `public_html/flowexpense`. Ne pas utiliser une base Guest comme base réelle.
5. Démarrer le site et se connecter avec le compte transféré. Les noms de fichiers des justificatifs doivent être conservés.

La base réelle demeure séparée des espaces Guest, même si les deux accès passent par la même adresse. Votre installation Docker conserve ses données locales : elle n'est pas synchronisée avec Hostinger. Après le transfert, choisir quelle installation utiliser pour les données réelles ; un export de fichiers ne constitue pas une synchronisation.

## Fonctionnement des accès

- **Connexion** : ouvre la base réelle, avec les données et droits du compte. Administrateurs et membres gardent leurs permissions habituelles.
- **Essayer en invité** : attribue une base temporaire propre au navigateur, avec cinq dépenses et cinq justificatifs fictifs. Les menus administrateur sont visibles avec un cadenas ; les endpoints les refusent aussi.
- **Me connecter à mon compte**, dans la démo : quitte le Guest et revient au formulaire de connexion. La connexion suivante consulte uniquement la base réelle.
- Les essais Guest ne sont jamais convertis ou importés automatiquement dans un compte réel.
- Les Guest expirent après 24 heures ; leur nettoyage et les limites d'utilisation restent ceux décrits dans GUEST-HOSTINGER.md. Les sauvegardes automatiques concernent uniquement la base réelle.

## Vérifier sur Hostinger

Dans un navigateur, se connecter au compte réel et ouvrir ses dépenses. Dans une fenêtre privée, essayer en invité : seuls les exemples fictifs doivent être présents. Quitter le Guest avec **Me connecter à mon compte**, puis se connecter : les données du compte doivent réapparaître et les essais Guest doivent être absents.

Vérifier le refus HTTP de `hosting-config.php`, `lib/` et `vendor/`, et l'absence de stockage dans `public_html`. Le code utilise `.htaccess` pour bloquer les fichiers internes. Un test local ne valide pas les extensions ou les règles du compte Hostinger.

Pour le nettoyage horaire facultatif des Guest :

```sh
php /home/ACCOUNT/domains/alanlouette.be/public_html/flowexpense/lib/cli-demo-cleanup.php /home/ACCOUNT/domains/alanlouette.be/flowexpense-guest-data
```

Pour déclencher quotidiennement les sauvegardes réelles sans attendre une visite :

```sh
php /home/ACCOUNT/domains/alanlouette.be/public_html/flowexpense/lib/cli-backup.php
```

Adapter les chemins à ceux de votre compte. Conserver régulièrement une copie des sauvegardes hors de Hostinger.

## Reconstruire le paquet

```sh
python3 tools/package-complete-release.py
```

Cette commande prend le code courant du projet. Elle exclut les bases, les justificatifs, les mots de passe et les configurations locales. La publication n'est pas effectuée automatiquement.
