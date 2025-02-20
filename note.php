<?php
// Connexion à la base de données
$servername = "localhost";
$username = "root";
$password = "root";
$dbname = "produits_db";

try {
    $conn = new PDO(
        "mysql:host=$servername;dbname=$dbname",
        $username, 
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    // Récupération des notes groupées par date
    $stmt = $conn->query("
        SELECT 
            id, 
            content, 
            DATE(created_at) as date,
            created_at
        FROM notess 
        ORDER BY created_at DESC
    ");
    $notes = $stmt->fetchAll();
    
    // Groupement par date
    $groupedNotes = [];
    foreach($notes as $note) {
        $date = date('d F Y', strtotime($note['date']));
        $groupedNotes[$date][] = $note;
    }

} catch(PDOException $e) {
    die("<div class='error'>Erreur : " . htmlspecialchars($e->getMessage()) . "</div>");
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notes Perso - Smart Notes</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #6366f1;
            --accent: #f43f5e;
            --text: #1e293b;
            --background: #f8fafc;
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
        /* Conteneur Principal */
        .container {
            max-width: 1200px;
            margin: 50px auto;
            padding: 0 40px;
        }

        /* Éditeur de Notes */
        .note-editor {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 3rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            position: relative;
        }

        .note-editor textarea {
            width: 100%;
            height: 150px;
            padding: 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            resize: none;
            font-size: 1.1rem;
            line-height: 1.6;
            transition: all 0.3s;
        }

        .note-editor textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
            outline: none;
        }

        /* Liste des Notes */
        .notes-grid {
            display: grid;
            gap: 2rem;
        }

        .note-day-group {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        }

        .note-date {
            color: var(--primary);
            font-size: 1.4rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e2e8f0;
        }

        .note-card {
            background: var(--background);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            position: relative;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .note-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .note-content {
            color: var(--text);
            font-size: 1.1rem;
            line-height: 1.7;
            white-space: pre-wrap;
        }

        .note-time {
            color: #64748b;
            font-size: 0.9rem;
            margin-top: 1rem;
        }

        .note-actions {
            position: absolute;
            top: 1rem;
            right: 1rem;
            display: flex;
            gap: 0.8rem;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .note-card:hover .note-actions {
            opacity: 1;
        }

        .action-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .edit-btn {
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary);
        }

        .delete-btn {
            background: rgba(244, 63, 94, 0.1);
            color: var(--accent);
        }
/* Bouton Ajouter Note */
.add-note-btn {
    margin-top: 1rem;
    background-color: var(--primary);
    color: white;
    border: none;
    padding: 12px 24px;
    font-size: 1rem;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
}

.add-note-btn:hover {
    background-color: var(--accent);
    transform: scale(1.05);
}

        .action-btn:hover {
            transform: scale(1.1);
        }

        /* Bouton Flottant */
        .floating-btn {
            position: fixed;
            bottom: 2rem;
            right: calc(var(--sidebar-width) + 2rem);
            background: var(--primary);
            color: white;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 500;
        }

        .floating-btn:hover {
            transform: scale(1.1) rotate(90deg);
            box-shadow: 0 15px 30px rgba(0,0,0,0.3);
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
                width: 240px;
            }

            .container {
                padding: 0 20px;
            }

            .floating-btn {
                right: 2rem;
            }
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

<div class="note-editor">
    <form id="noteForm">
        <textarea 
            placeholder="Commencez à écrire votre note..."
            rows="4"
            id="noteContent"
        ></textarea>
        <button type="button" id="addNoteBtn" class="add-note-btn">
            Ajouter Note
        </button>
    </form>
</div>


    <div class="notes-grid">
        <?php if(!empty($groupedNotes)): ?>
            <?php foreach($groupedNotes as $date => $notes): ?>
                <div class="note-day-group">
                    <div class="note-date"><?= $date ?></div>
                    <?php foreach($notes as $note): ?>
                        <div class="note-card">
                            <div class="note-actions">
                                <div class="action-btn edit-btn" data-id="<?= $note['id'] ?>">
                                    <i class="fas fa-edit"></i>
                                </div>
                                <div class="action-btn delete-btn" data-id="<?= $note['id'] ?>">
                                    <i class="fas fa-trash"></i>
                                </div>
                            </div>
                            <div class="note-content"><?= htmlspecialchars($note['content']) ?></div>
                            <div class="note-time">
                                <?= date('H:i', strtotime($note['created_at'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="note-day-group">
                <div class="note-date">Aucune note</div>
                <div class="note-card">
                    <div class="note-content">Commencez par créer votre première note !</div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="floating-btn" id="saveNote">
    <i class="fas fa-plus"></i>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const saveBtn = document.getElementById('saveNote');
    const noteContent = document.getElementById('noteContent');
    const addNoteBtn = document.getElementById('addNoteBtn');  // Nouveau bouton

    // Fonction pour ajouter une note
    addNoteBtn.addEventListener('click', async () => {
        const content = noteContent.value.trim();
        if(content) {
            try {
                const response = await fetch('save_note.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ content })
                });

                if(response.ok) {
                    location.reload();
                }
            } catch(error) {
                console.error('Erreur:', error);
            }
        }
    });

    // Édition de note
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const noteId = this.dataset.id;
            const content = this.closest('.note-card').querySelector('.note-content').textContent;
            noteContent.value = content;
            noteContent.focus();
            saveBtn.dataset.mode = 'edit';
            saveBtn.dataset.noteId = noteId;
        });
    });

    // Suppression de note
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            const noteId = this.dataset.id;
            if(confirm('Supprimer définitivement cette note ?')) {
                try {
                    await fetch(`delete_note.php?id=${noteId}`, { method: 'DELETE' });
                    location.reload();
                } catch(error) {
                    console.error('Erreur:', error);
                }
            }
        });
    });
});

</script>

</body>
</html>