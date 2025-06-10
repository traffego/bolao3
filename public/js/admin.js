/**
 * Admin JavaScript for Bolão Vitimba
 */

document.addEventListener('DOMContentLoaded', function() {
    // Set active navigation item
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.sidebar .nav-link');
    
    navLinks.forEach(link => {
        if (currentPath.includes(link.getAttribute('href'))) {
            link.classList.add('active');
        }
    });
    
    // Initialize all tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Auto-close alerts after 5 seconds
    const autoCloseAlerts = document.querySelectorAll('.alert-dismissible:not(.alert-persistent)');
    autoCloseAlerts.forEach(function(alert) {
        setTimeout(function() {
            if (alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, 5000);
    });
    
    // Confirm delete actions
    const deleteButtons = document.querySelectorAll('[data-confirm]');
    deleteButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            if (!confirm(button.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });
    
    // Handle form submission with validation
    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
    
    // File input preview
    const fileInputs = document.querySelectorAll('input[type="file"][data-preview]');
    fileInputs.forEach(function(input) {
        const previewElement = document.getElementById(input.getAttribute('data-preview'));
        if (previewElement) {
            input.addEventListener('change', function() {
                if (input.files && input.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewElement.src = e.target.result;
                    };
                    reader.readAsDataURL(input.files[0]);
                }
            });
        }
    });
    
    // Date range picker initialization (if exists)
    const dateRangePickers = document.querySelectorAll('.date-range-picker');
    if (dateRangePickers.length > 0 && typeof daterangepicker !== 'undefined') {
        dateRangePickers.forEach(function(picker) {
            $(picker).daterangepicker({
                locale: {
                    format: 'DD/MM/YYYY',
                    separator: ' - ',
                    applyLabel: 'Aplicar',
                    cancelLabel: 'Cancelar',
                    fromLabel: 'De',
                    toLabel: 'Até',
                    customRangeLabel: 'Personalizado',
                    weekLabel: 'S',
                    daysOfWeek: ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'],
                    monthNames: ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'],
                    firstDay: 1
                }
            });
        });
    }
    
    // JSON editor (if exists)
    const jsonEditors = document.querySelectorAll('textarea[data-json-editor]');
    jsonEditors.forEach(function(editor) {
        // Simple formatting for JSON
        editor.addEventListener('blur', function() {
            try {
                const value = editor.value.trim();
                if (value) {
                    const jsonObj = JSON.parse(value);
                    editor.value = JSON.stringify(jsonObj, null, 2);
                    editor.classList.remove('is-invalid');
                }
            } catch (e) {
                editor.classList.add('is-invalid');
            }
        });
    });
    
    // API Football search team functionality
    const teamSearchForm = document.getElementById('team-search-form');
    const teamSearchResults = document.getElementById('team-search-results');
    
    if (teamSearchForm && teamSearchResults) {
        teamSearchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const searchTerm = document.getElementById('team-search-input').value;
            if (!searchTerm) return;
            
            // Display loading
            teamSearchResults.innerHTML = '<div class="text-center p-3"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Buscando equipes...</p></div>';
            
            // Make AJAX request
            fetch(`${APP_URL}/admin/api/search-teams.php?term=${encodeURIComponent(searchTerm)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.teams.length > 0) {
                        let html = '<div class="list-group">';
                        data.teams.forEach(team => {
                            html += `
                                <a href="#" class="list-group-item list-group-item-action team-select" 
                                   data-id="${team.id}" data-name="${team.name}" data-logo="${team.logo}">
                                    <div class="d-flex align-items-center">
                                        <img src="${team.logo}" alt="${team.name}" class="team-logo me-3">
                                        <div>
                                            <h6 class="mb-0">${team.name}</h6>
                                            <small class="text-muted">${team.country}</small>
                                        </div>
                                    </div>
                                </a>`;
                        });
                        html += '</div>';
                        teamSearchResults.innerHTML = html;
                        
                        // Add event listeners for team selection
                        document.querySelectorAll('.team-select').forEach(item => {
                            item.addEventListener('click', function(e) {
                                e.preventDefault();
                                const id = this.getAttribute('data-id');
                                const name = this.getAttribute('data-name');
                                const logo = this.getAttribute('data-logo');
                                
                                // Fill hidden inputs
                                document.getElementById('team_id').value = id;
                                document.getElementById('team_name').value = name;
                                document.getElementById('team_logo').value = logo;
                                
                                // Update display
                                document.getElementById('selected-team-name').textContent = name;
                                document.getElementById('selected-team-logo').src = logo;
                                document.getElementById('selected-team').classList.remove('d-none');
                                
                                // Clear search results
                                teamSearchResults.innerHTML = '';
                                document.getElementById('team-search-input').value = '';
                            });
                        });
                    } else {
                        teamSearchResults.innerHTML = '<div class="alert alert-warning">Nenhuma equipe encontrada com esse termo.</div>';
                    }
                })
                .catch(error => {
                    teamSearchResults.innerHTML = '<div class="alert alert-danger">Erro ao buscar equipes. Tente novamente.</div>';
                    console.error('Error:', error);
                });
        });
    }
}); 