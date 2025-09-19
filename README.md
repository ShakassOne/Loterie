# Loterie Manager

Le plugin **Loterie Manager** ajoute une couche de gestion de loteries aux sites WordPress équipés de WooCommerce. Les articles WordPress standards servent d'objets loterie et sont connectés aux produits WooCommerce via une interface simple pour distribuer, suivre et réaffecter les tickets.

## Installation

1. Copiez le dossier `loterie-manager` dans `wp-content/plugins/`.
2. Activez le plugin depuis **Extensions** → **Extensions installées**.
3. Sauvegardez les permaliens depuis **Réglages** → **Permaliens** pour enregistrer l'onglet « Mes tickets ».

## Configuration des loteries

1. Créez ou éditez un article WordPress existant.
2. Renseignez la métabox **Paramètres de loterie** :
   - **Capacité totale de tickets** : nombre maximal de tickets disponibles.
   - **Description du lot** : informations sur le lot mis en jeu.
   - **Date de fin** : échéance de la loterie (affichée sur le site et dans l'espace client).
3. Publiez l'article. Il sera utilisé comme loterie et un overlay de progression sera ajouté automatiquement dans les boucles d’articles.

### Shortcode

Utilisez `[lm_loterie id="123"]` pour afficher un résumé d'une loterie spécifique (remplacez `123` par l’ID de l’article). Sans paramètre `id`, le shortcode utilisera l’article courant dans la boucle.

## Configuration des produits WooCommerce

1. Ouvrez un produit dans WooCommerce.
2. Dans l’onglet **Général**, définissez :
   - **Tickets attribués** : nombre de tickets générés par achat du produit.
   - **Loteries cibles** : une ou plusieurs loteries (articles) pouvant recevoir les tickets.
3. Mettez à jour le produit. Lorsqu'un client ajoute ce produit au panier, un popup lui demandera de choisir la ou les loteries auxquelles ses tickets seront associés.

## Expérience client

- **Popup de sélection** : après avoir cliqué sur « Ajouter au panier », un popup liste les loteries disponibles pour le produit. Le client doit en sélectionner au moins une pour finaliser l’ajout.
- **Overlay sur les articles** : chaque article-loterie affiche une barre de progression indiquant le nombre de tickets vendus par rapport à la capacité maximale ainsi que la date de fin et le lot.
- **Onglet « Mes tickets »** : dans l’espace « Mon compte », un nouvel onglet affiche le total de tickets par loterie. Le client peut réaffecter ses tickets vers une autre loterie éligible.

## Réaffectation des tickets

Depuis l’onglet « Mes tickets », le client sélectionne une nouvelle loterie dans le menu déroulant puis clique sur **Réaffecter**. Les métadonnées de commande sont mises à jour et les compteurs de tickets de chaque loterie sont ajustés en conséquence.

## Assets & Traductions

- `assets/css/frontend.css` : styles de l’overlay, du popup et du tableau « Mes tickets ».
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
