<?php
session_start();
include '../conexão/conecta.php';

// GARANTIA DE ACESSO: SÓ PROFESSOR (tipo 1) PODE ACESSAR
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 1) {
    header("Location: ./login.php");
    exit();
}

$nome_professor = $_SESSION['user_name'];
$prova_id = isset($_GET['prova_id']) ? $conn->real_escape_string($_GET['prova_id']) : null;
$titulo_prova = isset($_GET['titulo']) ? htmlspecialchars($_GET['titulo']) : 'Nova Prova';

if (!$prova_id) {
    header("Location: painel_professor.php");
    exit();
}

// === LÓGICA PARA CARREGAR QUESTÕES EXISTENTES (EDIÇÃO) ===
$questoes_existentes = [];

$sql_questoes_existentes = "SELECT * FROM Questao WHERE prova_id = '$prova_id' ORDER BY id ASC";
$questoes_result = $conn->query($sql_questoes_existentes);

if ($questoes_result && $questoes_result->num_rows > 0) {
    while ($q = $questoes_result->fetch_assoc()) {
        $q_id = $q['id'];
        $q['opcoes_db'] = [];
        
        // Se for Múltipla Escolha, busca as opções
        if ($q['tipo'] == 1 || $q['tipo'] == 2) {
            $sql_opcoes = "SELECT id, texto_opcao, correta FROM RespostaOpcao WHERE questao_id = '$q_id'";
            $opcoes_result = $conn->query($sql_opcoes);
            while ($o = $opcoes_result->fetch_assoc()) {
                $q['opcoes_db'][] = $o;
            }
        }
        $questoes_existentes[] = $q;
    }
}

