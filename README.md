# FlowExpense

Application de suivi des dépenses professionnelles et des remboursements de frais.

## Démarrage avec Docker

Depuis le dossier `Docker` :

```sh
docker compose up -d --build
```

Ouvrir http://localhost:8080. Le code et les données sont montés depuis les dossiers `public` et `data` situés à côté de `Docker`. Les modifications du code sont disponibles en rechargeant la page. Reconstruire l’image après une modification du Dockerfile.

## Utilisation

- Choisir **Français**, **English** ou **Nederlands** à la connexion ou dans la barre supérieure. Le choix est mémorisé dans ce navigateur. Les noms, descriptions et catégories saisis par les utilisateurs gardent leur langue d’origine.
- Dans **Paramètres → Mode d’utilisation**, choisir **Solo** pour ouvrir les nouvelles dépenses en saisie rapide. Le mode **Équipe** ouvre les remboursements avec leurs coordonnées de bénéficiaire. Chaque dépense peut être de l’un ou l’autre type, quel que soit le mode.
- Une **dépense professionnelle** nécessite un fournisseur ; les coordonnées bancaires ne sont pas obligatoires. Un **remboursement** demande le bénéficiaire, son adresse et ses coordonnées bancaires.
- Saisir les prix HT et le taux de TVA de chaque ligne. Les montants sont arrondis au centime par ligne, puis additionnés : HT + TVA = TTC. Les taux sont libres et ne constituent pas un calcul de TVA déductible ni une déclaration fiscale.
- Enregistrer une dépense, puis ajouter ses justificatifs (PDF, CSV, Excel, JPEG, PNG, GIF ou WebP ; 20 Mo maximum par fichier).
- Dans **Export comptable**, choisir une période et un type facultatifs. Le ZIP contient `expenses.csv`, `expense-lines.csv` et les justificatifs référencés. Le CSV utilise UTF-8, le séparateur `;` et le point décimal. Un justificatif manquant fait échouer explicitement l’export.
- Les membres accèdent à leurs propres dépenses ; les administrateurs accèdent aux dépenses de l’organisation sélectionnée.

## Données existantes

Les migrations ajoutent des colonnes sans supprimer les notes existantes. Les anciens montants sont conservés, avec une TVA enregistrée à zéro ; aucune TVA historique n’est déduite automatiquement. Sauvegarder le dossier `data` avant un déploiement. La langue et le type des dépenses ne changent pas les justificatifs existants.

`EXPENSE_DB_PATH` permet de choisir une autre base SQLite. Les justificatifs se trouvent dans le dossier `attachments` adjacent à cette base. Les variables du fichier `.env` sont chargées avant l’ouverture de la base.

## Sauvegardes, suivi et hébergement

La recherche et le récapitulatif mensuel figurent sur la liste des dépenses. La corbeille conserve les dépenses et justificatifs supprimés et permet leur restauration. L'historique commence à l'installation de cette version.

Les administrateurs disposent de sauvegardes ZIP complètes (base SQLite et justificatifs), avec contrôle d'intégrité à la restauration. Une sauvegarde quotidienne est créée à la première visite authentifiée ; une tâche cron permet de la déclencher sans visite. Les 14 dernières sauvegardes quotidiennes sont conservées. Les sauvegardes manuelles et celles réalisées avant une restauration sont conservées séparément. Télécharger régulièrement une copie hors du serveur.

Pour Hostinger, consulter [le guide d'installation privée](docs/HOSTINGER.md). Le paquet se construit avec `python3 tools/package-release.py` ; il exclut les données personnelles et les identifiants. Docker reste utile en local, mais n'est pas requis sur un hébergement PHP compatible.

Consulter [les protections et limites de sécurité](docs/SECURITY.md).

## Vérification automatisée

```sh
python3 tests/integration.py
```

Le test utilise une base et un serveur PHP temporaires, sans modifier la base de l’application. Il vérifie les migrations, les trois langues, les permissions, les calculs, les opérations de saisie, les justificatifs et les exports. PHP 8.2+, les extensions du Dockerfile et les dépendances `public/vendor` sont requis. Node, s’il est disponible, vérifie également la syntaxe des scripts JavaScript rendus.

## Démonstration de la véritable application

Le mode invité est désactivé par défaut. Une installation dédiée avec `FLOWEXPENSE_DEMO=1` et un `DEMO_STORAGE_ROOT` privé attribue à chaque navigateur une base et des justificatifs fictifs séparés. Aucun administrateur n'est créé dans ces bases. Les espaces expirent après 24 heures et les sauvegardes automatiques sont désactivées. Le code de l'application privée reste identique.

Voir [le guide Guest Hostinger](docs/GUEST-HOSTINGER.md). Construire le paquet dédié avec `python3 tools/package-guest-release.py`. Vérifier son fonctionnement avec `python3 tests/demo-integration.py` (stockage temporaire uniquement).

## Comptes réels et Guest à la même adresse

Le mode `GUEST_ENABLED=1` propose sur la même connexion les comptes de la base réelle et des essais Guest séparés. `FLOWEXPENSE_DEMO=1` reste le mode historique exclusivement Guest. Pour la version complète à une seule adresse, construire `python3 tools/package-complete-release.py` et suivre [COMPLETE-HOSTINGER.md](docs/COMPLETE-HOSTINGER.md). Les tests `python3 tests/mixed-integration.py` vérifient les transitions et l'absence de mélange entre les bases.
