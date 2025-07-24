            </div> <!-- /.container-fluid -->
        </section> <!-- /.content -->
    </div> <!-- /.content-wrapper -->
    
    <footer class="main-footer text-center">
        <strong>&copy; <?= date('Y') ?> Bolão Vitimba.</strong> Todos os direitos reservados.
    </footer>
</div> <!-- /.wrapper -->

<!-- JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

<?php if (isset($extraJs)): ?>
    <?= $extraJs ?>
<?php endif; ?>

</body>
</html> 