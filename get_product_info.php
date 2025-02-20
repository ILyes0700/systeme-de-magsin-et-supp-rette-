<?php
// Configuration de la base de données
$servername = "localhost";
$username = "root";
$password = "root";
$dbname = "produits_db";

// Créer la connexion
$conn = new mysqli($servername, $username, $password, $dbname);

// Vérifier la connexion
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id = $_GET['id'];

    // Récupérer les informations du produit basé sur le code QR (id)
    $sql = "SELECT * FROM produits WHERE codeqr = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Récupérer les données du produit
        $product = $result->fetch_assoc();
        echo json_encode($product);
    } else {
        echo json_encode(['error' => 'Produit non trouvé']);
    }

    $stmt->close();
} else {
    echo json_encode(['error' => 'ID du produit manquant ou invalide']);
}

$conn->close();
?>
