# Cinq boutiques, un moteur

Cinq sites e-commerce construits à partir des cinq catalogues fournis
(`5 Website Scraped Done.zip`) : **24 607 produits** au total.

Le moteur est écrit **une seule fois**, dans `moteur/`. Chaque boutique est
un dossier autonome produit par `outils/construire.py` : on le dépose dans
`public_html` et il tourne. Corriger le moteur puis relancer le script
corrige les cinq d'un coup.

| Dossier livré | Produits | Langue | Devise | Source des données |
|---|---:|---|---|---|
| `sites/panier` | 8 735 | fr | CAD | voila.ca |
| `sites/sillage` | 6 958 | en | USD | perfume.com |
| `sites/marche-asie` | 4 271 | fr | CAD | tntsupermarket.com |
| `sites/cueillette` | 3 522 | en | USD | foraged.com |
| `sites/snackmonde` | 1 121 | en | USD | snacksfrom.com |

Les chiffres détaillés, mesurés dans les bases et non saisis à la main,
sont dans **[RAPPORT.md](RAPPORT.md)**.

---

## Les noms sont provisoires

« Panier », « Sillage », « Cueillette », « Marché d'Asie », « Snack Monde »
sont des noms de travail, écrits par moi. **Le nom du site d'origine n'est
jamais repris** : une boutique qui porte le nom d'un autre commerce se fait
passer pour lui. Chaque pied de page le dit tant que le vrai nom n'est pas
choisi ; il suffit de changer `'nom'` dans `config.php` et d'enlever
`'nom_provisoire'`.

Les cinq sites portent aussi `noindex, nofollow` : tant que le contenu
définitif n'est pas en place, ils ne doivent pas être indexés.

---

## Ce qui est publié, et ce qui ne l'est pas

Les CSV contiennent trois choses de nature différente.

**Publié — les faits.** Nom du produit, marque, catégorie, poids, unité,
code SKU, code-barres, origine, ingrédients, prix. Ce sont des données
factuelles ; elles font le squelette d'une boutique et personne n'en revendique
la propriété.

**Conservé mais jamais affiché — les textes des sites d'origine.** Les
descriptions rédigées par les cinq commerçants sont chargées dans une table
`source` que les pages publiques **ne lisent jamais**. Elles servent de pièce
justificative, pas de contenu. Recopier mot pour mot le catalogue d'un
concurrent, c'est ce qui se règle par lettre d'avocat — et sur `foraged.com`
il y a en plus **381 producteurs tiers nommés**, avec leurs notes et leurs
ventes, dont la reprise laisserait croire à un partenariat qui n'existe pas.

**Les photos** sont hébergées par les cinq sites d'origine. Le moteur ne
pointe **jamais** vers leurs serveurs : soit la photo a été déposée dans
`photos/` et elle est servie depuis la boutique, soit la fiche affiche une
vignette dessinée à partir du nom du produit. Le téléchargement a été demandé
par le client le 31/08 pour `sillage`, `snackmonde` et `marche-asie` ; les
deux garde-fous (`--je-detiens-les-droits` et `--limite`) restent en place,
parce que la décision engage l'exploitant du site, pas l'auteur du code.

**Les photos ne sont jamais poussées dans le dépôt GitHub** — voir
`.gitignore`.

---

## Les photos

Deux outils font le même travail, à deux endroits différents.

| | `outils/images.py` | `photos.php` |
|---|---|---|
| tourne sur | mon poste | **le serveur de la boutique** |
| il faut | Python + Pillow | PHP + GD (présents chez Hostinger) |
| ensuite | il faut téléverser les fichiers | rien à téléverser |

**`photos.php` est celui à utiliser en production.** Le serveur va chercher
les photos lui-même : plus de 300 Mo à envoyer à la main par le gestionnaire
de fichiers, ça n'a pas de sens quand SSH ne répond pas.

1. Dans `config.php` : `'cle_photos' => 'un-mot-de-passe-a-vous'`.
2. Ouvrir `/photos.php?cle=un-mot-de-passe-a-vous`. La page enchaîne les lots
   toute seule et affiche l'avancement.
3. On peut fermer l'onglet : rouvrir la même adresse reprend au bon endroit.
4. **À la fin, remettre `'cle_photos' => ''` ou supprimer le fichier.** Un
   script qui écrit sur le disque n'a rien à faire en ligne une fois son
   travail terminé. Sans clé, il refuse de s'exécuter.

### Trois choses mesurées sur les sources

**`tntsupermarket.com` répond 403** à une requête sans les en-têtes d'un
navigateur (`Accept`, `Sec-Fetch-*`). Avec, 200.

**Et il sert de l'AVIF** dès que `Accept` l'annonce — un format que ni Pillow
ni GD ne lisent ici. 4 images sur 14 arrivaient illisibles, sans qu'aucune
erreur réseau ne le signale. On ne demande donc pas d'AVIF : on y perd en
bande passante, on y gagne une image.

**`perfume.com` : les CSV portent l'URL de la vignette**, `.../sku/small/`,
250×250, 4 ko — floue sur une fiche produit. La même image existe en
`.../sku/large/`, 750×750. On demande `large`, on retombe sur `small` si elle
n'existe pas.

### Deux règles de traitement

**On ne grossit jamais une image.** Agrandir une vignette de 250 px ne lui
ajoute aucun détail : ça la rend floue et ça triple le fichier.

**Une petite image déjà compressée est gardée telle quelle.** La réencoder la
ferait grossir tout en lui coûtant une génération de perte — mesuré sur
`perfume.com` : 5 366 o à l'entrée, 6 114 o à la sortie.

