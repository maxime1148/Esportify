<?php
session_start();

$host = 'erxv1bzckceve5lh.cbetxkdyhwsb.us-east-1.rds.amazonaws.com';
$db = 'zghm67erntjc1fv1';
$dbuser = 'hxwvxrhk7b1h4vdl';
$dbpass = 'enpr39qjhrz8ojjd';

$mongodbAvailable = class_exists('MongoDB\\Driver\\Manager');
// Helper: session / role
$allowed_roles = ['organisateur','administrateur','admin','administrator'];
$user_role = strtolower(trim($_SESSION['role'] ?? ''));
$user_logged = !empty($_SESSION['username']);

// Handler de suppression d'événement via POST (action=delete_event)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_event') {
    $del_id = trim($_POST['event_id'] ?? '');
    $message = '';
    if ($del_id === '') {
        $message = 'Identifiant d\'événement manquant.';
    } elseif (!$mongodbAvailable) {
        $message = 'MongoDB non disponible.';
    } else {
        try {
            $mgr = new MongoDB\Driver\Manager('mongodb+srv://maximecassignol11_db_user:pNjiHPWGMi3DfbV3@esportifymongodb.jymrecd.mongodb.net/?appName=esportifyMongoDB');
            // normalise le filtre pour retrouver le document
            if (preg_match('/^[a-f\\d]{24}$/i', $del_id)) {
                try {
                    $filter = ['_id' => new MongoDB\BSON\ObjectId($del_id)];
                } catch (Exception $e) {
                    $filter = ['_id' => $del_id];
                }
            } else {
                $filter = ['_id' => $del_id];
            }

            $q = new MongoDB\Driver\Query($filter, ['limit' => 1]);
            $cursor = $mgr->executeQuery('esportifyMongoDB.evenements', $q);
            $found = null;
            foreach ($cursor as $d) { $found = $d; break; }

            // Vérifie les permissions : admin OU organisateur de l'événement
            $role = strtolower($_SESSION['role'] ?? '');
            $username = $_SESSION['username'] ?? '';
            $user_id = $_SESSION['user_id'] ?? '';
            // admin roles accepted
            $allowed = in_array($role, ['administrateur','organisateur'], true);

            

            if (!$allowed) {
                $message = 'Permission refusée pour supprimer cet événement.';
            } else {
                $bulk = new MongoDB\Driver\BulkWrite();
                if (preg_match('/^[a-f\\d]{24}$/i', $del_id)) {
                    $bulk->delete(['_id' => new MongoDB\BSON\ObjectId($del_id)], ['limit' => 1]);
                } else {
                    $bulk->delete(['_id' => $del_id], ['limit' => 1]);
                }
                $res = $mgr->executeBulkWrite('esportifyMongoDB.evenements', $bulk);
                // redirect pour recharger la liste
                header('Location: evenements.php');
                exit;
            }
        } catch (Exception $e) {
            $message = 'Erreur lors de la suppression : ' . $e->getMessage();
        }
    }
}

if ($mongodbAvailable) {
    try {
        // Utilisation du driver bas-niveau MongoDB\Driver\Manager
        $manager = new MongoDB\Driver\Manager('mongodb+srv://maximecassignol11_db_user:pNjiHPWGMi3DfbV3@esportifymongodb.jymrecd.mongodb.net/?appName=esportifyMongoDB');
        $filter = [];
        $options = ['sort' => ['date_debut' => 1]]; // tri par date_debut asc
        $query = new MongoDB\Driver\Query($filter, $options);
        $namespace = 'esportifyMongoDB.evenements';
        $cursor = $manager->executeQuery($namespace, $query);
        foreach ($cursor as $doc) {
            $events[] = $doc;
        }
    } catch (MongoDB\Driver\Exception\Exception $e) {
        $events = [];
        error_log('MongoDB Driver error: ' . $e->getMessage());
    } catch (Exception $e) {
        $events = [];
        error_log('General error while reading MongoDB: ' . $e->getMessage());
    }
} else {
    session_destroy();
    header('Location: menu.php');
    exit;
}

