<?php
// Prescription returned as a JSON in case we receive a POST
if (isset($_POST["prescription"])) {
    $prescription = new Prescription($_POST["prescription"]);
    header('Content-Type: application/json');
    echo $prescription->toJSON();
    exit();
}
?>

<html>
    <head>
    	<meta charset="UTF-8">
    	<title><?php
    echo (Localization::translate('Head.Title'))?></title>
    	
   		<link rel="stylesheet" type="text/css" href="css/bootstrap.min.css"> 
   		<link rel="stylesheet" type="text/css" href="css/font-awesome.min.css">   		
   		<link rel="stylesheet" type="text/css" href="css/style.css">
   		
   		<script src="js/jquery-3.5.1.min.js"></script>
   		<script src="js/popper.min.js"></script>
   		<script src="js/bootstrap.min.js"></script>
   		
   		<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
   		
   		<script>
       		$(function () {
       		  $('[data-toggle="popover"]').popover({ 
                  html: true, 
                  content: $('.locales').html() 
              }); 
       		})
        </script>
    </head>
    <body>
    
    <!-- Navbar -->
    
    <nav class="navbar bg-light navbar-light">
        
        <!-- Logos -->            
      	<a class="navbar-brand d-none d-lg-block" href="#">
            <img src="img/logo.png" alt="Linkcare">
      	</a>
      	<a class="navbar-brand d-lg-none" href="#">
            <img src="img/logo_small.png" alt="Linkcare">
      	</a>
    
        <!-- Toggler/collapsibe Button -->
      	<button class="navbar-toggler" type="button" data-toggle="popover" data-placement="bottom">
        	<span class="fa fa-globe">
        	</span>
      	</button>
    
        <!-- Navbar links, loaded at the popover using the locales class -->
     	<div class="collapse navbar-collapse locales">
            <ul class="navbar-nav">              	
              	<?php
            for ($i = 0; $i < sizeof(Localization::SUPPORTED_LOCALES); $i++) {
                $url = substr($_SERVER['REQUEST_URI'], 0, strpos($_SERVER['REQUEST_URI'], "/?"));
                $langUrl = Localization::SUPPORTED_LOCALES[$i];

                if ($id = $_GET['id']) {
                    echo ('<li class="nav-item"><a class="nav-link" href="' . $url . '?id=' . $id . '&culture=' . $langUrl . '">' .
                            Localization::translateLanguage($i) . '</a></li>');
                } else {
                    // If there is no id sent through GET, we might come from Prescription, meaning we should check for other GET fields
                    if (isset($_GET['error'])) {
                        $errorGet = "&error=" . $_GET['error'];
                    } else {
                        $errorGet = "";
                    }
                    if (isset($_GET['return'])) {
                        $returnGet = "&return=" . $_GET['return'];
                    } else {
                        $returnGet = "";
                    }

                    echo ('<li class="nav-item"><a class="nav-link" href="' . $url . '?culture=' . $langUrl . $errorGet . $returnGet . '">' .
                            Localization::translateLanguage($i) . '</a></li>');
                }
            }
            ?>
            
        	</ul>
  		</div>
	</nav> 