#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Lit les cinq CSV et ecrit un catalogue SQLite par boutique.

Les fichiers sources sont en WINDOWS-1252, pas en UTF-8. Decodes en utf-8 ils
levent une erreur des la premiere ligne accentuee ; decodes en « utf-8 avec
remplacement » ils rendent « Canard laqu? de P?kin » et la donnee est perdue
pour de bon. On decode donc en cp1252, une seule fois, ici — et plus jamais
ailleurs : la base est ecrite en UTF-8 et tout le reste de la chaine y lit.

Rien n'est invente. Un champ absent a la source reste NULL en base et le site
ecrit « non renseigne ». Les descriptions et les URL d'images des sites
d'origine sont conservees dans une table a part (`source`), qui n'est jamais
lue par les pages publiques : elles servent de piece justificative, pas de
contenu publie.
"""

import csv
import io
import json
import os
import re
import sqlite3
import sys
import unicodedata

RACINE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CSVS = os.path.join(RACINE, 'csv')
SORTIE = os.path.join(RACINE, 'donnees')

SCHEMA = """
PRAGMA journal_mode = DELETE;

CREATE TABLE produits (
	id            INTEGER PRIMARY KEY,
	ref           TEXT    NOT NULL UNIQUE,
	titre         TEXT    NOT NULL,
	cherche       TEXT    NOT NULL,          -- titre + marque, sans accents, minuscules
	marque        TEXT,
	rayon         TEXT    NOT NULL,
	rayon_slug    TEXT    NOT NULL,
	sous_rayon    TEXT,
	sous_slug     TEXT,
	sous_rayon2   TEXT,
	prix_cents    INTEGER,                   -- NULL = prix non renseigne a la source
	unite         TEXT,
	poids         TEXT,
	sku           TEXT,
	upc           TEXT,
	origine       TEXT,
	vendeur       TEXT,
	note          REAL,
	avis          INTEGER,
	ingredients   TEXT,
	specs         TEXT,                      -- JSON {libelle: valeur}
	image         TEXT,                      -- image locale si telechargee, sinon NULL
	images_n      INTEGER NOT NULL DEFAULT 0,
	complet       INTEGER NOT NULL DEFAULT 1 -- 0 = pas vendable en l'etat (voir rapport)
);

CREATE INDEX i_rayon   ON produits(rayon_slug);
CREATE INDEX i_sous    ON produits(sous_slug);
CREATE INDEX i_marque  ON produits(marque);
CREATE INDEX i_prix    ON produits(prix_cents);
CREATE INDEX i_cherche ON produits(cherche);

-- Les rayons sont calcules a l'import, pas a chaque requete : la boutique
-- affiche le meme menu sur toutes ses pages.
CREATE TABLE rayons (
	slug TEXT PRIMARY KEY, nom TEXT NOT NULL, n INTEGER NOT NULL,
	-- 1 = rayon technique (le fourre-tout) : toujours affiche en dernier,
	-- meme s'il contient le plus de produits.
	ordre INTEGER NOT NULL DEFAULT 0
);
-- Un meme sous-rayon (« Sauces ») peut exister sous deux rayons : la cle est
-- donc le couple, pas le slug seul.
CREATE TABLE sous_rayons (
	rayon_slug TEXT NOT NULL, slug TEXT NOT NULL, nom TEXT NOT NULL,
	n INTEGER NOT NULL, PRIMARY KEY (rayon_slug, slug)
);
CREATE INDEX i_sous_rayon ON sous_rayons(rayon_slug);

-- Piece justificative. Jamais affichee par les pages publiques.
CREATE TABLE source (
	id          INTEGER PRIMARY KEY,
	url         TEXT,
	description TEXT,
	images      TEXT                         -- JSON [url, ...]
);

CREATE TABLE meta (cle TEXT PRIMARY KEY, valeur TEXT);
"""


def sans_accents(s):
	d = unicodedata.normalize('NFKD', s)
	return ''.join(c for c in d if not unicodedata.combining(c)).lower()


# Les produits sans categorie a la source vont dans ce rayon. Il est nomme,
# pas invente : il dit exactement ce qu'il est, et il se range en dernier.
RAYON_INCONNU = 'Rayon non renseigné'


def slugue(s):
	"""« Fruits & legumes » -> « fruits-legumes ». Deux rayons differents ne
	doivent jamais tomber sur le meme slug : l'appelant verifie."""
	s = sans_accents(s or '')
	s = re.sub(r'[^a-z0-9]+', '-', s).strip('-')
	return s or 'divers'


