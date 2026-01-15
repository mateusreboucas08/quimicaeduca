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

// 1. Busca o Código Público do Professor e a contagem de alunos
$sql_info = "SELECT codigo_publico FROM Usuario WHERE id = '$professor_id'";
$result_info = $conn->query($sql_info)->fetch_assoc();
$codigo_publico = $result_info['codigo_publico'] ?? 'NÃO DEFINIDO';

$sql_alunos = "SELECT id, nome, email, data_cadastro FROM Usuario WHERE professor_id = '$professor_id' AND tipo_usuario = 2 ORDER BY nome ASC";
$alunos_result = $conn->query($sql_alunos);

$conn->close();

$total_alunos = $alunos_result->num_rows ?? 0;

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Painel do Professor - Gestão de Química</title>
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
            <h1 class="page-header">Dashboard</h1>
            
            <ul class="breadcrumb">
                <li><span class="glyphicon glyphicon-home">&nbsp;</span>Home</li>
                <li><a href="painel_professor.php">Dashboard</a></li>
            </ul>
            
            <div class="card-info" style="background-color: #d4edda; border-color: #c3e6cb;">
                <h3>Seu Código Público de Cadastro:</h3>
                <p>Compartilhe este código com seus alunos.</p>
                <p class="codigo" style="font-size: 2em; color: #155724; font-weight: bold;"><?php echo htmlspecialchars($codigo_publico); ?></p>
            </div>

            <h2 class="sub-header">👥 Alunos Cadastrados (Total: <?php echo $total_alunos; ?>)</h2>
            
            <?php if ($total_alunos > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Email</th>
                                <th>Desde</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($aluno = $alunos_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($aluno['nome']); ?></td>
                                <td><?php echo htmlspecialchars($aluno['email']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($aluno['data_cadastro'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>Nenhum aluno associado com o seu código ainda.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
</body>
</html>