Côté maximal 600 px, JPEG qualité 80 progressif. La fiche produit affiche
520 px : au-delà, on paie du poids que personne ne voit.

---

## Déployer une boutique

1. Envoyer le contenu de `sites/<boutique>/` dans `public_html` (ou dans le
   sous-dossier du domaine).
2. Vérifier que **PHP 7.4 ou plus** et **pdo_sqlite** sont actifs. C'est le
   cas par défaut chez Hostinger.
3. Ouvrir le site. Si les adresses `/rayon/...` renvoient une erreur 404,
   `mod_rewrite` est désactivé : mettre `'reecriture' => false` dans
   `config.php` et tout repasse en `index.php?r=...`, sans rien perdre.

Aucune base MySQL, aucun identifiant, aucune installation. Le catalogue est
un fichier SQLite dans `donnees/`, protégé par un `.htaccess` qui interdit
son téléchargement — **à vérifier après l'envoi**, en tentant d'ouvrir
`/donnees/catalogue.sqlite` dans un navigateur : la réponse doit être 403.

---

## Ce que le moteur fait

- **Accueil** : rayons avec leur nombre de produits, sélection.
- **Rayon** : filtres sous-rayon, marque et prix, cinq tris, pagination.
- **Recherche** : tous les mots doivent apparaître — « huile olive » ne
  ramène pas tout ce qui contient « huile ».
- **Fiche produit** : tableau de caractéristiques, ingrédients, suggestions.
- **Panier** : session, quantités bornées à 99, jeton anti-CSRF.
- **Commande** : formulaire validé côté serveur, enregistrement en base,
  référence rendue au client.

**Les prix ne transitent jamais par le formulaire.** Ils sont relus en base à
chaque calcul, sinon n'importe qui poste le prix de son choix.

**Le paiement n'est pas branché**, et la page le dit au lieu de le simuler :
le prestataire et le pays d'exploitation ne sont pas choisis. La commande est
enregistrée dans `donnees/commandes.sqlite`, séparée du catalogue, qui reste
en lecture seule.

Tout marche sans JavaScript : chaque bouton est un lien ou un formulaire.

---

## Trois décisions qui viennent des données

Elles sont mesurées, pas supposées — le détail est dans `RAPPORT.md`.

**Un prix absent n'est pas un prix nul.** 423 produits de `snacksfrom` n'ont
pas de prix, et trois girolles de `foraged` sont à 0,00 $. Les uns comme les
autres sont traités comme « prix sur demande » : la fiche reste consultable,
la mise au panier est refusée. Afficher 0,00 $ aurait été une promesse de
vente à zéro.

**Une note de 0 avec 0 avis n'est pas une note de zéro.** Sur `perfume.com`,
4 278 produits sur 6 958 sont dans ce cas — 61 % du catalogue. Les afficher
aurait mis cinq étoiles vides et « 0,00 » sur trois fiches sur cinq.

**Un rayon vide de sens se nomme.** 3 462 produits de `voila.ca` n'ont aucune
catégorie à la source. Les répartir au jugé aurait été inventer ; les laisser
sans rayon les aurait rendus introuvables. Ils sont dans « Rayon non
renseigné », qui est toujours affiché **en dernier**, quel que soit son
nombre de produits.

---

## Les fichiers sources sont en Windows-1252

Pas en UTF-8. Lus comme de l'UTF-8, ils cassent dès la première ligne
accentuée ; lus en « UTF-8 avec remplacement », ils rendent
« Canard laqu? de P?kin » et la donnée est perdue pour de bon.

`outils/importer.py` décode en `cp1252`, **à un seul endroit**, et écrit la
base en UTF-8. Rien n'est perdu : 30 594 caractères accentués récupérés sur
le seul fichier T&T.

---

## Regénérer

```
python3 outils/importer.py     # les cinq CSV  ->  donnees/*.sqlite
python3 outils/construire.py   # le moteur + les bases  ->  sites/*
python3 outils/rapport.py      # RAPPORT.md, mesuré dans les bases
python3 outils/apercus.py      # les captures de apercus/
```

Les photos, une boutique à la fois, par lots :

```
python3 outils/images.py sillage --limite 500 --je-detiens-les-droits
```

`construire.py` **préserve** `donnees/commandes.sqlite` et le contenu de
`photos/`, puis **raccroche** la colonne `image` en relisant le dossier des
photos. Sans ce raccrochage, le catalogue neuf reposé par-dessus rendait
invisibles des photos pourtant bien présentes sur le disque : une simple
correction du moteur les perdait toutes, en silence.

Pour essayer une boutique en local, sans Apache :

```
php -S 127.0.0.1:8800 -t sites/panier outils/routeur-dev.php
```

`apercus.py` lance Chromium avec `--disable-lcd-text` : sans ce drapeau,
l'anticrénelage sous-pixel pose des franges colorées sur chaque lettre, et
une capture censée montrer les couleurs du site en contient d'autres.

---

## Ce qu'il reste à décider

- **Le pays d'exploitation.** Il commande le prestataire de paiement, la
  taxe, les frais de port et les mentions légales. Les frais de livraison
  actuels sont des valeurs de remplissage, marquées comme telles dans
  `config.php`.
- **Le nom et le domaine de chaque boutique.**
- **Les photos** : le téléchargement est lancé sur `sillage`, `snackmonde` et
  `marche-asie` à votre demande. Reste à décider pour `panier` et
  `cueillette` — et à confirmer que vous avez le droit de publier celles-là.
  Sur `cueillette`, les photos sont celles de **381 producteurs tiers nommés**
  dans les données : ce ne sont pas les photos d'un commerçant unique.
- **Les descriptions produit** : à réécrire, elles ne peuvent pas être celles
  des sites d'origine.
