<!DOCTYPE html>
<html lang="en">
  <head>
	<meta charset="utf-8">
	<title>OpenPort LogCfg Generator</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="description" content="">
	<meta name="author" content="">

	<!-- Le styles -->
	<link href="css/bootstrap.css" rel="stylesheet">
	<style>
	  body {
		padding-top: 60px; /* 60px to make the container go all the way to the bottom of the topbar */
	  }
	</style>
	<link href="css/bootstrap-responsive.css" rel="stylesheet">

	<!-- HTML5 shim, for IE6-8 support of HTML5 elements -->
	<!--[if lt IE 9]>
	  <script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
	<![endif]-->

	<!-- Fav and touch icons -->
	<link rel="apple-touch-icon-precomposed" sizes="144x144" href="ico/apple-touch-icon-144-precomposed.png">
	<link rel="apple-touch-icon-precomposed" sizes="114x114" href="ico/apple-touch-icon-114-precomposed.png">
	<link rel="apple-touch-icon-precomposed" sizes="72x72" href="ico/apple-touch-icon-72-precomposed.png">
	<link rel="apple-touch-icon-precomposed" href="ico/apple-touch-icon-57-precomposed.png">
	<link rel="shortcut icon" href="ico/favicon.png">
  </head>

  <body>
	
	<div class="navbar navbar-inverse navbar-fixed-top">
		<div class="navbar-inner">
			<div class="container">
				<a class="brand" href="#">Subaru OpenPort LogCfg Generator</a>
			</div>
		</div>
	</div>

	<div class="container">
		
		<div id="alert-container">
			<?php if(isset($_GET['alert'])) : ?>
			<div class="alert <?= $_GET['alert']['type'] ?: 'alert-warning' ?>">
				<button type="button" class="close" data-dismiss="alert">&times;</button>
				<?= $_GET['alert']['msg'] ?>
			</div>
			<?php endif; ?>
		</div>
		
		<form class="form-horizontal" enctype="multipart/form-data" method="POST" action="generate.php">
			<fieldset>
				<legend>Convert RomRaider Profile to Standalone LogCfg.txt</legend>
				<div class="control-group">
					<label class="control-label" for="Profile">RomRaider Profile</label>
					<div class="controls">
						<div class="input-prepend">
							<span class="add-on"><i class="icon-file"></i></span>
							<input type="file" name="Profile" id="Profile" />
						</div>
						<span class="help-block">Saved RomRaider Logger profile.xml</span>
					</div>
				</div>
				<div class="control-group">
					<label class="control-label" for="Definition">Logger Definition</label>
					<div class="controls">
						<select name="Definition" id="Definition">
							<option value="">-- Pick Logger Definition --</option>
							<?php
								foreach(glob('../misc/loggerdefs/*xml') as $path) {
									$fileArray = pathinfo($path);
									echo "<option>{$fileArray['basename']}</option>";
								}
							?>
						</select>
						<span class="help-block">
							Which logger definition should be used to generate the file?
						</span>
					</div>
				</div>
				<div class="control-group">
					<label class="control-label" for="ECUID">ECU ID</label>
					<div class="controls">
						<select name="ECUID" id="ECUID">
							<option value="">-- Pick ECU --</option>
						</select>
						<span class="help-block">
							You can find your ECU ID by opening RomRaider Logger when the OpenPort is connected to your car.  
							It will be in the bottom right corner.
						</span>
					</div>
				</div>
				<div class="control-group">
					<label class="control-label">Logging Method</label>
					<div class="controls">
						<label class="radio inline"><input type="radio" name="Type" value="ssmk" checked="checked" /> K-Line</label>
						<label class="radio inline"><input type="radio" name="Type" value="ssmcan" /> CAN BUS</label>
						<span class="help-block">
							CAN requires 08+ Subarus with the CAN Logging Patch applied in ECUEdit and allows for much faster logging
						</span>
					</div>
				</div>
				<div class="control-group">
					<label class="control-label">Logging Trigger</label>
					<div class="controls">
						<label class="radio inline"><input type="radio" name="Trigger" value="defogger" checked="checked" /> Defogger Switch</label>
						<label class="radio inline"><input type="radio" name="Trigger" value="engine" /> Engine Start/Stop</label>
						<span class="help-block">
							What event should trigger a log start/stop event?
						</span>
					</div>
				</div>
				<div class="form-actions">
					<button type="submit" class="btn btn-primary">Generate File</button>
				</div>
			</fieldset>
		</form>
					
		<!-- <textarea style=" width: 100%; height: 500px"></textarea> -->
		
	</div> <!-- /container -->

	<!-- Le javascript
	================================================== -->
	<!-- Placed at the end of the document so the pages load faster -->
	<script src="http://code.jquery.com/jquery-latest.js"></script>
	<script src="js/bootstrap.js"></script>
	<script type="text/javascript">
		$('#Definition').bind('change', function() {
			if($(this).val() != "") {
				var select = $('#ECUID');
				select.attr('disabled','disabled');
				select.find('option:not(:first)').remove();
				$.ajax({
					url: "ajax/get_ecus.php",
					type: "POST",
					dataType: "json",
					data: { Definition: $(this).val() },
					success: function(data) {
						for(var i = 0; i < data.length; i++) {
							var option = $('<option></option>').html(data[i]);
							select.append(option);
						}
						select.removeAttr('disabled');
					},
					error: function(jqXHR) {
						var alert = $('<div class="alert alert-warning"><button type="button" class="close" data-dismiss="alert">&times;</button>' + jqXHR.responseText + '</div>');
						$('#alert-container').append(alert);
						select.removeAttr('disabled');
					}
				});
			}
		});
	</script>
	
  </body>
</html>