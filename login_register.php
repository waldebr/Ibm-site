<?php
    session_start();
    require_once 'config.php';

    // Função auxiliar para retornar à página inicial
    function redirect_to_index($activeForm, $errorMessage = '') {
        $_SESSION['active_form'] = $activeForm;
        if (!empty($errorMessage)) {
            $_SESSION[$activeForm . '_error'] = $errorMessage;
        }
        header("Location: index.php");
        exit();
    }


if (isset($_POST['register'])) {
    
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $cargo = 'aluno'; // Cargo é SEMPRE 'aluno' para novos registros

    // 1. Verifica se o email já existe
    $stmt = $conn->prepare("SELECT email FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $checkEmail = $stmt->get_result();

    if ($checkEmail->num_rows > 0) {
        redirect_to_index('register', 'Email já registrado.');
    }

    // --- INÍCIO DA TRANSAÇÃO: Garante a Dupla Inserção (users + aluno) ---
    $conn->begin_transaction();
    
    try {
        // 2. Insere na tabela USERS
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, cargo) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $email, $password, $cargo);
        
        if (!$stmt->execute()) {
            throw new Exception("Falha ao inserir em users.");
        }
        
        // 3. Obtém o ID do usuário recém-criado (CRUCIAL!)
        $user_id = $conn->insert_id;

        // 4. Insere na tabela ALUNO
        // Valores automáticos/padrão para Matrícula e Turma (NOT NULL no DB)
        $matricula = 'ALU-' . str_pad($user_id, 5, '0', STR_PAD_LEFT);
        $turma = 'A1'; 

        $stmt_aluno = $conn->prepare("INSERT INTO aluno (id_aluno, matricula, turma) VALUES (?, ?, ?)");
        $stmt_aluno->bind_param("iss", $user_id, $matricula, $turma);
        
        if (!$stmt_aluno->execute()) {
             throw new Exception("Falha ao inserir em aluno. Verifique as colunas.");
        }

        // 5. Se tudo deu certo, confirma a transação
        $conn->commit();
        redirect_to_index('login', 'Registro de aluno efetuado com sucesso!');

    } catch (Exception $e) {
        // Se algo falhou, desfaz todas as alterações
        $conn->rollback();
        redirect_to_index('register', 'Erro ao registrar: ' . $e->getMessage()); 
    }
    // --- FIM DA TRANSAÇÃO ---
}


if (isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, name, email, password, cargo FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if(password_verify($password, $user['password'])) {
            // Salva todas as informações essenciais na sessão
            $_SESSION['id'] = $user['id']; 
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['cargo'] = $user['cargo'];

            // AQUI ESTÁ A DIFERENÇA DE LOGIN: Redireciona com base no CARGO
            if ($user['cargo'] == 'professor') {
                header("Location: professor_page.php");
                exit();
            } else { // 'aluno'
                header("Location: user_page.php");
                exit();
            }
        }
    }
    
    redirect_to_index('login', 'Email ou senha incorreta.');
}
?>