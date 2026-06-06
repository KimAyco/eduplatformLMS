<div id="pageLoader" class="page-loader" aria-hidden="true" role="status">
    <div class="page-loader-inner">
        <?= siteLogoImg('site-logo site-logo--loader') ?>
        <span class="page-loader-text">Loading…</span>
    </div>
</div>

<div id="confirmOverlay" class="confirm-overlay" hidden>
    <div class="confirm-dialog" role="alertdialog" aria-modal="true" aria-labelledby="confirmTitle">
        <h3 id="confirmTitle" class="confirm-dialog-title">Confirm action</h3>
        <p id="confirmMessage" class="confirm-dialog-message"></p>
        <div class="confirm-dialog-actions">
            <button type="button" class="btn btn-secondary" id="confirmCancel">Cancel</button>
            <button type="button" class="btn btn-danger" id="confirmOk">Confirm</button>
        </div>
    </div>
</div>
