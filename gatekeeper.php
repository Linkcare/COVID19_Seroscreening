<?php
require_once 'default_conf.php';

/* Initialize the connection to the DB */
$dbConnResult = Database::init($GLOBALS["DBConnection_URI"]);

if ($dbConnResult !== true) {
    $err = new ErrorInfo(ErrorInfo::DB_CONNECTION_ERROR, $dbConnResult);
    openErrorInfoView($err);
} else {
    ?>
<html>
    <head>
    	<meta charset="UTF-8">
    	<title>Gatekeeper</title>
    	
   		<link rel="stylesheet" type="text/css" href="css/bootstrap.min.css"> 
   		<link rel="stylesheet" type="text/css" href="css/font-awesome.min.css">   		
   		<link rel="stylesheet" type="text/css" href="css/style.css">
   		<style>            
            .circle {
                height: 200px;
                width: 200px;                
                border-radius: 50%;
                display: inline-block;
            }
            .red {
                background-color: red;
            }
            .green{
                background-color: green;
            }
            .orange-question{
                font-size: 135;
                color: orange;
                height: 200px;
                width: 200px;
                border-radius: 50%;
                border: 2px solid orange;
                margin-left: 25%;
            }            
        </style>
   		
   		<script src="js/jquery-3.5.1.min.js"></script>
   		<script src="js/popper.min.js"></script>
   		<script src="js/bootstrap.min.js"></script>
   		<script src="js/detect.js"></script>
   		<script src="js/jquery.playSound.js"></script>   		
   		
   		<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />   		
    </head>
    <body>
    	<div id="div-qr-video" style="display:flex;position: absolute;top: 0;width:100%;height:100%;align-items: center;justify-content: center;z-index:1000;">
            <div style="position:relative;max-height:90%;background-color:white;border-style: solid;border-width: 2px;box-shadow: 5px 10px rgba(0,0,0,0.1);border-radius: 5px;">
              	<video id="qr-video" class="embed-responsive" style="transform: scaleX(-1);/*! position: absolute; */top: 0;"></video>
          		<p id="stop-button" style="position: absolute;top: 0;text-align: right;width: 100%;padding-right: 15px;font-size: 18px;font-weight: bold;cursor: pointer;text-shadow:0.1rem 0.1rem rgba(255,255,255,.5);">&times;</p>
            </div>
        </div>
        
    	<div class="container col-lg-4 col-md-8">
    		<div class="row">
    			<div class="col-lg-12 col-md-12 text-center" style="padding: 5; height: 300px; padding-top: 65px;">
            		<div class="circle red" style="display: none;"></div>
            		<div class="circle green" style="display: none;"></div>
            		<div class="orange-question" style="display: none;">?</div>
            	</div>
    		</div>
    		<div class="row">
    			<div class="col-lg-12 col-md-12 text-center" style="padding: 5;">
            		<img id="start-button" class="img-fluid mx-auto" style="width: 40%; cursor: pointer; margin-bottom: 8px;" src="img/scan_qr.png">
            		
            		<!-- message in case we are on a different browser than Safari at iOS -->
            		<p id="ios-camera-message" style="display: none; color: red;"><?php
    echo (Localization::translate('Camera.iOS'));
    ?></p>
            	</div>
    		</div>
    		
    		<audio id="audio-error" preload="none">
              <source src="audio/error.mp3" type="audio/mpeg">
            </audio>
        	
        	<input type="hidden" id="qr_code">
        	
        	<!-- div to display any errors when scanning a qr code -->
        	<p id="cam-errors" style="display: none;"></p>
    	</div>
    

    <script>
    	/* Hide the qr-scanner div in order to show it when it's requested */
    	$('#div-qr-video').hide();    	
    </script>
    
    <!-- Script area for the qr-scanner plugin -->
    <script type="module">
        /* Import and Worker Path */
        import QrScanner from "./js/qr-scanner.min.js";
        QrScanner.WORKER_PATH = './js/qr-scanner-worker.min.js';
    
        /* Variables from the html */
    
        const video = document.getElementById('qr-video');
        const camQrResult = document.getElementById('qr_code');
        const camError = document.getElementById('cam-errors');
    
        /* Helper functions */
    
        //Function to stop the qr camera scan from working and hide its div
        function cameraStop(){
            scanner.stop();
            //hide again the camera div
            $('#div-qr-video').hide();
        }
    
        //Function to write the results of the qr scan at a certain label
        function setResult(label, result) {
            $.post(
    			'actions.php',
                {action: 'check_test_results', id: result},
                function(positive){
                    if(positive == 0){
                    	$("div.orange-question").show();
                        $.playSound('audio/error.mp3');
                    }else if(positive == 1){
                    	$("div.circle.red").show();
                        $.playSound('audio/error.mp3');
                    }else if(positive == 2){
                    	$("div.circle.green").show();
                        $.playSound('audio/success.mp3');
                    }
                });
                
            //Once a result has been scanned, stop the scanner and hide the camera div
            cameraStop();
        }
    
        /* QR Scanner functionality and events */
    
        const scanner = new QrScanner(video, result => setResult(camQrResult, result), error => {
            //The "No QR code found" error is common when there is no qr code being scanned, unless it's different,
            //write the error at the corresponding div at the bottom of the page
            if(error != "No QR code found"){
                camError.textContent = error;
                camError.style.color = 'inherit';
            }
        });
    
        //Get the user agent
        var ua = detect.parse(navigator.userAgent);
        // in case we are at iOS using Firefox or Chrome, show a warning message to notify the user that the camera won't work
        if(ua.browser.family != 'Mobile Safari' && ua.os.family === 'iOS') {
            $("#start-button").hide();
        	$("#ios-camera-message").show();
        }else{
            //else add the scanner button listener functionality
            document.getElementById('start-button').addEventListener('click', () => {
                $("div.circle.red").hide();
                $("div.circle.green").hide();
                $("div.orange-question").hide();
                $.playSound('audio/blank.mp3');   //necessary in order to be able to play the success/error afterwards
                scanner.start();
                //show the camera div
                $('#div-qr-video').show();
            });
        }
    
        document.getElementById('stop-button').addEventListener('click', () => {
            cameraStop();
        });
    </script>

	</body>
</html>
<?php
}
?>
