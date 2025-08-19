// Estado global do aplicativo
const state = {
    jogosSelecionados: new Set(),
    quantidadeMaxima: 11,
    todosJogos: [],
    filtros: {
        pais: '',
        campeonato: '',
        status: 'NS',
        incluirSemHorario: false
    }
};

// Inicialização quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', function() {
    initBolaoCreator2();
});

/**
 * Inicializa o criador de bolão simplificado
 */
function initBolaoCreator2() {
    console.log('Inicializando Bolão Creator 2...');
    
    // Configurar eventos dos elementos
    configurarEventos();
    
    // Carregar dados dos jogos se existirem
    carregarDadosJogos();
    
    // Configurar seleção automática inicial
    configurarSelecaoInicial();
    
    // Configurar filtros
    configurarFiltros();
    
    // Configurar eventos dos jogos
    configurarEventosJogos();
    
    // Atualizar contador
    atualizarContador();
}

/**
 * Configura todos os eventos necessários
 */
function configurarEventos() {
    // Evento para mudança na quantidade de jogos
    const quantidadeInput = document.getElementById('quantidade_jogos');
    if (quantidadeInput) {
        quantidadeInput.addEventListener('change', handleQuantidadeChange);
        quantidadeInput.addEventListener('input', handleQuantidadeChange);
    }
    
    // Eventos para checkboxes de jogos
    const checkboxes = document.querySelectorAll('.jogo-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', handleJogoSelection);
    });
    
    // Botão buscar jogos
    const btnBuscarJogos = document.getElementById('buscar-jogos');
    if (btnBuscarJogos) {
        btnBuscarJogos.addEventListener('click', buscarJogos);
    }
    
    // Botão selecionar todos
    const btnSelecionarTodos = document.getElementById('selecionar-todos');
    if (btnSelecionarTodos) {
        btnSelecionarTodos.addEventListener('click', selecionarTodos);
    }
    
    // Botão limpar seleção
    const btnLimparSelecao = document.getElementById('limpar-selecao');
    if (btnLimparSelecao) {
        btnLimparSelecao.addEventListener('click', limparSelecao);
    }
    
    // Evento do formulário
    const form = document.getElementById('formBolao');
    if (form) {
        form.addEventListener('submit', handleFormSubmit);
    }
    
    // Eventos para filtros
    const paisSelect = document.getElementById('pais');
    if (paisSelect) {
        paisSelect.addEventListener('change', handleFiltroChange);
    }
    
    const campeonatoSelect = document.getElementById('campeonato');
    if (campeonatoSelect) {
        campeonatoSelect.addEventListener('change', handleFiltroChange);
    }
    
    const statusSelect = document.getElementById('status');
    if (statusSelect) {
        statusSelect.addEventListener('change', handleFiltroChange);
    }
    
    // Preview da imagem
    const inputImagem = document.getElementById('imagem_bolao');
    if (inputImagem) {
        inputImagem.addEventListener('change', handleImagemPreview);
    }
    
    // Sugestão automática de nome do bolão
    const dataInicioInput = document.getElementById('data_inicio_bolao') || document.getElementById('data_inicio');
    const dataFimInput = document.getElementById('data_fim_bolao') || document.getElementById('data_fim');
    
    if (dataInicioInput && dataFimInput) {
        dataInicioInput.addEventListener('change', sugerirNomeBolao);
        dataFimInput.addEventListener('change', sugerirNomeBolao);
    }
}

/**
 * Configurar filtros
 */
function configurarFiltros() {
    // Carregar campeonatos quando país mudar
    const paisSelect = document.getElementById('pais');
    if (paisSelect) {
        paisSelect.addEventListener('change', carregarCampeonatos);
    }
}

/**
 * Carregar campeonatos baseado no país selecionado
 */
function carregarCampeonatos() {
    const paisSelect = document.getElementById('pais');
    const campeonatoSelect = document.getElementById('campeonato');
    
    if (!paisSelect || !campeonatoSelect) return;
    
    const paisSelecionado = paisSelect.value;
    
    // Limpar opções atuais
    campeonatoSelect.innerHTML = '<option value="">Carregando...</option>';
    
    if (!paisSelecionado) {
        campeonatoSelect.innerHTML = '<option value="">Todos os campeonatos</option>';
        return;
    }
    
    // Aqui você pode fazer uma requisição AJAX para carregar os campeonatos
    // Por enquanto, vamos apenas resetar
    setTimeout(() => {
        campeonatoSelect.innerHTML = '<option value="">Todos os campeonatos</option>';
    }, 500);
}

