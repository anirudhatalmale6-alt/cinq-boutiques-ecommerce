#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Depose les photos d'une boutique dans sites/<boutique>/photos/.

AUTORISATION. Le telechargement a ete demande par le client le 31/08 pour
perfume, snacksfrom et T&T. La decision d'heberger des photos qui viennent
d'un autre site engage l'exploitant de la boutique, pas l'auteur du code :
c'est pour ca que les deux garde-fous restent en place.

  * rien ne part sans --je-detiens-les-droits ;
  * --limite est obligatoire, pour qu'un lot soit un choix et pas un reflexe.

Usage :
    python3 outils/images.py sillage --limite 500 --je-detiens-les-droits

Relancer reprend ou ca s'etait arrete : seules les fiches encore sans photo
sont traitees. Les photos ne partent JAMAIS dans le depot GitHub.

Trois choses mesurees sur les sources, qui expliquent le code plus bas.

1. perfume.com — les CSV contiennent l'URL de la VIGNETTE (.../sku/small/,
   250x250, 4 ko). Affichee sur une fiche produit, elle est floue. La meme
   image existe en .../sku/large/ (750x750). On demande large, on retombe
   sur small si elle n'existe pas.

2. tntsupermarket.com — repond 403 a une requete qui n'a pas l'entete d'un
   navigateur (Accept, Sec-Fetch-*). Avec les bons entetes, 200.

3. tntsupermarket.com sert de l'AVIF des que Accept l'annonce, et Pillow ne
   sait pas le lire : 4 images sur 14 arrivaient illisibles. On ne demande
   donc PAS d'AVIF. On y perd en bande passante, on y gagne une image.
"""

import argparse
import io
import json
import os
import queue
import sqlite3
import sys
import threading
import time
import urllib.error
import urllib.request

RACINE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SITES = os.path.join(RACINE, 'sites')

COTE = 600          # cote maximal en pixels : la fiche produit affiche 520
QUALITE = 80
FILS = 4            # 4 connexions, comme un navigateur — pas davantage
PAUSE = 0.25        # seconde, par fil

UA = ('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) '
      'Chrome/124.0 Safari/537.36')

# Pas d'AVIF dans Accept : voir le point 3 de l'entete.
ACCEPT = 'image/webp,image/apng,image/*,*/*;q=0.8'

REFERER = {
	'sillage':     'https://www.perfume.com/',
	'snackmonde':  'https://snacksfrom.com/',
	'marche-asie': 'https://www.tntsupermarket.com/',
	'panier':      'https://voila.ca/',
	'cueillette':  'https://foraged.com/',
}


def variantes(boutique, url):
	"""Les URL a essayer, dans l'ordre. La premiere qui repond gagne."""
	if boutique == 'sillage' and '/sku/small/' in url:
		return [url.replace('/sku/small/', '/sku/large/'), url]
	return [url]


def telecharge(boutique, url):
	"""Rend (octets, url_utilisee) ou leve la derniere erreur rencontree."""
	entetes = {
		'User-Agent': UA,
		'Accept': ACCEPT,
		'Accept-Language': 'en-CA,en;q=0.9',
		'Referer': REFERER.get(boutique, ''),
		'Sec-Fetch-Dest': 'image',
		'Sec-Fetch-Mode': 'no-cors',
		'Sec-Fetch-Site': 'same-origin',
	}
	derniere = None
	for u in variantes(boutique, url):
		for essai in (1, 2):
			try:
				req = urllib.request.Request(u, headers=entetes)
				with urllib.request.urlopen(req, timeout=25) as r:
					return r.read(), u
			except urllib.error.HTTPError as e:
				derniere = e
				if e.code in (403, 404, 410):
					break          # inutile de reessayer, on passe a la suivante
				time.sleep(1.0)
			except (urllib.error.URLError, OSError, ValueError) as e:
				derniere = e
				time.sleep(1.0)
	raise derniere if derniere else OSError('aucune URL')


