<?php
require_once 'default_conf.php';

/* Initialize the connection to the DB */
$dbConnResult = Database::init($GLOBALS["DBConnection_URI"]);

if ($dbConnResult !== true) {
    $err = new ErrorInfo(ErrorInfo::DB_CONNECTION_ERROR, $dbConnResult);
    openErrorInfoView($err);
} else {
    /* Initialize user language */
    setLanguage();
    ?>
<html>
    <head>
    	<meta charset="UTF-8">
    	<title>Gatekeeper</title>
    	
   		<link rel="stylesheet" type="text/css" href="css/bootstrap.min.css"> 
   		<link rel="stylesheet" type="text/css" href="css/font-awesome.min.css">   		
   		<link rel="stylesheet" type="text/css" href="css/style.css">
   		<link rel="stylesheet" type="text/css" href="css/gatekeeper.css">

   		<script src="js/jquery-3.5.1.min.js"></script>
   		<script src="js/popper.min.js"></script>
   		<script src="js/bootstrap.min.js"></script>
   		<script src="js/detect.js"></script>
   		
   		<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
   		
   		<script>
   			// Function to turn on the language switch popover at the Navbar
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
          	<a class="navbar-brand d-lg-block" href="#">
                <img src="img/LinkcareBio_logo.png" alt="Linkcare" style="max-width: 150px;">
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
        $getParams = "?";
        foreach ($_GET as $key => $value) {
            if ($key == "culture") {
                $value = $langUrl;
            }
            if ($getParams == "?") {
                $getParams .= $key . "=" . $value;
            } else {
                $getParams .= "&" . $key . "=" . $value;
            }
        }
        if (!array_key_exists('culture', $_GET)) {
            if ($getParams == "?") {
                $getParams .= "culture=" . $langUrl;
            } else {
                $getParams .= "&culture=" . $langUrl;
            }
        }
        echo ('<li class="nav-item"><a class="nav-link" href="' . $url . $getParams . '">' . Localization::translateLanguage($i) . '</a></li>');
    }
    ?>
                
            	</ul>
        	</div>
        </nav> 
    
    	<!-- Page content -->
    
    	<div id="div-qr-video" style="display:flex;position: absolute;top: 0;width:100%;height:100%;align-items: center;justify-content: center;z-index:1000;">
            <div style="position:relative;max-height:90%;background-color:white;border-style: solid;border-width: 2px;box-shadow: 5px 10px rgba(0,0,0,0.1);border-radius: 5px;">
              	<video id="qr-video" class="embed-responsive" style="transform: scaleX(-1);/*! position: absolute; */top: 0;"></video>
          		<p id="stop-button" style="position: absolute;top: 0;text-align: right;width: 100%;padding-right: 15px;font-size: 18px;font-weight: bold;cursor: pointer;text-shadow:0.1rem 0.1rem rgba(255,255,255,.5);">&times;</p>
            </div>
        </div>
        
    	<div class="container col-lg-4 col-md-8">
    		<div class ="row">
				<div class="col-lg-12 col-md-12 text-center" style="padding: 5;">
        			<p class="mt-4 border border-secondary rounded text-center" style="text-transform: uppercase;" ><?php
    echo (Localization::translate('Gatekeeper.Title'));
    ?></p>
    			</div>
    		</div>
    		<div class="row">
    			<div class="col-lg-12 col-md-12 text-center" style="padding: 5; height: 250px; padding-top: 20px;">
            		<div class="circle red" style="display: none;"></div>
            		<div class="circle green" style="display: none;"></div>
            		<div class="orange-question" style="display: none;">?</div>
            		<div class="hourglass" style="display:none;">
            			<img src="img/hourglass.png" style="height: 200px;" />
            		</div>
            	</div>
    		</div>
    		
    		<div class="row">
    			<div class="col-lg-12 col-md-12 text-center" style="padding: 5;">
            		<img id="start-button" class="img-fluid mx-auto" style="width: 40%; cursor: pointer; margin-bottom: 8px;" src="img/scan_qr.png">
            		<p class="mt-4" style="text-transform: uppercase;" ><?php
    echo (Localization::translate('Gatekeeper.Button.CheckParticipant'));
    ?></p>
            		<!-- message in case we are on a different browser than Safari at iOS -->
            		<p id="ios-camera-message" style="display: none; color: red;"><?php
    echo (Localization::translate('Camera.iOS'));
    ?></p>
            	</div>
    		</div>
    		
            <audio id="audio-error" src="audio/error.mp3" type="audio/mpeg"/>
            <audio id="audio-blank" src="audio/blank.mp3" type="audio/mpeg"/>
        	<audio id="audio-success" src="audio/success.mp3" type="audio/mpeg"/>
        	
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
                function(ret){
                    if(ret.result == 0){
                    	$("div.orange-question").show();
                        $("#audio-error")[0].play();
                    }else if(ret.result == 1){
                    	$("div.circle.green").show();
                        $("#audio-success")[0].play();
                    }else if(ret.result == 2){
                        $("div.circle.red").show();
                        $("#audio-error")[0].play();                    	
                    }else if(ret.result == 3){
                        $("div.hourglass").show();
                        $("#audio-error")[0].play();                    	
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
                // Hide the past results
                $("div.circle.red").hide();
                $("div.circle.green").hide();
                $("div.orange-question").hide();

                // Necessary in order to be able to play the success/error afterwards
                $("#audio-blank")[0].play();
                
                // Start the scanner and show the camera div
                scanner.start();
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
