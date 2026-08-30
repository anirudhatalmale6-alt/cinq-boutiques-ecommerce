#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Photographie les boutiques pour l'envoi au client.

Aucune capture pleine page : fenetre fixe, on cadre ce qu'on veut montrer.
Chaque vue est verifiee — largeur et hauteur sous 2000 px, et on s'assure
qu'aucune image de la page n'est cassee avant de garder la capture.
"""

import os
import sys

from playwright.sync_api import sync_playwright

RACINE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SORTIE = os.path.join(RACINE, 'apercus')

# (fichier, url, largeur, hauteur, defilement en px)
VUES = [
	('01-panier-accueil.png',      'http://127.0.0.1:8802/', 1280, 860, 0),
	('02-panier-rayon.png',        'http://127.0.0.1:8802/rayon/pantry', 1280, 900, 150),
	('03-panier-fiche.png',        'http://127.0.0.1:8802/produit/1-quaker', 1280, 860, 0),
	('04-panier-recherche.png',    'http://127.0.0.1:8802/recherche?q=bio', 1280, 900, 150),
	('05-sillage-accueil.png',     'http://127.0.0.1:8804/', 1280, 860, 0),
	('06-sillage-rayon.png',       'http://127.0.0.1:8804/rayon/femme', 1280, 900, 150),
	('07-cueillette-accueil.png',  'http://127.0.0.1:8803/', 1280, 860, 0),
	('08-cueillette-fiche.png',    'http://127.0.0.1:8803/produit/1-x', 1280, 860, 0),
	('09-snackmonde-rayon.png',    'http://127.0.0.1:8805/rayon/candy', 1280, 900, 150),
	('10-marche-asie-accueil.png', 'http://127.0.0.1:8806/', 1280, 860, 0),
	('11-marche-asie-fiche.png',   'http://127.0.0.1:8806/produit/1-x', 1280, 860, 0),
	('12-mobile-accueil.png',      'http://127.0.0.1:8802/', 390, 780, 0),
	('13-mobile-rayon.png',        'http://127.0.0.1:8802/rayon/pantry', 390, 780, 220),
]

# Le panier et la commande n'existent qu'apres un ajout : on les prend dans
# une session a part, en passant par les boutons, comme un vrai client.
PARCOURS = [
	('14-panier.png', 'http://127.0.0.1:8802', ['/produit/1-x', '/produit/7-x'],
	 '/panier', 1280, 860),
	('15-commande.png', 'http://127.0.0.1:8802', ['/produit/1-x', '/produit/7-x'],
	 '/commande', 1280, 980),
]

LIMITE = 2000


def main():
	os.makedirs(SORTIE, exist_ok=True)
	faits = []
	souci = []
	with sync_playwright() as p:
		# --disable-lcd-text : l'anticrenelage sous-pixel de Chromium pose des
		# franges colorees sur chaque lettre. Invisible a l'ecran, tres
		# visible dans un PNG que le client va agrandir.
		nav = p.chromium.launch(args=['--disable-lcd-text',
		                              '--force-color-profile=srgb'])
		for nom, url, larg, haut, dy in VUES:
			if larg > LIMITE or haut > LIMITE:
				raise SystemExit('vue %s : %dx%d depasse %d px' % (nom, larg, haut, LIMITE))
			page = nav.new_page(viewport={'width': larg, 'height': haut},
			                    device_scale_factor=1)
			page.goto(url, wait_until='networkidle')
			if dy:
				page.evaluate('y => window.scrollTo(0, y)', dy)
				page.wait_for_timeout(250)
			# Une capture ne dit pas si une image a echoue : on demande au
			# navigateur, image par image.
			#
			# On ne regarde QUE les images visibles dans la fenetre. Les
			# autres portent loading="lazy" et ne sont pas encore chargees :
			# les compter comme cassees ferait echouer toute vue longue, et
			# un vrai probleme se noierait dans ce bruit.
			cassees = page.evaluate("""() => {
				const h = window.innerHeight, w = window.innerWidth;
				return [...document.images].filter(i => {
					const r = i.getBoundingClientRect();
					const vu = r.bottom > 0 && r.top < h && r.right > 0 && r.left < w;
					return vu && (!i.complete || i.naturalWidth === 0);
				}).map(i => i.currentSrc.slice(0, 90)).slice(0, 5);
			}""")
			if cassees:
				souci.append((nom, cassees))
			chemin = os.path.join(SORTIE, nom)
			page.screenshot(path=chemin)
			faits.append((nom, os.path.getsize(chemin), larg, haut))
			page.close()

		for nom, base, fiches, cible, larg, haut in PARCOURS:
			ctx = nav.new_context(viewport={'width': larg, 'height': haut})
			page = ctx.new_page()
			for f in fiches:
				page.goto(base + f, wait_until='networkidle')
				page.click('.fiche-achat button[type=submit]')
				page.wait_for_load_state('networkidle')
			page.goto(base + cible, wait_until='networkidle')
			# Un panier vide se photographie sans erreur et ne montre rien :
			# on verifie qu'il y a bien des lignes avant de garder la vue.
			lignes = page.evaluate(
				"() => document.querySelectorAll('.tab-panier tbody tr,"
				" .recap-liste li').length")
			if lignes < len(fiches):
				souci.append((nom, ['panier vide : %d ligne(s)' % lignes]))
			chemin = os.path.join(SORTIE, nom)
			page.screenshot(path=chemin)
			faits.append((nom, os.path.getsize(chemin), larg, haut))
			ctx.close()

		nav.close()

	for nom, taille, larg, haut in faits:
		print('%8d o  %4dx%-4d  %s' % (taille, larg, haut, nom))
	if souci:
		print('\nIMAGES CASSEES :')
		for nom, urls in souci:
			print('  %s : %s' % (nom, ', '.join(urls)))
		return 1
	print('\naucune image cassee sur les %d vues' % len(faits))
	return 0


if __name__ == '__main__':
	sys.exit(main())
