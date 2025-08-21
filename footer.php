</div>
    </main>

    <!-- Footer -->
    <footer class="bg-light py-4 mt-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h5 class="text-green mb-3">
                        <i class="bi bi-trophy-fill me-2"></i>Bolão do Brasileirão
                    </h5>
                    <p class="text-muted mb-0">
                        A melhor plataforma para criar e participar de bolões do Campeonato Brasileiro.
                        Faça seus palpites, acompanhe os resultados e ganhe prêmios!
                    </p>
                </div>
                <div class="col-lg-2 col-md-4">
                    <h6 class="text-green mb-3">Links Rápidos</h6>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <a href="<?= APP_URL ?>" class="text-muted text-decoration-none">
                                <i class="bi bi-house-fill me-2"></i>Início
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?= APP_URL ?>/boloes.php" class="text-muted text-decoration-none">
                                <i class="bi bi-ticket-fill me-2"></i>Bolões
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?= APP_URL ?>/ranking.php" class="text-muted text-decoration-none">
                                <i class="bi bi-star-fill me-2"></i>Ranking
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-4">
                    <h6 class="text-green mb-3">Ajuda</h6>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <a href="<?= APP_URL ?>/como-funciona.php" class="text-muted text-decoration-none">
                                <i class="bi bi-info-circle-fill me-2"></i>Como Funciona
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?= APP_URL ?>/regras.php" class="text-muted text-decoration-none">
                                <i class="bi bi-book-fill me-2"></i>Regras
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?= APP_URL ?>/contato.php" class="text-muted text-decoration-none">
                                <i class="bi bi-envelope-fill me-2"></i>Contato
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="col-lg-4 col-md-4">
                    <h6 class="text-green mb-3">Redes Sociais</h6>
                    <div class="d-flex gap-3">
                        <a href="#" class="text-muted text-decoration-none fs-5">
                            <i class="bi bi-facebook"></i>
                        </a>
                        <a href="#" class="text-muted text-decoration-none fs-5">
                            <i class="bi bi-instagram"></i>
                        </a>
                        <a href="#" class="text-muted text-decoration-none fs-5">
                            <i class="bi bi-twitter"></i>
                        </a>
                        <a href="#" class="text-muted text-decoration-none fs-5">
                            <i class="bi bi-whatsapp"></i>
                        </a>
                    </div>
                </div>
            </div>
            <hr class="my-4">
            <div class="row">
                <div class="col-md-6">
                    <p class="text-muted mb-md-0">
                        &copy; <?= date('Y') ?> Bolão do Brasileirão. Todos os direitos reservados.
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="<?= APP_URL ?>/privacidade.php" class="text-muted text-decoration-none me-3">
                        Política de Privacidade
                    </a>
                    <a href="<?= APP_URL ?>/termos.php" class="text-muted text-decoration-none">
                        Termos de Uso
                    </a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php
    // Incluir gerenciador de afiliação JavaScript
    if (function_exists('getReferralManagerScript')) {
        echo getReferralManagerScript();
    }
    ?>
</body>
</html>