</main>
<footer class="site-footer">
    <div class="container footer-inner">
        <p>© <?php echo date('Y'); ?> <?php echo e(SITE_NAME); ?> · Hyperlocal · Group ordering · Smart ETA</p>
    </div>
</footer>
<script>window.APP_BASE = <?php echo json_encode(BASE_URL, JSON_THROW_ON_ERROR); ?>;</script>
<?php
$__toast = flash('toast');
if ($__toast): ?>
<script>document.addEventListener('DOMContentLoaded',function(){if(window.showToast)showToast(<?php echo json_encode($__toast, JSON_THROW_ON_ERROR); ?>,'success');});</script>
<?php endif; ?>
<script src="<?php echo e(BASE_URL); ?>/assets/js/main.js"></script>
</body>
</html>
