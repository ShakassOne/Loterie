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
   - **Statut affiché** : sélection manuelle du badge visible côté site (automatique par défaut, ou bien *En cours*, *À venir*, *Annulées*, *Suspendues*, *Terminées*).
   - **Description du lot** : informations sur le lot mis en jeu.
   - **Date de début** : moment à partir duquel la loterie est considérée comme démarrée (le compteur de jours reste à zéro avant cette date).
   - **Date de fin** : échéance de la loterie (affichée sur le site et dans l'espace client).
   - **URL du bouton « Participer »** : lien personnalisé à ouvrir lorsqu'un visiteur clique sur le bouton d'appel à l'action (laisser vide pour utiliser le permalien de l’article).
   - **URL du bouton « Participer » (miniature)** : lien dédié pour les vignettes/front-end, pratique pour renvoyer directement vers une fiche produit ou une page spéciale (laisse vide pour utiliser l’URL précédente ou le permalien).
   - **Masquer le bouton pour les loteries à venir** : option pour afficher une carte informative sans CTA tant que la date de début n’est pas atteinte.
3. Publiez l'article pour qu'il devienne disponible comme loterie (les brouillons, articles privés ou planifiés ne seront pas proposés lors de la sélection sur les produits).
4. Vérifiez que la métabox affiche bien les valeurs enregistrées : elles seront utilisées pour alimenter le popup de sélection.

### Shortcode

Utilisez `[lm_loterie id="123"]` pour afficher un résumé d'une loterie spécifique (remplacez `123` par l’ID de l’article). Sans paramètre `id`, le shortcode affiche désormais un catalogue interactif avec filtres dynamiques (statut, catégorie, recherche et tri) qui s'actualise via AJAX. Le paramètre optionnel `sort` permet de définir le tri par défaut (`date_desc`, `date_asc`, `title_asc`, `title_desc`). Pour afficher automatiquement la loterie la plus avancée (celle ayant vendu le plus de tickets), utilisez `[lm_loterie id="most_advanced"]`.

Le shortcode `[lm_loterie_summary id="123"]` affiche désormais un bandeau coloré indiquant le jour écoulé depuis le lancement, le nombre d’articles encore disponibles et l’objectif total. Parfait pour insérer un rappel visuel percutant dans la description d’un produit.

Le shortcode `[lm_loterie_grid]` affiche automatiquement toutes les loteries publiées sous forme de grille responsive. Il accepte plusieurs attributs optionnels (`posts_per_page`, `orderby`, `order`, `category`, `ids`, `exclude`, `status`, `empty_message`) pour filtrer ou personnaliser la liste rendue. Utilisez également `columns`, `columns_tablet` et `columns_mobile` pour définir respectivement le nombre de cartes par ligne sur desktop, tablette et mobile (par défaut la grille revient à trois colonnes sur tablette et à une colonne sur mobile lorsqu’un nombre de colonnes desktop est fourni). La grille embarque désormais le même panneau de filtres dynamiques que `[lm_loterie]` (statut, catégorie, recherche, tri) tout en conservant les colonnes configurées et l’ordre manuel défini via l’attribut `ids`.

Le shortcode `[lm_loterie_sold id="123"]` renvoie uniquement le nombre total de tickets vendus pour la loterie visée, sans balisage HTML additionnel, idéal pour l’intégrer dans une phrase ou un compteur personnalisé.

Le shortcode `[lm_loterie_remaining id="123"]` affiche uniquement le temps restant avant la fin de la loterie, avec un texte paramétrable lorsque la date de fin est absente ou que la loterie est terminée (attributs `no_date_text` et `ended_text`).

> ℹ️ **Comment est calculé le « Jour » ?**
>
> Le badge « Jour X » correspond désormais au nombre de jours écoulés depuis la **date de début** renseignée dans la métabox. Tant que cette date n’est pas atteinte, le compteur reste bloqué sur « Jour 0 ». Si aucun début n’est défini, le plugin se rabat sur la date de publication de l’article, calcule la différence avec l’heure actuelle (`current_time('timestamp')`), puis ajoute 1 pour afficher « Jour 1 » le jour du lancement, « Jour 2 » le lendemain, etc. Pour remettre le compteur à zéro, ajustez la date de début (ou, à défaut, la date de publication) puis enregistrez : le bandeau reflètera immédiatement cette nouvelle date de départ.

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

- **1.3.22** : bouton admin pour supprimer les tickets d’une commande test sans toucher au paiement et exclusion des commandes marquées des compteurs.
- **1.3.21** : nouveau shortcode `[lm_loterie_remaining]` pour afficher le compte à rebours textuel d’une loterie.
- **1.3.20** : URL dédiée pour le bouton des miniatures et option pour masquer le CTA des loteries à venir.
- **1.3.19** : amélioration de la configuration front-end et ajustements mineurs de stabilité.
- **1.3.18** : ajout d’un champ d’URL pour personnaliser la destination du bouton « Participer » dans la métabox des loteries.
- **1.3.17** : correction de la persistance du commutateur « Toujours afficher cet attribut » et maintien des options de variation actives côté boutique après modification d’un attribut.
- **1.3.16** : ajout d’un commutateur « Toujours afficher cet attribut » pour les attributs globaux WooCommerce et forçage de l’affichage des options de variation associées côté boutique.
- **1.3.13** : ajout d'un champ de date de début pour différer automatiquement le démarrage des compteurs et du statut des loteries à venir.
- **1.3.12** : ajout d’une option de réinitialisation forcée (dashboard et fiche loterie) avec avertissements dédiés lorsque des tickets valides subsistent.
- **1.3.11** : ajout d’un bouton de réinitialisation par loterie (dashboard et fiche détaillée) avec messages contextualisés et blocage automatique en présence de tickets valides.
- **1.3.10** : bouton d’administration pour réinitialiser les compteurs de tickets avec contrôle des ventes actives.
- **1.3.9** : annulation automatique des tickets lorsque la commande passe sur un statut exclu (annulée, remboursée, etc.).
- **1.3.8** : filtres dynamiques et AJAX pour `[lm_loterie_grid]`, conservation des colonnes configurées et boutons de filtres harmonisés.
- **1.3.7** : ajout d'un champ de statut manuel pour les loteries (En cours, À venir, Annulées, Suspendues, Terminées) avec badges et filtres front-end mis à jour.
- **1.3.6** : ajout du shortcode `[lm_loterie_sold]` pour afficher uniquement le total de tickets vendus.
- **1.3.4** : optimisation de l'affichage mobile du shortcode `[lm_loterie_grid]` (marges réduites, typographie ajustée et mise en page resserrée).
- **1.3.3** : filtres dynamiques pour le shortcode `[lm_loterie]`, rechargement AJAX et nouveaux styles cohérents avec la grille.
- **1.3.2** : correction du balisage généré par le shortcode `[lm_loterie_grid]` et fiabilisation de l'application des attributs de colonnes personnalisés.
- **1.3.1** : correction de l'interprétation des attributs de colonnes du shortcode `[lm_loterie_grid]` pour garantir une grille multi-colonnes quelle que soit la configuration.
- **1.3.0** : ajout des attributs `columns`, `columns_tablet` et `columns_mobile` pour configurer le nombre de cartes par ligne dans le shortcode `[lm_loterie_grid]`.
- **1.2.9** : nouveau shortcode `[lm_loterie_grid]` pour afficher toutes les loteries en grille responsive.
- **1.2.8** : ajout des miniatures des loteries sélectionnées dans les e-mails de confirmation WooCommerce.
- **1.2.7** : suppression de l'overlay sur les vignettes de loterie pour alléger la présentation des articles.
- **1.2.6** : affichage du nombre total de tickets vendus sur les cartes front-end à la place des participants uniques.
- **1.2.5** : correction de la détection des sélections de loterie lors de l'ajout au panier pour rétablir la création des tickets et amélioration de la compatibilité front-end.
- **1.2.4** : ajout d'un alias `id="most_advanced"` pour afficher automatiquement la loterie ayant vendu le plus de tickets.
- **1.2.3** : correction de l'affichage du chiffre d'affaires pour afficher uniquement la valeur formatée sans balises HTML WooCommerce.
- **1.2.2** : refonte du dashboard admin en thème dark glassmorphism, nouvelles cartes KPI, graphe temporel, flux de tirage manuel en 3 étapes, export huissier contextualisé et timeline modernisée.
- **1.2.1** : ajout du tableau de bord d’administration, du journal des actions, de l’export officiel et du tirage manuel ; amélioration de l’espace client avec filtres et statuts explicites.

## Assets & Traductions

- `assets/css/frontend.css` : styles du popup et du tableau « Mes tickets ».
- `assets/js/frontend.js` : logique du popup de sélection et interactions front-end.
- `assets/js/loterie-filters.js` : gestion des filtres dynamiques du shortcode `[lm_loterie]` et rafraîchissement AJAX.
- `languages/loterie-manager-fr_FR.po` : base de traduction française (la langue par défaut du plugin est déjà en français, mais le fichier facilite les personnalisations).

## Développement

Le code principal du plugin se trouve dans `loterie-manager.php` et suit une architecture orientée objet simple. Les clés méta utilisées sont :

| Contexte | Clé méta | Description |
| --- | --- | --- |
| Article | `_lm_ticket_capacity` | Capacité totale de tickets |
| Article | `_lm_loterie_status` | Statut affiché manuellement sur le site |
| Article | `_lm_lot_description` | Description du lot |
| Article | `_lm_start_date` | Date de début |
| Article | `_lm_end_date` | Date de fin |
| Article | `_lm_participation_url` | URL personnalisée du bouton « Participer » |
| Article | `_lm_card_participation_url` | URL personnalisée du bouton « Participer » sur les miniatures |
| Article | `_lm_hide_upcoming_cta` | Masquage du bouton « Participer » tant que la loterie est à venir |
| Article | `_lm_tickets_sold` | Tickets vendus (mis à jour à la validation des commandes et lors des réaffectations) |
| Produit | `_lm_product_ticket_allocation` | Tickets attribués par achat |
| Produit | `_lm_product_target_lotteries` | Loteries éligibles |

## Compatibilité

- Requiert WordPress 6.0 ou supérieur.
- Requiert WooCommerce 6.0 ou supérieur.
- Nécessite un thème compatible WooCommerce pour afficher correctement les champs et l’onglet « Mes tickets ».

## Support

Pour signaler un bug ou proposer une amélioration, ouvrez une issue sur le dépôt du projet ou contactez l’équipe technique.
