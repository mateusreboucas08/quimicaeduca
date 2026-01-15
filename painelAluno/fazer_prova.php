<?php
session_start();
include '../conexão/conecta.php'; 

// GARANTIA DE ACESSO: SÓ ALUNO (tipo 2) PODE ACESSAR
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 2) {
    header("Location: ./login.php");
    exit();
}

$aluno_id = $_SESSION['user_id'];
$aluno_nome = $_SESSION['user_name'];

$conteudo_id = isset($_GET['conteudo_id']) ? $conn->real_escape_string($_GET['conteudo_id']) : null;

if (!$conteudo_id) {
    header("Location: painel_aluno.php?status=erro&msg=" . urlencode("ID da Atividade não especificado."));
    exit();
}

// 1. Busca o ID da Prova associado a este Conteúdo
$sql_prova_info = "SELECT P.id AS prova_id, P.titulo 
                   FROM Prova P WHERE P.conteudo_id = '$conteudo_id'";
$prova_info_result = $conn->query($sql_prova_info);

if ($prova_info_result->num_rows == 0) {
    header("Location: painel_aluno.php?status=erro&msg=" . urlencode("Prova não encontrada."));
    exit();
}

$prova_info = $prova_info_result->fetch_assoc();
$prova_id = $prova_info['prova_id'];
$titulo_prova = htmlspecialchars($prova_info['titulo']);

// 2. Verifica se o aluno JÁ ENTREGOU ou está em andamento (AQUI ESTÁ A CORREÇÃO!)
$sql_entrega_check = "SELECT id, status, nota_final FROM EntregaProva WHERE aluno_id = '$aluno_id' AND prova_id = '$prova_id'";
$entrega_check = $conn->query($sql_entrega_check);

if ($entrega_check->num_rows > 0) {
    $entrega = $entrega_check->fetch_assoc();
    if ($entrega['status'] >= 2) { // 2 = Aguardando Correção, 3 = Corrigida
        
        // NOVO COMPORTAMENTO: BLOQUEIA E REDIRECIONA IMEDIATAMENTE PARA O BOLETIM
        // (Assumindo que 'boletim_aluno.php' mostra os resultados)
        // Passamos os IDs para que a página de boletim possa exibir o resultado específico.
        $redirect_url = "boletim_aluno.php?prova_id=" . $prova_id . "&conteudo_id=" . $conteudo_id;
        header("Location: " . $redirect_url);
        exit();
    }
}


// 3. Busca as Questões da Prova (Este bloco só é executado se a prova não foi entregue)
$sql_questoes = "SELECT * FROM Questao WHERE prova_id = '$prova_id' ORDER BY id ASC";
$questoes_result = $conn->query($sql_questoes);

$questoes = [];
if ($questoes_result->num_rows > 0) {
    while ($q = $questoes_result->fetch_assoc()) {
        $q['opcoes'] = [];
        
        // Se for Múltipla Escolha, busca as opções
        if ($q['tipo'] == 1 || $q['tipo'] == 2) {
            $sql_opcoes = "SELECT id, texto_opcao FROM RespostaOpcao WHERE questao_id = '{$q['id']}'";
            $opcoes_result = $conn->query($sql_opcoes);
            while ($o = $opcoes_result->fetch_assoc()) {
                $q['opcoes'][] = $o;
            }
        }
        $questoes[] = $q;
    }
}

