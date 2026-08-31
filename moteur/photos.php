<?php
/*
 * Depose les photos du catalogue, DEPUIS LE SERVEUR DE LA BOUTIQUE.
 *
 * Pourquoi cet outil existe. Les photos telechargees sur mon poste doivent
 * ensuite remonter chez vous : plusieurs centaines de mega-octets a envoyer
 * a la main par le gestionnaire de fichiers. Lance ici, le serveur va les
 * chercher lui-meme et il n'y a rien a televerser.
 *
 * MODE D'EMPLOI
 *   1. Dans config.php, mettre 'cle_photos' => 'un-mot-de-passe-a-vous'.
 *   2. Ouvrir  /photos.php?cle=un-mot-de-passe-a-vous
 *   3. La page enchaine les lots toute seule. On peut fermer l'onglet et
 *      revenir : elle reprend ou elle s'etait arretee.
 *   4. QUAND C'EST FINI : remettre 'cle_photos' => '' ou supprimer ce
 *      fichier. Un script qui ecrit sur le disque n'a rien a faire en
 *      ligne une fois son travail termine.
 *
 * Sans cle dans config.php, ce fichier refuse de s'executer. C'est voulu :
 * si vous l'oubliez en place, il ne fait rien.
 */

$RACINE = __DIR__;
$CFG = require $RACINE . '/config.php';

header('Content-Type: text/html; charset=utf-8');

$cle = isset($CFG['cle_photos']) ? (string)$CFG['cle_photos'] : '';
if ($cle === '') {
	http_response_code(403);
	exit('Desactive. Mettre \'cle_photos\' dans config.php pour s\'en servir.');
}
// hash_equals : une comparaison qui prend le meme temps quelle que soit
// l'erreur, sinon la cle se devine caractere par caractere.
if (!isset($_GET['cle']) || !hash_equals($cle, (string)$_GET['cle'])) {
	http_response_code(403);
	exit('Cle absente ou incorrecte.');
}

@set_time_limit(0);
ignore_user_abort(false);

$LOT   = isset($_GET['n']) ? max(1, min(200, (int)$_GET['n'])) : 60;
$COTE  = 600;
$QUAL  = 80;
$PAUSE = 250000;   // microsecondes entre deux requetes

$photos = $RACINE . '/photos';
@mkdir($photos, 0755, true);

$db = new PDO('sqlite:' . $RACINE . '/donnees/catalogue.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$reste = (int)$db->query('SELECT COUNT(*) FROM produits'
	. ' WHERE image IS NULL AND images_n > 0')->fetchColumn();
$total = (int)$db->query('SELECT COUNT(*) FROM produits'
	. ' WHERE images_n > 0')->fetchColumn();
$faites = $total - $reste;

/* perfume.com : le CSV porte l'URL de la vignette 250x250. La meme image
 * existe en 750x750 sous /sku/large/. On demande large, on retombe sur
 * small si elle n'y est pas. */
function variantes($slug, $url) {
	if ($slug === 'sillage' && strpos($url, '/sku/small/') !== false) {
		return array(str_replace('/sku/small/', '/sku/large/', $url), $url);
	}
	return array($url);
}

function referer($slug) {
	$r = array(
		'sillage'     => 'https://www.perfume.com/',
		'snackmonde'  => 'https://snacksfrom.com/',
		'marche-asie' => 'https://www.tntsupermarket.com/',
		'panier'      => 'https://voila.ca/',
		'cueillette'  => 'https://foraged.com/',
	);
	return isset($r[$slug]) ? $r[$slug] : '';
}

/* tntsupermarket.com repond 403 sans les entetes d'un navigateur, et sert
 * de l'AVIF des que Accept l'annonce — un format que GD ne lit pas. On ne
 * demande donc pas d'AVIF.
 *
 * ACCEPT-ENCODING EST OBLIGATOIRE, et c'est le piege le plus vicieux des
 * trois. Une requete SANS aucun en-tete Accept-Encoding recoit 403 ; avec
 * n'importe lequel, 200. Mesure sur deux URL, quatre passages chacune :
 *
 *     aucun en-tete          0/8 en 200   (403 a chaque fois)
 *     Accept-Encoding: identity   8/8 en 200
 *     CURLOPT_ENCODING ''         8/8 en 200
 *
 * curl n'en envoie aucun par defaut. Python en envoie un tout seul. C'est
 * pour ca, et pour rien d'autre, que le script Python marchait et que
 * celui-ci renvoyait 403 sur la totalite du lot. */
function entetes($slug) {
	return array(
		'User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36'
			. ' (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
		'Accept: image/webp,image/apng,image/*,*/*;q=0.8',
		'Accept-Language: en-CA,en;q=0.9',
		'Accept-Encoding: identity',
		'Referer: ' . referer($slug),
		'Sec-Fetch-Dest: image',
		'Sec-Fetch-Mode: no-cors',
		'Sec-Fetch-Site: same-origin',
	);
}

