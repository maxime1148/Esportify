<?php
$host = '127.0.0.1';
$db = 'esportify_sql';
$dbuser = 'root';
$dbpass = '';

$mysqli = new mysqli($host, $dbuser, $dbpass, $db);
$db_error = '';
if ($mysqli->connect_errno) {
    $db_error = 'Erreur de connexion à la base de données: ' . $mysqli->connect_error;
}

$now = date('Y-m-d H:i:s');

// Événements en cours: date_debut <= now <= date_fin
$ongoing = null;
if (!$db_error) {
    $stmt = $mysqli->prepare("SELECT e.id, e.titre, e.description, e.date_debut, e.date_fin, e.nb_joueurs, u.username AS organisateur
        FROM evenements e
        LEFT JOIN utilisateurs u ON e.organisateur_id = u.id
        WHERE e.visibilite = 'visible' AND e.date_debut <= ? AND e.date_fin >= ?
        ORDER BY e.date_debut ASC");
    if ($stmt) {
        $stmt->bind_param('ss', $now, $now);
        $stmt->execute();
        $ongoing = $stmt->get_result();
        $stmt->close();
    }

    // Événements à venir: date_debut > now
    $upcoming = null;
    $stmt2 = $mysqli->prepare("SELECT e.id, e.titre, e.description, e.date_debut, e.date_fin, e.nb_joueurs, u.username AS organisateur
        FROM evenements e
        LEFT JOIN utilisateurs u ON e.organisateur_id = u.id
        WHERE e.visibilite = 'visible' AND e.date_debut > ?
        ORDER BY e.date_debut ASC");
    if ($stmt2) {
        $stmt2->bind_param('s', $now);
        $stmt2->execute();
        $upcoming = $stmt2->get_result();
        $stmt2->close();
    }
} else {
    $ongoing = null;
    $upcoming = null;
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
                <div class="row">
                    <nav class="col-12 menu-bar">
                        <div class="menu-item">
                            <a href="menu.php" class="menu-button">Menu</a>
                            <span class="menu-icon-mobile"><a href="menu.php" class="menu-icon-mobile">&#9776;</a></span> <!-- Icône pour smartphone -->
                        </div>
                        <div class="menu-title">
                            <h1><em>Accueil</em></h1>
                        </div>
                        <div class="menu-logo">
                            
                                <img src="images/logo-jeu.png" alt="Icône" class="menu-icon">
                                <span class="menu-text">Esportify</span>
                        
                        </div>
                    </nav>
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
                    <p class="current-events"><span class="events-icon">&#128197;</span> Événements en cours</p>
                    <?php if ($ongoing && $ongoing->num_rows > 0): ?>
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
                            <?php while ($ev = $ongoing->fetch_assoc()):
                                $start = $ev['date_debut'] ? date('d/m/Y H:i', strtotime($ev['date_debut'])) : '';
                                $end = $ev['date_fin'] ? date('d/m/Y H:i', strtotime($ev['date_fin'])) : '';
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($ev['id']); ?></td>
                                    <td><?php echo htmlspecialchars($ev['titre']); ?></td>
                                    <td><?php echo htmlspecialchars($ev['description']); ?></td>
                                    <td><?php echo htmlspecialchars($start); ?></td>
                                    <td><?php echo htmlspecialchars($end); ?></td>
                                    <td><?php echo htmlspecialchars($ev['nb_joueurs']); ?></td>
                                    <td><?php echo htmlspecialchars($ev['organisateur'] ?? ''); ?></td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                
                </div>
                <div class="row">
                        <p class="upcoming-events"><span class="events-icon">&#128197;</span> Événements à venir</p>
                        <?php if ($upcoming && $upcoming->num_rows > 0): ?>
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
                            <?php while ($ev = $upcoming->fetch_assoc()):
                                $start = $ev['date_debut'] ? date('d/m/Y H:i', strtotime($ev['date_debut'])) : '';
                                $end = $ev['date_fin'] ? date('d/m/Y H:i', strtotime($ev['date_fin'])) : '';
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($ev['id']); ?></td>
                                    <td><?php echo htmlspecialchars($ev['titre']); ?></td>
                                    <td><?php echo htmlspecialchars($ev['description']); ?></td>
                                    <td><?php echo htmlspecialchars($start); ?></td>
                                    <td><?php echo htmlspecialchars($end); ?></td>
                                    <td><?php echo htmlspecialchars($ev['nb_joueurs']); ?></td>
                                    <td><?php echo htmlspecialchars($ev['organisateur'] ?? ''); ?></td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
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