<?php

namespace App\Http\Controllers\Api;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Services\RestService;
use App\Utils\AppUtil;
use App\Security\JwtAuth;
use Illuminate\Http\Client\RequestException;

class RestApiController extends ApiController
{

    /**
     * Create a new REST API controller instance.
     * @return void
     */
    public function __construct(Request $request)
    {
        parent::__construct($request);
        $this->middleware('auth', ['except' => ['index', 'token']]);
    }

    /**
     * Function index
     * @return string
     */
    public function index(): JsonResponse
    {
        $name = $this->request->input('name');
        Log::channel('stack')->info('Parameter: {name}', ['name' => $name]);
        return AppUtil::sendResponse(['value'=>$name]);
    }

    /**
     * Function token
     * @return string
     */
    public function token()
    {
        $key = $this->request->input('key');
        $secret = $this->request->input('secret');
        return AppUtil::sendResponse(['token'=>JwtAuth::token($key, $secret)]);
    }

    /**
     * Function select
     * Sample JSON input: {
     *   "fields": ["sum(z.zone_id) as sum_zone_id", "sum(z.zone_id)/:div as half_sum_zone_id", "cr.region_name as region_name", "csp.provider_name"],
     *   "table": "zones z",
     *   "inner": ["cloud_regions cr on cr.region_id = z.region_id"],
     *   "left": ["cloud_service_providers csp on csp.provider_id = cr.provider_id"],
     *   "where": "z.zone_id > :zoneId and cr.region_name like :regionName",
     *   "group": ["z.zone_id", "cr.region_name", "csp.provider_name"],
     *   "having": "sum(z.zone_id) > :zoneIdSum",
     *   "sort": ["z.zone_name asc"],
     *   "bind": {"div":2, "zoneId":0, "regionName": "%north%", "zoneIdSum": 100},
     *   "limit": 1,
     *   "offset": 0
     * } 
     * @return string
     */
    public function select()
    {
        $params = $this->request->json()->all();
        $result = [];
        try {
            $result = RestService::restApi($params, '/select');
        } catch(Exception $e) {
            Log::info('Error while fetching data', $e->getTrace());
        }
        if (is_array($result) && array_key_exists('data', $result) && array_key_exists('code', $result) && array_key_exists('msg', $result)) {
            return AppUtil::sendResponse($result['data'], $result['code'], $result['msg']);
        }
        return AppUtil::sendResponse($result, 500, ["Error" => "Server Error"]);
    }

    /**
     * Function insertGetId
     * @return string
     */
    public function insertGetId()
    {
        $params = array();
        $params = $this->request->json();
        $table = $params->get('table', '');
        $data = $params->get('data', []);
        $primaryKeyName = $params->get('primaryKeyName', 'id');
        $parameters = ["table"=>$table, "data"=>$data, "primaryKeyName"=>$primaryKeyName];
        $result = [];
        try {
            $result = RestService::restApi($parameters, '/insertGetId');
        } catch(Exception $e) {
            Log::info('Error while fetching data', $e->getTrace());
        }
        if (is_array($result) && array_key_exists('data', $result) && array_key_exists('code', $result) && array_key_exists('msg', $result)) {
            return AppUtil::sendResponse($result['data'], $result['code'], $result['msg']);
        }
        return AppUtil::sendResponse($result, 500, ["Error" => "Server Error"]);
    }

    /**
     * Function insertData
     * Sample JSON input: {
     *   "table": "zones",
     *   "records": [{
     *     "zone_name": "test zone 104",
     *     "region_id": 41
     *   },{
     *     "zone_name": "test zone 105",
     *     "region_id": 41
     *   }]
     * }
     * @return string
     */
    public function insertData()
    {
        $params = array();
        $params = $this->request->json();

        $table = $params->get('table', '');
        $records = $params->get('records', []);
        $ignoreError = $params->get('ignoreError', FALSE);
        $parameters = ["table"=>$table, "records"=>$records, "ignoreError"=>$ignoreError];
        $result = [];
        try {
            $result = RestService::restApi($parameters, '/insert');
        } catch (RequestException $e) {
            return AppUtil::sendResponse([], 400, json_decode($e->response->body(), 1));
        } catch(Exception $e) {
            Log::info('Error while fetching data', $e->getTrace());
        }
        if (is_array($result) && array_key_exists('data', $result) && array_key_exists('code', $result) && array_key_exists('msg', $result)) {
            return AppUtil::sendResponse($result['data'], $result['code'], $result['msg']);
        }
        return AppUtil::sendResponse($result, 500, ["Error" => "Server Error"]);
    }