// Codifica as questões para serem lidas pelo JavaScript
$questoes_existentes_json = json_encode($questoes_existentes);

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Criar Questões - <?php echo $titulo_prova; ?></title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/estilo_admin.css"> 
    <style>
        /* Estilos e animações */
        .spinning { animation: spin 1s linear infinite; }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .questao-existente { background-color: #f9f9f9; border-color: #d1e2f7; }
    </style>
</head>
<body>

<nav class="navbar navbar-inverse navbar-fixed-top navbar-top">
    <div class="container-fluid">
        <div class="navbar-header">
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar-collapse-1" aria-expanded="false">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="painel_professor.php">Química Educa - Professor</a>
        </div>
        <div class="collapse navbar-collapse" id="navbar-collapse-1">
            <ul class="nav navbar-nav navbar-right">
                <li><a href="#"><span class="glyphicon glyphicon-user">&nbsp;</span>Olá, <?php echo htmlspecialchars($nome_professor); ?></a></li>
                <li><a href="../logout.php"><span class="glyphicon glyphicon-log-out"></span> Logout</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container-fluid">
    <div class="row">
        
        <div class="col-sm-3 col-md-2" id="sidebar">
            <div class="container-fluid tmargin" style="padding: 15px;">
                 <div style="color: white; padding-bottom: 15px; border-bottom: 1px solid #495057;">
                    <span class="glyphicon glyphicon-search"></span> Busca Rápida
                </div>
            </div>
            <ul class="nav nav-sidebar side-bar">
                <li><a href="painel_professor.php"><span class="glyphicon glyphicon-dashboard">&nbsp;</span>Dashboard</a></li>
                <li class="active"><a href="criar_conteudo.php"><span class="glyphicon glyphicon-pencil">&nbsp;</span>Criar Conteúdo</a></li>
                <li><a href="correcao_provas.php"><span class="glyphicon glyphicon-check">&nbsp;</span>Corrigir Provas</a></li>
                <li><a href="progresso_alunos.php"><span class="glyphicon glyphicon-stats">&nbsp;</span>Acompanhar Progresso</a></li>
                <li><a href="relatorio_dificuldade.php"><span class="glyphicon glyphicon-list">&nbsp;</span>Régua de Dificuldade</a></li>
            </ul>
        </div>

        <div class="col-sm-9 col-sm-offset-3 col-md-10 col-md-offset-2 main-content">
            <h1 class="page-header">✍️ Cadastrar Questões: <?php echo $titulo_prova; ?></h1>
            <p>Adicione as questões da prova manualmente ou use a IA para gerar conteúdo rapidamente.</p>
            <hr>

            <div class="panel panel-default">
                <div class="panel-heading">
                    <h4 class="panel-title">⭐ Gerar Questões por Inteligência Artificial</h4>
                </div>
                <div class="panel-body">
                    <div class="form-group">
                        <label for="tema_ia">Tópicos/Assuntos (Separados por vírgula):</label>
                        <input type="text" id="tema_ia" class="form-control" placeholder="Ex: Balanceamento, Nomenclatura, Estequiometria">
                        <p class="help-block">A IA buscará informações na web e gerará questões cobrindo todos os temas listados.</p>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label for="nivel_ia">Nível Escolar:</label>
                            <select id="nivel_ia" class="form-control" required>
                                <option value="Ensino Médio Básico">Ensino Médio Básico</option>
                                <option value="Ensino Médio Intermediário">Ensino Médio Intermediário</option>
                                <option value="Ensino Superior/Avançado">Ensino Superior/Avançado</option>
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                             <label for="num_questoes_ia">Número de Questões a Gerar:</label>
                            <input type="number" id="num_questoes_ia" class="form-control" value="3" min="1" max="10">
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-info btn-block" id="btn-gerar-ia" disabled>
                        <span class="glyphicon glyphicon-magic"></span> Gerar e Adicionar Questões
                    </button>
                    <p class="help-block" id="ia-status" style="margin-top: 10px;"></p>
                </div>
            </div>
            <hr>
            
            <div id="status-message" style="margin-top: 20px;"></div>

            <form id="quiz-form" action="salvar_questoes.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="prova_id" value="<?php echo $prova_id; ?>">
                
                <div id="questoes-container">
                    <?php if (!empty($questoes_existentes)): ?>
                        <div class="alert alert-warning">
                            Estas questões já existem no banco de dados. Elas serão **sobrescritas ou adicionadas** ao salvar. 
                            Remova o que não for mais necessário.
                        </div>
                    <?php endif; ?>
                </div>

                <hr>
                <div class="form-group">
                    <button type="button" class="btn btn-primary" onclick="adicionarQuestaoManual(1)">
                        <span class="glyphicon glyphicon-plus"></span> Múltipla Escolha (Única)
                    </button>
                    <button type="button" class="btn btn-primary" onclick="adicionarQuestaoManual(2)">
                        <span class="glyphicon glyphicon-plus"></span> Múltipla Escolha (Múltipla)
                    </button>
                    <button type="button" class="btn btn-primary" onclick="adicionarQuestaoManual(3)">
                        <span class="glyphicon glyphicon-plus"></span> Resposta em Texto
                    </button>
                </div>

                <div class="form-group" style="margin-top: 30px;">
                    <button type="submit" class="btn btn-success btn-lg">
                        <span class="glyphicon glyphicon-floppy-disk"></span> Salvar Prova e Publicar
                    </button>
                    <a href="painel_professor.php" class="btn btn-default btn-lg">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
<script>
let questaoCount = 0;
let opcaoCount = 0; 
const questoesExistentes = <?php echo $questoes_existentes_json; ?>;

// === 1. FUNÇÕES DE VALIDAÇÃO ===
function validarFormulario() {
    let isValid = true;
    const errorDiv = $('#status-message');
    errorDiv.empty(); 
    
    const requiredTextareas = $('#quiz-form').find('textarea[required], input[type="text"][required], input[type="number"][required]');
    
    requiredTextareas.each(function() {
        if ($(this).val().trim() === '' || $(this).val() === 'N/A') {
            errorDiv.append('<div class="alert alert-danger">❌ Todos os campos de texto, título e pontuação são obrigatórios.</div>');
            isValid = false;
            return false;
        }
    });

    if (!isValid) return false;

    // Validação de Gabarito (Múltipla Escolha)
    $('.panel-info').each(function(index, element) {
        const tipoInput = $(element).find('input[name^="questoes"][name$="[tipo]"]').val();
        
        if (tipoInput === '1' || tipoInput === '2') {
            let gabaritoMarcado = false;
            
            $(element).find('input[name$="[correta]"]').each(function() {
                if ($(this).prop('checked')) {
                    gabaritoMarcado = true;
                    return false;
                }
            });

            if (!gabaritoMarcado) {
                errorDiv.append(`<div class="alert alert-danger">❌ A Questão #${index + 1} de Múltipla Escolha requer que **pelo menos um Gabarito** seja marcado.</div>`);
                isValid = false;
            }
        }
    });

    return isValid;
}


// === 2. FUNÇÕES DE CRIAÇÃO/EDIÇÃO DE OPÇÕES ===

function adicionarOpcao(questaoId, inputType, opcaoId=null, texto='', isCorreta=false) { 
    opcaoCount++;
    const opcoesContainer = document.getElementById(`opcoes-${questaoId}`);
    
    const uniqueId = opcaoId !== null ? `existing_${opcaoId}` : `new_${opcaoCount}`;
    const baseName = `questoes[${questaoId}][opcoes][${uniqueId}]`;

    const opcaoHTML = `
        <div class="input-group" style="margin-bottom: 5px;" id="opcao-${questaoId}-${uniqueId}">
            <span class="input-group-addon">
                <input type="${inputType}" name="${baseName}[correta]" value="1" ${isCorreta ? 'checked' : ''}> Gabarito
            </span>
            <input type="text" name="${baseName}[texto]" class="form-control" value="${texto}" placeholder="Texto da Opção" required>
            <input type="hidden" name="${baseName}[opcao_id]" value="${opcaoId || ''}">
            <span class="input-group-btn">
                <button class="btn btn-default" type="button" onclick="removerElemento('opcao-${questaoId}-${uniqueId}')">
                    <span class="glyphicon glyphicon-trash"></span>
                </button>
            </span>
        </div>
    `;
    if (opcoesContainer) {
        opcoesContainer.insertAdjacentHTML('beforeend', opcaoHTML);
    }
}

function removerElemento(id) {
    document.getElementById(id).remove();
    reindexarQuestaoVisualmente(); // Reorganiza a numeração após remover
}


// === 3. FUNÇÃO DE CARREGAMENTO/EDIÇÃO (UNIFICADA) ===
function carregarQuestao(q, isNew=false) {
    // ID interno usado para names de input (garantido por new_X ou ID do DB)
    const currentQuestaoId = q.id || `new_${++questaoCount}`; 
    const container = document.getElementById('questoes-container');
    const baseName = `questoes[${currentQuestaoId}]`;
    const tipo = parseInt(q.tipo);
    const pontuacao = parseFloat(q.pontuacao || q.pontuacao_max || 1.00).toFixed(2);
    
    let tipoTexto = '';
    let opcoesHTML = '';
    let gabaritoHTML = '';
    let inputType = '';
    let textoQuestao = q.texto_questao || q.texto || '';
    let gabaritoTexto = q.gabarito_texto || '';
    let imagemPath = q.caminho_imagem || '';
    
    const panelClass = isNew ? 'panel-info' : 'panel-info questao-existente';

    if (tipo === 1 || tipo === 2) {
        tipoTexto = (tipo === 1) ? 'Múltipla Escolha (Única)' : 'Múltipla Escolha (Múltipla)';
        inputType = (tipo === 1) ? 'radio' : 'checkbox';

        let opcoesBody = '';
        const opcoesSource = q.opcoes_db || q.opcoes || [];
        
        opcoesSource.forEach((opcao, index) => {
            const opcaoId = opcao.id || null;
            const texto = opcao.texto_opcao || opcao; 
            const isCorreta = (opcao.correta == 1) || (q.gabarito && q.gabarito.includes(index));
            
            // Adiciona um placeholder que será substituído após a injeção do HTML
            opcoesBody += `<span class="temp-opcao" data-opid="${opcaoId}" data-texto="${texto}" data-correta="${isCorreta}" data-tipo="${inputType}"></span>`;
        });
        
        opcoesHTML = `
            <div class="panel panel-default">
                <div class="panel-heading">Opções de Resposta:</div>
                <div class="panel-body" id="opcoes-${currentQuestaoId}">
                    ${opcoesBody}
                </div>
                <div class="panel-footer">
                    <button type="button" class="btn btn-xs btn-default" 
                            onclick="adicionarOpcao('${currentQuestaoId}', '${inputType}')">
                        <span class="glyphicon glyphicon-plus"></span> Adicionar Opção
                    </button>
                    <p class="help-block" style="margin-top: 10px;">Marque a(s) caixa(s) de Gabarito para indicar a resposta correta.</p>
                </div>
            </div>
        `;
    } else if (tipo === 3) {
        tipoTexto = 'Resposta em Texto (Correção Manual)';
        gabaritoHTML = `
            <div class="form-group">
                <label>Gabarito para Referência (Professor):</label>
                <textarea name="${baseName}[gabarito_texto]" class="form-control" rows="2" required placeholder="Digite a resposta esperada para referência de correção manual.">${gabaritoTexto}</textarea>
            </div>
        `;
    }

    const questaoHTML = `
        <div class="panel ${panelClass}" id="questao-${currentQuestaoId}">
            <div class="panel-heading">
                <h4 class="panel-title">Questão #<span class="questao-numero-placeholder"></span> - Tipo: ${tipoTexto}</h4>
                <input type="hidden" name="${baseName}[questao_id]" value="${q.id || ''}">
                <input type="hidden" name="${baseName}[tipo]" value="${tipo}">
            </div>
            <div class="panel-body">
                <div class="form-group">
                    <label>Texto da Questão:</label>
                    <textarea name="${baseName}[texto]" class="form-control" rows="3" required>${textoQuestao}</textarea>
                </div>
                
                <div class="form-group">
                    <label>Imagem Opcional (apenas JPG/PNG):</label>
                    ${imagemPath ? `<p class="help-block">Imagem atual: <a href="../uploads/questoes/${imagemPath}" target="_blank">${imagemPath}</a></p>` : ''}
                    <input type="file" name="imagem_questao_${currentQuestaoId}" class="form-control" accept="image/png, image/jpeg, image/gif">
                </div>
                
                <div class="form-group col-md-4" style="padding-left: 0;">
                    <label>Pontuação:</label>
                    <input type="number" step="0.01" min="0.01" name="${baseName}[pontuacao]" class="form-control" value="${pontuacao}" required>
                </div>
                
                <div class="clearfix"></div>
                
                ${opcoesHTML}
                ${gabaritoHTML}
                
                <button type="button" class="btn btn-danger btn-sm pull-right" onclick="removerElemento('questao-${currentQuestaoId}')">
                    Remover Questão
                </button>
            </div>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', questaoHTML);
    
    // Substitui os placeholders de opções pelo HTML real
    $(`#opcoes-${currentQuestaoId} span.temp-opcao`).each(function() {
        const span = $(this);
        adicionarOpcao(
            currentQuestaoId, 
            span.data('tipo'), 
            span.data('opid'), 
            span.data('texto'), 
            span.data('correta') === true 
        );
        span.remove();
    });
    
    return currentQuestaoId;
}

