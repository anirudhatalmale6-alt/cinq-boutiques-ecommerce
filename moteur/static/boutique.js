// Le site fonctionne entierement sans JavaScript : chaque bouton est un
// formulaire ou un lien. Ce fichier n'ajoute que du confort.
(function () {
	'use strict';

	// Le menu de tri navigue au changement. Sans JS, il reste un <select>
	// dans un formulaire — mais on le rend utilisable a la souris seule.
	var tri = document.getElementById('tri');
	if (tri && tri.dataset.base) {
		tri.addEventListener('change', function () {
			window.location.href = tri.dataset.base.replace(
				'__T__', encodeURIComponent(tri.value));
		});
	}

	// Sur telephone, la colonne de filtres part repliee : les produits
	// doivent etre visibles sans defiler. Le repli est purement visuel, le
	// contenu reste dans la page pour les lecteurs d'ecran une fois ouvert.
	var pli = document.querySelector('.filtres-pli');
	if (pli && window.matchMedia('(max-width: 980px)').matches) {
		pli.removeAttribute('open');
	}

	// Retour visuel a l'ajout au panier : le compteur monte tout de suite,
	// la page se recharge derriere. Si la requete echoue, le rechargement
	// remet le vrai chiffre — on n'invente jamais l'etat du panier.
	document.querySelectorAll('form.ajout').forEach(function (f) {
		f.addEventListener('submit', function () {
			var b = f.querySelector('button');
			if (b) { b.textContent = '✓'; b.disabled = true; }
		});
	});
})();
