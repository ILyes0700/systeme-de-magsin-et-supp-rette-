<?php
require_once(__DIR__ . '/vendor/autoload.php'); // Inclure le fichier d'autoload de Composer

$host   = 'localhost';
$dbname = 'produits_db';
$user   = 'root';
$pass   = 'root';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connexion échouée : " . $e->getMessage());
}

// Vérifier si l'ID de la carte est présent
if (!isset($_GET['card_id'])) {
    die("ID de la carte manquant !");
}

$card_id = intval($_GET['card_id']);

// Récupérer les informations de la carte
$stmt = $pdo->prepare("SELECT * FROM cards WHERE id = :id");
$stmt->execute(['id' => $card_id]);
$card = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$card) {
    die("Carte introuvable !");
}

// Récupérer les articles de la carte
$stmt = $pdo->prepare("SELECT * FROM items WHERE card_id = :card_id");
$stmt->execute(['card_id' => $card_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Créer une nouvelle instance TCPDF
$pdf = new TCPDF();
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('9a4ya');
$pdf->SetTitle($card['title']);
$pdf->SetMargins(10, 10, 10);
$pdf->AddPage();

// Ajouter le titre
$pdf->SetFont('helvetica', 'B', 20);
$pdf->SetTextColor(0, 51, 102); // Couleur bleu
$pdf->Cell(0, 10, "" . $card['title'], 0, 1, 'C');

// Ajouter un espace
$pdf->Ln(5);

// Ajouter le tableau des articles
$pdf->SetFont('helvetica', '', 12);
$html = '<table border="1" cellpadding="5" style="border-collapse: collapse;">
            <tr style="background-color:#0044cc; color: white; font-weight: bold;">
                <th>Nom</th>
                <th>Quantité</th>
                <th>Prix Unitaire</th>
                <th>Total</th>
            </tr>';

$totalAmount = 0;
foreach ($items as $item) {
    $html .= '<tr>
                <td>' . htmlspecialchars($item['text']) . '</td>
                <td>' . $item['quantity'] . '</td>
                <td>' . number_format($item['unit_price'], 2) . '</td>
                <td>' . number_format($item['price_total'], 2) . '</td>
              </tr>';
    $totalAmount += $item['price_total'];
}

$html .= '</table>';

// Ajouter le total en rouge
$html .= '<br><br><table>';
$html .= '<tr><td style="text-align: right; font-weight: bold;">Total:</td>';
$html .= '<td style="color: red; font-weight: bold;">' . number_format($totalAmount, 2) . '  TND</td></tr>';
$html .= '</table>';

$pdf->writeHTML($html, true, false, true, false, '');

// Générer et envoyer le fichier PDF au navigateur
$pdf->Output('Carte_' . $card['title'] . '.pdf', 'D');
?>