def prix_cents(v):
	"""« $7.99 », « 26.99 », «  » -> 799, 2699, None. Jamais 0 par defaut :
	0 voudrait dire gratuit, et un prix absent n'est pas un prix nul."""
	if v is None:
		return None
	v = v.strip().replace('$', '').replace(' ', '').replace(',', '')
	if not v:
		return None
	m = re.match(r'^(\d+(?:\.\d+)?)$', v)
	if not m:
		return None
	cents = int(round(float(m.group(1)) * 100))
	# Un prix a 0,00 dans un catalogue n'est pas un produit gratuit : c'est
	# un prix qui n'a pas ete capture. Trois girolles a 0 $ sur foraged le
	# montrent bien. On le traite comme absent, pas comme nul.
	return cents if cents > 0 else None


def txt(v):
	if v is None:
		return None
	v = ' '.join(v.split())
	return v or None


def titre_propre(t):
	"""foraged.com ecrit tout en CAPITALES. Un catalogue en capitales est
	illisible en liste ; on repasse en casse de phrase en gardant les sigles
	de deux lettres ou moins et les mots deja mixtes."""
	if not t or t != t.upper():
		return t
	mots = []
	for m in t.split(' '):
		if len(m) <= 2 or not m.isalpha():
			mots.append(m)
		else:
			mots.append(m.capitalize())
	return ' '.join(mots)


def lire(nom):
	chemin = os.path.join(CSVS, nom)
	brut = open(chemin, 'rb').read()
	texte = brut.decode('cp1252')          # <- le seul endroit ou on decode
	return list(csv.DictReader(io.StringIO(texte)))


def images_de(ligne, colonnes):
	out = []
	for c in colonnes:
		u = txt(ligne.get(c))
		if u:
			out.append(u)
	return out


# --- Un adaptateur par source --------------------------------------------
#
# Chacun rend un dict au schema commun. Tout ce qui n'existe pas a la source
# vaut None ; aucun adaptateur ne fabrique de valeur de remplacement.

def a_foraged(r):
	imgs = images_de(r, ['Image 1', 'Image 2', 'Image 3', 'Image 4', 'Image 5'])
	specs = {}
	if txt(r.get('Weights')):
		specs['Conditionnements'] = txt(r['Weights'])
	if txt(r.get('Joined_Date')):
		specs['Producteur inscrit'] = txt(r['Joined_Date']).replace('JOINED ', '')
	if txt(r.get('Sales')):
		specs['Ventes a la source'] = txt(r['Sales'])
	return dict(
		ref=r['Link'],
		titre=titre_propre(txt(r.get('Title')) or ''),
		marque=None,
		rayon=titre_propre(txt(r.get('Category')) or 'Divers'),
		sous_rayon=titre_propre(txt(r.get('Sub_Category'))),
		sous_rayon2=None,
		prix_cents=prix_cents(r.get('Price')),
		unite=txt(r.get('Unit')),
		poids=txt(r.get('Weights')),
		sku=None, upc=None,
		origine=txt(r.get('Location')),
		vendeur=txt(r.get('Vendor_Name')),
		note=flottant(r.get('Rating')),
		avis=entier(r.get('Reviews')),
		ingredients=None,
		specs=specs,
		images=imgs,
		url=txt(r.get('Link')),
		description=txt(r.get('Description')),
	)


def a_perfume(r):
	genre = {"Women's Fragrances": 'Femme',
	         "Men's Fragrances": 'Homme',
	         'Unisex Fragrances': 'Mixte'}.get(txt(r.get("Men's / Women's")) or '', 'Divers')
	img = txt(r.get('Image Link'))
	return dict(
		ref=r['Product Link'],
		titre=txt(r.get('Title')) or '',
		marque=txt(r.get('Category')),          # « Category » est la marque
		rayon=genre,
		sous_rayon=txt(r.get('Category')),
		sous_rayon2=None,
		prix_cents=prix_cents(r.get('Price')),
		unite=None, poids=None, sku=None, upc=None,
		origine=None, vendeur=None,
		note=flottant(r.get('Rating')),
		avis=entier(r.get('Review')),
		ingredients=None,
		specs={},
		images=[img] if img else [],
		url=txt(r.get('Product Link')),
		description=None,
	)


