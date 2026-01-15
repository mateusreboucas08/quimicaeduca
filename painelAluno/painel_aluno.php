<?php
session_start();
// Caminho de conexão corrigido para garantir que não haja caracteres inválidos
include '../conexão/conecta.php'; 

// GARANTIA DE ACESSO
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 2) {
    header("Location: ./login.php");
    exit();
}

// Inicialização da variável reportada na linha 7
$nome_aluno = $_SESSION['user_name'];

// 1. Busca todos os tópicos disponíveis no banco de dados
$topicos_sql = "SELECT id, titulo, descricao FROM Topico ORDER BY titulo ASC";
$topicos_result = $conn->query($topicos_sql);

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Painel do Aluno - Química Educa</title>
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
            <li><a href="#"><span class="glyphicon glyphicon-user"></span> Olá, <?php echo htmlspecialchars($nome_aluno); ?></a></li>
            <li><a href="../logout.php"><span class="glyphicon glyphicon-log-out"></span> Sair</a></li>
        </ul>
    </div>
</nav>

<div class="container container-estudo">
    
    <div class="header-welcome">
        <h1>Bem-vindo(a) à Área de Estudos!</h1>
        <p>Selecione um tópico abaixo para começar ou continuar seus estudos de Química.</p>
    </div>

    <div class="topico-card" style="border-left: 5px solid #28a745;">
        <h3><a href="boletim_aluno.php">
            <span class="glyphicon glyphicon-list-alt"></span> Acessar Meu Boletim e Notas
        </a></h3>
        <p>Visualize suas notas, o status das provas e os comentários detalhados do professor.</p>
    </div>

    <h2>📚 Biblioteca de Conteúdo</h2>

    <?php
    if ($topicos_result && $topicos_result->num_rows > 0) {
        while($topico = $topicos_result->fetch_assoc()) {
            ?>
            <div class="topico-card">
                <h3>
                    <a href="visualizar_conteudo.php?topico_id=<?php echo $topico['id']; ?>">
                        <?php echo htmlspecialchars($topico['titulo']); ?>
                    </a>
                </h3>
                <p><?php echo htmlspecialchars($topico['descricao']); ?></p>
            </div>
            <?php
        }
    } else {
        echo "<p class='alert alert-warning'>Nenhum tópico de Química encontrado. Aguarde o Professor(a) liberar o conteúdo.</p>";
    }
    ?>
    
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
</body>
</html>