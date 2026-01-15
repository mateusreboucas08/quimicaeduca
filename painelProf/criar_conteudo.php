<?php
session_start();
include '../conexão/conecta.php';

// GARANTIA DE ACESSO: SÓ PROFESSOR (tipo 1) PODE ACESSAR
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 1) {
    header("Location: ./login.php");
    exit();
}
$nome_professor = $_SESSION['user_name'];
$mensagem = "";

// Buscando os tópicos existentes para o select box
$topicos_sql = "SELECT id, titulo FROM Topico ORDER BY titulo ASC";
$topicos_result = $conn->query($topicos_sql);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Coleta e sanitiza os dados (INCLUINDO DESCRICAO e TEXTO_AULA)
    $topico_id = $conn->real_escape_string($_POST['topico_id']);
    $titulo = $conn->real_escape_string($_POST['titulo']);
    $descricao = $conn->real_escape_string($_POST['descricao']);
    $texto_aula = $conn->real_escape_string($_POST['texto_aula'] ?? ''); // NOVO CAMPO
    $tipo = $conn->real_escape_string($_POST['tipo']);
    $link_arquivo = $conn->real_escape_string($_POST['link_arquivo']); 
    
    // 2. Insere o novo Conteúdo na tabela
    $sql_insert = "INSERT INTO Conteudo (topico_id, titulo, descricao, texto_aula, tipo, link_arquivo) 
                    VALUES ('$topico_id', '$titulo', '$descricao', '$texto_aula', '$tipo', '$link_arquivo')";

    if ($conn->query($sql_insert) === TRUE) {
        $novo_conteudo_id = $conn->insert_id;
        
        // 3. SE FOR QUIZ/ATIVIDADE (tipo 2): REDIRECIONA PARA CRIAR AS QUESTÕES
        if ($tipo == 2) {
            $sql_prova = "INSERT INTO Prova (conteudo_id, titulo) VALUES ('$novo_conteudo_id', '$titulo')";
            
            if ($conn->query($sql_prova) === TRUE) {
                $nova_prova_id = $conn->insert_id;
                header("Location: form_questoes.php?prova_id=" . $nova_prova_id . "&titulo=" . urlencode($titulo));
                exit();
            } else {
                $mensagem = "<p class='alert alert-danger'>❌ Erro ao criar registro da Prova: " . $conn->error . "</p>";
            }
        
        } else {
            $mensagem = "<p class='alert alert-success'>✅ Conteúdo **" . htmlspecialchars($titulo) . "** adicionado com sucesso!</p>";
        }

    } else {
        $mensagem = "<p class='alert alert-danger'>❌ Erro ao adicionar conteúdo: " . $conn->error . "</p>";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Criar Conteúdo - Professor</title>
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
                <li class="active"><a href="criar_conteudo.php"><span class="glyphicon glyphicon-pencil">&nbsp;</span>Criar Conteúdo</a></li>
                <li><a href="correcao_provas.php"><span class="glyphicon glyphicon-check">&nbsp;</span>Corrigir Provas</a></li>
                <li><a href="progresso_alunos.php"><span class="glyphicon glyphicon-stats">&nbsp;</span>Acompanhar Progresso</a></li>
                <li><a href="relatorio_dificuldade.php"><span class="glyphicon glyphicon-list">&nbsp;</span>Régua de Dificuldade</a></li>
            </ul>
        </div>

        <div class="col-sm-9 col-sm-offset-3 col-md-10 col-md-offset-2 main-content">
            <h1 class="page-header">📝 Criar Novo Conteúdo de Química</h1>
            
            <ul class="breadcrumb">
                <li><span class="glyphicon glyphicon-home">&nbsp;</span>Home</li>
                <li><a href="painel_professor.php">Dashboard</a></li>
                <li>Criar Conteúdo</li>
            </ul>
            
            <?php echo $mensagem; // Exibe a mensagem de status ?>

            <div class="card-info">
                <form action="criar_conteudo.php" method="POST">
                    
                    <div class="form-group">
                        <label for="topico_id">Tópico/Unidade:</label>
                        <select id="topico_id" name="topico_id" class="form-control" required>
                            <option value="">-- Selecione o Tópico --</option>
                            <?php 
                            if ($topicos_result && $topicos_result->num_rows > 0) {
                                while($row = $topicos_result->fetch_assoc()) {
                                    echo "<option value='{$row['id']}'>" . htmlspecialchars($row['titulo']) . "</option>";
                                }
                            } else {
                                echo "<option value=''>**Nenhum Tópico Encontrado! Crie um primeiro no phpMyAdmin.**</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="titulo">Título Curto do Material (Ex: Aula 1.1 - Átomos):</label>
                        <input type="text" id="titulo" name="titulo" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="descricao">Descrição Detalhada/Resumo:</label>
                        <textarea id="descricao" name="descricao" class="form-control" rows="3" placeholder="Insira uma breve descrição ou resumo do material para o aluno visualizar na biblioteca." required></textarea>
                    </div>

                    <div class="form-group">
                        <label for="tipo">Tipo de Material:</label>
                        <select id="tipo" name="tipo" class="form-control" required>
                            <option value="1">1 - Aula/Texto (CONTEÚDO DIRETO)</option>
                            <option value="2">2 - Atividade/Quiz</option>
                            <option value="3">3 - Resumo/PDF (LINK)</option>
                            <option value="4">4 - Link Externo/Vídeo (LINK)</option>
                        </select>
                    </div>

                    <div class="form-group" id="aula-group" style="display: none;">
                        <label for="texto_aula">Conteúdo Completo da Aula/Resumo:</label>
                        <textarea id="texto_aula" name="texto_aula" class="form-control" rows="15" placeholder="Digite ou cole o texto completo da aula aqui."></textarea>
                    </div>
                    
                    <div class="form-group" id="link-group">
                        <label for="link_arquivo">Link/Caminho do Arquivo (URL do YouTube, link para PDF, etc.):</label>
                        <input type="text" id="link_arquivo" name="link_arquivo" class="form-control" required>
                    </div>

                    <button type="submit" class="btn btn-primary"><span class="glyphicon glyphicon-upload"></span> Publicar Conteúdo</button>
                </form>
            </div>

        </div>
    </div>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const tipoSelect = document.getElementById('tipo');
        const aulaGroup = document.getElementById('aula-group');
        const aulaInput = document.getElementById('texto_aula');
        const linkGroup = document.getElementById('link-group');
        const linkInput = document.getElementById('link_arquivo');

        function toggleContentInputs() {
            const tipo = tipoSelect.value;
            
            // Lógica para AULA/TEXTO (TIPO 1)
            if (tipo == 1) {
                // Ativa a área de texto longo
                aulaGroup.style.display = 'block';
                aulaInput.setAttribute('required', 'required');
                
                // Desativa o link
                linkGroup.style.display = 'none';
                linkInput.removeAttribute('required');
                linkInput.value = 'N/A';
            } 
            // Lógica para QUIZ (TIPO 2)
            else if (tipo == 2) {
                // Desativa a área de texto longo
                aulaGroup.style.display = 'none';
                aulaInput.removeAttribute('required');
                
                // Desativa o link
                linkGroup.style.display = 'none';
                linkInput.removeAttribute('required');
                linkInput.value = 'N/A'; 
            } 
            // Lógica para LINKS/PDF (TIPO 3 e 4)
            else { 
                // Desativa a área de texto longo
                aulaGroup.style.display = 'none';
                aulaInput.removeAttribute('required');
                
                // Ativa o link
                linkGroup.style.display = 'block';
                linkInput.setAttribute('required', 'required');
                linkInput.value = '';
            }
        }

        tipoSelect.addEventListener('change', toggleContentInputs);
        toggleContentInputs(); // Executa ao carregar a página
    });
</script>
</body>
</html>