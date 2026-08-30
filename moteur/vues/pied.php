</main>

<footer class="pied">
  <div class="pied-h">
    <div>
      <div class="pied-titre"><?= h($CFG['nom']) ?></div>
      <p class="pied-txt"><?= h($CFG['baseline']) ?></p>
    </div>
    <div>
      <div class="pied-titre"><?= h(t('rayons')) ?></div>
      <ul class="pied-liste">
        <?php foreach (array_slice(rayons(), 0, 6) as $r): ?>
          <li><a href="<?= h(u('rayon/' . $r['slug'])) ?>"><?= h($r['nom']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div>
      <div class="pied-titre"><?= h(t('catalogue')) ?></div>
      <p class="pied-txt">
        <?= number_format((int)meta('produits'), 0, ',', ' ') ?> <?= h(t('produits')) ?><br>
        <?= (int)meta('rayons') ?> <?= h(mb_strtolower(t('rayons'), 'UTF-8')) ?><?php
        if ((int)meta('marques')): ?><br><?= number_format((int)meta('marques'), 0, ',', ' ') ?> <?= h(mb_strtolower(t('marques'), 'UTF-8')) ?><?php endif; ?>
      </p>
    </div>
  </div>
  <div class="pied-bas">
    <span><?= h($CFG['nom']) ?><?php if (!empty($CFG['nom_provisoire'])): ?>
      — <?= h(t('nom_provisoire')) ?><?php endif; ?></span>
  </div>
</footer>

<script src="<?= h(base_url()) ?>/static/boutique.js" defer></script>
</body>
</html>
