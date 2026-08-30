<?php
// Une carte produit. Attend $c (une ligne de la table produits).
$px = prix($c['prix_cents']);
?>
<article class="carte<?= $c['complet'] ? '' : ' carte-incomplete' ?>">
  <a class="carte-img" href="<?= h(lien_produit($c)) ?>">
    <img src="<?= h(image_produit($c)) ?>" alt="" loading="lazy" width="300" height="300">
    <?php if (!$c['complet']): ?>
      <span class="etiq"><?= h(t('fiche_incomplete')) ?></span>
    <?php endif; ?>
  </a>
  <div class="carte-corps">
    <?php if ($c['marque']): ?>
      <div class="carte-marque"><?= h($c['marque']) ?></div>
    <?php elseif ($c['vendeur']): ?>
      <div class="carte-marque"><?= h($c['vendeur']) ?></div>
    <?php endif; ?>
    <h3 class="carte-titre"><a href="<?= h(lien_produit($c)) ?>"><?= h($c['titre']) ?></a></h3>
    <?php if ($c['unite'] || $c['poids']): ?>
      <div class="carte-unite"><?= h($c['unite'] ? $c['unite'] : $c['poids']) ?></div>
    <?php endif; ?>
    <?php if ($c['note'] !== null): ?>
      <div class="carte-note" title="<?= h(t('note')) ?>">
        <span class="etoiles" style="--n:<?= (float)$c['note'] ?>"></span>
        <span><?= h(nombre($c['note'])) ?><?php
          if ($c['avis']): ?> · <?= (int)$c['avis'] ?>
          <?= h((int)$c['avis'] > 1 ? t('avis') : t('avis_un')) ?><?php endif; ?></span>
      </div>
    <?php endif; ?>
    <div class="carte-bas">
      <div class="carte-prix<?= $px === null ? ' sans' : '' ?>">
        <?= h($px === null ? t('prix_sur_demande') : $px) ?>
      </div>
      <?php if ($px !== null): ?>
        <form method="post" action="<?= h(u('panier')) ?>" class="ajout">
          <input type="hidden" name="jeton" value="<?= h(jeton()) ?>">
          <input type="hidden" name="action" value="ajouter">
          <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
          <button type="submit" class="btn btn-mini" aria-label="<?= h(t('ajouter')) ?>">+</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</article>
