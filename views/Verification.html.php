<?php
// Translate the company name received through GET params
switch ($_GET['company']) {
    case 'dbt' :
        $company = 'Diagnos Biotech';
        break;
    default :
        $company = $_GET['company'];
}

// The button URL
$url = 'https://www.linkcare.es';
// The button's URL parameters
switch (Localization::getLang()) {
    case 'es' :
    case 'ca' :
    case 'en' :
        $urlParams = '?lang=' . Localization::getLang();
        break;
    default :
        $urlParams = '?lang=en';
}
?>

    <div class="container col-lg-4 col-md-8 j_warning" style="display: none;">
    	<p>It is mandatory to provide your location in order to continue with the verification.</p>
    </div>
	<div class="container col-lg-4 col-md-8 j_body" style="display: none;">
		
	
        <p class="mt-4 border border-secondary rounded text-center">
        	<?php
        echo (Localization::translate('Verification.Title'));
        ?>
        </p>
    			
		<ul class="list-group list-group-flush">
        	<li class="list-group-item">
        		<b>
        			<?php
        echo (Localization::translate('Verification.Label.Company'));
        ?>:
        		</b>
 					<?php
    echo $company;
    ?>
 				</li>
              	<li class="list-group-item">
              		<b>
              			<?php
                echo (Localization::translate('Verification.Label.RevisionDate'));
                ?>: 
          			</b>
     				06/07/2022
              	</li>
              	<li class="list-group-item">
              		<b>
              			<?php
                echo (Localization::translate('KitInfo.Label.BatchNumber'));
                ?>: 
          			</b>
     				<?php

        echo $_GET['id'];
        ?>
              	</li>
            </ul>
			<br>
    	
    	<!-- Buttons area -->
            <button class="btn btn-primary text-center btn-block btn-lg j_submit" onclick="">
            	<?php
            echo (Localization::translate('Verification.Button.Products'));
            ?>
            </button>
        
        </div>
        
        	<script>
    		<?php
    // We'll turn on the functionlity to save the user's Geolocation based on the 'Request_Geolocation' global variable
    if ($GLOBALS["Request_Geolocation"]) {
        ?>
                function getLocation() {
              		if (navigator.geolocation) {
                    	navigator.geolocation.getCurrentPosition(showPosition, denied);
                  	} else { 
                  		$('.j_warning p').html("Geolocation is not supported by this browser, please try with a different one.");
                    }
                }
        
                function showPosition(position) {
                	$.get(
                			'actions.php',
                            {action: 'store_geolocation', latitude: position.coords.latitude, longitude: position.coords.longitude, id: <?php

echo $_GET['id'];
        ?>},
                            function(ret){
                                	console.log(ret);
                            	 	var url = "<?php

        echo $url . $urlParams;
        ?>";
        	                    	$('.j_submit').attr('onclick', "window.location.href='" + url + "';");
        	                    	$('.j_body').show();
                            }
                        );
                }
        
                function denied(){
                	$('.j_warning').show();
                }
        
                getLocation();
            <?php
    } else {
        ?>
            	var url = "<?php
        echo $url . $urlParams;
        ?>";
                $('.j_submit').attr('onclick', "window.location.href='" + url + "';");
            	$('.j_body').show();
            <?php
    }
    ?>
        </script>
	</body>
</html>