// Função de helper para os botões manuais
function adicionarQuestaoManual(tipo) {
    const q_template = {
        id: null,
        tipo: tipo,
        texto: 'Novo texto da questão...',
        gabarito_texto: (tipo === 3) ? 'Gabarito dissertativo esperado...' : ''
    };
    
    // 1. CHAMA carregarQuestao e captura o ID gerado
    const currentQuestaoId = carregarQuestao(q_template, true); 
    
    // 2. Adiciona opções usando o ID CAPTURADO
    if (tipo === 1 || tipo === 2) {
        const inputType = (tipo === 1) ? 'radio' : 'checkbox';
        adicionarOpcao(currentQuestaoId, inputType);
        adicionarOpcao(currentQuestaoId, inputType);
    }
    
    reindexarQuestaoVisualmente(); // Atualiza a numeração após inserção manual
}

// NOVA FUNÇÃO: Re-numera as questões visualmente
function reindexarQuestaoVisualmente() {
    let visualIndex = 1;
    $('#questoes-container .panel-info').each(function() {
        // Encontra o elemento do título e atualiza apenas o placeholder
        $(this).find('.panel-title .questao-numero-placeholder').text(visualIndex);
        visualIndex++;
    });
}


// === 4. AJAX E MANIPULADORES DE EVENTOS ===

