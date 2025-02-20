<?php
header("Content-Type: application/json");
require 'db_connect.php';

$data = json_decode(file_get_contents('php://input'), true);

try {
    $stmt = $conn->prepare("INSERT INTO notess (content) VALUES (?)");
    $stmt->execute([$data['content']]);
    echo json_encode(['success' => true]);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}