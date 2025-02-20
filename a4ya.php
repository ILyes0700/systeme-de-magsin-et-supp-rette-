<?php
// ---------------------------
// CONNEXION À LA BASE DE DONNÉES
// ---------------------------
$host   = 'localhost';
$dbname = 'produits_db';
$user   = 'root';
$pass   = 'root';
// Inclure PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';  // Autoload de Composer pour PHPMaile
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connexion à la base de données échouée : " . $e->getMessage());
}

// Envoi d'email avec la carte
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_email') {
    $card_id = intval($_POST['card_id']);
    $recipient_email = filter_var($_POST['recipient_email'], FILTER_SANITIZE_EMAIL);

    // Récupérer les données de la carte
    $stmt = $pdo->prepare("SELECT * FROM cards WHERE id = :id");
    $stmt->execute(['id' => $card_id]);
    $card = $stmt->fetch(PDO::FETCH_ASSOC);

    // Récupérer les items de la carte
    $stmt = $pdo->prepare("SELECT * FROM items WHERE card_id = :card_id");
    $stmt->execute(['card_id' => $card_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Construire le contenu HTML de l'email
    $emailContent = "<h4 style='color: #4CAF50;'>bonjour, ".htmlspecialchars($card['title'])."</h4>";
$emailContent .= "<table style='width: 100%; border-collapse: collapse; margin-top: 20px;'>";
$emailContent .= "<thead style='background-color: #f5f5f5;'>";
$emailContent .= "<tr><th style='padding: 10px; text-align: left; border-bottom: 2px solid #ddd;'>Produit</th><th style='padding: 10px; text-align: left; border-bottom: 2px solid #ddd;'>Quantité</th><th style='padding: 10px; text-align: left; border-bottom: 2px solid #ddd;'>Prix unitaire</th><th style='padding: 10px; text-align: left; border-bottom: 2px solid #ddd;'>Total</th></tr>";
$emailContent .= "</thead>";
$emailContent .= "<tbody>";

foreach ($items as $item) {
    $emailContent .= sprintf(
        "<tr>
            <td style='padding: 10px; border-bottom: 1px solid #f5f5f5;'>%s</td>
            <td style='padding: 10px; border-bottom: 1px solid #f5f5f5;'>%d</td>
            <td style='padding: 10px; border-bottom: 1px solid #f5f5f5;'>%.2f د.ت</td>
            <td style='padding: 10px; border-bottom: 1px solid #f5f5f5;'>%.2f د.ت</td>
        </tr>",
        htmlspecialchars($item['text']),
        $item['quantity'],
        $item['unit_price'],
        $item['price_total']
    );
}

$emailContent .= "</tbody>";
$emailContent .= "</table>";

$total = array_sum(array_column($items, 'price_total'));
$emailContent .= "<h3 style='margin-top: 20px; color: #333;'>Total : <span style='color: #e74c3c;'>".number_format($total, 2)."د.ت</span></h3>";

// Configuration de PHPMailer
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'pharfind@gmail.com';
    $mail->Password = 'rfqdlvatmnuklgtb';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom('pharfind@gmail.com', 'Hamrouni');
    $mail->addAddress($recipient_email);
    $mail->isHTML(true);
    $mail->Subject = 'Détails de la card : '.$card['title'];
    $mail->Body = "
        <html>
        <head>
            <style>
                body { font-family: 'Arial', sans-serif; background-color: #f9f9f9; color: #333; margin: 0; padding: 20px; }
                .container { background-color: #ffffff; padding: 20px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); border-radius: 8px; }
                h2, h3 { font-weight: normal; }
                table { width: 100%; margin-top: 20px; }
                th, td { text-align: left; padding: 10px; border-bottom: 1px solid #f5f5f5; }
                th { background-color: #f5f5f5; }
                .total { color: #e74c3c; font-size: 1.2em; }
                p { font-size: 1em; color: #777; }
            </style>
        </head>
        <body>
            <div class='container'>
                $emailContent
                <center><p style='color: #4CAF50;'>Cordialement<br>L'équipe Hamrouni</p></center>
            </div>
        </body>
        </html>
    ";

    $mail->send();
    header("Location: " . $_SERVER['REQUEST_URI']);
} catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
}

    exit;
}

// -------------------------------
// TRAITEMENT DES ACTIONS (GET)
// -------------------------------

// Suppression d'un article
if (isset($_GET['delete_item'])) {
    $item_id = intval($_GET['delete_item']);
    $stmt = $pdo->prepare("DELETE FROM items WHERE id = :id");
    $stmt->execute(['id' => $item_id]);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Modification d'un article (texte, quantité et prix)
if (
    isset($_GET['edit_item']) &&
    isset($_GET['new_text']) &&
    isset($_GET['new_quantity']) &&
    isset($_GET['new_price'])
) {
    $item_id      = intval($_GET['edit_item']);
    $new_text     = trim($_GET['new_text']);
    $new_quantity = intval($_GET['new_quantity']);
    $new_price    = floatval($_GET['new_price']);

    if ($new_text !== '' && $new_quantity > 0 && $new_price > 0) {
        $new_total = $new_quantity * $new_price;
        $stmt = $pdo->prepare("UPDATE items 
                               SET text = :text, quantity = :quantity, unit_price = :price, price_total = :price_total 
                               WHERE id = :id");
        $stmt->execute([
            'text'        => $new_text,
            'quantity'    => $new_quantity,
            'price'       => $new_price,
            'price_total' => $new_total,
            'id'          => $item_id
        ]);
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Suppression d'une carte et de ses items associés
if (isset($_GET['delete_card'])) {
    $card_id = intval($_GET['delete_card']);
    // Supprimer les items liés à cette carte
    $stmt = $pdo->prepare("DELETE FROM items WHERE card_id = :card_id");
    $stmt->execute(['card_id' => $card_id]);
    // Supprimer la carte
    $stmt = $pdo->prepare("DELETE FROM cards WHERE id = :id");
    $stmt->execute(['id' => $card_id]);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// -----------------------------
// TRAITEMENT DES FORMULAIRES (POST)
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Ajout d'une nouvelle carte
        if ($_POST['action'] === 'add_card') {
            $card_title = trim($_POST['card_title']);
            if (!empty($card_title)) {
                $stmt = $pdo->prepare("INSERT INTO cards (title, created_at) VALUES (:title, NOW())");
                $stmt->execute(['title' => $card_title]);
            }
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
        // Ajout d'un item
        elseif ($_POST['action'] === 'add_item') {
            $card_id   = intval($_POST['card_id']);
            $quantity  = intval($_POST['quantity']);
            $price     = floatval($_POST['price']);
            $item_text = trim($_POST['item_text']);
            $total_price = $quantity * $price;

            if ($card_id && $quantity && $price && !empty($item_text)) {
                $stmt = $pdo->prepare("INSERT INTO items (card_id, quantity, unit_price, price_total, text, created_at) 
                                     VALUES (:card_id, :quantity, :unit_price, :price_total, :text, NOW())");
                $stmt->execute([
                    'card_id'     => $card_id,
                    'quantity'    => $quantity,
                    'unit_price'  => $price,
                    'price_total' => $total_price,
                    'text'        => $item_text
                ]);
            }
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}

// ----------------------------------
// RÉCUPÉRATION DES DONNÉES
// ----------------------------------
$stmt  = $pdo->query("SELECT * FROM cards ORDER BY created_at DESC");
$cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cards_items = [];
$cards_totals = [];
foreach ($cards as $card) {
    $stmt2 = $pdo->prepare("SELECT *, (quantity * unit_price) as price_total FROM items WHERE card_id = :card_id ORDER BY created_at ASC");
    $stmt2->execute(['card_id' => $card['id']]);
    $cards_items[$card['id']] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcul du total de la carte
    $total = 0;
    foreach ($cards_items[$card['id']] as $item) {
        $total += $item['price_total'];
    }
    $cards_totals[$card['id']] = $total;
}

// -----------------------------
// GROUPER LES CARTES PAR LA PREMIÈRE LETTRE DU TITRE
// -----------------------------
$grouped_cards = [];
foreach ($cards as $card) {
    $firstLetter = strtoupper(substr($card['title'], 0, 1));
    if (!isset($grouped_cards[$firstLetter])) {
        $grouped_cards[$firstLetter] = [];
    }
    $grouped_cards[$firstLetter][] = $card;
}
ksort($grouped_cards);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des Stocks</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Importation de Font Awesome et Google Fonts -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --secondary: #4f46e5;
            --accent: #f43f5e;
            --success: #10b981;
            --sidebar-bg: rgba(255, 255, 255, 0.95);
    --sidebar-border: rgba(209, 213, 219, 0.3);
    --sidebar-icon: #4f46e5;
    --sidebar-icon-hover: #6366f1;
            --background: #f8fafc;
            --text: #1e293b;
            --card-bg: #ffffff;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --gradient: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        }

        * {
            box-sizing: border-box;
            margin:0;
            padding: 5px;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        body {
            background: var(--background);
            color: var(--text);
            min-height: 100vh;
            padding-right: 80px; /* Adjusted for right sidebar */
        }

        /* Nouveau design de la sidebar */
        .sidebar {
    position: fixed;
    right: 20px;
    top: 50%;
    transform: translateY(-50%);
    width: 90px;
    background: var(--sidebar-bg);
    backdrop-filter: blur(12px);
    border-radius: 24px;
    padding: 1.5rem 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1.2rem;
    box-shadow: 
        0 8px 32px rgba(0, 0, 0, 0.05),
        0 4px 12px rgba(0, 0, 0, 0.03);
    border: 1px solid var(--sidebar-border);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 1000;
}

.sidebar:hover {
    box-shadow: 
        0 12px 48px rgba(0, 0, 0, 0.08),
        0 6px 24px rgba(0, 0, 0, 0.05);
    transform: translateY(-50%) scale(1.02);
}

.sidebar a {
    color: var(--sidebar-icon);
    font-size: 1.6rem;
    padding: 18px;
    border-radius: 16px;
    transition: all 0.3s ease;
    position: relative;
    display: flex;
    justify-content: center;
    align-items: center;
}

.sidebar a::after {
    content: attr(data-tooltip);
    position: absolute;
    right: 100%;
    top: 50%;
    transform: translateY(-50%);
    background: var(--sidebar-icon);
    color: white;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 500;
    white-space: nowrap;
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.sidebar a:hover::after {
    opacity: 1;
}

.sidebar a:hover {
    color: var(--sidebar-icon-hover);
    background: rgba(99, 102, 241, 0.08);
    transform: scale(1.15);
}

.sidebar a.active {
    color: white;
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    box-shadow: 0 4px 6px rgba(79, 70, 229, 0.15);
}

.sidebar a i {
    transition: transform 0.3s ease;
}

.sidebar a:hover i {
    transform: rotate(-10deg);
}
.container {
            max-width: 1200px;
            margin: 40px 20px;
            padding: 0px 40px;
            animation: fadeIn 0.5s ease-out;
        }
        /* Animations */
        @keyframes slideIn {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }



        /* Nouveau style pour le groupement par lettre */
        .card-group {
            margin-bottom: 2rem;
        }
        .group-header {
            background: #eee;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        /* Global Notification Styling */
/* Global Notification Styling */
.notification {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 15px 25px;
    margin: 20px 0;
    border-radius: 10px;
    color: white;
    font-size: 1rem;
    opacity: 0;
    animation: fadeInOut 5s forwards;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    position: relative;
}

.success {
    background-color: #4CAF50;
}

.error {
    background-color: #f44336;
}

.notification i {
    margin-right: 15px;
    font-size: 1.5rem;
}

.notification .message {
    flex-grow: 1;
}

.notification button {
    background: transparent;
    border: none;
    color: white;
    font-size: 1.25rem;
    cursor: pointer;
    transition: opacity 0.3s;
}

.notification button:hover {
    opacity: 0.7;
}

@keyframes fadeInOut {
    0% {
        opacity: 0;
    }
    10% {
        opacity: 1;
    }
    90% {
        opacity: 1;
    }
    100% {
        opacity: 0;
    }
}

/* Input Group Styles */
.input-group {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
}

.price-input {
    flex: 1;
    padding: 10px;
    border: 2px solid #ddd;
    border-radius: 8px;
    font-size: 1rem;
    background: #fafafa;
    transition: border-color 0.3s ease;
}

.price-input:focus {
    border-color: var(--primary);
    outline: none;
}


        .group-header h2 {
            margin: 0;
            font-size: 1.5rem;
            color: var(--primary);
        }
        .cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1rem;
        }

        /* Ancien style de la carte */
        .card {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
        }
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid rgba(0,0,0,0.05);
        }
        .card-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }
        .card-actions {
            display: flex;
            gap: 0.5rem;
        }
        /* Formulaire nouvelle carte */
        .new-card-form .input-group {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        /* Formulaire item modernisé */
        .item-form {
            display: none;
            margin-top: 1rem;
            background: rgba(255, 255, 255, 0.9);
            padding: 1rem;
            border-radius: 12px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        .item-form.active {
            display: block;
            animation: fadeIn 0.3s ease-out;
        }
        
        .quantity-control {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .quantity-btn {
            background: var(--primary);
            color: white;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .quantity-btn:hover {
            background: var(--secondary);
            transform: scale(1.05);
        }
        .quantity-input {
            width: 60px;
            padding: 8px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
        }
        .item-text-input {
            flex: 1;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            background: #fafafa;
            transition: border-color 0.3s ease;
        }
        .item-text-input:focus {
            border-color: var(--primary);
            outline: none;
        }
        .btn-submit {
            background: var(--success);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
        }
        /* Liste des items */
        .items-list {
            list-style: none;
            margin: 1.5rem 0;
        }
        .item {
            padding: 1rem;
            background: rgba(241, 245, 249, 0.5);
            border-radius: 8px;
            margin-bottom: 0.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s ease;
        }
        .item:hover {
            transform: translateX(5px);
            background: #fff;
        }
        .item-info {
            flex: 1;
        }
        .item-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        .item-details {
            display: flex;
            gap: 1rem;
            color: #64748b;
            font-size: 0.9rem;
        }
        .item-actions {
            display: flex;
            gap: 0.5rem;
        }
        .action-btn {
            background: none;
            border: none;
            padding: 6px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 1.1rem;
        }
        .action-btn.edit {
            color: var(--primary);
        }
        .action-btn.delete {
            color: var(--accent);
        }

        .action-btn:hover {
            background: rgba(0,0,0,0.05);
        }
        /* Total de la carte */
        .card-total {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 2px solid rgba(0,0,0,0.05);
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--success);
            display: flex;
            justify-content: space-between;
        }
        .email-form {
    display: none;
    margin-top: 1rem;
    background: rgba(255, 255, 255, 0.9);
    padding: 1rem;
    border-radius: 12px;
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
    border: 1px solid #e2e8f0;
    transition: all 0.3s ease;
}

.email-form.active {
    display: block;
    animation: fadeIn 0.3s ease-out;
}
@media (max-width: 768px) {
            body {
                padding-right: 0;
            }

            .sidebar {
                width: 60px;
                right: -60px;
            }

            .sidebar:hover {
                right: 0;
                width: 220px;
            }

            .container {
                padding: 0 20px;
                margin-top: 20px;
            }
        }
        .section-letter {
            font-size: 2rem;
            color: #f43f5e;
            margin: 2rem 0 1rem;
            padding-left: 1rem;
            border-left: 4px solid #f43f5e;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <a href="calcul.html" data-tooltip="Calculatrice">
        <i class="fas fa-calculator"></i>
    </a>
    <a href="index.html" data-tooltip="Accueil">
        <i class="fas fa-home"></i>
    </a>
    <a href="ajouter_client.php" data-tooltip="Nouveau client">
        <i class="fas fa-plus"></i>
    </a>
    <a href="karni.php" data-tooltip="Panier">
        <i class="fas fa-shopping-cart"></i>
    </a>
</div>
<div class="container">  
    <h1 style='color:#f43f5e;'><i class="fas fa-boxes"></i>SBASA</h1>
    <?php if (isset($_GET['success'])): ?>
    <div class="notification success" id="successNotification">
        <i class="fas fa-check-circle"></i>
        <div class="message">Email envoyé avec succès !</div>
        <button onclick="this.parentElement.style.display='none';">×</button>
    </div>
    <script>
        setTimeout(function() {
            document.getElementById('successNotification').style.display = 'none';
        }, 5000); // Hide notification after 5 seconds
    </script>
    <?php elseif (isset($_GET['error'])): ?>
    <div class="notification error" id="errorNotification">
        <i class="fas fa-times-circle"></i>
        <div class="message">Erreur lors de l'envoi de l'email</div>
        <button onclick="this.parentElement.style.display='none';">×</button>
    </div>
    <script>
        setTimeout(function() {
            document.getElementById('errorNotification').style.display = 'none';
        }, 5000); // Hide notification after 5 seconds
    </script>
    <?php endif; ?>
    <!-- Formulaire nouvelle carte -->
    <form method="POST" class="new-card-form" style='margin-top:20px;'>
        <input type="hidden" name="action" value="add_card">
        <div class="input-group">
            <input type="text" name="card_title" class="price-input" placeholder="Nom de client " required>
            <button type="submit" class="btn-submit">
                <i class="fas fa-plus"></i> Créer
            </button>
        </div>
    </form>
</div>


        <!-- Affichage des cartes groupées par lettre -->
        <?php foreach ($grouped_cards as $letter => $cardsForLetter): ?>
        <div class="card-group">
        <div class="section-letter" style="color:#f43f5e;"><?= $letter ?></div>
            <div class="cards-container">
                <?php foreach ($cardsForLetter as $card): ?>
                <div class="card">
          
                    <div class="card-header">
                        <!-- Formulaire d'envoi d'email -->

                        <h3 class="card-title">
                                <i class="fas fa-user"></i>
                            <?= htmlspecialchars($card['title']) ?></h3>
                        <div class="card-actions">
                        <a href="generate_pdf.php?card_id=<?= $card['id'] ?>" class="download-btn quantity-btn" title="Télécharger en PDF">
    <i class="fas fa-file-pdf"></i>
</a>                                                
<button onclick="toggleEmailForm(<?= $card['id'] ?>)" class="quantity-btn" title="Envoyer par email">
        <i class="fas fa-envelope"></i>
    </button>
                            <button onclick="toggleItemForm(<?= $card['id'] ?>)" class="quantity-btn">
                                <i class="fas fa-plus"></i>
                            </button>
                            <button onclick="deleteCard(<?= $card['id'] ?>)" class="quantity-btn">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </div>
                    <form method="POST" class="email-form" id="email-form-<?= $card['id'] ?>">
    <input type="hidden" name="action" value="send_email">
    <input type="hidden" name="card_id" value="<?= $card['id'] ?>">
    
    <div class="input-group">
        <input type="email" name="recipient_email" class="price-input" 
               placeholder="Entrez l'adresse email" required>
        <button type="submit" class="btn-submit">
            <i class="fas fa-paper-plane"></i>
        </button>
    </div>
</form>

                    <!-- Formulaire item modernisé -->
                    <form method="POST" class="item-form" id="form-<?= $card['id'] ?>">
                        <input type="hidden" name="action" value="add_item">
                        <input type="hidden" name="card_id" value="<?= $card['id'] ?>">
                        
                        <div class="input-group">
                            <div class="quantity-control">
                                <button type="button" class="quantity-btn" onclick="changeQuantity(<?= $card['id'] ?>, -1)">-</button>
                                <input type="number" name="quantity" class="quantity-input" value="1" min="1" required>
                                <button type="button" class="quantity-btn" onclick="changeQuantity(<?= $card['id'] ?>, 1)">+</button>
                            </div>
                            <input type="number" step="0.01" name="price" class="price-input" placeholder="Prix" required>
                        </div>
                        
                        <input type="text" name="item_text" class="item-text-input" placeholder="Nom de l'article" required>
                        
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-save"></i> Enregistrer
                        </button>
                    </form>

                    <!-- Liste des items -->
                    <ul class="items-list">
                        <?php foreach ($cards_items[$card['id']] as $item): ?>
                        <li class="item">
                            <div class="item-info">
                                <div class="item-title"><?= htmlspecialchars($item['text']) ?></div>
                                <div class="item-details">
                                    <span><?= $item['quantity'] ?> x <?= number_format($item['unit_price'], 2) ?> د.ت</span>
                                    <span>Total: <?= number_format($item['price_total'], 2) ?> د.ت</span>
                                </div>
                            </div>
                            <div class="item-actions">
                                <button class="action-btn edit" onclick='editItem(<?= $item["id"] ?>, <?= $item["quantity"] ?>, <?= $item["unit_price"] ?>, <?= json_encode($item["text"]) ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="action-btn delete" onclick="deleteItem(<?= $item['id'] ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>

                    <!-- Total de la carte -->
                    <div class="card-total">
                        <span>Total:</span>
                        <span><?= number_format($cards_totals[$card['id']], 2) ?> د.ت</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <script>
        // Affiche/Masque le formulaire d'item en basculant la classe "active"
        function toggleItemForm(cardId) {
            const form = document.getElementById(`form-${cardId}`);
            form.classList.toggle('active');
        }

        // Contrôle du nombre
        function changeQuantity(cardId, delta) {
            const input = document.querySelector(`#form-${cardId} .quantity-input`);
            let value = parseInt(input.value) + delta;
            input.value = value < 1 ? 1 : value;
        }

        // Suppression d'un article via redirection GET
        function deleteItem(itemId) {
            if (confirm('Supprimer cet article ?')) {
                window.location.href = "?delete_item=" + itemId;
            }
        }

        // Modification d'un article (quantité, prix et texte) via prompt et redirection GET
        function editItem(itemId, currentQuantity, currentPrice, currentText) {
            let newQuantity = prompt("Modifier la quantité :", currentQuantity);
            if (newQuantity === null) return; // Annulation
            let newPrice = prompt("Modifier le prix :", currentPrice);
            if (newPrice === null) return;
            let newText = prompt("Modifier le texte de l'article :", currentText);
            if (newText === null) return;
            window.location.href = "?edit_item=" + itemId 
                + "&new_quantity=" + encodeURIComponent(newQuantity)
                + "&new_price=" + encodeURIComponent(newPrice)
                + "&new_text=" + encodeURIComponent(newText);
        }

        // Suppression d'une carte via redirection GET
        function deleteCard(cardId) {
            if (confirm("Supprimer cette catégorie et tous ses articles ?")) {
                window.location.href = "?delete_card=" + cardId;
            }
        }
        function toggleEmailForm(cardId) {
    const form = document.getElementById(`email-form-${cardId}`);
    form.classList.toggle('active');
}
    </script>
</body>
</html>
