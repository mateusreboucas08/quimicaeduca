<?php
session_start();
include '../conexão/conecta.php'; 

// GARANTIA DE ACESSO
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 2 || !isset($_GET['conteudo_id'])) {
    header("Location: ./login.php");
    exit();
}

$aluno_nome = $_SESSION['user_name'];
$conteudo_id = $conn->real_escape_string($_GET['conteudo_id']);

// Busca o conteúdo da aula
$sql_aula = "SELECT titulo, texto_aula, topico_id FROM Conteudo WHERE id = '$conteudo_id' AND tipo = 1";
$aula_info = $conn->query($sql_aula)->fetch_assoc();

if (!$aula_info) {
    die("Conteúdo da aula não encontrado ou tipo incorreto.");
}

$titulo_aula = htmlspecialchars($aula_info['titulo']);
// O texto da aula pode conter HTML, então usamos echo diretamente (se confiável) ou nl2br.
$texto_aula = $aula_info['texto_aula']; 
$topico_id = $aula_info['topico_id'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Aula: <?php echo $titulo_aula; ?></title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/estilo_aluno.css"> 
    <style>
        .aula-content {
            background-color: white;
            padding: 30px;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            line-height: 1.6;
            font-size: 1.1em;
        }
        .aula-content h1, .aula-content h2, .aula-content h3 { color: #17a2b8; margin-top: 25px; }
    </style>
</head>
<body>

<nav class="navbar navbar-fixed-top navbar-aluno">...</nav> <div class="container container-estudo">
    <a href="visualizar_conteudo.php?topico_id=<?php echo $topico_id; ?>" class="back-link">
        <span class="glyphicon glyphicon-chevron-left"></span> Voltar para o Tópico
    </a>
    
    <h1 class="page-header">📘 <?php echo $titulo_aula; ?></h1>

    <div class="aula-content">
        <?php 
        // Se você permitir HTML (avançado), use: echo $texto_aula;
        // Se você quiser apenas quebras de linha formatadas, use:
        echo nl2br(htmlspecialchars($texto_aula)); 
        ?>
    </div>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
</body>
</html>