def a_snacksfrom(r):
	imgs = images_de(r, ['Image 1', 'Image 2', 'Image 3'])
	# 423 lignes portent le LOGO du site en guise de photo produit. Une image
	# qui est le logo n'est pas une image : on la retire plutot que de laisser
	# le meme visuel sur des centaines de fiches.
	imgs = [u for u in imgs if 'logo-full-transparent' not in u]
	specs = {}
	for src, lib in (('Case Contents', 'Contenu du carton'),
	                 ('Individual Unit', 'Unite')):
		if txt(r.get(src)):
			specs[lib] = txt(r[src])
	upc = txt(r.get('UPC'))
	if upc and upc.upper() == 'N/A':
		upc = None
	return dict(
		ref=r['Links'],
		titre=txt(r.get('Title')) or '',
		marque=txt(r.get('Brand')),
		rayon=txt(r.get('Category')) or 'Divers',
		sous_rayon=txt(r.get('Origin')),
		sous_rayon2=None,
		prix_cents=prix_cents(r.get('Price')),
		unite=txt(r.get('Individual Unit')),
		poids=None,
		sku=txt(r.get('SKU')),
		upc=upc,
		origine=txt(r.get('Origin')),
		vendeur=None, note=None, avis=None,
		ingredients=None,
		specs=specs,
		images=imgs,
		url=txt(r.get('Links')),
		description=txt(r.get('Description')),
	)


def a_tnt(r):
	imgs = images_de(r, ['Image %d' % i for i in range(1, 11)])
	return dict(
		ref=r['URL'],
		titre=txt(r.get('Title')) or '',
		marque=None,
		rayon=txt(r.get('Category')) or 'Divers',
		sous_rayon=None,
		sous_rayon2=None,
		prix_cents=prix_cents(r.get('Price')),
		unite=None,
		poids=txt(r.get('Weight')),
		sku=txt(r.get('SKU')),
		upc=None, origine=None, vendeur=None, note=None, avis=None,
		ingredients=None,
		specs={},
		images=imgs,
		url=txt(r.get('URL')),
		description=txt(r.get('Description')),
	)


def a_voila(r):
	imgs = images_de(r, ['Image_%d' % i for i in range(1, 11)])
	return dict(
		ref=r['URL'],
		titre=txt(r.get('Title')) or '',
		marque=txt(r.get('Brand')),
		# 3 462 produits sur 8 735 n'ont aucune categorie a la source. Les
		# ranger ailleurs les rendrait faux ; les laisser vides les rendrait
		# introuvables. Ils vont dans un rayon qui dit ce qu'il est.
		rayon=txt(r.get('Category')) or RAYON_INCONNU,
		sous_rayon=txt(r.get('Sub_Category_1')),
		sous_rayon2=txt(r.get('Sub_Category_2')),
		prix_cents=prix_cents(r.get('Price')),
		unite=txt(r.get('Unit')),
		poids=txt(r.get('Unit')),
		sku=None, upc=None, origine=None, vendeur=None, note=None, avis=None,
		ingredients=txt(r.get('Ingredients')),
		specs={},
		images=imgs,
		url=txt(r.get('URL')),
		description=txt(r.get('Description')),
	)


def flottant(v):
	try:
		return float((v or '').strip())
	except ValueError:
		return None


def entier(v):
	try:
		return int(float((v or '').strip()))
	except ValueError:
		return None


BOUTIQUES = [
	('cueillette', 'foraged.csv', a_foraged, 'USD'),
	('sillage', 'perfume.csv', a_perfume, 'USD'),
	('snackmonde', 'snacksfrom.csv', a_snacksfrom, 'USD'),
	('marche-asie', 'tntsupermarket.csv', a_tnt, 'CAD'),
	('panier', 'Voila.csv', a_voila, 'CAD'),
]


