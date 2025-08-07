// Configuração dos campeonatos
const CAMPEONATOS = {
    BRASILEIRAO_A: 71,
    BRASILEIRAO_B: 72,
    BRASILEIRAO_C: 75,
    BRASILEIRAO_D: 76,
    COPA_BRASIL: 73,
    LIBERTADORES: 13
};

// Estado global do aplicativo
let state = {
    quantidadeJogos: 0,
    dataInicio: null,
    dataFim: null,
    campeonatos: [],
    jogosSelecionados: new Set(),
    todosJogos: [],
    maxJogos: 0
};

// Adicionar controle do checkbox de pré-seleção
let preSelecionarJogos = true;
const preSelecionarCheckbox = document.getElementById('preselecionar-jogos');
if (preSelecionarCheckbox) {
    preSelecionarJogos = preSelecionarCheckbox.checked;
    preSelecionarCheckbox.addEventListener('change', function() {
        preSelecionarJogos = this.checked;
        if (preSelecionarJogos) {
            selecionarJogosAutomaticamente();
        }
    });
}

// Função para inicializar o componente
function initBolaoCreator() {
    console.log('Iniciando BolaoCreator...');
    
    // Elementos do DOM
    const quantidadeInput = document.getElementById('quantidade-jogos');
    const dataInicioInput = document.getElementById('data-inicio');
    const dataFimInput = document.getElementById('data-fim');
    const jogosTableContainer = document.getElementById('jogos-table-container');
    const campeonatoCheckboxes = document.querySelectorAll('.campeonato-checkbox');
    const buscarJogosBtn = document.getElementById('buscar-jogos-btn');

    console.log('Elementos encontrados:', {
        quantidadeInput: !!quantidadeInput,
        dataInicioInput: !!dataInicioInput,
        dataFimInput: !!dataFimInput,
        jogosTableContainer: !!jogosTableContainer,
        campeonatoCheckboxes: campeonatoCheckboxes.length,
        buscarJogosBtn: !!buscarJogosBtn
    });

    // Event listeners
    if (quantidadeInput) {
        quantidadeInput.addEventListener('input', handleQuantidadeChange);
    }
    
    if (dataInicioInput) {
        dataInicioInput.addEventListener('change', function() {
            console.log('Data início alterada:', dataInicioInput.value);
            state.dataInicio = dataInicioInput.value;
        });
    }
    
    if (dataFimInput) {
        dataFimInput.addEventListener('change', function() {
            console.log('Data fim alterada:', dataFimInput.value);
            state.dataFim = dataFimInput.value;
        });
    }
    
    // Adicionar listener para checkboxes de campeonatos
    campeonatoCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            state.campeonatos = getCampeonatosSelecionados();
            console.log('Campeonatos selecionados:', state.campeonatos);
        });
    });
    
    // Adicionar listener para botão de buscar jogos
    if (buscarJogosBtn) {
        buscarJogosBtn.addEventListener('click', function() {
            console.log('Botão buscar jogos clicado');
            // Validar campos obrigatórios antes de buscar jogos
            const nomeBolaoInput = document.getElementById('nome-bolao');
            
            if (!nomeBolaoInput.value.trim()) {
                alert('Por favor, preencha o nome do bolão');
                nomeBolaoInput.focus();
                return;
            }
            
            if (state.campeonatos.length === 0) {
                alert('Por favor, selecione pelo menos um campeonato');
                return;
            }
            
            if (!state.dataInicio) {
                alert('Por favor, selecione a data de início');
                dataInicioInput.focus();
                return;
            }
            
            if (!state.dataFim) {
                alert('Por favor, selecione a data de fim');
                dataFimInput.focus();
                return;
            }
            
            if (new Date(state.dataInicio) > new Date(state.dataFim)) {
                alert('A data de início não pode ser posterior à data de fim');
                dataInicioInput.focus();
                return;
            }
            
            carregarJogos();
        });
    }

    // Inicializar estado
    state.maxJogos = parseInt(quantidadeInput?.value) || 0;
    state.dataInicio = dataInicioInput?.value;
    state.dataFim = dataFimInput?.value;
    state.campeonatos = getCampeonatosSelecionados();

    console.log('Estado inicial:', state);

    // Não carregar jogos inicialmente
    if (jogosTableContainer) {
        jogosTableContainer.style.display = 'none';
    }
}

function getCampeonatosSelecionados() {
    return Array.from(document.querySelectorAll('.campeonato-checkbox:checked')).map(cb => cb.value);
}

