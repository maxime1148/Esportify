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

// Vérification session / rôle
$allowed_roles = ['organisateur', 'administrateur', 'admin', 'administrator'];
$user_role = strtolower(trim($_SESSION['role'] ?? ''));
$user_logged = !empty($_SESSION['username']);

$mongodbAvailable = class_exists('MongoDB\\Driver\\Manager');

if (!$mongodbAvailable) {
    session_destroy();
    header('Location: menu.php');
    exit;
}

// Helper to convert MongoDB UTCDateTime to datetime-local input value
$toInputDate = function($val) {
    if (class_exists('MongoDB\\BSON\\UTCDateTime') && $val instanceof MongoDB\BSON\UTCDateTime) {
        $dt = $val->toDateTime();
        $dt->setTimezone(new DateTimeZone(date_default_timezone_get()));
        return $dt->format('Y-m-d\\TH:i');
    }
    if (is_string($val) && strtotime($val) !== false) {
        return date('Y-m-d\\TH:i', strtotime($val));
    }
    return '';
};

// Récupère la liste d'organisateurs depuis MySQL pour le select
$organizers = [];
if ($mysqli) {
    $q = "SELECT id, username, role FROM utilisateurs ORDER BY username ASC";
    $res = $mysqli->query($q);
    if ($res) {
        while ($r = $res->fetch_assoc()) { $organizers[] = $r; }
        $res->free();
    }
}

// Load event by id (GET)
$event = null;
$id = isset($_GET['id']) ? trim($_GET['id']) : '';
try {
    $mgr = new MongoDB\Driver\Manager('mongodb+srv://maximecassignol11_db_user:pNjiHPWGMi3DfbV3@esportifymongodb.jymrecd.mongodb.net/');
    $namespace = 'esportifyMongoDB.evenements';
    if ($id !== '') {
        // try ObjectId
        if (preg_match('/^[a-f\\d]{24}$/i', $id)) {
            try {
                $filter = ['_id' => new MongoDB\BSON\ObjectId($id)];
                $q = new MongoDB\Driver\Query($filter, ['limit' => 1]);
                $cursor = $mgr->executeQuery($namespace, $q);
                foreach ($cursor as $d) { $event = $d; break; }
            } catch (Exception $e) { /* ignore */ }
        }
        // try string _id or event_id
        if ($event === null) {
            $filter = ['_id' => $id];
            $q = new MongoDB\Driver\Query($filter, ['limit' => 1]);
            $cursor = $mgr->executeQuery($namespace, $q);
            foreach ($cursor as $d) { $event = $d; break; }
        }
        if ($event === null) {
            $filter = ['event_id' => $id];
            $q = new MongoDB\Driver\Query($filter, ['limit' => 1]);
            $cursor = $mgr->executeQuery($namespace, $q);
            foreach ($cursor as $d) { $event = $d; break; }
        }
    }
} catch (Exception $e) {
    error_log('MongoDB load error: ' . $e->getMessage());
}

