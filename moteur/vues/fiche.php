<?php
$px = prix($p['prix_cents']);
$specs = json_decode($p['specs'], true);
if (!is_array($specs)) $specs = array();

// La fiche montre ce qui est FACTUEL : marque, poids, unite, code, origine.
// Rien n'est comble : un champ absent est ecrit « non renseigne » plutot
// qu'efface, pour qu'on voie ce qui reste a saisir.
$car = array();
if ($p['marque'])   $car[t('marque')] = $p['marque'];
if ($p['vendeur'])  $car[t('producteur')] = $p['vendeur'];
if ($p['poids'])    $car[t('poids')] = $p['poids'];
if ($p['unite'] && $p['unite'] !== $p['poids']) $car[t('unite')] = $p['unite'];
if ($p['origine'])  $car[t('origine')] = $p['origine'];
if ($p['sku'])      $car[t('reference')] = $p['sku'];
if ($p['upc'])      $car[t('code_barres')] = $p['upc'];
foreach ($specs as $k => $v) { $car[$k] = $v; }
$car[t('rayon')] = $p['rayon'] . ($p['sous_rayon'] ? ' · ' . $p['sous_rayon'] : '');
?>
<div class="fil">
  <a href="<?= h(u('')) ?>"><?= h(t('accueil')) ?></a>
  <span>›</span>
  <a href="<?= h(u('rayon/' . $p['rayon_slug'])) ?>"><?= h($p['rayon']) ?></a>
  <span>›</span>
  <span><?= h(extrait($p['titre'], 60)) ?></span>
</div>

<div class="fiche">
  <div class="fiche-visuel">
    <img src="<?= h(image_produit($p)) ?>" alt="<?= h($p['titre']) ?>" width="640" height="640">
    <?php if (empty($p['image'])): ?>
      <p class="fiche-visuel-note"><?= h(t('photo_absente')) ?><?php
        if ((int)$p['images_n']): ?> <?= (int)$p['images_n'] ?> <?= h(t('photos_source')) ?>.<?php endif; ?></p>
    <?php endif; ?>
  </div>

  <div class="fiche-infos">
    <?php if ($p['marque']): ?><div class="fiche-marque"><a href="<?= h(u('rayon/' . $p['rayon_slug'], array('marque' => $p['marque']))) ?>"><?= h($p['marque']) ?></a></div><?php endif; ?>
    <h1 class="fiche-titre"><?= h($p['titre']) ?></h1>

    <?php if ($p['note'] !== null): ?>
      <div class="fiche-note">
        <span class="etoiles" style="--n:<?= (float)$p['note'] ?>"></span>
        <span><?= h(nombre($p['note'])) ?><?php
          if ($p['avis']): ?> · <?= (int)$p['avis'] ?>
          <?= h((int)$p['avis'] > 1 ? t('avis') : t('avis_un')) ?><?php endif; ?></span>
      </div>
    <?php endif; ?>

    <div class="fiche-prix<?= $px === null ? ' sans' : '' ?>">
      <?= h($px === null ? t('prix_sur_demande') : $px) ?>
      <?php if ($p['unite']): ?><small><?= h($p['unite']) ?></small><?php endif; ?>
    </div>

    <?php if ($px !== null): ?>
      <form method="post" action="<?= h(u('panier')) ?>" class="fiche-achat">
        <input type="hidden" name="jeton" value="<?= h(jeton()) ?>">
        <input type="hidden" name="action" value="ajouter">
        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
        <label class="qte">
          <span><?= h(t('quantite')) ?></span>
          <input type="number" name="qte" value="1" min="1" max="99">
        </label>
        <button type="submit" class="btn btn-plein btn-large"><?= h(t('ajouter')) ?></button>
      </form>
    <?php else: ?>
      <p class="avert"><?= h(t('prix_sur_demande')) ?> — <?= h(t('fiche_incomplete')) ?>.</p>
    <?php endif; ?>

    <div class="fiche-bloc">
      <h2><?= h(t('caracteristiques')) ?></h2>
      <table class="specs">
        <?php foreach ($car as $k => $v): ?>
          <tr><th><?= h($k) ?></th><td><?= h($v) ?></td></tr>
        <?php endforeach; ?>
      </table>
    </div>

    <?php if ($p['ingredients']): ?>
      <div class="fiche-bloc">
        <h2><?= h(t('ingredients')) ?></h2>
        <p class="ingr"><?= h($p['ingredients']) ?></p>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($suggestions): ?>
  <section class="bloc">
    <h2 class="bloc-titre"><?= h(t('a_decouvrir')) ?></h2>
    <div class="grille">
      <?php foreach ($suggestions as $c) { include __DIR__ . '/carte.php'; } ?>
    </div>
  </section>
<?php endif; ?>
