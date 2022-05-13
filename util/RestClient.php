<?php

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;

use function PHPUnit\Framework\isNull;

const AUTHOR_LOGIN_URL = 'auth/login';
const AUTHOR_REFRESH_URL = 'auth/refresh';
const AUTHOR_ME_URL = "users/me";

const REPO_LOGIN_URL = 'api/auth/login';
const REPO_ME_URL = "api/auth/me";

class RestClient
{

    private $client;
    private $baseUrl;
    private $token;
    private $isAuthorKit;
    private $isOnline = false;

    public function __construct($baseUrl = null, $token)
    {
        $client = HttpClient::create();
        $clientOptions = [
            'base_uri' => $baseUrl
        ];
        if(!empty($token)){
            $clientOptions["headers"] = [
                'Authorization' => "Bearer {$token}"
            ];
        }
        $client = $client->withOptions($clientOptions);
        $this->client = $client;
        $this->baseUrl = $baseUrl;
        $this->isAuthorKit = !str_contains($baseUrl, 'http://');
    }

    public function getClient()
    {
        global $CFG;
        $jsonData = JSONManager::getJsonData(getenv("CODETEST_REST_FILE_PATH") ? sys_get_temp_dir().getenv("CODETEST_REST_FILE_PATH") : $CFG->codetestBasePath. '/rest-data.json');
        if($this->isAuthorKit){
            $expireTime = $jsonData['authorkit']['token']['expireDate'];
            $expireRefreshTime = $jsonData['authorkit']['token']['expireRefreshDate'];
            if(time() > $expireTime){
                if(time() > $expireRefreshTime){
                    $this->loginAuthor(
                        $CFG->apiConfigs['authorkit']['user'],
                        $CFG->apiConfigs['authorkit']['pass']
                    );
                }else{
                    $this->refreshAuthorToken();
                }
            }
        }else{
            $expireTime = $jsonData['spring-repo']['token']['expireDate'];
            if(time() > $expireTime) {
                $this->loginRepo(
                    $CFG->apiConfigs['spring-repo']['user'],
                    $CFG->apiConfigs['spring-repo']['pass']
                );
            }
        }
        return $this->client;
    }

    public static function generatePOSTData($exerciseCode, $files){

        $formFields = [
            'json' => json_encode($exerciseCode),
        ];

        if (count($files) != 0) {
            foreach ($files as $key => $value) {
                $formFields['file_field_' . $key] = DataPart::fromPath($value);
            }

            $formData = new FormDataPart($formFields);

            return array(
                'headers' => $formData->getPreparedHeaders()->toArray(),
                'body' => $formData->bodyToIterable(),
            );
        }
        return $formFields;
    }

    public function loginAuthor($user, $pass)
    {
        $this->log("Logging to authorkit...");
        $response = $this->client->request('POST', AUTHOR_LOGIN_URL, [
            'json' => [
                'email' => $user,
                'password' => $pass,
            ]
        ]);
        $responseData = $response->toArray();
        $this->setAuthorData($responseData);
    }

    public function refreshAuthorToken(){
        global $CFG;

        $this->log("Refreshing authorkit token...");

        $jsonData = JSONManager::getJsonData(getenv("CODETEST_REST_FILE_PATH") ? sys_get_temp_dir().getenv("CODETEST_REST_FILE_PATH") : $CFG->codetestBasePath. '/rest-data.json');

        $response = $this->client->request('POST', AUTHOR_REFRESH_URL, [
            'json' => [
                'refreshToken' => $jsonData['authorkit']['token']['refreshToken']
            ]
        ]);
        $responseData = $response->toArray();
        $this->setAuthorData($responseData);
    }

