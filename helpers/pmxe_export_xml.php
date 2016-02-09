<?php
// Export XML helper
function pmxe_export_xml($exportQuery, $exportOptions, $preview = false, $is_cron = false, $file_path = false, $exported_by_cron = 0){	

	require_once PMXE_ROOT_DIR . '/classes/XMLWriter.php';
	
	$xmlWriter = new PMXE_XMLWriter();
	$xmlWriter->openMemory();
	$xmlWriter->setIndent(true);
	$xmlWriter->setIndentString("\t");
	$xmlWriter->startDocument('1.0', $exportOptions['encoding']);
	$xmlWriter->startElement($exportOptions['main_xml_tag']);	

	if ($is_cron)
	{							
		if ( ! $exported_by_cron )
		{
			$additional_data = apply_filters('wp_all_export_additional_data', array(), $exportOptions);

			if ( ! empty($additional_data))
			{
				foreach ($additional_data as $key => $value) 
				{
					$xmlWriter->startElement(preg_replace('/[^a-z0-9_-]/i', '', $key));
						$xmlWriter->writeData($value);
					$xmlWriter->endElement();		
				}
			}
		}					
	}
	else
	{

		if ( empty(PMXE_Plugin::$session->file) ){

			$additional_data = apply_filters('wp_all_export_additional_data', array(), $exportOptions);

			if ( ! empty($additional_data))
			{
				foreach ($additional_data as $key => $value) 
				{
					$xmlWriter->startElement(preg_replace('/[^a-z0-9_-]/i', '', $key));
						$xmlWriter->writeData($value);
					$xmlWriter->endElement();		
				}
			}
		}			
	}
	
	global $wpdb;

	while ( $exportQuery->have_posts() ) :				

		$exportQuery->the_post(); $record = get_post( get_the_ID() );	

		$is_export_record = apply_filters('wp_all_export_xml_rows', true, $record, $exportOptions);		

		if ( ! $is_export_record ) continue;

		$xmlWriter->startElement($exportOptions['record_xml_tag']);			

			if ($exportOptions['ids']):		

				if ( wp_all_export_is_compatible() and $exportOptions['is_generate_import'] and $exportOptions['import_id']){	
					$postRecord = new PMXI_Post_Record();
					$postRecord->clear();
					$postRecord->getBy(array(
						'post_id' => $record->ID,
						'import_id' => $exportOptions['import_id'],
					));

					if ($postRecord->isEmpty()){
						$postRecord->set(array(
							'post_id' => $record->ID,
							'import_id' => $exportOptions['import_id'],
							'unique_key' => $record->ID,
							'product_key' => $record->ID						
						))->save();
					}
					unset($postRecord);
				}								

				foreach ($exportOptions['ids'] as $ID => $value) {

					if (is_numeric($ID)){ 

						if (empty($exportOptions['cc_name'][$ID]) or empty($exportOptions['cc_type'][$ID])) continue;
						
						$element_name_ns = '';
						$element_name = ( ! empty($exportOptions['cc_name'][$ID]) ) ? preg_replace('/[^a-z0-9_:-]/i', '', $exportOptions['cc_name'][$ID]) : 'untitled_' . $ID;				
						$fieldSnipped = ( ! empty($exportOptions['cc_php'][$ID]) and ! empty($exportOptions['cc_code'][$ID]) ) ? $exportOptions['cc_code'][$ID] : false;

						if (strpos($element_name, ":") !== false)
						{
							$element_name_parts = explode(":", $element_name);
							$element_name_ns = (empty($element_name_parts[0])) ? '' : $element_name_parts[0];
							$element_name = (empty($element_name_parts[1])) ? 'untitled_' . $ID : preg_replace('/[^a-z0-9_-]/i', '', $element_name_parts[1]);							
						}

						switch ($exportOptions['cc_type'][$ID]) {
							case 'id':
								$xmlWriter->putElement($element_name_ns, $element_name, null, apply_filters('pmxe_post_id', pmxe_filter(get_the_ID(), $fieldSnipped), get_the_ID()));			
								break;
							case 'permalink':
								$xmlWriter->putElement($element_name_ns, $element_name, null, apply_filters('pmxe_post_guid', pmxe_filter(get_permalink(), $fieldSnipped), get_the_ID()));
								break;
							case 'post_type':
								$pType = get_post_type();
								if ($pType == 'product_variation') $pType = 'product';
								$xmlWriter->putElement($element_name_ns, $element_name, null, apply_filters('pmxe_post_type', pmxe_filter($pType, $fieldSnipped), get_the_ID()));											
								break;							
							case 'title':								
								$xmlWriter->beginElement($element_name_ns, $element_name, null);
									$xmlWriter->writeData(apply_filters('pmxe_post_title', pmxe_filter($record->post_title, $fieldSnipped) , get_the_ID()));
								$xmlWriter->endElement();								
								break;
							case 'content':
								$xmlWriter->beginElement($element_name_ns, $element_name, null);
									$xmlWriter->writeData(apply_filters('pmxe_post_content', pmxe_filter($record->post_content, $fieldSnipped), get_the_ID()));
								$xmlWriter->endElement();
								break;
							case 'media':
								$xmlWriter->beginElement($element_name_ns, $element_name, null);
									
									$attachment_ids = array();

									$_featured_image = get_post_meta(get_the_ID(), '_thumbnail_id', true); 

									if ( ! empty($_featured_image)) $attachment_ids[] = $_featured_image;

									$_gallery = get_post_meta(get_the_ID(), '_product_image_gallery', true); 

									if (!empty($_gallery)){
										$gallery = explode(',', $_gallery);
										if (!empty($gallery) and is_array($gallery)){
											foreach ($gallery as $aid) {
												if (!in_array($aid, $attachment_ids)) $attachment_ids[] = $aid;
											}
										}
									}

									$attachment_imgs = get_posts( array(
										'post_type' => 'attachment',
										'posts_per_page' => -1,
										'post_parent' => $record->ID,
									) );

									if ( ! empty($attachment_imgs)):

										foreach ($attachment_imgs as $attach) {
											if ( wp_attachment_is_image( $attach->ID ) and ! in_array($attach->ID, $attachment_ids)){
												$attachment_ids[] = $attach->ID;
											}
										}

									endif;

									if ( ! empty($attachment_ids)):

										foreach ($attachment_ids as $attach_id) {

											$attach = get_post($attach_id);

											if ( $attach and ! is_wp_error($attach) and wp_attachment_is_image( $attach->ID ) ) {

												$xmlWriter->startElement('image');

													$val = wp_get_attachment_url( $attach->ID );														

													if (!empty($exportOptions['cc_options'][$ID])){
														switch ($exportOptions['cc_options'][$ID]) {															
															case 'filenames':
																$val = basename(wp_get_attachment_url( $attach->ID ));																
																break;
															case 'filepaths':
																$val = get_attached_file( $attach->ID );													
																break;
															
															default:
																# code...
																break;
														}
													}

													$xmlWriter->writeElement('file', apply_filters('pmxe_attachment_url', $val, get_the_ID(), $attach->ID));													
													$xmlWriter->writeElement('title', apply_filters('pmxe_attachment_title', $attach->post_title, get_the_ID(), $attach->ID));
													$xmlWriter->writeElement('caption', apply_filters('pmxe_attachment_caption', $attach->post_excerpt, get_the_ID(), $attach->ID));
													$xmlWriter->writeElement('description', apply_filters('pmxe_attachment_content', $attach->post_content, get_the_ID(), $attach->ID));													
													$xmlWriter->writeElement('alt', apply_filters('pmxe_attachment_alt', get_post_meta($attach->ID, '_wp_attachment_image_alt', true), get_the_ID(), $attach->ID));

												$xmlWriter->endElement();
											}
										}

									endif;
								$xmlWriter->endElement();
								break;

							case 'date':
								if (!empty($exportOptions['cc_options'][$ID])){ 
									switch ($exportOptions['cc_options'][$ID]) {
										case 'unix':
											$post_date = get_post_time('U', true);
											break;										
										default:
											$post_date = date($exportOptions['cc_options'][$ID], get_post_time('U', true));
											break;
									}									
								}
								else{
									$post_date = date("Ymd", get_post_time('U', true));
								}
								$xmlWriter->putElement($element_name_ns, $element_name, null, apply_filters('pmxe_post_date', pmxe_filter($post_date, $fieldSnipped), get_the_ID()));
								break;

							case 'attachments':
								$xmlWriter->beginElement($element_name_ns, $element_name, null);
									$attachment_imgs = get_posts( array(
										'post_type' => 'attachment',
										'posts_per_page' => -1,
										'post_parent' => $record->ID,
									) );

									if ( ! empty($attachment_imgs)):

										foreach ($attachment_imgs as $attach) {
											if ( ! wp_attachment_is_image( $attach->ID ) ){
												$xmlWriter->startElement('attach');
													$xmlWriter->writeElement('url', apply_filters('pmxe_attachment_url', pmxe_filter(wp_get_attachment_url( $attach->ID ), $fieldSnipped), get_the_ID(), $attach->ID));														
												$xmlWriter->endElement();
											}
										}

									endif;
								$xmlWriter->endElement(); // end attachments
								break;

							case 'parent':
								$xmlWriter->putElement($element_name_ns, $element_name, null, apply_filters('pmxe_post_parent', pmxe_filter($record->post_parent, $fieldSnipped), get_the_ID()));
								break;

							case 'comment_status':
								$xmlWriter->putElement($element_name_ns, $element_name, null, apply_filters('pmxe_comment_status', pmxe_filter($record->comment_status, $fieldSnipped), get_the_ID()));
								break;

							case 'ping_status':
								$xmlWriter->putElement($element_name_ns, $element_name, null, apply_filters('pmxe_ping_status', pmxe_filter($record->ping_status, $fieldSnipped), get_the_ID()));
								break;

							case 'template':
								$xmlWriter->putElement($element_name_ns, $element_name, null, apply_filters('pmxe_post_template', pmxe_filter(get_post_meta($record->ID, '_wp_page_template', true), $fieldSnipped), get_the_ID()));
								break;

							case 'order':
								$xmlWriter->putElement($element_name_ns, $element_name, null, apply_filters('pmxe_menu_order', pmxe_filter($record->menu_order, $fieldSnipped), get_the_ID()));
								break;

							case 'status':
								$xmlWriter->putElement($element_name_ns, $element_name, null, apply_filters('pmxe_post_status', pmxe_filter($record->post_status, $fieldSnipped), get_the_ID()));
								break;

							case 'format':
								$xmlWriter->putElement($element_name_ns, $element_name, null, apply_filters('pmxe_post_format', pmxe_filter(get_post_format($record->ID), $fieldSnipped), get_the_ID()));
								break;

							case 'author':
								$xmlWriter->putElement($element_name_ns, $element_name, null, apply_filters('pmxe_post_author', pmxe_filter($record->post_author, $fieldSnipped), get_the_ID()));
								break;

							case 'slug':
								$xmlWriter->putElement($element_name_ns, $element_name, null, apply_filters('pmxe_post_slug', pmxe_filter($record->post_name, $fieldSnipped), get_the_ID()));
								break;

							case 'excerpt':
								$xmlWriter->beginElement($element_name_ns, $element_name, null);
									$xmlWriter->writeData(apply_filters('pmxe_post_excerpt', pmxe_filter($record->post_excerpt, $fieldSnipped) , get_the_ID()));
								$xmlWriter->endElement();
								break;

							case 'cf':							
								if ( ! empty($exportOptions['cc_value'][$ID]) ){																		
									$cur_meta_values = get_post_meta($record->ID, $exportOptions['cc_value'][$ID]);																				
									if (!empty($cur_meta_values) and is_array($cur_meta_values)){
										foreach ($cur_meta_values as $key => $cur_meta_value) {
											$xmlWriter->beginElement($element_name_ns, $element_name, null);
												$xmlWriter->writeData(apply_filters('pmxe_custom_field', pmxe_filter(maybe_serialize($cur_meta_value), $fieldSnipped), $exportOptions['cc_value'][$ID], get_the_ID()));
											$xmlWriter->endElement();
										}
									}

									if (empty($cur_meta_values)){
										$xmlWriter->beginElement($element_name_ns, $element_name, null);
											$xmlWriter->writeData(apply_filters('pmxe_custom_field', pmxe_filter('', $fieldSnipped), $exportOptions['cc_value'][$ID], get_the_ID()));
										$xmlWriter->endElement();
									}																																																												
								}								
								break;
							case 'acf':							

								if ( ! empty($exportOptions['cc_label'][$ID]) and class_exists( 'acf' ) ){		

									global $acf;

									$field_value = get_field($exportOptions['cc_label'][$ID], $record->ID);

									$field_options = unserialize($exportOptions['cc_options'][$ID]);

									pmxe_export_acf_field_xml($field_value, $exportOptions, $ID, $record->ID, $xmlWriter, $element_name, $element_name_ns, $fieldSnipped, $field_options['group_id']);
																																																																					
								}				
												
								break;
							case 'woo':						
								
								XmlExportWooCommerce::getInstance()->export_xml($xmlWriter, $record, $exportOptions, $ID); 								

								break;
							case 'woo_order':								
								
								XmlExportWooCommerceOrder::getInstance()->export_xml($xmlWriter, $record, $exportOptions, $ID); 								

								break;
							case 'attr':								
								if ( ! empty($exportOptions['cc_value'][$ID])){
									if ($record->post_parent == 0){
										$is_variable_product = false;
										$product_terms = wp_get_post_terms( $record->ID, 'product_type' );
										if( ! empty($product_terms)){
							  				if( ! is_wp_error( $product_terms )){
							  					foreach($product_terms as $term){
							  						if ('variable' == $term->slug){
							  							$is_variable_product = true;
							  							break;
							  						}							  						
							  					}
							  				}
							  			}							  										  		
										$txes_list = get_the_terms($record->ID, $exportOptions['cc_value'][$ID]);
										if ( ! is_wp_error($txes_list)) {								
											$attr_new = array();										
											if (!empty($txes_list)):
												foreach ($txes_list as $t) {
													$attr_new[] = $t->name;												
												}		
												$xmlWriter->beginElement($element_name_ns, $is_variable_product ? $element_name : 'attribute_' . $element_name, null);
													$xmlWriter->writeData(apply_filters('pmxe_woo_attribute', pmxe_filter(implode('|', $attr_new), $fieldSnipped), get_the_ID(), $exportOptions['cc_value'][$ID]));
												$xmlWriter->endElement();		
											endif;									
										}
									}
									else{
										$attribute_pa = apply_filters('pmxe_woo_attribute', get_post_meta($record->ID, 'attribute_' . $exportOptions['cc_value'][$ID], true), get_the_ID(), $exportOptions['cc_value'][$ID]);
										if ( ! empty($attribute_pa)){
											$xmlWriter->beginElement($element_name_ns, 'attribute_' . $element_name, null);
												$xmlWriter->writeData(apply_filters('woo_field', $attribute_pa));
											$xmlWriter->endElement();											
										}
									}
								}
								break;
							case 'cats':
								if ( ! empty($exportOptions['cc_value'][$ID]) )
								{										
									if ($exportOptions['cc_label'][$ID] == 'product_type' and get_post_type() == 'product_variation')
									{ 
										$xmlWriter->writeElement('parent_id', $record->post_parent);
										$xmlWriter->writeElement($element_name, 'variable');										
									}
									else
									{
										$txes_list = get_the_terms($record->ID, $exportOptions['cc_value'][$ID]);
										if ( ! is_wp_error($txes_list)) {								
																					
											$txes_ids = array();										
											$hierarchy_groups = array();
																					
											if ( ! empty($txes_list) ):
												foreach ($txes_list as $t) {																						
													$txes_ids[] = $t->term_id;
												}

												foreach ($txes_list as $t) {
													if ( wp_all_export_check_children_assign($t->term_id, $exportOptions['cc_value'][$ID], $txes_ids) ){
														$ancestors = get_ancestors( $t->term_id, $exportOptions['cc_value'][$ID] );
														if (count($ancestors) > 0){
															$hierarchy_group = array();
															for ( $i = count($ancestors) - 1; $i >= 0; $i-- ) { 															
																$term = get_term_by('id', $ancestors[$i], $exportOptions['cc_value'][$ID]);
																if ($term){
																	$hierarchy_group[] = $term->name;
																}
															}
															$hierarchy_group[]  = $t->name;
															$hierarchy_groups[] = implode(">", $hierarchy_group);
														}
														else{
															$hierarchy_groups[] = $t->name;
														}
													}
												}		

												if ( ! empty($hierarchy_groups) ){

													$xmlWriter->beginElement($element_name_ns, $element_name, null);
														$xmlWriter->writeData(apply_filters('pmxe_post_taxonomy', pmxe_filter(implode('|', $hierarchy_groups), $fieldSnipped), get_the_ID()));
													$xmlWriter->endElement();												
																								
												}
												
											endif;							

										}
									}									
								}
								break;								
							
							case 'sql':

								if ( ! empty($exportOptions['cc_sql'][$ID]) ){									
									$val = $wpdb->get_var( $wpdb->prepare( stripcslashes(str_replace("%%ID%%", "%d", $exportOptions['cc_sql'][$ID])), get_the_ID() ));
									if ( ! empty($exportOptions['cc_php'][$ID]) and !empty($exportOptions['cc_code'][$ID])){
										// if shortcode defined
										if (strpos($exportOptions['cc_code'][$ID], '[') === 0){									
											$val = do_shortcode(str_replace("%%VALUE%%", $val, $exportOptions['cc_code'][$ID]));
										}	
										else{
											$val = eval('return ' . stripcslashes(str_replace("%%VALUE%%", $val, $exportOptions['cc_code'][$ID])) . ';');
										}										
									}
									$xmlWriter->beginElement($element_name_ns, $element_name, null);
										$xmlWriter->writeData(apply_filters('pmxe_sql_field', $val, $element_name, get_the_ID()));
									$xmlWriter->endElement();
								}
								break;							

							default:
								# code...
								break;
						}						
					}					
				}
			endif;		

		$xmlWriter->endElement(); // end post		
		
		if ($preview) break;

		do_action('pmxe_exported_post', $record->ID );

	endwhile;
	
	$xmlWriter->endElement(); // end data
	
	if ($preview) return $xmlWriter->flush(true);//wp_all_export_remove_colons($xmlWriter->flush(true));	

	if ($is_cron)
	{		
		
		$xml = $xmlWriter->flush(true);

		if ( ! $exported_by_cron )
		{
			// The BOM will help some programs like Microsoft Excel read your export file if it includes non-English characters.
			if ($exportOptions['include_bom']) 
			{
				file_put_contents($file_path, chr(0xEF).chr(0xBB).chr(0xBF).substr($xml, 0, (strlen($exportOptions['main_xml_tag']) + 4) * (-1)));
			}
			else
			{
				file_put_contents($file_path, substr($xml, 0, (strlen($exportOptions['main_xml_tag']) + 4) * (-1)));
			}			
		}
		else
		{
			file_put_contents($file_path, substr(substr($xml, 41 + strlen($exportOptions['main_xml_tag'])), 0, (strlen($exportOptions['main_xml_tag']) + 4) * (-1)), FILE_APPEND);
		}
		
		return $file_path;	
		
	}
	else
	{

		if ( empty(PMXE_Plugin::$session->file) ){

			// generate export file name
			$export_file = wp_all_export_generate_export_file( XmlExportEngine::$exportID );			

			// The BOM will help some programs like Microsoft Excel read your export file if it includes non-English characters.
			if ($exportOptions['include_bom']) 
			{
				file_put_contents($export_file, chr(0xEF).chr(0xBB).chr(0xBF).substr($xmlWriter->flush(true), 0, (strlen($exportOptions['main_xml_tag']) + 4) * (-1)));
			}
			else
			{
				file_put_contents($export_file, substr($xmlWriter->flush(true), 0, (strlen($exportOptions['main_xml_tag']) + 4) * (-1)));
			}

			PMXE_Plugin::$session->set('file', $export_file);
			
			PMXE_Plugin::$session->save_data();

		}	
		else
		{
			file_put_contents(PMXE_Plugin::$session->file, substr(substr($xmlWriter->flush(true), 41 + strlen($exportOptions['main_xml_tag'])), 0, (strlen($exportOptions['main_xml_tag']) + 4) * (-1)), FILE_APPEND);
		}

		return true;

	}	

}