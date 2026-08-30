#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Assemble les cinq boutiques a partir du moteur commun et des catalogues.

Le moteur est ecrit UNE FOIS dans moteur/. Chaque boutique est un dossier
autonome — on le depose dans public_html et il tourne, sans rien a regler.
Corriger le moteur puis relancer ce script corrige les cinq d'un coup.

Les noms de boutique sont PROVISOIRES et le disent dans leur pied de page :
je ne reprends pas le nom du site d'ou vient la donnee, ce serait se faire
passer pour lui.
"""

import colorsys
import os
import shutil
import sys

RACINE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
MOTEUR = os.path.join(RACINE, 'moteur')
DONNEES = os.path.join(RACINE, 'donnees')
SORTIE = os.path.join(RACINE, 'sites')

HTACCESS = """Options -Indexes
DirectoryIndex index.php

<IfModule mod_rewrite.c>
	RewriteEngine On
	RewriteBase %(base)s
	RewriteCond %%{REQUEST_FILENAME} !-f
	RewriteCond %%{REQUEST_FILENAME} !-d
	RewriteRule ^ index.php [QSA,L]
</IfModule>

# Si mod_rewrite est absent, le moteur retombe de lui-meme sur
# index.php?r=... : mettre 'reecriture' a false dans config.php.
"""

# Le catalogue et les commandes ne doivent jamais etre telechargeables.
HTACCESS_DONNEES = """<IfModule mod_authz_core.c>
	Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
	Order allow,deny
	Deny from all
</IfModule>
"""

CONFIG = """<?php
// Configuration de la boutique « %(nom)s ».
// Tout ce qui se regle sans toucher au moteur est ici.

return array(

	'slug'  => '%(slug)s',

	// NOM PROVISOIRE. Il vient de moi, pas du client : le nom du site
	// d'origine des donnees n'est pas repris, et ne doit pas l'etre.
	'nom'   => '%(nom)s',
	'nom_provisoire' => true,

	'titre_accueil' => '%(titre)s',
	'baseline'      => '%(baseline)s',

	'langue'      => '%(langue)s',       // 'fr' ou 'en' — voir inc/langue.*.php
	'devise'      => '%(devise)s',
	'symbole'     => '%(symbole)s',
	'format_prix' => '%(format)s',       // 'fr' : 7,99 $   |   'en' : $7.99

	// Couleur de la boutique. La teinte et la saturation servent aussi aux
	// fonds clairs et aux vignettes : elles sont donnees a part pour que le
	// CSS puisse en deriver des variantes sans recalculer.
	'teinte'   => '%(teinte)s',
	'teinte_h' => %(h)d,
	'teinte_s' => %(s)d,

	// PLACEHOLDERS : a fixer quand le pays d'exploitation sera choisi.
	'livraison_cents' => %(livraison)d,
	'franco_cents'    => %(franco)s,

	'par_page' => 24,

	// Mettre a false si mod_rewrite n'est pas disponible sur l'hebergement.
	'reecriture' => true,
);
"""


def hs(hexa):
	"""Teinte et saturation HSL d'un #RRGGBB, pour que le CSS en derive des
	variantes claires sans que j'aie a les saisir a la main."""
	r, g, b = (int(hexa[i:i + 2], 16) / 255.0 for i in (1, 3, 5))
	h, l, s = colorsys.rgb_to_hls(r, g, b)
	return int(round(h * 360)), int(round(s * 100))