/**
 * Manipular mudanças nos filtros
 */
function handleFiltroChange() {
    // Atualizar estado dos filtros
    const paisSelect = document.getElementById('pais');
    const campeonatoSelect = document.getElementById('campeonato');
    const statusSelect = document.getElementById('status');
    
    if (paisSelect) state.filtros.pais = paisSelect.value;
    if (campeonatoSelect) state.filtros.campeonato = campeonatoSelect.value;
    if (statusSelect) state.filtros.status = statusSelect.value;
    
    console.log('Filtros atualizados:', state.filtros);
}

/**
 * Preview da imagem
 */
function handleImagemPreview(event) {
    const previewDiv = document.getElementById('preview-imagem');
    if (!previewDiv) return;
    
    previewDiv.innerHTML = '';
    
    if (event.target.files && event.target.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            previewDiv.innerHTML = `<img src="${e.target.result}" alt="Preview" style="max-width: 100%; max-height: 120px; border-radius: 10px; box-shadow: 0 2px 8px #ccc;">`;
        };
        reader.readAsDataURL(event.target.files[0]);
    }
}

/**
 * Sugestão automática de nome do bolão
 */
function sugerirNomeBolao() {
    const nomeBolaoInput = document.getElementById('nome');
    const dataInicioInput = document.getElementById('data_inicio_bolao') || document.getElementById('data_inicio');
    const dataFimInput = document.getElementById('data_fim_bolao') || document.getElementById('data_fim');
    
    if (!nomeBolaoInput || !dataInicioInput || !dataFimInput) return;
    
    if (dataInicioInput.value && dataFimInput.value && nomeBolaoInput.value === '') {
        const [ano1, mes1, dia1] = dataInicioInput.value.split('-');
        const [ano2, mes2, dia2] = dataFimInput.value.split('-');
        nomeBolaoInput.value = `Bolão ${dia1}/${mes1}/${ano1} a ${dia2}/${mes2}/${ano2}`;
    }
}

/**
 * Carrega os dados dos jogos do campo oculto
 */
function carregarDadosJogos() {
    const jogosDataElement = document.getElementById('jogos-data');
    if (jogosDataElement && jogosDataElement.value) {
        try {
            state.todosJogos = JSON.parse(jogosDataElement.value);
            console.log('Jogos carregados:', state.todosJogos.length);
        } catch (e) {
            console.error('Erro ao carregar dados dos jogos:', e);
            state.todosJogos = [];
        }
    }
}

/**
 * Buscar jogos com filtros aplicados
 */
function buscarJogos() {
    const btnBuscar = document.getElementById('buscar-jogos');
    const loader = document.getElementById('loader');
    const tabelaJogos = document.getElementById('tabela-jogos');
    
    if (btnBuscar) {
        btnBuscar.disabled = true;
        btnBuscar.textContent = 'Buscando...';
    }
    
    if (loader) {
        loader.style.display = 'block';
    }
    
    // Coletar dados do formulário
    const formData = new FormData();
    formData.append('action', 'buscar_jogos');
    
    // Adicionar filtros
    const paisSelect = document.getElementById('pais');
    if (paisSelect && paisSelect.value) {
        formData.append('pais', paisSelect.value);
    }
    
    const campeonatoSelect = document.getElementById('campeonato');
    if (campeonatoSelect && campeonatoSelect.value) {
        formData.append('campeonato', campeonatoSelect.value);
    }
    
    const statusSelect = document.getElementById('status');
    if (statusSelect && statusSelect.value) {
        formData.append('status', statusSelect.value);
    }
    
    const dataInicio = document.getElementById('data_inicio');
    if (dataInicio && dataInicio.value) {
        formData.append('data_inicio', dataInicio.value);
    }
    
    const dataFim = document.getElementById('data_fim');
    if (dataFim && dataFim.value) {
        formData.append('data_fim', dataFim.value);
    }
    
    const incluirSemHorario = document.getElementById('incluir_sem_horario');
    if (incluirSemHorario && incluirSemHorario.checked) {
        formData.append('incluir_sem_horario', '1');
    }
    
    // Fazer requisição AJAX
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Atualizar jogos disponíveis
            state.todosJogos = data.jogos || [];
            
            // Atualizar tabela
            if (tabelaJogos) {
                tabelaJogos.innerHTML = data.html || '<tr><td colspan="6" class="text-center">Nenhum jogo encontrado</td></tr>';
            }
            
            // Reconfigurar eventos dos checkboxes
            configurarEventosJogos();
            
            // Limpar seleção anterior
            state.jogosSelecionados.clear();
            
            // Atualizar contador
            atualizarContador();
            
            console.log(`${state.todosJogos.length} jogos carregados`);
        } else {
            alert('Erro ao buscar jogos: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro na requisição:', error);
        alert('Erro ao buscar jogos. Tente novamente.');
    })
    .finally(() => {
        if (btnBuscar) {
            btnBuscar.disabled = false;
            btnBuscar.textContent = 'Buscar Jogos';
        }
        
        if (loader) {
            loader.style.display = 'none';
        }
    });
}

