<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title>Esportify - Menu</title>
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
            <div class="row">
                <nav class="col-12 menu-bar">
                    <div class="menu-item">
                        <a href="index.php" class="menu-button">
                            <span class="menu-home-icon"><i class="bi bi-house-door-fill"></i></span> Accueil
                        </a>
                        <span class="menu-icon-mobile">
                            <a href="index.php" class="menu-icon-mobile">&#8962;</a>
                        </span>
                    </div>
                    <div class="menu-title">
                        <h1><em>Menu</em></h1>
                    </div>
                    <div class="menu-logo">
                        <img src="images/logo-jeu.png" alt="Icône" class="menu-icon">
                        <span class="menu-text">Esportify</span>
                    </div>
                </nav>
            </div>
            
        </section>

        <section id="contenu">
            <section class="menu-main login-section">
                <div class="row login-box">
                    <!-- Icône Bootstrap blanche au-dessus du titre -->
                    <div class="login-icon mb-2">
                        <i class="bi bi-person-circle text-white" style="font-size: 2.5rem;"></i>
                    </div>
                    <div class="login-title">Connexion</div>
                    <form>
                        <div class="row">
                            <input type="text" placeholder="Nom d'utilisateur" class="login-input" />
                        </div>
                        <div class="row">
                            <input type="password" placeholder="Mot de passe" class="login-input" />
                        </div>
                        <div class="row">
                            <button type="submit" class="login-btn">Se connecter</button>
                        </div>
                    </form>
                </div>
            </section>
            <div class="row">
                
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th><span class="events-icon">&#128197;</span> Événements</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr>
                                <td>1</td>
                                <td>Tournoi Fortnite - 01/03/2025 de 10:00 à 12:00</td>
                            </tr>
                            <tr>
                                <td>2</td>
                                <td>Tournoi League of Legend - 01/03/2025 de 10:00 à 12:00</td>
                            </tr>
                            <tr>
                                <td>3</td>
                                <td>Tournoi Counter-Strike - 02/03/2025 de 10:00 à 12:00</td>
                            </tr>
                            <tr>
                                <td>4</td>
                                <td>Tournoi Valorant - 02/03/2025 de 10:00 à 12:00</td>
                            </tr>
                            <tr>
                                <td>5</td>
                                <td>Tournoi Overwatch 2 - 08/03/2025 de 10:00 à 16:00</td>
                            </tr>
                            <tr>
                                <td>6</td>
                                <td>Tournoi Rocket League - 08/03/2025 de 14:00 à 16:00</td>
                            </tr>
                            <tr>
                                <td>7</td>
                                <td>Tournoi Call of Duty - 09/03/2025 de 10:00 à 12:00</td>
                            </tr>
                            <tr>
                                <td>8</td>
                                <td>Tournoi Rainbow Six - 09/03/2025 de 14:00 à 16:00</td>
                            </tr>
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