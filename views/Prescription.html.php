         <div id="div-qr-video" style="display:flex;position: absolute;top: 0;width:100%;height:100%;align-items: center;justify-content: center;z-index:1000;">
            <div style="position:relative;max-height:90%;background-color:white;border-style: solid;border-width: 2px;box-shadow: 5px 10px rgba(0,0,0,0.1);border-radius: 5px;">
              	<video id="qr-video" class="embed-responsive" style="transform: scaleX(-1);/*! position: absolute; */top: 0;"></video>
          		<p id="stop-button" style="position: absolute;top: 0;text-align: right;width: 100%;padding-right: 15px;font-size: 18px;font-weight: bold;cursor: pointer;text-shadow:0.1rem 0.1rem rgba(255,255,255,.5);">&times;</p>
            </div>
        </div>

        <div class="container col-lg-4 col-md-8">
        	<p>
       		 <i id="back-arrow" class="btn fa fa-arrow-left" aria-hidden="true" style="cursor: pointer;"></i>
        	</p>
        	<div class="form-group">
        		<h4 style="text-align: center;"><b><?php
        echo (Localization::translate('Prescription.Title'));
        ?></b></h4>                

                    <div id="prescriptionInfo" style="display: none;">
                  		<ul>
                  			<li id=prescriptionIdBlock"" style="display:none;"><b><?php
                    echo (Localization::translate('Prescription.Id.Label'));
                    ?>:</b> <span id="prescriptionId"></span></li>
                    		<li id="prescriptionProgramBlock" style="display:none;"><b><?php
                    echo (Localization::translate('Prescription.Program.Label'));
                    ?>:</b> <span id="prescriptionProgram"></span></li>
                    		<li id="prescriptionTeamBlock" style="display:none;"><b><?php
                    echo (Localization::translate('Prescription.Team.Label'));
                    ?>:</b> <span id="prescriptionTeam"></span></li>
                    		<li id="prescriptionNameBlock" style="display:none;"><b><?php
                    echo (Localization::translate('Prescription.PatientName.Label'));
                    ?>:</b> <span id="prescriptionName"></span></li>
                    		<li id="prescriptionEmailBlock" style="display:none;"><b><?php
                    echo (Localization::translate('Prescription.Email.Label'));
                    ?>:</b> <span id="prescriptionEmail"></span></li>
                    		<li id="prescriptionPhoneBlock" style="display:none;"><b><?php
                    echo (Localization::translate('Prescription.Phone.Label'));
                    ?>:</b> <span id="prescriptionPhone"></span></li>
                    		<li id="prescriptionParticipantIdBlock" style="display:none;"><b><?php
                    echo (Localization::translate('Prescription.ParticipantId.Label'));
                    ?>:</b> <span id="prescriptionParticipantId"></span></li>
                    		<li id="prescriptionExpiresBlock" style="display:none;"><b><?php
                    echo (Localization::translate('Prescription.Expires.Label'));
                    ?>:</b> <span id="prescriptionExpires"></span></li>
                    		<li id="prescriptionRoundsBlock" style="display:none;"><b><?php
                    echo (Localization::translate('Prescription.Rounds.Label'));
                    ?>:</b> <span id="prescriptionRounds"></span></li>
                  		</ul>
          			</div>
          			
            		<div class="form-check" style="margin-top: 15px;">
                      	<input class="form-check-input" type="checkbox" value="" id="participant_ref">
                      	<label id="label-text" class="form-check-label" for="participant_ref">
                        	<?php

                        echo (Localization::translate('Prescription.Label.Checkbox'));
                        ?>
                      	</label>
                    </div>
            		<input type="hidden" id="qr_code">
          	</div>

          	<div id="info-div">
          		<p><?php

            echo (Localization::translate('Prescription.Info'));
            ?></p>
            	<div style="text-align: center;">
                	<img class="img-fluid mx-auto" style="width: 55%;" src="img/ePrescription.png">
                	&nbsp;
                	<img class="img-fluid mx-auto" style="width: 20%;" src="img/phone-min.png">
            	</div>
          	</div>

          	<div id="button-div">
              	<p><?php
            echo (Localization::translate('Camera.Open.Message'));
            ?>:</p>

            	<div class="text-center">
            		<img id="start-button" class="img-fluid mx-auto" style="width: 30%; cursor: pointer; margin-bottom: 8px;" src="img/camera.png">
            		
            		<!-- message and disabled button in case we are on a different browser than Safari at iOS -->
            		<img id="start-button-disabled" class="img-fluid mx-auto" style="display: none; width: 30%; margin-bottom: 8px;" src="img/camera_disabled.png">
        			<p id="ios-camera-message" style="display: none; color: red;"><?php
        echo (Localization::translate('Camera.iOS'));
        ?></p>
            	</div>

        	</div>

        	<input id="btnSubmit" type="button" style="margin-bottom: 10px;" class="btn btn-success text-center btn-block btn-lg" target_url="<?php
        echo ($kit->generateURLtoLC2())?>" value="<?php

        echo (Localization::translate('Prescription.Button.Start'));
        ?>">

    		<!-- div to display any errors when scanning a qr code -->
        	<p id="cam-errors" style="display: none;"></p>
        </div>
	</body>
</html>