function recupere($slug, $url, &$pourquoi = null) {
	$entetes = entetes($slug);
	foreach (variantes($slug, $url) as $u) {
		if (function_exists('curl_init')) {
			$c = curl_init($u);
			curl_setopt_array($c, array(
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_TIMEOUT        => 25,
				// Chaine vide = curl annonce ce qu'il sait decompresser et
				// decompresse tout seul. C'est la ligne qui evite le 403.
				CURLOPT_ENCODING       => '',
				CURLOPT_HTTPHEADER     => $entetes,
			));
			$b = curl_exec($c);
			$code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE);
			$err = curl_error($c);
			curl_close($c);
			if ($b !== false && $code === 200 && strlen($b) > 200) return $b;
			$pourquoi = $err !== '' ? $err : ('HTTP ' . $code);
		} else {
			// Sans curl : le contexte de flux doit porter les MEMES en-tetes,
			// Accept-Encoding compris, sinon meme 403.
			$ctx = stream_context_create(array('http' => array(
				'method'          => 'GET',
				'header'          => implode("\r\n", $entetes),
				'follow_location' => 1,
				'timeout'         => 25,
				'ignore_errors'   => true,
			)));
			$b = @file_get_contents($u, false, $ctx);
			$code = 0;
			if (isset($http_response_header[0])
				&& preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
				$code = (int)$m[1];
			}
			if ($b !== false && $code === 200 && strlen($b) > 200) return $b;
			$pourquoi = 'HTTP ' . $code . ' (sans curl)';
		}
	}
	return null;
}

/* La source repond 200 mais ne donne pas une photo ? perfume.com sert un SVG
 * de remplacement — toujours 19 678 octets — pour les fiches dont il n'a pas
 * l'image : 146 sur 6 958. C'est SON dessin de « pas d'image ». Le poser sur
 * une fiche, ce serait afficher le graphisme d'un autre commercant a la place
 * du produit. On ne le garde pas, et surtout on marque la fiche : sans ca,
 * chaque lot suivant reessaierait les memes fiches en tete de liste et
 * n'atteindrait jamais les suivantes. */
function est_une_photo($octets) {
	$t = ltrim(substr($octets, 0, 400));
	return $t !== '' && $t[0] !== '<';
}

/* Reduit et reencode en JPEG. Une image deja sous la taille cible et deja
 * plus legere que le reencodage est gardee telle quelle : la reencoder la
 * ferait grossir tout en lui coutant une generation de perte. */
function reduit($octets, $cote, $qual) {
	if (!function_exists('imagecreatefromstring')) return $octets;
	$im = @imagecreatefromstring($octets);
	if ($im === false) return null;
	$l = imagesx($im); $h = imagesy($im);
	$max = max($l, $h);
	if ($max > $cote) {
		$r = $cote / $max;
		$nl = max(1, (int)($l * $r)); $nh = max(1, (int)($h * $r));
		$d = imagecreatetruecolor($nl, $nh);
		imagefill($d, 0, 0, imagecolorallocate($d, 255, 255, 255));
		imagecopyresampled($d, $im, 0, 0, 0, 0, $nl, $nh, $l, $h);
		imagedestroy($im); $im = $d;
	} else {
		$d = imagecreatetruecolor($l, $h);
		imagefill($d, 0, 0, imagecolorallocate($d, 255, 255, 255));
		imagecopy($d, $im, 0, 0, 0, 0, $l, $h);
		imagedestroy($im); $im = $d;
	}
	ob_start();
	imagejpeg($im, null, $qual);
	$out = ob_get_clean();
	imagedestroy($im);
	if ($max <= $cote && strlen($out) >= strlen($octets)
		&& substr($octets, 0, 2) === "\xff\xd8") {
		return $octets;
	}
	return $out;
}

$lignes = $db->prepare('SELECT p.id, s.images FROM produits p'
	. ' JOIN source s ON s.id = p.id'
	. ' WHERE p.image IS NULL AND p.images_n > 0 ORDER BY p.id LIMIT ?');
$lignes->bindValue(1, $LOT, PDO::PARAM_INT);
$lignes->execute();
$maj = $db->prepare('UPDATE produits SET image = ? WHERE id = ?');

$slug = isset($CFG['slug']) ? $CFG['slug'] : '';
$ok = 0; $ko = 0; $absentes = 0; $poids = 0; $t0 = microtime(true);
$echecs = array();

