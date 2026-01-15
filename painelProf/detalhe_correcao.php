<?php
session_start();
include '../conexão/conecta.php'; 

// GARANTIA DE ACESSO: SÓ PROFESSOR (tipo 1) PODE ACESSAR
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 1 || !isset($_GET['entrega_id'])) {
    header("Location: ./login.php");
    exit();
}

$entrega_id = $conn->real_escape_string($_GET['entrega_id']);
$professor_id = $_SESSION['user_id'];
$nome_professor = $_SESSION['user_name'];
$mensagem_status = '';

// --- BLOCO 1: BUSCA INICIAL DE DADOS ---

// Busca Detalhes da Entrega e do Aluno
$sql_entrega_info = "
    SELECT 
        EP.id, U.nome AS aluno_nome, P.titulo AS prova_titulo, EP.nota_final, EP.status, P.id AS prova_id
    FROM 
        EntregaProva EP
    JOIN 
        Usuario U ON EP.aluno_id = U.id
    JOIN
        Prova P ON EP.prova_id = P.id
    WHERE 
        EP.id = '$entrega_id' AND U.professor_id = '$professor_id'
";
$entrega_info = $conn->query($sql_entrega_info)->fetch_assoc();

if (!$entrega_info) {
    die("Acesso negado ou prova não encontrada.");
}

$aluno_nome = $entrega_info['aluno_nome'];
$prova_titulo = $entrega_info['prova_titulo'];
$status_prova = $entrega_info['status'];
$prova_id = $entrega_info['prova_id'];
$nota_atual = $entrega_info['nota_final'] ?? 0.00;


// 2. Busca todas as Questões, Respostas do Aluno e Gabaritos
$sql_respostas = "
    SELECT 
        Q.id AS questao_id, Q.texto_questao, Q.tipo, Q.pontuacao, Q.gabarito_texto, Q.caminho_imagem, Q.gabarito_multipla, 
        RA.nota_obtida, RA.resposta_texto, RA.resposta_multipla, RA.comentario_professor
    FROM 
        Questao Q
    JOIN 
        RespostaAluno RA ON Q.id = RA.questao_id
    WHERE 
        RA.entrega_id = '$entrega_id'
    ORDER BY 
        Q.id
";
$respostas_result = $conn->query($sql_respostas);

$total_pontos_prova = 0;
$respostas_a_corrigir = [];
$todas_opcoes_ids = []; // Array para coletar todos os IDs de opções para uma única busca massiva

while ($r = $respostas_result->fetch_assoc()) {
    $total_pontos_prova += (float)$r['pontuacao'];
    
    // Coletar IDs das opções marcadas pelo aluno e do gabarito para buscar o TEXTO
    if ($r['tipo'] == 1 || $r['tipo'] == 2) {
        $opcoes_marcadas = json_decode($r['resposta_multipla'], true) ?? [];
        $gabarito_ids = json_decode($r['gabarito_multipla'], true) ?? [];
        
        $r['opcoes_marcadas_ids'] = $opcoes_marcadas;
        $r['gabarito_ids'] = $gabarito_ids;
        
        $todas_opcoes_ids = array_merge($todas_opcoes_ids, $opcoes_marcadas, $gabarito_ids);
    }
    
    $respostas_a_corrigir[] = $r;
}

// 2.1. Busca o TEXTO de todas as opções necessárias (CORREÇÃO DE EXIBIÇÃO)
$mapa_opcoes_texto = [];
if (!empty($todas_opcoes_ids)) {
    // Remove duplicatas e formata para SQL IN
    $unique_ids = array_unique(array_map('intval', $todas_opcoes_ids));
    $ids_string = implode(',', $unique_ids);
    
    if (!empty($ids_string)) {
        $sql_opcoes_texto = "SELECT id, texto_opcao FROM RespostaOpcao WHERE id IN ($ids_string)";
        $result_opcoes_texto = $conn->query($sql_opcoes_texto);
        while ($o = $result_opcoes_texto->fetch_assoc()) {
            $mapa_opcoes_texto[$o['id']] = htmlspecialchars($o['texto_opcao']);
        }
    }
}


