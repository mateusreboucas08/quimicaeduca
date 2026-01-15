<?php
session_start();
include '../conexão/conecta.php';

// Redireciona se não estiver logado ou não for Aluno
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 2 || $_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: ./login.php");
    exit();
}

$aluno_id = $_SESSION['user_id'];
// Sanitiza o ID do conteúdo recebido do formulário POST
$conteudo_id = $conn->real_escape_string($_POST['conteudo_id']);

// Verifica se o progresso já existe para evitar duplicatas (chave UNIQUE na tabela)
$sql_check = "SELECT id FROM ProgressoAluno WHERE aluno_id = '$aluno_id' AND conteudo_id = '$conteudo_id'";
$result_check = $conn->query($sql_check);

if ($result_check->num_rows == 0) {
    // Se o progresso não existe, insere como CONCLUÍDO
    $sql_insert = "INSERT INTO ProgressoAluno (aluno_id, conteudo_id, completo) 
                   VALUES ('$aluno_id', '$conteudo_id', TRUE)";

    if ($conn->query($sql_insert) === TRUE) {
        $status = "sucesso";
    } else {
        $status = "erro";
    }
} else {
    // Se já existe, atualiza a data de conclusão (caso queira reabrir o progresso no futuro)
    $sql_update = "UPDATE ProgressoAluno SET data_conclusao = NOW(), completo = TRUE 
                   WHERE aluno_id = '$aluno_id' AND conteudo_id = '$conteudo_id'";
    
    if ($conn->query($sql_update) === TRUE) {
        $status = "sucesso";
    } else {
        $status = "erro";
    }
}

$conn->close();

// Redireciona o aluno de volta para a página do conteúdo, mostrando o status
header("Location: visualizar_conteudo.php?topico_id=" . $_POST['topico_id'] . "&status=" . $status);
exit();
?>