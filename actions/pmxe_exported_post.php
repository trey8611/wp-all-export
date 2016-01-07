<?php
function pmxe_pmxe_exported_post( $pid ){
	
	$postRecord = new PMXE_Post_Record();	
	$postRecord->getBy(array(
		'post_id'   => $pid,
		'export_id' => XmlExportEngine::$exportID
	));

	$postRecord->isEmpty() and $postRecord->set(array(
		'post_id'   => $pid,
		'export_id' => XmlExportEngine::$exportID		
	))->insert();
	
}