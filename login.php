<?php
// Inicia a sessão. Essencial para armazenar o estado do usuário após o login.
session_start();

// Inclui o script de conexão com o banco de dados.
include 'conexão/conecta.php';

// Verifica se a requisição foi feita via método POST (envio do formulário).
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. Obtém e sanitiza os dados do formulário
    // real_escape_string previne ataques básicos de SQL Injection.
    $email = $conn->real_escape_string($_POST['email']);
    $senha = $conn->real_escape_string($_POST['senha']);
    
    // 2. Consulta SQL para buscar o usuário pelo email
    $sql = "SELECT id, nome, senha, tipo_usuario FROM Usuario WHERE email = '$email'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $usuario = $result->fetch_assoc();
        
        // 3. VERIFICAÇÃO SEGURA DE SENHA
        // Compara a senha digitada (criptografada em tempo real) com o hash armazenado.
        if (hash('sha256', $senha) === $usuario['senha']) { 
            
            // Login BEM-SUCEDIDO!

            // 4. Configura as variáveis de SESSÃO
            // A sessão "lembra" o usuário enquanto ele navega.
            $_SESSION['user_id'] = $usuario['id'];
            $_SESSION['user_name'] = $usuario['nome'];
            $_SESSION['user_type'] = $usuario['tipo_usuario']; // 1=Professor, 2=Aluno
            
            // 5. REDIRECIONAMENTO baseado no tipo de usuário
            if ($usuario['tipo_usuario'] == 1) {
                // Professor: Redireciona para o painel de administração
                header("Location: painelProf/painel_professor.php"); 
            } else {
                // Aluno: Redireciona para a área de estudos
                header("Location: painelAluno/painel_aluno.php"); 
            }
            exit(); // Encerra o script após o redirecionamento.
            
        } else {
            // Senha incorreta
            $mensagem_erro = "Senha incorreta.";
        }
    } else {
        // Usuário não encontrado
        $mensagem_erro = "Usuário com este email não encontrado.";
    }
}

// Fecha a conexão com o banco de dados APÓS a conclusão de todas as operações.
$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Login - Química Educa</title>
    <link rel="stylesheet" href="./style/estilo_acesso.css"> 
</head>
<body>

    <div class="container-acesso">

        <h1>Acesso à Plataforma</h1>
        
        <?php if (isset($mensagem_erro)): ?>
            <p class="error">❌ Erro: <?php echo htmlspecialchars($mensagem_erro); ?></p>
        <?php endif; ?>

        <form action="login.php" method="POST">
            
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="senha">Senha:</label>
                <input type="password" id="senha" name="senha" required>
            </div>
            
            <input type="submit" value="Fazer Login" class="btn-submit">
        </form>
        
        <div class="link-alternativo">
            <p>Não tem conta? <a href="cadastro.php">Cadastre-se como Aluno</a></p>
        </div>
        
    </div>

</body>
</html>