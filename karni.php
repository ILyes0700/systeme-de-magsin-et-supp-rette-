<?php  
// Connexion à la base de données
$servername = "localhost";
$username = "root";
$password = "root";
$dbname = "produits_db";
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';  // Autoload de Composer pour PHPMaile
try {
    // Connexion à la base de données
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Vérifier si la requête est un envoi d'email
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_email') { 
        $client_id = intval($_POST['client_id']);
        $recipient_email = filter_var($_POST['recipient_email'], FILTER_SANITIZE_EMAIL);

        // Récupérer les données du panier pour ce client (en utilisant client_id)
        $stmt = $conn->prepare("SELECT * FROM panier WHERE client_id = :client_id");
        $stmt->execute(['client_id' => $client_id]);
        $panier = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Vérifier s'il y a un panier
        if (empty($panier)) {
            echo "Aucun panier trouvé pour ce client.";
            exit;
        }

        // Initialiser le total du panier
        $total = 0;

        // Construire le contenu HTML de l'email avec les informations du panier
        $emailContent = "<h2 style='font-weight: normal; color: #333;'>Détails de votre panier</h2>";
        $emailContent .= "<table style='width: 100%; border-collapse: collapse; margin-top: 20px;'>";
        $emailContent .= "<thead style='background-color: #f5f5f5;'><tr><th>Date</th><th>Montant</th></tr></thead><tbody>";

        // Parcourir les produits du panier et ajouter à l'email
        foreach ($panier as $item) {
            $date = htmlspecialchars($item['date']);
            $montant = number_format($item['montant'], 2, '.', ',');
            $emailContent .= "<tr><td>{$date}</td><td>{$montant} د.ت</td></tr>";
            $total += $item['montant'];
        }

        // Afficher le total du panier
        $emailContent .= "</tbody></table>";
        $emailContent .= "<h3 style='margin-top: 20px; color: #333;'>Total : <span style='color: #e74c3c;'>" . number_format($total, 2) . " د.ت</span></h3>";

        // Configuration de PHPMailer
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'pharfind@gmail.com';
            $mail->Password = 'rfqdlvatmnuklgtb';  // Assurez-vous de ne jamais exposer vos mots de passe en production !
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('pharfind@gmail.com', 'Hamrouni');
            $mail->addAddress($recipient_email);
            $mail->isHTML(true);
            $mail->Subject = 'Détails de votre panier';
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

            // Envoi de l'email
            $mail->send();
            header("Location: " . $_SERVER['REQUEST_URI']);
        } catch (Exception $e) {
            echo "Le message n'a pas pu être envoyé. Erreur de PHPMailer: {$mail->ErrorInfo}";
        }

        exit;
    }


    
    // Traitement de l'ajout au panier
    if (isset($_POST['ajouter_montant'])) {
        $client_id = $_POST['client_id'];
        $montant = $_POST['montant'];
        
        $stmt = $conn->prepare("INSERT INTO panier (client_id, montant) VALUES (:client_id, :montant)");
        $stmt->bindParam(':client_id', $client_id);
        $stmt->bindParam(':montant', $montant);
        $stmt->execute();
        header("Location: karni.php");
        exit;
    }

    // Traitement de la suppression du panier
    if (isset($_POST['vider_panier'])) {
        $client_id = $_POST['client_id'];
        
        $stmt = $conn->prepare("DELETE FROM panier WHERE client_id = :client_id");
        $stmt->bindParam(':client_id', $client_id);
        $stmt->execute();
        header("Location: karni.php");
        exit;
    }

    // Récupérer tous les clients triés par nom
    $stmt = $conn->query("SELECT * FROM clients ORDER BY nom ASC");
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organiser les clients par lettre initiale
    $groupedClients = [];
    foreach ($clients as $client) {
        $firstLetter = strtoupper($client['nom'][0]);
        if (!isset($groupedClients[$firstLetter])) {
            $groupedClients[$firstLetter] = [];
        }
        $groupedClients[$firstLetter][] = $client;
    }

    // Récupérer les paniers pour chaque client
    $stmt_panier = $conn->query("SELECT * FROM panier");
    $paniers = $stmt_panier->fetchAll(PDO::FETCH_ASSOC);

    // Ajouter une note
    if (isset($_POST['ajouter_note'])) {
        $client_id = $_POST['client_id'];
        $note = $_POST['note'];
        $stmt = $conn->prepare("INSERT INTO notes (client_id, note) VALUES (:client_id, :note)");
        $stmt->bindParam(':client_id', $client_id);
        $stmt->bindParam(':note', $note);
        $stmt->execute();
        header("Location: karni.php");
        exit;
    }

    // Supprimer une note
    if (isset($_POST['supprimer_note'])) {
        $note_id = $_POST['note_id'];
        $stmt = $conn->prepare("DELETE FROM notes WHERE id = :note_id");
        $stmt->bindParam(':note_id', $note_id);
        $stmt->execute();
        header("Location: karni.php");
        exit;
    }

    // Récupérer les notes pour chaque client
    $stmt_notes = $conn->query("SELECT * FROM notes");
    $notes = $stmt_notes->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Clients - Grotte</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #6366f1;
            --secondary: #4f46e5;
            --accent: #f43f5e;
            --success: #10b981;
            --background: #f8fafc;
            --text: #1e293b;
            --card-bg: #ffffff;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --sidebar-bg: rgba(255, 255, 255, 0.95);
    --sidebar-border: rgba(209, 213, 219, 0.3);
    --sidebar-icon: #4f46e5;
    --sidebar-icon-hover: #6366f1;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        body {
            background: var(--background);
            color: var(--text);
            min-height: 100vh;
            padding-right: 80px; /* Adjusted for right sidebar */
        }


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
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        h1 {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        h1 i {
            font-size: 2rem;
        }

        .client-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .client-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            transition: transform 0.2s, box-shadow 0.2s;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .client-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .client-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .client-icon {
            width: 45px;
            height: 45px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }

        .client-name {
            font-size: 1.3rem;
            color: var(--text);
            font-weight: 600;
        }

        .total {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.2rem;
            margin: 1rem 0;
            padding: 0.8rem;
            background: rgba(99, 102, 241, 0.1);
            border-radius: 8px;
            color: var(--primary);
        }

        .input-group {
            position: relative;
            margin: 1rem 0;
        }

        .input-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
        }

        input[type="number"] {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        input[type="number"]:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
            outline: none;
        }

        .button-group {
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--secondary);
            transform: translateY(-1px);
        }

        .btn-danger {
            background: #f43f5e1a;
            color: var(--accent);
            border: 2px solid #f43f5e33;
        }

        .btn-danger:hover {
            background: #f43f5e26;
        }

        .section-letter {
            font-size: 2rem;
            color: #f43f5e;
            margin: 2rem 0 1rem;
            padding-left: 1rem;
            border-left: 4px solid #f43f5e;
        }
        .history-icon {
            cursor: pointer;
            color: #10b981;
            margin-left: 10px;
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            width: 80%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            animation: modalSlide 0.3s ease-out;
        }

        @keyframes modalSlide {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-close {
            cursor: pointer;
            font-size: 1.5rem;
            color: #64748b;
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .history-table th, 
        .history-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .history-table th {
            background-color: #f8fafc;
            color: #64748b;
        }

        .total-badge {
            background: #10b981;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            margin-top: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .note-icon {
            cursor: pointer;
            color: var(--primary);
        }
        .notes-modal-content {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            width: 90%;
            max-width: 500px;
            animation: modalSlide 0.3s ease-out;
        }

        .note-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 8px;
            margin: 0.5rem 0;
            transition: all 0.2s ease;
        }

        .note-item:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        }

        .note-actions {
            display: flex;
            gap: 0.8rem;
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
.input-group {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
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
        .note-action-icon {
            cursor: pointer;
            padding: 6px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .note-edit {
            color: #3b82f6;
            background: rgba(59, 130, 246, 0.1);
        }

        .note-edit:hover {
            background: rgba(59, 130, 246, 0.2);
        }

        .note-delete {
            color: #ef4444;
            background: rgba(239, 68, 68, 0.1);
        }

        .note-delete:hover {
            background: rgba(239, 68, 68, 0.2);
        }

        .note-input-group {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .note-input {
            flex: 1;
            padding: 0.8rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .note-input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }

        .btn-add-note {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-add-note:hover {
            background: var(--secondary);
            transform: translateY(-1px);
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
    <h1><i class="fas fa-users-cog"></i>KARNY</h1>

    <?php foreach (range('A', 'Z') as $letter): ?>
        <?php if (isset($groupedClients[$letter])): ?>
            <div class="section-letter" style="color:#f43f5e;"><?= $letter ?></div>
            <div class="client-grid">
                <?php foreach ($groupedClients[$letter] as $client): ?>
                    <div class="client-card">
                        <div class="client-header">
                            <div class="client-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <h3 class="client-name"><?= $client['nom'] ?></h3>
                            <!-- Icone de note -->
                            <!-- Remplacez la partie des icônes de note -->
<div>
    <i class="fas fa-sticky-note note-icon" 
       onclick="toggleNote(<?= $client['id'] ?>, '<?= $client['nom'] ?>')"></i>
    <i class="fas fa-history history-icon" 
       onclick="showHistory(<?= $client['id'] ?>, '<?= $client['nom'] ?>')"></i>
       <i class="fas fa-paper-plane note-icon " style='margin-left:10px; color:#f43f5e;'  onclick="toggleEmailForm(<?= $client['id'] ?>)"></i>
</div>
                        </div>
                        <form method="POST" class="email-form" id="email-form-<?= $client['id'] ?>">
    <input type="hidden" name="action" value="send_email">
    <input type="hidden" name="client_id" value="<?= $client['id'] ?>">
    
    <div class="input-group">
        <input type="email" name="recipient_email" class="price-input" 
               placeholder="Entrez l'adresse email" required>
        <button type="submit" class="btn-submit">
            <i class="fas fa-paper-plane"></i>
        </button>
    </div>
</form>

                        

                        <?php
                        $client_id = $client['id'];
                        $total = 0;
                        foreach ($paniers as $panier) {
                            if ($panier['client_id'] == $client_id) {
                                $total += $panier['montant'];
                            }
                        }
                        ?>

                        <?php if ($total > 0): ?>
                            <div class="total">
                                <i class="fas fa-coins"></i>
                                <span>Total : <?= $total ?> د.ت</span>
                            </div>
                        <?php endif; ?>

                        <form method="post">
                            <div class="input-group">
                                <input type="number" name="montant" placeholder="Montant" step="0.01" >
                                <span class="input-icon">د.ت</span> <!-- Affichage du symbole du dinar tunisien -->
                            </div>

                            <input type="hidden" name="client_id" value="<?= $client['id'] ?>">
                            <div class="button-group">
                                <button type="submit" name="ajouter_montant" class="btn btn-primary">
                                    <i class="fas fa-cart-plus"></i>
                                    Ajouter
                                </button>
                                <button type="submit" name="vider_panier" class="btn btn-danger">
                                    <i class="fas fa-trash-alt"></i>
                                    Vider le panier
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
<div class="modal-overlay" id="notesModal">
        <div class="notes-modal-content">
            <div class="modal-header">
                <h3 id="notesModalTitle"></h3>
                <span class="modal-close" onclick="closeNotesModal()">&times;</span>
            </div>
            <div id="notesModalContent">
                <!-- Les notes seront chargées ici dynamiquement -->
            </div>
        </div>
    </div>
<div class="modal-overlay" id="historyModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle"></h3>
                <span class="modal-close" onclick="closeModal()">&times;</span>
            </div>
            <div id="modalContent"></div>
        </div>
    </div>
<script>
     async function showHistory(clientId, clientName) {
            try {
                const response = await fetch(`get_panier.php?client_id=${clientId}&ajax=1`);
                const history = await response.json();
                
                let html = `<table class="history-table">
                                <thead>
                                    <tr>
                                        <th>Montant (TND)</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>`;
                
                let total = 0;
                history.forEach(entry => {
                    html += `<tr>
                                <td>${parseFloat(entry.montant).toFixed(2)}</td>
                                <td>${new Date(entry.date).toLocaleDateString()}</td>
                            </tr>`;
                    total += parseFloat(entry.montant);
                });

                html += `</tbody></table>
                        <div class="total-badge">
                            <i class="fas fa-coins"></i>
                            Total: ${total.toFixed(2)} TND
                        </div>`;

                document.getElementById('modalTitle').textContent = `Historique de ${clientName}`;
                document.getElementById('modalContent').innerHTML = html;
                document.getElementById('historyModal').style.display = 'flex';
            } catch (error) {
                console.error('Erreur:', error);
            }
        }

        function closeModal() {
            document.getElementById('historyModal').style.display = 'none';
        }

        // Fermer la modale en cliquant à l'extérieur
        window.onclick = function(event) {
            const modal = document.getElementById('historyModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        async function showNotes(clientId, clientName) {
            try {
                const response = await fetch(`get_notes.php?client_id=${clientId}`);
                const notes = await response.json();

                let html = `
                    <div class="note-input-group">
                        <input type="text" class="note-input" id="newNoteInput" placeholder="Écrire une nouvelle note...">
                        <button class="btn-add-note" onclick="addNewNote(${clientId})">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>`;

                notes.forEach(note => {
                    html += `
                        <div class="note-item">
                            <div class="note-text">${note.note}</div>
                            <div class="note-actions">
                                <i class="fas fa-edit note-action-icon note-edit" 
                                   onclick="editNote(${note.id}, '${note.note.replace(/'/g, "\\'")}')"></i>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="note_id" value="${note.id}">
                                    <button type="submit" name="supprimer_note" class="note-action-icon note-delete">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </div>
                        </div>`;
                });

                document.getElementById('notesModalTitle').textContent = `Notes de ${clientName}`;
                document.getElementById('notesModalContent').innerHTML = html;
                document.getElementById('notesModal').style.display = 'flex';
            } catch (error) {
                console.error('Erreur:', error);
            }
        }

        function closeNotesModal() {
            document.getElementById('notesModal').style.display = 'none';
        }

        async function addNewNote(clientId) {
            const noteInput = document.getElementById('newNoteInput');
            const formData = new FormData();
            formData.append('client_id', clientId);
            formData.append('note', noteInput.value);
            formData.append('ajouter_note', true);

            try {
                await fetch('karni.php', {
                    method: 'POST',
                    body: formData
                });
                location.reload(); // Recharger pour voir la nouvelle note
            } catch (error) {
                console.error('Erreur:', error);
            }
        }

        function editNote(noteId, currentNote) {
            const newNote = prompt('Modifier la note :', currentNote);
            if (newNote !== null) {
                const formData = new FormData();
                formData.append('note_id', noteId);
                formData.append('nouvelle_note', newNote);
                formData.append('modifier_note', true);

                fetch('karni.php', {
                    method: 'POST',
                    body: formData
                }).then(() => location.reload());
            }
        }

        // Modifier l'appel original pour utiliser la nouvelle modale
        function toggleNote(clientId, clientName) {
            showNotes(clientId, clientName);
        }
        function toggleEmailForm(clientId) {
    const form = document.getElementById(`email-form-${clientId}`);
    form.classList.toggle('active');
}
</script>
</body>
</html>
