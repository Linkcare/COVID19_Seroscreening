<?php

/* @var KitInfo $kit */
$kit = $GLOBALS["VIEW_MODEL"];
$_SESSION["KIT"] = serialize($kit);

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
        echo (Localization::translate('KitInfo.Title.InfoFromKitId', ['kit_id' => $kit->getId()]));
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
            /* The Kit status is: not used */
            ?>
        	<button id="btnProcessKit" class="btn btn-success text-center btn-block btn-lg"><?php

            echo (Localization::translate('KitInfo.Button.Continue'));
            ?>	</button>            
        	<br>
          <?php
        } else if ($kit->getStatus() == KitInfo::STATUS_ASSIGNED || $kit->getStatus() == KitInfo::STATUS_PROCESSING ||
                $kit->getStatus() == KitInfo::STATUS_PROCESSING_5MIN || $kit->getStatus() == KitInfo::STATUS_INSERT_RESULTS) {
            /* The Kit status is: assigned, processing, processing (results in <5 min.) or insert results */
            ?>  
        	<button id="btnProcessKit" class="btn btn-success text-center btn-block btn-lg"><?php
            if ($kit->getStatus() == KitInfo::STATUS_ASSIGNED || $kit->getStatus() == KitInfo::STATUS_PROCESSING) {
                echo (Localization::translate('KitInfo.Button.Proceed'));
            } else if ($kit->getStatus() == KitInfo::STATUS_PROCESSING_5MIN) {
                echo (Localization::translate('KitInfo.Button.EarlyProceed'));
            } else if ($kit->getStatus() == KitInfo::STATUS_INSERT_RESULTS) {
                echo (Localization::translate('KitInfo.Button.InsertResults'));
            }
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
            
            <div style="text-align: center; display: flex; flex-flow: column; height: 250px;">
        		<div style="flex: 1;"></div>
            	<div>
                	<a href="https://www.linkcare.es<?php
                $locale = Localization::getLang();
                if (in_array($locale, ['en', 'es', 'ca'])) {
                    echo "?lang=" . $locale;
                } else {
                    echo "?lang=en";
                }
                ?>"><img src="img/linkcarebio.jpg" alt="Linkcare" width=150></a>
            	</div>
            	<div style="flex: 1;"></div>
            </div>
        
        <?php
        // The discard button will be hidden at the moment, the correct if would be the following:
        // if ($kit->getStatus() == KitInfo::STATUS_NOT_USED || $kit->getStatus() == KitInfo::STATUS_ASSIGNED) {
        if (false) {
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
        <script>
        	$("#btnProcessKit").click(function (e) {
                $.get(
                	'actions.php',
                    {action: 'process_kit',lang: '<?php

                    echo (Localization::getLang());
                    ?>'},
                    function(targetUrl){
                        window.location.href = targetUrl;
                    }
                );      		   	
        	});
        </script>    	
	</body>
</html>