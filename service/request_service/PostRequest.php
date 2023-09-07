<?php


class PostRequest
{
    public function __invoke($url, $dataFunc)
    {
        $curl = curl_init($url);

        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($dataFunc()));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            throw new Exception(curl_errno($curl));
        }

        curl_close($curl);
        return $response;
    }
}
