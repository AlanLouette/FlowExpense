# Publier la véritable application en mode invité

Adresse : **https://alanlouette.be/flowexpense/**

Le paquet **flowexpense-guest-hostinger.zip** contient la véritable application PHP sur laquelle nous travaillons, ses dépendances PDF/Excel et la configuration du mode invité. Il remplace la petite démonstration HTML. Il ne contient ni base personnelle, ni justificatifs personnels, ni identifiants.

## Installation sur Hostinger

1. Choisir PHP **8.2 ou supérieur** pour le site et vérifier les extensions `pdo_sqlite`, `zip`, `mbstring`, `dom`, `xml`, `xmlreader`, `xmlwriter`, `gd`, `fileinfo`. La disponibilité de SQLite dépend de l'offre ; si `pdo_sqlite` est absent, contacter Hostinger avant de continuer. L'application affichera une extension manquante si nécessaire.
2. Dans le gestionnaire de fichiers, ouvrir **public_html/flowexpense/**. Conserver une copie de l'ancienne démo si souhaité.
3. Téléverser **flowexpense-guest-hostinger.zip** et l'extraire. Hostinger peut créer un sous-dossier lors de l'extraction : déplacer alors **tout son contenu, fichiers cachés compris**, dans `public_html/flowexpense/`.
4. Vérifier que **index.php**, **guest.php**, **hosting-config.php**, **.htaccess**, **lib/** et **vendor/** se trouvent directement dans `public_html/flowexpense/`. Le ZIP ajoute `DirectoryIndex index.php` : si l'ancien `index.html` est encore présent, il n'est pas la page d'accueil de cette version. Pour éviter de le confondre avec la nouvelle version, le déplacer dans votre copie de l'ancienne démo.
5. Vérifier que HTTPS fonctionne. Le paquet utilise des cookies Secure : l'accès en HTTP ne permet pas d'ouvrir l'espace invité.
6. Ouvrir **https://alanlouette.be/flowexpense/** puis cliquer sur **Essayer en invité**. Cinq dépenses et cinq justificatifs PDF fictifs apparaissent.
7. Dans Bricks, garder l'URL **https://alanlouette.be/flowexpense/** pour le bouton **Essayer la démo**. Aucune page WordPress n'est nécessaire à cette adresse.

La configuration fournie crée automatiquement **flowexpense-guest-data/** à côté de `public_html`, hors du site public. La création de ce dossier doit être permise par l'hébergement. Si elle est refusée, créer ce dossier privé depuis le gestionnaire, puis vérifier ses permissions. Pour une autre structure d'hébergement, adapter le chemin dans `hosting-config.php` avant installation ; ne jamais placer les bases ou justificatifs dans `public_html`.

Ne pas importer la base de votre application privée. Ne pas remplacer la configuration invitée par votre `.env` personnel. L'application privée continue à fonctionner séparément.

## Vérifier après publication

- Ouvrir un premier espace invité et ajouter une dépense fictive.
- Dans une fenêtre privée, entrer comme invité : les cinq exemples de base doivent être présents et la dépense ajoutée dans la première fenêtre doit être absente.
- Ouvrir une facture fictive et tester les exports PDF, Excel et ZIP comptable.
- `/flowexpense/users.php`, `/flowexpense/settings.php`, `/flowexpense/organizations.php` et `/flowexpense/backups.php` doivent être refusés à l'invité.
- `/flowexpense/hosting-config.php`, `/flowexpense/lib/` et `/flowexpense/vendor/` doivent être refusés par le serveur. La configuration utilise `.htaccess` ; vérifier qu'il a bien été téléversé.

## Expiration et entretien

Un espace expire **24 heures après sa création**. Ses données sont supprimées au prochain passage dans la démonstration, lorsqu'aucune requête n'utilise cet espace. Le bouton **Réinitialiser la démo** recrée les cinq exemples et efface les essais de l'espace précédent. Une déconnexion ramène à l'accueil ; l'ancien espace devient inaccessible et sera nettoyé à expiration.

Pour supprimer les espaces expirés même en l'absence de visiteurs, ajouter une tâche cron horaire dans Hostinger. Adapter les chemins avec ceux de votre compte :

```sh
php /home/ACCOUNT/domains/alanlouette.be/public_html/flowexpense/lib/demo-cleanup.php /home/ACCOUNT/domains/alanlouette.be/flowexpense-guest-data
```

Ce script est utilisable uniquement en ligne de commande, et son dossier `lib` est interdit en HTTP.

## Limites du mode public

Chaque invité est un membre sans droits administrateur, avec une base et des justificatifs séparés. Il utilise les vrais écrans de dépenses, bénéficiaires, catégories, unités, corbeille et historique ainsi que les vrais exports.

Le mode démo limite les dépenses à 100 par espace, les lignes à 50 par dépense, les opérations POST à 600 par espace, les justificatifs à 2 Mo par fichier / 5 Mo par envoi / 20 Mo par espace. Les créations d'espaces sont limitées à 10 par heure par adresse IP et à 100 espaces présents au total. Des limites supplémentaires de trafic peuvent être configurées chez l'hébergeur si la fréquentation augmente.

Les sauvegardes automatiques sont désactivées dans le mode public. Les extensions et permissions réelles du serveur doivent être vérifiées après installation : les tests locaux ne valident pas le compte Hostinger.

## Mise à jour

La démo utilise le même code PHP que l'application privée. Après une modification, lancer `python3 tools/package-guest-release.py` pour reconstruire le paquet, puis remplacer les fichiers du code sur le serveur. Le dossier privé des espaces invités n'est pas inclus dans le ZIP et ne doit pas être téléversé.
