<?php

namespace App\Services;

use App\Security\JwtAuth;
use Illuminate\Support\Facades\Http;

class RestService {

  /**
   * Function restApi
   * @param array $data
   * @param string $url
   * @param string $path
   * @param array $headers
   * @return array
   */
  public static function restApi(array $data, string $path='/', array $headers=[]): array {
    $token = JwtAuth::getServiceToken();
    // $baseRestURI = ($url && (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')))? $url : env('DB_SERVICE_URI', '') + '/api';
    // $restUri = $baseRestURI + '/' + $path;
    $headers = array_merge($headers, [
      "Content-Type" => "application/json",
      "Accept" => "application/json",
      "Authorization" => "Bearer " . $token
    ]);
    $resp = Http::DbService()->withHeaders($headers)->withToken($token)->throw()->post($path, $data)->throw()->json();
    return $resp;
  }

}
