<?php
session_start();

$host = 'erxv1bzckceve5lh.cbetxkdyhwsb.us-east-1.rds.amazonaws.com';
$db = 'zghm67erntjc1fv1';
$dbuser = 'hxwvxrhk7b1h4vdl';
$dbpass = 'enpr39qjhrz8ojjd';

// Connexion MySQL pour récupérer les organisateurs
$mysqli = new mysqli($host, $dbuser, $dbpass, $db);
if ($mysqli->connect_errno) {
    error_log('MySQL connection error: ' . $mysqli->connect_error);
}

// Vérification session / rôle (utilisateur connecté ? rôle?)
$allowed_roles = ['organisateur', 'administrateur', 'admin', 'administrator'];
$user_role = strtolower(trim($_SESSION['role'] ?? ''));
$user_logged = !empty($_SESSION['username']);

$mongodbAvailable = class_exists('MongoDB\\Driver\\Manager');

if ($mongodbAvailable) {
    try {
        // Utilisation du driver bas-niveau MongoDB\Driver\Manager
        $manager = new MongoDB\Driver\Manager('mongodb+srv://maximecassignol11_db_user:pNjiHPWGMi3DfbV3@esportifymongodb.jymrecd.mongodb.net/?appName=esportifyMongoDB');
        $namespace = 'esportifyMongoDB.evenements';
        $events = [];

        // Récupère l'id depuis la querystring et interroge MongoDB pour ce document
        $id = isset($_GET['id']) ? trim($_GET['id']) : null;
        $triedFilters = [];
        if ($id) {
            // 1) Si c'est un ObjectId hex (24 hex chars), essaye avec ObjectId
            if (preg_match('/^[a-f\d]{24}$/i', $id)) {
                try {
                    $filter = ['_id' => new MongoDB\BSON\ObjectId($id)];
                    $triedFilters[] = $filter;
                    $options = ['limit' => 1];
                    $query = new MongoDB\Driver\Query($filter, $options);
                    $cursor = $manager->executeQuery($namespace, $query);
                    foreach ($cursor as $doc) { $events[] = $doc; }
                } catch (Exception $e) {
                    error_log('ObjectId conversion failed: ' . $e->getMessage());
                }
            }

            // 2) Si pas trouvé, essaye avec _id comme string (cas où _id n'est pas ObjectId)
            if (empty($events)) {
                $filter = ['_id' => $id];
                $triedFilters[] = $filter;
                $options = ['limit' => 1];
                $query = new MongoDB\Driver\Query($filter, $options);
                $cursor = $manager->executeQuery($namespace, $query);
                foreach ($cursor as $doc) { $events[] = $doc; }
            }

            // 3) Si toujours pas trouvé, essaye avec event_id (certains documents peuvent stocker un champ event_id)
            if (empty($events)) {
                $filter = ['event_id' => $id];
                $triedFilters[] = $filter;
                $options = ['limit' => 1];
                $query = new MongoDB\Driver\Query($filter, $options);
                $cursor = $manager->executeQuery($namespace, $query);
                foreach ($cursor as $doc) { $events[] = $doc; }
            }

            // 4) Enfin, si l'id contient une ObjectId au format ObjectId("...") ou ObjectId('...'), on essaye d'extraire l'hex
            if (empty($events) && preg_match('/([a-f\d]{24})/i', $id, $m)) {
                try {
                    $oid = $m[1];
                    $filter = ['_id' => new MongoDB\BSON\ObjectId($oid)];
                    $triedFilters[] = $filter;
                    $options = ['limit' => 1];
                    $query = new MongoDB\Driver\Query($filter, $options);
                    $cursor = $manager->executeQuery($namespace, $query);
                    foreach ($cursor as $doc) { $events[] = $doc; }
                } catch (Exception $e) {
                    error_log('Extracted ObjectId conversion failed: ' . $e->getMessage());
                }
            }
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

// Handler de création d'événement (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_event') {
    // Autorisation côté serveur : seul un utilisateur connecté avec le rôle autorisé peut créer
    $errors = [];
    if (!$user_logged || !in_array($user_role, $allowed_roles, true)) {
        $errors[] = "Accès refusé : vous devez être connecté en tant qu'organisateur ou administrateur pour créer un événement.";
    }

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $start = trim($_POST['start_date'] ?? '');
    $end = trim($_POST['end_date'] ?? '');
    $nb = intval($_POST['number_of_players'] ?? 0);
    $organizer_id = $_POST['organizer_id'] ?? '';

    if ($title === '') $errors[] = 'Le titre est requis.';
    if ($description === '') $errors[] = 'La description est requise.';
    if ($start === '') $errors[] = 'La date de début est requise.';
    if ($end === '') $errors[] = 'La date de fin est requise.';
    if ($nb <= 0) $errors[] = 'Le nombre de joueurs doit être un entier positif.';

    if (empty($errors)) {
        try {
            $mgr = new MongoDB\Driver\Manager('mongodb+srv://maximecassignol11_db_user:pNjiHPWGMi3DfbV3@esportifymongodb.jymrecd.mongodb.net/?appName=esportifyMongoDB');
            $bulk = new MongoDB\Driver\BulkWrite();

            // convertit les dates en UTCDateTime si possible
            // Parse the browser `datetime-local` value as server-local time, then convert to UTC for storage.
            $toUTC = function($s) {
                if (!$s) return null;
                // Expected input like "YYYY-MM-DDTHH:MM" (datetime-local)
                $serverTz = new DateTimeZone(date_default_timezone_get());
                // try strict format first
                $dt = DateTime::createFromFormat('Y-m-d\TH:i', $s, $serverTz);
                if (!$dt) {
                    // fallback to more flexible parsing
                    try {
                        $dt = new DateTime($s, $serverTz);
                    } catch (Exception $e) {
                        return null;
                    }
                }
                // convert to UTC instant
                $dt->setTimezone(new DateTimeZone('UTC'));
                return new MongoDB\BSON\UTCDateTime($dt->getTimestamp() * 1000);
            };

            $doc = [
                'titre' => $title,
                'description' => $description,
                'date_debut' => $toUTC($start),
                'date_fin' => $toUTC($end),
                'nb_joueurs' => $nb,
                // enregistre le créateur (utilisateur connecté)
                'organisateur_id' => isset($_SESSION['username']) ? $_SESSION['username'] : null,
            ];

            // si un organisateur a été sélectionné, tenter de récupérer son username depuis MySQL
            if (!empty($organizer_id) && $mysqli && ctype_digit((string)$organizer_id)) {
                $stmt = $mysqli->prepare('SELECT id, username FROM utilisateurs WHERE id = ? LIMIT 1');
                if ($stmt) {
                    $stmt->bind_param('i', $organizer_id);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($row = $res->fetch_assoc()) {
                        $doc['organisateur'] = $row['username'];
                        // store as string id for cross-ref
                        $doc['organisateur_id'] = (string)$row['id'];
                    }
                    $stmt->close();
                }
            }

            // If an image was uploaded, handle it (basic move and record filename)
            if (!empty($_FILES['image']['tmp_name'])) {
                $uploadOk = true;
                $allowedExt = ['jpg','jpeg','png','webp','gif'];
                $maxBytes = 5 * 1024 * 1024; // 5MB
                $tmp = $_FILES['image']['tmp_name'];
                $origName = basename($_FILES['image']['name']);
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExt, true)) {
                    $errors[] = 'Type d\'image non supporté.';
                    $uploadOk = false;
                }
                if ($_FILES['image']['size'] > $maxBytes) {
                    $errors[] = 'Image trop volumineuse (max 5MB).';
                    $uploadOk = false;
                }
                if ($uploadOk) {
                    $targetDir = __DIR__ . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'tournois' . DIRECTORY_SEPARATOR;
                    if (!is_dir($targetDir)) {@mkdir($targetDir, 0755, true);}
                    // Use the original filename (preserve name). Minimal sanitation to avoid path traversal
                    $origNameSafe = basename($origName);
                    // replace any characters except letters, numbers, dot, underscore, hyphen
                    $origNameSafe = preg_replace('/[^A-Za-z0-9._-]/', '_', $origNameSafe);
                    $newName = $origNameSafe;
                    $targetPath = $targetDir . $newName;
                    if (@move_uploaded_file($tmp, $targetPath)) {
                        $doc['image'] = $newName;
                    } else {
                        $errors[] = 'Échec du déplacement de l\'image.';
                    }
                }
            }

            if (empty($errors)) {
                $bulk->insert($doc);
                $result = $mgr->executeBulkWrite('esportifyMongoDB.evenements', $bulk);
                header('Location: evenements.php');
                exit;
            }
        } catch (Exception $e) {
            $errors[] = 'Erreur lors de la création : ' . $e->getMessage();
        }
    }
}

