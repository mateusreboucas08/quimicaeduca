<?php
session_start();
include '../conexão/conecta.php'; 

// GARANTIA DE ACESSO
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 2 || !isset($_GET['topico_id'])) {
    header("Location: ./login.php");
    exit();
}

$aluno_id = $_SESSION['user_id'];
$aluno_nome = $_SESSION['user_name'];
$topico_id = $conn->real_escape_string($_GET['topico_id']);
$mostrar_nao_feitas = isset($_GET['filtro']) && $_GET['filtro'] == 'nao_feitas';

// 1. Busca Título do Tópico
$sql_topico = "SELECT titulo FROM Topico WHERE id = '$topico_id'";
$topico_info = $conn->query($sql_topico)->fetch_assoc();
$titulo_topico = $topico_info ? htmlspecialchars($topico_info['titulo']) : "Tópico Desconhecido";

// 2. Busca todas as Entregas de Provas do aluno para o Tópico (para filtrar o que foi feito)
$concluidos = [];
$sql_concluidos = "
    SELECT 
        C.id AS conteudo_id
    FROM 
        Conteudo C
    JOIN 
        Prova P ON P.conteudo_id = C.id
    JOIN 
        EntregaProva EP ON EP.prova_id = P.id
    WHERE 
        C.topico_id = '$topico_id' AND EP.aluno_id = '$aluno_id' AND EP.status >= 2
    GROUP BY C.id
";
$result_concluidos = $conn->query($sql_concluidos);
while ($c = $result_concluidos->fetch_assoc()) {
    $concluidos[] = $c['conteudo_id'];
}

// 3. Busca todos os Conteúdos do Tópico, ordenados por tipo
$sql_conteudos = "
    SELECT 
        C.id, C.titulo, C.descricao, C.tipo, 
        P.id AS prova_id 
    FROM 
        Conteudo C
    LEFT JOIN 
        Prova P ON P.conteudo_id = C.id
    WHERE 
        C.topico_id = '$topico_id'
    ORDER BY 
        C.tipo ASC, C.id ASC
";
$conteudos_result = $conn->query($sql_conteudos);

// 4. Agrupa os Conteúdos por Tipo
$conteudos_agrupados = [];
while ($c = $conteudos_result->fetch_assoc()) {
    $c['realizada'] = in_array($c['id'], $concluidos);
    
    // APLICA O FILTRO CORRIGIDO: Tipo 2 é a Atividade/Prova Avaliativa.
    if ($mostrar_nao_feitas && $c['tipo'] == 2 && $c['realizada']) { 
        continue; 
    }
    
    $conteudos_agrupados[$c['tipo']][] = $c;
}

$conn->close();

// FUNÇÕES DE EXIBIÇÃO HARMONIZADAS
function getTipoTexto($tipo) {
    switch ($tipo) {
        case 1: return "Aulas/Textos de Estudo 📚"; // Novo tipo 1: Texto interno
        case 2: return "Atividades/Provas Avaliativas 🧠"; // Tipo 2 é o Quiz/Prova
        case 3: return "Materiais para Download/Resumos 📎";
        case 4: return "Links Externos/Vídeos 🔗";
        default: return "Outros";
    }
}

