<?php
session_start();

$host = '127.0.0.1';
$db = 'esportify_sql';
$dbuser = 'root';
$dbpass = '';

$mysqli = new mysqli($host, $dbuser, $dbpass, $db);
if ($mysqli->connect_errno) {
    // If DB not available, redirect back
    header('Location: users_list.php');
    exit;
}

// Only allow administrators
$role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
if (!in_array($role, ['administrateur'], true)) {
    header('Location: users_list.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: users_list.php');
    exit;
}

$username = trim($_POST['username'] ?? '');
$new_role = trim($_POST['new_role'] ?? '');

$allowed = ['joueur', 'organisateur', 'administrateur'];
if ($username === '' || $new_role === '' || !in_array($new_role, $allowed, true)) {
    header('Location: users_list.php');
    exit;
}

// Try to update by username
$stmt = $mysqli->prepare('UPDATE utilisateurs SET role = ? WHERE username = ? LIMIT 1');
if ($stmt) {
    $stmt->bind_param('ss', $new_role, $username);
    if ($stmt->execute()) {
        $stmt->close();
        header('Location: users_list.php');
        exit;
    }
    $stmt->close();
}


header('Location: users_list.php');
exit;
