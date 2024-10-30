<?php
/*
Plugin Name: Coditor
Description: A standalone wordpress advanced code editor. it can edit your themes or plugins files.
Author: dr.iel
Author URI: mailto:lamer.idiot@gmail.com
License:     GPLv3 or later
Text Domain: coditor
Version: 1.1
 */

/*
Copyright (C) 2017 Dariel Pratama

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
class Coditor{
	/**
	 * it's actually a wp-content path
	 */
	public $root_path;

	/**
	 * error constants
	 */
	const ERROR_CANNOT_WRITE = "Cannot write to file, permission denied.";
	const ERROR_DIR_TRAVERSAL = "Directroy traversal is not allowed";
	const FILE_HAS_BEEN_SAVED = "File has been saved";

	function __construct($path){
		$this->root_path = $path;
		add_action('admin_menu', array($this, 'coditor_create_menu_page'));
		add_action('admin_head', array($this, 'coditor_register_scripts'));
		add_action('wp_ajax_coditor_process_ajax', array($this, 'coditor_process_ajax'));
	}

	/**
	 * register scripts and styles
	 * @return void
	 */
	function coditor_register_scripts(){
		wp_enqueue_style('coditor-jquery-ui', plugins_url('assets/jquery-ui.min.css', __FILE__));
		wp_enqueue_style('coditor-jquery-ui-themes', plugins_url('assets/jquery-ui.theme.min.css', __FILE__));
		wp_enqueue_style('coditor-css', plugins_url('assets/coditor.css', __FILE__));
		wp_enqueue_style('coditor-fa', plugins_url('assets/font-awesome/css/font-awesome.min.css', __FILE__));
		wp_enqueue_script('coditor-js', plugins_url('assets/coditor.js', __FILE__));
		wp_enqueue_script('coditor-ace', plugins_url('ace/src-min-noconflict/ace.js', __FILE__));
		wp_enqueue_script('jquery-ui-dialog');
	}

	/**
	 * create new admin page
	 * @return void
	 */
	function coditor_create_menu_page(){
		add_menu_page(
	        __( 'Coditor', 'coditor' ),
	        'Coditor',
	        'manage_options',
	        'coditor',
	        array($this, 'coditor_menu_page_output'),
	        'dashicons-editor-code',
	        6
	    );
	}

	/**
	 * HTML output for coditor page
	 * @return void
	 */
	function coditor_menu_page_output(){
		$themes_path = $this->root_path."/themes";
		$plugins_path = $this->root_path."/plugins";

		?>
		<div id="coditor-header">
			<a href=""><i class="fa fa-code"></i> Coditor</a>
			<div id="toolbar">
				<ul>
					<li><a href="" class="ajax-action" data-cmd="savefile" data-path=""><i class="fa fa-save"></i></a></li>
					<li><a href="" class="editor-action" data-cmd="undo"><i class="fa fa-mail-reply"></i></a></li>
					<li><a href="" class="editor-action" data-cmd="redo"><i class="fa fa-mail-forward"></i></a></li>
					<li><a href="" class="editor-action" data-cmd="find"><i class="fa fa-search"></i></a></li>
					<li><a href="" class="editor-action" data-cmd="about"><i class="fa fa-info"></i></a></li>
				</ul>
			</div>
		</div>
		<div id="coditor-ide">
			<div id="file-tree">
				<ul id="tab">
					<li class="active"><a href="#themes"><?php _e('Themes', 'coditor'); ?></a></li>
					<li><a href="#plugins"><?php _e('Plugins', 'coditor'); ?></a></li>
				</ul>
				<div class="tab-content">
					<div class="tab-pane active" id="themes">
						<?php echo $this->scandir($themes_path); ?>
					</div>
					<div class="tab-pane" id="plugins">
						<?php echo $this->scandir($plugins_path); ?>
					</div>
				</div>
			</div>
			<div id="editor"></div>
		</div>
		<div id="coditor-footer">
			<span id="current-filename">&nbsp;</span>
			<span id="file-info"></span>
		</div>
		<div id="about-dlg" title="About Coditor">
			<i class="fa fa-code fa-6"></i>
			<h2>Coditor v1.0</h2>
			<p>A standalone code editor for editing wordpress themes or plugins.<br />&copy; copyleft 2017 - dr.iel</p>
		</div>
		<div id="error-dlg" title="Error"></div>
		<?php
	}

	/**
	 * process ajax and send response to client
	 * @return void
	 */
	function coditor_process_ajax(){
		header('Content-type: application/json');
		$response = new stdClass();

		switch($_POST['cmd']){
			case "scandir":
				if($this->secure_file_path($_POST['path']) === TRUE){
					$response->error = 0;
					$response->content = $this->scandir($this->root_path.$_POST['path']);
				}else{
					$response->error = 1;
					$response->message = self::ERROR_DIR_TRAVERSAL;
				}

				echo json_encode($response);
			break;

			case "readfile":
				if($this->secure_file_path($_POST['path']) === TRUE){
					$response->error = 0;
					$response->content = file_get_contents($this->root_path.$_POST['path']);
					$response->is_writable = is_writable($this->root_path.$_POST['path']);
				}else{
					$response->error = 1;
					$response->message = self::ERROR_DIR_TRAVERSAL;
				}

				echo json_encode($response);
			break;

			case "savefile":
				if($this->secure_file_path($_POST['file']) === TRUE){
					$content = stripslashes($_POST['content']);
					if(is_writable($this->root_path.$_POST['file'])){
						file_put_contents($this->root_path.$_POST['file'], $content);
						$response->error = 0;
						$response->message = self::FILE_HAS_BEEN_SAVED;
					}else{
						$response->error = 1;
						$response->message = self::ERROR_CANNOT_WRITE;
					}
				}else{
					$response->error = 1;
					$response->message = self::ERROR_DIR_TRAVERSAL;
				}

				echo json_encode($response);
			break;
		}
		wp_die();
	}

	/**
	 * scan directory
	 * @param  string $dir
	 * @return string
	 */
	function scandir($dir){
		$html = '<ul>';
		foreach (new DirectoryIterator($dir) as $file){
			if($file->isDot()) continue;

			$icon = '<i class="fa fa-file-code-o"></i>';
			if($file->isDir()){
				$icon = '<i class="fa fa-folder"></i>';
			}

			preg_match('/\/(themes|plugins)(.*)$/', $file->getPath(), $path);

			$fullPath = $path[0].'/'.$file->getFileName();

			$html .= '<li>';
			$html .= '<a href="" data-path="'.$fullPath.'" class="file ajax-action" data-fp="'.$file->getPath().'" data-cmd="'.($file->isDir() ? "scandir":"readfile").'">'.$icon.' '.$file->getFileName().'</a>';
			$html .= '</li>';
		}

		$html .= '</ul>';

		return $html;
	}

	/**
	 * check if path is secure.
	 * @param  string $file_path
	 * @return boolean
	 */
	function secure_file_path($file_path){
		$realBasePath = realpath($this->root_path);

		$userPath = $this->root_path.$file_path;
		$realUserPath = realpath($userPath);

		if ($realUserPath === false || strpos($realUserPath, $realBasePath) !== 0) {
		    return false;
		}

		return true;
	}
}

$coditor = new Coditor(WP_CONTENT_DIR);
