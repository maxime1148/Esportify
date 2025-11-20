<?php
session_start();
$host = 'erxv1bzckceve5lh.cbetxkdyhwsb.us-east-1.rds.amazonaws.com';
$db = 'zghm67erntjc1fv1';
$dbuser = '	hxwvxrhk7b1h4vdl';
$dbpass = 'enpr39qjhrz8ojjd';

$mysqli = new mysqli($host, $dbuser, $dbpass, $db);
$db_error = '';
if ($mysqli->connect_errno) {
    $db_error = 'Erreur de connexion à la base de données: ' . $mysqli->connect_error;
}

$now = date('Y-m-d H:i:s');
$events = [];
$currentEvents = [];
$upcomingEvents = [];

// helper: convert various date formats (MongoDB UTCDateTime, string, DateTime) to PHP DateTime or null
$toDateTime = function ($val) {
    if (class_exists('MongoDB\\BSON\\UTCDateTime') && $val instanceof MongoDB\BSON\UTCDateTime) {
        return $val->toDateTime();
    }
    if ($val instanceof DateTime) {
        return $val;
    }
    if (is_string($val) && strtotime($val) !== false) {
        try {
            return new DateTime($val);
        } catch (Exception $e) {
            return null;
        }
    }
    return null;
};

// helper pour formater les dates (supporte BSON UTCDateTime si présent)
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
$mongodbAvailable = class_exists('MongoDB\\Driver\\Manager');

