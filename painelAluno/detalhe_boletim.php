<?php
session_start();
include '../conexão/conecta.php'; 

// GARANTIA DE ACESSO: SÓ ALUNO (tipo 2) PODE ACESSAR E RECEBER O ID DA ENTREGA
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 2 || !isset($_GET['entrega_id'])) {
    header("Location: ./login.php");
    exit();
}

$aluno_id = $_SESSION['user_id'];
$aluno_nome = $_SESSION['user_name'];
$entrega_id = $conn->real_escape_string($_GET['entrega_id']);

// 1. Validação de Propriedade e Status (Deve ser do aluno logado e Corrigida - status 3)
$sql_validacao = "
    SELECT 
        EP.id, P.titulo AS prova_titulo, EP.nota_final
    FROM 
        EntregaProva EP
    JOIN 
        Prova P ON EP.prova_id = P.id
    WHERE 
        EP.id = '$entrega_id' AND EP.aluno_id = '$aluno_id' AND EP.status = 3
";
$validacao = $conn->query($sql_validacao)->fetch_assoc();

if (!$validacao) {
    // Se a prova não for encontrada ou não estiver corrigida, redireciona para o boletim geral
    header("Location: boletim_aluno.php?status=error&msg=" . urlencode("Prova não corrigida ou acesso inválido."));
    exit();
}

$prova_titulo = $validacao['prova_titulo'];
$nota_final = $validacao['nota_final'];

// 2. Busca todas as Questões, Respostas, Notas e Comentários do Professor
$sql_respostas = "
    SELECT 
        Q.id AS questao_id, Q.texto_questao, Q.tipo, Q.pontuacao, Q.caminho_imagem, Q.gabarito_multipla,
        RA.nota_obtida, RA.comentario_professor, RA.resposta_multipla, RA.resposta_texto
    FROM 
        Questao Q
    JOIN 
        RespostaAluno RA ON Q.id = RA.questao_id
    WHERE 
        RA.entrega_id = '$entrega_id'
    ORDER BY 
        Q.id
";
$respostas_result = $conn->query($sql_respostas);

$total_pontos_prova = 0;
$respostas_detalhadas = [];

while ($r = $respostas_result->fetch_assoc()) {
    $total_pontos_prova += (float)$r['pontuacao'];
    
    // Processamento da Resposta do Aluno
    if ($r['tipo'] == 1 || $r['tipo'] == 2) {
        // Múltipla Escolha: Busca as opções marcadas
        $opcoes_marcadas_ids = json_decode($r['resposta_multipla'], true) ?? [];
        $r['resposta_aluno'] = 'Nenhuma opção marcada.';
        
        if (!empty($opcoes_marcadas_ids)) {
             $ids_string = implode(',', array_map('intval', $opcoes_marcadas_ids));
             $sql_opcoes = "SELECT texto_opcao FROM RespostaOpcao WHERE id IN ($ids_string)";
             $opcoes_result = $conn->query($sql_opcoes);
             $opcoes_texto = [];
             while($o = $opcoes_result->fetch_assoc()) {
                 $opcoes_texto[] = $o['texto_opcao'];
             }
             $r['resposta_aluno'] = "<ul><li>" . implode('</li><li>', $opcoes_texto) . "</li></ul>";
        }
    } else {
         // Resposta em Texto
         $r['resposta_aluno'] = nl2br(htmlspecialchars($r['resposta_texto'] ?? 'Nenhuma resposta.'));
    }
    $respostas_detalhadas[] = $r;
}


$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Detalhes da Correção - <?php echo $prova_titulo; ?></title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/estilo_aluno.css"> 
    <style>
        /* Estilos para o Boletim */
        .questao-detalhe { border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; border-radius: 6px; background-color: white; }
        .feedback { border-left: 4px solid #007bff; padding-left: 15px; margin-top: 15px; background-color: #f0f8ff; }
        .nota-alerta { font-size: 1.1em; font-weight: bold; margin-top: 10px; }
        .acerto { color: green; }
        .erro { color: red; }
        .resposta-aluno-box { padding: 10px; border: 1px dashed #ccc; background-color: #fffaf0; margin-top: 10px; }
    </style>
</head>
<body>

<nav class="navbar navbar-fixed-top navbar-aluno">
    <div class="container">
        <div class="navbar-header">
            <a class="navbar-brand" href="painel_aluno.php">Química Educa 🧪</a>
        </div>
        <ul class="nav navbar-nav navbar-right">
            <li><a href="#"><span class="glyphicon glyphicon-user"></span> Olá, <?php echo htmlspecialchars($aluno_nome); ?></a></li>
            <li><a href="../logout.php"><span class="glyphicon glyphicon-log-out"></span> Sair</a></li>
        </ul>
    </div>
</nav>

<div class="container container-estudo">
    <a href="boletim_aluno.php" class="back-link"><span class="glyphicon glyphicon-chevron-left"></span> Voltar para o Boletim</a>
    
    <h1 class="page-header">Detalhes da Correção: <?php echo $prova_titulo; ?></h1>
    <hr>
    
    <div class="alert alert-success text-center">
        <h2>Nota Final: **<?php echo number_format($nota_final, 2); ?>** / <?php echo $total_pontos_prova; ?></h2>
    </div>

    <?php $q_num = 1; ?>
    <?php foreach ($respostas_detalhadas as $r): 
        $nota = (float)$r['nota_obtida'];
        $pontuacao_max = (float)$r['pontuacao'];
        $status_css = ($nota == $pontuacao_max) ? 'acerto' : 'erro';
    ?>
    <div class="questao-detalhe">
        <h4>Questão #<?php echo $q_num++; ?>: <?php echo htmlspecialchars($r['texto_questao']); ?></h4>
        
        <?php if ($r['caminho_imagem']): ?>
            <img src="/quimicaeduca/uploads/questoes/<?php echo htmlspecialchars($r['caminho_imagem']); ?>" style="max-width: 250px; margin-top: 10px;" class="img-responsive" alt="Imagem Questão">
        <?php endif; ?>
        
        <div class="resposta-aluno-box">
            <strong>Sua Resposta:</strong>
            <?php echo $r['resposta_aluno']; ?>
        </div>

        <p class="nota-alerta <?php echo $status_css; ?>">
            Nota na Questão: **<?php echo number_format($nota, 2); ?>** / <?php echo number_format($pontuacao_max, 2); ?>
            <?php echo ($nota == $pontuacao_max) ? '<span class="acerto">(CORRETA)</span>' : '<span class="erro">(INCORRETA/PARCIAL)</span>'; ?>
        </p>

        <?php if ($r['comentario_professor']): ?>
        <div class="feedback">
            <strong>Feedback do Professor:</strong>
            <p><?php echo nl2br(htmlspecialchars($r['comentario_professor'])); ?></p>
        </div>
        <?php endif; ?>
        
    </div>
    <?php endforeach; ?>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
</body>
</html>