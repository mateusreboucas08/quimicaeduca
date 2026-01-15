<?php
session_start();
include '../conexão/conecta.php';

// GARANTIA DE ACESSO: SÓ PROFESSOR (tipo 1) PODE ACESSAR
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 1) {
    header("Location: ./login.php");
    exit();
}

$nome_professor = $_SESSION['user_name'];
$professor_id = $_SESSION['user_id'];

// 1. Busca dos Alunos e Cálculo do Progresso Total
// Esta consulta (JOINs e subconsultas) é necessária para calcular:
// (Total de Conteúdos Concluídos) / (Total de Conteúdos Publicados) para cada aluno.

$sql_progresso = "
    SELECT 
        U.id AS aluno_id,
        U.nome AS aluno_nome,
        U.email,
        COUNT(C.id) AS total_conteudo,
        COUNT(PA.conteudo_id) AS total_concluido,
        IF(COUNT(C.id) > 0, (COUNT(PA.conteudo_id) / COUNT(C.id)) * 100, 0) AS porcentagem
    FROM 
        Usuario U 
    LEFT JOIN 
        Conteudo C ON C.topico_id IN (SELECT id FROM Topico) -- Pega todos os conteúdos publicados
    LEFT JOIN 
        ProgressoAluno PA ON U.id = PA.aluno_id AND C.id = PA.conteudo_id
    WHERE 
        U.professor_id = '$professor_id' AND U.tipo_usuario = 2
    GROUP BY 
        U.id, U.nome, U.email
    ORDER BY 
        U.nome ASC;
";

$progresso_result = $conn->query($sql_progresso);

$conn->close();

function formatarPorcentagem($valor) {
    return number_format($valor, 0) . "%";
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Progresso dos Alunos - Professor</title>
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
                <li><a href="criar_conteudo.php"><span class="glyphicon glyphicon-pencil">&nbsp;</span>Criar Conteúdo</a></li>
                <li><a href="correcao_provas.php"><span class="glyphicon glyphicon-check">&nbsp;</span>Corrigir Provas</a></li>
                <li><a href="progresso_alunos.php"><span class="glyphicon glyphicon-stats">&nbsp;</span>Acompanhar Progresso</a></li>
                <li><a href="relatorio_dificuldade.php"><span class="glyphicon glyphicon-list">&nbsp;</span>Régua de Dificuldade</a></li>
            </ul>
        </div>

        <div class="col-sm-9 col-sm-offset-3 col-md-10 col-md-offset-2 main-content">
            <h1 class="page-header">Acompanhamento de Progresso</h1>
            
            <ul class="breadcrumb">
                <li><span class="glyphicon glyphicon-home">&nbsp;</span>Home</li>
                <li><a href="progresso_alunos.php">Progresso</a></li>
            </ul>

            <div class="card-info">
                <h2 class="sub-header">📊 Progresso Geral por Aluno</h2>
            
                <?php if ($progresso_result && $progresso_result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover tabela-progresso">
                            <thead>
                                <tr>
                                    <th>Aluno</th>
                                    <th>Email</th>
                                    <th>Progresso (%)</th>
                                    <th>Concluído / Total</th>
                                    <th>Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($aluno_progresso = $progresso_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($aluno_progresso['aluno_nome']); ?></td>
                                    <td><?php echo htmlspecialchars($aluno_progresso['email']); ?></td>
                                    <td>
                                        <div class="barra-progresso">
                                            <div class="progresso-fill" style="width: <?php echo $aluno_progresso['porcentagem']; ?>%;"></div>
                                        </div>
                                        <?php echo formatarPorcentagem($aluno_progresso['porcentagem']); ?>
                                    </td>
                                    <td><?php echo $aluno_progresso['total_concluido']; ?> / <?php echo $aluno_progresso['total_conteudo']; ?></td>
                                    <td>
                                        <a href="detalhe_progresso.php?aluno_id=<?php echo $aluno_progresso['aluno_id']; ?>" class="btn btn-xs btn-info">Ver Detalhes</a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p>Nenhum aluno associado ou nenhum conteúdo publicado ainda.</p>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
</body>
</html>