if ($mongodbAvailable) {
    try {
        // Utilisation du driver bas-niveau MongoDB\Driver\Manager
        $manager = new MongoDB\Driver\Manager('mongodb://127.0.0.1:27017');
        $filter = [];
        $options = ['sort' => ['date_debut' => 1]]; // tri par date_debut asc
        $query = new MongoDB\Driver\Query($filter, $options);
        $namespace = 'esportifyMongoDB.evenements';
        $cursor = $manager->executeQuery($namespace, $query);
        foreach ($cursor as $doc) {
            $events[] = $doc;
        }
        // Séparer les événements en "en cours" et "à venir" selon les dates
        $nowDateTime = new DateTime('now');
        if (count($events) > 0) {
            foreach ($events as $eDoc) {
                $eArr = is_object($eDoc) ? (array) $eDoc : $eDoc;
                $startDT = isset($eArr['date_debut']) ? $toDateTime($eArr['date_debut']) : null;
                $endDT = isset($eArr['date_fin']) ? $toDateTime($eArr['date_fin']) : null;

                if ($startDT instanceof DateTime && $endDT instanceof DateTime) {
                    if ($startDT->getTimestamp() <= $nowDateTime->getTimestamp() && $nowDateTime->getTimestamp() <= $endDT->getTimestamp()) {
                        $currentEvents[] = $eDoc;
                        continue;
                    }
                    if ($startDT->getTimestamp() > $nowDateTime->getTimestamp()) {
                        $upcomingEvents[] = $eDoc;
                        continue;
                    }
                } elseif ($startDT instanceof DateTime) {
                    if ($startDT->getTimestamp() <= $nowDateTime->getTimestamp()) {
                        $currentEvents[] = $eDoc;
                        continue;
                    }
                    $upcomingEvents[] = $eDoc;
                    continue;
                } else {
                    // Pas de date valide : considérer comme à venir
                    $upcomingEvents[] = $eDoc;
                    continue;
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
    <title>Esportify - Accueil</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Holtwood+One+SC&family=Lato:ital,wght@0,100;0,300;0,400;0,700;0,900;1,100;1,300;1,400;1,700;1,900&family=Limelight&family=Lobster&display=swap" rel="stylesheet">

    <script src="https://code.jquery.com/jquery-3.6.0.min.js" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/stylesAccueil.css">
</head>

    <body>
        <div class="container">
            <section id="menu">
                <div class="menu-bar">
                    <div class="row">
                            <div class="menu-title">
                                <h1><em>Esportify - Accueil</em></h1>
                            </div>
                            <div class="menu-logo">
                                <img src="images/logo-jeu.png" alt="Icône" class="menu-icon">
                                <span class="menu-text">Esportify</span>
                            </div>
                    </div>
                    <br>
                    <div class="row">
                        <nav class="row" style="position:relative;">
                            <div class="col-12 menu-item">
                                <a href="menu.php" class="home-button">
                                    <span class="menu-home-icon"><i class="bi bi-house-door-fill"></i></span> Menu
                                </a>
                                <span class="menu-icon-mobile">
                                    <a href="mmenu.php" class="menu-icon-mobile">&#8962;</a>
                                </span>
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

            <section id="contenu">
                <!-- Section "Qui sommes-nous" -->
                <div class="row about">
                    <h2>Qui sommes nous</h2>
                    <div class="about-content">
                        <p>
                            Esportify est une startup innovante spécialisée dans le domaine du e-sport, fondée le 17 mars 2021 en France.
                            Elle organise divers événements dédiés aux compétitions de jeux vidéo.
                        </p>
                    </div>
                </div>

                <!-- Section "Galerie d'images" -->
                <div class="row gallery">
                    <h2>Galerie d'images</h2>
                    <div class="gallery-content">
                        <button class="arrow left-arrow">&#9664;</button> <!-- Flèche gauche -->
                        <img src="images/esport3.webp" alt="Esports Academy" class="gallery-image" id="gallery-image">
                        <button class="arrow right-arrow">&#9654;</button> <!-- Flèche droite -->
                    </div>
                </div>

                <!-- Section "événements en cours" et "événements à venir" -->
                <div class="row">
                    <p class="current-events"><span class="events-icon">&#128197;</span> Événements en cours :</p>
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Titre</th>
                                    <th>Date de début</th>
                                    <th>Date de fin</th>
                                    <th>Joueurs</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Afficher les événements en cours
                                if (!isset($currentEvents) || !is_array($currentEvents)) {
                                    $currentEvents = [];
                                }

                                if (count($currentEvents) > 0) {
                                    foreach ($currentEvents as $ev) {
                                        if (is_object($ev)) {
                                            $ev = (array) $ev;
                                        }

                                        $event_id = $ev['event_id'] ?? ($ev['_id'] ?? '');
                                        if (is_object($event_id) && method_exists($event_id, '__toString')) {
                                            $event_id = (string) $event_id;
                                        }

                                        $titre = $ev['titre'] ?? '';
                                        $start = isset($ev['date_debut']) ? $formatDate($ev['date_debut']) : '';
                                        $end = isset($ev['date_fin']) ? $formatDate($ev['date_fin']) : '';
                                        $nb = $ev['nb_joueurs'] ?? '';

                                        echo '<tr>';
                                        echo '<td>' . htmlspecialchars($titre) . '</td>';
                                        echo '<td>' . htmlspecialchars($start) . '</td>';
                                        echo '<td>' . htmlspecialchars($end) . '</td>';
                                        echo '<td>' . htmlspecialchars($nb) . '</td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="6">Aucun événement en cours.</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                 
                
                </div>
                <div class="row">
                        <p class="upcoming-events"><span class="events-icon">&#128197;</span> Événements à venir :</p>
                        <table class="table table-bordered table-striped">
                            <thead>
                            <tr>
                                <th>Titre</th>
                                <th>Date de début</th>
                                <th>Date de fin</th>
                                <th>Joueurs</th>
                            </tr>
                        </thead>
                            <tbody>
                               <?php
                                // Afficher les événements à venir
                                if (!isset($upcomingEvents) || !is_array($upcomingEvents)) {
                                    $upcomingEvents = [];
                                }

                                if (count($upcomingEvents) > 0) {
                                    foreach ($upcomingEvents as $ev) {
                                        if (is_object($ev)) {
                                            $ev = (array) $ev;
                                        }

                                        $event_id = $ev['event_id'] ?? ($ev['_id'] ?? '');
                                        if (is_object($event_id) && method_exists($event_id, '__toString')) {
                                            $event_id = (string) $event_id;
                                        }

                                        $titre = $ev['titre'] ?? '';
                                        $start = isset($ev['date_debut']) ? $formatDate($ev['date_debut']) : '';
                                        $end = isset($ev['date_fin']) ? $formatDate($ev['date_fin']) : '';
                                        $nb = $ev['nb_joueurs'] ?? '';

                                        echo '<tr>';
                                        echo '<td>' . htmlspecialchars($titre) . '</td>';
                                        echo '<td>' . htmlspecialchars($start) . '</td>';
                                        echo '<td>' . htmlspecialchars($end) . '</td>';
                                        echo '<td>' . htmlspecialchars($nb) . '</td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="6">Aucun événement à venir.</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                      
                </div>
            </section>

           

        
            <script>
                const images = [
                    "images/esport3.webp",
                    "images/esport2.jpg",
                    "images/esport1.jpg"
                ];
                let currentIndex = 0;

                const galleryImage = document.getElementById("gallery-image");
                const leftArrow = document.querySelector(".left-arrow");
                const rightArrow = document.querySelector(".right-arrow");

                function showImage(index) {
                    galleryImage.classList.add("fade-out");
                    setTimeout(() => {
                        galleryImage.src = images[index];
                        galleryImage.classList.remove("fade-out");
                        galleryImage.classList.add("fade-in");
                        setTimeout(() => {
                            galleryImage.classList.remove("fade-in");
                        }, 400);
                    }, 400);
                }

                leftArrow.addEventListener("click", () => {
                    currentIndex = (currentIndex - 1 + images.length) % images.length;
                    showImage(currentIndex);
                });

                rightArrow.addEventListener("click", () => {
                    currentIndex = (currentIndex + 1) % images.length;
                    showImage(currentIndex);
                });
            </script>
        </div>
    </body>
</html>