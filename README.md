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
3. Publiez l'article pour qu'il devienne disponible comme loterie (les brouillons, articles privés ou planifiés ne seront pas proposés lors de la sélection sur les produits).
4. Vérifiez que la métabox affiche bien les valeurs enregistrées : elles seront utilisées pour alimenter le popup de sélection.

## Lexique des shortcodes

Tous les shortcodes du module partagent la même logique d’internationalisation et respectent les permissions WooCommerce/WordPress. Les variantes ci-dessous couvrent les usages côté boutique et les intégrations éditoriales.

### `[lm_loterie]` — Carte unique ou catalogue filtrable

Affiche soit la carte complète d’une loterie précise, soit (en l’absence d’`id`) un catalogue dynamique doté de filtres AJAX pour explorer les tirages en cours, à venir ou terminés.

**Attributs**

| Attribut | Description | Valeurs acceptées | Valeur par défaut |
| --- | --- | --- | --- |
| `id` | Identifiant de l’article utilisé comme loterie. Utilisez la valeur spéciale `most_advanced` pour cibler automatiquement la loterie ayant vendu le plus de tickets. Quand l’attribut est omis, le shortcode rend la liste filtrable. | ID numérique de loterie, `most_advanced` ou vide | `''` |
| `sort` | Tri appliqué au chargement initial du catalogue interactif. | `date_desc`, `date_asc`, `title_asc`, `title_desc` | `date_desc` |

**À savoir**

- Le catalogue affiche d’emblée toutes les loteries publiées puis laisse l’internaute filtrer par statut (`En cours`, `À venir`, `Annulées`, `Suspendues`, `Terminées`), par catégorie, par recherche textuelle et par tri.
- Lorsqu’un `id` est fourni, les filtres sont masqués et seule la carte détaillée est affichée.
- Le module gère automatiquement la récupération du contexte (tickets vendus, capacités, progression) et désactive l’affichage si l’identifiant est invalide.

**Exemples rapides**

- `[lm_loterie id="123"]` pour intégrer la carte de la loterie #123 dans une page.
- `[lm_loterie id="most_advanced"]` afin de toujours afficher la loterie la plus avancée sans mise à jour manuelle.
- `[lm_loterie sort="title_asc"]` pour charger par défaut la liste triée de A à Z tout en conservant les filtres en front.

### `[lm_loterie_grid]` — Grille responsive filtrable

Construit une grille responsive de loteries avec les mêmes filtres AJAX que `[lm_loterie]`. Les colonnes s’adaptent selon les attributs fournis et la grille respecte l’ordre manuel si des identifiants précis sont imposés.

**Attributs**

| Attribut | Description | Valeurs acceptées | Valeur par défaut |
| --- | --- | --- | --- |
| `posts_per_page` | Limite le nombre de cartes chargées initialement. La valeur `-1` (ou `0`) affiche toutes les loteries correspondantes. | Entier (ex. `6`, `12`, `-1`) | `-1` |
| `orderby` | Champ de tri initial des résultats. | `date`, `title` | `date` |
| `order` | Sens du tri correspondant à `orderby`. | `ASC`, `DESC` | `DESC` |
| `category` | Filtre sur une ou plusieurs catégories d’articles (slugs séparés par virgule ou espace). | Slugs de catégories | `''` |
| `ids` | Restreint la grille à une liste d’identifiants précis ; la grille respecte l’ordre fourni. | Liste d’IDs numériques | `''` |
| `exclude` | Exclut certains identifiants de la sélection. | Liste d’IDs numériques | `''` |
| `status` | Filtre les statuts WordPress des articles chargés (ex. `publish`, `draft`). | Liste de statuts WP séparés par virgule | `publish` |
| `empty_message` | Message affiché lorsqu’aucune loterie ne correspond aux critères (laisser vide pour masquer le texte). | Chaîne libre | `Aucune loterie disponible pour le moment.` |
| `columns` | Nombre de colonnes desktop. | Entier ≥ 0 | `0` (auto) |
| `columns_tablet` | Nombre de colonnes tablette. Si omis mais `columns` défini, la valeur est ramenée au minimum entre `columns` et `3`. | Entier ≥ 0 | `0` (auto) |
| `columns_mobile` | Nombre de colonnes mobile. Lorsqu’une valeur desktop ou tablette est fournie, un fallback à `1` est appliqué. | Entier ≥ 0 | `0` (auto) |

**À savoir**

- Les filtres AJAX côté client (statut, catégorie, recherche, tri) sont identiques à ceux du shortcode `[lm_loterie]` et permettent aux visiteurs d’afficher uniquement les loteries à venir ou terminées.
- Lorsque `ids` est renseigné, la sélection est limitée à ces loteries et l’ordre reste identique à celui de la liste fournie (`post__in`).
- Les attributs de colonnes contrôlent le rendu CSS ; laissez-les vides pour conserver le comportement responsive natif.

**Exemples rapides**

- `[lm_loterie_grid posts_per_page="6" columns="3" columns_tablet="2" columns_mobile="1"]` pour une grille paginée visuellement équilibrée.
- `[lm_loterie_grid ids="42, 51, 78" empty_message="Revenez bientôt pour de nouvelles loteries !"]` pour mettre en avant une sélection éditoriale dans un ordre précis.

### `[lm_loterie_summary]` — Bandeau de progression

Affiche un bandeau synthétique (jour en cours, tickets restants, objectif) idéal dans une fiche produit ou en haut d’un article de blog.

**Attributs**

| Attribut | Description | Valeurs acceptées | Valeur par défaut |
| --- | --- | --- | --- |
| `id` | Identifiant de la loterie à résumer. Si l’attribut est omis, le shortcode utilise l’ID du contenu courant. | ID numérique ou vide | ID du contenu en cours |

**Exemples rapides**

- `[lm_loterie_summary id="123"]` pour rappeler la progression de la loterie #123 dans un bloc promotionnel.
- `[lm_loterie_summary]` directement dans le contenu d’une loterie pour afficher automatiquement le bandeau correspondant.

### `[lm_loterie_sold]` — Compteur brut de tickets vendus

Renvoie uniquement le nombre de tickets vendus, sans balises supplémentaires, pour l’intégrer dans une phrase ou un design personnalisé.

**Attributs**

| Attribut | Description | Valeurs acceptées | Valeur par défaut |
| --- | --- | --- | --- |
| `id` | Identifiant de la loterie ciblée. À défaut, l’ID du contenu courant est utilisé. | ID numérique ou vide | ID du contenu en cours |

**Exemples rapides**

- `Il reste seulement [lm_loterie_sold id="123"] tickets vendus sur notre grand tirage !`
- `[lm_loterie_sold]` dans une loterie pour afficher le total courant dans la description.

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

- **1.3.18** : ajout d’un lexique détaillé des shortcodes (usages, attributs et exemples) pour faciliter l’intégration des loteries.
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
| Article | `_lm_tickets_sold` | Tickets vendus (mis à jour à la validation des commandes et lors des réaffectations) |
| Produit | `_lm_product_ticket_allocation` | Tickets attribués par achat |
| Produit | `_lm_product_target_lotteries` | Loteries éligibles |

## Compatibilité

- Requiert WordPress 6.0 ou supérieur.
- Requiert WooCommerce 6.0 ou supérieur.
- Nécessite un thème compatible WooCommerce pour afficher correctement les champs et l’onglet « Mes tickets ».

## Support

Pour signaler un bug ou proposer une amélioration, ouvrez une issue sur le dépôt du projet ou contactez l’équipe technique.
