<?php
session_start();
include '../conexão/conecta.php'; 

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 2) {
    header("Location: ./login.php");
    exit();
}

$aluno_id = $_SESSION['user_id'];
$prova_id = $conn->real_escape_string($_GET['prova_id']);
$conteudo_id = $conn->real_escape_string($_GET['conteudo_id']);

$conn->begin_transaction();

try {
    // Insere o registro na tabela EntregaProva com status 0 (Desistência)
    // Usamos INSERT IGNORE caso o aluno clique duas vezes (opcional)
    $sql_desistencia = "INSERT INTO EntregaProva (aluno_id, prova_id, status, nota_final) 
                        VALUES ('$aluno_id', '$prova_id', 0, NULL)"; // Status 0 = Desistência/Não Concluído
    
    if (!$conn->query($sql_desistencia)) {
        throw new Exception("Falha ao registrar desistência.");
    }
    
    // Nenhuma resposta é salva, apenas o registro de que ele tentou e desistiu.

    $conn->commit();
    $conn->close();

    // Redireciona de volta para a biblioteca de tópicos
    header("Location: painel_aluno.php?status=desistiu");
    
} catch (Exception $e) {
    $conn->rollback();
    $conn->close();
    header("Location: painel_aluno.php?status=erro&msg=" . urlencode("Erro ao registrar desistência."));
}
exit();
?>