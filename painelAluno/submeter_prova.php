<?php
session_start();
include '../conexão/conecta.php'; 

// Redireciona se não for Aluno ou não for POST
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 2 || $_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: ./login.php");
    exit();
}

$aluno_id = $_SESSION['user_id'];
$prova_id = $conn->real_escape_string($_POST['prova_id']);
$conteudo_id = $conn->real_escape_string($_POST['conteudo_id']);
$respostas_aluno = $_POST['respostas'] ?? [];
$nota_inicial_mc = 0.00; // Iremos calcular apenas a nota das Múltipla Escolha aqui
$total_pontuacao = 0.00;

// FORÇA STATUS PARA 2 (Aguardando Correção)
$STATUS_FORCADO = 2; 

// 1. Inicia Transação
$conn->begin_transaction();

try {
    // 2. Cria o Registro de Entrega da Prova (STATUS FORÇADO: 2)
    // NOTA: nota_final é NULL na inserção inicial, mas o status é 2.
    $sql_entrega = "INSERT INTO EntregaProva (aluno_id, prova_id, status) VALUES ('$aluno_id', '$prova_id', '$STATUS_FORCADO')";
    if (!$conn->query($sql_entrega)) {
        throw new Exception("Falha ao criar registro de entrega.");
    }
    $entrega_id = $conn->insert_id;

    // 3. Busca Gabaritos e Questões
    $sql_questoes_gabarito = "SELECT * FROM Questao WHERE prova_id = '$prova_id'";
    $questoes_result = $conn->query($sql_questoes_gabarito);

    if ($questoes_result->num_rows == 0) {
        throw new Exception("Nenhuma questão encontrada para correção.");
    }
    
    // 4. Processa e Corrige Questões (Auto-Correção apenas para MC)
    while ($q = $questoes_result->fetch_assoc()) {
        $questao_id = $q['id'];
        $tipo = (int)$q['tipo'];
        $pontuacao = (float)$q['pontuacao'];
        $total_pontuacao += $pontuacao;

        $resposta_multipla = null;
        $resposta_texto = null;
        $nota_obtida = 0.00; // Nota inicial, calculada apenas para Múltipla Escolha
        
        // Se o aluno respondeu a questão
        if (isset($respostas_aluno[$questao_id])) {
            $resposta_bruta = $respostas_aluno[$questao_id];

            if ($tipo == 1 || $tipo == 2) { // Múltipla Escolha
                $gabarito_ids = json_decode($q['gabarito_multipla'], true);
                
                if (is_array($resposta_bruta)) {
                    $resposta_ids = array_map('intval', $resposta_bruta);
                    sort($resposta_ids);
                } else {
                    $resposta_ids = [(int)$resposta_bruta];
                }
                
                $resposta_multipla = json_encode($resposta_ids);

                // Auto-Correção: Se for correta, atribui a pontuação.
                // Esta pontuação será a NOTA INICIAL APENAS.
                if ($gabarito_ids == $resposta_ids) {
                    $nota_obtida = $pontuacao;
                }
                $nota_inicial_mc += $nota_obtida;

            } elseif ($tipo == 3) { // Resposta em Texto (Dissertativa)
                $resposta_texto = $conn->real_escape_string($resposta_bruta);
                // Nota obtida para dissertativa permanece 0.00 até a correção manual.
                $nota_obtida = 0.00;
            }
        }
        
        // 5. Insere a Resposta do Aluno
        $sql_resp = "INSERT INTO RespostaAluno (entrega_id, questao_id, resposta_multipla, resposta_texto, nota_obtida) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql_resp);
        
        // Bind parameters (necessário para prepared statements)
        $resp_multi = $resposta_multipla; 
        $resp_text = $resposta_texto;
        $nota_obt = $nota_obtida;
        
        $stmt->bind_param("iisss", $entrega_id, $questao_id, $resp_multi, $resp_text, $nota_obt);

        if (!$stmt->execute()) {
             throw new Exception("Falha ao inserir resposta do aluno. Detalhe: " . $stmt->error);
        }
        $stmt->close();
    }
    
    // 6. Atualiza a Nota Inicial (Apenas MC) e o Status de Entrega (STATUS FORÇADO: 2)
    // Usamos a nota_inicial_mc para o professor ter uma base, mas o status 2 bloqueia.
    $novo_status = $STATUS_FORCADO; 

    // Usamos a nota_inicial_mc como base para o professor. O professor pode alterá-la.
    $sql_update_entrega = "UPDATE EntregaProva SET nota_final = '$nota_inicial_mc', status = '$novo_status' WHERE id = '$entrega_id'";
    if (!$conn->query($sql_update_entrega)) {
        throw new Exception("Falha ao atualizar nota final.");
    }

    // 7. Finaliza a transação
    $conn->commit();
    $conn->close();

    $mensagem_status = "Prova entregue com sucesso! O resultado e as correções serão liberados após a avaliação do professor.";

    // Redireciona de volta para o painel do aluno com mensagem
    header("Location: painel_aluno.php?status=prova_entregue&msg=" . urlencode($mensagem_status));
    
} catch (Exception $e) {
    // Trata erros de banco de dados ou lógica
    if (isset($conn) && is_object($conn)) {
        $conn->rollback();
        $conn->close();
    }
    header("Location: painel_aluno.php?status=erro&msg=" . urlencode("Falha na submissão da prova. Detalhe: " . $e->getMessage()));
}
exit();