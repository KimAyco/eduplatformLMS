            </div>
        </main>
    </div>
</div>
<script src="<?= url('assets/js/app.js') ?>"></script>
<?php if (!empty($user['school_id'])): ?>
<script src="<?= url('assets/js/notifications.js') ?>"></script>
<?php endif; ?>
<?php if (!empty($pageScripts)): ?>
    <?php foreach ($pageScripts as $script): ?>
<script src="<?= url($script) ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
