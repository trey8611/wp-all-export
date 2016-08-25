<?php

class WpaePhpInterpreterErrorHandler
{
    public function handle(){
        $error = error_get_last();

        $wp_uploads = wp_upload_dir();
        if($error){
            $functions = 'in '.$wp_uploads['basedir'] . DIRECTORY_SEPARATOR . WP_ALL_EXPORT_UPLOADS_BASE_DIRECTORY . DIRECTORY_SEPARATOR . 'functions.php:'.$error['line'];
            $error['message'] = str_replace($functions, '', $error['message']);
            $error['message'] = str_replace("\\n",'',$error['message']);
            $errorParts = explode('Stack trace', $error['message']);
            $error['message'] = $errorParts[0];
            $error['message'] .='on line '.$error['line'];
            $error['message'] = str_replace("\n",'',$error['message']);
            $error['message'] = str_replace("Uncaught Error:", '', $error['message']);
            $error['message'] = 'PHP Error: ' . $error['message'];
            $error['message'] = str_replace('  ', ' ', $error['message']);
            echo "[[ERROR]]";
            exit(json_encode(array('error' => '<span class="error">'.$error['message'].'</span>', 'line' => $error['line'], 'title' => __('An error occurred','wp_all_import_plugin'))));
        }
    }
}