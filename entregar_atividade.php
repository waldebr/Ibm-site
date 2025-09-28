<?php
// CRUCIAL: buffer de saída para evitar problemas de "Headers already sent"
ini_set('display_errors', 1);
error_reporting(E_ALL); // Você pode manter E_ALL aqui se não quiser silenciar warnings do PHP
ob_start();
session_start(); // <<< Deve estar antes do require_once 'config.php'
require_once 'config.php';

// Proteção: Verifica se está logado E se é aluno
if (!isset($_SESSION['id']) || $_SESSION['cargo'] !== 'aluno') {
    header("Location: index.php");
    exit();
}

$aluno_id = $_SESSION['id'];
$atividade_id = $_GET['id'] ?? null;
$error_message = '';
$success_message = '';
$atividade = null;
$entrega = null;
$url_redirecionamento = "user_page.php";


// --- LÓGICA DE TRATAMENTO DE MENSAGENS ---
if (isset($_GET['error'])) {
    $error_message = htmlspecialchars($_GET['error']);
}
if (isset($_GET['success'])) {
    $success_message = htmlspecialchars($_GET['success']);
}

// 2. Validar e Buscar a Atividade
if (empty($atividade_id) || !is_numeric($atividade_id)) {
    header("Location: {$url_redirecionamento}?error=" . urlencode("ID de atividade inválido."));
    exit();
}
$atividade_id = (int)$atividade_id;

$stmt_ativ = $conn->prepare("SELECT titulo, descricao, data_entrega, pont_max FROM atividades WHERE id_atividade = ?");
$stmt_ativ->bind_param("i", $atividade_id);
$stmt_ativ->execute();
$result_ativ = $stmt_ativ->get_result();

if ($result_ativ->num_rows === 0) {
    header("Location: {$url_redirecionamento}?error=" . urlencode("Atividade não encontrada."));
    exit();
}
$atividade = $result_ativ->fetch_assoc();

// 3. Buscar Entrega Existente (se houver)
$stmt_entrega = $conn->prepare("SELECT id_entrega, caminho_arquivo, nota, status FROM entregas WHERE atividades_id = ? AND aluno_id = ?");
$stmt_entrega->bind_param("ii", $atividade_id, $aluno_id);
$stmt_entrega->execute();
$result_entrega = $stmt_entrega->get_result();

if ($result_entrega->num_rows > 0) {
    $entrega = $result_entrega->fetch_assoc();
}

