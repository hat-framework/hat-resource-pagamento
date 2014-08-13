<?php

/*
 * Para utilizar o recurso deve escolher a função assinatura ou compra. Depois configurar uma url para receber
 * a função retorno.
 * 
 * Mas info: https://pagseguro.uol.com.br/v2/guia-de-integracao/api-de-pagamentos.html
 * 
 * 
 */

use classes\Classes\Object;
class pagseguroResource extends classes\Classes\Object {

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

    private $token = "";
    private $url = "";
    private $urlpag = "";
    private $tabela = "pagamento_operacao";
    private $colunasAtualizar = "(cod_usuario, codproduto, cod, lstipo, codstatus, dtdata)";
    private $colunasGravar = '(cod_usuario,codproduto,dtdata,lstipo,codstatus)';
    private $colunaStatus = 'codstatus';
    private $colunaPerfil = 'cod_perfil';
    private $tabelaProduto = 'pagamento_produto';
    private $colunaCodUsuario = 'cod_usuario';
    private $tabelaUsuario = 'usuario';
    private $colunaDtPagamento = 'user_dtpago';

    public function __construct() {
        require_once 'files/config.php';
        $this->token = PAGSEGURO_TOKEN;
        $this->url = PAGSEGURO_URL;
        $this->checkout = PAGSEGURO_URL_CHECKOUT;
        $this->urlpag = 'https://ws.pagseguro.uol.com.br/v2/checkout/?email=' . EMAIL_PAGSEGURO . '&token=' . PAGSEGURO_TOKEN;
        $this->LoadResource('database', 'db');
    }

    public function compra($itens, $comprador, $codoperacao, $redirect_url) {
        $codoperacao = $this->gravarOperacao($itens, $comprador);
        $xml = $this->dadosCompraXML($itens, $comprador, $redirect_url);
        $http = array('Content-Type: application/xml; charset=ISO-8859-1');
        $xmlreturn = simple_curl($this->urlpag, $xml, array(), $http, false);
        $this->leituraXML($xmlreturn);
    }
   
    public function assinatura($itens, $comprador, $redirect_url) {
        $codoperacao = $this->gravarOperacao($itens, $comprador);
        $xml = $this->dadosAssinaturaXML($itens, $comprador, $codoperacao, $redirect_url);
        $http = array('Content-Type: application/xml; charset=ISO-8859-1');
        $xmlreturn = simple_curl($this->urlpag, $xml, array(), $http, false);
        $this->leituraXML($xmlreturn);
    }

