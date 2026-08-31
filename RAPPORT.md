# Les cinq catalogues, mesurés

Chiffres produits par `outils/rapport.py`, lu directement dans les
bases. Aucun n’est saisi à la main.

## Vue d’ensemble

| Boutique | Source | Lignes CSV | Produits | Rayons | Marques | Fiches complètes | Photos déposées |
|---|---|---:|---:|---:|---:|---:|---:|
| Panier | voila.ca | 8 735 | 8 735 | 25 | 1 460 | 8 735 (100 %) | 0 (0 %) |
| Sillage | perfume.com | 6 958 | 6 958 | 3 | 688 | 6 958 (100 %) | 6 812 (98 %) |
| Marché d’Asie | tntsupermarket.com | 4 271 | 4 271 | 11 | 0 | 4 266 (99,9 %) | 1 505 (35 %) |
| Cueillette | foraged.com | 3 568 | 3 522 | 44 | 0 | 3 479 (99 %) | 0 (0 %) |
| Snack Monde | snacksfrom.com | 1 121 | 1 121 | 7 | 83 | 681 (61 %) | 681 (61 %) |
| **Total** | | | **24 607** | | | **24 119** (98 %) | **8 998** (37 %) |

« Fiche complète » veut dire : un prix ET au moins une photo recensée.
Les autres restent en ligne et consultables — elles sont signalées, pas
cachées, et le filtre « fiches complètes » permet de les écarter.

## Panier — voila.ca

- Sans prix : **0** (0 %).
- Sans aucune photo recensée : **0** (0 %).
- Sans note : **8 735** (100 %).
- Prix : de 0.05 à 56.53, médiane 5.99.
- Principaux rayons : Pantry (1 205), Snacks & Candy (668), Frozen Foods (535), Beverages (480), Fresh Fruits & Vegetables (376).

## Sillage — perfume.com

- Sans prix : **0** (0 %).
- Sans aucune photo recensée : **0** (0 %).
- Sans note : **4 278** (61 %).
- Photos déposées : **6 812** (98 %), 227 Mo sur le disque, 35 ko par photo.
- Prix : de 0.74 à 878.89, médiane 26.88.
- Principaux rayons : Femme (3 223), Homme (2 128), Mixte (1 607).

## Marché d’Asie — tntsupermarket.com

- Sans prix : **4** (0,1 %).
- Sans aucune photo recensée : **1** (0,0 %).
- Sans note : **4 271** (100 %).
- Photos déposées : **1 505** (35 %), 50 Mo sur le disque, 35 ko par photo.
- Prix : de 0.33 à 109.99, médiane 6.68.
- Principaux rayons : Épicerie & sauces (1 867), Laitiers & surgelés (794), Viande & fruits de mer (505), Marque privée (257), Fruits & légumes (251).

## Cueillette — foraged.com

- 3 568 lignes dans le CSV, 3 522 produits retenus : 40 doublons d’URL écartés, 6 ligne(s) sans titre.
- Sans prix : **42** (1 %).
- Sans aucune photo recensée : **1** (0,0 %).
- Sans note : **873** (25 %).
- Prix : de 1.00 à 3500.00, médiane 19.99.
- Principaux rayons : Food (1 049), Wellness (816), Supplies (679), Food Products (537), Seeds (100).

## Snack Monde — snacksfrom.com

- Sans prix : **423** (38 %).
- Sans aucune photo recensée : **440** (39 %).
- Sans note : **1 121** (100 %).
- Photos déposées : **681** (61 %), 25 Mo sur le disque, 38 ko par photo.
- Prix : de 1.99 à 279.99, médiane 20.99.
- Principaux rayons : Beverages (401), Snack (351), Candy (338), Instant (17), Miscellaneous (10).

## Ce que ces chiffres imposent

1. **Les fiches sans prix ne peuvent pas être encaissées.** Le moteur
   refuse leur mise au panier et la fiche le dit, au lieu d’afficher
   un prix de zéro.
2. **Les fiches sans photo ne sont pas vides**, elles portent une
   vignette dessinée à partir du nom du produit. Ce n’est pas une
   photo : c’est un repère, en attendant les vraies.
3. **Une note de 0 avec 0 avis n’est pas une note de zéro** — c’est
   l’absence de note. Elle n’est donc pas affichée.

