<?php

use Symfony\Component\HttpClient\HttpClient;

const AUTHOR_LOGIN_URL = 'auth/login';
const AUTHOR_REFRESH_URL = 'auth/refresh';
const AUTHOR_ME_URL = "users/me";

const REPO_LOGIN_URL = 'api/auth/signin';
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
        if(str_contains($baseUrl, 'authorkit')){
            $this->isAuthorKit = true;
        }
    }

    public function getClient()
    {
        global $CFG;
        $jsonData = JSONManager::getJsonData($CFG->codetestBasePath. '/rest-data.json');
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
            if(time() > $expireTime){
                $this->loginRepo();
            }
        }
        return $this->client;
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

        $jsonData = JSONManager::getJsonData($CFG->codetestBasePath. '/rest-data.json');

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

        JSONManager::setKeyValue('[authorkit][token]', $responseData, $CFG->codetestBasePath. '/rest-data.json');

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
        $tokenJwt = json_decode(base64_decode(str_replace('_', '/', str_replace('-','+',explode('.', $responseData)[1]))), JSON_PRETTY_PRINT);
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
        $this->token = $tokenStr;

        JSONManager::setKeyValue('[spring-repo][token]', $returnData, $CFG->codetestBasePath. '/rest-data.json');

        $client = HttpClient::create();
        $client = $client->withOptions([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Authorization' => "Bearer {$this->token}"
            ]
        ]);
        $this->client = $client;
    }

    public function loginRepo()
    {
        global $CFG;
        $this->log("Logging to spring-repo...");
        $response = $this->client->request('POST', REPO_LOGIN_URL);
        $responseData = $response->getContent();
        $this->setRepoData($responseData);

    }

    public function setToken($token)
    {
        $this->token = $token;
    }

    public function checkAuthorkitIsOnline(){
        if($this->isAuthorKit){
            try {
                $response = $this->getClient()->request('GET', AUTHOR_ME_URL, [
                    "timeout" => 1
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