$conn->close();

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Prova: <?php echo $titulo_prova; ?></title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/estilo_aluno.css"> 
    <style>
        /* Estilos customizados para a prova (mantidos do último código) */
        .questao-card { border: 1px solid #ced4da; padding: 20px; margin-bottom: 25px; background-color: white; border-radius: 6px; }
        .questao-card h4 { font-weight: bold; color: #17a2b8; margin-top: 0; }
        .opcoes-list label { display: block; margin: 10px 0; font-weight: normal; }
        .opcoes-list input[type="radio"], .opcoes-list input[type="checkbox"] { margin-right: 10px; }
        .questao-imagem { max-width: 100%; height: auto; margin-bottom: 15px; border-radius: 4px; border: 1px solid #eee; }
        .status-popup { position: fixed; top: 20px; right: 20px; z-index: 1050; width: 300px; padding: 15px; border-radius: 4px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        /* Adicione ou garanta estes estilos em ../style/estilo_aluno.css para correção da sobreposição */
        body { padding-top: 70px; } 
        .navbar-aluno .navbar-brand, .navbar-aluno .navbar-nav > li > a { padding-top: 15px; padding-bottom: 15px; }
        .container-estudo .back-link { margin-top: 10px; margin-bottom: 20px; display: inline-block; }
    </style>
</head>
<body>

<nav class="navbar navbar-fixed-top navbar-aluno">
    <div class="container">
        <div class="navbar-header">
            <a class="navbar-brand back-link" href="painel_aluno.php">Química Educa 🧪</a>
        </div>
        <ul class="nav navbar-nav navbar-right">
            <li><a href="#"><span class="glyphicon glyphicon-user"></span> Olá, <?php echo $aluno_nome; ?></a></li>
            <li><a href="../logout.php"><span class="glyphicon glyphicon-log-out"></span> Sair</a></li>
        </ul>
    </div>
</nav>

<div class="container container-estudo">
    <a href="painel_aluno.php" class="back-link"><span class="glyphicon glyphicon-chevron-left"></span> Voltar para a Biblioteca</a>
    
    <div class="header-welcome" style="background-color: #17a2b8;">
        <h1>📝 Prova: <?php echo $titulo_prova; ?></h1>
        <p>Preencha todas as questões e clique em "Finalizar e Entregar" ao terminar.</p>
    </div>

    <div id="status-message-popup"></div>

    <form id="prova-form" action="submeter_prova.php" method="POST">
        <input type="hidden" name="prova_id" value="<?php echo $prova_id; ?>">
        <input type="hidden" name="conteudo_id" value="<?php echo $conteudo_id; ?>">
        
        <?php $num_questao = 1; ?>
        <?php foreach ($questoes as $q): ?>
            <div class="questao-card">
                <h4><?php echo $num_questao++; ?>. (<?php echo $q['pontuacao']; ?> pts) - <?php echo htmlspecialchars($q['texto_questao']); ?></h4>
                
                <?php if ($q['caminho_imagem']): ?>
                    <img src="/quimicaeduca/uploads/questoes/<?php echo htmlspecialchars($q['caminho_imagem']); ?>" 
                         alt="Imagem da Questão" class="questao-imagem">
                <?php endif; ?>

                <?php if ($q['tipo'] == 1 || $q['tipo'] == 2): // Múltipla Escolha ?>
                    <div class="opcoes-list">
                        <?php 
                        $input_type = ($q['tipo'] == 1) ? 'radio' : 'checkbox';
                        $input_name = "respostas[{$q['id']}]" . (($q['tipo'] == 2) ? '[]' : ''); 
                        ?>
                        <?php foreach ($q['opcoes'] as $o): ?>
                            <label>
                                <input type="<?php echo $input_type; ?>" 
                                       name="<?php echo $input_name; ?>" 
                                       value="<?php echo $o['id']; ?>"
                                       <?php echo ($q['tipo'] == 1) ? 'required' : ''; ?>>
                                <?php echo htmlspecialchars($o['texto_opcao']); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                <?php elseif ($q['tipo'] == 3): // Resposta em Texto ?>
                    <textarea name="respostas[<?php echo $q['id']; ?>]" class="form-control" rows="5" required
                              placeholder="Digite sua resposta aqui..."></textarea>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <div style="margin-top: 30px; margin-bottom: 50px;">
            <button type="button" id="btn-entregar" class="btn btn-success btn-lg">
                <span class="glyphicon glyphicon-send"></span> Finalizar e Entregar Prova
            </button>
        </div>
    </form>
    
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
<script>
    // Código JavaScript (mantido do último código)
    function showStatusPopup(type, message) {
        const statusPopupDiv = $('#status-message-popup');
        let alertClass = '';
        let icon = '';
        
        if (type === 'success' || type === 'prova_entregue') {
            alertClass = 'alert-success';
            icon = '✅';
        } else if (type === 'error') {
            alertClass = 'alert-danger';
            icon = '❌';
        } else if (type === 'desistiu') {
             alertClass = 'alert-warning';
            icon = '🛑';
            message = 'Sua desistência foi registrada. Você pode tentar a prova novamente.';
        }
        
        const html = `<div class="status-popup alert ${alertClass}"><p><strong>${icon} STATUS!</strong></p><p>${message}</p></div>`;
        statusPopupDiv.html(html);
        
        setTimeout(() => {
            statusPopupDiv.empty();
        }, 5000);
    }

    $(document).ready(function() {
        const provaId = '<?php echo $prova_id; ?>';
        const conteudoId = '<?php echo $conteudo_id; ?>';
        const form = $('#prova-form');
        
        // POP-UP DE ENTREGA
        $('#btn-entregar').on('click', function() {
            if (!form[0].checkValidity()) {
                showStatusPopup('error', 'Por favor, preencha todas as questões obrigatórias.');
                $('<input type="submit">').hide().appendTo(form).click().remove();
                return;
            }

            if (confirm('✅ Confirma a entrega da sua prova? Após a entrega, você não poderá mais alterar as respostas.')) {
                $(window).off('beforeunload');
                form.submit();
            }
        });

        // POP-UP DE DESISTÊNCIA (NAVEGAÇÃO/BACK LINK)
        $('.back-link').on('click', function(e) {
            e.preventDefault();
            
            if (confirm('⚠️ Deseja realmente desistir da prova? Sua tentativa será registrada como "Não Concluída" e as respostas NÃO serão salvas.')) {
                $(window).off('beforeunload');
                window.location.href = `desistir_prova.php?prova_id=${provaId}&conteudo_id=${conteudoId}`;
            }
        });

        // Alerta de navegação
        $(window).on('beforeunload', function() {
             return 'Sair da página sem entregar a prova registrará uma desistência.';
        });
        
        // Remove o alerta de beforeunload após a submissão
        form.on('submit', function() {
            $(window).off('beforeunload');
        });
    });
</script>
</body>
</html>