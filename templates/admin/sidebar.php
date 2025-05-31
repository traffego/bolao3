                <div class="sb-sidenav-menu-heading">Bolões</div>
                <a class="nav-link <?= $currentPage == 'boloes' ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/boloes.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-futbol"></i></div>
                    Gerenciar Bolões
                </a>
                <a class="nav-link <?= $currentPage == 'atualizar-jogos' ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/atualizar-jogos.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-sync-alt"></i></div>
                    Atualizar Resultados
                </a>
                <a class="nav-link <?= $currentPage == 'novo-bolao' ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/novo-bolao.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-plus"></i></div>
                    Novo Bolão
                </a> 