// If POST to update
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_event') {
    // authorization
    if (!$user_logged || !in_array($user_role, $allowed_roles, true)) {
        $errors[] = 'Accès refusé.';
    }

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $start = trim($_POST['start_date'] ?? '');
    $end = trim($_POST['end_date'] ?? '');
    $nb = intval($_POST['number_of_players'] ?? 0);
    $organizer_id = $_POST['organizer_id'] ?? '';
    $docUpdate = [];
    if ($title === '') $errors[] = 'Le titre est requis.';
    if ($start === '') $errors[] = 'La date de début est requise.';
    if ($end === '') $errors[] = 'La date de fin est requise.';

    if (empty($errors)) {
        // build update
        $toUTC = function($s) {
            if (!$s) return null;
            $serverTz = new DateTimeZone(date_default_timezone_get());
            $dt = DateTime::createFromFormat('Y-m-d\TH:i', $s, $serverTz);
            if (!$dt) {
                try {
                    $dt = new DateTime($s, $serverTz);
                } catch (Exception $e) {
                    return null;
                }
            }
            $dt->setTimezone(new DateTimeZone('UTC'));
            return new MongoDB\BSON\UTCDateTime($dt->getTimestamp() * 1000);
        };
        $docUpdate['titre'] = $title;
        $docUpdate['description'] = $description;
        $docUpdate['date_debut'] = $toUTC($start);
        $docUpdate['date_fin'] = $toUTC($end);
        $docUpdate['nb_joueurs'] = $nb;

        if (!empty($organizer_id) && $mysqli && ctype_digit((string)$organizer_id)) {
            $stmt = $mysqli->prepare('SELECT id, username FROM utilisateurs WHERE id = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('i', $organizer_id);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    $docUpdate['organisateur'] = $row['username'];
                    $docUpdate['organisateur_id'] = (string)$row['id'];
                }
                $stmt->close();
            }
        }

        // image handling (optional replace)
        if (!empty($_FILES['image']['tmp_name'])) {
            $allowedExt = ['jpg','jpeg','png','webp','gif'];
            $maxBytes = 5 * 1024 * 1024;
            $tmp = $_FILES['image']['tmp_name'];
            $origName = basename($_FILES['image']['name']);
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExt, true)) {
                $errors[] = 'Type d\'image non supporté.';
            } elseif ($_FILES['image']['size'] > $maxBytes) {
                $errors[] = 'Image trop volumineuse (max 5MB).';
            } else {
                $targetDir = __DIR__ . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'tournois' . DIRECTORY_SEPARATOR;
                if (!is_dir($targetDir)) {@mkdir($targetDir, 0755, true);}
                // Preserve original filename (minimal sanitation)
                $origNameSafe = basename($origName);
                $origNameSafe = preg_replace('/[^A-Za-z0-9._-]/', '_', $origNameSafe);
                $newName = $origNameSafe;
                $targetPath = $targetDir . $newName;
                if (@move_uploaded_file($tmp, $targetPath)) {
                    // store only filename; display code will prepend the folder
                    $docUpdate['image'] = $newName;
                } else {
                    $errors[] = 'Échec du déplacement de l\'image.';
                }
            }
        }

        if (empty($errors)) {
            try {
                $mgr = new MongoDB\Driver\Manager('mongodb://127.0.0.1:27017');
                $bulk = new MongoDB\Driver\BulkWrite();
                // determine filter from posted id
                $post_id = $_POST['event_id'] ?? '';
                if (preg_match('/^[a-f\\d]{24}$/i', $post_id)) {
                    $filter = ['_id' => new MongoDB\BSON\ObjectId($post_id)];
                } else {
                    $filter = ['_id' => $post_id];
                }
                $bulk->update($filter, ['$set' => $docUpdate], ['multi' => false, 'upsert' => false]);
                $res = $mgr->executeBulkWrite('esportifyMongoDB.evenements', $bulk);
                header('Location: evenements.php');
                exit;
            } catch (Exception $e) {
                $errors[] = 'Erreur lors de la mise à jour : ' . $e->getMessage();
            }
        }
    }
}

