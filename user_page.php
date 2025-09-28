<?php
session_start();
require_once 'config.php';

// Proteção: Verifica se está logado E se é aluno
if (!isset($_SESSION['id']) || $_SESSION['cargo'] !== 'aluno') {
    header("Location: index.php");
    exit();
}

$aluno_id = $_SESSION['id'];
$atividades = [];
$error_message = '';

// --- LÓGICA DE LISTAGEM DE ATIVIDADES PARA O ALUNO ---
$sql = "SELECT 
            a.id_atividade, 
            a.titulo, 
            a.descricao, 
            a.data_entrega, 
            a.pont_max, 
            u.name AS professor_name
        FROM 
            atividades a
        INNER JOIN 
            users u ON a.professor_id = u.id 
        ORDER BY 
            a.data_entrega ASC";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $atividades[] = $row;
    }
} else {
    // Exibe o erro de banco de dados se a query falhar
    $error_message = "Erro ao carregar atividades: " . $conn->error;
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel do Aluno</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .dashboard-container { max-width: 900px; margin: 50px auto; padding: 20px; background: #fff; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .atividade-list { list-style: none; padding: 0;}
        .atividade-list li { background: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 10px; border-left: 5px solid #225793; }
        .msg-error { color: red; margin-bottom: 15px; font-weight: bold; }
        .atividade-list button { background-color: #225793; border: none; color: white; padding: 8px 15px; border-radius: 5px; cursor: pointer; margin-top: 10px;}
    </style>
</head>
<body>
    <div class="dashboard-container">
        <h2>Bem-vindo, Aluno(a) <?= htmlspecialchars($_SESSION['name']); ?>!</h2>
        <p>Cargo: <?= htmlspecialchars($_SESSION['cargo']); ?> | <a href="logout.php">Sair</a></p>
        
        <hr>

        <?php if ($error_message): ?>
            <p class="msg-error">❌ ERRO SQL: <?= $error_message; ?></p>
        <?php endif; ?>

        <h3>Atividades Disponíveis (<?= count($atividades); ?>)</h3>
        <?php if (empty($atividades)): ?>
            <p>Não há nenhuma atividade disponível no momento.</p>
        <?php else: ?>
            <ul class="atividade-list">
                <?php foreach ($atividades as $ativ): ?>
                    <li>
                        <strong><?= htmlspecialchars($ativ['titulo']); ?></strong> (<?= htmlspecialchars($ativ['pont_max']); ?> Pontos)<br>
                        <small>Professor: <?= htmlspecialchars($ativ['professor_name']); ?></small><br>
                        Descrição: <?= nl2br(htmlspecialchars($ativ['descricao'])); ?><br>
                        **Prazo Final: <?= date('d/m/Y', strtotime($ativ['data_entrega'])); ?>**
                        <button>Entregar</button>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</body>
</html>