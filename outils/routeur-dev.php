<?php
// Routeur pour « php -S », uniquement pour essayer la boutique en local.
// En production c'est le .htaccess qui fait ce travail ; ce fichier n'a pas
// besoin d'etre deploye.
//
//   php -S 127.0.0.1:8800 -t sites/panier outils/routeur-dev.php
//
// Il rend false pour les fichiers qui existent (CSS, JS, photos) afin que
// le serveur integre les serve lui-meme, et passe tout le reste a index.php.

$chemin = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$fichier = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . $chemin;

if ($chemin !== '/' && is_file($fichier)) {
	// Le catalogue et les commandes ne se telechargent pas, comme en prod.
	if (strpos($chemin, '/donnees/') === 0) {
		http_response_code(403);
		exit('403');
	}
	return false;
}

require rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/index.php';
