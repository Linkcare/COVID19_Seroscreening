<?php
/* @var ErrorInfo $errorInfo */
$errorInfo = $GLOBALS["VIEW_MODEL"];

?>

<html>
    <head>
    	<meta charset="UTF-8">
    	<title>Error - Linkcare Kit Info App</title>
    
    	<link rel="stylesheet" type="text/css" href="css/bootstrap.min.css"> 
   		<link rel="stylesheet" type="text/css" href="css/font-awesome.min.css">   		
   		<link rel="stylesheet" type="text/css" href="css/style.css">
   		
   		<script src="js/jquery-3.5.1.slim.min.js"></script>
   		<script src="js/popper.min.js"></script>
   		<script src="js/bootstrap.min.js"></script>
   		
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
                    $id = $_GET['id'];
                    $lang = Localization::SUPPORTED_LOCALES[$i];
                    echo ('<li class="nav-item"><a class="nav-link" href="' . $url . '/?id=' . $id . '&culture=' . $lang . '">' .
                            Localization::translateLanguage($i) . '</a></li>');
                }
                ?>
                
            	</ul>
      		</div>
    	</nav> 
    
    	<!-- Page content -->
    
        <div class="container col-lg-4 col-md-6">
        
        	<p class="mt-4 border border-secondary rounded text-center">
        		<?php

        echo (Localization::translateError($errorInfo->getErrorCode()));
        ?>
        	</p>
        	
        	<p>
        		<?php

        // echo (Localization::translateError($errorInfo->getErrorMessage()));
        echo ($errorInfo->getErrorMessage());
        ?>
        	</p>
        	
        </div>
    
    </body>
</html>