</main>
<?php if (!empty($showFooter) && $showFooter): ?>
<footer class="public-footer">
    <div class="container footer-inner">
        <div class="footer-brand">
            <a href="<?= url('index.php') ?>" class="logo"><?= siteLogoImg('site-logo site-logo--footer') ?></a>
            <p>Learning management for modern educational institutions.</p>
        </div>
        <div class="footer-links">
            <a href="<?= url('register/school.php') ?>">Register school</a>
            <a href="<?= url('login.php') ?>">School login</a>
            <a href="<?= url('index.php#schools') ?>">Browse schools</a>
        </div>
        <p class="footer-copy">&copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. All rights reserved.</p>
        <p class="footer-version">System version <?= e(APP_VERSION) ?></p>
    </div>
</footer>
<?php endif; ?>
<?php require __DIR__ . '/scripts.php'; ?>
</body>
</html>
