# Installer la démonstration publique

Adresse prévue : https://alanlouette.be/flowexpense/

Cette démonstration est autonome : trois fichiers statiques, sans PHP, base de données, compte ni accès à l'application privée. Les essais sont conservés dans le stockage local du navigateur. Le bouton de réinitialisation remplace ces essais par les exemples initiaux.

## Dans le gestionnaire de fichiers Hostinger

1. Ouvrir le dossier racine du site alanlouette.be (généralement `public_html`, celui qui contient les fichiers WordPress).
2. Créer un dossier `flowexpense`. Si un dossier de ce nom existe déjà, vérifier son contenu avant de le modifier.
3. Téléverser `flowexpense-demo.zip` dans ce dossier et l'extraire.
4. Vérifier que `index.html`, `demo.css` et `demo.js` se trouvent directement dans `public_html/flowexpense/`, sans dossier intermédiaire.
5. Supprimer uniquement le ZIP téléversé après extraction si souhaité. Ne modifier aucun fichier WordPress.
6. Ouvrir https://alanlouette.be/flowexpense/ et essayer la recherche et l'ajout d'une dépense fictive.

Si WordPress affiche une page différente à cette adresse, vérifier qu'aucune page WordPress n'utilise déjà le slug `flowexpense`. Le dossier réel doit contenir son `index.html`. Vérifier aussi les caches du site et les règles de redirection personnalisées.

## Bouton dans Bricks

- Texte : **Essayer la démo**
- Lien personnalisé / URL : **https://alanlouette.be/flowexpense/**
- Ouvrir dans un nouvel onglet : selon votre préférence (recommandé pour garder la présentation ouverte).
- Si un champ de relation est proposé pour le nouvel onglet : `noopener`.

La démo n'est pas encore publiée : le ZIP doit être déposé sur Hostinger avant que le bouton fonctionne.

## Vérifications réalisées localement

Affichage du tableau, ajout d'un exemple (100 € HT à 21 % → 121 € TTC), conservation après rechargement, recherche et réinitialisation. Syntaxe JavaScript vérifiée. La démonstration présente un aperçu simplifié et n'inclut pas les comptes, justificatifs, PDF, Excel, sauvegardes ou administration.
