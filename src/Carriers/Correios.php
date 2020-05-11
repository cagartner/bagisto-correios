<?php

namespace Cagartner\Correios\Carriers;

use Cagartner\Correios\Helpers\Consult;
use Config;
use Illuminate\Support\Collection;
use Webkul\Checkout\Models\CartShippingRate;
use Webkul\Checkout\Facades\Cart;
use Webkul\Shipping\Carriers\AbstractShipping;

/**
 * Correios Shipping Methods.
 *
 * @author Carlos Gartner <contato@carlosgartner.com.br>
 */
class Correios extends AbstractShipping
{
    /**
     * Correios methods map
     */
    const METHODS_TITLE = [
        'sedex' => 'Sedex',
        'sedex_a_cobrar' => 'Sedex a Cobrar',
        'sedex_10' => 'Sedex 10',
        'sedex_hoje' => 'Sedex Hoje',
        'pac' => 'PAC',
        'pac_contrato' => 'PAC',
        'sedex_contrato' => 'Sedex',
        'esedex' => 'e-Sedex',
    ];

    /**
     * Payment method code
     *
     * @var string
     */
    protected $code = 'cagartner_correios';

    /**
     * Returns rate for correios
     *
     * @return array
     */
    public function calculate()
    {
        if (!$this->isAvailable())
            return false;

        /** @var \Webkul\Checkout\Models\Cart $cart */
        $cart = Cart::getCart();
        $total_weight = $cart->items->sum('total_weight');
        $rates = [];
        $tax_handling = (int) core()->convertPrice($this->getConfigData('tax_handling')) ?: 0;

        $methods = explode(',', $this->getConfigData('methods'));

        if (!$methods) {
            throw new \Exception('Select one shipping method of correios');
        }

        if (!$cart->shipping_address) {
            throw new \Exception('Seu carrinho está com problemas, atualize sua página e tente novamente.');
        }

        foreach ($methods as $method) {
            $data = [
                'tipo' => $method,
                'formato' => $this->getConfigData('package_type'), // opções: `caixa`, `rolo`, `envelope`
                'cep_destino' => $cart->shipping_address->postcode,
                'cep_origem' => core()->getConfigData('sales.shipping.origin.zipcode'),
                'peso' => $total_weight, // Peso em kilos
                'comprimento' => $this->getConfigData('package_length'), // Em centímetros
                'altura' => $this->getConfigData('package_height'), // Em centímetros
                'largura' => $this->getConfigData('package_width'), // Em centímetros
                'diametro' => $this->getConfigData('roll_diameter'), // Em centímetros, no caso de rolo
            ];

            if ($this->getConfigData('cod_company') && $this->getConfigData('password')) {
                $data['empresa'] = $this->getConfigData('cod_company');
                $data['senha'] = $this->getConfigData('password');
            }

            $consult = new Consult();

            /** @var Collection $result */
            try {
                $result = $consult->carriers($data);

                $shippingRate = new CartShippingRate;
                $shippingRate->carrier = 'correios';
                $shippingRate->carrier_title = $this->getConfigData('title');
                $shippingRate->method = 'cagartner_correios_' . Consult::getTipoIndex($result['codigo']);
                $shippingRate->method_title = $this->getMethodTitle($result['codigo']);
                $shippingRate->method_description = $this->getMethodDescription($result['prazo']);
                $shippingRate->price = core()->convertPrice($result['valor']) + $tax_handling;
                $shippingRate->base_price = core()->convertPrice($result['valor']) + $tax_handling;

                array_push($rates, $shippingRate);

            } catch (\Exception $exception) {

            }
        }

        // Adiciona o método de correios padrão se não tiver nenhum retorno da API dos correios
        if (!count($rates)) {
            $shippingRate = new CartShippingRate;
            $shippingRate->carrier = 'correios';
            $shippingRate->carrier_title = $this->getConfigData('title');
            $shippingRate->method = 'cagartner_correios_' . $this->getConfigData('default_method');
            $shippingRate->method_title = self::METHODS_TITLE[$this->getConfigData('default_method')];
            $shippingRate->method_description = $this->getMethodDescription($this->getConfigData('default_estimate'));
            $shippingRate->price = core()->convertPrice($this->getConfigData('default_price')) + $tax_handling;
            $shippingRate->base_price = core()->convertPrice($this->getConfigData('default_price')) + $tax_handling;

            array_push($rates, $shippingRate);
        }

        return $rates;
    }

    /**
     * @param $code
     * @return mixed
     */
    protected function getMethodTitle($code)
    {
        $method = Consult::getTipoIndex($code);
        return self::METHODS_TITLE[$method];
    }

    /**
     * @param $deadline
     * @return mixed
     */
    protected function getMethodDescription($deadline)
    {
        $template = $this->getConfigData('method_template');
        $extra_time = (int)core()->convertPrice($this->getConfigData('extra_time')) ?: 0;
        if (strpos($template, ':dia')) {
            $template = str_replace(':dia', ($deadline + $extra_time), $template);
        }
        return $template;
    }
}