// Função para carregar jogos da API
async function carregarJogos() {
    console.log('Iniciando carregamento de jogos...');
    console.log('Estado atual:', state);
    
    const jogosTableContainer = document.getElementById('jogos-table-container');
    const quantidadeInput = document.getElementById('quantidade-jogos');
    const dataInicioInput = document.getElementById('data-inicio');
    const dataFimInput = document.getElementById('data-fim');
    const nomeBolaoInput = document.getElementById('nome-bolao');
    
    // Verificar se todos os campos obrigatórios estão preenchidos
    if (!state.dataInicio || !state.dataFim || !state.campeonatos.length || 
        !quantidadeInput.value || !dataInicioInput.value || !dataFimInput.value || !nomeBolaoInput.value) {
        
        console.log('Campos faltando:', {
            dataInicio: !state.dataInicio,
            dataFim: !state.dataFim,
            campeonatos: !state.campeonatos.length,
            quantidade: !quantidadeInput.value,
            nome: !nomeBolaoInput.value
        });
        
        if (!nomeBolaoInput.value) {
            alert('Preencha o nome do bolão antes de buscar jogos');
            return;
        }
        
        if (!state.campeonatos.length) {
            alert('Selecione pelo menos um campeonato');
            return;
        }
        
        if (!dataInicioInput.value || !dataFimInput.value) {
            alert('Preencha as datas de início e fim');
            return;
        }
        
        jogosTableContainer.style.display = 'none';
        return;
    }

    try {
        console.log('Fazendo requisição para a API...');
        console.log('URL da requisição:', `${APP_URL}/admin/api/jogos.php?inicio=${state.dataInicio}&fim=${state.dataFim}&campeonatos=${state.campeonatos.join(',')}`);
        
        const response = await fetch(`${APP_URL}/admin/api/jogos.php?inicio=${state.dataInicio}&fim=${state.dataFim}&campeonatos=${state.campeonatos.join(',')}`);
        const data = await response.json();
        
        console.log('Resposta da API:', data);

        if (data.success) {
            // Limitar a 20 jogos
            state.todosJogos = data.jogos.slice(0, 20);
            console.log('Jogos carregados:', state.todosJogos);
            
            // Verificar alertas de horário (análise feita apenas nos 20 jogos da lista)
            if (data.alertas_horario && data.alertas_horario.length > 0) {
                console.log('Alertas de horário detectados nos 20 jogos da lista:', data.alertas_horario);
                mostrarAlertaHorarios(data.alertas_horario);
            }
            
            atualizarTabelaJogos();
            if (preSelecionarJogos) {
                selecionarJogosAutomaticamente();
            }
            
            // Mostrar estatísticas se disponíveis
            if (data.estatisticas) {
                mostrarEstatisticasJogos(data.estatisticas);
            }
            
            jogosTableContainer.style.display = state.todosJogos.length > 0 ? 'block' : 'none';
        } else {
            console.error('Erro ao carregar jogos:', data.message);
            alert('Erro ao carregar jogos: ' + (data.message || 'Não foi possível buscar os jogos'));
            jogosTableContainer.style.display = 'none';
        }
    } catch (error) {
        console.error('Erro na requisição:', error);
        alert('Erro de conexão ao buscar jogos. Verifique sua conexão à internet.');
        jogosTableContainer.style.display = 'none';
    }
}

// Função para atualizar a tabela de jogos
function atualizarTabelaJogos() {
    const tbody = document.querySelector('#jogos-table tbody');
    tbody.innerHTML = '';

    state.todosJogos.forEach((jogo, index) => {
        const tr = document.createElement('tr');
        const isSelected = state.jogosSelecionados.has(jogo.id);
        const isDisabled = !isSelected && state.jogosSelecionados.size >= state.maxJogos;
        const jaUtilizado = jogo.ja_utilizado || false;

        // Se o jogo já foi utilizado, desabilitar completamente
        const finalDisabled = jaUtilizado || isDisabled;
        
        // Adicionar classes CSS apropriadas
        if (jaUtilizado) {
            tr.classList.add('jogo-ja-utilizado');
        }
        if (isSelected) {
            tr.classList.add('jogo-selecionado');
        }

        tr.innerHTML = `
            <td>
                <input type="checkbox" 
                       class="jogo-checkbox" 
                       value="${jogo.id}"
                       ${isSelected ? 'checked' : ''}
                       ${finalDisabled ? 'disabled' : ''}>
            </td>
            <td>${jogo.data}</td>
            <td>${jogo.campeonato}</td>
            <td>${jogo.time_casa}</td>
            <td>${jogo.time_visitante}${jaUtilizado ? '<br><small class="text-danger"><i class="fa-solid fa-exclamation-triangle"></i> Já usado em: ' + jogo.bolao_nome + '</small>' : ''}</td>
        `;

        tbody.appendChild(tr);
    });

    // Adicionar event listeners aos checkboxes
    document.querySelectorAll('.jogo-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', handleJogoSelection);
    });
}

