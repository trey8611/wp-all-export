<?php

class PMXE_Export_Record extends PMXE_Model_Record {
		
	/**
	 * Initialize model instance
	 * @param array[optional] $data Array of record data to initialize object with
	 */
	public function __construct($data = array()) {
		parent::__construct($data);		
		$this->setTable(PMXE_Plugin::getInstance()->getTablePrefix() . 'exports');
	}						

    public function set_html_content_type(){
        return 'text/html';
    }

	public function generate_bundle( $debug = false)
	{
		// do not generate export bundle if not supported
		if ( ! self::is_bundle_supported($this->options) ) return;

		$uploads  = wp_upload_dir();

		//generate temporary folder
		$export_dir = wp_all_export_secure_file($uploads['basedir'] . DIRECTORY_SEPARATOR . PMXE_Plugin::UPLOADS_DIRECTORY, $this->id ) . DIRECTORY_SEPARATOR;
		$bundle_dir = $export_dir . 'bundle' . DIRECTORY_SEPARATOR;
		
		// clear tmp dir
		wp_all_export_rrmdir($bundle_dir);

		@mkdir($bundle_dir);		
						
		$friendly_name = sanitize_file_name($this->friendly_name);		

		$template  = "WP All Import Template - " . $friendly_name . ".txt";

		$templates = array();	

		$is_secure_import = PMXE_Plugin::getInstance()->getOption('secure');

		if ( ! $is_secure_import)
		{
			$filepath = get_attached_file($this->attch_id);
		}
		else
		{
			$filepath = wp_all_export_get_absolute_path($this->options['filepath']);
		}				
		
		@copy( $filepath, $bundle_dir . basename($filepath) );

		if ( ! empty($this->options['tpl_data']))
		{
			$template_data = array($this->options['tpl_data']);						

			$template_data[0]['source_file_name'] = basename($filepath);

			$template_options = maybe_unserialize($template_data[0]['options']);

			$templates[$template_options['custom_type']] = $template_data;			

			$readme = __("The other two files in this zip are the export file containing all of your data and the import template for WP All Import. \n\nTo import this data, create a new import with WP All Import and upload this zip file.", "wp_all_export_plugin");	

			file_put_contents($bundle_dir . 'readme.txt', $readme);
		}					

		file_put_contents($bundle_dir . $template, json_encode($templates));							

		if ($this->options['creata_a_new_export_file'] && ! empty($this->options['cpt']) and class_exists('WooCommerce') and in_array('shop_order', $this->options['cpt']) and empty($this->parent_id) )
		{
			$bundle_path = $export_dir . $friendly_name . '-' . ($this->iteration + 1) . '.zip';			
		}
		else
		{
			$bundle_path = $export_dir . $friendly_name . '.zip';			
		}		

		if ( @file_exists($bundle_path))
		{
			@unlink($bundle_path);
		}

		PMXE_Zip::zipDir($bundle_dir, $bundle_path);

		// clear tmp dir
		wp_all_export_rrmdir($bundle_dir);

		$exportOptions = $this->options;
		$exportOptions['bundlepath'] = wp_all_export_get_relative_path($bundle_path);
		$this->set(array(
			'options' => $exportOptions
		))->save();

		return $bundle_path;					
	}

