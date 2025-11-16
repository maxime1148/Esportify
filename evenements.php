<?php
session_start();

$host = '127.0.0.1';
$db = 'esportify_sql';
$dbuser = 'root';
$dbpass = '';

$mysqli = new mysqli($host, $dbuser, $dbpass, $db);
if ($mysqli->connect_errno) {
    $error = 'Erreur de connexion à la base de données: ' . $mysqli->connect_error;
}

// Logout handler
if (isset($_GET['logout'])) {
    if (isset($_SESSION['user_id']) && $mysqli) {
        $stmt = $mysqli->prepare("UPDATE utilisateurs SET status='not_connected' WHERE id=?");
        if ($stmt) {
            $stmt->bind_param('i', $_SESSION['user_id']);
            $stmt->execute();
            $stmt->close();
        }
    }
    session_unset();
    session_destroy();
    header('Location: menu.php');
    exit;
}

$message = '';
// Login handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $message = 'Veuillez remplir tous les champs.';
    } else {
        if ($mysqli) {
            $stmt = $mysqli->prepare('SELECT id, username, password, role FROM utilisateurs WHERE username = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    // NOTE: passwords in the provided SQL are stored in plain text.
                    // For production you should use password_hash() and password_verify().
                    if ($password === $row['password']) {
                        $_SESSION['user_id'] = $row['id'];
                        $_SESSION['username'] = $row['username'];
                        $_SESSION['role'] = $row['role'];

                        $uid = $row['id'];
                        $up = $mysqli->prepare("UPDATE utilisateurs SET status='connected' WHERE id=?");
                        if ($up) {
                            $up->bind_param('i', $uid);
                            $up->execute();
                            $up->close();
                        }

                        // Redirect to avoid form resubmission
                        header('Location: menu.php');
                        exit;
                    } else {
                        $message = 'Mot de passe incorrect.';
                    }
                } else {
                    $message = 'Utilisateur non trouvé.';
                }
                $stmt->close();
            } else {
                $message = 'Erreur interne (préparation de la requête).';
            }
        } else {
            $message = 'Connexion à la base de données impossible.';
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title>Esportify - Événements</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Holtwood+One+SC&family=Lato:ital,wght@0,100;0,300;0,400;0,700;0,900;1,100;1,300;1,400;1,700;1,900&family=Limelight&family=Lobster&display=swap" rel="stylesheet">

    <script src="https://code.jquery.com/jquery-3.6.0.min.js" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/stylesMenu.css">
   
</head>
<body>
    <div class="container">

    
        <section id="menu">
            <div class="menu-bar">
                <div class="row">
                        <div class="menu-title">
                            <h1><em>Esportify - Événements</em></h1>
                        </div>
                        <div class="menu-logo">
                            <img src="images/logo-jeu.png" alt="Icône" class="menu-icon">
                            <span class="menu-text">Esportify</span>
                        </div>
                </div>
                <br>
                <div class="row">
                    <nav class="row" style="position:relative;">
                        <div class="col-6 menu-item">
                            <a href="index.php" class="home-button">
                                <span class="menu-home-icon"><i class="bi bi-house-door-fill"></i></span> Accueil
                            </a>
                            <span class="menu-icon-mobile">
                                <a href="index.php" class="menu-icon-mobile">&#8962;</a>
                            </span>
                        </div>
                        <div class="col-6 menu-item">
                                <a href="menu.php" class="menu-button">Menu</a>
                                <span class="menu-icon-mobile"><a href="menu.php" class="menu-icon-mobile">&#9776;</a></span> <!-- Icône pour smartphone -->
                        </div>
                        
                        <?php if (!empty($_SESSION['username'])): ?>
                            <div class="nav-login-badge">
                                <div class="login-user"><?php echo htmlspecialchars($_SESSION['username']); ?> est connecté -</div>
                                <div class="login-role">statut : <?php echo htmlspecialchars($_SESSION['role'] ?? ''); ?></div>
                                <div class="login-logout"><a href="menu.php?logout=1">Se déconnecter</a></div>
                            </div>
                        <?php endif; ?>
                    </nav>
                </div>
            </div>
            
        </section>

        <section id="contenu">
            
            
            <br>
            <br>
            <div class="row">
                
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Titre</th>
                                <th>Description</th>
                                <th>Date de début</th>
                                <th>Date de fin</th>
                                <th>Nombre de joueurs</th>
                                <th>Organisateur</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php
                        // Récupère les événements visibles et le nom de l'organisateur
                        $sql = "SELECT e.id, e.titre, e.description, e.date_debut, e.date_fin, e.nb_joueurs, u.username AS organisateur
                                FROM evenements e
                                LEFT JOIN utilisateurs u ON e.organisateur_id = u.id
                                WHERE e.visibilite = 'visible'
                                ORDER BY e.date_debut ASC";

                        if ($res = $mysqli->query($sql)) {
                            if ($res->num_rows > 0) {
                                while ($ev = $res->fetch_assoc()) {
                                    $start = $ev['date_debut'] ? date('d/m/Y H:i', strtotime($ev['date_debut'])) : '';
                                    $end = $ev['date_fin'] ? date('d/m/Y H:i', strtotime($ev['date_fin'])) : '';
                                    echo '<tr>';
                                    echo '<td>' . htmlspecialchars($ev['id']) . '</td>';
                                    echo '<td>' . htmlspecialchars($ev['titre']) . '</td>';
                                    echo '<td>' . htmlspecialchars($ev['description']) . '</td>';
                                    echo '<td>' . htmlspecialchars($start) . '</td>';
                                    echo '<td>' . htmlspecialchars($end) . '</td>';
                                    echo '<td>' . htmlspecialchars($ev['nb_joueurs']) . '</td>';
                                    echo '<td>' . htmlspecialchars($ev['organisateur'] ?? '') . '</td>';
                                    echo '</tr>';
                                }
                                $res->free();
                            } else {
                                echo '<tr><td colspan="7">Aucun événement trouvé.</td></tr>';
                            }
                        } else {
                            echo '<tr><td colspan="7">Erreur lors de la lecture des événements.</td></tr>';
                        }
                        ?>
                        </tbody>
                    </table>
               
            </div>
            <section class="row contact-section">
                <div class="contact-box">
                    <span class="contact-title">Contact</span>
                    <span class="contact-icon">&#128231;</span>
                    <span class="contact-mail">esportify@gmail.com</span>
                </div>
            </section>
        </section>
    </div>
</body>
</html>