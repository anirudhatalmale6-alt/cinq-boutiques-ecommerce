<?php $lignes = json_decode($c['lignes'], true); if (!is_array($lignes)) $lignes = array(); ?>
<section class="bloc bloc-etroit">
  <div class="merci">
    <div class="merci-signe" aria-hidden="true">✓</div>
    <h1><?= h(t('merci')) ?></h1>
    <p class="merci-ref"><?= h(t('votre_ref')) ?> <b><?= h($c['reference']) ?></b></p>
  </div>

  <div class="mur">
    <b><?= h(t('paiement_titre')) ?></b>
    <p><?= h(t('paiement_texte')) ?></p>
  </div>

  <h2 class="bloc-titre"><?= h(t('recap')) ?></h2>
  <ul class="recap-liste">
    <?php foreach ($lignes as $l): ?>
      <li>
        <span class="rl-q"><?= (int)$l['qte'] ?>×</span>
        <span class="rl-n"><?= h($l['titre']) ?></span>
        <span class="rl-p"><?= h(prix($l['prix_cents'] * $l['qte'])) ?></span>
      </li>
    <?php endforeach; ?>
  </ul>
  <div class="totaux">
    <div><span><?= h(t('sous_total')) ?></span><b><?= h(prix($c['sous_total'])) ?></b></div>
    <div><span><?= h(t('livraison')) ?></span><b><?= $c['livraison'] ? h(prix($c['livraison'])) : h(t('offerte')) ?></b></div>
    <div class="tot"><span><?= h(t('total')) ?></span><b><?= h(prix($c['total'])) ?></b></div>
  </div>

  <a class="btn btn-plein" href="<?= h(u('')) ?>"><?= h(t('continuer')) ?></a>
</section>
