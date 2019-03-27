<?php
/**
 * Consult
 *
 */

namespace Cagartner\Correios\Helpers;

use PhpQuery\PhpQuery as phpQuery;

class Consult
{
    const FRETE_URL = 'http://ws.correios.com.br/calculador/CalcPrecoPrazo.asmx?WSDL';
    const CEP_URL = 'http://www.buscacep.correios.com.br/sistemas/buscacep/resultadoBuscaCepEndereco.cfm';
    const RASTREIO_URL = 'https://www2.correios.com.br/sistemas/rastreamento/resultado_semcontent.cfm';

    private static $methods = array(
        'sedex' => '04014',
        'sedex_a_cobrar' => '40045',
        'sedex_10' => '40215',
        'sedex_hoje' => '40290',
        'pac' => '04510',
        'pac_contrato' => '04669',
        'sedex_contrato' => '04162',
        'esedex' => '81019',
    );

    public static function getMethods()
    {
        return self::$methods;
    }

    /**
     * Verifica se e uma solicitacao de varios $tipos
     *
     * @param $valor string
     * @return boolean
     */
    public static function getTipoIsArray($valor)
    {
        return count(explode(",", $valor)) > 1 ?: false;
    }

    /**
     * @param $valor string
     * @return string
     */
    public static function getTipoIndex($valor)
    {
        return array_search($valor, self::getMethods());
    }

    /**
     * Retorna todos os codigos em uma linha
     *
     * @param $valor string
     * @return string
     */
    public static function getTipoInline($valor)
    {
        $explode = explode(",", $valor);
        $tipos = array();
        foreach ($explode as $value) {
            $tipos[] = self::$methods[$value];
        }
        return implode(",", $tipos);
    }

    /**
     * @param $data
     * @param array $options
     * @return array|mixed
     */
    public function carriers($data, $options = array())
    {
        $endpoint = self::FRETE_URL;
        $tipos = self::getTipoInline($data['tipo']);

        $formatos = array(
            'caixa' => 1,
            'rolo' => 2,
            'envelope' => 3,
        );

        $data['tipo'] = $tipos;
        $data['formato'] = $formatos[$data['formato']];

        $data['cep_destino'] = preg_replace("/[^0-9]/", '', $data['cep_destino']);
        $data['cep_origem'] = preg_replace("/[^0-9]/", '', $data['cep_origem']);

        $options = array_merge(array(
            'trace' => true,
            'exceptions' => true,
            'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP,
            'connection_timeout' => 1000
        ), $options);

        $soap = new \SoapClient($endpoint, $options);
        $params = array(
            'nCdEmpresa' => (isset($data['empresa']) ? $data['empresa'] : ''),
            'sDsSenha' => (isset($data['senha']) ? $data['senha'] : ''),
            'nCdServico' => $data['tipo'],
            'sCepOrigem' => $data['cep_origem'],
            'sCepDestino' => $data['cep_destino'],
            'nVlPeso' => $data['peso'],
            'nCdFormato' => $data['formato'],
            'nVlComprimento' => $data['comprimento'],
            'nVlAltura' => $data['altura'],
            'nVlLargura' => $data['largura'],
            'nVlDiametro' => $data['diametro'],
            'sCdMaoPropria' => (isset($data['mao_propria']) && $data['mao_propria'] ? 'S' : 'N'),
            'nVlValorDeclarado' => (isset($data['valor_declarado']) ? $data['valor_declarado'] : 0),
            'sCdAvisoRecebimento' => (isset($data['aviso_recebimento']) && $data['aviso_recebimento'] ? 'S' : 'N'),
            'sDtCalculo' => date('d/m/Y'),
        );

        $CalcPrecoPrazoData = $soap->CalcPrecoPrazoData($params);
        $resultado = $CalcPrecoPrazoData->CalcPrecoPrazoDataResult->Servicos->cServico;
        if (!is_array($resultado))
            $resultado = array($resultado);
        $data = array();
        foreach ($resultado as $consulta) {
            $consulta = (array)$consulta;
            $data[] = array(
                'codigo' => $consulta['Codigo'],
                'valor' => (float)str_replace(',', '.', $consulta['Valor']),
                'prazo' => (int)str_replace(',', '.', $consulta['PrazoEntrega']),
                'mao_propria' => (float)str_replace(',', '.', $consulta['ValorMaoPropria']),
                'aviso_recebimento' => (float)str_replace(',', '.', $consulta['ValorAvisoRecebimento']),
                'valor_declarado' => (float)str_replace(',', '.', $consulta['ValorValorDeclarado']),
                'entrega_domiciliar' => ($consulta['EntregaDomiciliar'] === 'S' ? true : false),
                'entrega_sabado' => ($consulta['EntregaSabado'] === 'S' ? true : false),
                'erro' => array('codigo' => (real)$consulta['Erro'], 'mensagem' => $consulta['MsgErro']),
            );
        }
        return collect($data);
    }

