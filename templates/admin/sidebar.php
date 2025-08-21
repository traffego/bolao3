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

                <div class="sb-sidenav-menu-heading">Usuários</div>
                <a class="nav-link <?= $currentPage == 'usuarios' ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/usuarios.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-users"></i></div>
                    Gerenciar Usuários
                </a>
                <a class="nav-link <?= $currentPage == 'afiliados' ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/afiliados.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-handshake"></i></div>
                    Afiliados
                </a>

                <div class="sb-sidenav-menu-heading">Financeiro</div>
                <a class="nav-link <?= $currentPage == 'pagamentos' ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/pagamentos.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-cash-register"></i></div>
                    Pagamentos
                </a>
                <a class="nav-link <?= $currentPage == 'configuracoes-pagamento' ? 'active' : '' ?>" href="<?= APP_URL ?>/admin/configuracoes-pagamento.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-cog"></i></div>
                    Configurações de Pagamento
                </a>

                <div class="sb-sidenav-menu-heading">Sistema</div>
                <a class="nav-link" href="<?= APP_URL ?>/admin/logout.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-sign-out-alt"></i></div>
                    Sair
                </a>