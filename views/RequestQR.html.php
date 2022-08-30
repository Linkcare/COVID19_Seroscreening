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
    </div>
	<script>
		$("#qr_id").focus();
		$('#btnRequestQR').click(function(){
			var id_regexp = new RegExp('^[a-zA-Z0-9_-]{5}$');
			var qr_id = $('input#qr_id').val();
			if(id_regexp.test(qr_id)){
				window.location.href = '?id=' + qr_id;
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
