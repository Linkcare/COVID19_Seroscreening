<div style="display: flex; flex-flow: column;height: calc(100% - 52px);">
    <div class="container col-lg-4 col-md-8" style="text-align: center;">
    	<img src="img/caixabank-logo.png" alt="CaixaBank Logo" style="width: 330px;">
    </div>
    <div class="container col-lg-4 col-md-8" style="text-align: center;display: flex;flex-flow: column;justify-content: center;flex: 1;">
    	<!-- Button trigger modal -->
    	<button type="button" class="btn btn-primary btn-lg btn-block" data-toggle="modal" data-target="#autoadminVideoModal" style="padding: 22px;"><?php
    echo (Localization::translate('Autoadmin.Button.Video'));
    ?></button>
    
    	<!-- Button link to registry -->
        <a class="btn btn-primary btn-lg btn-block" href="<?php
        echo $GLOBALS['URL_START_AUTOADMINISTERED'];
        ?>" role="button" style="margin-top: 30px; padding: 22px;"><?php

        echo ($GLOBALS['BUTTON_AUTOADMINISTERED']);
        ?></a>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="autoadminVideoModal" tabindex="-1" role="dialog" aria-labelledby="autoadminVideoModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document" style="max-width: 800px; margin: 30px auto;">
    <div class="modal-content">
      <div class="modal-body">
      	<button type="button" style="position: absolute;top: 0; right: -8; text-align: right;width: 100%;padding-right: 10px;font-size: 30px;border-color: black;font-weight: bold;background: transparent;" class="close" data-dismiss="modal" aria-label="Close">
        	<span aria-hidden="true">&times;</span>
      	</button>
        <div class="embed-responsive embed-responsive-16by9" style="position:relative; padding:0px;">
  			<video class="embed-responsive-item modal-video" controls="true">
  				<source src="<?php
    echo $GLOBALS['VIDEO_TUTORIAL_WONDFO']?>" type="video/mp4">
            </video>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
	$('#autoadminVideoModal').on('shown.bs.modal', function (e) {
		var videoElem = $(this).find(".modal-video")
    	videoElem.trigger('play');
  	});
	$('#autoadminVideoModal').on('hide.bs.modal', function (e) {
		$(this).find(".modal-video").trigger('pause');
    });
	$('#autoadminVideoModal').find('.modal-video').on('ended',function(){
		$('#autoadminVideoModal').modal('toggle');
    });
</script>
