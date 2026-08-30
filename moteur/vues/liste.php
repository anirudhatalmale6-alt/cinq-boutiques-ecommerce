<?php
$res = $res;
$q_actuel = isset($q) ? $q : '';
// On rejoue les parametres courants dans chaque lien : changer le tri ne doit
// pas effacer le filtre de marque, et inversement.
$params = array();
foreach (array('sous', 'marque', 'prix_min', 'prix_max', 'tri', 'vendables', 'q') as $k) {
	if (isset($_GET[$k]) && $_GET[$k] !== '') $params[$k] = $_GET[$k];
}
$chemin = $rayon ? 'rayon/' . $rayon['slug'] : 'recherche';
function lien_avec($chemin, $params, $modifs) {
	foreach ($modifs as $k => $v) {
		if ($v === null || $v === '') unset($params[$k]); else $params[$k] = $v;
	}
	unset($params['page']);
	return u($chemin, $params);
}
?>
<div class="fil">
  <a href="<?= h(u('')) ?>"><?= h(t('accueil')) ?></a>
  <span>›</span>
  <span><?= h($titre) ?></span>
</div>

<div class="liste">

  <aside class="filtres">
    <details class="filtres-pli" open>
    <summary class="filtres-titre"><?= h(t('filtres')) ?></summary>

    <?php if ($rayon && $sous): ?>
      <div class="filtre-bloc">
        <h3><?= h(t('rayon')) ?></h3>
        <ul class="filtre-liste">
          <li><a href="<?= h(lien_avec($chemin, $params, array('sous' => null))) ?>"
                 class="<?= $sous_actif ? '' : 'actif' ?>"><?= h(t('toutes')) ?></a></li>
          <?php foreach ($sous as $s): ?>
            <li><a href="<?= h(lien_avec($chemin, $params, array('sous' => $s['slug']))) ?>"
                   class="<?= ($sous_actif === $s['slug']) ? 'actif' : '' ?>">
              <?= h($s['nom']) ?> <span class="n"><?= (int)$s['n'] ?></span></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($marques): ?>
      <div class="filtre-bloc">
        <h3><?= h(t('marques')) ?></h3>
        <ul class="filtre-liste filtre-defile">
          <li><a href="<?= h(lien_avec($chemin, $params, array('marque' => null))) ?>"
                 class="<?= empty($_GET['marque']) ? 'actif' : '' ?>"><?= h(t('toutes')) ?></a></li>
          <?php foreach ($marques as $m): ?>
            <li><a href="<?= h(lien_avec($chemin, $params, array('marque' => $m['marque']))) ?>"
                   class="<?= (isset($_GET['marque']) && $_GET['marque'] === $m['marque']) ? 'actif' : '' ?>">
              <?= h($m['marque']) ?> <span class="n"><?= (int)$m['n'] ?></span></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div class="filtre-bloc">
      <h3><?= h(t('prix')) ?></h3>
      <form method="get" action="<?= h(u($chemin)) ?>" class="filtre-prix">
        <?php if (empty($CFG['reecriture'])): ?><input type="hidden" name="r" value="<?= h($chemin) ?>"><?php endif; ?>
        <?php foreach ($params as $k => $v): if ($k === 'prix_min' || $k === 'prix_max') continue; ?>
          <input type="hidden" name="<?= h($k) ?>" value="<?= h($v) ?>">
        <?php endforeach; ?>
        <div class="prix-champs">
          <input type="number" step="0.01" min="0" name="prix_min" inputmode="decimal"
                 placeholder="<?= h(t('min')) ?>" value="<?= h(isset($_GET['prix_min']) ? $_GET['prix_min'] : '') ?>">
          <span>—</span>
          <input type="number" step="0.01" min="0" name="prix_max" inputmode="decimal"
                 placeholder="<?= h(t('max')) ?>" value="<?= h(isset($_GET['prix_max']) ? $_GET['prix_max'] : '') ?>">
        </div>
        <?php if ($bornes && $bornes['a'] !== null): ?>
          <p class="filtre-aide"><?= h(prix($bornes['a'])) ?> — <?= h(prix($bornes['b'])) ?></p>
        <?php endif; ?>
        <label class="case">
          <input type="checkbox" name="vendables" value="1" <?= !empty($_GET['vendables']) ? 'checked' : '' ?>>
          <?= h(t('vendables_seulement')) ?>
        </label>
        <button type="submit" class="btn btn-plein btn-large"><?= h(t('appliquer')) ?></button>
      </form>
      <?php if ($params): ?>
        <a class="lien-effacer" href="<?= h(u($chemin, $q_actuel !== '' ? array('q' => $q_actuel) : array())) ?>"><?= h(t('effacer')) ?></a>
      <?php endif; ?>
    </div>
    </details>
  </aside>

  <div class="resultats">
    <div class="barre">
      <h1 class="barre-titre"><?= h($titre) ?>
        <span class="barre-n"><?= number_format((int)$res['total'], 0, ',', ' ') ?>
          <?= h($res['total'] > 1 ? t('produits') : t('produit')) ?></span>
      </h1>
      <div class="barre-tri">
        <label for="tri"><?= h(t('trier')) ?></label>
        <select id="tri" data-base="<?= h(lien_avec($chemin, $params, array('tri' => '__T__'))) ?>">
          <?php foreach (array('pertinence', 'prix_asc', 'prix_desc', 'nom', 'note') as $k): ?>
            <option value="<?= h($k) ?>" <?= ((isset($_GET['tri']) ? $_GET['tri'] : 'pertinence') === $k) ? 'selected' : '' ?>>
              <?= h(t('tri_' . $k)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <?php if (!$res['lignes']): ?>
      <div class="vide">
        <p class="vide-titre"><?= h(t('aucun_resultat')) ?></p>
        <p><?= h(t('aucun_resultat_aide')) ?></p>
      </div>
    <?php else: ?>
      <div class="grille">
        <?php foreach ($res['lignes'] as $c) { include __DIR__ . '/carte.php'; } ?>
      </div>

      <?php if ($res['pages'] > 1): ?>
        <nav class="pages" aria-label="<?= h(t('page')) ?>">
          <?php
          $p = (int)$res['page'];
          $params_p = $params;
          $lien = function ($n) use ($chemin, $params_p) {
            $params_p['page'] = $n;
            return u($chemin, $params_p);
          };
          ?>
          <a class="page-btn<?= $p <= 1 ? ' off' : '' ?>"
             <?= $p > 1 ? 'href="' . h($lien($p - 1)) . '"' : '' ?>><?= h(t('precedent')) ?></a>
          <?php
          $debut = max(1, $p - 2);
          $fin = min((int)$res['pages'], $debut + 4);
          $debut = max(1, $fin - 4);
          if ($debut > 1): ?>
            <a class="page-num" href="<?= h($lien(1)) ?>">1</a>
            <?php if ($debut > 2): ?><span class="page-pts">…</span><?php endif; ?>
          <?php endif; ?>
          <?php for ($i = $debut; $i <= $fin; $i++): ?>
            <a class="page-num<?= $i === $p ? ' actif' : '' ?>" href="<?= h($lien($i)) ?>"><?= $i ?></a>
          <?php endfor; ?>
          <?php if ($fin < (int)$res['pages']): ?>
            <?php if ($fin < (int)$res['pages'] - 1): ?><span class="page-pts">…</span><?php endif; ?>
            <a class="page-num" href="<?= h($lien((int)$res['pages'])) ?>"><?= (int)$res['pages'] ?></a>
          <?php endif; ?>
          <a class="page-btn<?= $p >= (int)$res['pages'] ? ' off' : '' ?>"
             <?= $p < (int)$res['pages'] ? 'href="' . h($lien($p + 1)) . '"' : '' ?>><?= h(t('suivant')) ?></a>
        </nav>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