def redimensionne(octets):
	"""Rend (octets, note). Sans Pillow, l'octet d'origine passe tel quel.

	Rend (False, ...) quand la source repond 200 mais ne donne PAS de photo :
	c'est une absence, pas une panne, et il ne faut pas la reessayer."""
	# perfume.com repond 200 avec un SVG de remplacement — toujours 19 678
	# octets — pour les fiches dont il n'a pas la photo. C'est leur dessin
	# de « pas d'image » : le poser sur 146 fiches de la boutique, ce serait
	# afficher le graphisme d'un autre commercant a la place du produit.
	tete = octets[:400].lstrip()
	if tete[:4] == b'<svg' or tete[:5] == b'<?xml' or tete[:1] == b'<':
		return False, 'pas de photo a la source'
	try:
		from PIL import Image
	except ImportError:
		return octets, 'brut'
	try:
		im = Image.open(io.BytesIO(octets))
		im.load()
	except Exception:
		return None, 'illisible'
	natif = max(im.size)
	if im.mode not in ('RGB', 'L'):
		fond = Image.new('RGB', im.size, (255, 255, 255))
		im = im.convert('RGBA')
		fond.paste(im, mask=im.split()[-1])
		im = fond
	else:
		im = im.convert('RGB')
	# On ne grossit JAMAIS une image : agrandir une vignette de 250 px la
	# rend floue, elle n'y gagne aucun detail et le fichier triple.
	if natif > COTE:
		r = COTE / float(natif)
		im = im.resize((max(1, int(im.size[0] * r)), max(1, int(im.size[1] * r))),
		               Image.LANCZOS)
	s = io.BytesIO()
	im.save(s, 'JPEG', quality=QUALITE, optimize=True, progressive=True)
	sortie = s.getvalue()
	# Reencoder une petite image deja compressee la fait GROSSIR, et lui coute
	# une generation de perte. Mesure sur perfume : 5 366 o -> 6 114 o. Dans
	# ce cas on garde l'original.
	if natif <= COTE and len(sortie) >= len(octets) and octets[:2] == b'\xff\xd8':
		return octets, 'origine %d px' % natif
	return sortie, '%d px' % min(natif, COTE)


def main():
	ap = argparse.ArgumentParser()
	ap.add_argument('boutique')
	ap.add_argument('--limite', type=int, required=True,
	                help='nombre maximal de fiches traitees pendant cette passe')
	ap.add_argument('--je-detiens-les-droits', action='store_true', dest='droits')
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

	entree = queue.Queue()
	sortie = queue.Queue()
	for pid, j in lignes:
		urls = json.loads(j or '[]')
		if urls:
			entree.put((pid, urls[0]))
	total = entree.qsize()

	def ouvrier():
		while True:
			try:
				pid, url = entree.get_nowait()
			except queue.Empty:
				return
			try:
				brut, utilisee = telecharge(a.boutique, url)
			except Exception as e:
				sortie.put((pid, None, 'echec : %s' % str(e)[:60], 0))
				time.sleep(PAUSE)
				continue
			octets, note = redimensionne(brut)
			sortie.put((pid, octets, note, len(brut)))
			time.sleep(PAUSE)

	fils = [threading.Thread(target=ouvrier, daemon=True) for _ in range(FILS)]
	debut = time.time()
	for f in fils:
		f.start()

	# Un seul fil ecrit : SQLite et les threads ne font pas bon menage, et
	# ecrire ici veut dire qu'une interruption laisse la base coherente.
	ok = echec = 0
	recu = pose = 0
	agrandies = 0
	sans_source = 0
	traites = 0
	while traites < total:
		try:
			pid, octets, note, taille = sortie.get(timeout=60)
		except queue.Empty:
			if not any(f.is_alive() for f in fils):
				break
			continue
		traites += 1
		recu += taille
		if octets is False:
			# Absence definitive : on la marque, sinon chaque passe suivante
			# reessaiera les memes fiches et n'avancera jamais.
			sans_source += 1
			db.execute("UPDATE produits SET image = '' WHERE id = ?", (pid,))
			db.commit()
			continue
		if octets is None:
			echec += 1
			print('  %-8d %s' % (pid, note))
			continue
		nom = '%d.jpg' % pid
		open(os.path.join(photos, nom), 'wb').write(octets)
		db.execute('UPDATE produits SET image = ? WHERE id = ?', (nom, pid))
		db.commit()
		ok += 1
		pose += len(octets)
		if note.startswith('origine'):
			agrandies += 1
		if ok % 100 == 0:
			v = ok / max(0.001, time.time() - debut)
			print('  %d/%d deposees — %.1f/s, %.0f Mo recus'
			      % (ok, total, v, recu / 1e6))

	restant = db.execute('SELECT COUNT(*) FROM produits'
	                     ' WHERE image IS NULL AND images_n > 0').fetchone()[0]
	db.close()
	print('%s : %d deposees, %d echecs, %d fiches encore sans photo'
	      % (a.boutique, ok, echec, restant))
	if sans_source:
		print('  %d fiches pour lesquelles la source n\'a pas de photo :'
		      ' elle repond 200 avec son propre dessin de remplacement.'
		      ' Vignette dessinee conservee.' % sans_source)
	print('  recu %.1f Mo, ecrit %.1f Mo, %.1f ko par photo, %d s'
	      % (recu / 1e6, pose / 1e6, pose / 1000.0 / max(1, ok),
	         time.time() - debut))
	if agrandies:
		print('  %d images gardees telles quelles (deja sous %d px)'
		      % (agrandies, COTE))
	return 0


if __name__ == '__main__':
	sys.exit(main())
