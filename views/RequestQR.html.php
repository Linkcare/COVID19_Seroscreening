    <div class="container col-lg-4 col-md-8">
    	<div class="form-group" style="margin-top: 20px; margin-bottom: 20px;">
    		<p class="mt-4 border border-secondary rounded text-center" style="text-transform: uppercase;">
        		<?php
        echo (Localization::translate('RequestQR.Title'));
        ?>
        	</p>
        </div>
        <div style="text-align: center;">
        	<img src="img/test_qr_example.png">
        </div>
        <form>
            <div class="form-group" style="margin-top: 20px;">
        		<input class="form-control" style="width: 135px; margin-left: auto; margin-right: auto;" name="qr_id" id="qr_id" pattern="^[a-zA-Z0-9_-]*$" maxlength=5 minlength=5 required>
            </div>
            <div>
            	<button id="btnRequestQR" type='submit' class="btn btn-success text-center btn-block btn-lg" style="text-transform: uppercase;"><?php
            echo (Localization::translate('RequestQR.Button.Verify'));
            ?>
                </button>   
            </div>
        </form>
        
        <!-- Linkcare Bio logo link -->
        
        <div style="text-align: center; display: flex; flex-flow: column; height: 140px;">
    		<div style="flex: 1;"></div>
        	<div>
            	<a href="https://linkcare.es<?php
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
    </div>
	<script>
		$("#qr_id").focus();
		$('#btnRequestQR').on('submit click',function(e){
			e.preventDefault();
			var id_regexp = new RegExp('^[a-zA-Z0-9_-]{5}$');
			var qr_id = $('input#qr_id').val();
			if(id_regexp.test(qr_id)){
				window.location.href = $(location).attr("origin") + '?id=' + qr_id;
			}
		});

		$("#qr_id").on('keydown keypress', function(e) {
			var id_regexp = new RegExp('^[a-zA-Z0-9_-]*$');
		  	var str = e.key;
		  	if (!id_regexp.test(str)) {
	       		e.preventDefault();
	    	}
		});
    </script>
	</body>
</html>
