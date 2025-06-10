/**
 * Main JavaScript file for Bolão Vitimba
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Format phone number inputs
    const phoneInputs = document.querySelectorAll('input[type="tel"]');
    phoneInputs.forEach(function(input) {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 11) {
                if (value.length > 2) {
                    value = '(' + value.substring(0, 2) + ') ' + value.substring(2);
                }
                if (value.length > 10) {
                    value = value.substring(0, 10) + '-' + value.substring(10);
                }
                e.target.value = value;
            }
        });
    });
    
    // Handle palpite forms
    const palpiteForms = document.querySelectorAll('.palpite-form');
    palpiteForms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            const inputs = form.querySelectorAll('input[type="number"]');
            let valid = true;
            
            inputs.forEach(function(input) {
                if (input.value === '' || isNaN(parseInt(input.value))) {
                    input.classList.add('is-invalid');
                    valid = false;
                } else {
                    input.classList.remove('is-invalid');
                }
            });
            
            if (!valid) {
                e.preventDefault();
                alert('Por favor, preencha todos os placares corretamente.');
            }
        });
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
    
    // Password strength indicator
    const passwordField = document.getElementById('password');
    const strengthIndicator = document.getElementById('password-strength');
    
    if (passwordField && strengthIndicator) {
        passwordField.addEventListener('input', function() {
            const value = passwordField.value;
            let strength = 0;
            
            if (value.length >= 6) strength += 1;
            if (value.length >= 8) strength += 1;
            if (/[A-Z]/.test(value)) strength += 1;
            if (/[0-9]/.test(value)) strength += 1;
            if (/[^A-Za-z0-9]/.test(value)) strength += 1;
            
            // Update indicator
            if (strength <= 2) {
                strengthIndicator.className = 'progress-bar bg-danger';
                strengthIndicator.style.width = '33%';
                strengthIndicator.textContent = 'Fraca';
            } else if (strength <= 4) {
                strengthIndicator.className = 'progress-bar bg-warning';
                strengthIndicator.style.width = '66%';
                strengthIndicator.textContent = 'Média';
            } else {
                strengthIndicator.className = 'progress-bar bg-success';
                strengthIndicator.style.width = '100%';
                strengthIndicator.textContent = 'Forte';
            }
        });
    }
    
    // Copy affiliate code to clipboard
    const copyAffiliateBtn = document.getElementById('copy-affiliate-code');
    if (copyAffiliateBtn) {
        copyAffiliateBtn.addEventListener('click', function() {
            const affiliateCode = document.getElementById('affiliate-code');
            if (affiliateCode) {
                navigator.clipboard.writeText(affiliateCode.value).then(function() {
                    // Show success message
                    const tooltip = bootstrap.Tooltip.getInstance(copyAffiliateBtn);
                    copyAffiliateBtn.setAttribute('title', 'Código copiado!');
                    tooltip.dispose();
                    new bootstrap.Tooltip(copyAffiliateBtn).show();
                    
                    // Reset tooltip after 2 seconds
                    setTimeout(function() {
                        copyAffiliateBtn.setAttribute('title', 'Copiar código');
                        tooltip.dispose();
                        new bootstrap.Tooltip(copyAffiliateBtn);
                    }, 2000);
                });
            }
        });
    }
    
    // Field counter for textareas
    const textAreas = document.querySelectorAll('textarea[data-max-length]');
    textAreas.forEach(function(textarea) {
        const maxLength = parseInt(textarea.getAttribute('data-max-length'));
        const counterEl = document.createElement('small');
        counterEl.className = 'text-muted d-block text-end mt-1';
        counterEl.innerHTML = `0/${maxLength} caracteres`;
        textarea.parentNode.appendChild(counterEl);
        
        textarea.addEventListener('input', function() {
            const currentLength = textarea.value.length;
            counterEl.innerHTML = `${currentLength}/${maxLength} caracteres`;
            
            if (currentLength > maxLength) {
                counterEl.classList.add('text-danger');
                textarea.classList.add('is-invalid');
            } else {
                counterEl.classList.remove('text-danger');
                textarea.classList.remove('is-invalid');
            }
        });
    });
}); 