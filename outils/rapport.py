#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Ecrit RAPPORT.md a partir des bases, jamais a partir de ma memoire.

Tout chiffre publie sort d'une requete faite ici. Si une donnee change et
qu'on relance, le rapport change avec elle : il n'y a pas de nombre recopie
a la main qui pourrait devenir faux sans que ca se voie.
"""

import os
import sqlite3
import sys

RACINE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DONNEES = os.path.join(RACINE, 'donnees')

BOUTIQUES = [
	('panier', 'Panier', 'Voila.csv', 'voila.ca'),
	('sillage', 'Sillage', 'perfume.csv', 'perfume.com'),
	('marche-asie', 'Marché d’Asie', 'tntsupermarket.csv', 'tntsupermarket.com'),
	('cueillette', 'Cueillette', 'foraged.csv', 'foraged.com'),
	('snackmonde', 'Snack Monde', 'snacksfrom.csv', 'snacksfrom.com'),
]


def n(v):
	return '{:,}'.format(int(v)).replace(',', ' ')


def pct(a, b):
	"""Un arrondi ne doit pas ecrire « 100 % » quand il en manque cinq :
	on garde une decimale des que ce n'est pas exactement plein ou vide."""
	if not b:
		return '0 %'
	v = 100.0 * a / b
	if v not in (0.0, 100.0) and round(v) in (0, 100):
		return ('%.1f %%' % v).replace('.', ',')   # document en francais
	return '%.0f %%' % v


def main():
	out = []
	w = out.append
	w('# Les cinq catalogues, mesurés\n')
	w('Chiffres produits par `outils/rapport.py`, lu directement dans les')
	w('bases. Aucun n’est saisi à la main.\n')

	w('## Vue d’ensemble\n')
	w('| Boutique | Source | Lignes CSV | Produits | Rayons | Marques | Fiches complètes |')
	w('|---|---|---:|---:|---:|---:|---:|')
	total_p = 0
	total_c = 0
	details = []
	for slug, nom, csvf, site in BOUTIQUES:
		db = sqlite3.connect(os.path.join(DONNEES, slug + '.sqlite'))
		m = dict(db.execute('SELECT cle, valeur FROM meta'))
		p = int(m['produits'])
		c = int(m['complets'])
		total_p += p
		total_c += c
		w('| %s | %s | %s | %s | %s | %s | %s (%s) |' % (
			nom, site, n(m['lignes_source']), n(p), m['rayons'],
			n(m['marques']), n(c), pct(c, p)))
		details.append((slug, nom, site, db, m))
	w('| **Total** | | | **%s** | | | **%s** (%s) |'
	  % (n(total_p), n(total_c), pct(total_c, total_p)))
	w('')
	w('« Fiche complète » veut dire : un prix ET au moins une photo recensée.')
	w('Les autres restent en ligne et consultables — elles sont signalées, pas')
	w('cachées, et le filtre « fiches complètes » permet de les écarter.\n')

	for slug, nom, site, db, m in details:
		w('## %s — %s\n' % (nom, site))
		p = int(m['produits'])
		lignes = int(m['lignes_source'])
		if lignes != p:
			w('- %s lignes dans le CSV, %s produits retenus : %s doublons '
			  'd’URL écartés%s.' % (
				n(lignes), n(p), n(m['doublons_ecartes']),
				', %s ligne(s) sans titre' % n(lignes - p - int(m['doublons_ecartes']))
				if lignes - p - int(m['doublons_ecartes']) else ''))
		sp = int(m['sans_prix'])
		si = int(m['sans_image'])
		w('- Sans prix : **%s** (%s).' % (n(sp), pct(sp, p)))
		w('- Sans aucune photo recensée : **%s** (%s).' % (n(si), pct(si, p)))

		sn = db.execute('SELECT COUNT(*) FROM produits WHERE note IS NULL').fetchone()[0]
		w('- Sans note : **%s** (%s).' % (n(sn), pct(sn, p)))

		a, b = db.execute('SELECT MIN(prix_cents), MAX(prix_cents) FROM produits'
		                  ' WHERE prix_cents IS NOT NULL').fetchone()
		med = db.execute('SELECT prix_cents FROM produits WHERE prix_cents IS NOT NULL'
		                 ' ORDER BY prix_cents LIMIT 1 OFFSET ?',
		                 ((p - sp) // 2,)).fetchone()
		if a is not None:
			w('- Prix : de %.2f à %.2f, médiane %.2f.'
			  % (a / 100.0, b / 100.0, (med[0] if med else 0) / 100.0))

		gros = db.execute('SELECT nom, n FROM rayons ORDER BY ordre, n DESC LIMIT 5').fetchall()
		w('- Principaux rayons : %s.'
		  % ', '.join('%s (%s)' % (r[0], n(r[1])) for r in gros))
		w('')
		db.close()

	w('## Ce que ces chiffres imposent\n')
	w('1. **Les fiches sans prix ne peuvent pas être encaissées.** Le moteur')
	w('   refuse leur mise au panier et la fiche le dit, au lieu d’afficher')
	w('   un prix de zéro.')
	w('2. **Les fiches sans photo ne sont pas vides**, elles portent une')
	w('   vignette dessinée à partir du nom du produit. Ce n’est pas une')
	w('   photo : c’est un repère, en attendant les vraies.')
	w('3. **Une note de 0 avec 0 avis n’est pas une note de zéro** — c’est')
	w('   l’absence de note. Elle n’est donc pas affichée.')
	w('')

	chemin = os.path.join(RACINE, 'RAPPORT.md')
	open(chemin, 'w', encoding='utf-8').write('\n'.join(out) + '\n')
	print('RAPPORT.md ecrit — %d octets' % os.path.getsize(chemin))
	return 0


if __name__ == '__main__':
	sys.exit(main())
