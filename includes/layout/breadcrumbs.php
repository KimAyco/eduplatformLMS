<?php if (!empty($breadcrumbs)): ?>
<nav class="breadcrumbs" aria-label="Breadcrumb">
    <?php foreach ($breadcrumbs as $i => $crumb): ?>
        <?php if ($i > 0): ?><span class="breadcrumb-sep"><i class="fa-solid fa-chevron-right"></i></span><?php endif; ?>
        <?php if (!empty($crumb['url'])): ?>
            <a href="<?= url($crumb['url']) ?>"><?= e($crumb['label']) ?></a>
        <?php else: ?>
            <span class="breadcrumb-current"><?= e($crumb['label']) ?></span>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
<?php endif; ?>
