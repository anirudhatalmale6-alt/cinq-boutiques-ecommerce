<section class="hero">
  <div class="hero-h">
    <h1><?= h($CFG['titre_accueil']) ?></h1>
    <p><?= h($CFG['baseline']) ?></p>
    <div class="hero-chiffres">
      <span><b><?= number_format((int)meta('produits'), 0, ',', ' ') ?></b> <?= h(t('produits')) ?></span>
      <span><b><?= (int)meta('rayons') ?></b> <?= h(mb_strtolower(t('rayons'), 'UTF-8')) ?></span>
      <?php if ((int)meta('marques')): ?>
        <span><b><?= number_format((int)meta('marques'), 0, ',', ' ') ?></b> <?= h(mb_strtolower(t('marques'), 'UTF-8')) ?></span>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="bloc">
  <h2 class="bloc-titre"><?= h(t('rayons')) ?></h2>
  <div class="rayons-grille">
    <?php foreach (rayons() as $r): ?>
      <a class="rayon-tuile" href="<?= h(u('rayon/' . $r['slug'])) ?>">
        <span class="rayon-nom"><?= h($r['nom']) ?></span>
        <span class="rayon-n"><?= number_format((int)$r['n'], 0, ',', ' ') ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<section class="bloc">
  <h2 class="bloc-titre"><?= h(t('selection')) ?></h2>
  <div class="grille">
    <?php foreach ($vitrine as $c) { include __DIR__ . '/carte.php'; } ?>
  </div>
</section>