<!-- Script area for the button visibility and submit button url composing -->
<script type="text/javascript">
	/* Hide the qr-scanner div in order to show it when it's requested */
	$('#div-qr-video').hide();

	/* The submit button will be started dinamically and the prescription added from the input */
	$("#btnSubmit").click(function (e) {
		/* If a prescription was scanned from a QR, it has priority over the check to enter manually */
		if($('#qr_code').val()){			
			var prescriptionStr = $("#qr_code").val();			
			$.get(
	        	'actions.php',
	            {action: 'create_admission', prescription: prescriptionStr},
	            function(targetUrl){
	                window.location.href = targetUrl;
	            }
	        );
		} else if($("#participant_ref").prop('checked')){
			var participant_ref_check = '';
			$.get(
	        	'actions.php',
	            {action: 'create_admission', participant: participant_ref_check},
	            function(targetUrl){
	                window.location.href = targetUrl;
	            }
	        );
		}
	});

	$("#back-arrow").click(function (e) {
		if($("#participant_ref").is(":hidden")){
			//If the participant_ref was hidden, then we are showing the scanned prescription information
			// then we will want to go back to the previous status where we were showing the other fields
			// and clean the input, while hidding the prescription information
            $("#prescriptionInfo").hide();
            $("#personalPrescriptionInfo").hide();

            $("#qr_code").val("");
            $("#participant_ref").prop('checked', false);
			$("#participant_ref").show();
            $("#label-text").show();
            $("#info-div").show();
            $("#button-div").show();

    	    $("#btnSubmit").prop('disabled', true);


		} else {
			//Else we want to go back to the index, meaning we are showing the fields and no prescription has been scanned
			// and we just want to go back
			window.location.href = "<?php
echo ("./index.php?id=" . $kit->getId() . "&culture=" . Localization::getLang());
?>";
		}
	});

	

	/* Disable and enable of the submit button according to the text input, if it's empty disable it */
	$(document).ready(function() {
	    var submit = $("#btnSubmit");
	    submit.prop('disabled', true);
				
	    $("#participant_ref").change(function() {
	        submit.prop('disabled', !$(this).prop('checked'));
	        $("#prescriptionError").hide();
	        $("#participant_ref").css( "background-color", "" );
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
    const camQrResult = document.getElementById('qr_code');
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
        $.get(
			'actions.php',
            {action: 'set_prescription', prescription: result},
            function(jsonPrescription){
                //Uncheck the checkbox to enter manually
                $( "#participant_ref" ).prop( "checked", false );
                
                if(jsonPrescription.success == 0 || jsonPrescription.success == undefined) {
                    $("#prescriptionInfo").hide();
                    $("#personalPrescriptionInfo").hide();
                    $("#participant_ref").show();
                    $("#prescriptionError").show();
                    window.location.href='error.php?error=PRESCRIPTION_WRONG_FORMAT&return=prescription&culture=<?php

                    echo (Localization::getLang());
                    ?>';
                } else{
                    // Fill the prescription fields
                    //Prescription QR                        
                    if(jsonPrescription.id != null && jsonPrescription.id != '') {
                        $("#prescriptionId").html(jsonPrescription.id);
                        $("#prescriptionIdBlock").show();
                    }
                    if(jsonPrescription.program != null && jsonPrescription.program != '') {
                        $("#prescriptionProgram").html(jsonPrescription.program);
                        $("#prescriptionProgramBlock").show();
                    }
                    if(jsonPrescription.team != null && jsonPrescription.team != '') {
                        $("#prescriptionTeam").html(jsonPrescription.team);
                        $("#prescriptionTeamBlock").show();
                    }
                    if(jsonPrescription.name != null && jsonPrescription.name != '') {
                        $("#prescriptionName").html(jsonPrescription.name);
                        $("#prescriptionNameBlock").show();
                    }
                    if(jsonPrescription.email != null && jsonPrescription.email != '') {
                        $("#prescriptionEmail").html(jsonPrescription.email);
                        $("#prescriptionEmailBlock").show();
                    }
                    if(jsonPrescription.phone != null && jsonPrescription.phone != '') {
                        $("#prescriptionPhone").html(jsonPrescription.phone);
                        $("#prescriptionPhoneBlock").show();
                    }
                    if(jsonPrescription.participantId != null && jsonPrescription.participantId != '') {
                        $("#prescriptionParticipantId").html(jsonPrescription.participantId);
                        $("#prescriptionParticipantIdBlock").show();
                    }
                    if(jsonPrescription.expirationDate != null && jsonPrescription.expirationDate != '') {
                       $("#prescriptionExpires").html(jsonPrescription.expirationDate);
                       $("#prescriptionExpiresBlock").show();
                    }
                    if(jsonPrescription.rounds != null) {
                       $("#prescriptionRounds").html(jsonPrescription.rounds);
                       $("#prescriptionRoundsBlock").show();
                    }
                    $("#prescriptionInfo").show();                        

                    //Now that a prescription has been scanned, hide the rest of the page
                    $("#participant_ref").hide();
                    $("#label-text").hide();
                    $("#info-div").hide();
                    $("#button-div").hide();
                    $("#prescriptionError").hide();

                    //Write the scanned data into the hidden input label
                    label.value = result;
                    //Enable the start button
                    submitButton.disabled = false;
                }
        });
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

    //Get the user agent
    var ua = detect.parse(navigator.userAgent);
    // in case we are at iOS using Firefox or Chrome, show a warning message to notify the user that the camera won't work
    if(ua.browser.family != 'Mobile Safari' && ua.os.family === 'iOS') {
        $("#start-button").hide();
    	$("#ios-camera-message").show();
        $("#start-button-disabled").show();
    }else{
        //else add the scanner button listener functionality
        document.getElementById('start-button').addEventListener('click', () => {
            scanner.start();
            //show the camera div
            $('#div-qr-video').show();
        });
    }

    document.getElementById('stop-button').addEventListener('click', () => {
        cameraStop();
    });
</script>