    public function setAuthorData($responseData){
        global $CFG;
        $currentTime = time();

        $responseData['expireDate'] = $currentTime + $responseData['expiresIn'];
        $responseData['expireRefreshDate'] = $currentTime + $responseData['refreshTokenExpiresIn'];

        $dateNow = new DateTime('now');
        $dateNow->setTimezone(new DateTimeZone("Europe/Madrid"));
        $dateNowStr = $dateNow->format('Y-m-d H:i:s [e]');

        $dateExpiresIn = new DateTime();
        $dateExpiresIn->setTimestamp($responseData['expireDate']);
        $dateExpiresIn->setTimezone(new DateTimeZone("Europe/Madrid"));
        $dateExpiresInStr = $dateExpiresIn->format('Y-m-d H:i:s [e]');

        $dateRefreshTokenExpiresIn = new DateTime();
        $dateRefreshTokenExpiresIn->setTimestamp($responseData['expireRefreshDate']);
        $dateRefreshTokenExpiresIn->setTimezone(new DateTimeZone("Europe/Madrid"));
        $dateRefreshTokenExpiresInStr = $dateRefreshTokenExpiresIn->format('Y-m-d H:i:s [e]');

        $responseData['expireDateHuman'] = $dateExpiresInStr;
        $responseData['expireRefreshDateHuman'] = $dateRefreshTokenExpiresInStr;
        $responseData['recievedAt'] = $dateNowStr;
        $this->token = $responseData['accessToken'];

        JSONManager::setKeyValue('[authorkit][token]', $responseData, getenv("CODETEST_REST_FILE_PATH") ? sys_get_temp_dir().getenv("CODETEST_REST_FILE_PATH") : $CFG->codetestBasePath. '/rest-data.json');

        $client = HttpClient::create();
        $client = $client->withOptions([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Authorization' => "Bearer {$this->token}"
            ]
        ]);
        $this->client = $client;
    }

    public function setRepoData($responseData){
        global $CFG;
        $currentTime = time();
        $returnData = [];
        $tokenJwt = $this->parseJwt($responseData);
        $tokenStr = $responseData;

        $returnData['expireDate'] = $tokenJwt['exp'];

        $dateNow = new DateTime('now');
        $dateNow->setTimezone(new DateTimeZone("Europe/Madrid"));
        $dateNowStr = $dateNow->format('Y-m-d H:i:s [e]');

        $dateExpiresIn = new DateTime();
        $dateExpiresIn->setTimestamp($returnData['expireDate']);
        $dateExpiresIn->setTimezone(new DateTimeZone("Europe/Madrid"));
        $dateExpiresInStr = $dateExpiresIn->format('Y-m-d H:i:s [e]');

        $returnData['expireDateHuman'] = $dateExpiresInStr;
        $returnData['recievedAt'] = $dateNowStr;
        $returnData['accessToken'] = $tokenStr;
        $returnData['expiresIn'] = $returnData['expireDate'] - $currentTime;
        $this->token = $tokenStr;

        JSONManager::setKeyValue('[spring-repo][token]', $returnData, getenv("CODETEST_REST_FILE_PATH") ? sys_get_temp_dir().getenv("CODETEST_REST_FILE_PATH") : $CFG->codetestBasePath. '/rest-data.json');

        $client = HttpClient::create();
        $client = $client->withOptions([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Authorization' => "Bearer {$this->token}"
            ]
        ]);
        $this->client = $client;
    }

    public function loginRepo($user, $pass)
    {
        $this->log("Logging to spring-repo...");
        $response = $this->client->request('POST', REPO_LOGIN_URL, [
            'json' => [
                'username' => $user,
                'password' => $pass,
            ]
        ]);
        $responseData = $response->toArray();
        $responseCode = $response->getStatusCode();
        if($responseCode == 401){
            throw new Exception(' ->> Login to central-repository failed, are you sure your user is created and your credentials are correct? <<- ');
        }

        $this->setRepoData($responseData['accessToken']);
    }

    public function setToken($token)
    {
        $this->token = $token;
    }

    public function getToken()
    {
        return $this->token;
    }

    public function checkAuthorkitIsOnline(){
        if($this->isAuthorKit){
            try {
                $response = $this->getClient()->request('GET', AUTHOR_ME_URL, [
                    "timeout" => 10
                ]);
                $responseData = $response->toArray();
                $this->setOnline(true);
            } catch (Exception $ex) {
                $errorMessage = "Authorkit-API is offline";
                $this->log($ex->getMessage());
                $this->log($errorMessage);
                $_SESSION["error"] = $errorMessage;
            }
        }
    }

    public function checkRepoIsOnline(){
        if(!$this->isAuthorKit){
            try {
                $response = $this->getClient()->request('GET', REPO_ME_URL, [
                    "timeout" => 1
                ]);
                $responseData = $response->getContent();
                $this->setOnline(true);
            } catch (Exception $ex) {
                $errorMessage = "SpringBoot repository is offline";
                $this->log($ex->getMessage());
                $this->log($errorMessage);
                $_SESSION["error"] = $errorMessage;
            }
        }
    }

    public function parseJwt($jwt){
        return json_decode(base64_decode(str_replace('_', '/', str_replace('-','+',explode('.', $jwt)[1]))), JSON_PRETTY_PRINT);
    }

    public function setOnline($val)
    {
        $this->isOnline = $val;
    }
    public function getIsOnline()
    {
        return $this->isOnline;
    }

    public function log($message){
        $timeFormat = new DateTime('now', new DateTimeZone("Europe/Madrid"));
        $timeFormat = $timeFormat->format('d/m/Y H:i:s');
        error_log("CT -> [$timeFormat] $message");
    }
}
