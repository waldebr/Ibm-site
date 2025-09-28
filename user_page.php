<?php
// CRUCIAL: buffer de saída para evitar erros de redirecionamento
ob_start();
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
$success_message = '';

// --- LÓGICA DE TRATAMENTO DE MENSAGENS ---
if (isset($_GET['error'])) {
    $error_message = htmlspecialchars($_GET['error']);
}
if (isset($_GET['success'])) {
    $success_message = htmlspecialchars($_GET['success']);
}

// --- LÓGICA DE LISTAGEM DE ATIVIDADES PARA O ALUNO ---
// Seleciona todas as atividades, o nome do professor E INCLUI DADOS DA ENTREGA/NOTA
$sql = "SELECT 
            a.id_atividade, 
            a.titulo, 
            a.descricao, 
            a.data_entrega, 
            a.pont_max, 
            u.name AS professor_name,
            e.id_entrega IS NOT NULL AS ja_entregue,
            e.nota, /* NOVA COLUNA: NOTA */
            e.status, /* NOVA COLUNA: STATUS */
            e.observacao_professor /* NOVA COLUNA: OBSERVAÇÃO/FEEDBACK */
        FROM 
            atividades a
        INNER JOIN 
            users u ON a.professor_id = u.id 
        LEFT JOIN
            entregas e ON a.id_atividade = e.atividades_id AND e.aluno_id = ? 
        ORDER BY 
            a.data_entrega ASC";

// Usando prepared statement para a query de listagem
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $aluno_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result) {
    while ($row = $result->fetch_assoc()) {
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
    <title>Dashboard do Aluno</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .dashboard-container { 
            width: 90%; 
            max-width: 800px; 
            background: white; 
            padding: 20px; 
            border-radius: 10px; 
            box-shadow: 0 0 10px rgba(0,0,0,0.1); 
            margin-top: 30px;
        }
        .dashboard-container h2 { margin-bottom: 25px; }
        .dashboard-container h3 { margin-top: 20px; color: #93221F; }
        .atividade-list { list-style: none; padding: 0; }
        .atividade-list li {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 5px solid #93221F;
            border-radius: 5px;
            background-color: #fcfcfc;
        }
        .atividade-list strong { display: block; margin-bottom: 5px;}
        .msg-error { color: red; background: #ffe0e0; padding: 10px; border: 1px solid red; border-radius: 5px; margin-bottom: 20px; font-weight: bold; }
        .msg-success { color: green; background: #e0ffe0; padding: 10px; border: 1px solid green; border-radius: 5px; margin-bottom: 20px; font-weight: bold; }
        .nota-box { 
            padding: 8px; 
            border-radius: 4px; 
            font-weight: bold; 
            display: inline-block;
            margin-top: 8px;
        }
        .nota-corrigida { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;}
        .nota-pendente { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba;}
        .btn-entregar {
            background-color: #28a745; 
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            width: auto;
            margin-top: 10px;
        }
        .btn-ver-entrega {
            background-color: #007bff; 
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            width: auto;
            margin-top: 10px;
            text-decoration: none;
            display: inline-block;
        }
        .link-logout {
            display: inline-block;
            margin-top: 20px;
            color: #93221F;
            text-decoration: none;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="dashboard-container">
            <h2>Bem-vindo, <?= htmlspecialchars($_SESSION['name']); ?> (Aluno)</h2>
            <a href="logout.php" class="link-logout">Sair</a>
            
            <hr style="margin: 15px 0;">

            <?php if ($error_message): ?>
                <p class="msg-error">❌ ERRO: <?= htmlspecialchars($error_message); ?></p>
            <?php endif; ?>
            <?php if ($success_message): ?>
                <p class="msg-success">✅ SUCESSO: <?= htmlspecialchars($success_message); ?></p>
            <?php endif; ?>

            <h3>Minhas Atividades</h3>
            
            <?php if (empty($atividades)): ?>
                <p>Nenhuma atividade encontrada.</p>
            <?php else: ?>
                <ul class="atividade-list">
                    <?php foreach ($atividades as $ativ): ?>
                        <?php $prazo_expirado = strtotime($ativ['data_entrega']) < time(); ?>
                        
                        <li>
                            <strong><?= htmlspecialchars($ativ['titulo']); ?></strong> (<?= htmlspecialchars($ativ['pont_max']); ?> Pontos)<br>
                            Professor: <?= htmlspecialchars($ativ['professor_name']); ?><br>
                            Descrição: <?= nl2br(htmlspecialchars($ativ['descricao'])); ?><br>
                            **Prazo Final: <?= date('d/m/Y', strtotime($ativ['data_entrega'])); ?>**
                            
                            <div style="margin-top: 15px;">
                                <?php if ($ativ['ja_entregue']): ?>
                                    
                                    <?php if ($ativ['status'] == 'Corrigido' && $ativ['nota'] !== null): ?>
                                        <p class="nota-box nota-corrigida">
                                            ✅ Corrigido: **<?= htmlspecialchars($ativ['nota']); ?> / <?= htmlspecialchars($ativ['pont_max']); ?>**
                                        </p>
                                    <?php else: ?>
                                        <p class="nota-box nota-pendente">
                                            ⏳ Entregue (Aguardando Correção)
                                        </p>
                                    <?php endif; ?>
                                    
                                    <a href="entregar_atividade.php?id=<?= $ativ['id_atividade']; ?>" class="btn-ver-entrega">Ver/Editar Entrega</a>
                                    
                                <?php elseif ($prazo_expirado): ?>
                                    <p style="color: red; font-weight: bold;">❌ Prazo de entrega expirado!</p>
                                    
                                <?php else: ?>
                                    <a href="entregar_atividade.php?id=<?= $ativ['id_atividade']; ?>" class="btn-ver-entrega">Entregar Atividade</a>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php ob_end_flush(); ?>