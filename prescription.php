<?php
require_once 'default_conf.php';
require_once "classes/Database.Class.php";
require_once "classes/class.DbManagerOracle.php";
require_once "classes/class.DbManagerResultsOracle.php";
require_once "classes/Localization.php";
require_once 'view_models/ErrorInfo.php';
require_once 'view_models/KitInfo.php';

if (isset($_SESSION["KIT"])) {
    /* @var KitInfo $kit */
    $kit = unserialize($_SESSION["KIT"]);
} else {
    header("Location: /index.php");
    die();
}

/* Initialize the connection to the DB */
$dbConnResult = Database::init($GLOBALS["DBConnection_URI"]);

if ($dbConnResult !== true) {
    $err = new ErrorInfo(ErrorInfo::DB_CONNECTION_ERROR, $dbConnResult);
    $GLOBALS["VIEW_MODEL"] = $err;
    include "views/Header.html.php";
    include "views/ErrorInfo.html.php";
} else {
    include "views/Header.html.php";

    ?>    
		<div id="div-qr-video" style="display:flex;position: absolute;top: 0;width:100%;height:100%;align-items: center;justify-content: center;z-index:1000;">
    		<div style="position:relative;background-color:white;border-style: solid;border-width: 2px;box-shadow: 5px 10px rgba(0,0,0,0.1);border-radius: 5px;">
              	<video id="qr-video" class="embed-responsive" style="transform: scaleX(-1);/*! position: absolute; */top: 0;"></video>
          		<p id="stop-button" style="position: absolute;top: 0;text-align: right;width: 100%;padding-right: 15px;font-size: 18px;font-weight: bold;cursor: pointer;text-shadow:0.1rem 0.1rem rgba(255,255,255,.5);">&times;</p>
            </div>
        </div>
    
    	<div class="container col-lg-4 col-md-8">
    	<br>
    		<p>
    		<a href="/index.php?id=<?php
    echo ($kit->getId());
    ?>"><i class="btn fa fa-arrow-left" aria-hidden="true"></i></a>
    		</p>
    		<div class="form-group">
                <label for="id"><b><?php

    echo (Localization::translate('Prescription.Label.Id'));
    ?>:</b></label>
            	<input type="text" class="form-control" id="idInput">
          	</div>
          	<br>          	
    		<div class="text-center">
    			<button id="start-button" style="font-size: 80px;" class="fa fa-qrcode btn btn-light" aria-hidden="true"></button>
    		</div>
    		<br>
    		<input id="btnSubmit" class="btn btn-success text-center btn-block btn-lg" target_url="<?php
    echo ($kit->getInstance_url())?>" value="<?php

    echo (Localization::translate('KitInfo.Button.Proceed'));
    ?>">     
    		<br>
    		<p id="cam-errors"></p>
    	</div>    	    	
<?php
}
?>

	<script type="text/javascript">
		$('#div-qr-video').hide();
		$("#btnSubmit").click(function (e) {
			var $kitId = $(this).attr("target_url");
			window.location.href = $kitId + "&prescription_id=" + $("#idInput").val();
		});
		
    	$(document).ready(function() {
    	    var $submit = $("#btnSubmit");
    	    $submit.prop('disabled', true);
    	    $("#idInput").on('input change', function() {
    	        $submit.prop('disabled', !$(this).val().length);
	    	});
    	});
	</script>
	
	<!-- Script area -->
    <script type="module">
        /* Import and Worker Path */
        import QrScanner from "./js/qr-scanner.min.js";
        QrScanner.WORKER_PATH = './js/qr-scanner-worker.min.js';
        
        /* Variables */        
    
        const video = document.getElementById('qr-video');
        const camQrResult = document.getElementById('idInput');
        const camError = document.getElementById('cam-errors');
        const submitButton = document.getElementById('btnSubmit');
    
        /* Helper functions */
    
        function setResult(label, submitButton, result) {
            if(result.length > 0){
                label.value = result; 
                submitButton.disabled = false; 
            }  
            scanner.stop(); 
            $('#div-qr-video').hide();                   
        }
    
        /* QR Scanner */
    
        const scanner = new QrScanner(video, result => setResult(camQrResult, submitButton, result), error => {   
            if(error != "No QR code found"){                        
                camError.textContent = error;
                camError.style.color = 'inherit';
            }                    
        });
        
        document.getElementById('start-button').addEventListener('click', () => {
            scanner.start();
            $('#div-qr-video').show();
        });

        document.getElementById('stop-button').addEventListener('click', () => {
                scanner.stop();
                $('#div-qr-video').hide();
            });
    </script>        
