<?php
// Le panier vit dans la session. Les prix ne sont JAMAIS pris dans le
// formulaire : on relit la base a chaque calcul. Sinon n'importe qui poste
// un prix de son choix.

function panier_init() {
	if (session_status() === PHP_SESSION_NONE) {
		session_set_cookie_params(array('httponly' => true, 'samesite' => 'Lax'));
		session_start();
	}
	if (!isset($_SESSION['panier']) || !is_array($_SESSION['panier'])) {
		$_SESSION['panier'] = array();
	}
	if (empty($_SESSION['jeton'])) {
		$_SESSION['jeton'] = bin2hex(random_bytes(16));
	}
}

function jeton() { return $_SESSION['jeton']; }

function jeton_valide($v) {
	return isset($_SESSION['jeton']) && is_string($v)
		&& hash_equals($_SESSION['jeton'], $v);
}

function panier_ajouter($id, $qte = 1) {
	$id = (int)$id;
	$p = produit($id);
	if (!$p) return false;
	// Un produit sans prix ne peut pas etre encaisse. Il reste consultable,
	// il n'entre pas au panier — et la fiche le dit.
	if ($p['prix_cents'] === null) return false;
	$qte = max(1, min(99, (int)$qte));
	$actuel = isset($_SESSION['panier'][$id]) ? $_SESSION['panier'][$id] : 0;
	$_SESSION['panier'][$id] = min(99, $actuel + $qte);
	return true;
}

function panier_fixer($id, $qte) {
	$id = (int)$id;
	$qte = (int)$qte;
	if ($qte <= 0) { unset($_SESSION['panier'][$id]); return; }
	$_SESSION['panier'][$id] = min(99, $qte);
}

function panier_vider() { $_SESSION['panier'] = array(); }

function panier_nb() { return array_sum($_SESSION['panier']); }

/** Rend les lignes completes + les totaux, tous relus en base. */
function panier_detail() {
	$ids = array_keys($_SESSION['panier']);
	$prods = produits_par_ids($ids);
	$lignes = array();
	$sous_total = 0;
	foreach ($_SESSION['panier'] as $id => $qte) {
		if (!isset($prods[$id])) { unset($_SESSION['panier'][$id]); continue; }
		$p = $prods[$id];
		if ($p['prix_cents'] === null) { unset($_SESSION['panier'][$id]); continue; }
		$ligne = (int)$p['prix_cents'] * (int)$qte;
		$sous_total += $ligne;
		$lignes[] = array('produit' => $p, 'qte' => (int)$qte, 'total' => $ligne);
	}
	global $CFG;
	$livraison = 0;
	if ($lignes) {
		$livraison = (int)$CFG['livraison_cents'];
		if ($CFG['franco_cents'] !== null && $sous_total >= $CFG['franco_cents']) {
			$livraison = 0;
		}
	}
	return array('lignes' => $lignes, 'sous_total' => $sous_total,
	             'livraison' => $livraison, 'total' => $sous_total + $livraison);
}

// --- Commandes ------------------------------------------------------------
//
// Aucun paiement n'est encaisse : le prestataire n'est pas choisi. La
// commande est enregistree et recoit une reference ; le mur du paiement est
// pose la, explicitement, plutot que simule.

function commandes_db() {
	global $CFG;
	$f = $CFG['commandes'];
	$neuf = !file_exists($f);
	$db = new PDO('sqlite:' . $f, null, null, array(
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
	));
	if ($neuf) {
		$db->exec('CREATE TABLE commandes (
			id INTEGER PRIMARY KEY, reference TEXT UNIQUE NOT NULL,
			creee_le TEXT NOT NULL, nom TEXT, courriel TEXT, telephone TEXT,
			adresse TEXT, ville TEXT, code_postal TEXT, pays TEXT, note TEXT,
			sous_total INTEGER, livraison INTEGER, total INTEGER,
			devise TEXT, lignes TEXT, etat TEXT NOT NULL DEFAULT "a_payer")');
	}
	return $db;
}

function commande_enregistrer($client, $detail) {
	global $CFG;
	$db = commandes_db();
	$ref = strtoupper(substr($CFG['slug'], 0, 3)) . '-' . date('ymd') . '-'
		. strtoupper(bin2hex(random_bytes(3)));
	$lignes = array();
	foreach ($detail['lignes'] as $l) {
		$lignes[] = array(
			'id' => (int)$l['produit']['id'],
			'titre' => $l['produit']['titre'],
			'prix_cents' => (int)$l['produit']['prix_cents'],
			'qte' => $l['qte'],
		);
	}
	$q = $db->prepare('INSERT INTO commandes (reference, creee_le, nom, courriel,
		telephone, adresse, ville, code_postal, pays, note, sous_total,
		livraison, total, devise, lignes)
		VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
	$q->execute(array($ref, gmdate('Y-m-d H:i:s'), $client['nom'],
		$client['courriel'], $client['telephone'], $client['adresse'],
		$client['ville'], $client['code_postal'], $client['pays'],
		$client['note'], $detail['sous_total'], $detail['livraison'],
		$detail['total'], $CFG['devise'],
		json_encode($lignes, JSON_UNESCAPED_UNICODE)));
	return $ref;
}

function commande_lire($ref) {
	$db = commandes_db();
	$q = $db->prepare('SELECT * FROM commandes WHERE reference = ?');
	$q->execute(array($ref));
	$c = $q->fetch();
	return $c ? $c : null;
}
