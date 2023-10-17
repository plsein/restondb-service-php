<?php

namespace App\Security;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Log;
use LogicException;
use UnexpectedValueException;
use Illuminate\Support\Facades\Http;

/**
 * Class JwtAuth
 */
class JwtAuth {

    /**
     * Property jwtAlgo
     * @var string
     */
    private static $jwtAlgo = 'HS256';

    /**
     * Property jwtKey
     * @var string
     */
    private static $jwtKey = 'jwt_secret-key';

    /**
     * Property jwtPayload
     * @var array
     */
    private static $jwtPayload = [
        'uid' => 0,
        'role' => 'service_user'
    ];

    /**
     * Property serviceToken
     * @var string
     */
    private static $serviceToken = '';

    /**
     * Function config
     * @return void
     */
    private static function config() {
        JwtAuth::$jwtAlgo = env('JWT_ALGO', 'HS256');
        JwtAuth::$jwtKey =  env('JWT_KEY', 'jwt_secret-key');
    }

    /**
     * Function validateCredentials
     * @return bool
     */
    private static function validateCredentials(string $auth_key, string $auth_secret): bool {
        if (empty($auth_key) || empty($auth_secret)) {
            return FALSE;
        }
        if($auth_key === env('AUTH_KEY') && $auth_secret === env('AUTH_SECRET')) {
            return TRUE;
        }
        return FALSE;
    }

    /**
     * Function newServiceToken
     * @return string
     */
    private static function newServiceToken(): string {
        $key = env('AUTH_KEY', '');
        $secret =  env('AUTH_SECRET', '');
        try {
            $resp = Http::post(env('DB_SERVICE_URI', '') . '/api/token', ["key"=>$key, "secret"=>$secret])->throw()->json();
            if (is_array($resp)) {
                JwtAuth::$serviceToken = (
                        array_key_exists('data', $resp) && is_array($resp['data']) && array_key_exists('token', $resp['data'])
                    )? $resp['data']['token'] : JwtAuth::$serviceToken;
            }
        } catch (Exception $e) {
            Log::info('Errors fetching service token');
        }
        return JwtAuth::$serviceToken;
    }

    /**
     * Function trackJwt
     * @return void
     */
    private static function trackJwt() {
        //
    }

    /**
     * Function token
     * @param mixed $payload
     * @param mixed $key
     * @return string
     */
    public static function token(string $auth_key, string $auth_secret, array $payload=[], string $key=''): string {
        if(!JwtAuth::validateCredentials($auth_key, $auth_secret)) {
            return '';
        }
        JwtAuth::config();
        if (!is_array($payload) || count($payload) < 1 || empty(trim(implode('', $payload)))) {
            $payload = JwtAuth::$jwtPayload;
        }
        $payload['iat'] = time();
        $payload['exp'] = $payload['iat'] + (60 * 60 * 12);   // expiry in seconds
        if(!is_string($key) ||strlen($key) < 1) {
            $key = JwtAuth::$jwtKey;
        }
        return JWT::encode($payload, $key, JwtAuth::$jwtAlgo);
        // JwtAuth::trackJwt();
    }

    /**
     * Function validateJwt
     * @param mixed $token
     * @param mixed $key
     * @return array
     */
    public static function validateJwt($token, string $key=''): array {
        JwtAuth::config();
        if(!is_string($token) ||strlen($token) < 1) {
            return [];
        }
        if(!is_string($key) ||strlen($key) < 1) {
            $key = JwtAuth::$jwtKey;
        }
        try {
            return (array) JWT::decode($token, new Key($key, JwtAuth::$jwtAlgo));
        } catch (LogicException $e) {
            Log::info('Errors having to do with environmental setup or malformed JWT Keys');
        } catch (UnexpectedValueException $e) {
            Log::info('Errors having to do with JWT signature and claims');
        }
        return [];
    }

    /**
     * Function getServiceToken
     * @return string
     */
    public static function getServiceToken(): string {
        try {
            $decoded = [];
            if (substr_count(JwtAuth::$serviceToken,'.') == 2) {
                list($headersB64, $payloadB64, $sig) = explode('.', JwtAuth::$serviceToken);
                $decoded = json_decode(base64_decode($payloadB64), true);
            }
            if (!array_key_exists('exp', $decoded) || $decoded['exp'] >= time() - env('PRIOR_TOKEN_EXP')) {
                JwtAuth::newServiceToken();
            }
        } catch (Exception $e) {
            Log::info('Error: Invalid JWT Key');
        }
        return JwtAuth::$serviceToken;
    }

}

/**
 * IMPORTANT:
 * You must specify supported algorithms for your application. See
 * https://tools.ietf.org/html/draft-ietf-jose-json-web-algorithms-40
 * for a list of spec-compliant algorithms.
 */

/*
 NOTE: Decode will return an object instead of an associative array. To get
 an associative array, you will need to cast it as such:
*/
// $decoded_array = (array) $decoded;

/**
 * You can add a leeway to account for when there is a clock skew times between
 * the signing and verifying servers. It is recommended that this leeway should
 * not be bigger than a few minutes.
 * https://github.com/firebase/php-jwt
 * Source: http://self-issued.info/docs/draft-ietf-oauth-json-web-token.html#nbfDef
 */
// JWT::$leeway = 60; // $leeway in seconds
// $decoded = JWT::decode($jwt, new Key($key, 'HS256'));