if (!isset($message)) { $message = ''; }
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

            <?php if ($user_logged && in_array($user_role, $allowed_roles, true)): ?>
            <a href="insert.php" class="btn btn-success btn-lg"><span class="bi-plus"></span> Créer un événement</a>
            <?php endif; ?>

            <br>
            <br>

            <div class="row">
                <?php if (!empty($message)): ?>
                    <div class="col-12"><div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div></div>
                <?php endif; ?>
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Titre</th>
                                <th>Date de début</th>
                                <th>Date de fin</th>
                                <th>Joueurs</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php
                        // Assure que $events est défini et est un tableau
                        if (!isset($events) || !is_array($events)) {
                            $events = [];
                        }

                        // Helper pour formater les dates (supporte BSON UTCDateTime si présent)
                        $formatDate = function ($val) {
                            if (class_exists('MongoDB\\BSON\\UTCDateTime') && $val instanceof MongoDB\BSON\UTCDateTime) {
                                $dt = $val->toDateTime();
                                // afficher en timezone serveur (ou config)
                                $dt->setTimezone(new DateTimeZone(date_default_timezone_get()));
                                return $dt->format('d/m/Y H:i');
                            }
                            if (is_string($val) && strtotime($val) !== false) {
                                return date('d/m/Y H:i', strtotime($val));
                            }
                            if ($val instanceof DateTime) {
                                $val->setTimezone(new DateTimeZone(date_default_timezone_get()));
                                return $val->format('d/m/Y H:i');
                            }
                            return (string) $val;
                        };

                        if (count($events) > 0) {
                            foreach ($events as $ev) {
                                // si $ev est un document MongoDB (objet), on lève en tableau
                                if (is_object($ev)) {
                                    $ev = (array) $ev;
                                }

                                // Prépare les données de la ligne

                                $titre = $ev['titre'] ?? '';
                                $start = isset($ev['date_debut']) ? $formatDate($ev['date_debut']) : '';
                                $end = isset($ev['date_fin']) ? $formatDate($ev['date_fin']) : '';
                                $nb = $ev['nb_joueurs'] ?? '';

                                echo '<tr>';
                                echo '<td>' . htmlspecialchars($titre) . '</td>';
                                echo '<td>' . htmlspecialchars($start) . '</td>';
                                echo '<td>' . htmlspecialchars($end) . '</td>';
                                echo '<td>' . htmlspecialchars($nb) . '</td>';
                                                    echo '<td width="340">';
                                                    $eid = (is_object($ev['_id']) && method_exists($ev['_id'], '__toString')) ? (string)$ev['_id'] : ($ev['_id'] ?? $ev['event_id'] ?? '');
                                                    echo '<a class="btn btn-secondary" href="view.php?id=' . rawurlencode($eid) . '"><span class="bi-eye"></span> Voir</a>';
                                                    echo ' ';
                                                    // Montrer le bouton Modifier seulement aux rôles autorisés (organisateur/admin) et utilisateur connecté
                                                    if ($user_logged && in_array($user_role, $allowed_roles, true)) {
                                                        echo '<a class="btn btn-primary" href="update.php?id=' . rawurlencode($eid) . '"><span class="bi-pencil"></span> Modifier</a>';
                                                        echo ' ';
                                                    }
                                                    echo ' ';
                                                    // détermine si l'utilisateur peut supprimer : admin ou organisateur
                                                    $role = strtolower($_SESSION['role'] ?? '');
                                                    $username = $_SESSION['username'] ?? '';
                                                    $user_id = $_SESSION['user_id'] ?? '';
                                                    // normalisation souple de champs organisateur
                                                    $normSingle = function($v) {
                                                        if (is_object($v) && method_exists($v, '__toString')) return (string)$v;
                                                        if (is_array($v)) {
                                                            if (isset($v['$oid'])) return (string)$v['$oid'];
                                                            if (isset($v['oid'])) return (string)$v['oid'];
                                                            if (isset($v['username'])) return (string)$v['username'];
                                                            if (isset($v['name'])) return (string)$v['name'];
                                                            return '';
                                                        }
                                                        if (is_scalar($v)) return (string)$v;
                                                        return '';
                                                    };

                                                    $orgName = '';
                                                    foreach (['organisateur','organizer','organisateur_name','organizer_name','organisateur_username','creator','created_by','owner'] as $f) {
                                                        if (isset($ev[$f]) && $ev[$f] !== null) { $orgName = $normSingle($ev[$f]); if ($orgName !== '') break; }
                                                    }

                                                    $orgId = '';
                                                    foreach (['organisateur_id','organizer_id','user_id','creator_id','created_by_id','owner_id','_id'] as $f) {
                                                        if (isset($ev[$f]) && $ev[$f] !== null) { $orgId = $normSingle($ev[$f]); if ($orgId !== '') break; }
                                                    }

                                                    $canDelete = false;
                                                    if (in_array($role, ['administrateur','organisateur'], true)) { $canDelete = true; }
                                                    if ($orgName !== '' && strtolower($orgName) === strtolower($username)) { $canDelete = true; }
                                                    if ($orgId !== '' && (string)$orgId === (string)$user_id) { $canDelete = true; }
                                                    if ($canDelete) {
                                                        echo '<form method="post" style="display:inline;" onsubmit="return confirm(\'Confirmer la suppression de cet événement ?\');">';
                                                        echo '<input type="hidden" name="action" value="delete_event">';
                                                        echo '<input type="hidden" name="event_id" value="' . htmlspecialchars($eid) . '">';
                                                        echo '<button type="submit" class="btn btn-danger"><span class="bi-x"></span> Supprimer</button>';
                                                        echo '</form>';
                                                    }
                                                    echo '</td>';
                                echo '</tr>';
                            }
                        } else {
                            echo '<tr><td colspan="5">Aucun événement trouvé.</td></tr>';
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