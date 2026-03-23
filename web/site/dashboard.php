<?php
// Initialize session
session_start();

if (!isset($_SESSION['loggedin']) && $_SESSION['loggedin'] !== false) {
	header('location: login.php');
	exit;
}
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

    <link rel="stylesheet" type="text/css" href="assets/static/css/style.css" />
</head>

<body>
    <div class="main">
        <div class="navbar">
            <h1>Tableau de bord du projet SIGACS</h1>

        </div>

        <div class="sidebar">
			<div class="sidebar_header">
				<img src="assets/static/logo.png" alt="logo SIGACS">
			
			<div class="greetings">
				Bienvenue <?php echo $_SESSION['username']; ?>
			</div>
			

				<div class="utils_menu">
	
					<a href="">
						<div class="utils_menu_btn">
							<img src="assets/static/icons/home.svg" alt="">
						</div>
					</a>
	
					<a href="/connexions/password_reset.php">
						<div class="utils_menu_btn">
							<img src="assets/static/icons/rotate-ccw-key.svg" alt="reset passwd">
						</div>
					</a>
	
					<a href="logout.php">
						<div class="utils_menu_btn">
							<img src="assets/static/icons/log-out.svg" alt="">
						</div>
					</a>
	
					<a href="">
						<div class="utils_menu_btn">
							<img src="assets/static/icons/settings.svg" alt="">
						</div>
					</a>
	
					<a href="">
						<div class="utils_menu_btn">
							<img src="assets/static/icons/refresh.svg" alt="">
						</div>
					</a>
	
					<a href="">
						<div class="utils_menu_btn">
							<img src="assets/static/icons/info-circle.svg" alt="">
						</div>
					</a>
	
				</div>
			</div>

			<div class="utils_block_title">
				<span>Administration</span>
				<div class="utils_block_icon">
					<img src="assets/static/icons/panel-top-open.svg" alt="">
				</div>
			</div>

			<div class="utils_block">
				<div class="utils_block_icon">
					<img src="assets/static/icons/brand-mysql.svg" alt="">
				</div>
				<div class="utils_block_text">
					<span>PHPMyAdmin</span>
				</div>
			</div>

			<div class="utils_block">
				<div class="utils_block_icon">
					<img src="assets/static/icons/zoom-code.svg" alt="">
				</div>
				<div class="utils_block_text">
					<span>Centreon</span>
				</div>
			</div>

			<div class="utils_block">
				<div class="utils_block_icon">
					<img src="assets/static/icons/network.svg" alt="">
				</div>
				<div class="utils_block_text">
					<span>PfSense</span>
				</div>
			</div>



			<div class="utils_block_title">
				<span>Parc</span>
				<div class="utils_block_icon">
					<img src="assets/static/icons/panel-top-open.svg" alt="">
				</div>
			</div>

			<div class="utils_block">
				<div class="utils_block_icon">
					<img src="assets/static/icons/house-plus.svg" alt="">
				</div>
				<div class="utils_block_text">
					<span>Ajouter une serre</span>
				</div>
			</div>

			<div class="utils_block">
				<div class="utils_block_icon">
					<img src="assets/static/icons/house-plus.svg" alt="">
				</div>
				<div class="utils_block_text">
					<span>Voir les serres</span>
				</div>
			</div>



			<div class="utils_block_title">
				<span>Serre</span>
				<div class="utils_block_icon">
					<img src="assets/static/icons/panel-top-open.svg" alt="">
				</div>
			</div>

			<div class="utils_block">
				<div class="utils_block_icon">
					<img src="assets/static/icons/grid-2x2-plus.svg" alt="">
				</div>
				<div class="utils_block_text">
					<span>Ajouter un bac</span>
				</div>
			</div>

			<div class="utils_block">
				<div class="utils_block_icon">
					<img src="assets/static/icons/plant.svg" alt="">
				</div>
				<div class="utils_block_text">
					<span>Ajouter une culture</span>
				</div>
			</div>


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