// 4. LÓGICA DE PROCESSAMENTO DE ENTREGA (COM UPLOAD DE ARQUIVO)
if (isset($_POST['fazer_entrega'])) {
    $caminho_arquivo = trim($_POST['caminho_arquivo'] ?? ''); // Texto/link da textarea
    $arquivo_upload = $_FILES['arquivo_entrega'] ?? null; // Arquivo enviado
    $caminho_final = $caminho_arquivo; // Valor padrão: o texto da textarea

    // Verifica se houve um upload de arquivo válido
    if ($arquivo_upload && $arquivo_upload['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/'; // O diretório onde os arquivos serão salvos (certifique-se que existe)
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_name = uniqid('entrega_') . '_' . basename($arquivo_upload['name']);
        $target_file = $upload_dir . $file_name;

        if (move_uploaded_file($arquivo_upload['tmp_name'], $target_file)) {
            // Se o upload for bem sucedido, o caminho final é o caminho do arquivo
            $caminho_final = $target_file; 
        } else {
            // Se falhar no upload
            header("Location: entregar_atividade.php?id={$atividade_id}&error=" . urlencode("Erro ao mover o arquivo de upload."));
            exit();
        }
    } 
    
    // Se não há arquivo de upload E o campo de texto/link está vazio, impede a entrega
    if (empty($caminho_final)) {
        header("Location: entregar_atividade.php?id={$atividade_id}&error=" . urlencode("A entrega deve conter um arquivo OU um link/texto."));
        exit();
    }


    $data_entrega = date('Y-m-d H:i:s');
    $status = 'Pendente'; // Novo status: Pendente de correção
    
    if ($entrega) {
        // --- UPDATE (Atualizar Entrega) ---
        $stmt_update = $conn->prepare("UPDATE entregas SET caminho_arquivo = ?, data_entrega = ?, status = ? WHERE id_entrega = ?");
        $stmt_update->bind_param("sssi", $caminho_final, $data_entrega, $status, $entrega['id_entrega']);

        if ($stmt_update->execute()) {
            header("Location: entregar_atividade.php?id={$atividade_id}&success=" . urlencode("Entrega atualizada com sucesso!"));
            exit();
        } else {
            $error_message = "Erro ao atualizar entrega: " . $stmt_update->error;
        }

    } else {
        // --- INSERT (Primeira Entrega) ---
        $stmt_insert = $conn->prepare("INSERT INTO entregas (atividades_id, aluno_id, caminho_arquivo, data_entrega, status) VALUES (?, ?, ?, ?, ?)");
        $stmt_insert->bind_param("iissi", $atividade_id, $aluno_id, $caminho_final, $data_entrega, $status);

        if ($stmt_insert->execute()) {
            header("Location: {$url_redirecionamento}?success=" . urlencode("Atividade entregue com sucesso!"));
            exit();
        } else {
            $error_message = "Erro ao entregar atividade: " . $stmt_insert->error;
        }
    }
}

// Variáveis para a visualização
$data_entrega_ativ = strtotime($atividade['data_entrega']);
$prazo_excedido = time() > $data_entrega_ativ;

// Lógica para determinar se o aluno pode editar
// Pode editar se: (já entregou E não foi corrigido E não excedeu o prazo) OU (nunca entregou E não excedeu o prazo)
$pode_editar = ($entrega && $entrega['status'] != 'Corrigido' && !$prazo_excedido) || (!$entrega && !$prazo_excedido); 

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entregar: <?= htmlspecialchars($atividade['titulo']); ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Estilos específicos */
        .dashboard-container { 
            width: 90%; 
            max-width: 600px; 
            background: white; 
            padding: 20px; 
            border-radius: 10px; 
            box-shadow: 0 0 10px rgba(0,0,0,0.1); 
            margin-top: 30px;
        }
        .msg-error { color: red; background: #ffe0e0; padding: 10px; border: 1px solid red; border-radius: 5px; margin-bottom: 20px; font-weight: bold; }
        .msg-success { color: green; background: #e0ffe0; padding: 10px; border: 1px solid green; border-radius: 5px; margin-bottom: 20px; font-weight: bold; }
        textarea {
            width: 100%;
            padding: 12px;
            background: #e4dada;
            border-radius: 6px;
            border: none;
            outline: none;
            font-size: 16px;
            color: #000000;
            margin-bottom: 20px;
            resize: vertical;
        }
        .disabled-form { opacity: 0.7; pointer-events: none; }
        .link-voltar {
            display: block;
            margin-top: 20px;
            color: #93221F;
            text-decoration: none;
            font-weight: bold;
        }
        .entrega-info {
            border-left: 5px solid #007bff;
            background: #f0f8ff;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
        }
        /* Estilo para mídias (imagem, video, audio) - Para visualização de entregas anteriores */
        .entrega-media {
            max-width: 100%; 
            height: auto; 
            border: 1px solid #ccc; 
            border-radius: 5px;
            display: block;
            margin: 10px 0;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="dashboard-container">
            <h2>Entregar Atividade: <?= htmlspecialchars($atividade['titulo']); ?></h2>
            <p>Pontuação Máxima: **<?= htmlspecialchars($atividade['pont_max']); ?>**</p>
            <p>Prazo Final: <?= date('d/m/Y', $data_entrega_ativ); ?></p>
            <p style="margin-top: 10px; border-top: 1px solid #ccc; padding-top: 10px;">
                Descrição: <?= nl2br(htmlspecialchars($atividade['descricao'])); ?>
            </p>
            
            <hr style="margin: 15px 0;">

            <?php if ($error_message): ?>
                <p class="msg-error">❌ ERRO: <?= htmlspecialchars($error_message); ?></p>
            <?php endif; ?>
            <?php if ($success_message): ?>
                <p class="msg-success">✅ SUCESSO: <?= htmlspecialchars($success_message); ?></p>
            <?php endif; ?>

            <?php if ($entrega): ?>
                <div class="entrega-info">
                    <p style="font-weight: bold; color: green; margin-bottom: 5px;">
                        <?= $entrega['status'] == 'Corrigido' ? '✔ ENTREGA CORRIGIDA' : '⏳ ENTREGA EXISTENTE (STATUS: ' . htmlspecialchars($entrega['status']) . ')'; ?>
                    </p>
                    <p>Conteúdo anterior: 
                        <?php 
                            // Lógica de exibição da entrega anterior (adaptação da lógica de ver_entregas.php)
                            $entrega_caminho = $entrega['caminho_arquivo'] ?? '';
                            $output_html_preview = '';

                            if (!empty($entrega_caminho)) {
                                $url_caminho = htmlspecialchars($entrega_caminho); 
                                $file_extension = strtolower(pathinfo(parse_url($entrega_caminho, PHP_URL_PATH), PATHINFO_EXTENSION));
                                
                                $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
                                $video_extensions = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv']; 
                                $audio_extensions = ['mp3', 'wav', 'ogg', 'aac']; 
                                $document_extensions = ['pdf', 'doc', 'docx', 'txt', 'zip', 'rar'];
                                
                                if (in_array($file_extension, $image_extensions)) {
                                    $output_html_preview = "<a href=\"{$url_caminho}\" target=\"_blank\">[Ver Imagem]</a>";
                                } elseif (in_array($file_extension, $video_extensions)) { 
                                    $output_html_preview = "<a href=\"{$url_caminho}\" target=\"_blank\">[Ver Vídeo]</a>";
                                } elseif (in_array($file_extension, $audio_extensions)) { 
                                    $output_html_preview = "<a href=\"{$url_caminho}\" target=\"_blank\">[Ouvir Áudio]</a>";
                                } elseif (in_array($file_extension, $document_extensions)) {
                                    $output_html_preview = "<a href=\"{$url_caminho}\" target=\"_blank\" class=\"link-download\">[Baixar/Ver Documento]</a>";
                                } elseif (filter_var($entrega_caminho, FILTER_VALIDATE_URL)) {
                                    $output_html_preview = "<a href=\"{$url_caminho}\" target=\"_blank\" style=\"word-break: break-all;\">[Ver Link Externo]</a>";
                                } else {
                                    $output_html_preview = "[Texto: " . htmlspecialchars(substr($entrega_caminho, 0, 50)) . "...]";
                                }
                            } else {
                                $output_html_preview = "[Nenhum conteúdo]";
                            }
                            echo $output_html_preview;
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <p style="font-weight: bold; margin-bottom: 15px;">Nova Entrega (Você pode enviar um arquivo OU um link/texto)</p>

            <form action="entregar_atividade.php?id=<?= $atividade_id; ?>" method="post" 
                class="<?= !$pode_editar ? 'disabled-form' : ''; ?>" **enctype="multipart/form-data"**>
                
                <input type="hidden" name="atividade_id" value="<?= htmlspecialchars($atividade['id_atividade']); ?>">
                
                <label for="arquivo_entrega" style="color:#000; display:block; margin-bottom: 5px; margin-top: 15px;">Opcional: Enviar Arquivo/Mídia (Imagem, Vídeo, Áudio, etc.):</label>
                <input type="file" name="arquivo_entrega" id="arquivo_entrega" <?= !$pode_editar ? 'disabled' : ''; ?> style="background: #fff; border: 1px solid #ccc;">

                <label for="caminho_arquivo" style="color:#000; display:block; margin-bottom: 5px; margin-top: 15px;">Cole aqui seu trabalho (Link do Drive, Texto, etc.):</label>
                <textarea name="caminho_arquivo" id="caminho_arquivo" rows="10" placeholder="Insira o link, texto ou conteúdo da sua entrega aqui..." 
                    <?= !$pode_editar ? 'disabled' : ''; ?> required><?= htmlspecialchars(strval($entrega['caminho_arquivo'] ?? '')); ?></textarea>
                
                <button type="submit" name="fazer_entrega" <?= !$pode_editar ? 'disabled' : ''; ?>>
                    <?= $entrega ? 'Atualizar Entrega' : 'Entregar Atividade'; ?>
                </button>
                
                <?php if (!$pode_editar && ($prazo_excedido || ($entrega && $entrega['status'] == 'Corrigido'))): ?>
                    <p style="color: red; margin-top: 10px;">Entrega Bloqueada. Motivo: Prazo expirado ou atividade já corrigida.</p>
                <?php endif; ?>

            </form>
            
            <a href="user_page.php" class="link-voltar">Voltar para Minhas Atividades</a>
        </div>
    </div>
</body>
</html>
<?php ob_end_flush(); ?>