foreach ($lignes->fetchAll(PDO::FETCH_NUM) as $r) {
	list($pid, $json) = $r;
	$urls = json_decode($json, true);
	if (!is_array($urls) || !count($urls)) continue;
	$pourquoi = null;
	$brut = recupere($slug, $urls[0], $pourquoi);
	if ($brut === null) {
		$ko++;
		if (count($echecs) < 5) $echecs[] = $pid . ' — ' . $pourquoi;
		usleep($PAUSE);
		continue;
	}
	if (!est_une_photo($brut)) {
		$absentes++;
		$maj->execute(array('', $pid));   // absence definitive, plus reessayee
		usleep($PAUSE);
		continue;
	}
	$img = reduit($brut, $COTE, $QUAL);
	if ($img === null) {
		$ko++;
		if (count($echecs) < 5) $echecs[] = $pid . ' (illisible)';
		usleep($PAUSE);
		continue;
	}
	$nom = $pid . '.jpg';
	if (file_put_contents($photos . '/' . $nom, $img) !== false) {
		$maj->execute(array($nom, $pid));
		$ok++; $poids += strlen($img);
	} else {
		$ko++;
		if (count($echecs) < 5) $echecs[] = $pid . ' (ecriture refusee)';
	}
	usleep($PAUSE);
}

$reste2 = (int)$db->query('SELECT COUNT(*) FROM produits'
	. ' WHERE image IS NULL AND images_n > 0')->fetchColumn();
$faites2 = $total - $reste2;
$pct = $total ? round(100 * $faites2 / $total) : 100;
$sec = max(0.1, microtime(true) - $t0);

$fini = ($reste2 === 0);
$bloque = ($ok === 0 && $absentes === 0 && $ko > 0);   // rien n'est passe : on arrete d'insister
$suite = 'photos.php?cle=' . rawurlencode($_GET['cle']) . '&n=' . $LOT;
$h = 'htmlspecialchars';
?>
<!doctype html>
<meta charset="utf-8">
<meta name="robots" content="noindex, nofollow">
<?php if (!$fini && !$bloque): ?><meta http-equiv="refresh" content="2; url=<?= $h($suite) ?>"><?php endif; ?>
<title>Photos — <?= $h(isset($CFG['nom']) ? $CFG['nom'] : '') ?></title>
<style>
 body{font:15px/1.6 system-ui,sans-serif;max-width:44rem;margin:3rem auto;padding:0 1.2rem;color:#1c1c1c}
 .barre{height:12px;background:#e6e6e6;border-radius:6px;overflow:hidden;margin:1rem 0}
 .barre i{display:block;height:100%;background:#136B54}
 code{background:#f2f2f2;padding:.1rem .3rem;border-radius:3px}
 .fini{background:#eef7f2;border:1px solid #bcdccd;padding:1rem 1.2rem;border-radius:6px}
 .ko{color:#8a2020}
</style>
<h1>Photos — <?= $h(isset($CFG['nom']) ? $CFG['nom'] : '') ?></h1>
<div class="barre"><i style="width:<?= $pct ?>%"></i></div>
<p><strong><?= number_format($faites2, 0, ',', ' ') ?></strong> photos sur
   <strong><?= number_format($total, 0, ',', ' ') ?></strong> — <?= $pct ?> %.</p>
<p>Ce lot : <?= $ok ?> deposees, <?php if ($ko): ?><span class="ko"><?= $ko ?> echecs</span><?php else: ?>0 echec<?php endif; ?>,
   <?= number_format($poids / 1048576, 1, ',', ' ') ?> Mo, <?= round($sec) ?> s.</p>
<?php if ($absentes): ?>
<p><?= $absentes ?> fiche(s) pour lesquelles la source n'a pas de photo : elle
repond bien, mais avec son propre dessin de remplacement. La vignette dessinee
de la boutique est conservee, et ces fiches ne seront plus reessayees.</p>
<?php endif; ?>
<?php if ($echecs): ?><p class="ko">Fiches en echec : <?= $h(implode(', ', $echecs)) ?></p><?php endif; ?>
<?php if ($bloque): ?>
 <div class="fini" style="background:#fdf0f0;border-color:#e3bcbc">
  <p><strong>Arrete : aucune photo de ce lot n'est passee.</strong> Ce n'est
  pas termine — le serveur d'origine a refuse les <?= $ko ?> requetes. La
  raison est indiquee ci-dessus, fiche par fiche. Rien n'est perdu : les
  <?= number_format($faites2, 0, ',', ' ') ?> photos deja deposees restent en
  place, et relancer la meme adresse reprendra a la meme fiche.</p>
  <p><a href="<?= $h($suite) ?>">Reessayer</a></p>
 </div>
<?php elseif ($fini): ?>
 <div class="fini">
  <p><strong>Termine.</strong><?php if ($reste2): ?> Il reste <?= $reste2 ?> fiches
  que la source ne rend pas — leur vignette dessinee est conservee, la boutique
  fonctionne.<?php endif; ?></p>
  <p>Derniere etape : remettre <code>'cle_photos' =&gt; ''</code> dans
  <code>config.php</code>, ou supprimer <code>photos.php</code>. Un script qui
  ecrit sur le disque n'a pas a rester accessible en ligne.</p>
 </div>
<?php else: ?>
 <p>Lot suivant dans 2 secondes. Vous pouvez fermer cet onglet : rouvrir la
 meme adresse reprend au bon endroit.</p>
 <p><a href="<?= $h($suite) ?>">Continuer maintenant</a></p>
<?php endif; ?>
