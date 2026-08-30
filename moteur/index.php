<?php
// Point d'entree unique de la boutique. Toutes les URL passent ici.

$RACINE = __DIR__;
$CFG = require $RACINE . '/config.php';

$CFG += array(
	'catalogue' => $RACINE . '/donnees/catalogue.sqlite',
	'commandes' => $RACINE . '/donnees/commandes.sqlite',
	'format_prix' => 'en',
	'symbole' => '$',
	'livraison_cents' => 0,
	'franco_cents' => null,
	'langue' => 'fr',
	'reecriture' => true,
	'par_page' => 24,
);

$L = require $RACINE . '/inc/langue.' . $CFG['langue'] . '.php';

require $RACINE . '/inc/outils.php';
require $RACINE . '/inc/base.php';
require $RACINE . '/inc/panier.php';

panier_init();

// --- Route ---------------------------------------------------------------
// Avec reecriture : /rayon/xxx. Sans : index.php?r=rayon/xxx. Les deux
// donnent le meme tableau, le reste du code ne sait pas lequel a servi.
if (isset($_GET['r'])) {
	$chemin = trim($_GET['r'], '/');
} else {
	$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
	$base = base_url();
	if ($base !== '' && strpos($uri, $base) === 0) {
		$uri = substr($uri, strlen($base));
	}
	$chemin = trim(rawurldecode($uri), '/');
	if ($chemin === 'index.php') $chemin = '';
}
$bouts = $chemin === '' ? array() : explode('/', $chemin);
$route = isset($bouts[0]) ? $bouts[0] : '';

$titre_page = $CFG['nom'];
$vue = null;
$vars = array();

switch ($route) {

case '':
	$vue = 'accueil';
	$vars['vitrine'] = vitrine(8);
	break;

case 'rayon':
	$slug = isset($bouts[1]) ? $bouts[1] : '';
	$r = rayon($slug);
	if (!$r) { $vue = '404'; break; }
	$vue = 'liste';
	$vars['rayon'] = $r;
	$vars['sous'] = sous_rayons($slug);
	$vars['sous_actif'] = isset($_GET['sous']) ? $_GET['sous'] : null;
	$vars['res'] = chercher(array(
		'rayon' => $slug,
		'sous' => $vars['sous_actif'],
		'marque' => isset($_GET['marque']) ? $_GET['marque'] : null,
		'prix_min' => isset($_GET['prix_min']) ? $_GET['prix_min'] : '',
		'prix_max' => isset($_GET['prix_max']) ? $_GET['prix_max'] : '',
		'vendables' => !empty($_GET['vendables']),
		'tri' => isset($_GET['tri']) ? $_GET['tri'] : 'pertinence',
		'page' => isset($_GET['page']) ? $_GET['page'] : 1,
		'par' => $CFG['par_page'],
	));
	$vars['marques'] = marques_du_rayon($slug);
	$vars['bornes'] = bornes_prix($slug);
	$vars['titre'] = $r['nom'];
	$titre_page = $r['nom'] . ' — ' . $CFG['nom'];
	break;

case 'recherche':
	$q = isset($_GET['q']) ? trim($_GET['q']) : '';
	$vue = 'liste';
	$vars['rayon'] = null;
	$vars['sous'] = array();
	$vars['sous_actif'] = null;
	$vars['q'] = $q;
	$vars['res'] = chercher(array(
		'q' => $q,
		'marque' => isset($_GET['marque']) ? $_GET['marque'] : null,
		'prix_min' => isset($_GET['prix_min']) ? $_GET['prix_min'] : '',
		'prix_max' => isset($_GET['prix_max']) ? $_GET['prix_max'] : '',
		'vendables' => !empty($_GET['vendables']),
		'tri' => isset($_GET['tri']) ? $_GET['tri'] : 'pertinence',
		'page' => isset($_GET['page']) ? $_GET['page'] : 1,
		'par' => $CFG['par_page'],
	));
	$vars['marques'] = marques_du_rayon(null);
	$vars['bornes'] = bornes_prix(null);
	$vars['titre'] = t('resultats_pour') . ' « ' . $q . ' »';
	$titre_page = t('rechercher') . ' : ' . $q . ' — ' . $CFG['nom'];
	break;

case 'produit':
	$id = isset($bouts[1]) ? (int)$bouts[1] : 0;
	$p = produit($id);
	if (!$p) { $vue = '404'; break; }
	$vue = 'fiche';
	$vars['p'] = $p;
	$vars['suggestions'] = suggestions($p);
	$titre_page = $p['titre'] . ' — ' . $CFG['nom'];
	break;

case 'panier':
	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		if (!jeton_valide(isset($_POST['jeton']) ? $_POST['jeton'] : '')) {
			http_response_code(400);
			exit('Jeton invalide.');
		}
		$action = isset($_POST['action']) ? $_POST['action'] : '';
		if ($action === 'ajouter') {
			$ok = panier_ajouter($_POST['id'], isset($_POST['qte']) ? $_POST['qte'] : 1);
			redirige(u('panier') . ($ok ? '' : '?refus=1'));
		} elseif ($action === 'majliste') {
			foreach ((array)$_POST['qte'] as $id => $q) { panier_fixer($id, $q); }
			redirige(u('panier'));
		} elseif ($action === 'retirer') {
			panier_fixer($_POST['id'], 0);
			redirige(u('panier'));
		} elseif ($action === 'vider') {
			panier_vider();
			redirige(u('panier'));
		}
		redirige(u('panier'));
	}
	$vue = 'panier';
	$vars['detail'] = panier_detail();
	$vars['refus'] = !empty($_GET['refus']);
	$titre_page = t('panier') . ' — ' . $CFG['nom'];
	break;

