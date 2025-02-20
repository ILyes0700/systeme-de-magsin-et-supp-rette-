<?php  
// Connexion sécurisée à la base de données
$servername = "localhost";
$username   = "root";
$password   = "root";
$dbname     = "produits_db";

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
    
    // Pour le tableau, on récupère toutes les commandes (triées par date décroissante pour l'affichage)
    $stmt = $conn->query("SELECT * FROM commandes ORDER BY created_at DESC");
    $commandes = $stmt->fetchAll();

    // Calcul du total général (pour l'ensemble des commandes)
    $totalGeneral = array_sum(array_column($commandes, 'total_price'));

    /*
      Pour les graphiques par produit, on regroupe les commandes par produit.
      Pour chaque produit, on regroupe ensuite par date (format 'Y-m-d') en sommant la quantité et le total.
      Ainsi, chaque graphique affichera l'évolution (selon la date) de la quantité commandée et du montant total.
    */
    $produitsGrouped = [];
    foreach ($commandes as $commande) {
        $product = $commande['product_name'];
        // Format de la date (exemple : 2025-02-07)
        $date = date('Y-m-d', strtotime($commande['created_at']));
        
        if (!isset($produitsGrouped[$product])) {
            $produitsGrouped[$product] = [];
        }
        if (!isset($produitsGrouped[$product][$date])) {
            $produitsGrouped[$product][$date] = ['quantity' => 0, 'total' => 0];
        }
        $produitsGrouped[$product][$date]['quantity'] += $commande['quantity'];
        $produitsGrouped[$product][$date]['total']    += $commande['total_price'];
    }
    
    // Préparation des données à transmettre à JavaScript pour la génération des graphiques
    $chartData = [];
    foreach ($produitsGrouped as $product => $dataByDate) {
        // Tri par date ascendante pour une courbe chronologique
        ksort($dataByDate);
        $dates = [];
        $quantities = [];
        $totals = [];
        foreach ($dataByDate as $date => $values) {
            $dates[] = $date;
            $quantities[] = $values['quantity'];
            $totals[] = $values['total'];
        }
        $chartData[] = [
            'product'      => $product,
            'dates'        => $dates,
            'quantities'   => $quantities,
            'totals'       => $totals,
            'overallTotal' => array_sum($totals)
        ];
    }

} catch(PDOException $e) {
    die("<div class='error'>Erreur de base de données : " . htmlspecialchars($e->getMessage()) . "</div>");
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Historique des Commandes</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <!-- Inclusion de Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
      --gradient: linear-gradient(135deg, #6366f1 0%, #f43f5e 100%);
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
      padding-right: 80px;
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
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.05), 0 4px 12px rgba(0, 0, 0, 0.03);
      border: 1px solid var(--sidebar-border);
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
      z-index: 1000;
    }

    .sidebar:hover {
      box-shadow: 0 12px 48px rgba(0, 0, 0, 0.08), 0 6px 24px rgba(0, 0, 0, 0.05);
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

    .sidebar a:hover {
      background: rgba(99, 102, 241, 0.08);
      transform: scale(1.15);
    }

    .container {
      max-width: 1200px;
      margin: 40px auto;
      padding: 0 40px;
      animation: fadeIn 0.5s ease-out;
    }

    h1 {
      margin-bottom: 2.5rem;
      color: var(--primary);
      display: flex;
      align-items: center;
      gap: 1rem;
      font-size: 2.2rem;
    }

    .commande-table {
      background: white;
      border-radius: 16px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
      overflow-x: auto;
    }

    table {
      width: 100%;
      min-width: 600px;
      border-collapse: collapse;
    }

    th, td {
      padding: 1.2rem 1.5rem;
      text-align: left;
      border-bottom: 1px solid #f1f5f9;
    }

    th {
      background: var(--background);
      font-weight: 600;
      color: var(--primary);
      position: sticky;
      top: 0;
    }

    tr:nth-child(even) {
      background-color: #f8fafc;
    }

    tr:hover {
      background-color: #f1f5f9;
    }

    .price {
      font-weight: 600;
      color: var(--primary);
    }

    .date {
      color: #64748b;
      font-size: 0.95em;
    }

    .total-box {
      background: var(--primary);
      color: white;
      padding: 1.5rem 2rem;
      border-radius: 12px;
      margin-top: 2rem;
      display: inline-flex;
      align-items: center;
      gap: 1rem;
      float: right;
      box-shadow: 0 5px 15px rgba(79, 70, 229, 0.2);
    }

    .total-box i {
      font-size: 1.5rem;
    }

    /* Styles pour la section des graphiques par produit */
    .charts-container {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 20px;
      margin: 2rem 0;
    }

    .chart-card {
      background: white;
      padding: 1rem;
      border-radius: 16px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
    }

    .chart-card h3 {
      text-align: center;
      margin-bottom: 1rem;
      color: var(--primary);
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

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .error {
      background: #fee2e2;
      color: #dc2626;
      padding: 1rem;
      border-radius: 8px;
      margin: 2rem;
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
    <a href="karni.php" class="nav-icon" data-tooltip="karny"> 
      <i class="fas fa-users"></i>
    </a>
  </div>

  <div class="container">
    <h1><i class="fas fa-receipt"></i> Historique des Commandes</h1>
    
    <div class="commande-table">
      <table>
        <thead>
          <tr>
            <th><i class="fas fa-hashtag"></i>ID</th>
            <th><i class="fas fa-cube"></i>Produit</th>
            <th><i class="fas fa-sort-amount-up"></i>Quantité</th>
            <th><i class="fas fa-tag"></i>Prix Unitaire</th>
            <th><i class="fas fa-coins"></i>Total</th>
            <th><i class="fas fa-calendar-alt"></i>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($commandes)): ?>
            <tr>
              <td colspan="6" style="text-align: center; padding: 2rem;">
                Aucune commande trouvée
              </td>
            </tr>
          <?php else: ?>
            <?php foreach($commandes as $commande): ?>
              <tr>
                <td>#<?= htmlspecialchars($commande['id']) ?></td>
                <td><?= htmlspecialchars($commande['product_name']) ?></td>
                <td><?= $commande['quantity'] ?></td>
                <td class="price">
                  <?= number_format($commande['total_price'] / $commande['quantity'], 2) ?> د.ت
                </td>
                <td class="price">
                  <?= number_format($commande['total_price'], 2) ?> د.ت
                </td>
                <td class="date">
                  <?= date('d/m/Y H:i', strtotime($commande['created_at'])) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    
    <?php if(!empty($commandes)): ?>
      <div class="total-box">
        <i class="fas fa-wallet"></i>
        <div>
          <div style="font-size: 0.9em; opacity: 0.9;">Total Général</div>
          <div style="font-size: 1.4em; font-weight: 600;">
            <?= number_format($totalGeneral, 2) ?> د.ت
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- Section des graphiques par produit -->
    <h2 style="margin:2rem 0; color: var(--primary);">
      <i class="fas fa-chart-line"></i> Graphiques par Produit
    </h2>
    <div class="charts-container">
      <?php foreach($chartData as $index => $data): ?>
        <div class="chart-card">
          <h3><?= htmlspecialchars($data['product']) ?></h3>
          <canvas id="chart<?= $index ?>"></canvas>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <script>
    // Transfert des données PHP vers JavaScript
    const produitsData = <?php echo json_encode($chartData); ?>;
    
    produitsData.forEach(function(productData, index) {
      const canvas = document.getElementById('chart' + index);
      const ctx = canvas.getContext('2d');
      
      // Création d'un dégradé pour le dataset "Quantité"
      const gradientQuantity = ctx.createLinearGradient(0, 0, 0, 400);
      gradientQuantity.addColorStop(0, 'rgba(99, 102, 241, 0.4)');
      gradientQuantity.addColorStop(1, 'rgba(99, 102, 241, 0.1)');

      new Chart(ctx, {
        type: 'line',
        data: {
          labels: productData.dates,
          datasets: [
            {
              label: 'Quantité',
              data: productData.quantities,
              borderColor: '#6366f1',
              backgroundColor: gradientQuantity,
              fill: true,
              tension: 0.4,
              pointRadius: 4,
              pointStyle: 'circle'
            },
            {
              label: 'Total',
              data: productData.totals,
              borderColor: '#f43f5e',
              backgroundColor: 'rgba(244, 67, 54, 0.1)',
              fill: true,
              // Design alternatif : ligne en pointillés et style de point différent
              tension: 0,
              borderDash: [8, 4],
              pointRadius: 6,
              pointStyle: 'triangle'
            }
          ]
        },
        options: {
          responsive: true,
          plugins: {
            // Affichage du total général du produit en titre du graphique
            title: {
              display: true,
              text: 'Total: ' + parseFloat(productData.overallTotal).toFixed(2) + ' د.ت',
              font: {
                size: 16,
                weight: 'bold'
              },
              padding: {
                top: 10,
                bottom: 20
              }
            },
            legend: {
              position: 'top'
            },
            tooltip: {
              callbacks: {
                label: function(context) {
                  let label = context.dataset.label || '';
                  if (label) {
                    label += ': ';
                  }
                  label += context.parsed.y;
                  if(context.dataset.label === 'Total'){
                    label += ' د.ت';
                  }
                  return label;
                }
              }
            }
          },
          scales: {
            x: {
              title: {
                display: true,
                text: 'Date'
              },
              ticks: {
                autoSkip: true,
                maxTicksLimit: 10
              }
            },
            y: {
              title: {
                display: true,
                text: 'Valeur'
              },
              beginAtZero: true
            }
          }
        }
      });
    });
  </script>
  
</body>
</html>
