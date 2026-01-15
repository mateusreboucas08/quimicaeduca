<?php
session_start();
include '../conexão/conecta.php'; 

// GARANTIA DE ACESSO: SÓ ALUNO (tipo 2) PODE ACESSAR
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 2 || !isset($_GET['conteudo_id'])) {
    header("Location: ./login.php");
    exit();
}

$conteudo_id = $conn->real_escape_string($_GET['conteudo_id']);
$aluno_nome = $_SESSION['user_name'];

// 1. Busca os detalhes do conteúdo
// Precisamos do 'tipo' para saber como exibir, e do 'link_arquivo'
$sql_conteudo = "
    SELECT 
        titulo, tipo, link_arquivo
    FROM 
        Conteudo
    WHERE 
        id = '$conteudo_id'
";
$conteudo_info = $conn->query($sql_conteudo)->fetch_assoc();

if (!$conteudo_info) {
    die("Conteúdo não encontrado.");
}

$titulo = htmlspecialchars($conteudo_info['titulo']);
$tipo = (int)$conteudo_info['tipo'];
$link_arquivo = htmlspecialchars($conteudo_info['link_arquivo']);

// 2. Processa o redirecionamento baseado no tipo
$destino_url = '';
$tipo_display = '';

switch ($tipo) {
    case 1: // Aula/Texto: Conteúdo longo que será exibido em uma página formatada
        $destino_url = "exibir_aula.php?conteudo_id=" . $conteudo_id;
        $tipo_display = 'Aula/Texto';
        break;
        
    case 2: // Atividade/Quiz: Redireciona para o formulário de prova
        $destino_url = "fazer_prova.php?conteudo_id=" . $conteudo_id;
        $tipo_display = 'Atividade/Quiz';
        break;
    
    case 3: // Resumo/PDF
    case 4: // Link Externo/Vídeo
        // Redireciona diretamente para o link_arquivo (URL/Caminho)
        if (empty($link_arquivo) || $link_arquivo == 'N/A') {
             die("Link de conteúdo não configurado.");
        }
        $destino_url = $link_arquivo;
        $tipo_display = 'Material de Estudo/Leitura';
        break;
    
    default:
        die("Tipo de conteúdo inválido.");
}

// 3. Redirecionamento Final
if (!empty($destino_url)) {
    // Redirecionamento HTTP
    header("Location: " . $destino_url);
    exit();
}

// Se, por algum motivo, o redirecionamento não ocorrer imediatamente (fallback)
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Acessando Conteúdo - <?php echo $titulo; ?></title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/estilo_aluno.css"> 
</head>
<body>

<nav class="navbar navbar-fixed-top navbar-aluno">...</nav> 

<div class="container container-estudo" style="text-align: center; padding-top: 100px;">
    <h1>Acessando o Material...</h1>
    <p>Você será redirecionado(a) em instantes para: **<?php echo $titulo; ?>** (<?php echo $tipo_display; ?>).</p>
    <p>Se o redirecionamento falhar, <a href="<?php echo $link_arquivo; ?>" target="_blank">clique aqui.</a></p>
    <p><a href="painel_aluno.php">Voltar para o Painel</a></p>
</div>
</body>
</html>