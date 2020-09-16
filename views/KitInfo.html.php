<?php

/* @var KitInfo $kit */
$kit = $GLOBALS["VIEW_MODEL"];

if ($kit->getStatus() == KitInfo::STATUS_NOT_USED) {
    $statusClassStyle = "text-success";
} else if ($kit->getStatus() == KitInfo::STATUS_DISCARDED || $kit->getStatus() == KitInfo::STATUS_EXPIRED) {
    $statusClassStyle = "text-danger";
} else {
    $statusClassStyle = "text-primary";
}
?>

<html>
    <head>
    	<meta charset="UTF-8">
    	<title>Linkcare Kit Info App</title>
    	
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
                echo ('<li class="nav-item"><a class="nav-link" href="' . $url . '?id=' . $id . '&culture=' . $lang . '">' .
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

        echo (Localization::translate('KitInfo.Title.InfoFromKitId') . ' ' . $kit->getId());
        ?>
        </p>
    			
		<ul class="list-group list-group-flush">
        	<li class="list-group-item">
        		<b>
        			<?php

        echo (Localization::translate('KitInfo.Label.ManufacturedIn'));
        ?>: 
        		</b>
 					<?php

    echo ($kit->getManufacture_place() . '<br>' . $kit->getManufacture_date());
    ?>
 				</li>
              	<li class="list-group-item">
              		<b>
              			<?php

                echo (Localization::translate('KitInfo.Label.BatchNumber'));
                ?>: 
          			</b>
     				<?php

        echo ($kit->getBatch_number());
        ?>
              	</li>
              	<li class="list-group-item">
              		<b>
              			<?php

                echo (Localization::translate('KitInfo.Label.ExpirationDate'));
                ?>: </b>
					<?php

    echo ($kit->getExp_date());
    ?>
    			</li>
              	<li class="list-group-item">
					<b>
						<?php

    echo (Localization::translate('KitInfo.Label.Status'));
    ?>: 
    				</b>
    				<span class="<?php

        echo ($statusClassStyle);
        ?>  ">
        			<?php

        echo (Localization::translateStatus($kit->getStatus()));
        ?>
    					</span>
				</li>
            </ul>
			<br>
    	
    	<!-- Buttons area -->
		
        <?php
        if ($kit->getStatus() == KitInfo::STATUS_NOT_USED || $kit->getStatus() == KitInfo::STATUS_ASSIGNED) {
            /* The Kit is valid */
            ?>  
        	<button class="btn btn-success text-center btn-block btn-lg" onclick="window.location.href='<?php

            echo ($kit->getInstance_url())?>';"><?php

            echo (Localization::translate('KitInfo.Button.Proceed'));
            ?>	</button>            
        	<br>
        	        	        	
        <?php
        }
        ?>
        
            <button class="btn btn-primary text-center btn-block btn-lg" onclick="window.location.href='http://seroscreening.com';"><?php

            echo (Localization::translate('KitInfo.Button.Close'));
            ?>	
            </button>
        
        <?php
        if ($kit->getStatus() == KitInfo::STATUS_NOT_USED || $kit->getStatus() == KitInfo::STATUS_ASSIGNED) {
            /* The Kit is valid, meaning it can be discarded */
            ?> 
            
        	<br><br><br><br>        
            <div class="container col-lg-4 text-center">

                <a href="<?php

            echo ($_SERVER['REQUEST_URI']);
            ?>&discard" onclick="return confirm('<?php

            echo (Localization::translate('KitInfo.Confirm'));
            ?>')" name="discard"><i style="text-decoration: underline;"><?php

            echo (Localization::translate('KitInfo.Button.Discard'));
            ?>
            	</i></a>

        	</div>
        		        	        	
        <?php
        }
        ?>
        
        </div>	      	
	</body>
</html>