<?php
session_start();
include '../conexão/conecta.php'; 

// GARANTIA DE ACESSO: SÓ PROFESSOR (tipo 1) PODE ACESSAR
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 1) {
    header("Location: ./login.php");
    exit();
}

$nome_professor = $_SESSION['user_name']; // Variável necessária para o Navbar
$professor_id = $_SESSION['user_id'];

// 1. Consulta para calcular a Média de Acerto por Questão (CORRIGIDA)
$sql_dificuldade = "
    SELECT 
        Q.id AS questao_id,
        Q.texto_questao,
        Q.pontuacao,
        PR.titulo AS prova_titulo,
        SUM(CASE WHEN RA.nota_obtida = Q.pontuacao THEN 1 ELSE 0 END) AS total_acertos,
        COUNT(RA.questao_id) AS total_respostas
    FROM 
        Questao Q
    JOIN 
        Prova PR ON Q.prova_id = PR.id
    JOIN
        Conteudo C ON PR.conteudo_id = C.id
    LEFT JOIN 
        RespostaAluno RA ON Q.id = RA.questao_id
    LEFT JOIN
        EntregaProva EP ON RA.entrega_id = EP.id
    LEFT JOIN 
        Usuario U ON EP.aluno_id = U.id
    WHERE 
        U.professor_id = '$professor_id' AND EP.status IN (2, 3) 
    GROUP BY 
        Q.id, Q.texto_questao, PR.titulo
    HAVING 
        total_respostas > 0 
    ORDER BY 
        PR.titulo, Q.id
";

$dificuldade_result = $conn->query($sql_dificuldade);
if ($dificuldade_result === FALSE) {
    die("Erro SQL ao gerar relatório: " . $conn->error);
}

$conn->close();

function getClassificacao($ia) {
    if ($ia >= 0.75) {
        return ['FÁCIL', 'success'];
    } elseif ($ia >= 0.25) {
        return ['MÉDIO', 'warning'];
    } else {
        return ['DIFÍCIL', 'danger'];
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Régua de Dificuldade - Professor</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/estilo_admin.css"> 
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
                <li><a href="./logout.php"><span class="glyphicon glyphicon-log-out"></span> Logout</a></li>
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
                <li><a href="criar_conteudo.php"><span class="glyphicon glyphicon-pencil">&nbsp;</span>Criar Conteúdo</a></li>
                <li><a href="correcao_provas.php"><span class="glyphicon glyphicon-check">&nbsp;</span>Corrigir Provas</a></li>
                <li><a href="progresso_alunos.php"><span class="glyphicon glyphicon-stats">&nbsp;</span>Acompanhar Progresso</a></li>
                <li class="active"><a href="relatorio_dificuldade.php"><span class="glyphicon glyphicon-list">&nbsp;</span>Régua de Dificuldade</a></li>
            </ul>
        </div>
        
        <div class="col-sm-9 col-sm-offset-3 col-md-10 col-md-offset-2 main-content">
            <h1 class="page-header">📉 Régua de Dificuldade das Questões</h1>
            <p>Análise do desempenho dos alunos em cada questão para classificar o nível de dificuldade.</p>
            <hr>

            <?php if ($dificuldade_result && $dificuldade_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Prova</th>
                                <th>Questão</th>
                                <th>Total de Acertos</th>
                                <th>Total de Tentativas</th>
                                <th>Índice de Acerto (IA)</th>
                                <th>Classificação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($q = $dificuldade_result->fetch_assoc()): 
                                $ia = $q['total_acertos'] / $q['total_respostas'];
                                list($classificacao, $cor) = getClassificacao($ia);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($q['prova_titulo']); ?></td>
                                <td><?php echo substr(htmlspecialchars($q['texto_questao']), 0, 50) . '...'; ?></td>
                                <td><?php echo $q['total_acertos']; ?></td>
                                <td><?php echo $q['total_respostas']; ?></td>
                                <td><?php echo number_format($ia * 100, 1) . '%'; ?></td>
                                <td><span class="label label-<?php echo $cor; ?>"><?php echo $classificacao; ?></span></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="alert alert-info">Ainda não há dados suficientes para gerar a régua de dificuldade (nenhuma prova corrigida ou entregue).</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
</body>
</html>