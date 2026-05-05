<?php
session_start();

// Fix: was using wrong logical operator
if (empty($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('location: login.php');
    exit;
}

// DB connection needed by menu/content.php
require_once 'config/config.php';
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
	
	<script src="forms/forms.js" defer></script>
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
					Bienvenue <?php echo $_SESSION['prenom']; ?>
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

					<?php if ($_SESSION['role'] == ('admin')): ?>
						<a href="connexions/register.php">
							<div class="admin utils_menu_btn">
								<img src="assets/static/icons/user-plus.svg" alt="">
							</div>
						</a>
					<?php endif ?>
	
				</div>
			</div>

			<div class="navigation_tree">
				<!--contenu admin eventuel-->
				<div class="navigation_tree_content" id="navigationTree">
					<?php include __DIR__ . '/menu/content.php'; ?>
					
					<!-- Form de démo offline -->
					<!-- <ul>
						<li>
							<div class="line" id="newSerreBtn">
								<img src="assets/static/icons/navigation_tree/house-plus.svg" alt="">
								<a>Ajouter une nouvelle serre</a>
							</div>
						</li>

						<li>
							<div class="line">
								<img src="assets/static/icons/navigation_tree/square-plus.svg" alt="">
								<img src="assets/static/icons/navigation_tree/greenhouse.png" alt="">
								<a>Serre N°xx</a>
							</div>
							<ul>
								<li>
									<div class="line" id="newBacBtn">
										<img src="assets/static/icons/navigation_tree/grid-2x2-plus.svg" alt="">
										<a>Ajouter un nouveau bac</a>
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
								<li>
									<div class="line">
										<img src="assets/static/icons/navigation_tree/planter-box.png" alt="">
										<a>Bac N°xx</a>
									</div>
								</li>
							</ul>
						</li>

						<li>
							<div class="line" id="newCultureBtn">
								<img src="assets/static/icons/navigation_tree/plant-plus.svg" alt="">
								<a>Ajouter une nouvelle culture</a>
							</div>
						</li>

						<li>
							<div class="line">
								<img src="assets/static/icons/navigation_tree/square-plus.svg" alt="">
								<img src="/assets/static/icons/navigation_tree/potted-plant-icon.png" alt="">
								<a>Liste des cultures disponibles</a>
							</div>
							<ul>
								<li>
									<div class="line">
										<img src="assets/static/icons/navigation_tree/potted-plant-icon.png" alt="">
										<a>Nom de la culture 1</a>
									</div>
								</li>
								<li>
									<div class="line">
										<img src="assets/static/icons/navigation_tree/potted-plant-icon.png" alt="">
										<a>Nom de la culture 2</a>
									</div>
								</li>
								<li>
									<div class="line">
										<img src="assets/static/icons/navigation_tree/potted-plant-icon.png" alt="">
										<a>Nom de la culture 3</a>
									</div>
								</li>

							</ul>
						</li>
						
					</ul> -->
				</div>
			</div>

        </div>

        <div class="content" id="mainContent">
            <p>Sélectionnez un élément dans le menu.</p>
        </div>

        <div class="footerbar">
            <span>Projet SIGACS - Sous licence MIT - <i><a href="https://github.com/clement-hervouet/SIGACS" target="_blank">Projet GitHub</a></i></span>
            <span>HERVOUET Clément - BANCQUART Alan - LE GOUALEC Titouan</span>
            <span><i>BTS CIEL – Saint Joseph LaSalle – Lorient</i></span>
        </div>
    </div>

	<div id="modalContainer" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;"></div>

</body>

</html>