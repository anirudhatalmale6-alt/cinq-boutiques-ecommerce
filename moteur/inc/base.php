<?php
// Acces au catalogue. Lecture seule : le fichier livre n'est jamais modifie
// par le site. Les commandes vont dans une base separee (commandes.php).

function db() {
	static $db = null;
	if ($db === null) {
		global $CFG;
		$f = $CFG['catalogue'];
		if (!is_readable($f)) {
			http_response_code(500);
			exit('Catalogue introuvable : ' . h(basename($f)));
		}
		$db = new PDO('sqlite:' . $f, null, null, array(
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		));
		$db->exec('PRAGMA query_only = 1');
	}
	return $db;
}

function meta($cle, $defaut = null) {
	static $m = null;
	if ($m === null) {
		$m = array();
		foreach (db()->query('SELECT cle, valeur FROM meta') as $r) {
			$m[$r['cle']] = $r['valeur'];
		}
	}
	return isset($m[$cle]) ? $m[$cle] : $defaut;
}

function rayons() {
	static $r = null;
	if ($r === null) {
		$r = db()->query('SELECT slug, nom, n FROM rayons'
			. ' ORDER BY ordre, n DESC, nom')
			->fetchAll();
	}
	return $r;
}

function rayon($slug) {
	foreach (rayons() as $r) { if ($r['slug'] === $slug) return $r; }
	return null;
}

function sous_rayons($rayon_slug) {
	$q = db()->prepare('SELECT slug, nom, n FROM sous_rayons WHERE rayon_slug = ?'
		. ' ORDER BY n DESC, nom');
	$q->execute(array($rayon_slug));
	return $q->fetchAll();
}

function produit($id) {
	$q = db()->prepare('SELECT * FROM produits WHERE id = ?');
	$q->execute(array((int)$id));
	$p = $q->fetch();
	return $p ? $p : null;
}

function produits_par_ids($ids) {
	if (!$ids) return array();
	$ids = array_map('intval', $ids);
	$in = implode(',', array_fill(0, count($ids), '?'));
	$q = db()->prepare('SELECT * FROM produits WHERE id IN (' . $in . ')');
	$q->execute($ids);
	$out = array();
	foreach ($q->fetchAll() as $p) { $out[(int)$p['id']] = $p; }
	return $out;
}

/** Un seul chemin de recherche pour le rayon, la recherche et les filtres :
 *  deux chemins finiraient par ne plus compter pareil. */
function chercher($f) {
	$f += array('rayon' => null, 'sous' => null, 'marque' => null, 'q' => '',
	            'prix_min' => '', 'prix_max' => '', 'vendables' => false,
	            'tri' => 'pertinence', 'page' => 1, 'par' => 24);
	$where = array();
	$args = array();
	if (!empty($f['rayon']))  { $where[] = 'rayon_slug = ?'; $args[] = $f['rayon']; }
	if (!empty($f['sous']))   { $where[] = 'sous_slug = ?';  $args[] = $f['sous']; }
	if (!empty($f['marque'])) { $where[] = 'marque = ?';     $args[] = $f['marque']; }
	if (!empty($f['q'])) {
		// Chaque mot doit apparaitre : « huile olive » ne doit pas ramener
		// tout ce qui contient « huile ».
		foreach (preg_split('/\s+/', normalise($f['q'])) as $mot) {
			if ($mot === '') continue;
			$where[] = 'cherche LIKE ?';
			$args[] = '%' . $mot . '%';
		}
	}
	if (isset($f['prix_min']) && $f['prix_min'] !== '') {
		$where[] = 'prix_cents >= ?'; $args[] = (int)round($f['prix_min'] * 100);
	}
	if (isset($f['prix_max']) && $f['prix_max'] !== '') {
		$where[] = 'prix_cents <= ?'; $args[] = (int)round($f['prix_max'] * 100);
	}
	if (!empty($f['vendables'])) { $where[] = 'complet = 1'; }
	$sql_where = $where ? ' WHERE ' . implode(' AND ', $where) : '';

	$tris = array(
		'pertinence' => 'complet DESC, images_n DESC, titre',
		'prix_asc'   => 'prix_cents IS NULL, prix_cents ASC',
		'prix_desc'  => 'prix_cents IS NULL, prix_cents DESC',
		'nom'        => 'titre',
		'note'       => 'note IS NULL, note DESC, avis DESC',
	);
	$tri = isset($tris[$f['tri']]) ? $tris[$f['tri']] : $tris['pertinence'];

	$q = db()->prepare('SELECT COUNT(*) FROM produits' . $sql_where);
	$q->execute($args);
	$total = (int)$q->fetchColumn();

	$par = isset($f['par']) ? (int)$f['par'] : 24;
	$page = max(1, (int)$f['page']);
	$pages = max(1, (int)ceil($total / $par));
	if ($page > $pages) $page = $pages;

	$q = db()->prepare('SELECT * FROM produits' . $sql_where
		. ' ORDER BY ' . $tri . ' LIMIT ' . $par . ' OFFSET ' . (($page - 1) * $par));
	$q->execute($args);

	return array('lignes' => $q->fetchAll(), 'total' => $total,
	             'page' => $page, 'pages' => $pages, 'par' => $par);
}