// Função para selecionar jogos automaticamente
function selecionarJogosAutomaticamente() {
    state.jogosSelecionados.clear();
    // Filtrar jogos que começam após o momento atual E que não estão sendo usados em outros bolões
    const agora = new Date();
    const jogosFuturos = state.todosJogos.filter(jogo => {
        // Verificar se o jogo não está sendo usado em outro bolão
        if (jogo.ja_utilizado) {
            return false;
        }
        
        // Considera que a data já está no formato brasileiro, então converte para ISO
        const [dia, mes, anoHora] = jogo.data.split('/');
        const [ano, hora] = anoHora.split(' ');
        const dataISO = `${ano}-${mes.padStart(2, '0')}-${dia.padStart(2, '0')}` + (hora ? `T${hora}` : '');
        return new Date(dataISO) > agora;
    });
    // Selecionar os primeiros jogos disponíveis até o limite máximo
    jogosFuturos.slice(0, state.maxJogos).forEach(jogo => {
        state.jogosSelecionados.add(jogo.id);
    });
    atualizarTabelaJogos();
}

// Handlers de eventos
function handleQuantidadeChange(event) {
    state.maxJogos = parseInt(event.target.value) || 0;
    if (preSelecionarJogos) {
        selecionarJogosAutomaticamente();
    }
}

function handleDataOrCampeonatoChange() {
    const dataInicioInput = document.getElementById('data-inicio');
    const dataFimInput = document.getElementById('data-fim');
    state.dataInicio = dataInicioInput.value;
    state.dataFim = dataFimInput.value;
    state.campeonatos = getCampeonatosSelecionados();
    
    // Não carregamos mais jogos automaticamente
    // carregarJogos();
}

function handleJogoSelection(event) {
    const jogoId = parseInt(event.target.value);
    const jogo = state.todosJogos.find(j => j.id === jogoId);
    
    if (event.target.checked) {
        // Verificar se o jogo já está sendo usado em outro bolão
        if (jogo && jogo.ja_utilizado) {
            event.target.checked = false;
            alert(`Este jogo já está sendo usado no bolão: "${jogo.bolao_nome}". Selecione outro jogo.`);
            return;
        }
        
        if (state.jogosSelecionados.size < state.maxJogos) {
            state.jogosSelecionados.add(jogoId);
        } else {
            event.target.checked = false;
            alert(`Você já selecionou o máximo de ${state.maxJogos} jogos.`);
        }
    } else {
        state.jogosSelecionados.delete(jogoId);
        // Selecionar o próximo jogo disponível (que não esteja sendo usado)
        const proximoJogo = state.todosJogos.find(jogo => 
            !state.jogosSelecionados.has(jogo.id) && 
            !jogo.ja_utilizado &&
            new Date(jogo.data) > new Date()
        );
        
        if (proximoJogo) {
            state.jogosSelecionados.add(proximoJogo.id);
            atualizarTabelaJogos();
        }
    }
    
    // Atualizar visual das linhas selecionadas
    atualizarTabelaJogos();
}

// Função para mostrar estatísticas dos jogos
function mostrarEstatisticasJogos(estatisticas) {
    // Procurar por um container existente ou criar um novo
    let statsContainer = document.querySelector('#stats-jogos');
    if (!statsContainer) {
        statsContainer = document.createElement('div');
        statsContainer.id = 'stats-jogos';
        statsContainer.className = 'alert alert-info mt-3';
        
        // Inserir antes da tabela de jogos
        const jogosTableContainer = document.getElementById('jogos-table-container');
        jogosTableContainer.parentNode.insertBefore(statsContainer, jogosTableContainer);
    }
    
    statsContainer.innerHTML = `
        <h5><i class="fa-solid fa-chart-bar"></i> Estatísticas dos Jogos</h5>
        <div class="row">
            <div class="col-md-4">
                <strong>Total de Jogos:</strong> ${estatisticas.total_jogos}
            </div>
            <div class="col-md-4">
                <strong>Jogos Disponíveis:</strong> <span class="text-success">${estatisticas.jogos_disponiveis}</span>
            </div>
            <div class="col-md-4">
                <strong>Já Utilizados:</strong> <span class="text-danger">${estatisticas.jogos_ja_utilizados}</span>
            </div>
        </div>
        ${estatisticas.jogos_ja_utilizados > 0 ? 
            '<small class="text-muted"><i class="fa-solid fa-info-circle"></i> Jogos já utilizados em outros bolões não podem ser selecionados.</small>' : 
            ''}
    `;
}

