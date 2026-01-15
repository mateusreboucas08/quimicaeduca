<?php
session_start();
include '../conexão/conecta.php';

// GARANTIA DE ACESSO: SÓ PROFESSOR (tipo 1) PODE ACESSAR
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 1 || !isset($_GET['aluno_id'])) {
    header("Location: ./login.php");
    exit();
}

$aluno_id = $conn->real_escape_string($_GET['aluno_id']);
$professor_id = $_SESSION['user_id'];

// 1. Busca o nome do aluno e verifica se ele pertence ao professor logado
$sql_aluno = "SELECT nome FROM Usuario WHERE id = '$aluno_id' AND professor_id = '$professor_id' AND tipo_usuario = 2";
$aluno_result = $conn->query($sql_aluno);

if ($aluno_result->num_rows == 0) {
    die("Acesso negado ou aluno não encontrado.");
}
$aluno_nome = $aluno_result->fetch_assoc()['nome'];

// 2. Busca o detalhe do progresso: Conteúdo, Tópico e Status de Conclusão
$sql_detalhe = "
    SELECT 
        C.titulo AS conteudo_titulo,
        T.titulo AS topico_titulo,
        PA.completo AS concluido,
        PA.data_conclusao
    FROM 
        Conteudo C
    JOIN 
        Topico T ON C.topico_id = T.id
    LEFT JOIN 
        ProgressoAluno PA ON C.id = PA.conteudo_id AND PA.aluno_id = '$aluno_id'
    ORDER BY 
        T.titulo, C.titulo;
";
$detalhe_result = $conn->query($sql_detalhe);

$conn->close();

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Detalhe Progresso: <?php echo htmlspecialchars($aluno_nome); ?></title>
    <style>
        body { font-family: sans-serif; padding: 20px; }
        h1 { color: #007bff; }
        .tabela-detalhe { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .tabela-detalhe th, .tabela-detalhe td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        .tabela-detalhe th { background-color: #5bc0de; color: white; }
        .concluido { background-color: #d4edda; color: #155724; }
        .pendente { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>

    <h1>Detalhes do Progresso: <?php echo htmlspecialchars($aluno_nome); ?></h1>
    <p><a href="progresso_alunos.php">← Voltar para a Lista de Alunos</a></p>
    <hr>

    <?php if ($detalhe_result && $detalhe_result->num_rows > 0): ?>
        <table class="tabela-detalhe">
            <thead>
                <tr>
                    <th>Tópico</th>
                    <th>Conteúdo</th>
                    <th>Status</th>
                    <th>Data de Conclusão</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($detalhe = $detalhe_result->fetch_assoc()): ?>
                <tr class="<?php echo $detalhe['concluido'] ? 'concluido' : 'pendente'; ?>">
                    <td><?php echo htmlspecialchars($detalhe['topico_titulo']); ?></td>
                    <td><?php echo htmlspecialchars($detalhe['conteudo_titulo']); ?></td>
                    <td>
                        <?php echo $detalhe['concluido'] ? '✅ CONCLUÍDO' : '❌ PENDENTE'; ?>
                    </td>
                    <td>
                        <?php 
                            if ($detalhe['concluido'] && $detalhe['data_conclusao']) {
                                echo date('d/m/Y H:i', strtotime($detalhe['data_conclusao']));
                            } else {
                                echo '-';
                            }
                        ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Nenhum conteúdo publicado para ser rastreado.</p>
    <?php endif; ?>

</body>
</html>