/** Marques presentes dans le perimetre courant, avec leur compte. */
function marques_du_rayon($rayon_slug, $limite = 40) {
	if ($rayon_slug) {
		$q = db()->prepare('SELECT marque, COUNT(*) n FROM produits'
			. ' WHERE marque IS NOT NULL AND rayon_slug = ?'
			. ' GROUP BY marque ORDER BY n DESC, marque LIMIT ' . (int)$limite);
		$q->execute(array($rayon_slug));
	} else {
		$q = db()->query('SELECT marque, COUNT(*) n FROM produits'
			. ' WHERE marque IS NOT NULL'
			. ' GROUP BY marque ORDER BY n DESC, marque LIMIT ' . (int)$limite);
	}
	return $q->fetchAll();
}

function bornes_prix($rayon_slug = null) {
	if ($rayon_slug) {
		$q = db()->prepare('SELECT MIN(prix_cents) a, MAX(prix_cents) b'
			. ' FROM produits WHERE prix_cents IS NOT NULL AND rayon_slug = ?');
		$q->execute(array($rayon_slug));
	} else {
		$q = db()->query('SELECT MIN(prix_cents) a, MAX(prix_cents) b'
			. ' FROM produits WHERE prix_cents IS NOT NULL');
	}
	return $q->fetch();
}

function suggestions($p, $n = 8) {
	$q = db()->prepare('SELECT * FROM produits WHERE rayon_slug = ? AND id <> ?'
		. ' AND complet = 1 ORDER BY images_n DESC, RANDOM() LIMIT ' . (int)$n);
	$q->execute(array($p['rayon_slug'], (int)$p['id']));
	return $q->fetchAll();
}

function vitrine($n = 8) {
	$q = db()->query('SELECT * FROM produits WHERE complet = 1'
		. ' ORDER BY note IS NULL, note DESC, avis DESC, images_n DESC'
		. ' LIMIT ' . (int)$n);
	return $q->fetchAll();
}

function normalise($s) {
	$s = mb_strtolower(trim((string)$s), 'UTF-8');
	$tr = array('à'=>'a','â'=>'a','ä'=>'a','á'=>'a','ã'=>'a','å'=>'a','ç'=>'c',
		'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','î'=>'i','ï'=>'i','í'=>'i',
		'ô'=>'o','ö'=>'o','ó'=>'o','õ'=>'o','ù'=>'u','û'=>'u','ü'=>'u','ú'=>'u',
		'ÿ'=>'y','ñ'=>'n','œ'=>'oe','æ'=>'ae');
	return strtr($s, $tr);
}
