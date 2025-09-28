<?php
// ATENÇÃO: As duas primeiras linhas são CRUCIAIS para o redirecionamento funcionar (resolver o problema de deslogamento)
ob_start(); 
session_start();
require_once 'config.php';

// Proteção: Verifica se está logado E se é professor
if (!isset($_SESSION['id']) || $_SESSION['cargo'] !== 'professor') {
    header("Location: index.php");
    exit();
}

$professor_id = $_SESSION['id'];
$error_message = '';

// ------------------------------------------------------------------
// CRÍTICO: CHECAGEM E INSERÇÃO AUTOMÁTICA NA TABELA `professor`
// Isso garante que a Foreign Key sempre será válida, resolvendo o último erro.
// ------------------------------------------------------------------

$stmt_check = $conn->prepare("SELECT id_professor FROM professor WHERE id_professor = ?");
$stmt_check->bind_param("i", $professor_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows === 0) {
    // Se o professor não estiver na tabela auxiliar `professor`, insere.
    $materia_padrao = 'Geral'; 
    $stmt_insert = $conn->prepare("INSERT INTO professor (id_professor, materia) VALUES (?, ?)");
    $stmt_insert->bind_param("is", $professor_id, $materia_padrao);
    
    if (!$stmt_insert->execute()) {
        die("Erro FATAL ao linkar o professor com a tabela auxiliar: " . $stmt_insert->error);
    }
}
// ------------------------------------------------------------------

// Implementação do PRG: Recupera mensagem de sucesso da sessão
$success_message = $_SESSION['professor_message'] ?? '';
unset($_SESSION['professor_message']); 


// --- LÓGICA DE INSERÇÃO DE NOVA ATIVIDADE ---
if (isset($_POST['criar_atividade'])) {
    $titulo = $_POST['titulo'];
    $descricao = $_POST['descricao'];
    $data_entrega = $_POST['data_entrega'];
    $pont_max = $_POST['pont_max']; 
    $data_criacao = date('Y-m-d H:i:s'); 

    if (empty($titulo) || empty($descricao) || empty($data_entrega) || empty($pont_max)) {
        $error_message = 'Todos os campos são obrigatórios.';
    } else {
        $stmt = $conn->prepare("INSERT INTO atividades (professor_id, titulo, descricao, data_criacao, data_entrega, pont_max) VALUES (?, ?, ?, ?, ?, ?)");
        
        if ($stmt === false) {
             $error_message = 'Erro na PREPARAÇÃO da query: ' . $conn->error;
        } else {
            // Parâmetros: i (int), s, s, s, s, i (integer/decimal)
            $stmt->bind_param("issssi", $professor_id, $titulo, $descricao, $data_criacao, $data_entrega, $pont_max);

            if ($stmt->execute()) {
                // SUCESSO E REDIRECIONAMENTO (PRG)
                $_SESSION['professor_message'] = 'Atividade **' . htmlspecialchars($titulo) . '** criada com sucesso!';
                header("Location: professor_page.php"); 
                exit();
            } else {
                $error_message = 'Erro ao criar atividade (EXECUÇÃO): ' . $stmt->error; 
            }
        }
    }
}

// --- LÓGICA DE LISTAGEM DE ATIVIDADES DO PRÓPRIO PROFESSOR ---
$atividades = [];
$stmt = $conn->prepare("SELECT id_atividade, titulo, data_criacao, data_entrega, pont_max, descricao FROM atividades WHERE professor_id = ? ORDER BY data_entrega ASC");
$stmt->bind_param("i", $professor_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $atividades[] = $row;
}

// Libera o buffer de saída. CRUCIAL!
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel do Professor</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .dashboard-container { max-width: 900px; margin: 50px auto; padding: 20px; background: #fff; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .atividade-form { margin-bottom: 40px; border-bottom: 2px solid #ccc; padding-bottom: 20px;}
        .atividade-list { list-style: none; padding: 0;}
        .atividade-list li { background: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 10px; border-left: 5px solid #93221F; }
        .msg-success { color: green; margin-bottom: 15px; font-weight: bold; }
        .msg-error { color: red; margin-bottom: 15px; font-weight: bold; }
        .atividade-form input[type="text"], .atividade-form input[type="number"], .atividade-form input[type="date"], .atividade-form textarea {
            width: 100%; padding: 12px; margin-bottom: 20px; border-radius: 6px; border: 1px solid #ccc;
        }
        .atividade-form button { width: 100%; background-color: #93221F; border: none; color: white; cursor: pointer; padding: 12px; border-radius: 6px;}
    </style>
</head>
<body>
    <div class="dashboard-container">
        <h2>Bem-vindo, Professor <?= htmlspecialchars($_SESSION['name']); ?>!</h2>
        <p>Cargo: <?= htmlspecialchars($_SESSION['cargo']); ?> | <a href="logout.php">Sair</a></p>
        
        <hr>

        <?php if ($success_message): ?>
            <p class="msg-success">✅ <?= $success_message; ?></p>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <p class="msg-error">❌ ERRO: <?= $error_message; ?></p>
        <?php endif; ?>

        <div class="atividade-form">
            <h3>Criar Nova Atividade</h3>
            <form action="" method="post">
                <input type="text" name="titulo" placeholder="Título da Atividade" required>
                <textarea name="descricao" placeholder="Descrição Detalhada" rows="4" required></textarea>
                <label>Data de Entrega:</label>
                <input type="date" name="data_entrega" required>
                <input type="number" name="pont_max" placeholder="Pontuação Máxima" required>
                <button type="submit" name="criar_atividade">Criar Atividade</button>
            </form>
        </div>

        <h3>Minhas Atividades Criadas (<?= count($atividades); ?>)</h3>
        <?php if (empty($atividades)): ?>
            <p>Você ainda não criou nenhuma atividade.</p>
        <?php else: ?>
            <ul class="atividade-list">
                <?php foreach ($atividades as $ativ): ?>
                    <li>
                        <strong><?= htmlspecialchars($ativ['titulo']); ?></strong> (<?= htmlspecialchars($ativ['pont_max']); ?> Pontos)<br>
                        Descrição: <?= nl2br(htmlspecialchars($ativ['descricao'])); ?><br>
                        Criada em: <?= date('d/m/Y H:i', strtotime($ativ['data_criacao'])); ?><br>
                        **Entrega: <?= date('d/m/Y', strtotime($ativ['data_entrega'])); ?>**
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</body>
</html>