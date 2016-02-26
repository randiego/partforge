<?php

require_once('fpdi/fpdi.php');

class FPDIChecker extends FPDI {
    
    public function Error($msg)
    {
        throw new Exception($msg);        
    }
    
    public function getErrorMessage($filename) {
        try {
            $pagecount = $this->setSourceFile($filename); 
            for ($i = 1; $i <= $pagecount; $i++) { 
                $tplidx = $this->ImportPage($i); 
                $s = $this->getTemplatesize($tplidx); 
            }
            return '';
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
}

?>
