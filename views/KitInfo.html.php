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
	
	<div class="container col-lg-4 col-md-8">
		
	
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
        if ($kit->getStatus() == KitInfo::STATUS_NOT_USED) {
            $_SESSION["KIT"] = serialize($kit);

            /* The Kit status is: not used */
            ?>
        	<button class="btn btn-success text-center btn-block btn-lg" onclick="window.location.href='prescription.php?culture=<?php

            echo (Localization::getLang());
            ?>'"><?php

            echo (Localization::translate('KitInfo.Button.Continue'));
            ?>	</button>            
        	<br>
          <?php
        } else if ($kit->getStatus() == KitInfo::STATUS_ASSIGNED) {
            /* The Kit status is: assigned */
            ?>  
        	<button class="btn btn-success text-center btn-block btn-lg" onclick="window.location.href='<?php

            echo ($kit->getInstance_url())?>';"><?php

            echo (Localization::translate('KitInfo.Button.Proceed'));
            ?>	</button>            
        	<br>
        	        	        	
        <?php
        }
        ?>
        
            <button class="btn btn-primary text-center btn-block btn-lg" onclick="window.location.href='<?php

            echo ($GLOBALS['CLOSE_URL']);
            ?>';"><?php

            echo (Localization::translate('KitInfo.Button.Close'));
            ?>	
            </button>
        
        <?php
        if (false) {
            // The discard button will be hidden at the moment, the correct if would be the following:
            // if ($kit->getStatus() == KitInfo::STATUS_NOT_USED || $kit->getStatus() == KitInfo::STATUS_ASSIGNED) {

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