    /**
     * Function updateData
     * Sample JSON input: {
     *   "objects": [{
     *     "table": "zones",
     *     "where": "zone_id=?",
     *     "bindings": [144],
     *     "data":{
     *       "zone_name": "test zone 106",
     *       "region_id": 41
     *     }
     *   },{
     *     "table": "zones",
     *     "where": "zone_id=?",
     *     "bindings": [145],
     *     "data": {
     *       "zone_name": "test zone 107"
     *     }
     *   }]
     * }
     * @return string
     */
    public function updateData()
    {
        $params = array();
        $params = $this->request->json();
        $objects = $params->get('objects', []);
        $parameters = ["objects"=>$objects];
        $result = [];
        try {
            $result = RestService::restApi($parameters, '/update');
        } catch (RequestException $e) {
            return AppUtil::sendResponse([], 400, json_decode($e->response->body(), 1));
        } catch(Exception $e) {
            Log::info('Error while fetching data', $e->getTrace());
        }
        if (is_array($result) && array_key_exists('data', $result) && array_key_exists('code', $result) && array_key_exists('msg', $result)) {
            return AppUtil::sendResponse($result['data'], $result['code'], $result['msg']);
        }
        return AppUtil::sendResponse($result, 500, ["Error" => "Server Error"]);
    }

    /**
     * Function incrementFields
     * Sample JSON input: {
     *   "table": "zones",
     *   "where": "zone_id=?",
     *   "bindings": [144],
     *   "data": {
     *     "votes": 5,
     *     "balance": 100
     *   },
     *   "other": {
     *     "type": "paid",
     *     "country_code": "US" 
     *   }
     * }
     * @return string
     */
    public function incrementFields()
    {
        $params = array();
        $params = $this->request->json();

        $table = $params->get('table', '');
        $where = $params->get('where', '');
        $data = $params->get('data', []);
        $other = $params->get('other', []);
        $bindings = $params->get('bindings', []);
        $parameters = ["table"=>$table,"where"=>$where,"data"=>$data,"other"=>$other,"bindings"=>$bindings];
        $result = [];
        try {
            $result = RestService::restApi($parameters, '/increment');
        } catch(Exception $e) {
            Log::info('Error while fetching data', $e->getTrace());
        }
        if (is_array($result) && array_key_exists('data', $result) && array_key_exists('code', $result) && array_key_exists('msg', $result)) {
            return AppUtil::sendResponse($result['data'], $result['code'], $result['msg']);
        }
        return AppUtil::sendResponse($result, 500, ["Error" => "Server Error"]);
    }

    /**
     * Function decrementFields
     * @return string
     */
    public function decrementFields()
    {
        $params = array();
        $params = $this->request->json();

        $table = $params->get('table', '');
        $where = $params->get('where', '');
        $data = $params->get('data', []);
        $other = $params->get('other', []);
        $bindings = $params->get('bindings', []);
        $parameters = ["table"=>$table,"where"=>$where,"data"=>$data,"other"=>$other,"bindings"=>$bindings];
        $result = [];
        try {
            $result = RestService::restApi($parameters, '/decrement');
        } catch(Exception $e) {
            Log::info('Error while fetching data', $e->getTrace());
        }
        if (is_array($result) && array_key_exists('data', $result) && array_key_exists('code', $result) && array_key_exists('msg', $result)) {
            return AppUtil::sendResponse($result['data'], $result['code'], $result['msg']);
        }
        return AppUtil::sendResponse($result, 500, ["Error" => "Server Error"]);
    }

    /**
     * Function deleteData
     * Sample JSON input: {
     *   "objects": [{
     *     "table": "zones",
     *     "where": "zone_id=:zoneId",
     *     "bindings": {"zoneId": 142}
     *   },{
     *     "table": "zones",
     *     "where": "zone_id=:zoneId",
     *     "bindings": {"zoneId": 143}
     *   }]
     * }
     * @return string
     */
    public function deleteData()
    {
        $params = array();
        $params = $this->request->json();
        $objects = $params->get('objects', []);
        $parameters = ["objects"=>$objects];
        $result = [];
        try {
            $result = RestService::restApi($parameters, '/delete');
        } catch(Exception $e) {
            Log::info('Error while fetching data', $e->getTrace());
        }
        if (is_array($result) && array_key_exists('data', $result) && array_key_exists('code', $result) && array_key_exists('msg', $result)) {
            return AppUtil::sendResponse($result['data'], $result['code'], $result['msg']);
        }
        return AppUtil::sendResponse($result, 500, ["Error" => "Server Error"]);
    }

}
