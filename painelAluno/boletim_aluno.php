<?php
session_start();
include '../conexão/conecta.php'; 

// GARANTIA DE ACESSO
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 2) {
    header("Location: ./login.php");
    exit();
}

$aluno_id = $_SESSION['user_id'];
$aluno_nome = $_SESSION['user_name'];

// 1. Busca todas as Entregas de Prova do Aluno
$sql_entregas = "
    SELECT 
        EP.id AS entrega_id,
        P.titulo AS prova_titulo,
        EP.nota_final,
        EP.status,
        EP.data_entrega
    FROM 
        EntregaProva EP
    JOIN 
        Prova P ON EP.prova_id = P.id
    WHERE 
        EP.aluno_id = '$aluno_id'
    ORDER BY 
        EP.data_entrega DESC
";

$entregas_result = $conn->query($sql_entregas);
$conn->close();

function getStatusText($status) {
    switch ($status) {
        case 0: return '<span class="label label-danger">Desistiu/Não Concluída</span>';
        case 2: return '<span class="label label-warning">Aguardando Correção</span>';
        case 3: return '<span class="label label-success">Corrigida</span>';
        default: return 'Desconhecido';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Boletim - <?php echo $aluno_nome; ?></title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/estilo_aluno.css"> 
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
    <h1 class="page-header">📈 Meu Boletim de Notas</h1>
    <p><a href="painel_aluno.php">← Voltar para a Área de Estudos</a></p>
    <hr>
    
    <?php if ($entregas_result && $entregas_result->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Prova/Atividade</th>
                        <th>Data de Entrega</th>
                        <th>Status</th>
                        <th>Nota Final</th>
                        <th>Detalhes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($entrega = $entregas_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($entrega['prova_titulo']); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($entrega['data_entrega'])); ?></td>
                        <td><?php echo getStatusText($entrega['status']); ?></td>
                        <td>
                            <?php 
                            if ($entrega['status'] == 3) {
                                echo '<strong>' . number_format($entrega['nota_final'], 2) . '</strong>';
                            } elseif ($entrega['status'] == 2) {
                                echo 'Pendente';
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td>
                            <?php if ($entrega['status'] == 3): ?>
                                <a href="detalhe_boletim.php?entrega_id=<?php echo $entrega['entrega_id']; ?>" class="btn btn-xs btn-info">Ver Correção</a>
                            <?php elseif ($entrega['status'] == 2): ?>
                                Aguardando...
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="alert alert-info">Você ainda não entregou nenhuma prova ou atividade.</p>
    <?php endif; ?>
    
</div>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
</body>
</html>