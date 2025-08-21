</div><!-- End of container -->

<!-- Footer -->
<footer class="bg-dark text-light py-4 mt-5">
    <div class="container">
        <div class="row">
            <div class="col-md-4 mb-3">
                <h5>Bolão Vitimba</h5>
                <p>O melhor sistema de bolões de futebol! Faça seus palpites e concorra a prêmios.</p>
            </div>
            <div class="col-md-4 mb-3">
                <h5>Links Úteis</h5>
                <ul class="list-unstyled">
                    <li><a href="<?= APP_URL ?>/boloes.php" class="text-light">Bolões Ativos</a></li>
                    <li><a href="<?= APP_URL ?>/como-funciona.php" class="text-light">Como Funciona</a></li>
                    <li><a href="<?= APP_URL ?>/termos.php" class="text-light">Termos de Uso</a></li>
                    <li><a href="<?= APP_URL ?>/privacidade.php" class="text-light">Política de Privacidade</a></li>
                </ul>
            </div>
            <div class="col-md-4 mb-3">
                <h5>Contato</h5>
                <ul class="list-unstyled">
                    <li><i class="bi bi-envelope"></i> contato@bolao.com</li>
                    <li><i class="bi bi-whatsapp"></i> (00) 00000-0000</li>
                    <li class="mt-3">
                        <a href="#" class="text-light me-2"><i class="bi bi-facebook fs-5"></i></a>
                        <a href="#" class="text-light me-2"><i class="bi bi-instagram fs-5"></i></a>
                        <a href="#" class="text-light me-2"><i class="bi bi-twitter fs-5"></i></a>
                    </li>
                </ul>
            </div>
        </div>
        <hr class="border-light">
        <div class="text-center">
            &copy; <?= date('Y') ?> Bolão Vitimba. Todos os direitos reservados.
        </div>
    </div>
</footer>

<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- jQuery (for certain plugins and features) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Custom JS -->
<script src="<?= APP_URL ?>/public/js/scripts.js"></script>

<!-- Sistema de Afiliados - Captura de Referência -->
<script src="<?= APP_URL ?>/public/js/ref-capture.js"></script>

<?php if (isset($extraJs)): ?>
    <?= $extraJs ?>
<?php endif; ?>

</body>
</html>