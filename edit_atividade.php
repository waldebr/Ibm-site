<?php
// CRUCIAL: buffer de saída para evitar erros de redirecionamento
ob_start(); 
session_start();
require_once 'config.php';

// 1. Proteção: Verifica se está logado E se é professor
if (!isset($_SESSION['id']) || $_SESSION['cargo'] !== 'professor') {
    header("Location: index.php");
    exit();
}

$professor_id = $_SESSION['id'];
$error_message = '';
$atividade = null;

// --- LÓGICA DE ATUALIZAÇÃO (POST) ---
if (isset($_POST['atualizar_atividade'])) {
    $atividade_id = $_POST['id_atividade'];
    $titulo = $_POST['titulo'];
    $descricao = $_POST['descricao'];
    $data_entrega = $_POST['data_entrega'];
    $pont_max = $_POST['pont_max']; 
    
    // Validação
    if (empty($titulo) || empty($descricao) || empty($data_entrega) || empty($pont_max) || empty($atividade_id)) {
        $error_message = 'Todos os campos são obrigatórios.';
    } else {
        // Executa o UPDATE (CRUCIAL: Garante que apenas o professor dono pode atualizar)
        $stmt = $conn->prepare("UPDATE atividades SET titulo = ?, descricao = ?, data_entrega = ?, pont_max = ? WHERE id_atividade = ? AND professor_id = ?");
        // ATENÇÃO: 's' para titulo, descricao, data_entrega. 'i' para pont_max, id_atividade, professor_id
        $stmt->bind_param("sssiii", $titulo, $descricao, $data_entrega, $pont_max, $atividade_id, $professor_id);

        if ($stmt->execute()) {
            // Sucesso: Redireciona
            header("Location: professor_page.php?success=" . urlencode("Atividade atualizada com sucesso!"));
            exit();
        } else {
            $error_message = "Erro ao atualizar: " . $stmt->error;
        }
        $stmt->close();
    }
}

// --- LÓGICA DE CARREGAMENTO DO FORMULÁRIO (GET) ---
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: professor_page.php?error=" . urlencode("ID de atividade inválido para edição."));
    exit();
}

$atividade_id = (int)$_GET['id'];

// Busca a atividade (CRUCIAL: Garante que apenas o professor dono veja)
$stmt = $conn->prepare("SELECT id_atividade, titulo, descricao, data_entrega, pont_max FROM atividades WHERE id_atividade = ? AND professor_id = ?");
$stmt->bind_param("ii", $atividade_id, $professor_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: professor_page.php?error=" . urlencode("Atividade não encontrada ou você não tem permissão para editar."));
    exit();
}

$atividade = $result->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Atividade</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Estilos adicionais para centralizar e dar espaçamento se o style.css não for suficiente */
        body {
            /* Remove a centralização se ela estiver no body do style.css e você não quiser */
            /* display: block !important; */
            /* min-height: initial !important; */
            padding: 20px;
        }
        .container {
            max-width: 600px; /* Limita a largura do formulário */
            margin-top: 50px;
            padding: 30px;
        }
        /* Garantindo que o form-box fique visível, já que o index.php usa .active */
        .form-box {
            display: block; /* Garante que o box do formulário seja exibido */
            max-width: 100%; /* Adapta ao container */
        }
        .link-voltar {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #93221F;
            text-decoration: none;
            font-weight: bold;
        }
        .msg-error {
            color: #93221F;
            font-weight: bold;
            text-align: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-box active" id="editar-atividade-form">
            <h2>Editar Atividade</h2>
            
            <?php if ($error_message): ?>
                <p class="msg-error">❌ <?= htmlspecialchars($error_message); ?></p>
            <?php endif; ?>

            <form action="edit_atividade.php" method="post">
                <input type="hidden" name="id_atividade" value="<?= htmlspecialchars($atividade['id_atividade'] ?? ''); ?>">
                
                <label for="titulo" style="color:#000; display:block; margin-bottom: 5px;">Título da Atividade:</label>
                <input type="text" name="titulo" id="titulo" placeholder="Título da Atividade" 
                    value="<?= htmlspecialchars($atividade['titulo'] ?? ''); ?>" required>
                
                <label for="descricao" style="color:#000; display:block; margin-bottom: 5px;">Descrição Detalhada:</label>
                <textarea name="descricao" id="descricao" placeholder="Descrição Detalhada" rows="6" required><?= htmlspecialchars($atividade['descricao'] ?? ''); ?></textarea>
                
                <label for="data_entrega" style="color:#000; display:block; margin-bottom: 5px;">Data de Entrega:</label>
                <input type="date" name="data_entrega" id="data_entrega" 
                    value="<?= htmlspecialchars($atividade['data_entrega'] ?? ''); ?>" required>
                
                <label for="pont_max" style="color:#000; display:block; margin-bottom: 5px;">Pontuação Máxima:</label>
                <input type="number" name="pont_max" id="pont_max" placeholder="Pontuação Máxima" 
                    value="<?= htmlspecialchars($atividade['pont_max'] ?? ''); ?>" required>
                
                <button type="submit" name="atualizar_atividade">Atualizar Atividade</button>
            </form>
            <a href="professor_page.php" class="link-voltar">Voltar para Minhas Atividades</a>
        </div>
    </div>
</body>
</html>
<?php ob_end_flush(); ?>