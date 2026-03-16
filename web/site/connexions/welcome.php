<?php
//// Initialize session
//session_start();
//
//if (!isset($_SESSION['loggedin']) && $_SESSION['loggedin'] !== false) {
//	header('location: login.php');
//	exit;
//}
?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Ubuntu:ital,wght@0,300;0,400;0,500;0,700;1,300;1,400;1,500;1,700&display=swap"
        rel="stylesheet" />

    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title>SIGACS - Tableau de bord</title>

    <link rel="stylesheet" href="../static/css/style.css" />
</head>

<body>
    <div class="main">
        <div class="navbar">
            <h1>Tableau de bord du projet SIGACS</h1>
			Bienvenue <?php// echo $_SESSION['username']; ?>

			<div class="account-management">
				<a href="password_reset.php" class="btn btn-block btn-outline-warning"><img src="../static/icons/rotate-ccw-key.svg" alt="" srcset=""></a>
				<a href="logout.php" class="btn btn-block btn-outline-danger"><img src="../static/icons/log-out.svg" alt="" srcset=""></a>
			</div>
			
        </div>

        <div class="sidebar">
            sidebar responsive
        </div>

        <div class="content">
            content responsive
        </div>

        <div class="footerbar">
            <span>Projet SIGACS - Sous licence MIT - <i><a href="https://github.com/clement-hervouet/SIGACS" target="_blank">Projet GitHub</a></i></span>
            <span>HERVOUET Clément - BANCQUART Alan - LE GOUALEC Titouan</span>
            <span><i>BTS CIEL – Saint Joseph LaSalle – Lorient</i></span>
        </div>
    </div>
</body>

</html>