function getTipoIcone($tipo) {
    switch ($tipo) {
        case 1: return "glyphicon-book";
        case 2: return "glyphicon-pencil"; // Ícone de Lápis/Caneta para Prova
        case 3: return "glyphicon-download-alt";
        case 4: return "glyphicon-globe"; // Ícone de Link/Globo
        default: return "glyphicon-info-sign";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Conteúdo: <?php echo $titulo_topico; ?></title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/estilo_aluno.css"> 
    <style>
        .conteudo-bloco { margin-top: 40px; }
        .conteudo-bloco h2 { color: #333; border-bottom: 2px solid #ccc; padding-bottom: 10px; margin-bottom: 25px; }
        .conteudo-card { border-left: 5px solid #17a2b8; padding: 15px; margin-bottom: 15px; background-color: white; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .conteudo-card.realizada { border-left-color: #28a745; background-color: #e9f7ee; }
        .conteudo-card h4 { margin-top: 0; display: flex; justify-content: space-between; align-items: center; }
        .tag-status { font-size: 0.8em; margin-left: 10px; }
        /* Adicione ou garanta estes estilos em ../style/estilo_aluno.css para correção da sobreposição */
        body { padding-top: 70px; } 
        .navbar-aluno .navbar-brand, .navbar-aluno .navbar-nav > li > a { padding-top: 15px; padding-bottom: 15px; }
        .container-estudo .back-link { margin-top: 10px; margin-bottom: 20px; display: inline-block; }
    </style>
</head>
<body>

<nav class="navbar navbar-fixed-top navbar-aluno">
    <div class="container">
        <div class="navbar-header">
            <a class="navbar-brand" href="painel_aluno.php">Química Educa 🧪</a>
        </div>
        <ul class="nav navbar-nav navbar-right">
            <li><a href="#"><span class="glyphicon glyphicon-user"></span> Olá, <?php echo $aluno_nome; ?></a></li>
            <li><a href="../logout.php"><span class="glyphicon glyphicon-log-out"></span> Sair</a></li>
        </ul>
    </div>
</nav>

<div class="container container-estudo">
    <a href="painel_aluno.php" class="back-link"><span class="glyphicon glyphicon-chevron-left"></span> Voltar para a Biblioteca</a>
    
    <h1 class="page-header">Conteúdo: <?php echo $titulo_topico; ?></h1>

    <div class="well well-sm clearfix">
        <p class="pull-left" style="margin-top: 5px;">Selecione o material de estudo ou atividade:</p>
        
        <div class="pull-right">
            <?php 
            $url_sem_filtro = "visualizar_conteudo.php?topico_id=" . $topico_id;
            $url_nao_feitas = "visualizar_conteudo.php?topico_id=" . $topico_id . "&filtro=nao_feitas";
            ?>
            <a href="<?php echo $url_sem_filtro; ?>" class="btn btn-default <?php echo !$mostrar_nao_feitas ? 'active' : ''; ?>">
                Mostrar Todos
            </a>
            <a href="<?php echo $url_nao_feitas; ?>" class="btn btn-warning <?php echo $mostrar_nao_feitas ? 'active' : ''; ?>">
                Apenas Atividades Não Feitas
            </a>
        </div>
    </div>

    <?php if (empty($conteudos_agrupados)): ?>
        <p class="alert alert-info">Nenhum conteúdo encontrado neste tópico. O professor pode não ter liberado ainda.</p>
    <?php else: ?>

        <?php foreach ($conteudos_agrupados as $tipo => $lista_conteudos): ?>
            <div class="conteudo-bloco">
                <h2><span class="glyphicon <?php echo getTipoIcone($tipo); ?>"></span> <?php echo getTipoTexto($tipo); ?></h2>
                
                <?php foreach ($lista_conteudos as $c): ?>
                    <?php 
                    $card_class = $c['realizada'] ? 'realizada' : '';
                    $link_destino = '#'; // Padrão
                    $link_class = 'btn btn-xs btn-default';

                    // CORREÇÃO: Tipo 2 é a Atividade/Prova Avaliativa (Quiz)
                    if ($c['tipo'] == 2 && $c['prova_id']) { 
                        $link_class = $c['realizada'] ? 'btn btn-xs btn-success' : 'btn btn-xs btn-primary';
                        $link_texto = $c['realizada'] ? 'Ver Resultado' : 'Iniciar Prova';
                        // Futuramente, 'Ver Resultado' deve ir para o detalhe_boletim.php
                        $link_destino = $c['realizada'] ? 'boletim_aluno.php' : 'fazer_prova.php?conteudo_id=' . $c['id'];
                    } else { // Tipos 1, 3 e 4 (Conteúdos de estudo)
                        $link_class = 'btn btn-xs btn-info';
                        $link_texto = ($c['tipo'] == 1) ? 'Ler Aula' : 'Acessar Conteúdo';
                        $link_destino = 'acessar_conteudo.php?conteudo_id=' . $c['id'];
                    }
                    ?>

                    <div class="conteudo-card <?php echo $card_class; ?>">
                        <h4>
                            <?php echo htmlspecialchars($c['titulo']); ?>
                            <div style="font-weight: normal;">
                                <?php if ($c['realizada']): ?>
                                    <span class="tag-status label label-success">FEITA</span>
                                <?php endif; ?>
                                <a href="<?php echo $link_destino; ?>" class="<?php echo $link_class; ?>">
                                    <?php echo $link_texto; ?>
                                </a>
                            </div>
                        </h4>
                        <p><?php echo nl2br(htmlspecialchars($c['descricao'])); ?></p>
                    </div>
                <?php endforeach; ?>

            </div>
        <?php endforeach; ?>
        
    <?php endif; ?>

</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
</body>
</html>