<?php
// Fonctions communes a toutes les pages. Aucune ne touche la base.

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/** Racine de la boutique, avec ou sans reecriture d'URL. */
function base_url() {
	static $b = null;
	if ($b === null) {
		$d = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
		$b = ($d === '' || $d === '.') ? '' : $d;
	}
	return $b;
}

/** Construit une URL interne. Si mod_rewrite est absent on retombe sur
 *  index.php?r=..., ce que le routeur sait lire aussi bien. */
function u($chemin, $params = array()) {
	global $CFG;
	$chemin = ltrim($chemin, '/');
	if (!empty($CFG['reecriture'])) {
		$url = base_url() . '/' . $chemin;
	} else {
		$url = base_url() . '/index.php' . ($chemin === '' ? '' : '?r=' . rawurlencode($chemin));
		if ($chemin !== '' && $params) {
			return $url . '&' . http_build_query($params);
		}
		if ($chemin === '' && $params) {
			return $url . '?' . http_build_query($params);
		}
		return $url;
	}
	return $params ? $url . '?' . http_build_query($params) : $url;
}

function slugue($s) {
	$s = mb_strtolower(trim((string)$s), 'UTF-8');
	$tr = array('à'=>'a','â'=>'a','ä'=>'a','á'=>'a','ã'=>'a','å'=>'a','ç'=>'c',
		'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','î'=>'i','ï'=>'i','í'=>'i',
		'ô'=>'o','ö'=>'o','ó'=>'o','õ'=>'o','ù'=>'u','û'=>'u','ü'=>'u','ú'=>'u',
		'ÿ'=>'y','ñ'=>'n','œ'=>'oe','æ'=>'ae');
	$s = strtr($s, $tr);
	$s = preg_replace('/[^a-z0-9]+/', '-', $s);
	return trim($s, '-');
}

/** Un prix absent n'est pas un prix nul : on le dit, on n'affiche pas 0. */
function prix($cents) {
	global $CFG;
	if ($cents === null || $cents === '') return null;
	$v = $cents / 100;
	if ($CFG['format_prix'] === 'fr') {
		return number_format($v, 2, ',', ' ') . ' ' . $CFG['symbole'];
	}
	return $CFG['symbole'] . number_format($v, 2, '.', ',');
}

/** Un decimal ecrit comme la boutique ecrit ses prix : 4,47 en francais,
 *  4.47 en anglais. Sinon une note francaise apparait dans une page anglaise. */
function nombre($v, $dec = 2) {
	global $CFG;
	return $CFG['format_prix'] === 'fr'
		? number_format((float)$v, $dec, ',', ' ')
		: number_format((float)$v, $dec, '.', ',');
}

function prix_ou_demande($cents) {
	$p = prix($cents);
	return $p === null ? t('prix_sur_demande') : $p;
}

/** Vignette de remplacement, dessinee ici : pas de fichier a charger, pas de
 *  requete vers un serveur tiers, et deux produits differents n'ont jamais la
 *  meme. Utilisee tant qu'aucune photo n'a ete deposee pour la fiche. */
function vignette($id, $titre) {
	global $CFG;
	$mots = preg_split('/\s+/', trim($titre));
	$ini = '';
	foreach ($mots as $m) {
		if ($m === '') continue;
		$c = mb_substr($m, 0, 1, 'UTF-8');
		if (preg_match('/\pL/u', $c)) { $ini .= mb_strtoupper($c, 'UTF-8'); }
		if (mb_strlen($ini, 'UTF-8') >= 2) break;
	}
	// Beaucoup de parfums s'appellent « 1000 » ou « 1881 » : sans ce
	// repli, toutes ces fiches affichaient un « ? » identique.
	if ($ini === '') $ini = mb_substr(trim($titre), 0, 2, 'UTF-8');
	if ($ini === '') $ini = '·';
	$l = 18 + (($id * 37) % 26);            // une clarte par produit
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 300">'
		. '<rect width="300" height="300" fill="hsl(' . $CFG['teinte_h'] . ','
		. $CFG['teinte_s'] . '%,' . (88 + ($l % 8)) . '%)"/>'
		. '<text x="150" y="150" text-anchor="middle" dominant-baseline="central"'
		. ' font-family="Helvetica,Arial,sans-serif" font-size="104" font-weight="600"'
		. ' fill="hsl(' . $CFG['teinte_h'] . ',' . $CFG['teinte_s'] . '%,' . (30 + $l % 14) . '%)"'
		. ' opacity="0.55">' . h($ini) . '</text></svg>';
	return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

/** Adresse de l'image d'un produit : le fichier local s'il a ete depose, la
 *  vignette dessinee sinon. On ne pointe JAMAIS vers le serveur d'origine. */
function image_produit($p) {
	if (!empty($p['image'])) {
		return base_url() . '/photos/' . rawurlencode($p['image']);
	}
	return vignette((int)$p['id'], $p['titre']);
}

function lien_produit($p) {
	return u('produit/' . (int)$p['id'] . '-' . slugue($p['titre']));
}

function extrait($s, $n = 150) {
	$s = trim(preg_replace('/\s+/', ' ', (string)$s));
	if (mb_strlen($s, 'UTF-8') <= $n) return $s;
	return mb_substr($s, 0, $n, 'UTF-8') . '…';
}

function t($cle) {
	global $L;
	return isset($L[$cle]) ? $L[$cle] : $cle;
}

function redirige($url) {
	header('Location: ' . $url);
	exit;
}