def construire(slug, fichier, adaptateur, devise):
	lignes = lire(fichier)
	chemin = os.path.join(SORTIE, slug + '.sqlite')
	if os.path.exists(chemin):
		os.remove(chemin)
	db = sqlite3.connect(chemin)
	db.executescript(SCHEMA)

	vus = set()
	noms_rayon = {}
	noms_sous = {}
	collisions = []
	doublons = 0
	sans_titre = 0
	sans_prix = 0
	sans_image = 0
	n = 0
	for ligne in lignes:
		p = adaptateur(ligne)
		if not p['titre']:
			sans_titre += 1
			continue
		if p['ref'] in vus:
			doublons += 1
			continue
		vus.add(p['ref'])
		# Une note de 0 avec 0 avis n'est pas une note de zero : c'est
		# l'absence de note. Mesure faite sur perfume.com : 4 278 lignes sur
		# 6 958 sont dans ce cas. Les afficher mettrait cinq etoiles vides et
		# « 0,00 » sur 61 % du catalogue.
		if not p['note'] and not p['avis']:
			p['note'] = None
			p['avis'] = None
		elif not p['avis']:
			p['avis'] = None       # foraged : note du producteur, sans avis

		if p['prix_cents'] is None:
			sans_prix += 1
		if not p['images']:
			sans_image += 1
		# « Vendable en l'etat » = un prix ET une photo. C'est le critere du
		# rapport, pas une suppression : le produit reste en base, la boutique
		# le range dans « fiches a completer ».
		complet = 1 if (p['prix_cents'] is not None and p['images']) else 0
		n += 1
		r_slug = slugue(p['rayon'])
		s_slug = slugue(p['sous_rayon']) if p['sous_rayon'] else None
		# Un slug est une cle : si deux libelles differents s'y ecrasent, la
		# navigation melange deux rayons sans rien signaler. On verifie.
		if noms_rayon.setdefault(r_slug, p['rayon']) != p['rayon']:
			collisions.append((r_slug, noms_rayon[r_slug], p['rayon']))
		if s_slug:
			cle = (r_slug, s_slug)
			if noms_sous.setdefault(cle, p['sous_rayon']) != p['sous_rayon']:
				collisions.append((s_slug, noms_sous[cle], p['sous_rayon']))
		db.execute(
			'INSERT INTO produits (id, ref, titre, cherche, marque, rayon,'
			' rayon_slug, sous_rayon, sous_slug, sous_rayon2, prix_cents,'
			' unite, poids, sku, upc,'
			' origine, vendeur, note, avis, ingredients, specs, image,'
			' images_n, complet)'
			' VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
			(n, p['ref'], p['titre'],
			 sans_accents(p['titre'] + ' ' + (p['marque'] or '')),
			 p['marque'], p['rayon'], r_slug, p['sous_rayon'], s_slug,
			 p['sous_rayon2'],
			 p['prix_cents'], p['unite'], p['poids'], p['sku'], p['upc'],
			 p['origine'], p['vendeur'], p['note'], p['avis'],
			 p['ingredients'], json.dumps(p['specs'], ensure_ascii=False),
			 None, len(p['images']), complet))
		db.execute('INSERT INTO source (id, url, description, images)'
		           ' VALUES (?,?,?,?)',
		           (n, p['url'], p['description'],
		            json.dumps(p['images'], ensure_ascii=False)))

	db.execute('INSERT INTO rayons (slug, nom, n, ordre)'
	           ' SELECT rayon_slug, MIN(rayon), COUNT(*),'
	           ' CASE WHEN MIN(rayon) = ? THEN 1 ELSE 0 END'
	           ' FROM produits GROUP BY rayon_slug', (RAYON_INCONNU,))
	db.execute('INSERT INTO sous_rayons (rayon_slug, slug, nom, n)'
	           ' SELECT rayon_slug, sous_slug, MIN(sous_rayon), COUNT(*)'
	           ' FROM produits WHERE sous_slug IS NOT NULL'
	           ' GROUP BY rayon_slug, sous_slug')

	rayons = db.execute('SELECT COUNT(DISTINCT rayon) FROM produits').fetchone()[0]
	marques = db.execute('SELECT COUNT(DISTINCT marque) FROM produits'
	                     ' WHERE marque IS NOT NULL').fetchone()[0]
	complets = db.execute('SELECT COUNT(*) FROM produits WHERE complet=1').fetchone()[0]
	for cle, val in (('slug', slug), ('source', fichier), ('devise', devise),
	                 ('produits', n), ('rayons', rayons), ('marques', marques),
	                 ('complets', complets), ('sans_prix', sans_prix),
	                 ('sans_image', sans_image), ('doublons_ecartes', doublons),
	                 ('lignes_source', len(lignes))):
		db.execute('INSERT INTO meta (cle, valeur) VALUES (?,?)', (cle, str(val)))
	db.commit()
	db.execute('VACUUM')
	db.close()
	return dict(slug=slug, lignes=len(lignes), produits=n, doublons=doublons,
	            sans_titre=sans_titre, sans_prix=sans_prix,
	            sans_image=sans_image, complets=complets, rayons=rayons,
	            marques=marques, octets=os.path.getsize(chemin),
	            collisions=collisions)


def main():
	os.makedirs(SORTIE, exist_ok=True)
	total = 0
	print('%-13s %7s %7s %6s %6s %8s %8s %7s %9s' % (
		'boutique', 'lignes', 'prod.', 'doubl', 'rayons', 'sans prix',
		'sans img', 'vendabl', 'base'))
	for slug, fichier, adaptateur, devise in BOUTIQUES:
		r = construire(slug, fichier, adaptateur, devise)
		total += r['produits']
		print('%-13s %7d %7d %6d %6d %8d %8d %7d %9d o' % (
			r['slug'], r['lignes'], r['produits'], r['doublons'], r['rayons'],
			r['sans_prix'], r['sans_image'], r['complets'], r['octets']))
		for slg, a, b in r['collisions'][:5]:
			print('   ! collision de slug « %s » : « %s » et « %s »' % (slg, a, b))
	print('total produits : %d' % total)
	return 0


if __name__ == '__main__':
	sys.exit(main())
