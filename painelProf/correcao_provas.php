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

// Busca apenas provas ENTREGUES e AGUARDANDO CORREÇÃO (Status = 2)
$sql_provas_pendentes = "
    SELECT 
        EP.id AS entrega_id,
        U.nome AS aluno_nome,
        P.titulo AS prova_titulo,
        EP.data_entrega
    FROM 
        EntregaProva EP
    JOIN 
        Usuario U ON EP.aluno_id = U.id
    JOIN
        Prova P ON EP.prova_id = P.id
    WHERE 
        U.professor_id = '$professor_id' AND EP.status = 2
    ORDER BY 
        EP.data_entrega DESC
";

$provas_pendentes = $conn->query($sql_provas_pendentes);

if ($provas_pendentes === FALSE) {
    // Tratamento de erro SQL caso o problema persista no banco de dados.
    die("Erro ao carregar provas pendentes: " . $conn->error);
}

// Verifica se a consulta retornou linhas
$has_pending_provas = $provas_pendentes->num_rows > 0;

$conn->close();

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Correção de Provas - Professor</title>
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
                <li class="active"><a href="correcao_provas.php"><span class="glyphicon glyphicon-check">&nbsp;</span>Corrigir Provas</a></li>
                <li><a href="progresso_alunos.php"><span class="glyphicon glyphicon-stats">&nbsp;</span>Acompanhar Progresso</a></li>
                <li><a href="relatorio_dificuldade.php"><span class="glyphicon glyphicon-list">&nbsp;</span>Régua de Dificuldade</a></li>
            </ul>
        </div>
        
        <div class="col-sm-9 col-sm-offset-3 col-md-10 col-md-offset-2 main-content">
            <h1 class="page-header">✏️ Provas Pendentes de Correção</h1>
            <p>Selecione uma prova abaixo para revisar as questões de texto e atribuir a nota final.</p>
            <hr>

            <?php if ($has_pending_provas): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Aluno</th>
                                <th>Prova</th>
                                <th>Data de Entrega</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($prova = $provas_pendentes->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($prova['aluno_nome']); ?></td>
                                <td><?php echo htmlspecialchars($prova['prova_titulo']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($prova['data_entrega'])); ?></td>
                                <td>
                                    <a href="detalhe_correcao.php?entrega_id=<?php echo $prova['entrega_id']; ?>" class="btn btn-sm btn-warning">
                                        <span class="glyphicon glyphicon-edit"></span> Corrigir
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="alert alert-info">Nenhuma prova pendente de correção manual no momento. 🎉</p>
                <p class="text-muted">Apenas provas que contêm questões dissertativas (de escrever) e foram entregues pelos alunos aparecem aqui.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
</body>
</html>