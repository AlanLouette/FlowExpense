# FlowExpense

## Le projet
### Simplifier le suivi des dépenses professionnelles

FlowExpense est une application web conçue pour centraliser les dépenses professionnelles et les notes de frais dans une interface claire. Elle rassemble les montants, les justificatifs et les statuts de traitement afin de faciliter le suivi au quotidien.

Le projet explore un usage individuel pour les indépendants et un fonctionnement en équipe, avec plusieurs utilisateurs et organisations. L’objectif est de rendre les opérations courantes accessibles : retrouver une dépense, consulter son justificatif, suivre son statut et préparer un export.

J’ai travaillé sur l’organisation des écrans, les parcours de saisie et la lisibilité des informations, avec une interface disponible en français, en anglais et en néerlandais.

**Bouton : Essayer la démo**

Texte sous le bouton : Découvrez l’interface avec des données fictives.

### Informations du projet
- Type de projet : Application web de gestion des dépenses
- Mon rôle : Conception de l’interface et développement
- Stack principale : PHP · SQLite · JavaScript · HTML/CSS
- Environnement local : Docker
- Statut : Projet personnel en évolution

## L’application en action
### De la saisie au suivi, au même endroit

Le tableau de bord permet de retrouver les dépenses grâce à la recherche et aux filtres, puis de consulter leurs montants et leurs statuts. Chaque dépense peut contenir plusieurs lignes, des taux de TVA et des justificatifs.

Les exports PDF et Excel facilitent le partage d’une note de frais. Un export comptable rassemble les données CSV et les justificatifs sur une période choisie. Le bilan mensuel complète la liste avec une répartition par catégorie et par fournisseur ou bénéficiaire.

**Visuel conseillé :** capture du tableau de bord avec uniquement des données fictives.

## Processus de réalisation
### Les différentes étapes de la conception

### 1. Définir les usages
Le projet distingue les dépenses professionnelles et les remboursements de frais. Cette distinction permet d’adapter les champs aux informations nécessaires, tout en conservant une liste commune pour le suivi. Les modes solo et équipe permettent d’ajuster le parcours de saisie.

### 2. Organiser une interface lisible
Le tableau de bord donne la priorité à la liste des dépenses. Les filtres restent accessibles, l’export comptable se déplie à la demande et le détail du bilan mensuel apparaît sous la liste. La gestion des utilisateurs regroupe les informations et les actions par compte pour éviter une interface trop chargée.

### 3. Relier les données et les justificatifs
Chaque dépense regroupe ses lignes, ses montants et ses pièces jointes. Les calculs HT, TVA et TTC sont réalisés par ligne. Les exports permettent de retrouver ces informations dans plusieurs formats, selon le besoin de consultation ou de partage.

### 4. Prévoir la récupération et les accès
Les droits d’accès séparent les dépenses des utilisateurs et des organisations. Une corbeille permet de récupérer les dépenses supprimées et un historique consigne les principales opérations. Des sauvegardes complètes réunissent la base de données et les justificatifs pour permettre une restauration.

## Démonstration : fonctionnement proposé

Le bouton ouvre FlowExpense, avec un choix entre connexion à un compte et essai en invité. Le mode invité utilise exclusivement des données fictives et ne donne aucun accès aux comptes, factures ou justificatifs réels.

La démo utilise la véritable application PHP avec un bouton « Essayer en invité ». Chaque visiteur dispose d'un espace temporaire séparé, contenant cinq dépenses et cinq justificatifs PDF fictifs. Il peut utiliser les écrans de saisie et les exports réels, sans accès aux données personnelles ni à l'administration. Les espaces expirent après 24 heures.

Adresse du bouton : **https://alanlouette.be/flowexpense/**. Le paquet complet PHP est préparé et reste à installer sur Hostinger. Consulter COMPLETE-HOSTINGER.md.

## Notes pour l’intégration

Reprendre les blocs de la page BE Pipeline Plugin : titre, présentation à gauche, capture et informations à droite, aperçu en action, puis quatre étapes de conception. Ne pas afficher de nombre d’utilisateurs ou de projets clients sans données vérifiées. Ne pas présenter FlowExpense comme un logiciel de facturation ou de déclaration fiscale.