/**
 * Configurar eventos dos checkboxes de jogos
 */
function configurarEventosJogos() {
    const checkboxes = document.querySelectorAll('.jogo-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.removeEventListener('change', handleJogoSelection);
        checkbox.addEventListener('change', handleJogoSelection);
    });
}

/**
 * Configura a seleção inicial baseada na quantidade definida
 */
function configurarSelecaoInicial() {
    const quantidadeInput = document.getElementById('quantidade_jogos');
    if (quantidadeInput) {
        state.quantidadeMaxima = parseInt(quantidadeInput.value) || 11;
        
        // Selecionar automaticamente os primeiros jogos
        selecionarPrimeirosJogos(state.quantidadeMaxima);
    }
}

/**
 * Seleciona automaticamente os primeiros N jogos
 */
function selecionarPrimeirosJogos(quantidade) {
    const checkboxes = document.querySelectorAll('.jogo-checkbox');
    
    // Limpar seleção atual
    state.jogosSelecionados.clear();
    
    // Selecionar os primeiros jogos até a quantidade desejada
    let selecionados = 0;
    checkboxes.forEach(checkbox => {
        if (selecionados < quantidade) {
            checkbox.checked = true;
            state.jogosSelecionados.add(checkbox.value);
            selecionados++;
        } else {
            checkbox.checked = false;
        }
    });
    
    atualizarContador();
    atualizarEstadoCheckboxes();
}

/**
 * Manipula mudanças na quantidade de jogos
 */
function handleQuantidadeChange(event) {
    const novaQuantidade = parseInt(event.target.value) || 0;
    const maxDisponivel = document.querySelectorAll('.jogo-checkbox').length;
    
    // Validar limites
    if (novaQuantidade > maxDisponivel) {
        event.target.value = maxDisponivel;
        state.quantidadeMaxima = maxDisponivel;
    } else if (novaQuantidade < 1) {
        event.target.value = 1;
        state.quantidadeMaxima = 1;
    } else {
        state.quantidadeMaxima = novaQuantidade;
    }
    
    // Ajustar seleção automaticamente
    ajustarSelecaoParaQuantidade(state.quantidadeMaxima);
}

/**
 * Ajusta a seleção para a quantidade especificada
 */
function ajustarSelecaoParaQuantidade(quantidade) {
    const checkboxes = document.querySelectorAll('.jogo-checkbox');
    const selecionadosAtualmente = state.jogosSelecionados.size;
    
    if (selecionadosAtualmente > quantidade) {
        // Remover seleções extras (últimas selecionadas)
        const checkboxesArray = Array.from(checkboxes);
        let removidos = 0;
        
        for (let i = checkboxesArray.length - 1; i >= 0 && removidos < (selecionadosAtualmente - quantidade); i--) {
            const checkbox = checkboxesArray[i];
            if (checkbox.checked) {
                checkbox.checked = false;
                state.jogosSelecionados.delete(checkbox.value);
                removidos++;
            }
        }
    } else if (selecionadosAtualmente < quantidade) {
        // Adicionar mais seleções
        let adicionados = 0;
        const necessarios = quantidade - selecionadosAtualmente;
        
        checkboxes.forEach(checkbox => {
            if (!checkbox.checked && adicionados < necessarios) {
                checkbox.checked = true;
                state.jogosSelecionados.add(checkbox.value);
                adicionados++;
            }
        });
    }
    
    atualizarContador();
    atualizarEstadoCheckboxes();
}

