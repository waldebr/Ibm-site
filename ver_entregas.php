<?php
// CRUCIAL: buffer de saída para evitar problemas de "Headers already sent"
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start();
session_start();
require_once 'config.php';

// 1. Proteção: Verifica se está logado E se é professor
if (!isset($_SESSION['id']) || $_SESSION['cargo'] !== 'professor') {
    header("Location: index.php");
    exit();
}

$professor_id = $_SESSION['id'];
$atividade_id = $_GET['id'] ?? null;
$error_message = '';
$success_message = '';
$atividade = null;
$entregas = [];

// --- LÓGICA DE TRATAMENTO DE MENSAGENS ---
if (isset($_GET['error'])) {
    $error_message = htmlspecialchars($_GET['error']);
}
if (isset($_GET['success'])) {
    $success_message = htmlspecialchars($_GET['success']);
}

// 2. Validação do ID
if (empty($atividade_id) || !is_numeric($atividade_id)) {
    header("Location: professor_page.php?error=" . urlencode("ID de atividade inválido."));
    exit();
}
$atividade_id = (int)$atividade_id;


// --- LÓGICA DE AVALIAÇÃO (NOTA) ---
if (isset($_POST['avaliar_entrega'])) {
    $entrega_id = $_POST['id_entrega'];
    $nota = $_POST['nota'];
    $observacao = $_POST['observacao_professor'];
    
    // Busca o pont_max para validar a nota
    $stmt_pont = $conn->prepare("
        SELECT a.pont_max FROM atividades a 
        INNER JOIN entregas e ON a.id_atividade = e.atividades_id 
        WHERE e.id_entrega = ?
    ");
    $stmt_pont->bind_param("i", $entrega_id);
    $stmt_pont->execute();
    $result_pont = $stmt_pont->get_result();
    $pont_max_avaliacao = $result_pont->fetch_assoc()['pont_max'] ?? 0;

    // Validação de nota
    if (!is_numeric($nota) || $nota < 0 || $nota > $pont_max_avaliacao) {
        header("Location: ver_entregas.php?id=" . $atividade_id . "&error=" . urlencode("Nota inválida. Insira um número entre 0 e {$pont_max_avaliacao}."));
        exit();
    } else {
        // ATUALIZA O STATUS PARA 'Corrigido'
        $status_corrigido = 'Corrigido'; 
        $stmt_grade = $conn->prepare("UPDATE entregas SET nota = ?, observacao_professor = ?, status = ? WHERE id_entrega = ?");
        $stmt_grade->bind_param("dssi", $nota, $observacao, $status_corrigido, $entrega_id); 

        if ($stmt_grade->execute()) {
            // Sucesso
            header("Location: ver_entregas.php?id=" . $atividade_id . "&success=" . urlencode("Entrega avaliada com sucesso!"));
            exit();
        } else {
            // Se falhar no banco
            header("Location: ver_entregas.php?id=" . $atividade_id . "&error=" . urlencode("Erro ao salvar a avaliação: " . $stmt_grade->error));
            exit();
        }
    }
}
// --- FIM LÓGICA DE AVALIAÇÃO ---


// 3. Busca a atividade e garante que pertence a este professor
$stmt_ativ = $conn->prepare("SELECT titulo, pont_max FROM atividades WHERE id_atividade = ? AND professor_id = ?");
$stmt_ativ->bind_param("ii", $atividade_id, $professor_id);
$stmt_ativ->execute();
$result_ativ = $stmt_ativ->get_result();

if ($result_ativ->num_rows === 0) {
    header("Location: professor_page.php?error=" . urlencode("Atividade não encontrada ou você não tem permissão para visualizá-la."));
    exit();
}

$atividade = $result_ativ->fetch_assoc();
$pont_max = $atividade['pont_max']; // Ponto máximo da atividade


// 4. Buscar as entregas para esta atividade
$sql_entregas = "SELECT 
    e.id_entrega, 
    e.data_entrega, 
    e.caminho_arquivo, 
    e.nota, 
    e.observacao_professor,
    e.status, 
    u.name AS nome_aluno, 
    a.matricula AS matricula_aluno 
FROM 
    entregas e
INNER JOIN 
    users u ON e.aluno_id = u.id
INNER JOIN 
    aluno a ON e.aluno_id = a.id_aluno
WHERE 
    e.atividades_id = ?
ORDER BY 
    e.data_entrega DESC";

$stmt_entregas = $conn->prepare($sql_entregas);
$stmt_entregas->bind_param("i", $atividade_id);
$stmt_entregas->execute();
$result_entregas = $stmt_entregas->get_result();

if ($result_entregas) {
    while ($row = $result_entregas->fetch_assoc()) {
        $entregas[] = $row;
    }
} else {
    $error_message = "Erro ao carregar entregas: " . $conn->error;
}


?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entregas: <?= htmlspecialchars($atividade['titulo']); ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Estilos específicos da página de visualização de entregas */
        .dashboard-container { 
            width: 90%; 
            max-width: 900px; 
            background: white; 
            padding: 20px; 
            border-radius: 10px; 
            box-shadow: 0 0 10px rgba(0,0,0,0.1); 
            margin-top: 30px;
        }
        .msg-error { color: red; background: #ffe0e0; padding: 10px; border: 1px solid red; border-radius: 5px; margin-bottom: 20px; font-weight: bold; }
        .msg-success { color: green; background: #e0ffe0; padding: 10px; border: 1px solid green; border-radius: 5px; margin-bottom: 20px; font-weight: bold; }
        .entrega-item {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 5px solid #93221F;
            border-radius: 5px;
            background-color: #fcfcfc;
        }
        .entrega-item strong { color: #93221F; display: block; margin-bottom: 5px;}
        .entrega-item h4 { margin-top: 20px; margin-bottom: 10px; color: #333; }
        .entrega-item input[type="number"], .entrega-item textarea {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .grade-btn {
            background-color: #28a745; /* Botão de salvar nota verde */
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            width: auto;
            display: block;
            margin-top: 10px;
        }
        .link-download {
            background-color: #007bff;
            color: white;
            padding: 5px 10px;
            border-radius: 3px;
            text-decoration: none;
            display: inline-block;
            margin-top: 5px;
        }
        .link-voltar {
            display: inline-block;
            margin-top: 20px;
            color: #93221F;
            text-decoration: none;
            font-weight: bold;
        }
        /* Estilo para mídias (imagem, video, audio) */
        .entrega-media {
            max-width: 100%; 
            height: auto; 
            border: 1px solid #ccc; 
            border-radius: 5px;
            display: block;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="dashboard-container">
            <h2>Entregas para: <?= htmlspecialchars($atividade['titulo']); ?></h2>
            <p>Pontuação Máxima: **<?= htmlspecialchars($pont_max); ?>**</p>
            
            <hr style="margin: 15px 0;">

            <?php if ($error_message): ?>
                <p class="msg-error">❌ ERRO: <?= htmlspecialchars($error_message); ?></p>
            <?php endif; ?>
            <?php if ($success_message): ?>
                <p class="msg-success">✅ SUCESSO: <?= htmlspecialchars($success_message); ?></p>
            <?php endif; ?>

            <h3>Total de Entregas: <?= count($entregas); ?></h3>

            <?php if (empty($entregas)): ?>
                <p>Nenhum aluno realizou a entrega desta atividade ainda.</p>
            <?php else: ?>
                <?php foreach ($entregas as $entrega): ?>
                    <?php 
                        $entrega_caminho = $entrega['caminho_arquivo'] ?? '';
                        $output_html = '';

                        if (!empty($entrega_caminho)) {
                            // Assumindo que o caminho é uma URL válida (uploads/arquivo.jpg ou link http)
                            $url_caminho = htmlspecialchars($entrega_caminho); 
                            
                            // 1. Lógica de detecção para exibição de IMAGENS/ARQUIVOS
                            $file_extension = strtolower(pathinfo(parse_url($entrega_caminho, PHP_URL_PATH), PATHINFO_EXTENSION));
                            
                            $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
                            $video_extensions = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv']; // EXTENSÕES DE VÍDEO
                            $audio_extensions = ['mp3', 'wav', 'ogg', 'aac']; // EXTENSÕES DE ÁUDIO
                            $document_extensions = ['pdf', 'doc', 'docx', 'txt', 'zip', 'rar'];
                            
                            if (in_array($file_extension, $image_extensions)) {
                                // É uma imagem: exibe a tag <img>
                                $output_html = "
                                    <p>Conteúdo da Entrega (Imagem):</p>
                                    <a href=\"{$url_caminho}\" target=\"_blank\">
                                        <img src=\"{$url_caminho}\" alt=\"Imagem da Entrega\" class=\"entrega-media\">
                                    </a>
                                ";
                            } elseif (in_array($file_extension, $video_extensions)) { 
                                // É um vídeo: exibe a tag <video>
                                $output_html = "
                                    <p>Conteúdo da Entrega (Vídeo):</p>
                                    <video controls class=\"entrega-media\" style=\"max-width: 100%; height: auto;\">
                                        <source src=\"{$url_caminho}\" type=\"video/{$file_extension}\">
                                        Seu navegador não suporta a tag de vídeo.
                                    </video>
                                    <a href=\"{$url_caminho}\" target=\"_blank\" class=\"link-download\">Baixar Vídeo (.{$file_extension})</a>
                                ";
                            } elseif (in_array($file_extension, $audio_extensions)) { 
                                // É um áudio: exibe a tag <audio>
                                $output_html = "
                                    <p>Conteúdo da Entrega (Áudio):</p>
                                    <audio controls class=\"entrega-media\" style=\"max-width: 100%;\">
                                        <source src=\"{$url_caminho}\" type=\"audio/{$file_extension}\">
                                        Seu navegador não suporta a tag de áudio.
                                    </audio>
                                    <a href=\"{$url_caminho}\" target=\"_blank\" class=\"link-download\">Baixar Áudio (.{$file_extension})</a>
                                ";
                            } elseif (in_array($file_extension, $document_extensions)) {
                                // É um documento: exibe link de download
                                $output_html = "
                                    <p>Documento de Entrega (Tipo: {$file_extension}):</p>
                                    <a href=\"{$url_caminho}\" target=\"_blank\" class=\"link-download\">Visualizar/Baixar Documento (.{$file_extension})</a>
                                ";
                            } elseif (filter_var($entrega_caminho, FILTER_VALIDATE_URL)) {
                                // É um link HTTP/URL externa
                                $output_html = "
                                    <p>Link de Entrega:</p>
                                    <div style=\"border: 1px dashed #ccc; padding: 10px; background-color: #f9f9f9; word-break: break-all; white-space: pre-wrap;\">
                                        <a href=\"{$url_caminho}\" target=\"_blank\" style=\"word-break: break-all;\">{$url_caminho}</a>
                                    </div>
                                ";
                            } else {
                                // Padrão: Texto puro ou caminho de arquivo não identificado
                                $output_html = "
                                    <p>Conteúdo da Entrega (Texto Puro/Caminho):</p>
                                    <div style=\"border: 1px dashed #ccc; padding: 10px; background-color: #f9f9f9; word-break: break-all; white-space: pre-wrap;\">
                                        " . nl2br(htmlspecialchars($entrega_caminho)) . "
                                    </div>
                                ";
                            }
                        } else {
                            $output_html = "<p style=\"color: gray;\">Nenhum conteúdo de entrega fornecido.</p>";
                        }
                    ?>
                    <div class="entrega-item">
                        <strong>Aluno: <?= htmlspecialchars($entrega['nome_aluno']); ?> (Matrícula: <?= htmlspecialchars($entrega['matricula_aluno']); ?>)</strong>
                        Data de Entrega: <?= date('d/m/Y H:i:s', strtotime($entrega['data_entrega'])); ?><br>
                        
                        <div style="margin-top: 10px; margin-bottom: 15px;">
                            <?= $output_html; ?>
                        </div>

                        
                        <hr style="margin: 15px 0;">
                        <h4>Avaliação (Nota Máxima: <?= htmlspecialchars($pont_max); ?>)</h4>

                        <form action="ver_entregas.php?id=<?= $atividade_id; ?>" method="POST">
                            <input type="hidden" name="id_entrega" value="<?= $entrega['id_entrega']; ?>">
                            
                            <label for="nota_<?= $entrega['id_entrega']; ?>" style="color:#000; display:block; margin-bottom: 5px;">Nota:</label>
                            <input type="number" step="0.01" name="nota" id="nota_<?= $entrega['id_entrega']; ?>" 
                                min="0" max="<?= htmlspecialchars($pont_max); ?>" 
                                value="<?= htmlspecialchars($entrega['nota'] ?? ''); ?>" required>
                            
                            <label for="obs_<?= $entrega['id_entrega']; ?>" style="color:#000; display:block; margin-bottom: 5px;">Observação/Feedback:</label>
                            <textarea name="observacao_professor" id="obs_<?= $entrega['id_entrega']; ?>" rows="4" 
                                placeholder="Insira seu feedback para o aluno..."><?= htmlspecialchars($entrega['observacao_professor'] ?? ''); ?></textarea>
                            
                            <button type="submit" name="avaliar_entrega" class="grade-btn">
                                <?= $entrega['nota'] !== null ? 'Atualizar Nota' : 'Atribuir Nota'; ?>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <a href="professor_page.php" class="link-voltar">Voltar para Minhas Atividades</a>
        </div>
    </div>
</body>
</html>
<?php ob_end_flush(); ?>