    public function retorno() {
        if (isset($_POST['notificationType']) && $_POST['notificationType'] == 'transaction') {
            $url = 'https://ws.pagseguro.uol.com.br/v2/transactions/notifications/' . $_POST['notificationCode'] . '?email=' . EMAIL_PAGSEGURO . '&token=' . PAGSEGURO_TOKEN;
            $transaction = simple_curl($url);
            if (empty($transaction) || $transaction == 'Unauthorized'){
                $this->setErrorMessage('Erro no recebimento das informações do PagSeguro, por favor contate o administrador do site.');
                return false;
            }            
           $this->leituraRetornoXML($transaction);
            return true;
        }
        else {$this->setErrorMessage('Erro ao salvar sua assinatura em nosso banco de dados, por favor
                contate o administrador do site.');return false;}
    }

    private function leituraRetornoXML($transaction) {
        $xml = simplexml_load_string($transaction);
        if(empty($xml)){$this->setErroMessage('Informações não capturada do PagSeguro, por favor
        entre em contato com o administrador do site.');return false;}
        $explodeDate = explode('T',$xml->date);
        $data = $explodeDate[0];
        $code = $xml->reference;
        $codProduto = $xml->items->item->id;
        $tipo = 'pagseguro';
        $explode = explode(" ", $xml->sender->name);
        $codUsuario = $explode[0];
        $status = $xml->status;
        if($status == 1 || $status == 2){ $this->atualizar($codUsuario, $codProduto, $code, $tipo, $status, $data);
            $this->setSuccessMessage('Transação iniciada com sucesso. Estamos aguardando a liberação do pagamento');}
        elseif($status == 3){$this->atualizar($codUsuario, $codProduto, $code, $tipo, $status, $data);
        $this->liberar($codProduto,$codUsuario);
            $this->setSuccessMessage('Pagamento realizado com sucesso. Assinatura está liberada.');}
        elseif($status == 4){$this->atualizar($codUsuario, $codProduto, $code, $tipo, $status, $data);
        $this->liberar($codProduto,$codUsuario);
             $this->setSuccessMessage('Transação ok!');}
        elseif($status == 5){$this->atualizar($codUsuario, $codProduto, $code, $tipo, $status, $data);
        $this->setAlertMessage('Em disputa.');}
        elseif($status == 6){$this->cancelar($codUsuario);
        $this->setAlertMessage('Pagamento devolvido.');}
        elseif($status == 7){$this->cancelar($codUsuario);
        $this->setAlertMessage('Assinatura cancelada.');}
        elseif($status == ''){$this->setAlertMessage('Nenhuma alteração no status.');}
        else{$this->setAlertMessage('Operação não localizada');}
        return true;
    }
    
    private function atualizar($codUsuario, $codProduto, $code, $tipo, $status, $data){
        $insert = "INSERT INTO $this->tabela $this->colunasAtualizar"
                . " values ('$codUsuario', '$codProduto', '$code', '$tipo', '$status', $data) "
                . "ON DUPLICATE KEY UPDATE $this->colunaStatus = '$status'";
        $this->db->executeQuery("$insert");
        return true;
    }
    
    private function liberar($codProduto,$codUsuario){
        $date = date('Y-m-d H:i:s');
        $select = "SELECT $this->codPerfil FROM $this->tabelaProduto WHERE cod = $codProduto";
        $result = $this->db->executeQuery("$select");
        foreach($result as $item){$perfil = $item['codperfil'];}
        $insert = "UPDATE $this->tabelaUsuario SET $this->colunaPerfil=$perfil , $this->colunaDtPagamento=$date WHERE $this->colunaCodUsuario = $codUsuario";
        $this->db->executeQuery("$insert");
        return true;
    }
    
    private function cancelar($codUsuario){
        $insert = "UPDATE $this->tabelaUsuario SET $this->colunaPerfil=1 WHERE $this->colunaCodUsuario = $codUsuario";
        $this->db->executeQuery("$insert");
        return true;
    }

    private function leituraXML($xmlreturn) {
        $xml = simplexml_load_string($xmlreturn);
        if (count($xml->error) > 0) {
            $this->setErrorMessage('Erro na leitura xml');
                return false;
        }
        $url = PAGSEGURO_URL_CHECKOUT . $xml->code;
        SRedirect($url);
        return true;
    }
    
     private function gravarOperacao($itens, $comprador){
        extract($itens);
        extract($comprador);
        $data = \classes\Classes\timeResource::getDbDate('',$patthern = "Y-m-d");
        $insert = "INSERT INTO $this->tabela $this->colunasGravar "
                . "values ('$cod_usuario','$cod','$data','pagseguro','100')";
        $this->db->executeQuery("$insert");
        $select = "SELECT cod FROM $this->tabela ORDER BY cod DESC LIMIT 1";
        $result = $this->db->executeQuery("$select");
        foreach($result as $item){$codOperacao = $item['cod'];}
        return $codOperacao;
    }

    private function dadosCompraXML($itens, $comprador, $codoperacao, $redirect_url) {
        $var = "<?xml version='1.0' encoding='" . CHARSET . "' standalone='yes'?>  
        <checkout>
         <currency>BRL</currency>  
         <redirectURL>$redirect_url</redirectURL>
            <items>";
        extract($itens);
        $nrvalor = number_format($nrvalor, 2, ".", "");
        $var .= "
                    <item>  
                        <id>$cod</id>  
                        <description>$dsnome</description>  
                        <amount>$nrvalor</amount>  
                        <quantity>1</quantity>  
                    </item>";
        extract($comprador);
        $var .= "</items> 
             <reference>$codoperacao</reference>
            <sender>  
                <name>$cod_usuario $user_name</name>  
                <email>$email</email>
            </sender>
         </checkout>";

        return $var;
    }

    private function dadosAssinaturaXML($itens, $comprador, $codoperacao, $redirect_url) {
        $var = "<?xml version='1.0' encoding='" . CHARSET . "' standalone='yes'?>  
            <checkout><currency>BRL</currency>
                <redirectURL>$redirect_url</redirectURL>";
        extract($itens);
        $var .= "<preApproval>
                    <preApprovalCharge>auto</preApprovalCharge>
                    <name>$dsnome</name>
                    <details>$dsdescricao</details>
                    <amountPerPayment>$nrvalor</amountPerPayment>
                    <period>$__lsperiodo</period>";
        if ($__lsperiodo == 'monthly' || $__lsperiodo == 'bimonthly' ||
                $__lsperiodo == 'trimonthly' || $__lsperiodo == 'seminnually') {
             $dia = \classes\Classes\timeResource::getDbDate('',$patthern = "d");
            $var .= "<dayOfMonth>$dia</dayOfMonth>";
        } else {
            $dia = \classes\Classes\timeResource::getDbDate('',$patthern = "m-d");
            $var .= "<dayOfYear>$dia</dayOfYear>";
        }
        $hoje = str_replace(" ", 'T', \classes\Classes\timeResource::getDbDate());
        $var.= "<initialDate>$hoje.45-03:00</initialDate>
                    <finalDate>" . str_replace(" ", 'T', \classes\Classes\timeResource::addDateTime($hoje, 2, 'year')) . ".45-03:00</finalDate>
                    <maxAmountPerPeriod>$nrvalor</maxAmountPerPeriod>
                    <maxTotalAmount>35000.00</maxTotalAmount>
                    <reviewURL>$redirect_url</reviewURL>
                  </preApproval>
                     ";
        $var .= "
                   <items>
                        <item>  
                            <id>$cod</id>  
                            <description>$dsnome</description>  
                            <amount>$nrvalor</amount>  
                            <quantity>1</quantity>  
                        </item>
                    </items>
                    <reference>$codoperacao</reference>";
        extract($comprador);
        $var.= "<sender>  
                    <name>$cod_usuario $user_name</name>  
                    <email>$email</email>
                </sender>
            </checkout>";
        return $var;
    }

}

/*
 * 
  <details>Toda segunda feira será cobrado o valor de R$150,00 para o seguro do notebook</details>
  Mais detalhes do que é a assinatura em questão, mas cuidado para não abusar de detalhes, esse campo só aceita 255 caracteres, semelhante ao preApprovalName o PagSeguro irá remover os caracteres < (menor que) ou > (maior que).

  <amountPerPayment>150.00</amountPerPayment>
  Valor exato de cada cobrança, como padrão do PagSeguro todos campos referente a valores deverão ser decimal com duas casas decimais separadas por ponto. Não um erro muito comum é as pessoas utilizarem virgulas o que irá resultar erro. Nesse campo você deverá passa um valor entre 1.00 e 1000000.00, qualquer coisa diferente disso poderá ocorrer erro.

  <period>WEEKLY</period>
  Um dos campos que devem ter muita atenção é esse, porque ele irá determinar qual periodicidade que a cobrança será feita, imagina você cobrar seu cliente todo ano ao invés de todo mês, então cuidado. Não preciso nem falar que esse é um campo obrigatório, afinal sem ele não dá para saber qual periodicidade será a recorrência. Nesse campo você poderá usar:

  WEEKLY para toda semana;
  MONTHLY para todo mês;
  BIMONTHLY para a cada dois meses;
  TRIMONTHLY para cada três meses;
  SEMIANNUALLY a cada seis meses.
  YEARLY para cada ano;
  Esse é um campo case insensitive, ou seja não importa se os valores estão maiúsculo ou minusculo, o PagSeguro irá reconhece-los.

  <dayOfWeek>MONDAY</dayOfWeek>
  Utilize esse campo caso no parâmetro preApprovalPeriod esteja configurado como WEEKLY; Os parâmetros que podem ser passados são:

  MONDAY para toda Segunda-Feira;
  TUESDAY para toda Terça-Feira;
  WEDNESDAY para toda Quarta-Feira;
  THURSDAY para toda Quinta-Feira;
  FRIDAY para toda Sexta-Feira;
  SATURDAY para todo Sábado;
  SUNDAY para todo Domingo;

  <dayOfMonth>1</dayOfMonth>
  Utilize esse campo caso no parâmetro preApprovalPeriod esteja configurado como MONTHLY, BIMONTHLY ou TRIMONTHLY. Nesse campo você pode enviar um valor inteiro entre 1 e 28 (Okay sua cobrança nunca poderá ocorrer dias 29, 30 ou 31. Não insista rs )

  <dayOfYear>03-12</dayOfYear>
  Utilize esse campo caso no parâmetro preApprovalPeriod esteja configurado como YEARLY. Nesse campo você deve enviar o dia e mês em que ocorrerá a cobrança, lembrando que no PagSeguro primeiro vem o mês, depois o dia, seguindo o formato MM-dd

  <initialDate>2015-01-17T19:20:30.45-03:00</initialDate>
  Esse é um parâmetro interessante, ele define quando que será o inicio da vigência da assinatura, assim seu sistema poderá enviar todos dados para o PagSeguro e só começar a cobrar tempos depois, será muito útil em promoções do tipo, compre agora e comece a pagar somente depois do carnaval…
  Valores desse parâmetro deve seguir o formato YYYY-MM-DDThh:mm:ss.sTZD clique para ver regras veja detalhes no site W3C, lembrando que a data não deverá ser inferior a data atual, e não poderá ser superior a dois anos da data atual.

  <finalDate>2017-01-17T19:20:30.45-03:00</finalDate>
  Semelhante ao parâmetro preApprovalInitialDate com diferença que este define qual será o final da vigência da assinatura, essa data obviamente deverá não ser inferior a data atual, e não poderá ser superior até dois anos da data atual. Caso o parâmetro preApprovalInitialDate foi definido o preApprovalFinalDate deverá ser superior a data definida em preApprovalInitialDate, e poderá ser superior até dois anos da preApprovalInitialDate

  <maxAmountPerPeriod>200.00</maxAmountPerPeriod>
  Nesse parâmetro deve ser informado qual valor total máximo que o PagSeguro irá cobrar dentro do período. Esse campo deverá ser decimal com duas casas decimais separadas por ponto. Nesse campo você deverá passa um valor entre 1.00 e 2000.00.

  <maxTotalAmount>900.00</maxTotalAmount>
  Nesse parâmetro deve ser informado qual valor total máximo que o PagSeguro irá cobrar enquanto a assinatura for válida. Como todo campo de moeda esse deverá ser decimal com duas casas decimais separadas por ponto. Nesse campo você deverá passa um valor entre 1.00 e 35000.00.

  <reviewURL>http://sounoob.com.br/produto1</reviewURL>
  Na documentação esse parâmetros deveria ser para informar a URL onde o usuário possa ver as regras da assinatura, mas… depois de testes eu vi que esse link aparece em: “Assinatura – alterar”, sendo assim ele possivelmente possa ser utilizado para dar outras opções ao assinante, como alteração de datas e afins…
 */
?>
