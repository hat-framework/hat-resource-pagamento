<?php

class pagamentoResource extends \classes\Interfaces\resource{
    

    
    /**
    * retorna a instância do banco de dados
    * @uses Faz a chamada do contrutor
    * @throws DBException
    * @return retorna um objeto com a instância do banco de dados
    */
    private static $instance = NULL;
    public static function getInstanceOf(){
        
        $class_name = __CLASS__;
        if (!isset(self::$instance)) {
            self::$instance = new $class_name;
        }

        return self::$instance;
    }
    
    public function needPayment(){
        echo "pagandoo";
    }
}

?>