// Adicionar inputs hidden dos jogos selecionados antes do submit
const form = document.querySelector('form');
if (form) {
    form.addEventListener('submit', function(e) {
        // Remover antigos
        document.querySelectorAll('input[name="jogos_selecionados[]"]').forEach(el => el.remove());
        document.querySelectorAll('input[name="jogos_json"]').forEach(el => el.remove());
        
        // Converter o Set para Array para facilitar
        const jogosArray = Array.from(state.jogosSelecionados);
        
        // Adicionar JSON com todos os jogos
        const jsonInput = document.createElement('input');
        jsonInput.type = 'hidden';
        jsonInput.name = 'jogos_json';
        jsonInput.value = JSON.stringify(jogosArray);
        form.appendChild(jsonInput);
        
        // Adicionar também como array para maior compatibilidade
        jogosArray.forEach(jogoId => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'jogos_selecionados[]';
            input.value = jogoId;
            form.appendChild(input);
        });
        
        // Adicionar como string concatenada
        const stringInput = document.createElement('input');
        stringInput.type = 'hidden';
        stringInput.name = 'jogos_string';
        stringInput.value = jogosArray.join(',');
        form.appendChild(stringInput);
        
        console.log('Jogos enviados:', state.jogosSelecionados);
    });
}

// Função para mostrar alerta de horários suspeitos
function mostrarAlertaHorarios(alertas) {
    console.log('Alertas de horário detectados:', alertas);
    
    let conteudoModal = '<div class="alert alert-warning mb-3">';
    conteudoModal += '<h5><i class="fas fa-exclamation-triangle"></i> Atenção: Horários Suspeitos Detectados</h5>';
    conteudoModal += '<p>Foram encontrados múltiplos jogos com o mesmo horário. Isso pode indicar que os horários ainda não foram confirmados pela CBF.</p>';
    conteudoModal += '</div>';
    
    alertas.forEach(alerta => {
        conteudoModal += `<div class="card mb-3">`;
        conteudoModal += `<div class="card-header bg-warning text-dark">`;
        conteudoModal += `<strong>${alerta.quantidade} jogos</strong> marcados para <strong>${alerta.horario}</strong>`;
        conteudoModal += `</div>`;
        conteudoModal += `<div class="card-body">`;
        conteudoModal += `<div class="list-group list-group-flush">`;
        
        alerta.jogos.forEach(jogo => {
            conteudoModal += `<div class="list-group-item">`;
            conteudoModal += `<strong>${jogo.campeonato}</strong><br>`;
            conteudoModal += `${jogo.time_casa} x ${jogo.time_visitante}`;
            conteudoModal += `</div>`;
        });
        
        conteudoModal += `</div></div></div>`;
    });
    
    conteudoModal += '<div class="alert alert-info">';
    conteudoModal += '<h6>Recomendação:</h6>';
    conteudoModal += '<p class="mb-1">• Verifique os horários oficiais no site da CBF</p>';
    conteudoModal += '<p class="mb-1">• Considere aguardar a confirmação dos horários antes de criar o bolão</p>';
    conteudoModal += '<p class="mb-0">• Se necessário, atualize o bolão após a confirmação dos horários</p>';
    conteudoModal += '</div>';
    
    // Criar modal dinamicamente se não existir
    let modal = document.getElementById('alertaHorarioModal');
    if (!modal) {
        const modalHtml = `
        <div class="modal fade" id="alertaHorarioModal" tabindex="-1" aria-labelledby="alertaHorarioModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title" id="alertaHorarioModalLabel">
                            <i class="fas fa-clock"></i> Verificação de Horários
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="alertaHorarioModalBody">
                        <!-- Conteúdo será inserido aqui -->
                    </div>
                    <div class="modal-footer">
                        <a href="https://www.cbf.com.br/futebol-brasileiro/tabelas/campeonato-brasileiro/serie-a/2025" target="_blank" class="btn btn-info me-auto">
                            <i class="fas fa-external-link-alt"></i> Verificar na CBF
                        </a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Entendi</button>
                        <button type="button" class="btn btn-warning" data-bs-dismiss="modal">Continuar Mesmo Assim</button>
                    </div>
                </div>
            </div>
        </div>`;
        
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        modal = document.getElementById('alertaHorarioModal');
    }
    
    // Atualizar conteúdo do modal
    document.getElementById('alertaHorarioModalBody').innerHTML = conteudoModal;
    
    // Mostrar modal
    const bootstrapModal = new bootstrap.Modal(modal);
    bootstrapModal.show();
}

// Inicializar quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', initBolaoCreator); 