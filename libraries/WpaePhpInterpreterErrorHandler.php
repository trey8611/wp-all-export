<?php

class WpaePhpInterpreterErrorHandler
{
    public function handle(){

        $error = $this->getLastError();
        if($error){
            $wp_uploads = $this->getUploadsDir();
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
            $this->terminate(json_encode(array('error' => '<span class="error">'.$error['message'].'</span>', 'line' => $error['line'], 'title' => __('An error occurred','wp_all_import_plugin'))));
        }
    }

    /**
     * @return array
     */
    protected function getLastError()
    {
        return error_get_last();
    }

    /**
     * @return mixed
     */
    protected function getUploadsDir()
    {
        return wp_upload_dir();
    }

    /**
     * Hack to be able to test the class in isolation
     *
     * @param $message
     */
    protected function terminate($message)
    {
        exit($message);
    }
}