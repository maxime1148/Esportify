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
$reg_message = '';

// Form handling: distinguer login vs register via champ hidden 'action'
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';

    // Suppression d'un utilisateur (action spécifique)
    if ($action === 'delete_user') {
        $del_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        if ($del_id > 0) {
            // Empêcher suppression de soi-même
            if (!empty($_SESSION['user_id']) && intval($_SESSION['user_id']) === $del_id) {
                $message = 'Vous ne pouvez pas supprimer votre propre compte.';
            } else {
                if ($mysqli) {
                    $del = $mysqli->prepare('DELETE FROM utilisateurs WHERE id = ? LIMIT 1');
                    if ($del) {
                        $del->bind_param('i', $del_id);
                        if ($del->execute()) {
                            $del->close();
                            // Redirect pour éviter double soumission
                            header('Location: users_list.php');
                            exit;
                        } else {
                            $message = 'Erreur lors de la suppression.';
                        }
                    } else {
                        $message = 'Erreur interne (préparation de la suppression).';
                    }
                } else {
                    $message = 'Connexion à la base de données impossible.';
                }
            }
        }
    }

    if ($action === 'register') {
        // Inscription
        $reg_username = trim($_POST['reg_username'] ?? '');
        $reg_password = trim($_POST['reg_password'] ?? '');
        $reg_password_confirm = trim($_POST['reg_password_confirm'] ?? '');

        if ($reg_username === '' || $reg_password === '' || $reg_password_confirm === '') {
            $reg_message = 'Veuillez remplir tous les champs d\'inscription.';
        } elseif ($reg_password !== $reg_password_confirm) {
            $reg_message = 'Les mots de passe ne correspondent pas.';
        } else {
            if ($mysqli) {
                // Vérifier que le nom d'utilisateur n'existe pas
                $check = $mysqli->prepare('SELECT id FROM utilisateurs WHERE username = ? LIMIT 1');
                if ($check) {
                    $check->bind_param('s', $reg_username);
                    $check->execute();
                    $res = $check->get_result();
                    if ($res && $res->fetch_assoc()) {
                        $reg_message = 'Ce nom d\'utilisateur est déjà pris.';
                        $check->close();
                    } else {
                        $check->close();
                        // NOTE: la base existante stocke les mots de passe en clair.
                        // Pour rester compatible avec le système de login actuel,
                        // nous stockons ici le mot de passe tel quel. En production,
                        // utilisez password_hash() ici et password_verify() au login.

                        $role = 'joueur';
                        $status = 'connected';
                        $ins = $mysqli->prepare('INSERT INTO utilisateurs (username, password, role, status) VALUES (?, ?, ?, ?)');
                        if ($ins) {
                            $ins->bind_param('ssss', $reg_username, $reg_password, $role, $status);
                            if ($ins->execute()) {
                                // Auto-login
                                $newId = $mysqli->insert_id;
                                $_SESSION['user_id'] = $newId;
                                $_SESSION['username'] = $reg_username;
                                $_SESSION['role'] = $role;

                                // Redirect pour éviter resoumission
                                header('Location: menu.php');
                                exit;
                            } else {
                                $reg_message = 'Erreur lors de la création du compte.';
                            }
                            $ins->close();
                        } else {
                            $reg_message = 'Erreur interne (préparation de l\'insertion).';
                        }
                    }
                } else {
                    $reg_message = 'Erreur interne (préparation de la vérification).';
                }
            } else {
                $reg_message = 'Connexion à la base de données impossible.';
            }
        }

    } else {
        // Login
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
}
?>

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
            <div class="menu-bar">
                <div class="row">
                        <div class="menu-title">
                            <h1><em>Esportify - Gestion des utilisateurs</em></h1>
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
                       
            <div class="row">
                <a class="return-box" href="users_list.php"> Retour</a>
            </div>
            <br>
            <br>
            
            <div class="row">
<?php
// N'affiche la liste que pour les administrateurs
$role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
if (in_array($role, ['admin', 'administrateur', 'administrator'], true)):
?>
                <h2 style="color:orange">Modifier le rôle d'un utilisateur</h2>
                <?php
                    // Prefill username from querystring if provided (clicked from users_list)
                    $prefill_username = '';
                    if (!empty($_GET['username'])) {
                        $prefill_username = $_GET['username'];
                    } elseif (!empty($_GET['id'])) {
                        // optional: if id is provided, try to look up username
                        if ($mysqli) {
                            $stmt = $mysqli->prepare('SELECT username FROM utilisateurs WHERE id = ? LIMIT 1');
                            if ($stmt) {
                                $stmt->bind_param('i', $_GET['id']);
                                $stmt->execute();
                                $res = $stmt->get_result();
                                if ($row = $res->fetch_assoc()) {
                                    $prefill_username = $row['username'];
                                }
                                $stmt->close();
                            }
                        }
                    }
                ?>
                <form class="user-box" method="post" action="users_update_handler.php">
                    <div class="mb-3">
                        <label for="user_id" class="form-label">Nom de l'utilisateur :</label>
                        <br>
                        <p style="color: orange;"><?php echo htmlspecialchars($prefill_username); ?></p>
                        <input type="hidden" name="username" value="<?php echo htmlspecialchars($prefill_username); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="new_role" class="form-label">Nouveau rôle :</label>
                        <select class="form-select" id="new_role" name="new_role" required>
                            <option value="joueur">Joueur</option>
                            <option value="organisateur">Organisateur</option>
                            <option value="administrateur">Administrateur</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Mettre à jour le rôle</button>
                </form>
<?php else: ?>
                <div class="alert alert-warning">Accès réservé aux administrateurs.</div>
<?php endif; ?>
            </div>

                
            
            <br>
            <br>
            
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