<?php
session_start();

$host = '127.0.0.1';
$db = 'esportify_sql';
$dbuser = 'root';
$dbpass = '';

$mongodbAvailable = class_exists('MongoDB\\Driver\\Manager');

if ($mongodbAvailable) {
    try {
        // Utilisation du driver bas-niveau MongoDB\Driver\Manager
        $manager = new MongoDB\Driver\Manager('mongodb://127.0.0.1:27017');
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
        <br>
        <br>

        <section class="row" id="contenu">


            <div class="col-6">
<?php
$ev = null;
if (!empty($events) && isset($events[0])) {
    $ev = (array)$events[0];
}
$formatDate = function ($val) {
    if (class_exists('MongoDB\\BSON\\UTCDateTime') && $val instanceof MongoDB\BSON\UTCDateTime) {
        return $val->toDateTime()->format('d/m/Y H:i');
    }
    if (is_string($val) && strtotime($val) !== false) {
        return date('d/m/Y H:i', strtotime($val));
    }
    if ($val instanceof DateTime) {
        return $val->format('d/m/Y H:i');
    }
    return (string) $val;
};
?>
                <div class="view-event">
<?php
// Résolution du nom de l'organisateur lorsqu'un identifiant est présent
$organizer_name = '';
if ($ev) {
    // champs possibles contenant la référence à l'organisateur
    $orgRef = $ev['organisateur_id'] ?? $ev['organizer_id'] ?? $ev['organisateur'] ?? $ev['organizer'] ?? '';

    // Si orgRef semble être un id numérique, tenter lookup MySQL dans la table `utilisateurs`
    if ($orgRef !== '') {
        // Tentative MySQL si possible
        $mysqli = @new mysqli($host, $dbuser, $dbpass, $db);
        if ($mysqli && !$mysqli->connect_errno) {
            if (ctype_digit((string)$orgRef)) {
                $stmt = $mysqli->prepare('SELECT username FROM utilisateurs WHERE id = ? LIMIT 1');
                if ($stmt) {
                    $stmt->bind_param('i', $orgRef);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($row = $res->fetch_assoc()) {
                        $organizer_name = $row['username'];
                    }
                    $stmt->close();
                }
            } else {
                // Si orgRef n'est pas numérique, il se peut que ce soit déjà un nom
                // ou une valeur string référente. On essaye une recherche par username au cas où.
                $stmt = $mysqli->prepare('SELECT username FROM utilisateurs WHERE username = ? LIMIT 1');
                if ($stmt) {
                    $stmt->bind_param('s', $orgRef);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($row = $res->fetch_assoc()) {
                        $organizer_name = $row['username'];
                    }
                    $stmt->close();
                }
            }
        }

        
    }
}
?>
<?php if ($ev): ?>
                    <p><strong>Titre :</strong> <?php echo htmlspecialchars($ev['titre'] ?? ''); ?></p>
                    <p><strong>Description :</strong> <?php echo nl2br(htmlspecialchars($ev['description'] ?? '')); ?></p>
                    <p><strong>Date de début :</strong> <?php echo htmlspecialchars(isset($ev['date_debut']) ? $formatDate($ev['date_debut']) : ''); ?></p>
                    <p><strong>Date de fin :</strong> <?php echo htmlspecialchars(isset($ev['date_fin']) ? $formatDate($ev['date_fin']) : ''); ?></p>
                    <p><strong>Nombre de joueurs :</strong> <?php echo htmlspecialchars($ev['nb_joueurs'] ?? $ev['nb_joueur'] ?? ''); ?></p>
                    <p><strong>Organisateur :</strong> <?php echo htmlspecialchars($organizer_name ?: ($ev['organisateur'] ?? $ev['organizer'] ?? '')); ?></p>
<?php else: ?>
                    <p>Événement introuvable ou identifiant manquant.</p>
<?php if (!empty($id)): ?>
                    <p><strong>ID reçu :</strong> <?php echo htmlspecialchars($id); ?></p>
                    <p><strong>Filtres testés :</strong> <?php echo htmlspecialchars(json_encode($triedFilters)); ?></p>
<?php endif; ?>
<?php endif; ?>
                </div>
                <br>
                <div class="form-actions">
                    <a class="btn btn-primary" href="evenements.php"><span class="bi-arrow-left"></span> Retour</a>
                </div>
            </div>

            <div class="col-6">
<?php
// Choisit une image de fallback raisonnable : si default.png n'existe pas, prend la première image trouvée
$img_src = 'images/tournois/default.png';
if (!file_exists(__DIR__ . '/images/tournois/default.png')) {
    $files = glob(__DIR__ . '/images/tournois/*.{png,jpg,jpeg,gif,webp}', GLOB_BRACE);
    if (!empty($files)) {
        // glob retourne des chemins absolus ; convertit en chemin relatif pour l'attribut src
        $first = $files[0];
        $img_src = str_replace(str_replace('\\', '/', __DIR__), '', str_replace('\\', '/', $first));
        $img_src = ltrim($img_src, '/\\');
    } else {
        $img_src = '';
    }
}
if ($ev) {
    $possible = ['image','image_path','image_name','photo','img','imageUrl','image_url','picture','img_name'];
    $found = '';
    foreach ($possible as $k) {
        if (!empty($ev[$k])) { $found = $ev[$k]; break; }
    }
    if ($found) {
        if (preg_match('#^(https?://)#i', $found)) {
            $img_src = $found;
        } else {
            $img_src = 'images/tournois/' . ltrim($found, '/');
        }
    }
}
?>
                <?php if (!empty($img_src)): ?>
                <img src="<?php echo htmlspecialchars($img_src); ?>" class="img-fluid" alt="<?php echo htmlspecialchars($ev['titre'] ?? 'Événement'); ?>">
                <?php else: ?>
                <div class="img-fluid" style="background:#eee;height:200px;display:flex;align-items:center;justify-content:center;">Pas d'image</div>
                <?php endif; ?>
            </div>
            
        </section>
    </div>
</body>
</html>