            <footer class="pt-5 d-flex justify-content-between">
                <span>Copyright © <?= date('Y') ?> <?= APP_NAME ?></span>
                <span>v<?= APP_VERSION ?></span>
            </footer>
        </main>
    </div>
</div>

<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Admin JavaScript -->
<script src="<?= APP_URL ?>/public/js/admin.js"></script>

<?php if (isset($extraJs)): ?>
    <?= $extraJs ?>
<?php endif; ?>

</body>
</html> 