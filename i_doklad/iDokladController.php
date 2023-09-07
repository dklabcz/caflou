<?php

require_once __DIR__ . '/../libraries/dotenv/vendor/autoload.php';
require_once __DIR__ . '/../service/request_service/PostRequest.php';
require_once __DIR__ . '/../service/request_service/GetRequest.php';

class IDokladController
{
    private $apiToken;

    public function __construct()
    {
        $this->apiToken = $this->generateApiToken();
    }

    public function getInvoices($page)
    {
        $headers = function () {
            return [
                'Authorization: Bearer ' . $this->apiToken
            ];
        };

        $getRequest = new GetRequest();
        $jsonResponse = $getRequest($_ENV['HOST_URL'] . $_ENV['INVOICES_URL'] . '&page=' . $page, $headers);

        $invoices = json_decode($jsonResponse);
        return $invoices;
    }

    public function getCountries()
    {
        $headers = function () {
            return [
                'Authorization: Bearer ' . $this->apiToken
            ];
        };

        $countriesArray = [];
        $page = 0;
        do {
            $page++;
            $getRequest = new GetRequest();
            $jsonResponse = $getRequest($_ENV['HOST_URL'] . $_ENV['COUNTRIES_URL'] . '&page=' . $page, $headers);

            $countries = json_decode($jsonResponse);
            foreach($countries->Data->Items as $item){
                $countriesArray += [
                    $item->Id => $item->Code
                ];
            }
        } while (count($countries->Data->Items) !== 0);

        return $countriesArray;
    }

    public function isLegalEntity($partnerAdress) {
        $regex_sro = '/\b[sS]\s?\.?\s?[rR]\s?\.?\s?[oO]\s?\.?\s?\b/';
        $regex_as = '/\b[aA]\s?\.\s?[sS]\b/';

        if(empty($partnerAdress->NickName)){
            return false;
        }

        if(preg_match($regex_sro, $partnerAdress->NickName) || preg_match($regex_as, $partnerAdress->NickName)){
            return true;
        }

        if(!empty($partnerAdress->Firstname)){
            return false;
        }

        if(!empty($partnerAdress->Surname)){
            return false;
        }

        return false;
    }

    private function generateApiToken()
    {
        $dataFunc = function () {
            return [
                'grant_type' => 'client_credentials',
                'client_id' => $_ENV['CLIENT_ID'],
                'client_secret' => $_ENV['CLIENT_SECRET'],
                'scope' => 'idoklad_api'
            ];
        };

        $postRequest = new PostRequest();
        $jsonResponse = $postRequest($_ENV['AUTH_URL'], $dataFunc);
        $response = json_decode($jsonResponse);
        if (!isset($response->access_token) || empty($response->access_token)) {
            throw new Exception("Access token is not in the response");
        }

        return $response->access_token;
    }
}
