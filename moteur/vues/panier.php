<div class="fil">
  <a href="<?= h(u('')) ?>"><?= h(t('accueil')) ?></a>
  <span>›</span>
  <span><?= h(t('panier')) ?></span>
</div>

<section class="bloc bloc-etroit">
  <h1 class="bloc-titre"><?= h(t('panier')) ?></h1>

  <?php if ($refus): ?>
    <p class="avert"><?= h(t('prix_sur_demande')) ?> — <?= h(t('fiche_incomplete')) ?>.</p>
  <?php endif; ?>

  <?php if (!$detail['lignes']): ?>
    <div class="vide">
      <p class="vide-titre"><?= h(t('panier_vide')) ?></p>
      <a class="btn btn-plein" href="<?= h(u('')) ?>"><?= h(t('continuer')) ?></a>
    </div>
  <?php else: ?>
    <form method="post" action="<?= h(u('panier')) ?>">
      <input type="hidden" name="jeton" value="<?= h(jeton()) ?>">
      <input type="hidden" name="action" value="majliste">
      <table class="tab-panier">
        <tbody>
        <?php foreach ($detail['lignes'] as $l): $p = $l['produit']; ?>
          <tr>
            <td class="tp-img">
              <a href="<?= h(lien_produit($p)) ?>">
                <img src="<?= h(image_produit($p)) ?>" alt="" width="72" height="72" loading="lazy">
              </a>
            </td>
            <td class="tp-nom">
              <a href="<?= h(lien_produit($p)) ?>"><?= h($p['titre']) ?></a>
              <?php if ($p['marque'] || $p['unite']): ?>
                <div class="tp-sous"><?= h(trim($p['marque'] . ' ' . ($p['unite'] ? '· ' . $p['unite'] : ''))) ?></div>
              <?php endif; ?>
            </td>
            <td class="tp-pu"><?= h(prix($p['prix_cents'])) ?></td>
            <td class="tp-qte">
              <input type="number" name="qte[<?= (int)$p['id'] ?>]" value="<?= (int)$l['qte'] ?>"
                     min="0" max="99" aria-label="<?= h(t('quantite')) ?>">
            </td>
            <td class="tp-total"><?= h(prix($l['total'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <div class="panier-actions">
        <button type="submit" class="btn"><?= h(t('mettre_a_jour')) ?></button>
      </div>
    </form>

    <div class="totaux">
      <div><span><?= h(t('sous_total')) ?></span><b><?= h(prix($detail['sous_total'])) ?></b></div>
      <div><span><?= h(t('livraison')) ?></span><b><?= $detail['livraison'] ? h(prix($detail['livraison'])) : h(t('offerte')) ?></b></div>
      <div class="tot"><span><?= h(t('total')) ?></span><b><?= h(prix($detail['total'])) ?></b></div>
      <a class="btn btn-plein btn-large" href="<?= h(u('commande')) ?>"><?= h(t('commander')) ?></a>
      <a class="lien-effacer" href="<?= h(u('')) ?>"><?= h(t('continuer')) ?></a>
    </div>
  <?php endif; ?>
</section>
