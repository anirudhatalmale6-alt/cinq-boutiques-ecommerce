#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Depose les photos d'une boutique — a lancer par le proprietaire du site.

CE SCRIPT N'EST PAS LANCE PAR MOI, ET CE N'EST PAS UN OUBLI.

Les images referencees dans les CSV sont hebergees par les cinq sites
d'origine et leur appartiennent. Les recopier sur une autre boutique est une
decision qui engage celui qui exploite la boutique, pas celui qui ecrit le
code. Le script existe, il est complet, il fonctionne — vous le lancez sur
les lots dont vous detenez les droits (vos propres photos, celles d'un
fournisseur qui vous les fournit, une banque d'images sous licence).

Deux garde-fous volontaires :
  * rien ne part sans --je-detiens-les-droits ;
  * --limite est obligatoire, pour qu'un lot soit un choix et pas un reflexe.

Usage :
    python3 outils/images.py panier --limite 200 --je-detiens-les-droits

Les fichiers vont dans sites/<boutique>/photos/ et la colonne `image` du
catalogue est mise a jour. Relancer reprend la ou ca s'etait arrete.
"""

import argparse
import io
import json
import os
import sqlite3
import sys
import time
import urllib.error
import urllib.request

RACINE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SITES = os.path.join(RACINE, 'sites')

COTE = 800          # cote maximal, en pixels
QUALITE = 82
PAUSE = 0.4         # secondes entre deux requetes : on ne matraque personne
UA = ('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) '
      'Chrome/124.0 Safari/537.36')


def redimensionne(octets):
	"""Reduit et reencode en JPEG. Sans Pillow, l'octet d'origine est garde
	tel quel — la boutique marchera, les fichiers seront juste plus lourds."""
	try:
		from PIL import Image
	except ImportError:
		return octets, 'brut'
	try:
		im = Image.open(io.BytesIO(octets))
		im.load()
	except Exception:
		return None, 'illisible'
	if im.mode not in ('RGB', 'L'):
		fond = Image.new('RGB', im.size, (255, 255, 255))
		im = im.convert('RGBA')
		fond.paste(im, mask=im.split()[-1])
		im = fond
	else:
		im = im.convert('RGB')
	if max(im.size) > COTE:
		r = COTE / float(max(im.size))
		im = im.resize((max(1, int(im.size[0] * r)), max(1, int(im.size[1] * r))),
		               Image.LANCZOS)
	s = io.BytesIO()
	im.save(s, 'JPEG', quality=QUALITE, optimize=True)
	return s.getvalue(), 'jpeg'


def main():
	ap = argparse.ArgumentParser()
	ap.add_argument('boutique')
	ap.add_argument('--limite', type=int, required=True,
	                help='nombre maximal de fiches traitees pendant cette passe')
	ap.add_argument('--je-detiens-les-droits', action='store_true',
	                dest='droits')
	a = ap.parse_args()

	if not a.droits:
		print(__doc__)
		print('Refus : relancer avec --je-detiens-les-droits.')
		return 2

	dossier = os.path.join(SITES, a.boutique)
	base = os.path.join(dossier, 'donnees', 'catalogue.sqlite')
	photos = os.path.join(dossier, 'photos')
	if not os.path.isfile(base):
		print('Boutique inconnue : %s' % a.boutique)
		return 1
	os.makedirs(photos, exist_ok=True)

	db = sqlite3.connect(base)
	lignes = db.execute(
		'SELECT p.id, s.images FROM produits p JOIN source s ON s.id = p.id'
		' WHERE p.image IS NULL AND p.images_n > 0'
		' ORDER BY p.id LIMIT ?', (a.limite,)).fetchall()

	ok = echec = 0
	for pid, images_json in lignes:
		urls = json.loads(images_json or '[]')
		if not urls:
			continue
		nom = '%d.jpg' % pid
		chemin = os.path.join(photos, nom)
		try:
			req = urllib.request.Request(urls[0], headers={'User-Agent': UA})
			with urllib.request.urlopen(req, timeout=20) as r:
				brut = r.read()
		except (urllib.error.URLError, OSError, ValueError) as e:
			echec += 1
			print('  %-8d echec : %s' % (pid, e))
			time.sleep(PAUSE)
			continue
		octets, mode = redimensionne(brut)
		if octets is None:
			echec += 1
			print('  %-8d image illisible' % pid)
			time.sleep(PAUSE)
			continue
		open(chemin, 'wb').write(octets)
		db.execute('UPDATE produits SET image = ? WHERE id = ?', (nom, pid))
		db.commit()
		ok += 1
		if ok % 25 == 0:
			print('  %d deposees…' % ok)
		time.sleep(PAUSE)

	restant = db.execute('SELECT COUNT(*) FROM produits'
	                     ' WHERE image IS NULL AND images_n > 0').fetchone()[0]
	db.close()
	print('%s : %d deposees, %d echecs, %d fiches encore sans photo'
	      % (a.boutique, ok, echec, restant))
	return 0


if __name__ == '__main__':
	sys.exit(main())
