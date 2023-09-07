<?php

class GetRequest {
    public function __invoke($url, $headers)
    {
        $curl = curl_init($url);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers());

        $response = curl_exec($curl);
        if(curl_errno($curl)){
            throw new Exception(curl_errno($curl));
        }

        curl_close($curl);
        return $response;
    }
}