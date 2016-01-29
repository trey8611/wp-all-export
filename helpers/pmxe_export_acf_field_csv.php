<?php

function pmxe_export_acf_field_csv($field_value, $exportOptions, $ID, $recordID, &$article, &$acfs, $element_name = '', $fieldSnipped = '', $group_id = '', $preview = false, $parent_field_name = '', $return_value = false){	

	$put_to_csv = true;	

	$field_name = ($ID) ? $exportOptions['cc_label'][$ID] : $exportOptions['name'];			

	$field_options = ($ID) ? unserialize($exportOptions['cc_options'][$ID]) : $exportOptions;	

	if ( ! empty($field_value) ) {		

		global $acf;

		$field_value = maybe_unserialize($field_value);																					

		$implode_delimiter = ($exportOptions['delimiter'] == ',') ? '|' : ',';	

		// switch ACF field type
		switch ($field_options['type']) {			

			case 'date_time_picker':
			case 'date_picker':

				$field_value = date('Ymd', strtotime($field_value));				

				break;
			
			case 'file':
			case 'image':

				if (is_numeric($field_value)){
					$field_value = wp_get_attachment_url($field_value);
				}
				elseif(is_array($field_value)){
					$field_value = $field_value['url'];
				}

				break;
														
			case 'gallery':											
				
				$v = array();
				foreach ($field_value as $key => $item) {
					$v[] = $item['url'];											
				}
				$field_value = implode($implode_delimiter, $v);

				break;																																										
			case 'location-field':

				$localion_parts = explode("|", $field_value);

				$acfs[] = $element_name . '_address';
				$acfs[] = $element_name . '_lat';
				$acfs[] = $element_name . '_lng';							

				if (!empty($localion_parts)){

					$article[$element_name . '_address'] = $localion_parts[0];					
					
					if (!empty($localion_parts[1])){
						$coordinates = explode(",", $localion_parts[1]);
						if (!empty($coordinates)){
							$article[$element_name . '_lat'] = $coordinates[0];							
							$article[$element_name . '_lng'] = $coordinates[1];							
						}
					}					
				}												

				$put_to_csv = false;

				break;
			case 'paypal_item':										

				$acfs[] = array($element_name . '_item_name', $element_name . '_item_description', $element_name . '_price');

				if ( is_array($field_value) ){
					foreach ($field_value as $key => $value) {
						$article[$element_name . '_' . $key] = $value;												
					}
				}																	

				$put_to_csv = false;

				break;
			case 'google_map':

				$acfs[] = array($element_name . '_address', $element_name . '_lat', $element_name . '_lng');

				$article[$element_name . '_address'] = $field_value['address'];												
				$article[$element_name . '_lat'] = $field_value['lat'];				
				$article[$element_name . '_lng'] = $field_value['lng'];														
									
				$put_to_csv = false;

				break;

			case 'acf_cf7':
			case 'gravity_forms_field':
				
				if ( ! empty($field_options['multiple']) )
					$field_value = implode($implode_delimiter, $field_value);

				break;											

			case 'page_link':

				if (is_array($field_value))
					$field_value = implode($implode_delimiter, $field_value);

				break;
			case 'post_object':													

				if ( ! empty($field_options['multiple'])){
					$v = array();
					foreach ($field_value as $key => $pid) {														

						if (is_numeric($pid)){
							$entry = get_post($pid);
							if ($entry)
							{
								$v[] = $entry->post_name;
							}
						}
						else{
							$v[] = $pid->post_name;
						}
					}
					$field_value = implode($implode_delimiter, $v);
				}
				else{							
					if (is_numeric($field_value)){
						$entry = get_post($field_value);
						if ($entry)
						{
							$field_value = $entry->post_name;
						}
					}
					else{
						$field_value = $field_value->post_name;
					}
				}

				break;				
			case 'relationship':

				$v = array();
				foreach ($field_value as $key => $pid) {
					$entry = get_post($pid);
					if ($entry)
					{
						$v[] = $entry->post_title;
					}
				}
				$field_value = implode($implode_delimiter, $v);

				break;																													
			case 'user':	

				if ( ! empty($field_options['multiple'])){
					$v = array();
					foreach ($field_value as $key => $user) {																												
						if (is_numeric($user)){
							$entry = get_user_by('ID', $user);
							if ($entry)
							{
								$v[] = $entry->user_email;
							}
						}				
						else{
							$v[] = $user['user_email'];
						}										
					}
					$field_value = implode($implode_delimiter, $v);
				}
				else{													
					if (is_numeric($field_value)){
						$entry = get_user_by('ID', $field_value);
						if ($entry)
						{
							$field_value = $entry->user_email;
						}
					}
					else{
						$field_value = $field_value['user_email'];
					}
				}	

				break;									
			case 'taxonomy':

				if ( ! in_array($field_options['field_type'], array('radio', 'select'))){
					$v = array();
					foreach ($field_value as $key => $tid) {
						$entry = get_term($tid , $field_options['taxonomy']);
						if ($entry and !is_wp_error($entry))
						{
							$v[] = $entry->name;
						}
					}
					$field_value = implode($implode_delimiter, $v);
				}
				else{
					$entry = get_term($field_value, $field_options['taxonomy']);
					if ($entry)
					{
						$field_value = $entry->name;
					}
				}

				break;
			case 'select':

				if ( ! empty($field_options['multiple'])){
					$field_value = implode($implode_delimiter, $field_value);
				}

				break;
			case 'checkbox':		
				
				$field_value = implode($implode_delimiter, $field_value);																							

				break;
			
			case 'repeater':																				

				if( have_rows($field_name, $recordID) ){

					// $repeater_element_name = empty($ID) ? $parent_field_name : $element_name;

				 //    if ( ! empty($ID)) 
				 //    	$acfs[$repeater_element_name] = array();

					$rowValues = array();

					$repeater_sub_field_names = array();
 										
				    while( have_rows($field_name, $recordID) ): the_row(); 					

				    	$row = XmlExportACF::acf_get_row();				    	

				    	foreach ($row['field']['sub_fields'] as $sub_field) {				    					    		

				    		if ($acf and version_compare($acf->settings['version'], '5.0.0') >= 0)
				    		{
				    			// get
								$v = $row['value'][ $row['i'] ][ $sub_field['key'] ];//acf_format_value($row['value'][ $row['i'] ][ $sub_field['key'] ], $row['post_id'], $sub_field);				    					    		
				    		}
				    		else
				    		{
				    			$v = get_sub_field($sub_field['name']);
				    		}				    						    		
							
							$sub_field['delimiter'] = $exportOptions['delimiter'];

				    		switch ($sub_field['type']) {
				    			case 'repeater':				    				
									pmxe_export_acf_field_csv($v, $sub_field, false, $recordID, $article, $acfs, str_replace('acf' . $group_id, '', $element_name) . '_' . $sub_field['name'], '', '', false, $element_name);													
				    				break;
				    			case 'google_map':
								case 'paypal_item':		
								case 'location-field':
					    			$rowValues[$sub_field['name']][] = (is_array($v)) ? implode($exportOptions['delimiter'], $v) : $v;	
				    				break;

				    			default:

				    				$sub_field_value = pmxe_export_acf_field_csv($v, $sub_field, false, $recordID, $article, $acfs, str_replace('acf' . $group_id, '', $element_name) . '_' . $sub_field['name'], '', '', false, $element_name, true);													

				    				$rowValues[$sub_field['name']][] = (is_array($sub_field_value)) ? implode($exportOptions['delimiter'], $sub_field_value) : $sub_field_value;	
				    				break;
				    		}

							// if ($sub_field['type'] == 'repeater')
							// {
							// 	$sub_field['delimiter'] = $exportOptions['delimiter'];
							// 	pmxe_export_acf_field_csv($v, $sub_field, false, $recordID, $article, $acfs, str_replace('acf' . $group_id, '', $element_name) . '_' . $sub_field['name'], '', '', false, $element_name);													
							// }		
							// else
							// {
							// 	$rowValues[$sub_field['name']][] = (is_array($v)) ? implode($exportOptions['delimiter'], $v) : $v;	
							// }					

				    	}				    				    					       				    					    	
				        				        				        				        				    
				    endwhile;	

				    foreach ($rowValues as $key => $values) {
				    	$article[$element_name . '_' . $key] =  ($preview) ? trim(preg_replace('~[\r\n]+~', ' ', htmlspecialchars(implode($exportOptions['delimiter'], $values)))) : implode($exportOptions['delimiter'], $values);				    	
				    	if ( ! in_array($element_name . '_' . $key, $repeater_sub_field_names)) $repeater_sub_field_names[] = $element_name . '_' . $key;
				    }					    
					
					if ( ! empty($repeater_sub_field_names)) $acfs[] = $repeater_sub_field_names;

				}							

				$put_to_csv = false;

				break;

			case 'flexible_content':																	

				// check if the flexible content field has rows of data
				if( have_rows($field_name) ):	

				 	// loop through the rows of data
				    while ( have_rows($field_name) ) : the_row();				

						$row = XmlExportACF::acf_get_row();						

						foreach ($row['field']['layouts'] as $layout) {	

							if ($layout['name'] == $row['value'][ $row['i'] ]['acf_fc_layout']){									

						    	foreach ($layout['sub_fields'] as $sub_field) {				    					    		
						    		
						    		if (isset($row['value'][ $row['i'] ][ $sub_field['key'] ])){
							    		// get
										$v = $row['value'][ $row['i'] ][ $sub_field['key'] ]; //acf_format_value($row['value'][ $row['i'] ][ $sub_field['key'] ], $row['post_id'], $sub_field);																				

										$article[$element_name . '_' . $layout['name'] . '_' . $row['i'] . '_' . $sub_field['name']] = $v;
				    					$acfs[] = $element_name . '_' . $layout['name'] . '_' . $row['i'] . '_' . $sub_field['name'];	

										//pmxe_export_acf_field_csv($v, $sub_field, false, $recordID, $article, $acfs, str_replace('acf' . $group_id, '', $element_name) . '_' . $row['value'][ $row['i'] ]['acf_fc_layout'] . '_' . $row['i'] . '_' . $sub_field['name'], '', '', true);													
									}

						    	}						    	
						    }						    					    	
					    }

				    endwhile;

				else :

				    // no layouts found

				endif;					

				$put_to_csv = false;
				
				break;											
			
			default:
				
				break;
		}
			
	}

	if ($return_value) return $field_value;

	if ($put_to_csv){

		switch ($field_options['type']) {			

			case 'repeater':

				global $acf;

				if ($acf->settings['version'] and version_compare($acf->settings['version'], '5.0.0') >= 0){		

					if ( is_numeric($field_options['ID']))
					{
						$acf_fields = get_posts(array('posts_per_page' => -1, 'post_type' => 'acf-field', 'post_parent' => $field_options['ID'], 'post_status' => 'publish', 'orderby' => 'menu_order', 'order' => 'ASC'));				

						if ( ! empty($acf_fields) )
						{
							$repeater_sub_field_names = array();

							foreach ($acf_fields as $field) {			

								$sub_name = $element_name . '_' . $field->post_excerpt;						

								if ( ! in_array($sub_name, $acfs) and ! in_array($sub_name, $repeater_sub_field_names)) $repeater_sub_field_names[] = $sub_name;

							}

							if ( ! empty($repeater_sub_field_names)) $acfs[] = $repeater_sub_field_names;
						}						
					}					
					else
					{
						$acf_fields = acf_local()->fields;
							
						if ( ! empty($acf_fields) )
						{
							$repeater_sub_field_names = array();

							foreach ($acf_fields as $field_key => $field) 
							{														
								if ($field['parent'] == $field_options['key'])
								{																	
									$sub_name = $element_name . '_' . $field['name'];						

									if ( ! in_array($sub_name, $acfs) and ! in_array($sub_name, $repeater_sub_field_names)) $repeater_sub_field_names[] = $sub_name;									
								}
							}

							if ( ! empty($repeater_sub_field_names)) $acfs[] = $repeater_sub_field_names;
						}		
					}
				}
				else
				{
					if ( ! empty($field_options['sub_fields']))
					{
						$repeater_sub_field_names = array();
						
						foreach ($field_options['sub_fields'] as $n => $sub_field)
						{							
							$sub_name = $element_name . '_' . $sub_field['name'];						

							if ( ! in_array($sub_name, $acfs) and ! in_array($sub_name, $repeater_sub_field_names)) $repeater_sub_field_names[] = $sub_name;									
						}
						if ( ! empty($repeater_sub_field_names)) $acfs[] = $repeater_sub_field_names;
					}								
				}
				
			break;

			case 'google_map':
			case 'location-field':				

				$acfs[] = array($element_name . '_address', $element_name . '_lat', $element_name . '_lng');				
				
				break;
			case 'paypal_item':						

				$acfs[] = array($element_name . '_item_name', $element_name . '_item_description', $element_name . '_price');				

				break;			
								
			default:

				$val = apply_filters('pmxe_acf_field', pmxe_filter( ( ! empty($field_value) ) ? maybe_serialize($field_value) : '', $fieldSnipped), $field_name, $recordID);			
				$article[$element_name] = ($preview) ? trim(preg_replace('~[\r\n]+~', ' ', htmlspecialchars($val))) : $val;
				$acfs[] = $element_name;					

			break;

		}						

	}
}

