<?php
/* @var ErrorInfo $errorInfo */
$errorInfo = $GLOBALS["VIEW_MODEL"];

?>
    
        <div class="container col-lg-4 col-md-8">
        
        	<p class="mt-4 border border-secondary rounded text-center">
        		<?php
        if ($errorInfo->getErrorCode() == ErrorInfo::INVALID_KIT) {
            echo (Localization::translateError($errorInfo->getErrorCode()) . ': <span style="color:red;">' . $_GET["id"] . '</span>');
        } else {
            echo (Localization::translateError($errorInfo->getErrorCode()));
        }
        ?>
        	</p>
        	
        </div>
    
    </body>
</html>