// Récupère la liste d'organisateurs depuis MySQL pour le select (tous utilisateurs ou uniquement role organisateur)
$organizers = [];
if ($mysqli) {
    $q = "SELECT id, username, role FROM utilisateurs ORDER BY username ASC";
    $res = $mysqli->query($q);
    if ($res) {
        while ($r = $res->fetch_assoc()) { $organizers[] = $r; }
        $res->free();
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
                            <h1><em>Esportify - Créer un événement</em></h1>
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
        <br>
        

        <section class="row" id="contenu">


                <?php if (!$user_logged || !in_array($user_role, $allowed_roles, true)): ?>
                    <div class="alert alert-warning">Accès réservé : vous devez être connecté en tant qu'<strong>organisateur</strong> ou <strong>administrateur</strong> pour créer un événement. <a href="evenements.php" class="btn btn-sm btn-primary ms-2">Retour</a></div>
                <?php else: ?>
                <form method="post" enctype="multipart/form-data">
                    <?php if (!empty(${"errors"} ?? [])) : ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach (($errors ?? []) as $err) : ?>
                                    <li><?php echo htmlspecialchars($err); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <input type="hidden" name="action" value="create_event">
                    <div class="form-group">
                        <label for="title">Titre : </label>
                        <input type="text" class="form-control" name="title" id="title" placeholder="Titre" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
                        <br>
                        <label for="description">Description : </label>
                        <input type="text" class="form-control" name="description" id="description" placeholder="Description" value="<?php echo htmlspecialchars($_POST['description'] ?? ''); ?>">
                        <br>
                        <label for="start_date">Date de début : </label>
                        <input type="datetime-local" class="form-control" name="start_date" id="start_date" placeholder="Date de début" value="<?php echo htmlspecialchars($_POST['start_date'] ?? ''); ?>">
                        <br>
                        <label for="end_date">Date de fin : </label>
                        <input type="datetime-local" class="form-control" name="end_date" id="end_date" placeholder="Date de fin" value="<?php echo htmlspecialchars($_POST['end_date'] ?? ''); ?>">
                        <br>
                        <label for="number_of_players">Nombre de joueurs : </label>
                        <input type="number" class="form-control" name="number_of_players" id="number_of_players" placeholder="Nombre de joueurs" value="<?php echo htmlspecialchars($_POST['number_of_players'] ?? ''); ?>">

                        <br>
                        <label for="image">Image (optionnelle) : </label>
                        <input type="file" class="form-control" name="image" id="image" accept="image/*">
                        <small class="form-text text-muted">Types acceptés : jpg, jpeg, png, webp, gif. Taille max 5MB.</small>

                    </div>

                    <div class="row mt-3">
                        <div class="col-6">
                            <button type="submit" class="btn btn-success"><span class="bi-plus-circle"></span> Créer</button>
                            <a class="btn btn-primary" href="evenements.php"><span class="bi-arrow-left"></span> Retour</a>
                        </div>
                    </div>
                    <br>
                </form>
                <?php endif; ?>
           
            
        </section>
    </div>
</body>
</html>





