<?php
header("Content-Type: application/json");
$conn = new PDO("mysql:host=localhost;dbname=notes_db", "root", "root");

$id = $_GET['id'];
$content = $_POST['content'] ?? '';

try {
    $stmt = $conn->prepare("UPDATE notess SET content = ? WHERE id = ?");
    $stmt->execute([$content, $id]);
    echo json_encode(['success' => true]);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}