// Prepare values for the form: if $event found, convert to array and extract
$form = [
    'titre' => '',
    'description' => '',
    'date_debut' => '',
    'date_fin' => '',
    'nb_joueurs' => '',
    'organisateur_id' => '',
    'image' => '',
    'id' => $id,
];
if ($event !== null) {
    if (is_object($event)) $event = (array)$event;
    $form['titre'] = $event['titre'] ?? '';
    $form['description'] = $event['description'] ?? '';
    $form['date_debut'] = $toInputDate($event['date_debut'] ?? '');
    $form['date_fin'] = $toInputDate($event['date_fin'] ?? '');
    $form['nb_joueurs'] = $event['nb_joueurs'] ?? '';
    // creator info
    if (isset($event['created_by'])) $form['created_by'] = $event['created_by'];
    if (isset($event['created_by_id'])) $form['created_by_id'] = (string)$event['created_by_id'];
    // try organiser id
    if (isset($event['organisateur_id'])) {
        $form['organisateur_id'] = (string)$event['organisateur_id'];
    } elseif (isset($event['organisateur'])) {
        $form['organisateur_display'] = (string)$event['organisateur'];
    }
    if (isset($event['image'])) $form['image'] = $event['image'];
    // set id to object id string if present
    if (isset($event['_id'])) {
        if (is_object($event['_id']) && method_exists($event['_id'], '__toString')) $form['id'] = (string)$event['_id'];
        elseif (is_array($event['_id']) && isset($event['_id']['$oid'])) $form['id'] = $event['_id']['$oid'];
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
                            <h1><em>Esportify - Modifier un événement</em></h1>
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


                <?php if ($event === null): ?>
                    <div class="alert alert-warning">Événement introuvable. <a href="evenements.php" class="btn btn-sm btn-primary ms-2">Retour</a></div>
                <?php elseif (!$user_logged || !in_array($user_role, $allowed_roles, true)): ?>
                    <div class="alert alert-warning">Accès réservé : vous devez être connecté en tant qu'<strong>organisateur</strong> ou <strong>administrateur</strong> pour modifier un événement. <a href="evenements.php" class="btn btn-sm btn-primary ms-2">Retour</a></div>
                <?php else: ?>
                <form method="post" enctype="multipart/form-data">
                    <?php if (!empty(($errors ?? []))) : ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach (($errors ?? []) as $err) : ?>
                                    <li><?php echo htmlspecialchars($err); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <input type="hidden" name="action" value="update_event">
                    <input type="hidden" name="event_id" value="<?php echo htmlspecialchars($form['id'] ?? ''); ?>">
                    <div class="form-group">
                        <label for="title">Titre : </label>
                        <input type="text" class="form-control" name="title" id="title" placeholder="Titre" value="<?php echo htmlspecialchars($_POST['title'] ?? $form['titre']); ?>">
                        <?php if (!empty($form['created_by'])): ?>
                            <div class="form-text">Créé par : <strong><?php echo htmlspecialchars($form['created_by']); ?></strong><?php if (!empty($form['created_by_id'])) echo ' (id: ' . htmlspecialchars($form['created_by_id']) . ')'; ?></div>
                        <?php endif; ?>
                        <br>
                        <label for="description">Description : </label>
                        <input type="text" class="form-control" name="description" id="description" placeholder="Description" value="<?php echo htmlspecialchars($_POST['description'] ?? $form['description']); ?>">
                        <br>
                        <label for="start_date">Date de début : </label>
                        <input type="datetime-local" class="form-control" name="start_date" id="start_date" placeholder="Date de début" value="<?php echo htmlspecialchars($_POST['start_date'] ?? $form['date_debut']); ?>">
                        <br>
                        <label for="end_date">Date de fin : </label>
                        <input type="datetime-local" class="form-control" name="end_date" id="end_date" placeholder="Date de fin" value="<?php echo htmlspecialchars($_POST['end_date'] ?? $form['date_fin']); ?>">
                        <br>
                        <label for="number_of_players">Nombre de joueurs : </label>
                        <input type="number" class="form-control" name="number_of_players" id="number_of_players" placeholder="Nombre de joueurs" value="<?php echo htmlspecialchars($_POST['number_of_players'] ?? $form['nb_joueurs']); ?>">

                        <br>
                        <label for="organizer_id">Organisateur : </label>
                        <?php
                            // Determine which organizer should be pre-selected:
                            // Priority: POST (form re-submit) -> explicit organisateur_id from document -> created_by_id (if numeric) -> none
                            $preselected_org_id = null;
                            $preselected_org_name = null;
                            if (isset($_POST['organizer_id']) && $_POST['organizer_id'] !== '') {
                                $preselected_org_id = $_POST['organizer_id'];
                            } elseif (!empty($form['organisateur_id'])) {
                                $preselected_org_id = $form['organisateur_id'];
                            } elseif (!empty($form['created_by_id']) && ctype_digit((string)$form['created_by_id'])) {
                                $preselected_org_id = (string)$form['created_by_id'];
                            } elseif (!empty($form['created_by'])) {
                                $preselected_org_name = $form['created_by'];
                            }
                        ?>
                        <select class="form-control" name="organizer_id" id="organizer_id">
                            <option value="">-- Aucun --</option>
                            <?php if ($preselected_org_id === null && $preselected_org_name !== null): ?>
                                <!-- show creator name as a disabled selected option when we don't have an id to select -->
                                <option value="" selected disabled>Créé par : <?php echo htmlspecialchars($preselected_org_name); ?></option>
                            <?php endif; ?>
                            <?php foreach ($organizers as $org):
                                $orgIdStr = (string)$org['id'];
                                $sel = ($preselected_org_id !== null && $preselected_org_id === $orgIdStr) || (isset($_POST['organizer_id']) && $_POST['organizer_id'] == $org['id']) ? 'selected' : '';
                            ?>
                                <option value="<?php echo htmlspecialchars($org['id']); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($org['username'] . (!empty($org['role']) ? ' (' . $org['role'] . ')' : '')); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($form['organisateur_display']) && empty($form['organisateur_id']) && empty($preselected_org_name)): ?><div class="form-text">Organisateur actuel : <?php echo htmlspecialchars($form['organisateur_display']); ?></div><?php endif; ?>

                        <br>
                        <label for="image">Image (optionnelle) : </label>
                        <?php if (!empty($form['image'])): ?>
                            <div class="mb-2"><img src="<?php echo htmlspecialchars('images/tournois/' . $form['image']); ?>" alt="image" style="max-width:200px;"></div>
                            <div class="form-text">Nom du fichier : <strong><?php echo htmlspecialchars($form['image']); ?></strong></div>
                        <?php endif; ?>
                        <input type="file" class="form-control" name="image" id="image" accept="image/*">
                        <small class="form-text text-muted">Types acceptés : jpg, jpeg, png, webp, gif. Taille max 5MB.</small>

                    </div>

                    <div class="row mt-3">
                        <div class="col-6">
                            <button type="submit" class="btn btn-success"><span class="bi-plus-circle"></span> Modifier</button>
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





