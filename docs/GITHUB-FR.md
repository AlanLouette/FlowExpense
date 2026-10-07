# Publier FlowExpense sur GitHub

Le dépôt Git se trouve dans `expense-reports`, pas dans le dossier parent `FlowExpense`. Son adresse distante actuelle est `https://github.com/AlanLouette/FlowExpense.git`.

## Avec GitHub Desktop

1. Ajouter le dépôt local existant en sélectionnant le dossier `expense-reports`.
2. Vérifier les modifications. Ne pas ajouter les bases de données, les justificatifs, les identifiants ou les ZIP personnalisés. Les dossiers `data` et `dist` sont ignorés.
3. Enregistrer les modifications avec un commit, par exemple « Add FlowExpense accounts, Guest demo and Hostinger deployment ».
4. Envoyer les commits au dépôt distant avec Push origin. L'accès au compte GitHub propriétaire est nécessaire.

GitHub conserve le code : cette publication ne modifie pas automatiquement l'installation Hostinger. Les mots de passe et les données restent sur votre hébergement privé.

## Créer un dépôt distinct

Si vous préférez un nouveau dépôt nommé FlowExpense, choisir d'abord sa visibilité (public ou privé), puis créer un dépôt vide sur votre compte GitHub. Modifier l'adresse origin du dépôt local pour utiliser l'adresse de ce nouveau dépôt avant d'envoyer les commits. Ne pas remplacer un dépôt distant contenant déjà du travail et ne pas utiliser de push forcé.

## Données et secrets

Les archives `flowexpense-complete-alan-hostinger.zip` et `flowexpense-complete-alan-exemples-hostinger.zip` contiennent une base personnelle : ne pas les publier comme releases ou pièces jointes. Le fichier d'identifiants temporaires ne doit jamais être publié. Les paquets génériques se reconstruisent depuis les scripts `tools/package-*.py`.

Les secrets saisis dans un commit ancien restent présents dans l'historique même si un fichier est ensuite ignoré. Avant de rendre un dépôt privé public, vérifier aussi son historique.
