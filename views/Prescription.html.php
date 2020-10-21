         <div id="div-qr-video" style="display:flex;position: absolute;top: 0;width:100%;height:100%;align-items: center;justify-content: center;z-index:1000;">
            <div style="position:relative;max-height:90%;background-color:white;border-style: solid;border-width: 2px;box-shadow: 5px 10px rgba(0,0,0,0.1);border-radius: 5px;">
              	<video id="qr-video" class="embed-responsive" style="transform: scaleX(-1);/*! position: absolute; */top: 0;"></video>
          		<p id="stop-button" style="position: absolute;top: 0;text-align: right;width: 100%;padding-right: 15px;font-size: 18px;font-weight: bold;cursor: pointer;text-shadow:0.1rem 0.1rem rgba(255,255,255,.5);">&times;</p>
            </div>
        </div>
        
        <div class="container col-lg-4 col-md-8">
        <br>
        	<p>
        	<a href="./index.php?id=<?php
        echo ($kit->getId() . "&culture=" . Localization::getLang());
        ?>"><i class="btn fa fa-arrow-left" aria-hidden="true"></i></a>
        	</p>
        	<div class="form-group">
                <label for="id"><b><?php

                echo (Localization::translate('Prescription.Label'));
                ?>:</b></label>

                    <div id="prescriptionInfo" style="display: none;">
                  		<ul> 
                  			<li><b><?php
                    echo (Localization::translate('Prescription.Id.Label'));
                    ?>:</b> <span id="prescriptionId"></span></li>
                    		<li><b><?php
                    echo (Localization::translate('Prescription.Program.Label'));
                    ?>:</b> <span id="prescriptionProgram"></span></li>
                    		<li><b><?php
                    echo (Localization::translate('Prescription.Team.Label'));
                    ?>:</b> <span id="prescriptionTeam"></span></li>
                    <li><b><?php
                    echo (Localization::translate('Prescription.ParticipantId.Label'));
                    ?>:</b> <span id="prescriptionParticipantId"></span></li>
                    <li><b><?php
                    echo (Localization::translate('Prescription.Expires.Label'));
                    ?>:</b> <span id="prescriptionExpires"></span></li>
                    <li><b><?php
                    echo (Localization::translate('Prescription.Rounds.Label'));
                    ?>:</b> <span id="prescriptionRounds"></span></li>
                  		</ul>
          			</div>

            		<input type="text" class="form-control" id="labelInput">
          	</div>
          	
          	<div id="prescriptionError" style="color: red; display: none;"><?php
        echo (Localization::translateError(ErrorInfo::PRESCRIPTION_WRONG_FORMAT));
        ?></div>
          	
          	<br>          	
        	<div class="text-center">
        		<button id="start-button" class="btn btn-light mx-auto px-1">
        			<img id="start-button" class="img-fluid mx-auto" style="width: 60%;" src="img/qr.png">
    			</button>        		
        	</div>
        	<br>
        	<input id="btnSubmit" class="btn btn-success text-center btn-block btn-lg" target_url="<?php
        echo ($kit->getInstance_url())?>" value="<?php

        echo (Localization::translate('KitInfo.Button.Register'));
        ?>">     
        	<br>
        	
        	<!-- div to show any errors at the qr video scanning -->
        	<p id="cam-errors"></p>
        </div>  
	</body>
</html>


<!-- Script area for the button visibility and submit button url composing -->
<script type="text/javascript">
	/* Hide the qr-scanner div in order to show it when it's requested */
	$('#div-qr-video').hide();

	/* The submit button will be started dinamically and the prescription_id added from the input */
	$("#btnSubmit").click(function (e) {
		var kitId = $(this).attr("target_url");
		window.location.href = kitId + "&prescription_id=" + encodeURIComponent($("#labelInput").val());
	});

	/* Disable and enable of the submit button according to the text input, if it's empty disable it */
	$(document).ready(function() {
	    var submit = $("#btnSubmit");
	    submit.prop('disabled', true);
	    
	    $("#labelInput").on('input change', function() {
	        submit.prop('disabled', !$(this).val().length);
	        $("#prescriptionError").hide();
	        $("#labelInput").css( "background-color", "" );
    	});
	});
</script>


<!-- Script area for the qr-scanner plugin -->
<script type="module">
    /* Import and Worker Path */
    import QrScanner from "./js/qr-scanner.min.js";
    QrScanner.WORKER_PATH = './js/qr-scanner-worker.min.js';
    
    /* Variables from the html */        

    const video = document.getElementById('qr-video');
    const camQrResult = document.getElementById('labelInput');
    const camError = document.getElementById('cam-errors');
    const submitButton = document.getElementById('btnSubmit');

    /* Helper functions */

    //Function to stop the qr camera scan from working and hide its div
    function cameraStop(){
        scanner.stop();
        //hide again the camera div
        $('#div-qr-video').hide();        
    }
    
    //Function to write the results of the qr scan at a certain label
    function setResult(label, submitButton, result) {
    	if(result.includes(";")){
	       	$.post(
				window.location,
              	{prescription: result},
                function(jsonPrescription){
                    if(jsonPrescription.success == 0) {
                        $("#prescriptionInfo").hide();
                        $("#labelInput").show();
                        $("#prescriptionError").show();
                    } else{   
                        // Fill the prescription fields
                        console.log(jsonPrescription);
                        $("#prescriptionId").html(jsonPrescription.id);
                        $("#prescriptionProgram").html(jsonPrescription.program);
                        $("#prescriptionTeam").html(jsonPrescription.team);
                        $("#prescriptionParticipantId").html(jsonPrescription.participantId);
                        $("#prescriptionExpires").html(jsonPrescription.expirationDate);
                        $("#prescriptionRounds").html(jsonPrescription.rounds);
                        
                        $("#prescriptionInfo").show();
                        
                        $("#labelInput").hide();
                        $("#prescriptionError").hide();

                        label.value = result; 
                        submitButton.disabled = false; 
                        $("#labelInput").css( "background-color", "#D1E9FF" );       
 
                    }
                }
            );      		   	
	    }
        //Once a result has been scanned, stop the scanner and hide the camera div
        cameraStop();             
    }

    /* QR Scanner functionality and events */

    const scanner = new QrScanner(video, result => setResult(camQrResult, submitButton, result), error => {   
        //The "No QR code found" error is common when there is no qr code being scanned, unless it's different,
        //write the error at the corresponding div at the bottom of the page
        if(error != "No QR code found"){                        
            camError.textContent = error;
            camError.style.color = 'inherit';
        }                    
    });
    
    document.getElementById('start-button').addEventListener('click', () => {
        scanner.start();
        //show the camera div
        $('#div-qr-video').show();
    });

    document.getElementById('stop-button').addEventListener('click', () => {
        cameraStop();       
    });
</script>  