// --- BLOCO 3: PROCESSAMENTO DO FORMULÁRIO DE CORREÇÃO (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['notas'])) {
    
    $novas_notas = $_POST['notas'];
    $comentarios = $_POST['comentarios'];
    $nota_total_final = 0.00;
    
    // Reabre a conexão se ela foi fechada
    if ($conn->connect_errno) {
        include '../conexão/conecta.php';
        $conn->select_db("nome_do_seu_banco"); // Substitua pelo nome do seu banco
    }

    $conn->begin_transaction();
    
    try {
        foreach ($respostas_a_corrigir as $r) {
            $questao_id = $r['questao_id'];
            $pontuacao_max = (float)$r['pontuacao'];
            
            if (isset($novas_notas[$questao_id])) {
                $nota_input = $novas_notas[$questao_id];
                
                // Sanitiza e garante que a nota não exceda a pontuação máxima
                $nota_da_questao = min((float)$nota_input, $pontuacao_max);
                $comentario = $conn->real_escape_string($comentarios[$questao_id] ?? '');

                // Acumular Nota Total
                $nota_total_final += $nota_da_questao;
                
                // Atualizar RespostaAluno no banco
                $sql_update_resp = "
                    UPDATE RespostaAluno 
                    SET nota_obtida = '$nota_da_questao', 
                        comentario_professor = " . ($comentario ? "'$comentario'" : "NULL") . " 
                    WHERE entrega_id = '$entrega_id' AND questao_id = '$questao_id'
                ";
                
                if (!$conn->query($sql_update_resp)) {
                    throw new Exception("Erro ao atualizar nota da questão $questao_id: " . $conn->error);
                }
            } else {
                 // Se o campo de nota foi removido ou não foi enviado (fallback)
                 $nota_total_final += (float)$r['nota_obtida'];
            }
        }
        
        // Finaliza a correção atualizando a EntregaProva
        $sql_update_entrega = "UPDATE EntregaProva SET nota_final = '$nota_total_final', status = 3 WHERE id = '$entrega_id'"; // Status 3 = Corrigida
        if (!$conn->query($sql_update_entrega)) {
            throw new Exception("Erro ao finalizar a correção.");
        }

        // Commit e Mensagem de Sucesso
        $conn->commit();
        $nota_atual = $nota_total_final; 
        $mensagem_status = "<p class='alert alert-success'>✅ Prova corrigida e nota final (".$nota_total_final."/" . $total_pontos_prova . ") atribuída com sucesso!</p>";
        $status_prova = 3; 
        
    } catch (Exception $e) {
        $conn->rollback();
        $mensagem_status = "<p class='alert alert-danger'>❌ Falha na correção: " . $e->getMessage() . "</p>";
    }
    // Reabre a conexão APENAS para buscar a informação atualizada (Se necessário, mas o script recarrega a página)
}

