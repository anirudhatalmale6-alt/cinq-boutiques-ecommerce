<?php
$champs = array(
	'nom' => array('nom', 'text', true, 'name'),
	'courriel' => array('courriel', 'email', true, 'email'),
	'telephone' => array('telephone', 'tel', false, 'tel'),
	'adresse' => array('adresse', 'text', true, 'street-address'),
	'ville' => array('ville', 'text', true, 'address-level2'),
	'code_postal' => array('code_postal', 'text', true, 'postal-code'),
	'pays' => array('pays', 'text', true, 'country-name'),
);
?>
<div class="fil">
  <a href="<?= h(u('panier')) ?>"><?= h(t('panier')) ?></a>
  <span>›</span>
  <span><?= h(t('commander')) ?></span>
</div>

<div class="commande">
  <form class="cmd-form" method="post" action="<?= h(u('commande')) ?>" novalidate>
    <input type="hidden" name="jeton" value="<?= h(jeton()) ?>">
    <h1 class="bloc-titre"><?= h(t('coordonnees')) ?></h1>

    <?php foreach ($champs as $k => $d): list($lib, $type, $req, $ac) = $d; ?>
      <label class="champ<?= isset($erreurs[$k]) ? ' erreur' : '' ?>">
        <span><?= h(t($lib)) ?><?= $req ? ' *' : '' ?></span>
        <input type="<?= h($type) ?>" name="<?= h($k) ?>" autocomplete="<?= h($ac) ?>"
               value="<?= h($client[$k]) ?>"
               <?= isset($erreurs[$k]) ? 'aria-invalid="true"' : '' ?>>
        <?php if (isset($erreurs[$k])): ?>
          <em class="msg"><span aria-hidden="true">✕</span> <?= h($erreurs[$k]) ?></em>
        <?php endif; ?>
      </label>
    <?php endforeach; ?>

    <label class="champ">
      <span><?= h(t('note_commande')) ?></span>
      <textarea name="note" rows="3"><?= h($client['note']) ?></textarea>
    </label>

    <div class="mur">
      <b><?= h(t('paiement_titre')) ?></b>
      <p><?= h(t('paiement_texte')) ?></p>
    </div>

    <button type="submit" class="btn btn-plein btn-large"><?= h(t('valider')) ?></button>
  </form>

  <aside class="cmd-recap">
    <h2><?= h(t('recap')) ?></h2>
    <ul class="recap-liste">
      <?php foreach ($detail['lignes'] as $l): ?>
        <li>
          <span class="rl-q"><?= (int)$l['qte'] ?>×</span>
          <span class="rl-n"><?= h(extrait($l['produit']['titre'], 46)) ?></span>
          <span class="rl-p"><?= h(prix($l['total'])) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
    <div class="totaux">
      <div><span><?= h(t('sous_total')) ?></span><b><?= h(prix($detail['sous_total'])) ?></b></div>
      <div><span><?= h(t('livraison')) ?></span><b><?= $detail['livraison'] ? h(prix($detail['livraison'])) : h(t('offerte')) ?></b></div>
      <div class="tot"><span><?= h(t('total')) ?></span><b><?= h(prix($detail['total'])) ?></b></div>
    </div>
  </aside>
</div>