    /**
     * @param $postcode
     * @return array
     * @throws \Exception
     */
    public function postcode($postcode)
    {
        $query = array(
            'relaxation' => $postcode,
            'tipoCEP' => 'ALL',
            'semelhante' => 'N',
        );
        $curl = new Curl;
        $html = $curl->simple(self::CEP_URL, $query);
        phpQuery::newDocumentHTML($html, $charset = 'ISO-8859-1');
        $pq_form = phpQuery::pq('');

        $result = [];
        if (phpQuery::pq('.tmptabela')) {
            $line = 0;
            foreach (phpQuery::pq('.tmptabela tr') as $pq_div) {
                if ($line) {
                    $item = array();
                    foreach (phpQuery::pq('td', $pq_div) as $pq_td) {
                        $children = $pq_td->childNodes;
                        $innerHTML = '';
                        foreach ($children as $child) {
                            $innerHTML .= $child->ownerDocument->saveXML($child);
                        }
                        $item[] = trim(preg_replace("/&#?[a-z0-9]+;/i", "", $innerHTML));
                    }
                    $data = [];
                    $data['address'] = trim($item[0], " \t\n\r\0\x0B\xc2\xa0");
                    $data['neighbourhood'] = trim($item[1], " \t\n\r\0\x0B\xc2\xa0");
                    $data['postcode'] = trim($item[3], " \t\n\r\0\x0B\xc2\xa0");
                    $citystate = explode('/', trim($item[2], " \t\n\r\0\x0B\xc2\xa0"));
                    $data['city'] = trim($citystate[0], " \t\n\r\0\x0B\xc2\xa0");
                    $data['state'] = trim($citystate[1], " \t\n\r\0\x0B\xc2\xa0");
                    $result = $data;
                }
                $line++;
            }
        }
        return $result;
    }

    /**
     * @param $code
     * @return bool|\Illuminate\Support\Collection
     * @throws \Exception
     */
    public function tracking($code)
    {
        $curl = new Curl;
        $html = $curl->simple(self::RASTREIO_URL, array(
            "Objetos" => $code
        ));

        phpQuery::newDocumentHTML($html, $charset = 'utf-8');
        $tracking = array();
        $c = 0;
        foreach (phpQuery::pq('tr') as $tr) {
            $c++;
            if (count(phpQuery::pq($tr)->find('td')) == 2) {
                list($date, $hour, $place) = explode("<br>", phpQuery::pq($tr)->find('td:eq(0)')->html());
                list($status, $forwarded) = explode("<br>", phpQuery::pq($tr)->find('td:eq(1)')->html());
                $tracking[] = array('date' => trim($date) . " " . trim($hour), 'local' => trim($place), 'status' => trim(strip_tags($status)));
                if (trim($forwarded)) {
                    $tracking[count($tracking) - 1]['encaminhado'] = trim($encaminhado);
                }
            }
        }

        if (!count($tracking))
            return false;

        return collect($tracking);
    }
}