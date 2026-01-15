<?php
include './conexão/conecta.php';

$mensagem = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. Coleta e sanitiza os dados do formulário
    $nome = $conn->real_escape_string($_POST['nome']);
    $email = $conn->real_escape_string($_POST['email']);
    $senha = $_POST['senha'];
    $codigo_professor = $conn->real_escape_string($_POST['codigo_professor']); // NOVO CAMPO
    
    // 2. Busca o ID do professor com base no código
    $sql_prof = "SELECT id FROM Usuario WHERE codigo_publico = '$codigo_professor' AND tipo_usuario = 1";
    $result_prof = $conn->query($sql_prof);

    if ($result_prof->num_rows == 0) {
        // Erro: O código do professor não existe ou não pertence a um professor
        $mensagem = "<h2 style='color: red;'>❌ Erro no Cadastro:</h2><p>Código do Professor inválido. Verifique se o código de 4 dígitos está correto.</p>";
    } else {
        // Professor encontrado, pega o ID para associação
        $prof_row = $result_prof->fetch_assoc();
        $professor_id = $prof_row['id'];
        
        // 3. Criptografa a senha antes de salvar
        $senha_hash = hash('sha256', $senha);
        $tipo_usuario = 2; // Aluno
        
        // 4. Instrução SQL para inserir o novo aluno, incluindo o professor_id
        $sql = "INSERT INTO Usuario (nome, email, senha, tipo_usuario, professor_id) 
                VALUES ('$nome', '$email', '$senha_hash', '$tipo_usuario', '$professor_id')";

        if ($conn->query($sql) === TRUE) {
            $mensagem = "<h2 style='color: green;'>✅ Cadastro BEM-SUCEDIDO!</h2><p>Você já pode fazer login e começar a estudar!</p>";
        } else {
            // Erro de duplicidade de email ou outro erro de DB
            $mensagem = "<h2 style='color: red;'>❌ Erro ao cadastrar:</h2><p>" . $conn->error . "</p>";
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Cadastro de Aluno</title>
    <link rel="stylesheet" href="./style/estilo_acesso.css"> 
    <style>
        /* Estilos específicos para o cadastro (é maior) */
        .container-acesso { max-width: 500px; }
    </style>
</head>
<body>

    <div class="container-acesso">

        <h1>Cadastro de Aluno</h1>
        <?php echo $mensagem; // Exibe a mensagem de sucesso ou erro ?>

        <form action="cadastro.php" method="POST">
            
            <div class="form-group">
                <label for="nome">Nome Completo:</label>
                <input type="text" id="nome" name="nome" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email (Será seu Login):</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="senha">Senha:</label>
                <input type="password" id="senha" name="senha" required>
            </div>

            <div class="form-group">
                <label for="codigo_professor">**Código do Professor (4 dígitos):**</label>
                <input type="text" id="codigo_professor" name="codigo_professor" maxlength="4" required>
            </div>
            
            <input type="submit" value="Cadastrar" class="btn-submit">
        </form>
        
        <div class="link-alternativo">
            <p>Já tem conta? <a href="login.php">Fazer Login</a></p>
        </div>
        
    </div>

</body>
</html>