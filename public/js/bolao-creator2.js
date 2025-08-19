// Estado global simplificado
let state = {
    jogosSelecionados: new Set(),
    maxJogos: 0,
    todosJogos: []
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
 * Configura a seleção inicial baseada na quantidade definida
 */
function configurarSelecaoInicial() {
    const quantidadeInput = document.getElementById('quantidade_jogos');
    if (quantidadeInput) {
        state.maxJogos = parseInt(quantidadeInput.value) || 11;
        
        // Selecionar automaticamente os primeiros jogos
        selecionarPrimeirosJogos(state.maxJogos);
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
        state.maxJogos = maxDisponivel;
    } else if (novaQuantidade < 1) {
        event.target.value = 1;
        state.maxJogos = 1;
    } else {
        state.maxJogos = novaQuantidade;
    }
    
    // Ajustar seleção automaticamente
    ajustarSelecaoParaQuantidade(state.maxJogos);
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
        if (state.jogosSelecionados.size >= state.maxJogos) {
            checkbox.checked = false;
            alert(`Você pode selecionar no máximo ${state.maxJogos} jogos.`);
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
    selecionarPrimeirosJogos(state.maxJogos);
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
        contador.textContent = `${selecionados} de ${state.maxJogos} jogos selecionados (${total} disponíveis)`;
        
        // Adicionar classe de aviso se necessário
        if (selecionados < state.maxJogos) {
            contador.className = 'ms-3 text-warning';
        } else if (selecionados === state.maxJogos) {
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
    const limiteAtingido = state.jogosSelecionados.size >= state.maxJogos;
    
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
    if (state.jogosSelecionados.size !== state.maxJogos) {
        const confirmar = confirm(
            `Você selecionou ${state.jogosSelecionados.size} jogos, mas a quantidade definida é ${state.maxJogos}. ` +
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
        maxJogos: state.maxJogos,
        totalJogos: state.todosJogos.length
    });
}

// Expor função de debug globalmente
window.debugState = debugState;