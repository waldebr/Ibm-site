<?php
// CRUCIAL: Adicione ob_start() para evitar problemas de "Headers already sent"
// que causam redirecionamento quebrado (tela branca).
ob_start();
session_start();
require_once 'config.php';


// Proteção: Verifica se está logado E se é professor
if (!isset($_SESSION['id']) || $_SESSION['cargo'] !== 'professor') {
    header("Location: index.php");
    exit();
}

$professor_id = $_SESSION['id'];
$success_message = '';
$error_message = '';

// --- BLOCO CRÍTICO PARA FOREIGN KEY ---
// Isso garante que todo professor logado exista na tabela 'professor'
$stmt_check = $conn->prepare("SELECT id_professor FROM professor WHERE id_professor = ?");
$stmt_check->bind_param("i", $professor_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows === 0) {
    // Insere se não existir
    $materia_padrao = 'Geral';
    $stmt_insert = $conn->prepare("INSERT INTO professor (id_professor, materia) VALUES (?, ?)");
    $stmt_insert->bind_param("is", $professor_id, $materia_padrao);
    
    if (!$stmt_insert->execute()) {
        // Se houver um erro, exibe e interrompe o script
        die("Erro FATAL ao linkar o professor com a tabela auxiliar: " . $stmt_insert->error);
    }
}
// --- FIM BLOCO CRÍTICO ---


// --- LÓGICA PARA EXIBIÇÃO DE MENSAGENS DE SUCESSO/ERRO (Via GET) ---
if (isset($_GET['error'])) {
    $error_message = htmlspecialchars($_GET['error']);
}
if (isset($_GET['success'])) {
    $success_message = htmlspecialchars($_GET['success']);
}


// --- LÓGICA DE INSERÇÃO DE NOVA ATIVIDADE (POST) ---
if (isset($_POST['criar_atividade'])) {
    $titulo = $_POST['titulo'];
    $descricao = $_POST['descricao'];
    $data_entrega = $_POST['data_entrega'];
    $pont_max = $_POST['pont_max'];

    // Captura a data/hora atual
    $data_criacao = date('Y-m-d H:i:s');

    if (empty($titulo) || empty($descricao) || empty($data_entrega) || empty($pont_max)) {
        $error_message = 'Todos os campos são obrigatórios.';
    } else {
        $stmt = $conn->prepare("INSERT INTO atividades (professor_id, titulo, descricao, data_criacao, data_entrega, pont_max) VALUES (?, ?, ?, ?, ?, ?)");
        // 'issssi' para professor_id, titulo, descricao, data_criacao, data_entrega, pont_max
        $stmt->bind_param("issssi", $professor_id, $titulo, $descricao, $data_criacao, $data_entrega, $pont_max);

        if ($stmt->execute()) {
            // Redireciona com mensagem de sucesso
            header("Location: professor_page.php?success=" . urlencode("Atividade '$titulo' criada com sucesso!"));
            exit();
        } else {
            $error_message = "Erro ao criar atividade: " . $stmt->error;
        }
    }
}


// --- LÓGICA DE LISTAGEM DE ATIVIDADES CRIADAS ---
$atividades = [];
$stmt_list = $conn->prepare("SELECT id_atividade, titulo, descricao, data_criacao, data_entrega, pont_max FROM atividades WHERE professor_id = ? ORDER BY data_entrega DESC");
$stmt_list->bind_param("i", $professor_id);
$stmt_list->execute();
$result_list = $stmt_list->get_result();

if ($result_list) {
    while ($row = $result_list->fetch_assoc()) {
        $atividades[] = $row;
    }
} else {
    $error_message = "Erro ao carregar atividades: " . $conn->error;
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel do Professor</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Estilos específicos para o dashboard do professor */
        body {
            /* Remove o centralizador da tela de login e ajusta para dashboard */
            display: block; 
            min-height: 100vh;
            background: linear-gradient(to right,#93221F,#2D0A09);
            padding: 20px;
        }
        .dashboard-container {
            max-width: 900px;
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.2);
        }
        .msg-success { color: green; font-weight: bold; margin-bottom: 15px; border-left: 5px solid green; padding: 10px; background-color: #e6ffe6; }
        .msg-error { color: red; font-weight: bold; margin-bottom: 15px; border-left: 5px solid red; padding: 10px; background-color: #ffe6e6; }
        .create-atividade-form, .atividade-list {
            padding: 15px;
            border: 1px solid #ccc;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .atividade-list {
            list-style: none;
            padding: 0;
        }
        .atividade-list li {
            border: 1px solid #eee;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        .atividade-actions a {
            margin-right: 10px;
            padding: 5px 10px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
            display: inline-block;
            margin-top: 10px;
            color: white; /* Cor do texto dos botões */
        }
        .edit-btn { background-color: #225793; } /* Azul escuro */
        .delete-btn { background-color: #93221F; } /* Vermelho escuro */
        .view-btn { background-color: #333; } /* Cinza escuro */
        
        /* Ajuste de largura para inputs/textarea dentro do formulário */
        .create-atividade-form input[type="text"],
        .create-atividade-form input[type="date"],
        .create-atividade-form input[type="number"],
        .create-atividade-form textarea {
            width: 100%;
            margin-bottom: 15px;
        }
        .create-atividade-form button[type="submit"] {
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <h2>Painel do Professor: <?= htmlspecialchars($_SESSION['name']); ?></h2>
        <p>Cargo: <?= htmlspecialchars($_SESSION['cargo']); ?> | <a href="logout.php">Sair</a></p>

        <hr style="margin: 15px 0;">

        <?php if ($success_message): ?>
            <p class="msg-success">✅ <?= $success_message; ?></p>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <p class="msg-error">❌ <?= $error_message; ?></p>
        <?php endif; ?>

        <div class="create-atividade-form">
            <h3>Criar Nova Atividade</h3>
            <form action="professor_page.php" method="post">
                <input type="text" name="titulo" placeholder="Título da Atividade" required>
                <textarea name="descricao" placeholder="Descrição Detalhada" rows="4" required></textarea>
                <label style="display:block; margin-bottom: 5px; color: #333;">Data de Entrega:</label>
                <input type="date" name="data_entrega" required>
                <input type="number" name="pont_max" placeholder="Pontuação Máxima" required>
                <button type="submit" name="criar_atividade">Criar Atividade</button>
            </form>
        </div>

        <h3>Minhas Atividades Criadas - <?= count($atividades); ?></h3>
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
                        <div class="atividade-actions">
                            <a href="ver_entregas.php?id=<?= $ativ['id_atividade']; ?>" class="view-btn">Ver Entregas</a>
                            <a href="edit_atividade.php?id=<?= $ativ['id_atividade']; ?>" class="edit-btn">Editar</a>
                            <a href="delete_atividade.php?id=<?= $ativ['id_atividade']; ?>" class="delete-btn" onclick="return confirm('Tem certeza que deseja deletar a atividade \'<?= htmlspecialchars($ativ['titulo']); ?>\'?');">Excluir</a>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</body>
</html>
<?php ob_end_flush(); ?>