SITES = [
	dict(slug='cueillette', nom='Cueillette',
	     titre='Le meilleur de la cueillette sauvage',
	     baseline='Champignons, plantes, teintures et produits sauvages, '
	              'vendus par des cueilleurs.',
	     langue='en', devise='USD', symbole='$', format='en',
	     teinte='#2F6B3F', livraison=795, franco='6500'),
	dict(slug='sillage', nom='Sillage',
	     titre='Parfums et eaux de toilette',
	     baseline='Femme, homme et mixte — des centaines de maisons.',
	     langue='en', devise='USD', symbole='$', format='en',
	     teinte='#8A3A5E', livraison=595, franco='5000'),
	dict(slug='snackmonde', nom='Snack Monde',
	     titre='Les snacks du monde entier',
	     baseline='Boissons, confiseries et grignotages importes, au carton '
	              'ou a l’unite.',
	     langue='en', devise='USD', symbole='$', format='en',
	     teinte='#D9662B', livraison=899, franco='7500'),
	dict(slug='marche-asie', nom='Marché d’Asie',
	     titre='L’épicerie asiatique, livrée',
	     baseline='Frais, surgelés, épicerie et cuisine préparée.',
	     langue='fr', devise='CAD', symbole='$', format='fr',
	     teinte='#B23A32', livraison=995, franco='7500'),
	dict(slug='panier', nom='Panier',
	     titre='Vos courses, en ligne',
	     baseline='Épicerie, frais, surgelés et maison — des milliers de '
	              'références.',
	     langue='fr', devise='CAD', symbole='$', format='fr',
	     teinte='#136B54', livraison=995, franco='10000'),
]


def copier_moteur(dest):
	for nom in ('index.php',):
		shutil.copy2(os.path.join(MOTEUR, nom), os.path.join(dest, nom))
	for dossier in ('inc', 'vues', 'static'):
		d = os.path.join(dest, dossier)
		if os.path.isdir(d):
			shutil.rmtree(d)
		shutil.copytree(os.path.join(MOTEUR, dossier), d)


def construire(s):
	dest = os.path.join(SORTIE, s['slug'])

	# Relancer ce script ne doit JAMAIS effacer ce que la boutique a produit :
	# les commandes recues et les photos deposees sont mises de cote, puis
	# remises en place. Sans ca, une simple correction du moteur perdrait le
	# carnet de commandes.
	garde = {}
	anciennes = os.path.join(dest, 'donnees', 'commandes.sqlite')
	if os.path.isfile(anciennes):
		garde['commandes'] = open(anciennes, 'rb').read()
	photos = os.path.join(dest, 'photos')
	if os.path.isdir(photos):
		garde['photos'] = {f: open(os.path.join(photos, f), 'rb').read()
		                   for f in os.listdir(photos)
		                   if os.path.isfile(os.path.join(photos, f))}

	if os.path.isdir(dest):
		shutil.rmtree(dest)
	os.makedirs(os.path.join(dest, 'donnees'))
	os.makedirs(os.path.join(dest, 'photos'))

	if 'commandes' in garde:
		open(anciennes, 'wb').write(garde['commandes'])
	for f, octets in garde.get('photos', {}).items():
		open(os.path.join(photos, f), 'wb').write(octets)

	copier_moteur(dest)

	h, sat = hs(s['teinte'])
	open(os.path.join(dest, 'config.php'), 'w', encoding='utf-8').write(
		CONFIG % dict(s, h=h, s=sat))
	open(os.path.join(dest, '.htaccess'), 'w', encoding='utf-8').write(
		HTACCESS % {'base': '/'})
	open(os.path.join(dest, 'donnees', '.htaccess'), 'w',
	     encoding='utf-8').write(HTACCESS_DONNEES)
	# Le dossier des photos doit exister meme vide, sinon le premier depot
	# par FTP le cree avec des droits au hasard.
	open(os.path.join(dest, 'photos', '.gitkeep'), 'w').write('')

	src = os.path.join(DONNEES, s['slug'] + '.sqlite')
	shutil.copy2(src, os.path.join(dest, 'donnees', 'catalogue.sqlite'))

	n = 0
	octets = 0
	for r, _, fs in os.walk(dest):
		for f in fs:
			n += 1
			octets += os.path.getsize(os.path.join(r, f))
	return n, octets


def main():
	if not os.path.isdir(DONNEES):
		print('Lancer outils/importer.py d’abord.')
		return 1
	os.makedirs(SORTIE, exist_ok=True)
	print('%-13s %-9s %-5s %6s %12s' % ('boutique', 'devise', 'lang',
	                                    'fich.', 'poids'))
	for s in SITES:
		n, octets = construire(s)
		print('%-13s %-9s %-5s %6d %12s' % (
			s['slug'], s['devise'], s['langue'], n,
			'%.1f Mo' % (octets / 1048576.0)))
	print('sites/ pret — un dossier par boutique, a deposer dans public_html')
	return 0


if __name__ == '__main__':
	sys.exit(main())
