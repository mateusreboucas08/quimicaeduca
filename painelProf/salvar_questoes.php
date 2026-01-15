<?php
session_start();
// SUPRIME ERROS TEMPORARIAMENTE
error_reporting(0); 

// NOVO CAMINHO DE INCLUDE
include '../conexão/conecta.php'; 

// Configura o cabeçalho para retornar JSON
header('Content-Type: application/json');

// GARANTIA DE ACESSO
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 1 || $_SERVER["REQUEST_METHOD"] != "POST") {
    echo json_encode(['status' => 'error', 'message' => 'Acesso negado ou método inválido.']);
    exit();
}

$prova_id = isset($_POST['prova_id']) ? $conn->real_escape_string($_POST['prova_id']) : null;
$questoes_data = $_POST['questoes'] ?? [];

if (!$prova_id || empty($questoes_data)) {
    echo json_encode(['status' => 'error', 'message' => 'ID da Prova ou dados das questões não fornecidos.']);
    exit();
}

// Diretório de upload (IMPORTANTE: Verifique se as permissões 777 estão aplicadas)
$upload_dir = __DIR__ . '/../uploads/questoes/'; 

// Tenta criar o diretório se não existir
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0777, true)) {
        echo json_encode(['status' => 'error', 'message' => 'Falha ao criar diretório de upload. Verifique as permissões da pasta "uploads".']);
        exit();
    }
}

$conn->begin_transaction();

try {
    // Itera sobre os dados das questões (POST)
    foreach ($questoes_data as $index => $q) {
        
        $tipo = (int)$q['tipo'];
        $texto = $conn->real_escape_string($q['texto']);
        $pontuacao = $conn->real_escape_string($q['pontuacao']);
        
        $gabarito_texto = null;
        $caminho_imagem = null; 
        
        // 1. UPLOAD DA IMAGEM
        // NOVO: Busca o arquivo usando o nome único do campo
        $campo_file_name = 'imagem_questao_' . $index; 
        
        if (isset($_FILES[$campo_file_name]) && $_FILES[$campo_file_name]['error'] === UPLOAD_ERR_OK) {
            
            $file_info = $_FILES[$campo_file_name];
            $file_name = $file_info['name'];
            $file_tmp = $file_info['tmp_name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                $new_file_name = uniqid('qimg_', true) . '.' . $file_ext;
                $target_file = $upload_dir . $new_file_name;
                
                if (move_uploaded_file($file_tmp, $target_file)) {
                    $caminho_imagem = $new_file_name; // Salva apenas o nome/caminho relativo no DB
                } else {
                    // Falha de permissão ou caminho
                    throw new Exception("Falha ao mover arquivo. Verifique permissões (caminho: " . $target_file . ")");
                }
            } else {
                throw new Exception("Tipo de arquivo inválido para imagem da questão.");
            }
        }
        
        // 2. Trata Gabarito para Questões de Texto
        if ($tipo === 3) {
            $gabarito_texto = $conn->real_escape_string($q['gabarito_texto']);
        } 
        
        // 3. Insere a Questão na Tabela Questao
        $sql_questao = "INSERT INTO Questao (prova_id, tipo, texto_questao, gabarito_texto, pontuacao, caminho_imagem) 
                        VALUES ('$prova_id', '$tipo', '$texto', " . ($gabarito_texto ? "'$gabarito_texto'" : "NULL") . ", '$pontuacao', " . ($caminho_imagem ? "'$caminho_imagem'" : "NULL") . ")";
        
        if (!$conn->query($sql_questao)) {
            throw new Exception("Erro ao inserir Questão: " . $conn->error);
        }
        $questao_id = $conn->insert_id;
        
        // 4. Trata Opções e Gabarito (Para Múltipla Escolha)
        if ($tipo === 1 || $tipo === 2) {
            $gabarito_ids = [];
            
            if (!empty($q['opcoes'])) {
                foreach ($q['opcoes'] as $opcao) {
                    $texto_opcao = $conn->real_escape_string($opcao['texto']);
                    $correta = isset($opcao['correta']) ? 1 : 0;
                    
                    $sql_opcao = "INSERT INTO RespostaOpcao (questao_id, texto_opcao, correta) 
                                  VALUES ('$questao_id', '$texto_opcao', '$correta')";
                    
                    if (!$conn->query($sql_opcao)) {
                        throw new Exception("Erro ao inserir Opção: " . $conn->error);
                    }
                    
                    if ($correta) {
                        $gabarito_ids[] = $conn->insert_id;
                    }
                }
            }

            // 5. Atualiza a coluna gabarito_multipla na tabela Questao
            $gabarito_json = json_encode($gabarito_ids);
            $sql_update_gab = "UPDATE Questao SET gabarito_multipla = '$gabarito_json' WHERE id = '$questao_id'";
            if (!$conn->query($sql_update_gab)) {
                throw new Exception("Erro ao atualizar Gabarito Múltipla: " . $conn->error);
            }
        }
    }
    
    $conn->commit();
    $conn->close();
    echo json_encode(['status' => 'success', 'message' => 'Prova criada com sucesso!']);
    
} catch (Exception $e) {
    $conn->rollback();
    $conn->close();
    echo json_encode(['status' => 'error', 'message' => 'Falha ao salvar a prova. Detalhe: ' . $e->getMessage()]);
}
?>