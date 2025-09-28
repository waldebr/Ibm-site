<?php
session_start();
require_once 'config.php';

// 1. Proteção: Verifica se está logado E se é professor
if (!isset($_SESSION['id']) || $_SESSION['cargo'] !== 'professor') {
    header("Location: index.php");
    exit();
}

// 2. Verifica se o ID da atividade foi passado via GET e é numérico
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: professor_page.php?error=" . urlencode("ID de atividade inválido para exclusão."));
    exit();
}

$atividade_id = (int)$_GET['id'];
$professor_id = $_SESSION['id'];

// 3. Deleta a atividade (CRUCIAL: Garante que apenas o professor dono pode deletar)
$stmt = $conn->prepare("DELETE FROM atividades WHERE id_atividade = ? AND professor_id = ?");
$stmt->bind_param("ii", $atividade_id, $professor_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        // Sucesso: Atividade deletada
        header("Location: professor_page.php?success=" . urlencode("Atividade deletada com sucesso!"));
    } else {
        // Erro: ID existe mas o professor_id não corresponde (ou ID não existe)
        header("Location: professor_page.php?error=" . urlencode("Não foi possível deletar a atividade. Você é o criador?"));
    }
} else {
    // Erro de SQL
    header("Location: professor_page.php?error=" . urlencode("Erro ao executar a exclusão: " . $conn->error));
}

$stmt->close();
$conn->close();
exit();