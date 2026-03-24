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
	<script src="assets/static/css/script.js"></script>
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

			<div class="navigation_tree">
				<!--contenu admin eventuel-->
				<div class="navigation_tree_content">
					<ul>
						<li class="first new_serre">
							<div class="line">
								<a class="expander" href="#"><img src="assets/static/icons/navigation_tree/house-plus.svg" alt=""></a>
								<a class="expander" href="">Ajouter une nouvelle serre</a>
							</div>
						</li>

						<li>
							<div class="line">
								<img src="assets/static/icons/navigation_tree/square-plus.svg" alt="">
								<img src="assets/static/icons/navigation_tree/greenhouse.png" alt="">
								<a>Serre N°xx</a>
							</div>
							<ul style="display: none;">
								<li>
									<div class="line">
										<img src="assets/static/icons/navigation_tree/planter-box.png" alt="">
										<a>Bac N°xx</a>
									</div>
								</li>
								<li>
									<div class="line">
										<img src="assets/static/icons/navigation_tree/planter-box.png" alt="">
										<a>Bac N°xx</a>
									</div>
								</li>
								<li>
									<div class="line">
										<img src="assets/static/icons/navigation_tree/planter-box.png" alt="">
										<a>Bac N°xx</a>
									</div>
								</li>
								<li>
									<div class="line">
										<img src="assets/static/icons/navigation_tree/planter-box.png" alt="">
										<a>Bac N°xx</a>
									</div>
								</li>
							</ul>
						</li>
						
					</ul>
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