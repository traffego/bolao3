<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="<?= APP_URL ?>/admin" class="brand-link">
        <img src="<?= APP_URL ?>/assets/img/logo.png" alt="Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
        <span class="brand-text font-weight-light">Bolão Vitimba</span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Sidebar Menu -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                <!-- Dashboard -->
                <li class="nav-item">
                    <a href="<?= APP_URL ?>/admin" class="nav-link">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>Dashboard</p>
                    </a>
                </li>

                <!-- Bolões -->
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-trophy"></i>
                        <p>
                            Bolões
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="<?= APP_URL ?>/admin/boloes.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Listar Bolões</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?= APP_URL ?>/admin/criar-bolao.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Criar Bolão</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Resultados -->
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-futbol"></i>
                        <p>
                            Resultados
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="<?= APP_URL ?>/admin/gerenciar-resultados.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Gerenciar Resultados</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?= APP_URL ?>/admin/atualizar-jogos.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Atualizar Agora</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Usuários -->
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-users"></i>
                        <p>
                            Usuários
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="<?= APP_URL ?>/admin/usuarios.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Listar Usuários</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?= APP_URL ?>/admin/afiliados.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Afiliados</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Configurações -->
                <li class="nav-item">
                    <a href="<?= APP_URL ?>/admin/configuracoes.php" class="nav-link">
                        <i class="nav-icon fas fa-cog"></i>
                        <p>Configurações</p>
                    </a>
                </li>

                <!-- Sair -->
                <li class="nav-item">
                    <a href="<?= APP_URL ?>/admin/logout.php" class="nav-link">
                        <i class="nav-icon fas fa-sign-out-alt"></i>
                        <p>Sair</p>
                    </a>
                </li>
            </ul>
        </nav>
        <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
</aside> 