/**
 * Manipula a seleção/deseleção de jogos individuais
 */
function handleJogoSelection(event) {
    const checkbox = event.target;
    const jogoId = checkbox.value;
    
    if (checkbox.checked) {
        // Verificar se não excede o limite
        if (state.jogosSelecionados.size >= state.quantidadeMaxima) {
            checkbox.checked = false;
            alert(`Você pode selecionar no máximo ${state.quantidadeMaxima} jogos.`);
            return;
        }
        state.jogosSelecionados.add(jogoId);
    } else {
        state.jogosSelecionados.delete(jogoId);
    }
    
    atualizarContador();
    atualizarEstadoCheckboxes();
}

/**
 * Seleciona todos os jogos (limitado pela quantidade máxima)
 */
function selecionarTodos() {
    selecionarPrimeirosJogos(state.quantidadeMaxima);
}

/**
 * Limpa toda a seleção
 */
function limparSelecao() {
    const checkboxes = document.querySelectorAll('.jogo-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    
    state.jogosSelecionados.clear();
    atualizarContador();
    atualizarEstadoCheckboxes();
}

/**
 * Atualiza o contador de jogos selecionados
 */
function atualizarContador() {
    const contador = document.getElementById('contador-jogos');
    if (contador) {
        const selecionados = state.jogosSelecionados.size;
        const total = document.querySelectorAll('.jogo-checkbox').length;
        contador.textContent = `${selecionados} de ${state.quantidadeMaxima} jogos selecionados (${total} disponíveis)`;
        
        // Adicionar classe de aviso se necessário
        if (selecionados < state.quantidadeMaxima) {
            contador.className = 'ms-3 text-warning';
        } else if (selecionados === state.quantidadeMaxima) {
            contador.className = 'ms-3 text-success';
        } else {
            contador.className = 'ms-3 text-danger';
        }
    }
}

/**
 * Atualiza o estado dos checkboxes (habilita/desabilita)
 */
function atualizarEstadoCheckboxes() {
    const checkboxes = document.querySelectorAll('.jogo-checkbox');
    const limiteAtingido = state.jogosSelecionados.size >= state.quantidadeMaxima;
    
    checkboxes.forEach(checkbox => {
        if (!checkbox.checked && limiteAtingido) {
            checkbox.disabled = true;
            checkbox.parentElement.parentElement.style.opacity = '0.5';
        } else {
            checkbox.disabled = false;
            checkbox.parentElement.parentElement.style.opacity = '1';
        }
    });
}

/**
 * Manipula o envio do formulário
 */
function handleFormSubmit(event) {
    // Validar se há jogos selecionados
    if (state.jogosSelecionados.size === 0) {
        event.preventDefault();
        alert('Por favor, selecione pelo menos um jogo para o bolão.');
        return false;
    }
    
    // Validar se a quantidade está correta
    if (state.jogosSelecionados.size !== state.quantidadeMaxima) {
        const confirmar = confirm(
            `Você selecionou ${state.jogosSelecionados.size} jogos, mas a quantidade definida é ${state.quantidadeMaxima}. ` +
            'Deseja continuar mesmo assim?'
        );
        
        if (!confirmar) {
            event.preventDefault();
            return false;
        }
    }
    
    // Validar campos obrigatórios
    const camposObrigatorios = ['nome', 'data_inicio', 'data_fim', 'valor_participacao'];
    for (const campo of camposObrigatorios) {
        const elemento = document.getElementById(campo);
        if (elemento && !elemento.value.trim()) {
            event.preventDefault();
            alert(`Por favor, preencha o campo: ${elemento.labels[0].textContent}`);
            elemento.focus();
            return false;
        }
    }
    
    console.log('Formulário válido, enviando...');
    return true;
}

/**
 * Função de debug para verificar o estado atual
 */
function debugState() {
    console.log('Estado atual:', {
        jogosSelecionados: Array.from(state.jogosSelecionados),
        quantidadeMaxima: state.quantidadeMaxima,
        totalJogos: state.todosJogos.length,
        filtros: state.filtros
    });
}

// Expor função de debug globalmente
window.debugState = debugState;