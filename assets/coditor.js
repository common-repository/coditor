var currentEditFile;

jQuery(function($){
	$('body').on('click', '.ajax-action', function(e){
		e.preventDefault();

		var $this = $(this), parent = $(this).closest('li');
		var data = {
			action: 'coditor_process_ajax',
			path: $(this).data('path'),
			cmd: $(this).data('cmd')
		};
		var ext = data.path.split('.').pop();

		if(data.cmd == 'scandir' && $(this).hasClass('scanned')){
			$(this).next('ul').slideToggle(300, function(){
				$this.toggleClass('active');
			});
			return false;
		}

		if(data.cmd == 'savefile'){
			data.content = editor.getValue();
			data.file = currentEditFile;

			var annotations = editor.getSession().getAnnotations();
			if(annotations.length > 0){
				var err = '';
				for(var i = 0; i < annotations.length; i++){
					if(annotations[i].type == 'error'){
						err += '<li>'+annotations[i].type+': '+annotations[i].text+' on line '+(annotations[i].row + 1)+"</li>";
					}
				}

				if(err.length > 0){
					$('#error-dlg').html('<table><tr><td valign="top"><i class="fa fa-exclamation-triangle"></i></td><td><ul id="error-wrap">'+err+'</ul></td></tr></table>').dialog({
						width: 500,
						position: { my: "center", at: "center", of: $('#coditor-ide') }
					});

					return;
				}
			}
		}

		$.post(ajaxurl, data, function(response){
			switch(data.cmd){
				case "scandir":
					if(response.error > 0){
						alert(response.message);
					}else{
						parent.append(response.content);
						$this.addClass('scanned');
						$this.toggleClass('active');
					}
				break;

				case "readfile":
					if(response.error > 0){
						alert(response.message);
					}else{
						currentEditFile = data.path;
						switch(ext){
							case "php":
								editor.getSession().setMode('ace/mode/php');
								break;

							case "js":
								editor.getSession().setMode('ace/mode/javascript');
								break;

							case "html":
								editor.getSession().setMode('ace/mode/html');
								break;

							case "css":
								editor.getSession().setMode('ace/mode/css');
								break;

							default:
								editor.getSession().setMode('ace/mode/text');
								break;
						}

						editor.setValue(response.content, -1);

						if(!response.is_writable){
							$('#file-info').text('Readonly');
							editor.setReadOnly(true);
						}else{
							$('#file-info').text('');
							editor.setReadOnly(false);
						}

						$('#current-filename').text(currentEditFile);
					}
				break;

				case "savefile":
					if(response.hasOwnProperty('error')){
						alert(response.message);
					}
				break;
			}
		}, 'json');
	});

	$('.editor-action').on('click', function(e){
		e.preventDefault();

		var cmd = $(this).data('cmd');
		switch(cmd){
			case "undo":
				editor.execCommand("undo");
			break;

			case "redo":
				editor.execCommand("redo");
			break;

			case "find":
				editor.execCommand("find");
			break;

			case "about":
				$('#about-dlg').dialog({
					position: { my: "center", at: "center", of: $('#coditor-ide') }
				});
			break;
		}
	});

	$('#tab a').on('click', function(e){
		e.preventDefault();

		var target = $(this).attr('href');
		$('#tab li').removeClass('active');
		$('.tab-pane').removeClass('active');

		$(this).closest('li').addClass('active');
		$(target).addClass('active');
	});

	$(window).on('load', function(){
		editor = ace.edit("editor");
	    editor.setTheme("ace/theme/monokai");
	    editor.setShowPrintMargin(false);
	    
	    editor.commands.addCommand({
		    name: 'Save',
		    bindKey: {win: 'Ctrl-S',  mac: 'Command-S'},
		    exec: function(editor) {
		        var data = {
					action: 'coditor_process_ajax',
					path: '',
					cmd: 'savefile',
					content: editor.getValue(),
					file: currentEditFile
				};

				var annotations = editor.getSession().getAnnotations();
				if(annotations.length > 0){
					var err = '';
					for(var i = 0; i < annotations.length; i++){
						if(annotations[i].type == 'error'){
							err += '<li>'+annotations[i].type+': '+annotations[i].text+' on line '+(annotations[i].row + 1)+"</li>";
						}
					}

					if(err.length > 0){
						$('#error-dlg').html('<table><tr><td valign="top"><i class="fa fa-exclamation-triangle"></i></td><td><ul id="error-wrap">'+err+'</ul></td></tr></table>').dialog({
							width: 500,
							position: { my: "center", at: "center", of: $('#coditor-ide') }
						});

						return;
					}
				}

				$.post(ajaxurl, data, function(response){
					response = JSON.parse(response);
					if(response.hasOwnProperty('error')){
						alert(response.message);
					}
				});
		    },
		    readOnly: false
		});
	});
});