<?php

use classes\Classes\Object;
class moipResource extends classes\Classes\Object {

    /**
     * retorna a instância do banco de dados
     * @uses Faz a chamada do contrutor
     * @throws DBException
     * @return retorna um objeto com a instância do banco de dados
     */
    private static $instance = NULL;

    public static function getInstanceOf() {

        $class_name = __CLASS__;
        if (!isset(self::$instance)) {
            self::$instance = new $class_name;
        }

        return self::$instance;
    }

    public function needPayment() {
        echo "pagandoo";
    }

    public function __construct() {
        require_once 'files/config.php';
        $this->token = MOIP_TOKEN;
        $this->key = MOIP_KEY;
        $this->auth = $this->token . ':' . $this->key;
    }

    public function assinatura($itens, $comprador, $redirect_url) {
        $xml = "Razao do Pagamento 12345";
        $xmlreturn = $this->curlPag($xml);
        print_r($xmlreturn);
        die();
        $xml = $this->dadosAssinaturaXML($itens, $comprador, $redirect_url);

        $this->leituraXML($xmlreturn);
    }

    private function leituraXML($xmlreturn) {
        $xml = simplexml_load_string($xmlreturn);
        if (count($xml->error) > 0) {
            'Erro na leitura xml';
            exit;
        }
        $url = PAGSEGURO_URL_CHECKOUT . $xml->code;
        SRedirect($url);
    }

    private function curlPag($xml) {
        $url = 'https://desenvolvedor.moip.com.br/sandbox/ws/alpha/EnviarInstrucao/Unica';
        $header[] = "Authorization: Basic " . base64_encode($this->auth);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_USERPWD, $this->auth);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/4.0");
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $ret = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        return array('resposta' => $ret, 'erro' => $err);
    }

    private function dadosAssinaturaXML($itens, $comprador, $redirect_url) {
        $var = "<?xml version='1.0' encoding='" . CHARSET . "' standalone='yes'?>  
        <checkout><currency>BRL</currency>";
        extract($itens);
        $var .= "<preApproval>
                         <charge>auto</charge>
                         <name>$dsdescricao</name>
                         <details>$dsdetalhes</details>
                         <amountPerPayment>$nrvalor</amountPerPayment>
                         <period>$__lsperiodo</period>";
        if ($__lsperiodo == 'monthly' || $__lsperiodo == 'bimonthly' ||
                $__lsperiodo == 'trimonthly' || $__lsperiodo == 'seminnually') {
            $var .= "<dayOfMonth>$nriniciopagamento</dayOfMonth>";
        } else {
            $var .= "<dayOfYear>$nriniciopagamento</dayOfYear>";
        }
        $hoje = \classes\Classes\timeResource::getDbDate();
        $var.= "<initialDate>$hoje</initialDate>
                <finalDate>" . \classes\Classes\timeResource::addDateTime($hoje, 2, 'year') . "</finalDate>
                <maxAmountPerPeriod>2000.00</maxAmountPerPeriod>
                          <maxTotalAmount>35000.00</maxTotalAmount>
                         <reviewURL>http://sounoobcombr/produto1</reviewURL>
                     ";
        extract($comprador);
        $var.= "</preApproval><sender>  
                <name>$cod_usuario $user_name</name>  
                <email>$email</email>
            </sender>
            
         </checkout>";
        return $var;
    }

}

?>