	public function fix_template_options()
	{
		$options = $this->options;

		$is_options_changed = false;

		foreach ($options['ids'] as $ID => $value) 
		{
			switch ($options['cc_type'][$ID]) 
			{
				case 'media':					

					switch ($options['cc_options'][$ID]) 
					{
						case 'urls':
							$options['cc_label'][$ID] = 'url';
							$options['cc_value'][$ID] = 'url';
							$options['cc_type'][$ID] = 'image_url';
							break;	
						case 'filenames':
							$options['cc_label'][$ID] = 'filename';
							$options['cc_value'][$ID] = 'filename';
							$options['cc_type'][$ID] = 'image_filename';
							break;
						case 'filepaths':
							$options['cc_label'][$ID] = 'path';
							$options['cc_value'][$ID] = 'path';
							$options['cc_type'][$ID] = 'image_path';
							break;
						default:
							$options['cc_label'][$ID] = 'url';
							$options['cc_value'][$ID] = 'url';
							$options['cc_type'][$ID] = 'image_url';
							break;
					}

					$options['cc_name'][$ID] = 'media_images';					
					$options['cc_options'][$ID] = '{"is_export_featured":true,"is_export_attached":true,"image_separator":"|"}';											

					$new_fields = array('alt', 'description', 'caption', 'title');

					foreach ($new_fields as $value) 
					{
						$options['ids'][] = 1;
						$options['cc_label'][] = $value;
						$options['cc_php'][] = empty($options['ids']['cc_php'][$ID]) ? '' : $options['ids']['cc_php'][$ID];
						$options['cc_code'][] = empty($options['ids']['cc_code'][$ID]) ? '' : $options['ids']['cc_code'][$ID];
						$options['cc_sql'][] = empty($options['ids']['cc_sql'][$ID]) ? '' : $options['ids']['cc_sql'][$ID];
						$options['cc_options'][] = '{"is_export_featured":true,"is_export_attached":true,"image_separator":"|"}';
						$options['cc_type'][] = 'image_' . $value;
						$options['cc_value'][] = $value;
						$options['cc_name'][] = 'media_' . $value . 's';
						$options['cc_settings'][] = '';
					}	

					$is_options_changed = true;	

					break;
				case 'attachments':					
					$options['cc_type'][$ID] = 'attachment_url';
					$options['cc_options'][$ID] = '';
					$is_options_changed = true;
					break;
			}
		}

		if ( $is_options_changed ) $this->set(array('options' => $options))->save();		

		return $this;
	}

    public static function is_bundle_supported( $options )
    {	
    	$unsupported_post_types = array('comments', 'shop_order');
    	return ( empty($options['cpt']) and ! in_array($options['wp_query_selector'], array('wp_comment_query')) or ! empty($options['cpt']) and ! in_array($options['cpt'][0], $unsupported_post_types) ) ? true : false;
    }

    /**
	 * Clear associations with posts	 
	 * @return PMXE_Import_Record
	 * @chainable
	 */
	public function deletePosts() {
		$post = new PMXE_Post_List();					
		$this->wpdb->query($this->wpdb->prepare('DELETE FROM ' . $post->getTable() . ' WHERE export_id = %s', $this->id));
		return $this;
	}

	/**
	 * Delete associated sub exports
	 * @return PMXE_Export_Record
	 * @chainable
	 */
	public function deleteChildren(){
		$exportList = new PMXE_Export_List();
		foreach ($exportList->getBy('parent_id', $this->id)->convertRecords() as $i) {
			$i->delete();
		}
		return $this;
	}

	/**
	 * @see parent::delete()	 
	 */
	public function delete() {	
		$this->deletePosts()->deleteChildren();
		if ( ! empty($this->options['import_id']) and wp_all_export_is_compatible()){
			$import = new PMXI_Import_Record();
			$import->getById($this->options['import_id']);
			if ( ! $import->isEmpty() and $import->parent_import_id == 99999 ){
				$import->delete();
			}
		}	
		$export_file_path = wp_all_export_get_absolute_path($this->options['filepath']);
		if ( @file_exists($export_file_path) ){ 
			wp_all_export_remove_source($export_file_path);
		}
		if ( ! empty($this->attch_id) ){
			wp_delete_attachment($this->attch_id, true);
		}
		
		$wp_uploads = wp_upload_dir();	

		$file_for_remote_access = $wp_uploads['basedir'] . DIRECTORY_SEPARATOR . PMXE_Plugin::UPLOADS_DIRECTORY . DIRECTORY_SEPARATOR . md5(PMXE_Plugin::getInstance()->getOption('cron_job_key') . $this->id) . '.' . $this->options['export_to'];
		
		if ( @file_exists($file_for_remote_access)) @unlink($file_for_remote_access);

		return parent::delete();
	}
	
}