case 'commande':
	$detail = panier_detail();
	if (isset($bouts[1]) && $bouts[1] === 'confirmee') {
		$c = commande_lire(isset($_GET['ref']) ? $_GET['ref'] : '');
		if (!$c) { $vue = '404'; break; }
		$vue = 'confirmee';
		$vars['c'] = $c;
		$titre_page = t('merci') . ' — ' . $CFG['nom'];
		break;
	}
	if (!$detail['lignes']) { redirige(u('panier')); }
	$erreurs = array();
	$client = array('nom' => '', 'courriel' => '', 'telephone' => '',
		'adresse' => '', 'ville' => '', 'code_postal' => '', 'pays' => '',
		'note' => '');
	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		if (!jeton_valide(isset($_POST['jeton']) ? $_POST['jeton'] : '')) {
			http_response_code(400);
			exit('Jeton invalide.');
		}
		foreach ($client as $k => $_) {
			$client[$k] = isset($_POST[$k]) ? trim($_POST[$k]) : '';
		}
		foreach (array('nom', 'courriel', 'adresse', 'ville', 'code_postal', 'pays') as $k) {
			if ($client[$k] === '') $erreurs[$k] = t('obligatoire');
		}
		if ($client['courriel'] !== ''
			&& !filter_var($client['courriel'], FILTER_VALIDATE_EMAIL)) {
			$erreurs['courriel'] = t('courriel_invalide');
		}
		if (!$erreurs) {
			$ref = commande_enregistrer($client, $detail);
			panier_vider();
			redirige(u('commande/confirmee', array('ref' => $ref)));
		}
	}
	$vue = 'commande';
	$vars['detail'] = $detail;
	$vars['client'] = $client;
	$vars['erreurs'] = $erreurs;
	$titre_page = t('commander') . ' — ' . $CFG['nom'];
	break;

default:
	$vue = '404';
}

if ($vue === '404') {
	http_response_code(404);
	$titre_page = t('introuvable') . ' — ' . $CFG['nom'];
}

extract($vars, EXTR_SKIP);
include $RACINE . '/vues/entete.php';
include $RACINE . '/vues/' . $vue . '.php';
include $RACINE . '/vues/pied.php';