$conn->close(); 
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Corrigir Prova - <?php echo $aluno_nome; ?></title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/estilo_admin.css"> 
    <style>
        .questao-correcao { border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; border-radius: 6px; }
        .questao-header { background-color: #f5f5f5; padding: 10px; margin: -15px -15px 15px; border-bottom: 1px solid #eee; }
        .correta { background-color: #d4edda; }
        .errada { background-color: #f8d7da; }
        .gabarito-area { border-left: 3px solid #007bff; padding-left: 10px; margin-top: 10px; font-style: italic; }
        .aluno-resposta { margin-top: 10px; padding: 10px; border: 1px dashed #ccc; background-color: #fff; }
    </style>
</head>
<body>

<nav class="navbar navbar-inverse navbar-fixed-top navbar-top">
    <div class="container-fluid">
        <div class="navbar-header">
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
            <h1 class="page-header">📝 Correção: <?php echo $prova_titulo; ?></h1>
            <p><a href="correcao_provas.php">← Voltar para Pendentes</a></p>
            <hr>
            
            <?php echo $mensagem_status; ?>

            <div class="alert alert-info">
                **Aluno:** **<?php echo $aluno_nome; ?>** | 
                **Status:** <?php echo ($status_prova == 2) ? 'Aguardando Correção' : 'Corrigida'; ?> |
                **Pontuação Máxima:** <?php echo $total_pontos_prova; ?> pts
                
                <?php if ($status_prova == 3): ?>
                     | **Nota Final Publicada:** **<?php echo number_format($nota_atual, 2); ?>**
                <?php endif; ?>
            </div>

            <form action="detalhe_correcao.php?entrega_id=<?php echo $entrega_id; ?>" method="POST">
                <?php $q_num = 1; ?>
                <?php foreach ($respostas_a_corrigir as $r): 
                    $is_texto = ($r['tipo'] == 3);
                    $nota_obtida_exibicao = (float)$r['nota_obtida'];
                    $comentario = htmlspecialchars($r['comentario_professor'] ?? '');

                    // Lógica de CSS para feedback visual:
                    $css_class = '';
                    if (!$is_texto) { 
                        $pontuacao_max_q = (float)$r['pontuacao'];
                        if ($nota_obtida_exibicao == $pontuacao_max_q) {
                            $css_class = 'correta'; 
                        } elseif ($nota_obtida_exibicao < $pontuacao_max_q && $nota_obtida_exibicao >= 0) {
                             $css_class = 'errada'; 
                        }
                    }
                ?>
                <div class="questao-correcao <?php echo $css_class; ?>">
                    <div class="questao-header">
                        Questão #<?php echo $q_num++; ?> - Pontos: <?php echo $r['pontuacao']; ?>
                        (<?php echo $is_texto ? 'TEXTO - CORREÇÃO MANUAL' : 'MÚLTIPLA ESCOLHA - AUTO-CORRIGIDA'; ?>)
                    </div>
                    
                    <p><strong>Questão:</strong> <?php echo htmlspecialchars($r['texto_questao']); ?></p>
                    <?php if ($r['caminho_imagem']): ?>
                        <img src="../uploads/questoes/<?php echo htmlspecialchars($r['caminho_imagem']); ?>" style="max-width: 300px; margin-top: 10px;" class="img-responsive" alt="Imagem Questão">
                    <?php endif; ?>

                    <div class="aluno-resposta">
                        <strong>Resposta do Aluno:</strong> 
                        <?php if ($is_texto): ?>
                            <p><?php echo nl2br(htmlspecialchars($r['resposta_texto'] ?? 'Nenhuma resposta em texto.')); ?></p>
                        <?php else: 
                            // 🚀 CORREÇÃO DE EXIBIÇÃO: Exibir o texto das opções selecionadas
                            $opcoes_marcadas_ids = $r['opcoes_marcadas_ids'] ?? [];
                            if (!empty($opcoes_marcadas_ids)):
                                echo "<ul>";
                                foreach ($opcoes_marcadas_ids as $id) {
                                    $texto = $mapa_opcoes_texto[$id] ?? "ID $id (Texto não encontrado)";
                                    echo "<li>✅ " . $texto . "</li>";
                                }
                                echo "</ul>";
                            else:
                                echo "Nenhuma opção marcada.";
                            endif;
                        endif; ?>
                    </div>

                    <div class="gabarito-area">
                        <strong>Gabarito de Referência:</strong>
                        <?php 
                        if ($is_texto) {
                            echo nl2br(htmlspecialchars($r['gabarito_texto'] ?? ''));
                        } else {
                            // 🚀 CORREÇÃO DE EXIBIÇÃO: Exibir o texto das opções corretas
                            $gabarito_ids = $r['gabarito_ids'] ?? [];
                            if (!empty($gabarito_ids)) {
                                echo "<ul>";
                                foreach ($gabarito_ids as $id) {
                                    $texto = $mapa_opcoes_texto[$id] ?? "ID $id (Texto não encontrado)";
                                    echo "<li>🔑 " . $texto . "</li>";
                                }
                                echo "</ul>";
                            } else {
                                echo "Gabarito não configurado.";
                            }
                        }
                        ?>
                    </div>
                    
                    <hr>

                    <div class="row">
                        <div class="col-md-3">
                            <label>Nota Atribuída (Max: <?php echo $r['pontuacao']; ?>):</label>
                            <?php if ($is_texto): ?>
                                <input type="number" step="0.01" min="0" max="<?php echo $r['pontuacao']; ?>" 
                                        name="notas[<?php echo $r['questao_id']; ?>]" 
                                        value="<?php echo number_format($nota_obtida_exibicao, 2, '.', ''); ?>" 
                                        class="form-control" required>
                            <?php else: ?>
                                <input type="text" value="<?php echo number_format($nota_obtida_exibicao, 2); ?>" class="form-control" disabled>
                                <input type="hidden" name="notas[<?php echo $r['questao_id']; ?>]" value="<?php echo $nota_obtida_exibicao; ?>">
                            <?php endif; ?>
                        </div>
                        <div class="col-md-9">
                            <label>Comentário/Feedback (Opcional, mas recomendado):</label>
                            <textarea name="comentarios[<?php echo $r['questao_id']; ?>]" class="form-control" rows="2" 
                                        placeholder="Explique o erro do aluno e reforce a resposta correta."><?php echo $comentario; ?></textarea>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if ($status_prova == 2): // Exibe o botão apenas se estiver pendente ?>
                    <button type="submit" class="btn btn-success btn-lg pull-right" style="margin-bottom: 50px;">
                        <span class="glyphicon glyphicon-check"></span> Finalizar Correção e Publicar Nota
                    </button>
                <?php else: ?>
                    <p class="alert alert-warning">Esta prova já foi corrigida e a nota publicada.</p>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
</body>
</html>