$(document).ready(function() {
    // ------------------------------------------
    // A. CARREGAR QUESTÕES EXISTENTES
    // ------------------------------------------
    if (questoesExistentes.length > 0) {
        questoesExistentes.forEach(q => {
            carregarQuestao(q);
        });
    }

    // Chamada inicial para criar uma questão vazia se não houver nenhuma
    if (questoesExistentes.length === 0) {
        adicionarQuestaoManual(1);
    }
    
    // ** PONTO CRÍTICO: CHAMA A RENUMERAÇÃO APÓS INSERIR TUDO **
    reindexarQuestaoVisualmente(); 


    // ------------------------------------------
    // B. LÓGICA DA IA (Gera e insere via carregarQuestao)
    // ------------------------------------------
    const temaInput = $('#tema_ia');
    const numInput = $('#num_questoes_ia');
    const nivelInput = $('#nivel_ia'); 
    const gerarBtn = $('#btn-gerar-ia');
    const statusDivIA = $('#ia-status');

    temaInput.on('input', function() {
        gerarBtn.prop('disabled', temaInput.val().trim().length < 5);
    });

    gerarBtn.on('click', function() {
        const tema = temaInput.val().trim();
        const num = numInput.val();
        const nivel = nivelInput.val();
        
        if (tema.length < 5 || num < 1) {
            statusDivIA.html('Preencha o tema e o número de questões.');
            return;
        }

        statusDivIA.html('<span class="glyphicon glyphicon-refresh spinning"></span> **Gerando...** A IA está criando as questões. Aguarde.');
        gerarBtn.prop('disabled', true);
        
        $.ajax({
            url: 'gerar_questoes_ia.php',
            type: 'POST',
            dataType: 'json',
            data: { 
                tema: tema, 
                num_questoes: num,
                nivel: nivel 
            },
            success: function(response) {
                gerarBtn.prop('disabled', false);

                if (response.status === 'success') {
                    statusDivIA.html('✅ **Sucesso!** Questões adicionadas ao formulário.');
                    
                    response.questoes.forEach(q => {
                        // Mapeia o formato da IA para a função carregarQuestao
                        const tipoMapa = {
                            'multipla_escolha_unica': 1,
                            'multipla_escolha_multipla': 2,
                            'dissertativa': 3 
                        };
                        
                        const q_processada = {
                            id: null,
                            tipo: tipoMapa[q.tipo] || 3,
                            texto: q.texto,
                            pontuacao: q.pontuacao,
                            gabarito_texto: q.gabarito_texto,
                            opcoes: q.opcoes, // Array de strings (para M.C.)
                            gabarito: q.gabarito // Array de índices (para M.C.)
                        };
                        carregarQuestao(q_processada, true);
                    });
                    
                    // CORREÇÃO: Reindexar após a inserção de cada lote da IA
                    reindexarQuestaoVisualmente(); 
                    
                } else {
                    statusDivIA.html(`<div class="alert alert-danger" style="margin-top: 10px;">❌ Erro da IA: ${response.message}</div>`);
                }
            },
            error: function() {
                gerarBtn.prop('disabled', false);
                statusDivIA.html('<div class="alert alert-danger" style="margin-top: 10px;">❌ Erro de conexão com o servidor de IA.</div>');
            }
        });
    });

    // ------------------------------------------
    // C. LÓGICA DE SUBMISSÃO (Formulário)
    // ------------------------------------------
    $('#quiz-form').on('submit', function(e) {
        e.preventDefault();
        
        if (!validarFormulario()) {
            return; 
        }
        
        const formData = new FormData(this);
        const statusDiv = $('#status-message');
        
        statusDiv.html('<div class="alert alert-info"><span class="glyphicon glyphicon-refresh spinning"></span> Salvando... Aguarde.</div>');
        
        $.ajax({
            url: 'salvar_questoes.php',
            type: 'POST',
            data: formData,
            contentType: false, 
            processData: false, 
            success: function(response) {
                let data;
                try {
                    data = (typeof response === 'object') ? response : JSON.parse(response); 
                } catch (e) {
                    statusDiv.html('<div class="alert alert-danger">❌ ERRO: Resposta ilegível do servidor. Verifique logs.</div>');
                    return; 
                }

                if (data.status === 'success') {
                    statusDiv.html('<div class="alert alert-success"><span class="glyphicon glyphicon-ok"></span> **SUCESSO!** Prova salva e publicada! Redirecionando...</div>');
                    
                    setTimeout(function() {
                         window.location.href = 'painel_professor.php'; 
                    }, 2000); 
                    
                } else {
                    statusDiv.html(`<div class="alert alert-danger"><span class="glyphicon glyphicon-remove"></span> **ERRO:** ${data.message}</div>`);
                }
            },
            error: function() {
                statusDiv.html('<div class="alert alert-danger">❌ ERRO DE CONEXÃO: Não foi possível comunicar com o servidor.</div>');
            }
        });
    });
});
</script>
</body>
</html>