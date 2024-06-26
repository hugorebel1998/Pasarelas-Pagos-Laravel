<?php

namespace App\Services;

use Exception;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PaypalService extends HttpCliente
{

    protected $baseUri = 'https://api-m.sandbox.paypal.com/';


    public function crearOrden($payload)
    {
        $auth = $this->oAuth2Token();

        $response = $this->request('POST', 'v2/checkout/orders', [
            'headers' => [
                'Authorization' => 'Bearer ' . $auth['access_token'],
                'Prefer' => 'return=representation',
                // 'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'json' => $payload
        ]);

        return $this->decodificateResponse($response);
    }

    public function confirmarOrden($orden_id, $payload)
    {
        $auth = $this->oAuth2Token();

        $response = $this->request('POST', "v2/checkout/orders/$orden_id/confirm-payment-source", [
            'headers' => [
                'Authorization' => 'Bearer ' . $auth['access_token'],
                'Prefer' => 'return=representation',
                'Content-Type' => 'application/json',
            ],
            'json' => $payload
        ]);

        return $this->decodificateResponse($response);
    }

    public function capturarOrden($orden_id)
    {
        $auth = $this->oAuth2Token();

        $response = $this->request('POST', "v2/checkout/orders/$orden_id/capture", [
            'headers' => [
                'Authorization' => 'Bearer ' . $auth['access_token'],
                'Prefer' => 'return=representation',
                'Content-Type' => 'application/json',
            ],
        ]);

        return $this->decodificateResponse($response);
    }


    private function oAuth2Token()
    {
        try {
            return Cache::remember('paypal_service', 7200, function () {
                $paypal_key_db = confenv('PAYPAL_CLIENTE_KEY');
                $paypal_secret_db = confenv('PAYPAL_CLIENTE_SECRET');

                // Se desencripta valores para trabajarlos
                $paypal_key     =  (new Encrypter(env('KEY_ENCRYPT')))->decrypt($paypal_key_db);
                $paypal_secret  =  (new Encrypter(env('KEY_ENCRYPT')))->decrypt($paypal_secret_db);
                $response = $this->request('POST', 'v1/oauth2/token', [
                    'headers' => [
                        'Authorization' => 'Basic ' . base64_encode($paypal_key . ':' . $paypal_secret),
                        'Content-Type' => 'application/x-www-form-urlencoded',
                    ],
                    'form_params' => [
                        'grant_type' => 'client_credentials',
                    ],
                ]);

                return $this->decodificateResponse($response);
            });
        } catch (Exception $e) {
            Log::error(print_r($e, true));
            return $e;
        }
    }

    private function decodificateResponse($data)
    {
        return json_decode($data->getBody()->getContents(), true);
    }
}
