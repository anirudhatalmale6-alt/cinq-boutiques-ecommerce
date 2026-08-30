<?php $n = panier_nb(); ?>
<!doctype html>
<html lang="<?= h($CFG['langue']) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($titre_page) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="<?= h(base_url()) ?>/static/style.css">
<link rel="icon" href="data:image/svg+xml,<?= rawurlencode(
	'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><rect width="32" height="32" rx="7" fill="' . $CFG['teinte'] . '"/><text x="16" y="17" text-anchor="middle" dominant-baseline="central" font-family="Helvetica,Arial" font-size="17" font-weight="700" fill="#fff">' . mb_substr($CFG['nom'], 0, 1, 'UTF-8') . '</text></svg>') ?>">
<style>:root{--teinte:<?= h($CFG['teinte']) ?>;--teinte-h:<?= (int)$CFG['teinte_h'] ?>;--teinte-s:<?= (int)$CFG['teinte_s'] ?>%}</style>
</head>
<body>

<a class="saut" href="#contenu"><?= h(t('catalogue')) ?></a>

<header class="tete">
  <div class="tete-h">
    <a class="logo" href="<?= h(u('')) ?>">
      <span class="logo-marque"><?= h(mb_substr($CFG['nom'], 0, 1, 'UTF-8')) ?></span>
      <span class="logo-nom"><?= h($CFG['nom']) ?></span>
    </a>

    <form class="rech" action="<?= h(u('recherche')) ?>" method="get" role="search">
      <?php if (empty($CFG['reecriture'])): ?><input type="hidden" name="r" value="recherche"><?php endif; ?>
      <input type="search" name="q" placeholder="<?= h(t('recherche_ph')) ?>"
             value="<?= h(isset($_GET['q']) ? $_GET['q'] : '') ?>" aria-label="<?= h(t('rechercher')) ?>">
      <button type="submit" aria-label="<?= h(t('rechercher')) ?>">
        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/><path d="M16.5 16.5 21 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      </button>
    </form>

    <a class="panier-lien" href="<?= h(u('panier')) ?>">
      <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M3 4h2.2l2.3 11.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.5L21 8H6.4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="10" cy="20" r="1.4" fill="currentColor"/><circle cx="18" cy="20" r="1.4" fill="currentColor"/></svg>
      <span><?= h(t('panier')) ?></span>
      <?php if ($n): ?><b class="pastille"><?= (int)$n ?></b><?php endif; ?>
    </a>
  </div>

  <nav class="rayons-nav" aria-label="<?= h(t('rayons')) ?>">
    <div class="rayons-h">
      <?php foreach (array_slice(rayons(), 0, 12) as $r): ?>
        <a href="<?= h(u('rayon/' . $r['slug'])) ?>"<?= (isset($rayon) && $rayon && $rayon['slug'] === $r['slug']) ? ' class="actif"' : '' ?>><?= h($r['nom']) ?></a>
      <?php endforeach; ?>
    </div>
  </nav>
</header>

<main id="contenu">
