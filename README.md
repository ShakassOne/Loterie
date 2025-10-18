# Loterie Manager

Le plugin **Loterie Manager** ajoute une couche de gestion de loteries aux sites WordPress équipés de WooCommerce. Les articles WordPress standards servent d'objets loterie et sont connectés aux produits WooCommerce via une interface simple pour distribuer, suivre et réaffecter les tickets.

## Installation

1. Copiez le dossier `loterie-manager` dans `wp-content/plugins/`.
2. Activez le plugin depuis **Extensions** → **Extensions installées**.
3. Sauvegardez les permaliens depuis **Réglages** → **Permaliens** pour enregistrer l'onglet « Mes tickets ».

## Configuration des loteries

1. Créez ou éditez un **article WordPress** (type `post`).
2. Dans la métabox **Paramètres de loterie**, complétez les informations suivantes :
   - **Capacité totale de tickets** : nombre maximal de tickets disponibles.
   - **Description du lot** : informations sur le lot mis en jeu.
   - **Date de fin** : échéance de la loterie (affichée sur le site et dans l'espace client).
3. Publiez l'article pour qu'il devienne disponible comme loterie (les brouillons, articles privés ou planifiés ne seront pas proposés lors de la sélection sur les produits).
4. Vérifiez que la métabox affiche bien les valeurs enregistrées : elles seront utilisées pour alimenter le popup de sélection.

### Shortcode

Utilisez `[lm_loterie id="123"]` pour afficher un résumé d'une loterie spécifique (remplacez `123` par l’ID de l’article). Sans paramètre `id`, le shortcode utilisera l’article courant dans la boucle. Pour afficher automatiquement la loterie la plus avancée (celle ayant vendu le plus de tickets), utilisez `[lm_loterie id="most_advanced"]`.

Le shortcode `[lm_loterie_summary id="123"]` affiche désormais un bandeau coloré indiquant le jour écoulé depuis le lancement, le nombre d’articles encore disponibles et l’objectif total. Parfait pour insérer un rappel visuel percutant dans la description d’un produit.

Le shortcode `[lm_loterie_grid]` affiche automatiquement toutes les loteries publiées sous forme de grille responsive. Il accepte plusieurs attributs optionnels (`posts_per_page`, `orderby`, `order`, `category`, `ids`, `exclude`, `status`, `empty_message`) pour filtrer ou personnaliser la liste rendue. Utilisez également `columns`, `columns_tablet` et `columns_mobile` pour définir respectivement le nombre de cartes par ligne sur desktop, tablette et mobile (par défaut la grille revient à trois colonnes sur tablette et à une colonne sur mobile lorsqu’un nombre de colonnes desktop est fourni).

> ℹ️ **Comment est calculé le « Jour » ?**
>
> Le badge « Jour X » correspond désormais au nombre de jours écoulés depuis la publication de l’article-loterie. Le plugin récupère la date de création de l’article, calcule la différence avec l’heure actuelle (`current_time('timestamp')`), puis ajoute 1 pour afficher « Jour 1 » le jour du lancement, « Jour 2 » le lendemain, etc. Pour remettre le compteur à zéro, modifiez la date de publication de l’article (onglet **Publier** → lien **Modifier** à côté de la date) puis enregistrez : le bandeau reflètera immédiatement cette nouvelle date de départ.

## Configuration des produits WooCommerce

1. Ouvrez un produit dans WooCommerce.
2. Dans l’onglet **Général** :
   - Renseignez **Tickets attribués** (un entier positif ou `0` si vous ne souhaitez pas limiter le nombre de loteries sélectionnables).
   - Sélectionnez **Loteries cibles** : le menu déroulant liste tous les articles publiés. Utilisez `Ctrl`/`Cmd` + clic pour sélectionner plusieurs loteries.
3. Mettez à jour le produit. Le champ **Loteries cibles** doit maintenant afficher toutes les loteries choisies (par exemple trois loteries si vous en avez créé trois). Ce sont uniquement ces loteries qui seront proposées aux clients lors de l'ajout au panier.
4. Sur la fiche produit côté boutique, cliquez sur « Ajouter au panier » : le popup affiche la liste complète des loteries liées. Si toutes vos loteries ne sont pas visibles, retournez sur l’édition du produit et vérifiez qu’elles sont bien sélectionnées et publiées.

## Expérience client

- **Popup de sélection** : après avoir cliqué sur « Ajouter au panier », un popup liste les loteries disponibles pour le produit. Le client doit en sélectionner au moins une pour finaliser l’ajout.
- **Onglet « Mes tickets »** : dans l’espace « Mon compte », un nouvel onglet affiche le total de tickets par loterie. Le client peut réaffecter ses tickets vers une autre loterie éligible.

## Réaffectation des tickets

Depuis l’onglet « Mes tickets », le client sélectionne une nouvelle loterie dans le menu déroulant puis clique sur **Réaffecter**. Les métadonnées de commande sont mises à jour et les compteurs de tickets de chaque loterie sont ajustés en conséquence. Si l’administrateur a désactivé la réaffectation (globalement ou localement), un message clair est affiché et le ticket reste verrouillé.

## Tableau de bord d’administration

Le menu **WinShirt › Loteries** regroupe désormais les outils nécessaires au pilotage :

- **Tableau de bord global** avec progression, chiffre d’affaires associé, alertes et accès direct aux fiches détaillées.
- **Fiche loterie** avec KPIs, liste filtrable des tickets/participants, journal des actions, export officiel pour l’huissier, réglage local de la réaffectation et tirage manuel encadré.
- **Paramètres** pour ajuster la pagination, les statuts de commande exclus du tirage et activer/désactiver la réaffectation automatique.

Chaque action sensible (export, tirage, modification des réglages de réaffectation) est historisée dans un journal consultable depuis la fiche loterie.

## Tirage manuel traçable

Lorsque les critères sont remplis (tickets valides disponibles et loterie prête), un tirage manuel peut être lancé. L’interface impose la saisie d’un aléa public, offre la génération d’un rapport horodaté et verrouille automatiquement les tickets gagnants/suppléants. Le rapport JSON (checksum inclus) est téléchargeable pour constituer un dossier légal.

## Historique des versions

- **1.5.10** : correction du balisage généré par le shortcode `[lm_loterie_grid]` et fiabilisation de l'application des attributs de colonnes personnalisés.
- **1.5.9** : correction de l'interprétation des attributs de colonnes du shortcode `[lm_loterie_grid]` pour garantir une grille multi-colonnes quelle que soit la configuration.
- **1.5.8** : ajout des attributs `columns`, `columns_tablet` et `columns_mobile` pour configurer le nombre de cartes par ligne dans le shortcode `[lm_loterie_grid]`.
- **1.5.7** : nouveau shortcode `[lm_loterie_grid]` pour afficher toutes les loteries en grille responsive.
- **1.5.6** : ajout des miniatures des loteries sélectionnées dans les e-mails de confirmation WooCommerce.
- **1.5.5** : suppression de l'overlay sur les vignettes de loterie pour alléger la présentation des articles.
- **1.5.4** : affichage du nombre total de tickets vendus sur les cartes front-end à la place des participants uniques.
- **1.5.3** : correction de la détection des sélections de loterie lors de l'ajout au panier pour rétablir la création des tickets et amélioration de la compatibilité front-end.
- **1.5.2** : ajout d'un alias `id="most_advanced"` pour afficher automatiquement la loterie ayant vendu le plus de tickets.
- **1.5.1** : correction de l'affichage du chiffre d'affaires pour afficher uniquement la valeur formatée sans balises HTML WooCommerce.
- **1.5.0** : refonte du dashboard admin en thème dark glassmorphism, nouvelles cartes KPI, graphe temporel, flux de tirage manuel en 3 étapes, export huissier contextualisé et timeline modernisée.
- **1.4.0** : ajout du tableau de bord d’administration, du journal des actions, de l’export officiel et du tirage manuel ; amélioration de l’espace client avec filtres et statuts explicites.

## Assets & Traductions

- `assets/css/frontend.css` : styles du popup et du tableau « Mes tickets ».
- `assets/js/frontend.js` : logique du popup de sélection et interactions front-end.
- `languages/loterie-manager-fr_FR.po` : base de traduction française (la langue par défaut du plugin est déjà en français, mais le fichier facilite les personnalisations).

## Développement

Le code principal du plugin se trouve dans `loterie-manager.php` et suit une architecture orientée objet simple. Les clés méta utilisées sont :

| Contexte | Clé méta | Description |
| --- | --- | --- |
| Article | `_lm_ticket_capacity` | Capacité totale de tickets |
| Article | `_lm_lot_description` | Description du lot |
| Article | `_lm_end_date` | Date de fin |
| Article | `_lm_tickets_sold` | Tickets vendus (mis à jour à la validation des commandes et lors des réaffectations) |
| Produit | `_lm_product_ticket_allocation` | Tickets attribués par achat |
| Produit | `_lm_product_target_lotteries` | Loteries éligibles |

## Compatibilité

- Requiert WordPress 6.0 ou supérieur.
- Requiert WooCommerce 6.0 ou supérieur.
- Nécessite un thème compatible WooCommerce pour afficher correctement les champs et l’onglet « Mes tickets ».

## Support

Pour signaler un bug ou proposer une amélioration, ouvrez une issue sur le dépôt du projet ou contactez l’équipe technique.
